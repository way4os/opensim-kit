#!/usr/bin/env php
<?php

use OpenSimKit\Setup\PackagedProfiles;

/**
 * What the packages do to opensim.conf when they are installed, upgraded or removed (the postinst scripts run this):
 *
 *   update-profiles.php [--conf FILE] [--cores FOLDER] [--default-modules "a b"]
 *
 * One profile per release found in the cores folder (/usr/share/opensim), the one of a release that is gone forgotten, the latest
 * release the default when there is none. With --default-modules, the modules are enabled for the install if it has no setting
 * for them yet.
 */

require dirname(__DIR__) . '/bootstrap.php';

$options = getopt('', ['conf:', 'cores:', 'default-modules:']);
$profiles = new PackagedProfiles($options['conf'] ?? '/etc/opensim/opensim.conf', $options['cores'] ?? '/usr/share/opensim');
foreach ($profiles->update() as $note) {
    echo "opensim: $note\n";
}
if (isset($options['default-modules']) && $profiles->enableModulesOnce((string) $options['default-modules'])) {
    echo "opensim: modules enabled: {$options['default-modules']}\n";
}
