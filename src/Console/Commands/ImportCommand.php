<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim import: bring what a file holds into the install, a grid, its simulators, its accounts (libexec/import.php), as the
 * caller: it writes through the system user itself.
 *
 *   opensim import [GRID] FILE [--grid] [--simulator] [--users] [--apply] [--core DIR] [--name NAME]
 *                              [--keep-going] [--result FILE] [--format csv|json|yaml]
 */
final class ImportCommand extends DelegateCommand
{
    protected const USAGE = "usage: opensim import [GRID] FILE [--grid] [--simulator] [--users] [--apply] [--core DIR] [--name NAME]\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('import')->setDescription('Bring the config of a grid, of a simulator, a setup file or accounts into the install');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        return self::passthrough([PHP_BINARY, self::root() . '/libexec/import.php', ...$args]);
    }
}
