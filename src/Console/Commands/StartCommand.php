<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\OpenSim;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim start [--simulators] [target...]: start the services of grids (their Robust) and simulators; with --simulators, the simulators of
 * the grids named too, after their Robust.
 */
final class StartCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('start')
            ->setDescription('Start grids and simulators, everything enabled when none is given')
            ->addArgument('targets', InputArgument::IS_ARRAY, 'a grid (its services, its Robust), a simulator')
            ->addOption('simulators', null, InputOption::VALUE_NONE, 'also the simulators of the grids named');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::status(OpenSim::start($input->getArgument('targets') ?: null, $this->engine($output), (bool) $input->getOption('simulators')));
    }
}
