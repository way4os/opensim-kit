<?php

declare(strict_types=1);

use GridSurvey\Crawler;
use GridSurvey\Fetcher;

/** A site made of saved pages: what is not in the list answers 404, and every request is remembered. */
function site(array $pages): Fetcher
{
    return new class ($pages) implements Fetcher {
        /** @var list<string> */
        public array $requests = [];

        public function __construct(private array $pages)
        {
        }

        public function get(string $url): array
        {
            $this->requests[] = $url;
            $page = $this->pages[$url] ?? ['status' => 404, 'body' => 'Not found'];
            if (($page['error'] ?? null) !== null) {
                return ['url' => $url, 'final_url' => $url, 'status' => 0, 'headers' => [], 'cookies' => [], 'body' => '', 'error' => $page['error']];
            }

            return [
                'url' => $url, 'final_url' => $page['final_url'] ?? $url, 'status' => $page['status'] ?? 200,
                'headers' => $page['headers'] ?? [], 'cookies' => [], 'body' => $page['body'] ?? '', 'error' => null,
            ];
        }
    };
}

describe('robots.txt', function () {
    test('a site that forbids crawling is left alone: only robots.txt is read', function () {
        $http = site([
            'http://a.example/robots.txt' => ['body' => "User-agent: *\nDisallow: /\n"],
            'http://a.example/' => ['body' => '<h1>secret</h1>'],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($crawl['status'])->toBe('robots')->and($http->requests)->toBe(['http://a.example/robots.txt']);
    });

    test('a rule for this survey\'s agent applies, one for other robots does not', function () {
        $http = site([
            'http://a.example/robots.txt' => ['body' => "User-agent: GPTBot\nDisallow: /\n\nUser-agent: grid-web-survey\nDisallow: /private\n"],
            'http://a.example/' => ['body' => '<h1>open</h1>'],
        ]);

        expect((new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/'])['status'])->toBe('ok');
    });

    test('a site with no robots.txt (an HTML 404 page) is open', function () {
        $http = site(['http://a.example/' => ['body' => '<h1>open</h1>']]);

        expect((new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/'])['status'])->toBe('ok');
    });
});

describe('pages read', function () {
    test('a site that does not answer is unreachable', function () {
        $http = site(['http://a.example/' => ['error' => 'Connection reset by peer (56)']]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($crawl['status'])->toBe('unreachable')->and($crawl['note'])->toContain('Connection reset');
    });

    test('an HTTP error is an error, the page is kept', function () {
        $http = site(['http://a.example/' => ['status' => 523, 'body' => 'Origin unreachable']]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($crawl['status'])->toBe('error')->and($crawl['note'])->toBe('HTTP 523')->and($crawl['pages'])->toHaveCount(1);
    });

    test('a meta refresh is followed, to a page robots.txt allows', function () {
        $http = site([
            'http://a.example/' => ['body' => '<meta http-equiv="refresh" content="0; url=/welcome/">'],
            'http://a.example/welcome/' => ['body' => '<h1>Welcome</h1>'],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($crawl['pages'][0]['url'])->toBe('http://a.example/welcome/');
    });

    test('the surest registration link of the same site is read, never another site', function () {
        $home = '<a href="/login">Login</a> <a href="http://other.example/register">Register</a> <a href="/join">Join</a> <a href="/signup">Sign up</a>';
        $http = site([
            'http://a.example/' => ['body' => $home],
            'http://a.example/signup' => ['body' => '<form><input type="password"></form>'],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect(array_column($crawl['pages'], 'role'))->toBe(['home', 'register'])
            ->and($crawl['pages'][1]['url'])->toBe('http://a.example/signup')
            ->and($http->requests)->not->toContain('http://other.example/register');
    });

    test('the Site Credits page is read', function () {
        $http = site([
            'http://a.example/' => ['body' => '<a href="/site-credits">Site Credits</a>'],
            'http://a.example/site-credits' => ['body' => 'Hosting4Opensim'],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect(array_column($crawl['pages'], 'role'))->toContain('credits');
    });

    test('a WordPress site gets the two w4os readme probes, from its WordPress folder', function () {
        $http = site([
            'http://a.example/blog/' => ['body' => '<link href="http://a.example/blog/wp-content/themes/x/style.css">'],
        ]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/blog/']);

        expect($http->requests)->toContain(
            'http://a.example/blog/wp-content/plugins/w4os-opensimulator-web-interface/readme.txt',
            'http://a.example/blog/wp-content/plugins/w4os/readme.txt',
        );
    });

    test('a site that is not WordPress is not probed for w4os', function () {
        $http = site(['http://a.example/' => ['body' => '<h1>Hello</h1>']]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect(array_filter($http->requests, fn ($u) => str_contains($u, 'wp-content')))->toBe([]);
    });

    test('the pages a robots.txt forbids are not requested', function () {
        $http = site([
            'http://a.example/robots.txt' => ['body' => "User-agent: *\nDisallow: /register\n"],
            'http://a.example/' => ['body' => '<a href="/register">Register</a>'],
        ]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($http->requests)->not->toContain('http://a.example/register');
    });
});

describe('what the survey found on real sites', function () {
    test('a page that is only a script redirect is followed', function () {
        $http = site([
            'http://a.example/' => ['body' => "<script>\nlocation.href='./main/index.php';\n</script>"],
            'http://a.example/main/index.php' => ['body' => '<h1>Grid</h1>'],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($crawl['pages'][0]['url'])->toBe('http://a.example/main/index.php');
    });

    test('the sign-up on the grid port of the same host is read', function () {
        $http = site([
            'http://a.example/' => ['body' => '<a href="http://a.example:8002/wifi/user/account/">Register</a>'],
            'http://a.example:8002/wifi/user/account/' => ['body' => 'wifi'],
        ]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($http->requests)->toContain('http://a.example:8002/wifi/user/account/');
    });

    test('a wp-content folder of another site is not probed', function () {
        $http = site(['http://a.example:8002/wifi' => ['body' => '<img src="http://other.example/wp-content/uploads/x.png">']]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example:8002/wifi']);

        expect(array_filter($http->requests, fn ($u) => str_contains($u, 'other.example/wp-content/plugins')))->toBe([])
            ->and($http->requests)->toContain('http://a.example:8002/wp-content/plugins/w4os/readme.txt');
    });
});
