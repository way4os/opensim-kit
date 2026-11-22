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

    test('a system the groups do not know is reported, not dropped', function () use ($grids, $groups) {
        $grids[] = ['name' => 'E', 'url' => 'http://e/', 'actives' => 1, 'system' => 'mystery', 'outcome' => 'classified'];

        expect(Report::totals($grids, $groups)['unknown_systems'])->toBe(['mystery']);
    });
});
