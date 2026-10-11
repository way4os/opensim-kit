<?php

declare(strict_types=1);

namespace OpenSimKit\System;

use OpenSim\Provisioning\UiInterface;

/**
 * Starts and restarts instances for the setup through the command line of the kit (`opensim start|restart <target>`), which
 * acts as the user that runs the instances whoever types it: what it says is shown as it comes and kept to be read.
 */
final class CommandRunner
{
    /**
     * @param string $action start or restart
     * @param string $target a grid (its Robust) or a simulator
     * @param bool $now restart without warning the users
     * @return array{0:int,1:string} the status of the command and what it said
     */
    public static function run(UiInterface $ui, string $action, string $target, bool $now = false): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/bin/opensim', $action, ...($now && $action === 'restart' ? ['now'] : []), $target];
        $process = proc_open($command, [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        if (!is_resource($process)) {
            return [1, ''];
        }
        $said = '';
        while (($chunk = fread($pipes[1], 8192)) !== false && $chunk !== '') {
            echo $chunk;
            $said .= $chunk;
        }
        fclose($pipes[1]);

        return [proc_close($process), $said];
    }
}
