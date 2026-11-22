<?php

declare(strict_types=1);

/** Runs the command line as a process, with a cache holding the saved fingerprints so nothing is downloaded. */
function grid_survey(array $args): array
{
    $cache = sys_get_temp_dir() . '/grid-survey-cli-cache';
    if (!is_dir($cache . '/webappanalyzer')) {
        mkdir($cache, 0775, true);
        exec('cp -R ' . escapeshellarg(sample('webappanalyzer')) . ' ' . escapeshellarg($cache . '/webappanalyzer'));
    }
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(tool() . '/bin/grid-survey') . ' '
        . implode(' ', array_map('escapeshellarg', $args)) . ' --cache ' . escapeshellarg($cache);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);

    return ['code' => proc_close($process), 'out' => $out, 'err' => $err];
}

describe('command line', function () {
    test('--help lists the commands, each command has its own help', function () {
        $help = grid_survey(['--help']);

        expect($help['code'])->toBe(0)->and($help['out'])->toContain('run', 'update-page', 'signatures');
        foreach (['run' => '--list', 'update-page' => '--grids-json'] as $command => $option) {
            expect(grid_survey([$command, '--help'])['out'])->toContain($option);
        }
    });

    test('run says when the list cannot be read', function () {
        $run = grid_survey(['run', '--list', '/no/such/list.html']);

        expect($run['code'])->toBe(1)->and($run['err'])->toContain('No such file');
    });

    test('run --offline classifies the saved pages and writes the dated result', function () {
        $dir = sys_get_temp_dir() . '/grid-survey-out-' . uniqid();
        $run = grid_survey([
            'run', '--list', sample('hb-active-grids.html'), '--stats', sample('hb-stats-2026-10.html'),
            '--month', '2026-11', '--keep-pages', sample('crawls'), '--offline', '--lists', sample('lists'), '--out', "$dir/result.json", '--quiet',
        ]);
        $result = json_decode(file_get_contents("$dir/result.json"), true);
        $by = array_column($result['grids'], null, 'name');

        expect($run['code'])->toBe(0)
            ->and($result['source'])->toMatchArray(['month' => '2026-11', 'partial' => false])
            ->and($result['source']['list']['updated'])->toBe('2026-11-16')
            ->and($result['source']['stats']['month'])->toBe('2026-10')
            ->and($by['Neverworld']['system'])->toBe('wordpress_w4os')
            ->and($by['Neverworld']['actives'])->toBe(1211)
            ->and($by['Cave Grid']['system'])->toBe('dreamgrid')
            ->and($result['excluded'][0]['name'])->toBe('3D Worldz')
            ->and($result['totals']['groups']['interface']['wordpress_w4os']['grids'])->toBe(2)
            ->and($result['source']['list']['month'])->toBe('2026-11')
            ->and($run['err'])->toContain('excluded: 1, see lists/excluded.json', 'added by request: 0, see lists/included.json');
    });

    test('--only makes a partial result, and update-page refuses it', function () {
        $dir = sys_get_temp_dir() . '/grid-survey-out-' . uniqid();
        grid_survey(['run', '--list', sample('hb-active-grids.html'), '--keep-pages', sample('crawls'), '--offline', '--lists', sample('lists'), '--only', 'kurtopia', '--out', "$dir/partial.json", '--quiet']);
        $update = grid_survey(['update-page', '--result', "$dir/partial.json", '--grids-json', sample('grids.json')]);

        expect(json_decode(file_get_contents("$dir/partial.json"), true)['source']['partial'])->toBeTrue()
            ->and($update['code'])->toBe(1)->and($update['err'])->toContain('partial run');
    });
});

describe('update-page', function () {
    test('shows a diff and writes nothing without --write; writes with it', function () {
        $dir = sys_get_temp_dir() . '/grid-survey-out-' . uniqid();
        mkdir($dir);
        copy(sample('grids.json'), "$dir/grids.json");
        grid_survey(['run', '--list', sample('hb-active-grids.html'), '--month', '2026-11', '--keep-pages', sample('crawls'), '--offline', '--lists', sample('lists'), '--out', "$dir/result.json", '--quiet']);
        $args = ['update-page', '--result', "$dir/result.json", '--grids-json', "$dir/grids.json"];

        $dry = grid_survey($args);
        expect($dry['code'])->toBe(0)->and($dry['out'])->toContain('--- ', '+++ ', '"surveyed": "2026-11"')
            ->and(file_get_contents("$dir/grids.json"))->toBe(file_get_contents(sample('grids.json')));

        grid_survey([...$args, '--write']);
        expect(json_decode(file_get_contents("$dir/grids.json"), true)['surveyed'])->toBe('2026-11');
    });
});
