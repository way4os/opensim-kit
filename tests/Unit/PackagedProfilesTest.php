<?php

declare(strict_types=1);

/**
 * The install profiles the packages of the releases keep in opensim.conf: one per release, a default, the profile of a release that
 * is gone forgotten, the administrator's own lines left alone.
 */

use OpenSim\Config\ConfigReader;
use OpenSimKit\Setup\PackagedProfiles;

/** A machine: an opensim.conf as the package makes it, and the releases installed (name => assembly). */
function profiles_machine(array $releases, string $extra = ''): array
{
    $root = test_tmp() . '/profiles-' . bin2hex(random_bytes(4));
    mkdir("$root/cores", 0o755, true);
    mkdir("$root/etc");
    foreach ($releases as $name => $assembly) {
        mkdir("$root/cores/$name/bin", 0o755, true);
        touch("$root/cores/$name/bin/$assembly");
    }
    $conf = "$root/etc/opensim.conf";
    file_put_contents($conf, "[Defaults]\nDirectoryLayout = system\nCoreRoot = /usr/share/opensim\nEtcRoot = /etc/opensim\nVarRoot = /var/lib/opensim\nLogsRoot = /var/log/opensim\nCacheRoot = /var/cache/opensim\nDataRoot = /var/lib/opensim/data\nSystemUser = opensim\n$extra");

    return [new PackagedProfiles($conf, "$root/cores"), $conf, $root];
}

describe('The profiles of the packaged releases', function () {
    test('are one per release, with the locations of the install, the latest release being the default', function () {
        [$profiles, $conf, $root] = profiles_machine(['0.9.2.2' => 'OpenSim.exe', '0.9.3.0' => 'OpenSim.exe', 'unstable' => 'OpenSim.dll']);

        $notes = $profiles->update();

        $all = ConfigReader::file($conf);
        expect(array_keys($all))->toBe(['Defaults', 'opensim-0.9.2.2', 'opensim-0.9.3.0', 'opensim-unstable'])
            ->and($all['opensim-0.9.3.0'])->toBe([
                'DirectoryLayout' => 'system', 'CoreRoot' => '/usr/share/opensim', 'EtcRoot' => '/etc/opensim', 'VarRoot' => '/var/lib/opensim',
                'LogsRoot' => '/var/log/opensim', 'CacheRoot' => '/var/cache/opensim', 'DataRoot' => '/var/lib/opensim/data', 'SystemUser' => 'opensim',
                'OpensimVersion' => '0.9.3.0', 'CoreDirectory' => "$root/cores/0.9.3.0", 'Runtime' => 'dotnet',
            ])
            ->and($all['Defaults']['DefaultProfile'])->toBe('opensim-0.9.3.0')
            ->and($all['Defaults']['CoreDirectory'])->toBe("$root/cores/0.9.3.0")
            ->and($all['Defaults']['OpensimVersion'])->toBe('0.9.3.0')
            ->and($notes)->toContain('profile opensim-0.9.2.2 added', 'default profile: opensim-0.9.3.0');
    });

    test('are left as they are when nothing changed', function () {
        [$profiles, $conf] = profiles_machine(['0.9.3.0' => 'OpenSim.exe']);
        $profiles->update();
        $text = file_get_contents($conf);

        expect($profiles->update())->toBe([])->and(file_get_contents($conf))->toBe($text);
    });

    test('are forgotten with their release, and the default is chosen again among the others', function () {
        [$profiles, $conf, $root] = profiles_machine(['0.9.2.2' => 'OpenSim.exe', '0.9.3.0' => 'OpenSim.exe']);
        $profiles->update();
        unlink("$root/cores/0.9.3.0/bin/OpenSim.exe");
        rmdir("$root/cores/0.9.3.0/bin");
        rmdir("$root/cores/0.9.3.0");

        $notes = $profiles->update();

        $all = ConfigReader::file($conf);
        expect(array_keys($all))->toBe(['Defaults', 'opensim-0.9.2.2'])
            ->and($all['Defaults']['DefaultProfile'])->toBe('opensim-0.9.2.2')
            ->and($notes)->toContain('profile opensim-0.9.3.0 removed, its release is gone');
    });

    test('keep what is not theirs: an install of the administrator, comments, the default they chose', function () {
        [$profiles, $conf, $root] = profiles_machine(['0.9.3.0' => 'OpenSim.exe'], "DefaultProfile = mine\n\n; my own\n[mine]\nCoreDirectory = /opt/opensim/core\nEtcRoot = /opt/opensim/etc\n");

        $profiles->update();

        $all = ConfigReader::file($conf);
        expect($all['Defaults']['DefaultProfile'])->toBe('mine')
            ->and($all['mine'])->toBe(['CoreDirectory' => '/opt/opensim/core', 'EtcRoot' => '/opt/opensim/etc'])
            ->and(file_get_contents($conf))->toContain('; my own')
            ->and($all)->toHaveKey('opensim-0.9.3.0');
    });

    test('are nothing without opensim.conf or without a release', function () {
        [$profiles, $conf] = profiles_machine([]);
        expect($profiles->update())->toBe([]);
        unlink($conf);
        expect((new PackagedProfiles($conf, '/nonexistent'))->update())->toBe([]);
    });
});

describe('The modules enabled for an install', function () {
    test('are set the first time, never again, not even when the administrator emptied them', function () {
        [$profiles, $conf] = profiles_machine([]);

        expect($profiles->enableModulesOnce('opensimsearch gloebit'))->toBeTrue()
            ->and(ConfigReader::file($conf)['Defaults']['EnabledModules'])->toBe('opensimsearch gloebit')
            ->and($profiles->enableModulesOnce('other'))->toBeFalse();

        [$profiles, $conf] = profiles_machine([], "EnabledModules =\n");
        expect($profiles->enableModulesOnce('opensimsearch'))->toBeFalse()
            ->and(ConfigReader::file($conf)['Defaults']['EnabledModules'])->toBe('');
    });
});

describe('libexec/update-profiles.php', function () {
    test('does it for the packages that run it', function () {
        [, $conf, $root] = profiles_machine(['0.9.3.0' => 'OpenSim.exe']);
        $script = dirname(__DIR__, 2) . '/libexec/update-profiles.php';

        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --conf ' . escapeshellarg($conf) . ' --cores ' . escapeshellarg("$root/cores") . ' --default-modules "a b" 2>&1');

        expect($output)->toContain('opensim: profile opensim-0.9.3.0 added', 'opensim: modules enabled: a b')
            ->and(ConfigReader::file($conf)['Defaults']['EnabledModules'])->toBe('a b');
    });
});
