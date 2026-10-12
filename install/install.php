<?php

use OpenSim\Provisioning\SetupFailedException;
use OpenSimKit\Setup\Actions;
use OpenSimKit\Setup\Hub;
use OpenSimKit\Setup\NewGrid;
use OpenSimKit\Setup\NewSim;
use OpenSimKit\Setup\RuntimeCheck;
use OpenSimKit\Ui\PromptsUi;
use OpenSim\Provisioning\GridPlan;
use OpenSim\Provisioning\SimPlan;

/**
 * OpenSim Debian — setup hub (PHP wizard).
 *
 * Single entry point: detect the current state and route to the relevant
 * action (install a core, create/modify a grid, …). The flow lives in classes
 * (Hub, Installer, Grid\NewGrid) driven through an InstallerUi, so a web
 * frontend can reuse them later.
 */

require dirname(__DIR__) . '/bootstrap.php';

// A failure is already explained on screen: end with an error code, rather
// than going back to the menu
try {
    if (($argv[1] ?? '') === '--apply-grid') {
        // The writing of a grid, run as the system user by the setup of
        // another user, which sends the plan on the standard input
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        (new NewGrid(new PromptsUi()))->apply(GridPlan::fromArray($data['plan']), $data['profile']);
    } elseif (($argv[1] ?? '') === '--apply-sim') {
        // The same for a simulator
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        (new NewSim(new PromptsUi()))->apply(SimPlan::fromArray($data['plan']), $data['profile']);
    } elseif (($argv[1] ?? '') === '--apply-action') {
        // What a menu does to the install (enable, disable, restart), as the system user
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        exit((new Actions(new PromptsUi()))->perform($data['op'], $data['args'], $data['profile']) ? 0 : 1);
    } else {
        $ui = new PromptsUi();
        (new Hub($ui, new RuntimeCheck($ui)))->run();
    }
} catch (SetupFailedException) {
    exit(1);
}
