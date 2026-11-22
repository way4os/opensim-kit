<?php

declare(strict_types=1);

use GridSurvey\Lists;

describe('the lists kept by hand', function () {
    test('the files of the tool are valid', function () {
        expect(Lists::excluded(tool() . '/lists/excluded.json'))->not->toBeEmpty()
            ->and(array_column(Lists::excluded(tool() . '/lists/excluded.json'), 'name'))
            ->toContain('3D Worldz', 'Creo Mundos', 'Sovaria Estates');
        expect(Lists::included(tool() . '/lists/included.json'))->toBeArray();
    });

    test('every exclusion says why, where the statement is and when', function () {
        foreach (Lists::excluded(tool() . '/lists/excluded.json') as $entry) {
            expect($entry)->toHaveKeys(['name', 'host', 'reason', 'source', 'date']);
        }
    });

    test('an entry with a field missing is refused, with its number', function () {
        $file = sys_get_temp_dir() . '/excluded-' . uniqid() . '.json';
        file_put_contents($file, json_encode([['name' => 'X', 'host' => 'x.example', 'reason' => 'r', 'source' => 's']]));

        expect(fn () => Lists::excluded($file))->toThrow(RuntimeException::class, 'entry 1 has no "date"');
    });

    test('a grid that asks to be in the survey is added after the list, flagged as requested', function () {
        $grids = [['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']];
        $included = [['name' => 'B', 'url' => 'https://b.example', 'asked' => 'mail', 'recorded_by' => 'Olivier', 'date' => '2026-11-22']];
        $merged = Lists::merge($grids, $included);

        expect($merged)->toHaveCount(2)
            ->and($merged[0]['requested'])->toBeFalse()
            ->and($merged[1])->toMatchArray(['name' => 'B', 'requested' => true]);
    });

    test('a grid in both lists is counted once, and flagged', function () {
        $grids = [['name' => 'A', 'url' => 'http://www.a.example/', 'login_uri' => '']];
        $included = [['name' => 'A (mine)', 'url' => 'https://a.example', 'asked' => 'mail', 'recorded_by' => 'Olivier', 'date' => '2026-11-22']];
        $merged = Lists::merge($grids, $included);

        expect($merged)->toHaveCount(1)->and($merged[0]['requested'])->toBeTrue()->and($merged[0]['name'])->toBe('A');
    });
});
