<?php

namespace OpenSimKit\Setup;

use OpenSim\Console\InstanceConsoleService;
use OpenSim\Exceptions\ChannelUnavailable;
use OpenSim\Models\Grid;
use OpenSim\Models\Region;
use OpenSim\Models\Simulator;
use OpenSim\Provisioning\Import\RobustImporter;
use OpenSim\Provisioning\PendingRestarts;
use OpenSim\Provisioning\UiInterface;
use OpenSim\Runtime\Environment;
use OpenSim\Runtime\ShellService;

/**
 * What the setup menus do to the install once the questions are answered: enabling or disabling a
 * grid, a simulator or a region, restarting what has to. The files and the instances belong to
 * the system user of the install, so it is the process of that user that does them (see Elevated).
 */
final class Actions
{
    public function __construct(private UiInterface $ui) {}

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed> $profile
     * @return bool whether it was done, the reason said on screen when it was not
     */
    public function perform(string $op, array $args, array $profile): bool
    {
        $environment = InstallConfig::environmentOf($profile);

        switch ($op) {
            case 'grid-enable':
                return Grid::find($environment, $args['nick'])?->enable() === true ||
                    $this->no('Cannot enable the grid (no Robust config).');
            case 'grid-disable':
                $grid = Grid::find($environment, $args['nick']);
                $grid?->disable();

                return $grid?->isEnabled() !== true ||
                    $this->no('Cannot disable the grid (permission).');
            case 'simulator-enable':
                return (new Simulator($environment, $args['ini']))->enable() ||
                    $this->no('Cannot enable the simulator (no config).');
            case 'simulator-disable':
                $simulator = new Simulator($environment, $args['ini']);
                $simulator->disable();

                return !$simulator->isEnabled() ||
                    $this->no('Cannot disable the simulator (permission).');
            case 'region-enable':
            case 'region-disable':
                try {
                    foreach (Region::inFile($args['file']) as $region) {
                        $op === 'region-enable' ? $region->enable() : $region->disable();
                    }
                } catch (ChannelUnavailable) {
                    return $this->no(
                        'Cannot change the region (permission, or a region of that name is there already).',
                    );
                }
                PendingRestarts::add(
                    $environment,
                    $args['instance'],
                    "region {$args['name']} " . ($op === 'region-enable' ? 'enabled' : 'disabled'),
                );

                return true;
            case 'restart':
                return $this->restart($environment, (bool) ($args['warn'] ?? false));
            case 'copy-regions':
                // The region files of an imported simulator: copied, the original ones are not touched
                @mkdir((string) $args['to'], 0o755, true);
                foreach ($args['files'] as $file) {
                    $dest = rtrim((string) $args['to'], '/') . '/' . basename((string) $file);
                    if (!is_file($dest) && !@copy((string) $file, $dest)) {
                        return $this->no("Cannot copy $file to $dest.");
                    }
                }

                return true;
            case 'inject-config':
                // The settings of an imported config go into the config the kit wrote, and the report beside it
                $path = (string) $args['path'];
                if (
                    !is_file($path) ||
                    @file_put_contents(
                        $path,
                        RobustImporter::inject((string) file_get_contents($path), $args['customizations']),
                    ) === false
                ) {
                    return $this->no("Cannot write the settings into $path.");
                }
                if (($args['report_path'] ?? '') !== '') {
                    @file_put_contents((string) $args['report_path'], (string) $args['report']);
                }

                return true;
        }

        return $this->no("Unknown action $op.");
    }

    private function no(string $message): bool
    {
        $this->ui->warn($message);

        return false;
    }

    /**
     * Restart the instances waiting for it: the grids first (the simulators register in them),
     * then each simulator, stopped, its regions' parcels named, started again when it was running.
     */
    private function restart(Environment $environment, bool $warn): bool
    {
        $etcRoot = $environment->layout->etcRoot();
        $opensim = dirname(__DIR__, 2) . '/bin/opensim';
        $instances = array_values(array_unique(array_column(PendingRestarts::read($environment), 'instance')));
        usort(
            $instances,
            static fn(string $a, string $b): int => is_file("$etcRoot/robust.d/$b.ini") <=>
                is_file("$etcRoot/robust.d/$a.ini"),
        );

        $ok = true;
        foreach ($instances as $instance) {
            $now = $warn ? '' : 'now ';
            if (is_file("$etcRoot/robust.d/$instance.ini")) {
                $ok = ShellService::run(ShellService::arg($opensim) . ' restart now ' . ShellService::arg($instance)) === 0 && $ok;

                continue;
            }
            $running = InstanceConsoleService::running("$etcRoot/opensim.d/$instance.ini");
            if ($running) {
                $ok = ShellService::run(ShellService::arg($opensim) . " stop $now" . ShellService::arg($instance)) === 0 && $ok;
            }
            if ($running) {
                $ok = ShellService::run(ShellService::arg($opensim) . ' start ' . ShellService::arg($instance)) === 0 && $ok;
            }
        }
        if ($ok) {
            PendingRestarts::clear($environment);
        }

        return $ok;
    }
}
