<?php
/**
 * Pest setup. Run the suite with ./vendor/bin/pest.
 *
 * - tests/Environment: what the project needs from its environment (PHP version, compatibility)
 */

/** The temporary folder by its real path (macOS: /var is a link to /private/var, the paths the code finds are not the same). */
function test_tmp(): string
{
    return realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();
}

// The environment of the tests (tests/.env, see tests/bootstrap.sh for the scripts): what it defines, like CONTAINER_CONNECTION, is known
// to every process a test starts, so a container always goes to the machine that has the CPU
foreach (is_file(__DIR__ . '/.env') ? (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
    if (preg_match('/^\s*([A-Z][A-Z0-9_]*)=(.*)$/', $line, $m) && getenv($m[1]) === false) {
        putenv($m[1] . '=' . trim($m[2], " \t\"'"));
    }
}

// What the kit ships (presets, original MoneyServer.ini), where the engine looks for it
\OpenSim\Provisioning\ResourceRegistry::add(dirname(__DIR__));
