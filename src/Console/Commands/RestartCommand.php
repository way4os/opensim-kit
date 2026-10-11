<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\OpenSim;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim restart [--sims] [now] [target...]: everything named stops, then everything starts. A grid is its services (its Robust):
 * its simulators do not need to restart with them, --sims restarts them too.
 */
final class RestartCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('restart')
            ->setDescription('Stop then start grids and simulators; everything enabled when none is given')
            ->addArgument('targets', InputArgument::IS_ARRAY, '[now] a grid (its services, its Robust), a simulator')
            ->addOption('sims', null, InputOption::VALUE_NONE, 'also the simulators of the grids named');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$now, $targets] = self::splitNow($input->getArgument('targets'));

        return self::status(OpenSim::restart($targets ?: null, $now, $this->engine($output), (bool) $input->getOption('sims')));
    }
}
