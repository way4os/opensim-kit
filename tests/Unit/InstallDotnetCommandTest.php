<?php
/**
 * `opensim install-dotnet`: its arguments (what it installs is the engine's DotnetInstaller, tested there).
 */

describe('opensim install-dotnet', function () {
    test('takes nothing but a major version and -y', function () {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/opensim', 'install-dotnet', 'eight'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $errors = stream_get_contents($pipes[2]);
        stream_get_contents($pipes[1]);

        expect(proc_close($process))->toBe(2)->and($errors)->toContain('usage: opensim install-dotnet [-y] [MAJOR]');
    });
});
