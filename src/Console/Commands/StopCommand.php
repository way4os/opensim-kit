<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\OpenSim;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim stop [--sims] [now] [target...]: stop the services of grids (their Robust) and simulators, warning their users unless
 * `now`; with --sims, the simulators of the grids named too, before their Robust.
 */
final class StopCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('stop')
            ->setDescription('Stop grids and simulators, warning the users; everything enabled when none is given')
            ->addArgument('targets', InputArgument::IS_ARRAY, '[now] a grid (its services, its Robust), a simulator')
            ->addOption('sims', null, InputOption::VALUE_NONE, 'also the simulators of the grids named');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$now, $targets] = self::splitNow($input->getArgument('targets'));

        return self::status(OpenSim::stop($targets ?: null, $now, $this->engine($output), (bool) $input->getOption('sims')));
    }
}
