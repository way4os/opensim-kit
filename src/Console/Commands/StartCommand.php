<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\OpenSim;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim start [target...]: start grids and simulators (the Robust of a grid first, then its simulators).
 */
final class StartCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('start')
            ->setDescription('Start grids and simulators, everything enabled when none is given')
            ->addArgument('targets', InputArgument::IS_ARRAY, 'a grid (its Robust and its simulators), a simulator, robust:<grid>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::status(OpenSim::start($input->getArgument('targets') ?: null, $this->engine($output)));
    }
}
