<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim rest: the remote console of an instance of another machine or a container, by its address, as the caller
 * (opensim-rest-php).
 *
 *   opensim rest --url http://HOST:PORT --user USER (-- <command> | - | --repl)
 */
final class RestCommand extends DelegateCommand
{
    protected const USAGE = "usage: opensim rest --url http://HOST:PORT --user USER (-- <command> | - | --repl)\nThe password is read from OPENSIM_REST_PASSWORD. See opensim-rest-cli --help.\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('rest')->setDescription('The remote console of an instance of another machine or a container');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        return self::passthrough([PHP_BINARY, self::root() . '/vendor/magicoli/opensim-rest-php/opensim-rest-cli.php', ...$args]);
    }
}
