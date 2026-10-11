<?php

/**
 * What every entry point of the kit loads first: the autoload (the kit, the engine and the other libraries), then
 * what must always be there.
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use OpenSim\Provisioning\ResourceRegistry;
use OpenSimKit\I18n\I18n;

// The presets and the original MoneyServer.ini the kit ships, for the engine
ResourceRegistry::add(__DIR__);

I18n::init();
