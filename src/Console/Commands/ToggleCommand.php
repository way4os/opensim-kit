<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Archives\Instances;
use OpenSim\Models\GridInfo;
use OpenSim\Models\GridState;
use OpenSim\Models\SimState;
use OpenSim\Provisioning\Features;
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
 * And with a feature after the grid, what the feature needs in the files of the grid (see Features):
 *
 *   opensim enable|disable <grid> search [<url prefix>]
 *   opensim enable|disable <grid> economy [beta|gloebit] [<url prefix>]
 *   opensim enable|disable <grid> profiles
 */
abstract class ToggleCommand extends EngineCommand
{
    private const USAGE = "usage: opensim enable|disable <instance>...\n" .
        "       opensim enable|disable <grid> search [<url prefix>]\n" .
        "       opensim enable|disable <grid> economy [beta|gloebit] [<url prefix>]\n" .
        "       opensim enable|disable <grid> profiles\n";

    /** `enable` or `disable`. */
    abstract protected function verb(): string;

    protected function configure(): void
    {
        $verb = $this->verb();
        $this->setName($verb)
            ->setDescription(ucfirst($verb) . ' a grid or a simulator, or a feature of a grid (search, economy, profiles)')
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
        $layout = $this->engine($output)->layout;
        $profile = $layout->all();
        $etc = $layout->etcRoot();

        // A feature of a grid: the grid, then the word of the feature
        if (isset($names[1]) && Features::isFeature($names[1])) {
            return $this->feature($names, $profile, $etc, $output, $error);
        }

        // The grids and the simulators the setup made, by the name of their instance
        $instances = [];
        foreach (glob("$etc/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
            $nick = basename($dir);
            if (GridState::robustIni($etc, $nick) !== null) {
                $instances[GridInfo::instanceName($nick)] = ['kind' => 'grid', 'nick' => $nick, 'enabled' => GridState::isEnabled($etc, $nick)];
            }
            foreach (SimState::names($dir) as $slug) {
                $instances[$slug] = ['kind' => 'simulator', 'slug' => $slug, 'ini' => "$dir/sims/$slug.ini", 'enabled' => SimState::isEnabled($etc, $slug)];
            }
        }

        $enable = $verb === 'enable';
        $failed = false;
        foreach ($names as $name) {
            $instance = $instances[GridInfo::instanceName($name)] ?? null;
            if ($instance === null) {
                $error->writeln(
                    "$verb: no grid or simulator called $name" . ($instances === [] ? '' : ' (known: ' . implode(', ', array_keys($instances)) . ')'),
                    OutputInterface::OUTPUT_RAW,
                );
                $failed = true;

                continue;
            }
            if ($instance['enabled'] === $enable) {
                $output->writeln("{$instance['kind']} $name is already {$verb}d", OutputInterface::OUTPUT_RAW);

                continue;
            }
            if ($this->perform($instance, $enable, $etc, $error)) {
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
     * @param array<string,mixed> $instance
     */
    private function perform(array $instance, bool $enable, string $etc, OutputInterface $error): bool
    {
        $grid = $instance['kind'] === 'grid';
        if ($enable) {
            $done = $grid ? GridState::enable($etc, $instance['nick']) : SimState::enable($etc, $instance['slug'], $instance['ini']);
            $done || $error->writeln($grid ? 'Cannot enable the grid (no Robust config).' : 'Cannot enable the simulator (no config).', OutputInterface::OUTPUT_RAW);

            return $done;
        }
        $grid ? GridState::disable($etc, $instance['nick']) : SimState::disable($etc, $instance['slug']);
        $done = $grid ? !GridState::isEnabled($etc, $instance['nick']) : !SimState::isEnabled($etc, $instance['slug']);
        $done || $error->writeln($grid ? 'Cannot disable the grid (permission).' : 'Cannot disable the simulator (permission).', OutputInterface::OUTPUT_RAW);

        return $done;
    }

    /**
     * A feature of a grid.
     *
     * @param list<string> $names the grid, the feature, its options
     * @param array<string,string> $profile
     */
    private function feature(array $names, array $profile, string $etc, OutputInterface $output, OutputInterface $error): int
    {
        $verb = $this->verb();
        $nick = null;
        foreach (Instances::grids($etc) as $candidate) {
            if (strcasecmp($candidate, $names[0]) === 0) {
                $nick = $candidate;
            }
        }
        $grid = $nick === null ? null : GridInfo::load($profile, $nick);
        if ($grid === null) {
            $error->writeln("$verb: no grid called {$names[0]}", OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
        $feature = strtolower($names[1]);
        $options = [];
        $more = array_slice($names, 2);
        if ($feature === Features::ECONOMY && isset($more[0]) && !str_contains($more[0], '/')) {
            $options['money'] = strtolower(array_shift($more));
        }
        if (count($more) > 1 || ($more !== [] && $feature === Features::PROFILES)) {
            $error->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        if ($more !== []) {
            $options['prefix'] = $more[0];
        }

        $features = new Features($grid, $profile);
        $result = $verb === 'enable' ? $features->enable($feature, $options) : $features->disable($feature);
        if ($result['error'] !== null) {
            $error->writeln("$verb: {$result['error']}", OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
        $state = $verb . 'd';
        $what = $feature . ($feature === Features::ECONOMY && $verb === 'enable' ? ' (' . ($options['money'] ?? 'beta') . ')' : '');
        $touched = $result['sims'];
        if ($touched === [] && !$result['grid'] && !$result['robust']) {
            $output->writeln(
                "nothing to change: $what is already $state for {$grid->nick}" . ($features->simulators() === [] ? ' (the grid has no simulator on this machine)' : ''),
                OutputInterface::OUTPUT_RAW,
            );
        } else {
            // What the simulators and Robust read at their start; the OpenSim.ini of the grid is read by the helpers each time
            $restart = $touched;
            if ($result['robust']) {
                array_unshift($restart, GridInfo::instanceName($grid->nick));
            }
            $parts = array_filter([
                $touched === [] ? null : count($touched) . ' simulator' . (count($touched) === 1 ? '' : 's'),
                $result['robust'] ? 'Robust' : null,
                $result['grid'] ? 'the OpenSim.ini of the grid' : null,
            ]);
            $changed = count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts) : (string) reset($parts);
            $output->writeln(
                "$what $state for {$grid->nick}: $changed changed" . ($restart === [] ? '' : ', restart to apply: opensim restart ' . implode(' ', $restart)),
                OutputInterface::OUTPUT_RAW,
            );
        }
        foreach ($result['notes'] as $note) {
            $output->writeln("note: $note", OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
