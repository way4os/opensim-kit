<?php

declare(strict_types=1);

use GridSurvey\Matcher;

function detect(array $pages): array
{
    return (new Matcher(signatures()->technologies()))->detect($pages);
}

describe('webappanalyzer rules on a saved page', function () {
    test('a generator meta gives the technology and its version', function () {
        $found = detect([page('home', 'http://a.example/', '<html><head><meta name="generator" content="WordPress 7.1.4"></head></html>')]);

        expect($found)->toHaveKey('WordPress')->and($found['WordPress']['version'])->toBe('7.1.4');
    });

    test('a cookie gives the technology', function () {
        $found = detect([page('home', 'http://a.example/', '<html></html>', cookies: ['ci_session'])]);

        expect($found)->toHaveKey('CodeIgniter');
    });

    test('a header gives the technology', function () {
        $found = detect([page('home', 'http://a.example/', '<html></html>', headers: ['x-wix-request-id' => 'abc'])]);

        expect($found)->toHaveKey('Wix');
    });

    test('a script source gives the technology', function () {
        $html = '<html><script src="https://a.example/wp-content/themes/x/app.js"></script></html>';

        expect(detect([page('home', 'http://a.example/', $html)]))->toHaveKey('WordPress');
    });

    test('nothing is found on a page with no signal', function () {
        expect(detect([page('home', 'http://a.example/', '<html><body><h1>My grid</h1></body></html>')]))->toBe([]);
    });

    test('what a technology implies is added, what it excludes is removed', function () {
        $rules = [
            'Shop' => ['cats' => [1], 'meta' => ['generator' => '^Shop'], 'implies' => ['Engine\\;confidence:50'], 'excludes' => 'Blog'],
            'Engine' => ['cats' => [1]],
            'Blog' => ['cats' => [1], 'html' => 'blog'],
        ];
        $found = (new Matcher($rules))->detect([page('home', 'http://a.example/', '<meta name="generator" content="Shop 1"> my blog')]);

        expect($found)->toHaveKey('Engine')->and($found['Engine']['confidence'])->toBe(50)->and($found)->not->toHaveKey('Blog');
    });

    test('a confidence adds up to 100 at most, a version template reads the capture', function () {
        $rules = ['Thing' => ['cats' => [1], 'html' => ['abc\\;confidence:60', 'def\\;confidence:60', 'v([\\d.]+)\\;version:\\1']]];
        $found = (new Matcher($rules))->detect([page('home', 'http://a.example/', 'abc def v2.3')]);

        expect($found['Thing'])->toMatchArray(['confidence' => 100, 'version' => '2.3']);
    });

});

describe('our own rules', function () {
    test('w4os is found by its assets', function () {
        $html = '<link href="https://a.example/wp-content/plugins/w4os/v2/public/public.css?ver=2.9.4">';

        expect(detect([page('home', 'http://a.example/', $html)]))->toHaveKey('w4os');
    });

    test('w4os is found by the readme of the plugin', function () {
        $found = detect([page('probe', 'http://a.example/wp-content/plugins/w4os/readme.txt', "=== w4os - OpenSimulator Web Interface ===\nContributors: x")]);

        expect($found)->toHaveKey('w4os');
    });

    test('a not-found page for the probe is not w4os', function () {
        expect(detect([page('probe', 'http://a.example/wp-content/plugins/w4os/readme.txt', '<html><h1>Not found</h1></html>', status: 404)]))->not->toHaveKey('w4os');
    });

    test('Diva Wifi is found when the grid serves it itself', function () {
        $html = '<link rel="stylesheet" href="/wifi/fluid.css"><li><a href="/wifi/user/account/">CREATE ACCOUNT</a></li>';

        expect(detect([page('home', 'http://a.example:8002/wifi', $html)]))->toHaveKey('Diva Wifi');
    });

    test('a link to a Wifi on another host is not a Wifi site', function () {
        $html = '<a href="http://a.example:8002/wifi/user/account/">Sign up</a>';

        expect(detect([page('home', 'http://a.example/', $html)]))->not->toHaveKey('Diva Wifi');
    });
});
