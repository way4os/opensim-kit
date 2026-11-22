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

    test('with --probe-w4os, a WordPress site gets the two w4os readme probes, from its WordPress folder', function () {
        $http = site([
            'http://a.example/blog/' => ['body' => '<link href="http://a.example/blog/wp-content/themes/x/style.css">'],
        ]);
        (new Crawler($http, probeW4os: true))->crawl(['name' => 'A', 'url' => 'http://a.example/blog/']);

        expect($http->requests)->toContain(
            'http://a.example/blog/wp-content/plugins/w4os-opensimulator-web-interface/readme.txt',
            'http://a.example/blog/wp-content/plugins/w4os/readme.txt',
        );
    });

    test('no address is guessed by default: a WordPress site is not probed for w4os', function () {
        $http = site(['http://a.example/' => ['body' => '<link href="http://a.example/wp-content/themes/x/style.css">']]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect(array_filter($http->requests, fn ($u) => str_contains($u, 'plugins/w4os')))->toBe([]);
    });

    test('a site that is not WordPress is not probed for w4os', function () {
        $http = site(['http://a.example/' => ['body' => '<h1>Hello</h1>']]);
        (new Crawler($http, probeW4os: true))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

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
        (new Crawler($http, probeW4os: true))->crawl(['name' => 'A', 'url' => 'http://a.example:8002/wifi']);

        expect(array_filter($http->requests, fn ($u) => str_contains($u, 'other.example/wp-content/plugins')))->toBe([])
            ->and($http->requests)->toContain('http://a.example:8002/wp-content/plugins/w4os/readme.txt');
    });
});

describe('the grid itself', function () {
    $info = '<gridinfo><login>http://a.example:8002/</login><gridname>A Grid</gridname><register>http://a.example:8002/wifi/user/account/</register>'
        . '<welcome>http://a.example:8002/</welcome><password>http://a.example:8002/wifi/forgotpassword</password>'
        . '<economy>http://a.example/helpers/</economy><about>http://a.example/</about></gridinfo>';

    test('reads the grid info of the login address and the pages it announces', function () use ($info) {
        $http = site([
            'http://a.example/' => ['body' => '<h1>A</h1>'],
            'http://a.example:8002/get_grid_info' => ['body' => $info],
            'http://a.example:8002/wifi/user/account/' => ['body' => 'wifi'],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);

        expect($crawl['grid']['status'])->toBe('ok')
            ->and($crawl['grid']['info'])->toMatchArray(['gridname' => 'A Grid', 'register' => 'http://a.example:8002/wifi/user/account/'])
            ->and(array_column($crawl['grid']['pages'], 'key'))->toBe(['login', 'register', 'password']);
    });

    test('the services of the viewer (economy) and the page already read are not requested', function () use ($info) {
        $http = site(['http://a.example/' => ['body' => '<h1>A</h1>'], 'http://a.example:8002/get_grid_info' => ['body' => $info]]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);

        expect($http->requests)->not->toContain('http://a.example/helpers/')
            ->and(array_count_values($http->requests)['http://a.example/'])->toBe(1);
    });

    test('https is tried when http does not answer, nothing else is guessed', function () use ($info) {
        $http = site([
            'http://a.example/' => ['body' => '<h1>A</h1>'],
            'http://a.example:8002/get_grid_info' => ['error' => 'refused'],
            'https://a.example:8002/get_grid_info' => ['body' => $info],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);

        expect($crawl['grid']['status'])->toBe('ok')
            ->and(array_filter($http->requests, fn ($u) => str_contains($u, 'b.example')))->toBe([]);
    });

    test('a grid with no login address is read by its site only', function () {
        $http = site(['http://a.example/' => ['body' => '<h1>A</h1>']]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/']);

        expect($crawl['grid']['status'])->toBe('none')
            ->and(array_filter($http->requests, fn ($u) => str_contains($u, 'get_grid_info')))->toBe([]);
    });

    test('a page of the grid info that robots.txt forbids is not requested', function () use ($info) {
        $http = site([
            'http://a.example/' => ['body' => '<h1>A</h1>'],
            'http://a.example:8002/robots.txt' => ['body' => "User-agent: *\nDisallow: /wifi\n"],
            'http://a.example:8002/get_grid_info' => ['body' => $info],
        ]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);

        expect($http->requests)->not->toContain('http://a.example:8002/wifi/user/account/');
    });
});

describe('the login page of the grid', function () {
    $info = '<gridinfo><login>http://a.example:8002/</login><gridname>A</gridname><register>http://a.example:8002/wifi/user/account/</register></gridinfo>';

    test('the root of the login address is read once, after its robots.txt, even when it is not in the announced pages', function () use ($info) {
        $http = site([
            'http://a.example/' => ['body' => '<h1>A</h1>'],
            'http://a.example:8002/get_grid_info' => ['body' => $info],
            'http://a.example:8002/' => ['body' => file_get_contents(sample('pages/dracsoom-login.html'))],
        ]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);

        expect(array_column($crawl['grid']['pages'], 'key'))->toContain('login')
            ->and(array_count_values($http->requests)['http://a.example:8002/'])->toBe(1)
            ->and(array_search('http://a.example:8002/robots.txt', $http->requests))->toBeLessThan(array_search('http://a.example:8002/', $http->requests));
    });

    test('no error when it answers something else or nothing', function () use ($info) {
        $http = site(['http://a.example/' => ['body' => '<h1>A</h1>'], 'http://a.example:8002/get_grid_info' => ['body' => $info]]);
        $crawl = (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);

        expect($crawl['grid']['status'])->toBe('ok');
    });

    test('it is not read when the robots.txt of the grid forbids it', function () use ($info) {
        $http = site([
            'http://a.example/' => ['body' => '<h1>A</h1>'],
            'http://a.example:8002/robots.txt' => ['body' => "User-agent: *\nDisallow: /\n"],
            'http://a.example:8002/get_grid_info' => ['body' => $info],
        ]);
        (new Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);

        expect($http->requests)->not->toContain('http://a.example:8002/');
    });
});
