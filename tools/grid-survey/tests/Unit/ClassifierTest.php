<?php

declare(strict_types=1);

// The classification of the pages saved from the real sites of the November 2026 study (tests/fixtures/crawls),
// against the system the study (docs/market/grid-web-systems-2026-11.md) gave them.
describe('saved pages of real grids', function () {
    test('a grid goes to the system of the study', function (string $slug, string $system) {
        expect(classifier()->classify(crawl($slug))['system'])->toBe($system);
    })->with([
        'w4os, flagship' => ['neverworldgrid-com', 'wordpress_w4os'],
        'w4os, on a http site' => ['kurtopia-ddns-net', 'wordpress_w4os'],
        'WordPress without w4os' => ['digiworldz-com', 'wordpress'],
        'DreamGrid bundle' => ['cavegrid-org-8002', 'dreamgrid'],
        'DreamGrid on the Outworldz host' => ['freelife-outworldz-net-8002-wifi', 'dreamgrid'],
        'jOpenSim on Joomla' => ['trianon-world-com', 'jopensim'],
        'Moodle with Modlos' => ['jogrid-net-wi', 'modlos'],
        'osWebInterface' => ['grid-2nd-sanctuary-de-welcomesplashpage-php', 'oswebinterface'],
        'Hosting4Opensim panel' => ['friends-grid-com', 'hosted'],
        'own code with its own sign-up' => ['osgrid-org', 'custom'],
        'framework with its own sign-up' => ['zetaworlds-com', 'other_cms_ui'],
        'Concrete CMS' => ['darkheartsos-com', 'other_cms'],
        'Grav' => ['4creative-net', 'other_cms'],
        'Google Sites' => ['twiztidtimez-com', 'builders'],
        'static page' => ['planet3l-com', 'custom_site'],
    ]);

    test('a grid that forbids crawling is in no system', function () {
        $result = classifier()->classify(crawl('astralia-eu'));

        expect($result['system'])->toBeNull()->and($result['outcome'])->toBe('robots');
    });

    test('the signals say what was seen', function () {
        expect(classifier()->classify(crawl('kurtopia-ddns-net'))['signals'])->toContain('w4os');
    });
});

describe('sites with no known system', function () {
    $crawl = fn (array $pages, string $status = 'ok') => ['status' => $status, 'note' => '', 'pages' => $pages];

    test('a page with a login form is the grid\'s own interface, without one it is a custom website', function () use ($crawl) {
        $login = $crawl([page('home', 'http://a.example/', '<form><input type="password" name="pw"></form>')]);
        $plain = $crawl([page('home', 'http://a.example/', '<h1>Welcome</h1><p>Join us in world</p>')]);

        expect(classifier()->classify($login)['system'])->toBe('custom')
            ->and(classifier()->classify($plain)['system'])->toBe('custom_site');
    });

    test('an error page for the w4os probe is not w4os, even if it repeats the address', function () use ($crawl) {
        $found = $crawl([
            page('home', 'http://a.example/', '<meta name="generator" content="WordPress 7.1.4"><link href="/wp-content/x.css">'),
            page('probe', 'http://a.example/wp-content/plugins/w4os/readme.txt', '<html>Not found: /wp-content/plugins/w4os/readme.txt <link href="/wp-content/plugins/w4os/x.css"></html>', status: 404),
        ]);

        expect(classifier()->classify($found)['system'])->toBe('wordpress');
    });

    test('WordPress is found by its uploads when the page has no generator and no stylesheet of its own', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<html><img src="http://a.example/wp-content/uploads/2025/06/x.png"></html>')]);

        expect(classifier()->classify($found)['system'])->toBe('wordpress');
    });

    test('Laravel is a CMS, not custom code', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<h1>Welcome</h1>', cookies: ['laravel_session'])]);

        expect(classifier()->classify($found)['system'])->toBe('other_cms');
    });

    test('Wix is a commercial web host', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<h1>Welcome</h1><meta name="generator" content="Wix.com Website Builder">')]);

        expect(classifier()->classify($found)['system'])->toBe('builders');
    });

    test('a grid port with no page is the grid service only', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example:8002/', '', headers: ['server' => 'OSWebServer'], status: 404)], 'error');

        expect(classifier()->classify($found)['system'])->toBe('grid_server');
    });

    test('the page of an unconfigured web server is no website', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<title>Apache2 Ubuntu Default Page: It works</title>')]);
        $result = classifier()->classify($found);

        expect($result['system'])->toBeNull()->and($result['outcome'])->toBe('default_page');
    });

    test('a site that did not answer is in no system', function (string $status) use ($crawl) {
        $result = classifier()->classify($crawl([page('home', 'http://a.example/', '', status: 0)], $status));

        expect($result['system'])->toBeNull()->and($result['outcome'])->toBe($status);
    })->with(['unreachable', 'error']);
});
