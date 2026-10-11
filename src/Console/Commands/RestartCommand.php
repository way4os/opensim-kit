<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\OpenSim;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim restart [now] [target...]: everything named stops, then everything starts.
 */
final class RestartCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('restart')
            ->setDescription('Stop then start grids and simulators; everything enabled when none is given')
            ->addArgument('targets', InputArgument::IS_ARRAY, '[now] a grid, a simulator, robust:<grid>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$now, $targets] = self::splitNow($input->getArgument('targets'));

        return self::status(OpenSim::restart($targets ?: null, $now, $this->engine($output)));
    }
}
