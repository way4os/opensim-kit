<?php

declare(strict_types=1);

use GridSurvey\ListReader;

describe('active grids page', function () {
    $list = ListReader::activeGrids(file_get_contents(sample('hb-active-grids.html')));

    test('reads the grids with their site and login address', function () use ($list) {
        expect($list['grids'])->toHaveCount(19)
            ->and($list['grids'][0])->toMatchArray([
                'name' => '3D Worldz',
                'url' => 'https://www.3dworldz.com/',
                'login_uri' => 'www.3dWorldz.com:8002',
                'features' => 'No hypergrid',
            ]);
    });

    test('decodes the names', function () use ($list) {
        expect(array_column($list['grids'], 'name'))->toContain('Darkheart’s Playground');
    });

    test('reads the date of the list (US order) and the total it announces', function () use ($list) {
        expect($list['updated'])->toBe('2026-11-16')->and($list['total'])->toBe(369);
    });
});

describe('monthly stats page', function () {
    $stats = ListReader::stats(file_get_contents(sample('hb-stats-2026-10.html')));

    test('reads the month from the title', function () use ($stats) {
        expect($stats['month'])->toBe('2026-10');
    });

    test('reads regions, users and actives, blank when the grid gave none', function () use ($stats) {
        $by = array_column($stats['grids'], null, 'name');
        expect($by['Neverworld'])->toMatchArray(['regions' => 3546, 'actives' => 1211])
            ->and($by['3D Worldz']['actives'])->toBeNull();
    });
});

describe('joining the sizes to the list', function () {
    test('matches by site address, ignoring scheme, www and trailing slash', function () {
        $grids = [['name' => 'Kurtopia', 'url' => 'http://kurtopia.ddns.net/']];
        $stats = [['name' => 'Kurtopia (new name)', 'url' => 'https://www.kurtopia.ddns.net', 'regions' => 80, 'users' => 1, 'actives' => 263]];

        expect(ListReader::join($grids, $stats)[0]['actives'])->toBe(263);
    });

    test('falls back to the name, and leaves null for a grid without figures', function () {
        $grids = [['name' => 'A Grid', 'url' => 'http://a.example/'], ['name' => 'No Figures', 'url' => 'http://n.example/']];
        $stats = [['name' => 'a grid', 'url' => 'http://other.example/', 'regions' => 5, 'users' => 6, 'actives' => 7]];
        $joined = ListReader::join($grids, $stats);

        expect($joined[0]['regions'])->toBe(5)->and($joined[1]['actives'])->toBeNull();
    });
});
