<?php
/**
 * `opensim ban`: the grid it works on and what it asks for; the statements themselves are the engine's (UserBan).
 */

/**
 * Run bin/opensim ban as a user whose setup is a temporary tree.
 *
 * @param list<string> $arguments What follows `opensim ban`.
 * @return array{0:int,1:string,2:string} Exit status, output, errors.
 */
function ban_run(array $arguments, string $home): array
{
    $root = dirname(__DIR__, 2);
    $process = proc_open(
        [PHP_BINARY, "$root/bin/opensim", 'ban', ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
        ['HOME' => $home, 'PATH' => getenv('PATH')],
    );
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    return [proc_close($process), (string) $output, trim((string) $errors)];
}

/**
 * A setup with grids whose Robust config is given: nick => text of Robust.HG.ini.
 *
 * @param array<string,string> $grids
 */
function ban_home(array $grids): string
{
    $root = test_tmp() . '/ban-' . bin2hex(random_bytes(4));
    $etc = "$root/etc";
    mkdir("$root/home", 0o755, true);
    file_put_contents("$root/home/.opensim.conf", "[Defaults]\nDefaultProfile = 0.9.3.0\n\n[0.9.3.0]\nEtcRoot = $etc\n");
    foreach ($grids as $nick => $robust) {
        mkdir("$etc/grids/$nick", 0o755, true);
        file_put_contents("$etc/grids/$nick/Robust.HG.ini", $robust);
    }

    return "$root/home";
}

describe('opensim ban', function () {
    test('needs a grid on this machine, and says which when there are several', function () {
        [$status, , $errors] = ban_run(['Jane Doe'], ban_home([]));
        expect($status)->toBe(2)->and($errors)->toContain('no grid of this machine');

        [$status, , $errors] = ban_run(['Jane Doe'], ban_home(['alpha' => "[Const]\n", 'beta' => "[Const]\n"]));
        expect($status)->toBe(2)->and($errors)->toContain('several grids, say which: opensim ban <grid>');
    });

    test('needs somebody to ban', function () {
        [$status, , $errors] = ban_run([], ban_home(['alpha' => "[Const]\n"]));

        expect($status)->toBe(2)->and($errors)->toContain('usage: opensim ban');
    });

    test('needs the database of the grid', function () {
        [$status, , $errors] = ban_run(['alpha', 'Jane Doe'], ban_home(['alpha' => "[Const]\n", 'beta' => "[Const]\n"]));

        expect($status)->toBe(1)->and($errors)->toContain('No database in the Robust config of alpha');
    });
});
