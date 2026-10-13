<?php
/**
 * The containers of the tests all go where tests/.env says (CONTAINER_CONNECTION: the machine that has the CPU, not the one the tests
 * are started from), and nothing in the tests names a machine: a script that starts a container loads tests/bootstrap.sh first.
 */

$root = dirname(__DIR__, 2);

/**
 * The files of tests/ and dev/ that git knows, scripts only (what is not code or a doc of the project is left out).
 *
 * @return list<string> paths relative to the root
 */
function container_scripts(string $root): array
{
    $files = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files --cached --others --exclude-standard -- tests dev', $files);

    return array_values(array_filter(
        $files,
        static fn(string $file): bool => is_file("$root/$file")
            && !preg_match('/\.(php|md|json|yaml|yml|txt|ini|csv|Containerfile)$/i', $file)
            && !str_starts_with($file, 'tests/lib/')
            && !str_ends_with($file, '.example'),
    ));
}

/** The lines of a script that do something (not the comments). */
function container_code(string $root, string $file): string
{
    return implode("\n", array_filter(
        file("$root/$file", FILE_IGNORE_NEW_LINES) ?: [],
        static fn(string $line): bool => !str_starts_with(ltrim($line), '#'),
    ));
}

describe('the containers of the tests', function () use ($root) {
    test('every script that starts one loads the bootstrap of the tests, which exports CONTAINER_CONNECTION', function () use ($root) {
        $without = [];
        foreach (container_scripts($root) as $file) {
            if ($file !== 'tests/bootstrap.sh' && preg_match('/\b(podman|docker)\b/', container_code($root, $file)) && !str_contains(container_code($root, $file), 'tests/bootstrap.sh')) {
                $without[] = $file;
            }
        }

        expect($without)->toBe([])
            ->and(file_get_contents("$root/tests/bootstrap.sh"))->toContain('export CONTAINER_CONNECTION');
    });

    test('the bootstrap is plain bash: safe in strict mode, from anywhere, and what the caller set wins over the files', function () use ($root) {
        $run = static fn(string $env): array => (function () use ($root, $env): array {
            $out = [];
            exec("cd / && $env bash -c " . escapeshellarg("set -euo pipefail; source $root/tests/bootstrap.sh 2>/dev/null; echo \"[\${MEMORY:-}]\"") . ' 2>&1', $out, $status);

            return [$status, implode("\n", $out)];
        })();

        expect($run('MEMORY=1g'))->toBe([0, '[1g]'])
            ->and($run('')[0])->toBe(0);
    });

    test('and no script of the tests names the machine: that is for tests/.env', function () use ($root) {
        $naming = [];
        foreach (container_scripts($root) as $file) {
            if (preg_match('/CONTAINER_CONNECTION=(?!name\b)[A-Za-z0-9_.-]+/', container_code($root, $file)) || preg_match('/--connection[ =][A-Za-z]/', container_code($root, $file))) {
                $naming[] = $file;
            }
        }

        expect($naming)->toBe([]);
    });
});
