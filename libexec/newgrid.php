<?php

/**
 * newgrid (PHP) — configure a grid on top of an installed framework.
 *
 * Thin entry point: the flow lives in OpenSim\Installer\Grid\NewGrid so the
 * setup hub can reuse it. Runnable standalone too.
 */


use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Ui\PromptsUi;

require dirname(__DIR__) . '/bootstrap.php';

try {
    (new NewGrid(new PromptsUi()))->run();
} catch (SetupFailedException) {
    exit(1);
}
