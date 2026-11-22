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
        $grids[] = ['name' => $name, 'url' => $url, 'actives' => 1, 'system' => 'wordpress_w4os', 'outcome' => 'classified'];
    }

    return ['source' => ['month' => $month], 'totals' => Report::totals($grids, $groups), 'grids' => $grids];
}

describe('page data update', function () {
    $json = file_get_contents(sample('grids.json'));
    // the counts the page has now
    $same = [
        'dreamgrid' => 42, 'modlos' => 1, 'oswebinterface' => 4, 'diva_wifi' => 36, 'jopensim' => 14, 'hosted' => 10, 'custom' => 37,
        'other_cms_ui' => 1, 'custom_site' => 34, 'wordpress' => 31, 'builders' => 13, 'joomla' => 6, 'other_cms' => 2, 'grid_server' => 4,
    ];

    test('changes the counts and nothing else', function () use ($json, $same) {
        $proposal = PageData::propose($json, result(['wordpress' => 33, 'dreamgrid' => 40] + $same));
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

    test('keeps the layout of the file line by line', function () use ($json, $same) {
        $proposal = PageData::propose($json, result(['wordpress' => 33] + $same));
        $before = explode("\n", $json);
        $after = explode("\n", $proposal['json']);
        $changed = array_filter(array_keys($before), fn ($i) => ($before[$i] ?? '') !== ($after[$i] ?? ''));

        // the line of WordPress, and the new line of the survey month
        expect(count($after) - count($before))->toBe(1)
            ->and(count(array_filter($changed, fn ($i) => str_contains($before[$i], '"wordpress"'))))->toBe(1);
    });

    test('records the month of the survey, replacing it the next time', function () use ($json, $same) {
        $first = PageData::propose($json, result($same, '2026-12'));
        $second = PageData::propose($first['json'], result($same, '2027-01'));

        expect(json_decode($first['json'], true)['surveyed'])->toBe('2026-12')
            ->and(json_decode($second['json'], true)['surveyed'])->toBe('2027-01');
    });

    test('proposes nothing when nothing changed', function () use ($json, $same) {
        $first = PageData::propose($json, result($same, '2026-12'));

        expect(PageData::propose($first['json'], result($same, '2026-12'))['changes'])->toBe([]);
    });

    test('a system of the page the survey does not know is left as it is, and said', function () use ($json, $same) {
        $withOther = str_replace('{"id": "grid_server", "grids": 4, "last": true}', '{"id": "grid_server", "grids": 4, "last": true}, {"id": "mystery", "grids": 7}', $json);
        $proposal = PageData::propose($withOther, result($same));

        expect($proposal['notes'])->toContain('mystery: not in the survey result, left as it is')
            ->and(json_decode($proposal['json'], true)['groups'][1]['systems'][6]['grids'])->toBe(7);
    });

    test('a system with no grid any more goes to zero', function () use ($json, $same) {
        $proposal = PageData::propose($json, result(array_diff_key($same, ['grid_server' => 0])));

        expect($proposal['changes'])->toContain('grid_server: 4 -> 0');
    });

    test('tells which grids run w4os and are not on the list of the page, and the other way round', function () use ($json, $same) {
        $proposal = PageData::propose($json, result($same, w4os: ['New One' => 'http://new-one.example/', 'Ankabi' => 'http://ankabi.de']));

        expect($proposal['notes'])->toContain('w4os detected on New One (http://new-one.example/), not in the list of the page')
            ->and(implode("\n", $proposal['notes']))->toContain('Kurtopia is in the list of the page, w4os not detected in this survey')
            ->and(implode("\n", $proposal['notes']))->not->toContain('Ankabi');
        // the list itself is never edited
        expect(json_decode($proposal['json'], true)['grids'])->toBe(json_decode($json, true)['grids']);
    });
});
