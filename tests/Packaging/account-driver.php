<?php

use OpenSimKit\Setup\InstallConfig;
use OpenSim\Models\Grid;
use OpenSim\Provisioning\DatabaseService;
use OpenSim\Provisioning\GridAccounts;

// Makes an account in the grid TEST_GRID (default testgrid), through the console of its Robust, as the setup does,
// for tests/Packaging/package-scenario.sh. TEST_ACCOUNT (default "Later User"), its password TEST_ACCOUNT_PASSWORD.

require '/usr/share/opensim-tools/bootstrap.php';
require __DIR__ . '/ScriptedUi.php';

$ui = new ScriptedUi([]);
$grid = Grid::find(InstallConfig::environmentOf((new InstallConfig())->profile()), getenv('TEST_GRID') ?: 'testgrid');
if ($grid === null) {
    echo "grid not found\n";
    exit(1);
}

$created = (new GridAccounts(new DatabaseService($ui), $ui))->create(
    $grid,
    getenv('TEST_ACCOUNT') ?: 'Later User',
    getenv('TEST_ACCOUNT_PASSWORD') ?: 'laterpw1',
    'later@example.com',
);
exit($created ? 0 : 1);
