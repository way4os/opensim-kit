<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Archives\BackupService;
use OpenSim\Archives\DatabaseDump;
use OpenSim\Archives\Rotation;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim backup: what it takes to install the grids again as they are (see BackupService), as the user of the instances.
 *
 *   opensim backup [options]                  every grid of this machine and its simulators
 *   opensim backup GRID [options]             a grid and its simulators
 *   opensim backup GRID SIM|_SIM [options]    one simulator
 *   --logs        the logs too
 *   --archives    the archives of the users (iar, oar) too, they are backups already
 *   --output DIR  where the files go (default: <data>/backups/admin)
 *   --keep N      keep the N newest backups of the same kind, remove the older ones
 */
final class BackupCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim backup [GRID [SIM]] [--logs] [--archives] [--output DIR] [--keep N]\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('backup')->setDescription('Save what it takes to install the grids again: configuration, data, databases');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $refs = [];
        $options = [];
        while ($args !== []) {
            $arg = array_shift($args);
            if ($arg === '--logs' || $arg === '--archives') {
                $options[substr($arg, 2)] = true;
            } elseif ($arg === '--output' || str_starts_with($arg, '--output=')) {
                $options['output'] = str_contains($arg, '=') ? substr($arg, 9) : array_shift($args) ?? '';
                if ($options['output'] === '') {
                    self::say($err, "backup: --output needs a folder\n");

                    return 2;
                }
                if (!str_starts_with($options['output'], '/')) {
                    $options['output'] = getcwd() . '/' . $options['output'];
                }
            } elseif ($arg === '--keep' || str_starts_with($arg, '--keep=')) {
                try {
                    $options['keep'] = Rotation::count(str_contains($arg, '=') ? substr($arg, 7) : array_shift($args) ?? '');
                } catch (\InvalidArgumentException $e) {
                    self::say($err, 'backup: ' . $e->getMessage() . "\n");

                    return 2;
                }
            } elseif (str_starts_with($arg, '-') && $arg !== '_') {
                self::say($err, "backup: unknown option $arg\n" . self::USAGE);

                return 2;
            } else {
                $refs[] = $arg;
            }
        }

        $profile = $this->engine($out)->layout->all();
        if (($profile['EtcRoot'] ?? '') === '') {
            self::say($err, "backup: no install found\n");

            return 1;
        }
        try {
            $files = (new BackupService($profile, DatabaseDump::write(...), static function (string $message) use ($err): void {
                self::say($err, $message . "\n");
            }))->run($refs, $options);
        } catch (\InvalidArgumentException $e) {
            self::say($err, 'backup: ' . $e->getMessage() . "\n");

            return 2;
        } catch (\RuntimeException $e) {
            self::say($err, 'backup: ' . $e->getMessage() . "\n");

            return 1;
        }
        self::say($out, implode("\n", $files) . "\n");

        return self::ok();
    }
}
