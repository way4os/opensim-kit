<?php

declare(strict_types=1);

namespace OpenSimKit\System;

/**
 * Lines for the console of an instance, sent by the command line of the kit (`opensim command <instance> -`), which acts as the
 * user that runs the instances whoever types it: a screen session is per user, and the setup is not always that user.
 */
final class CommandSender
{
    public static function send(string $instance, string $lines): bool
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/opensim', 'command', $instance, '-'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            return false;
        }
        fwrite($pipes[0], $lines);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        return proc_close($process) === 0;
    }
}
