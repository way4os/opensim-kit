<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Archives\Instances;
use OpenSim\Interfaces\Enablable;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use OpenSim\Provisioning\GridOptions;
use OpenSim\Provisioning\Slug;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim enable|disable: enable or disable a grid or a simulator by the name of its instance (the same as the menus of the
 * setup). An enabled instance is started with the others by `opensim start` and at boot; disabling it does not stop it.
 *
 *   opensim enable|disable <instance>...
 *
 * And with an option after the grid, what the option needs in the files of the grid (see GridOptions, the presets that have an
 * option.conf):
 *
 *   opensim enable|disable <grid> <option> [<variant>] [<url prefix>]
 */
abstract class ToggleCommand extends EngineCommand
{
    private const USAGE = "usage: opensim enable|disable <instance>...\n" .
        "       opensim enable|disable <grid> <option> [<variant>] [<url prefix>]    (search, economy gloebit, profiles, offline robust, ossl-extended...)\n";

    /** `enable` or `disable`. */
    abstract protected function verb(): string;

    protected function configure(): void
    {
        $verb = $this->verb();
        $this->setName($verb)
            ->setDescription(ucfirst($verb) . ' a grid or a simulator, or an option of a grid (search, economy, profiles, offline, ossl-extended...)')
            ->addArgument('names', InputArgument::IS_ARRAY, 'the instances, or a grid and a feature [options]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $verb = $this->verb();
        $names = array_values((array) $input->getArgument('names'));
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        if ($names === []) {
            $error->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        $environment = $this->engine($output);
        $etc = $environment->layout->etcRoot();

        // An option of a grid: the grid, then the word of the option
        $grid = isset($names[1]) ? $this->gridOf($names[0], $environment, $etc) : null;
        if ($grid !== null && (new GridOptions($grid))->resolve($names[1]) !== null) {
            return $this->feature($names, $grid, $output, $error);
        }

        // The grids and the simulators the setup made, by the name of their instance
        $instances = [];
        foreach (Grid::all($environment) as $grid) {
            if ($grid->robustIni() !== null) {
                $instances[Slug::snake($grid->nick())] = ['kind' => 'grid', 'model' => $grid];
            }
            foreach ($grid->simulators() as $simulator) {
                $instances[basename($simulator->ini(), '.ini')] = ['kind' => 'simulator', 'model' => $simulator];
            }
        }

        $enable = $verb === 'enable';
        $failed = false;
        foreach ($names as $name) {
            $instance = $instances[Slug::snake($name)] ?? null;
            if ($instance === null) {
                $error->writeln(
                    "$verb: no grid or simulator called $name" . ($instances === [] ? '' : ' (known: ' . implode(', ', array_keys($instances)) . ')'),
                    OutputInterface::OUTPUT_RAW,
                );
                $failed = true;

                continue;
            }
            if ($instance['model']->isEnabled() === $enable) {
                $output->writeln("{$instance['kind']} $name is already {$verb}d", OutputInterface::OUTPUT_RAW);

                continue;
            }
            if ($this->perform($instance, $enable, $error)) {
                $output->writeln("{$instance['kind']} $name {$verb}d" . ($enable ? ": opensim start $name" : ''), OutputInterface::OUTPUT_RAW);
            } else {
                $failed = true;
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Link the config of an instance where the launcher finds it, or unlink it.
     *
     * @param array{kind:string,model:Enablable} $instance
     */
    private function perform(array $instance, bool $enable, OutputInterface $error): bool
    {
        $grid = $instance['kind'] === 'grid';
        $model = $instance['model'];
        if ($enable) {
            $done = $model->enable();
            $done || $error->writeln($grid ? 'Cannot enable the grid (no Robust config).' : 'Cannot enable the simulator (no config).', OutputInterface::OUTPUT_RAW);

            return $done;
        }
        $model->disable();
        $done = !$model->isEnabled();
        $done || $error->writeln($grid ? 'Cannot disable the grid (permission).' : 'Cannot disable the simulator (permission).', OutputInterface::OUTPUT_RAW);

        return $done;
    }

    /** The grid of this nick on this machine, null when there is none. */
    private function gridOf(string $name, Environment $environment, string $etc): ?Grid
    {
        foreach (Instances::grids($etc) as $candidate) {
            if (strcasecmp($candidate, $name) === 0) {
                return Grid::find($environment, $candidate);
            }
        }

        return null;
    }

    /**
     * An option of a grid.
     *
     * @param list<string> $names the grid, the option, its variant and its options
     */
    private function feature(array $names, Grid $grid, OutputInterface $output, OutputInterface $error): int
    {
        $verb = $this->verb();
        $more = array_slice($names, 2);
        $variant = isset($more[0]) && !str_contains($more[0], '/') ? strtolower(array_shift($more)) : '';
        $options = new GridOptions($grid);
        $option = $options->resolve($names[1], $variant);
        if ($option === null) {
            $error->writeln("$verb: no option {$names[1]}" . ($variant !== '' ? " $variant" : '') . ' (' . implode(', ', array_keys($options->templates())) . ')', OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
        if (count($more) > 1) {
            $error->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        $params = $more !== [] ? ['prefix' => $more[0]] : [];

        $result = $verb === 'enable' ? $options->enable($option->name(), $params) : $options->disable($option->name());
        if ($result['error'] !== null) {
            $error->writeln("$verb: {$result['error']}", OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
        $state = $verb . 'd';
        $what = $option->name();
        $touched = $result['simulators'];
        if ($touched === [] && !$result['grid'] && !$result['robust'] && $result['files'] === []) {
            $output->writeln(
                "nothing to change: $what is already $state for {$grid->nick()}" . ($options->simulators() === [] ? ' (the grid has no simulator on this machine)' : ''),
                OutputInterface::OUTPUT_RAW,
            );
        } else {
            // What the simulators and Robust read at their start; the OpenSim.ini of the grid is read by the helpers each time
            $restart = $touched;
            if ($result['robust']) {
                array_unshift($restart, $grid->robustInstance());
            }
            $parts = array_filter([
                $touched === [] ? null : count($touched) . ' simulator' . (count($touched) === 1 ? '' : 's'),
                $result['robust'] ? 'Robust' : null,
                $result['grid'] ? 'the OpenSim.ini of the grid' : null,
                $result['files'] === [] ? null : count($result['files']) . ' other file' . (count($result['files']) === 1 ? '' : 's'),
            ]);
            $changed = count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts) : (string) reset($parts);
            $output->writeln(
                "$what $state for {$grid->nick()}: $changed changed" . ($restart === [] ? '' : ', restart to apply: opensim restart ' . implode(' ', $restart)),
                OutputInterface::OUTPUT_RAW,
            );
        }
        foreach ($result['notes'] as $note) {
            $output->writeln("note: $note", OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
