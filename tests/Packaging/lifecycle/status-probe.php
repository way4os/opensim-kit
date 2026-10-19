<?php

// What the engine sees of the instances of the machine, as the user that runs it (for lifecycle-scenario.sh).
require '/usr/share/opensim-tools/bootstrap.php';

$environment = OpenSim\OpenSim::environment();
$status = OpenSim\OpenSim::status($environment);
echo 'processes: ', count($status->processes), ', instances: ', count($status->instances), ', up: ', $status->up(), "\n";
foreach ($status->instances as $item) {
    echo $item->name(), ' ', $item->ini(), ' ', $item->isRunning() ? 'up' : 'down', "\n";
}
foreach ($status->processes as $process) {
    echo 'process ', $process->pid, ' ', $process->user, ' ', substr($process->arguments, 0, 90), "\n";
}
echo 'table: ', count($environment->processes->all()), ', class ', get_class($environment->processes), "\n";
echo 'user: ', get_current_user(), ' ', (function_exists('posix_geteuid') ? posix_geteuid() : '?'), ' shell_exec: ', var_export(function_exists('shell_exec'), true), "\n";
