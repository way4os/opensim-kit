<?php

/**
 * What every entry point of the kit loads first: the autoload (the kit, the engine and the other libraries), then
 * what must always be there.
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use OpenSim\Console\InstanceConsoleService;
use OpenSim\Provisioning\InstanceControl;
use OpenSim\Provisioning\ResourceRegistry;
use OpenSimKit\I18n\I18n;
use OpenSimKit\System\CommandRunner;
use OpenSimKit\System\CommandSender;

// The presets and the original MoneyServer.ini the kit ships, for the engine
ResourceRegistry::add(__DIR__);
// The console of an instance is reached through the command line, which acts as the user that runs the instances
InstanceConsoleService::sendThrough(CommandSender::send(...));
InstanceControl::runThrough(CommandRunner::run(...));

I18n::init();
