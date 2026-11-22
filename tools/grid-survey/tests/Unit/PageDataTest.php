<?php

declare(strict_types=1);

use GridSurvey\PageData;
use GridSurvey\Report;

/** A survey result with the given counts by system. */
function result(array $counts, string $month = '2026-12', array $w4os = []): array
{
    $groups = json_decode(file_get_contents(tool() . '/signatures/classes.json'), true)['systems'];
    $grids = [];
    foreach ($counts as $system => $n) {
        for ($i = 0; $i < $n; $i++) {
            $grids[] = ['name' => "$system $i", 'url' => "http://$system-$i.example/", 'actives' => 1, 'system' => $system, 'outcome' => 'classified'];
        }
    }
    foreach ($w4os as $name => $url) {
        // a grid, or its name => address
        $grid = is_array($url) ? $url + ['name' => $name] : ['name' => $name, 'url' => $url];
        $grids[] = $grid + ['actives' => 1, 'system' => 'wordpress_w4os', 'outcome' => 'classified', 'grid_info' => [], 'by' => ['site' => 'wordpress_w4os']];
    }

    return ['source' => ['month' => $month], 'totals' => Report::totals($grids, $groups), 'grids' => $grids];
}

describe('page data update', function () {
    $json = file_get_contents(sample('grids.json'));
    // the grids the page lists now, as a survey that finds them all would give them
    $listed = array_column(json_decode($json, true)['grids'], 'url', 'name');
    // the counts the page has now
    $same = [
        'dreamgrid' => 42, 'modlos' => 1, 'oswebinterface' => 4, 'diva_wifi' => 36, 'jopensim' => 14, 'hosted' => 10, 'custom' => 37,
        'other_cms_ui' => 1, 'custom_site' => 34, 'wordpress' => 31, 'builders' => 13, 'joomla' => 6, 'other_cms' => 2, 'grid_server' => 4,
    ];

    test('changes the counts and nothing else', function () use ($json, $same, $listed) {
        $proposal = PageData::propose($json, result(['wordpress' => 33, 'dreamgrid' => 40] + $same, w4os: $listed));
        $new = json_decode($proposal['json'], true);
        $old = json_decode($json, true);
        $byId = fn (array $d) => array_column(array_merge(...array_column($d['groups'], 'systems')), null, 'id');

        expect($byId($new)['wordpress']['grids'])->toBe(33)
            ->and($byId($new)['dreamgrid']['grids'])->toBe(40)
            ->and($proposal['changes'])->toContain('wordpress: 31 -> 33', 'dreamgrid: 42 -> 40');

        // the dates, the sources, the year a development ended, the order and the list of grids stay as they were
        foreach ($byId($old) as $id => $system) {
            unset($system['grids']);
            $kept = $byId($new)[$id];
            unset($kept['grids']);
            expect($kept)->toBe($system);
        }
        expect($new['grids'])->toBe($old['grids']);
    });

    test('keeps the layout of the file line by line', function () use ($json, $same, $listed) {
        $proposal = PageData::propose($json, result(['wordpress' => 33] + $same, w4os: $listed));
        $before = explode("\n", $json);
        $after = explode("\n", $proposal['json']);
        $changed = array_filter(array_keys($before), fn ($i) => ($before[$i] ?? '') !== ($after[$i] ?? ''));

        // the line of WordPress, and the new line of the survey month
        expect(count($after) - count($before))->toBe(1)
            ->and(count(array_filter($changed, fn ($i) => str_contains($before[$i], '"wordpress"'))))->toBe(1);
    });

    test('records the month of the survey, replacing it the next time', function () use ($json, $same, $listed) {
        $first = PageData::propose($json, result($same, '2026-12', $listed));
        $second = PageData::propose($first['json'], result($same, '2027-01', $listed));

        expect(json_decode($first['json'], true)['surveyed'])->toBe('2026-12')
            ->and(json_decode($second['json'], true)['surveyed'])->toBe('2027-01');
    });

    test('proposes nothing when nothing changed', function () use ($json, $same, $listed) {
        $first = PageData::propose($json, result($same, '2026-12', $listed));

        expect(PageData::propose($first['json'], result($same, '2026-12', $listed))['changes'])->toBe([]);
    });

    test('a system of the page the survey does not know is left as it is, and said', function () use ($json, $same, $listed) {
        $withOther = str_replace('{"id": "grid_server", "grids": 4, "last": true}', '{"id": "grid_server", "grids": 4, "last": true}, {"id": "mystery", "grids": 7}', $json);
        $proposal = PageData::propose($withOther, result($same, w4os: $listed));

        expect($proposal['notes'])->toContain('mystery: not in the survey result, left as it is')
            ->and(json_decode($proposal['json'], true)['groups'][1]['systems'][6]['grids'])->toBe(7);
    });

    test('a system with no grid any more goes to zero', function () use ($json, $same, $listed) {
        $proposal = PageData::propose($json, result(array_diff_key($same, ['grid_server' => 0]), w4os: $listed));

        expect($proposal['changes'])->toContain('grid_server: 4 -> 0');
    });
});

