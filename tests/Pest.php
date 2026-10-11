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

// What the kit ships (presets, original MoneyServer.ini), where the engine looks for it
\OpenSim\Provisioning\ResourceRegistry::add(dirname(__DIR__));
