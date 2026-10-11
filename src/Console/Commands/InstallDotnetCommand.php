<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim install-dotnet: the .NET runtime OpenSimulator needs, installed for the whole system (as root).
 */
final class InstallDotnetCommand extends DelegateCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('install-dotnet')->setDescription('Install the .NET runtime OpenSimulator needs (as root)');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        return self::passthrough([self::root() . '/libexec/install-dotnet', ...$args]);
    }
}