describe('the names of the grids that use w4os', function () {
    $json = file_get_contents(sample('grids.json'));
    $entries = fn (string $json) => json_decode($json, true)['grids'];
    $counts = ['wordpress' => 1];

    test('are written from the result, in alphabetical order, case ignored, one line each as in the file', function () use ($json, $counts, $entries) {
        $proposal = PageData::propose($json, result($counts, w4os: [
            'speculoos' => 'https://speculoos.world/', 'SV3D' => 'https://sv3d.fr/', 'Ankabi' => 'https://ankabi.de/', 'Neverworld' => 'https://neverworldgrid.com/',
        ]));

        expect(array_column($entries($proposal['json']), 'name'))->toBe(['Ankabi', 'Neverworld', 'speculoos', 'SV3D'])
            ->and($proposal['json'])->toContain('    {"name": "Ankabi", "url": "https://ankabi.de/"},');
    });

    test('a grid found is added, one not found any more is removed, and the diff says so', function () use ($json, $counts, $entries) {
        $proposal = PageData::propose($json, result($counts, w4os: ['Ankabi' => 'https://ankabi.de/', 'Ansjela Grid' => 'http://hg.ansjelagrid.com']));

        expect($proposal['changes'])->toContain('names: + Ansjela Grid', 'names: - Kurtopia', 'names: - Six Sides')
            ->and(array_column($entries($proposal['json']), 'name'))->toBe(['Ankabi', 'Ansjela Grid']);
    });

    test('the name is the one of the grid when it has one, and the difference is shown', function () use ($json, $counts, $entries) {
        $proposal = PageData::propose($json, result($counts, w4os: [
            'Speculoos' => ['url' => 'https://speculoos.world/', 'grid_info' => ['gridname' => 'Speculoos World']],
            'Ankabi' => ['url' => 'https://ankabi.de/', 'grid_info' => ['gridname' => '']],
            'Neverworld' => ['url' => 'https://neverworldgrid.com/', 'grid_info' => ['gridname' => 'Neverworld Grid']],
        ]));

        expect(array_column($entries($proposal['json']), 'name'))->toBe(['Ankabi', 'Neverworld Grid', 'Speculoos World'])
            ->and($proposal['notes'])->toContain("name: the list says 'Speculoos', the grid says 'Speculoos World' (the grid's is used)")
            ->and(implode("\n", $proposal['notes']))->not->toContain("'Ankabi'");
    });

    test('a grid already on the page keeps its address when the site is the same', function () use ($json, $counts, $entries) {
        $proposal = PageData::propose($json, result($counts, w4os: ['Ankabi' => 'http://ankabi.de']));

        expect($entries($proposal['json'])[0])->toBe(['name' => 'Ankabi', 'url' => 'https://ankabi.de/']);
    });

    test('a grid whose site answers an error uses the address of the page that carries w4os', function () use ($json, $counts, $entries) {
        $proposal = PageData::propose($json, result($counts, w4os: ['Coconuts' => [
            'url' => 'https://www.coconuts.example/', 'by' => ['site' => 'error'], 'decided_by' => 'grid_info', 'decisive' => ['page' => 'welcome'],
            'grid_info' => ['welcome' => 'https://world.coconuts.example/splash/'],
        ]]));

        expect($entries($proposal['json']))->toBe([['name' => 'Coconuts', 'url' => 'https://world.coconuts.example']]);
    });

    test('an excluded grid never appears', function () use ($json, $counts, $entries) {
        $result = result($counts, w4os: ['Ankabi' => 'https://ankabi.de/']);
        $result['grids'][] = ['name' => 'Halcyon World', 'url' => 'https://halcyon.example/', 'system' => 'wordpress_w4os', 'outcome' => 'excluded', 'actives' => 1];

        expect(array_column($entries(PageData::propose($json, $result)['json']), 'name'))->toBe(['Ankabi']);
    });

    test('a grid that claims w4os through included.json is in the list and marked requested', function () use ($json, $counts, $entries) {
        $result = result($counts, w4os: ['Watta World' => ['url' => 'https://watta.world/', 'requested' => true, 'decided_by' => 'claim']]);
        $proposal = PageData::propose($json, $result);

        expect(array_column($entries($proposal['json']), 'name'))->toBe(['Watta World'])
            ->and($result['totals']['sources']['requested'])->toBe(1);
    });

    test('the hand-kept parts of the file stay', function () use ($json, $counts) {
        $proposal = PageData::propose($json, result($counts, w4os: ['Ankabi' => 'https://ankabi.de/']));

        expect($proposal['json'])->toContain('"updated": "2026-11-18", "source": "https://outworldz.com/Outworldz_installer/Grid/"')
            ->and($proposal['json'])->toContain('"ended": "2025"');
    });
});
