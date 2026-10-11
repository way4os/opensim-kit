<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command that is done by another program with the terminal of the caller: the standard input and outputs are its own, its
 * status is the status of the command. For what is an interactive program or a tool with its own command line (the setup,
 * the remote console client).
 */
abstract class DelegateCommand extends ScriptCommand
{
    /**
     * Run a program with the terminal of this process.
     *
     * @param list<string> $command the program and its arguments
     */
    protected static function passthrough(array $command): int
    {
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);

        return is_resource($process) ? proc_close($process) : 1;
    }

    /** The root of the kit. */
    protected static function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
