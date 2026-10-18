<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Archives\ArchiveRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim save|load iar|oar: the inventory and region archives, through the console of a running simulator (see ArchiveRunner).
 * Run as the user of the instances: it writes where they write.
 *
 *   opensim save iar [GRID [SIM]] [options] FIRST LAST PATH [PASSWORD] [FILE]
 *   opensim load iar [GRID [SIM]] [-m|--merge] FIRST LAST PATH [PASSWORD] [FILE]
 *   opensim save oar [GRID [SIM|REGION]] [options] [FILE]
 *   opensim load oar [GRID [SIM|REGION]] [options] [FILE]
 */
abstract class ArchiveCommand extends ScriptCommand
{
    protected const USAGE = <<<'USAGE'
        usage: opensim save iar [GRID [SIM]] [-h|--home=URL] [-v] [--noassets] [--perm=PERMISSIONS] [--skipbadassets]
                                FIRST LAST PATH [PASSWORD] [FILE]
               opensim load iar [GRID [SIM]] [-m|--merge] FIRST LAST PATH [PASSWORD] [FILE]
               opensim save oar [GRID [SIM|REGION]] [--region NAME] [--all] [--noassets] [-h|--home=URL] [--publish]
                                [--perm=PERMISSIONS] [FILE]
               opensim load oar [GRID [SIM|REGION]] [--region NAME] [--merge] [--skip-assets] [FILE]

        FILE: a name (kept in <data of the grid>/backups/iar or oar) or a path; load takes the newest when none is given.
        The password is asked when left out, or read from OPENSIM_IAR_PASSWORD.

        USAGE;

    /** `save` or `load`. */
    abstract protected function verb(): string;

    protected function configure(): void
    {
        parent::configure();
        $this->setName($this->verb())->setDescription(ucfirst($this->verb()) . ' an inventory (iar) or a region (oar) archive through the console of a simulator');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $verb = $this->verb();
        if ($args === []) {
            self::say($err, self::USAGE);

            return 2;
        }
        $environment = $this->engine($out);
        if ($environment->layout->etcRoot() === '') {
            self::say($err, "$verb: no install found\n");

            return 1;
        }
        [$code, $file] = ArchiveRunner::system($environment)->run($verb, $args);
        if ($file !== null) {
            self::say($out, "$file\n");
        }

        return $code;
    }
}
