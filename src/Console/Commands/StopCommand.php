<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\OpenSim;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim stop [now] [target...]: stop grids and simulators (the simulators first), warning their users unless `now`.
 */
final class StopCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('stop')
            ->setDescription('Stop grids and simulators, warning the users; everything enabled when none is given')
            ->addArgument('targets', InputArgument::IS_ARRAY, '[now] a grid (its simulators, then its Robust), a simulator, robust:<grid>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$now, $targets] = self::splitNow($input->getArgument('targets'));

        return self::status(OpenSim::stop($targets ?: null, $now, $this->engine($output)));
    }
}
