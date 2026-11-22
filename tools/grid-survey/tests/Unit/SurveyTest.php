<?php

declare(strict_types=1);

use GridSurvey\Crawler;
use GridSurvey\Report;
use GridSurvey\Survey;

$excluded = [['name' => 'Halcyon World', 'host' => 'halcyon.example', 'reason' => 'Runs Halcyon']];
$groups = json_decode(file_get_contents(tool() . '/signatures/classes.json'), true)['systems'];

describe('survey', function () use ($excluded, $groups) {
    test('an excluded grid is listed with its reason, not crawled and not counted', function () use ($excluded, $groups) {
        $http = site(['http://ok.example/' => ['body' => '<h1>hi</h1>']]);
        $survey = new Survey(new Crawler($http), classifier(), null, $excluded);
        $grids = $survey->run([
            ['name' => 'Halcyon World', 'url' => 'https://www.halcyon.example/', 'actives' => 10],
            ['name' => 'Ok', 'url' => 'http://ok.example/', 'actives' => 5],
        ]);
        $totals = Report::totals($grids, $groups);

        expect($grids[0])->toMatchArray(['outcome' => 'excluded', 'system' => null, 'note' => 'Runs Halcyon'])
            ->and($http->requests)->not->toContain('https://www.halcyon.example/robots.txt')
            ->and($totals['reachable'])->toBe(1)->and($totals['listed'])->toBe(2);
    });

    test('a site that did not answer is tried a second time', function () {
        $http = site(['http://down.example/' => ['error' => 'timeout']]);
        (new Survey(new Crawler($http), classifier()))->run([['name' => 'Down', 'url' => 'http://down.example/']]);

        expect(array_count_values($http->requests)['http://down.example/'])->toBe(2);
    });

    test('offline, the saved pages are classified and nothing is fetched', function () {
        $http = site([]);
        $store = new GridSurvey\Store(sample('crawls'));
        $grids = (new Survey(new Crawler($http), classifier(), $store))->run(
            [['name' => 'Kurtopia', 'url' => 'http://kurtopia.ddns.net/']],
            offline: true,
        );

        expect($grids[0]['system'])->toBe('wordpress_w4os')->and($http->requests)->toBe([]);
    });
});

describe('a grid that claims w4os', function () {
    test('is w4os whatever the tool sees, decided by the claim, and counted as requested', function () {
        $http = site(['http://w.example/' => ['body' => '<h1>A custom site</h1>']]);
        $grids = (new Survey(new Crawler($http), classifier()))->run([
            ['name' => 'W', 'url' => 'http://w.example/', 'requested' => true, 'w4os_claim' => 'its owner, 2026-11-22'],
        ]);
        $totals = Report::totals($grids, json_decode(file_get_contents(tool() . '/signatures/classes.json'), true)['systems']);

        expect($grids[0])->toMatchArray(['system' => 'wordpress_w4os', 'decided_by' => 'claim', 'w4os_signal' => 'claimed'])
            ->and($grids[0]['decisive'])->toMatchArray(['page' => 'included.json', 'tech' => 'w4os', 'evidence' => 'its owner, 2026-11-22'])
            ->and($grids[0]['by']['site'])->toBe('custom_site')
            ->and($totals['groups']['interface']['wordpress_w4os']['grids'])->toBe(1)->and($totals['sources']['requested'])->toBe(1);
    });

    test('an excluded grid stays out even when it claims w4os', function () {
        $excluded = [['name' => 'W', 'host' => 'w.example', 'reason' => 'asked', 'source' => 's', 'date' => 'd']];
        $grids = (new Survey(new Crawler(site([])), classifier(), null, $excluded))->run([['name' => 'W', 'url' => 'http://w.example/', 'w4os_claim' => 'x']]);

        expect($grids[0]['outcome'])->toBe('excluded')->and($grids[0]['system'])->toBeNull();
    });
});

