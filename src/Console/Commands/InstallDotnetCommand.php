<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Runtime\DotnetInstaller;
use OpenSimKit\Ui\PromptsUi;
use OpenSimKit\Ui\QuietUi;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim install-dotnet [-y] [MAJOR]: what OpenSimulator needs to run, installed when it is missing: the .NET runtime (default
 * 8, a newer one fits) and libgdiplus. As root, for the whole system; otherwise in the home of the person.
 *
 * With -y, or when nobody can answer (no terminal), the default answers are taken.
 */
final class InstallDotnetCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim install-dotnet [-y] [MAJOR]\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('install-dotnet')->setDescription('Install the .NET runtime OpenSimulator needs (as root)');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $yes = in_array('-y', $args, true) || in_array('--yes', $args, true);
        $words = array_values(array_filter($args, static fn(string $a): bool => $a !== '' && $a[0] !== '-'));
        if (count($words) > 1 || ($words !== [] && !ctype_digit($words[0]))) {
            self::say($err, self::USAGE);

            return 2;
        }
        $ui = $yes || !stream_isatty(STDIN) ? new QuietUi(verbose: true) : new PromptsUi();

        return (new DotnetInstaller($ui))->install((int) ($words[0] ?? 8)) ? self::ok() : 1;
    }
}