describe('kept pages', function () {
    test('a saved crawl comes back as it was, with the grid info it holds', function () {
        $store = new GridSurvey\Store(sys_get_temp_dir() . '/grid-survey-store-' . uniqid());
        $grid = ['name' => 'A', 'url' => 'http://a.example/'];
        $crawl = ['status' => 'ok', 'note' => '', 'pages' => [page('home', 'http://a.example/', '<h1>A</h1>')],
            'grid' => ['status' => 'ok', 'note' => '', 'info' => ['register' => 'http://a.example/wifi'], 'pages' => []]];
        $store->save($grid, $crawl);
        $back = $store->load($grid);

        expect($back['grid'])->toBe($crawl['grid'])->and($back['listed'])->toBe($grid)->and($back['pages'][0]['body'])->toBe('<h1>A</h1>');
    });

    test('nothing is kept unless a folder is given', function () {
        $http = site(['http://a.example/' => ['body' => '<h1>A</h1>']]);
        $before = glob(sys_get_temp_dir() . '/grid-survey-*');
        (new Survey(new Crawler($http), classifier()))->run([['name' => 'A', 'url' => 'http://a.example/']]);

        expect(glob(sys_get_temp_dir() . '/grid-survey-*'))->toBe($before);
    });
});

describe('totals', function () use ($groups) {
    $grids = [
        ['name' => 'A', 'url' => 'http://a/', 'actives' => 100, 'system' => 'wordpress_w4os', 'outcome' => 'classified'],
        ['name' => 'B', 'url' => 'http://b/', 'actives' => 300, 'system' => 'wordpress', 'outcome' => 'classified'],
        ['name' => 'C', 'url' => 'http://c/', 'actives' => null, 'system' => 'wordpress', 'outcome' => 'classified'],
        ['name' => 'D', 'url' => 'http://d/', 'actives' => 50, 'system' => null, 'outcome' => 'unreachable'],
    ];

    test('count the grids with a system, by system and group', function () use ($grids, $groups) {
        $totals = Report::totals($grids, $groups);

        expect($totals['reachable'])->toBe(3)
            ->and($totals['groups']['interface']['wordpress_w4os']['grids'])->toBe(1)
            ->and($totals['groups']['none']['wordpress']['grids'])->toBe(2)
            ->and($totals['outcomes'])->toBe(['unreachable' => 1]);
    });

    test('weigh by active users, a grid with no figure weighs zero', function () use ($grids, $groups) {
        $totals = Report::totals($grids, $groups);

        expect($totals['actives'])->toBe(400)
            ->and($totals['groups']['none']['wordpress']['share_actives'])->toBe(75.0)
            ->and($totals['groups']['none']['wordpress']['share_grids'])->toBe(66.7);
    });

    test('counts which side decided, on which page, and the w4os that only a published page shows', function () use ($groups) {
        $grids = [
            ['name' => 'A', 'url' => 'http://a/', 'actives' => 1, 'system' => 'dreamgrid', 'outcome' => 'classified', 'decided_by' => 'grid_info', 'decisive' => ['page' => 'login'], 'by' => ['site' => 'builders']],
            ['name' => 'B', 'url' => 'http://b/', 'actives' => 1, 'system' => 'wordpress_w4os', 'outcome' => 'classified', 'decided_by' => 'grid_info', 'decisive' => ['page' => 'welcome'], 'by' => ['site' => 'custom_site']],
            ['name' => 'C', 'url' => 'http://c/', 'actives' => 1, 'system' => 'wordpress_w4os', 'outcome' => 'classified', 'decided_by' => 'both', 'decisive' => ['page' => 'welcome'], 'by' => ['site' => 'wordpress_w4os']],
            ['name' => 'D', 'url' => 'http://d/', 'actives' => 1, 'system' => 'wordpress', 'outcome' => 'classified', 'decided_by' => 'site', 'decisive' => null, 'by' => ['site' => 'wordpress']],
        ];
        $totals = Report::totals($grids, $groups);

        expect($totals['decided_by'])->toBe(['site' => 1, 'grid_info' => 2, 'both' => 1, 'claim' => 0])
            ->and($totals['grid_info_pages'])->toBe(['login' => 1, 'welcome' => 1])
            ->and($totals['w4os_only_from_grid_pages'])->toBe(1);
    });

    test('a system the groups do not know is reported, not dropped', function () use ($grids, $groups) {
        $grids[] = ['name' => 'E', 'url' => 'http://e/', 'actives' => 1, 'system' => 'mystery', 'outcome' => 'classified'];

        expect(Report::totals($grids, $groups)['unknown_systems'])->toBe(['mystery']);
    });
});
