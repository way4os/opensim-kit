<?php

namespace OpenSimKit\Setup;

use OpenSim\Config\GridConfig;
use OpenSim\Provisioning\Slug;
use OpenSimKit\System\Elevated;
use OpenSimKit\Ui\Back;
use OpenSimKit\Ui\EditConfig;
use OpenSimKit\Ui\Quit;
use OpenSim\Console\InstanceConsoleService;
use OpenSim\Models\Grid;
use OpenSim\Models\Region;
use OpenSim\Provisioning\PendingRestarts;
use OpenSim\Exceptions\SetupFailedException;
use OpenSim\Provisioning\SimulatorConfig;
use OpenSim\Provisioning\UiInterface;

/**
 * Setup hub: screens one level deeper each time, from the home to a region:
 *
 *   home          the core, the grids, add a grid
 *   grid          configure, its simulators, add a simulator, enable or disable
 *   simulator     reconfigure, enable or disable, its regions, add a region
 *   region        reconfigure, enable or disable
 *
 * Back goes up one level, Quit leaves the setup from any of them. A grid whose Robust is on another machine is only a
 * list of simulators here.
 *
 * The flow talks only to an InstallerUi, so a web frontend can render the same
 * screens later.
 */
final class Hub
{
    public function __construct(private UiInterface $ui, private ?RuntimeCheck $runtime = null) {}

    public function run(): void
    {
        // A core that cannot run on this machine is said at once, not when the first grid fails to start
        $this->runtime?->ensure();

        $first = true;
        while (true) {
            $config = new InstallConfig();
            $cores = $config->profiles();
            $activeCore = $config->defaultProfile();
            $etcRoot = $config->profile()['EtcRoot'] ?? '';
            $grids = $this->grids($etcRoot);

            $options = ['core' => 'OpenSim core ' . ($cores !== [] ? "[$activeCore]" : '(install)')];
            foreach ($grids as $nick) {
                $name = $this->ui->entity($nick);
                $options["grid:$nick"] = (Grid::find(InstallConfig::environmentOf(['EtcRoot' => $etcRoot]), $nick)?->isRemote() ?? false)
                    ? "$name (Robust on another machine)"
                    : $name . ($this->grid($etcRoot, $nick)?->isEnabled() ? '' : ' [disabled]');
            }
            $options['add'] = _('Add grid');
            $options['quit'] = _('Quit');

            $default = $cores === [] ? 'core' : ($grids === [] ? 'add' : "grid:{$grids[0]}");
            // A machine with a core and nothing else goes straight to what it is for: no menu to read first
            $choice = $first && $cores !== [] && $grids === [] ? 'add' : $this->ui->choose(_('OpenSim — setup'), $options, $default);
            $first = false;

            try {
                $quit = match (true) {
                    $choice === 'quit' => true,
                    $choice === 'core' => $this->coreMenu($cores),
                    $cores === [] => $this->ui->warn(_('Install an OpenSim core first.')) ?? false,
                    $choice === 'add' => $this->addGrid(),
                    default => $this->gridScreen(substr($choice, strlen('grid:'))),
                };
            } catch (Back) {
                // Escape in a screen: the menu it came from (here, the first one)
                $quit = false;
            } catch (Quit) {
                $quit = true;
            }
            if ($quit) {
                $this->offerRestarts();

                return;
            }
        }
    }

    /**
     * A flow that asks questions, which the user can give up with Escape: back to the screen that started
     * it, nothing was written (everything is written after the questions).
     *
     * @template T
     * @param \Closure():T $flow
     * @return T|null
     */
    private function guard(\Closure $flow): mixed
    {
        try {
            return $flow();
        } catch (Back) {
            $this->ui->note(_('Abandoned.'));

            return null;
        }
    }

    /** What the setup did that needs instances to restart: shown when it ends, to restart them or leave them. */
    private function offerRestarts(): void
    {
        $profile = (new InstallConfig())->profile();
        $etcRoot = $profile['EtcRoot'] ?? '';
        $pending = PendingRestarts::read(InstallConfig::environmentOf($profile));
        if ($pending === []) {
            return;
        }

        $lines = [];
        $running = false;
        foreach ($pending as $entry) {
            $lines[] = "  {$entry['instance']}: {$entry['reason']}";
            $running = $running || InstanceConsoleService::running("$etcRoot/opensim.d/{$entry['instance']}.ini");
        }
        $this->ui->note(_('To take these changes into account, restart:') . "\n" . implode("\n", $lines));
        if (!$this->ui->confirm(_('Restart them now?'), true)) {
            $this->ui->note(
                sprintf(
                    _('They stay listed in %s: the setup offers them again next time.'),
                    PendingRestarts::path(InstallConfig::environmentOf($profile)),
                ),
            );

            return;
        }
        // A simulator that runs warns its users and waits, or restarts at once
        $warn =
            $running && $this->ui->confirm(_('Warn the users of the simulators first (it takes two minutes)?'), false);
        if (!$this->act('restart', ['warn' => $warn])) {
            $this->ui->warn(_('Some instances did not restart: see above, they stay listed.'));
        }
    }

    /**
     * What a menu does to the install, by the system user of the install.
     *
     * @param array<string,mixed> $args
     */
    private function act(string $op, array $args): bool
    {
        $profile = (new InstallConfig())->profile();
        if (!Elevated::needed($profile)) {
            return (new Actions($this->ui))->perform($op, $args, $profile);
        }
        try {
            Elevated::run(
                $this->ui,
                '--apply-action',
                ['op' => $op, 'args' => $args, 'profile' => $profile],
                $profile['SystemUser'],
            );
        } catch (SetupFailedException) {
            return false;
        }

        return true;
    }

    /** @return bool whether to quit the setup */
    private function coreMenu(array $cores): bool
    {
        $options = [];
        foreach ($cores as $name) {
            $options[$name] = $name;
        }
        $options['new'] = _('Install a new version');
        $options['back'] = _('Back');
        $options['quit'] = _('Quit');

        $choice = $this->ui->choose(_('OpenSim core'), $options, 'new');
        if ($choice === 'new') {
            (new Installer($this->ui))->run();
        } elseif ($choice !== 'back' && $choice !== 'quit') {
            (new InstallConfig())->setDefaultProfile($choice);
        }

        return $choice === 'quit';
    }

    /**
     * A grid run from this machine, or one run elsewhere, of which only its simulators are set up
     * here. What is configured is where the setup goes next: its screen, ready to add a simulator.
     *
     * @return bool whether to quit the setup
     */
    private function addGrid(): bool
    {
        $start = (new StartSetup($this->ui))->ask();
        $made = $this->guard(fn() => $this->make($start));

        return $made !== null && ($start['type'] === StartSetup::GRID ? $this->ready($made[0], $made[1]) : $this->simulatorScreen($made[0], $made[1]));
    }

    /**
     * What the start of the setup asked, made: a grid and its first simulator, or a simulator that joins a grid. Quick shows the plan
     * and writes it once accepted (when it is not, every question is asked, with the answers proposed).
     *
     * @param array{type:string,mode:string,data:array<string,mixed>} $start
     * @return ?array{0:string,1:string} the grid and its simulator
     */
    private function make(array $start): ?array
    {
        $runner = new SetupRunner($this->ui);
        $advanced = $start['mode'] === StartSetup::ADVANCED;
        if ($start['type'] === StartSetup::SIMULATOR) {
            return $runner->runJoin($start['data'], $advanced);
        }
        if ($advanced) {
            return $runner->run($start['data'], [], '', true);
        }
        try {
            return $runner->runQuick($start['data']);
        } catch (EditConfig) {
            // Nothing is written yet: every question again, with what was typed and what the setup chose as the answers proposed
            return $runner->run($start['data'], [], '', true);
        }
    }

    /**
     * What the user sees when a grid and its first region are made: it is finished, and what remains
     * is optional. Finishing is what is proposed.
     *
     * @return bool whether to quit the setup
     */
    private function ready(string $nick, string $slug): bool
    {
        $profile = (new InstallConfig())->profile();
        $grid = Grid::find(InstallConfig::environmentOf($profile), $nick);
        $this->ui->note(
            sprintf(
                _('Your grid %s is ready, nothing more is needed.'),
                $this->ui->entity($grid !== null && $grid->name() !== '' ? $grid->name() : $nick),
            ) .
                ($grid !== null && !$grid->isRemote()
                    ? "\n" . sprintf(_('Login URI: %s'), "http://{$grid->baseHostname()}:{$grid->publicPort()}")
                    : '') .
                "\n" .
                _('You can finish now, or continue to add regions and simulators and change settings.'),
        );
        $choice = $this->ui->choose(_('Setup'), ['quit' => _('Finish setup'), 'more' => _('Continue setup')], 'quit');

        return $choice === 'quit' || $this->simulatorScreen($nick, $slug);
    }

    /** @return bool whether to quit the setup */
    private function gridScreen(string $nick): bool
    {
        while (true) {
            $etcRoot = (new InstallConfig())->profile()['EtcRoot'] ?? '';
            $remote = (Grid::find(InstallConfig::environmentOf(['EtcRoot' => $etcRoot]), $nick)?->isRemote() ?? false);
            $enabled = $this->grid($etcRoot, $nick)?->isEnabled() ?? false;
            $simulators = $this->simulators($etcRoot, $nick);

            // The grid is its regions: the simulators are the pool of servers that provide them
            $options = [];
            foreach ($simulators as $slug) {
                foreach (Region::inDirectory(GridConfig::simulatorsDirectory("$etcRoot/grids/$nick") . "/$slug/regions") as $region) {
                    $name = $region->name();
                    $options["region:$slug:$name"] =
                        $this->ui->entity($name) .
                        ' [' .
                        $this->simulatorName($nick, $slug) .
                        ']' .
                        ($region->isEnabled() ? '' : ' [disabled]');
                }
            }
            $options['addregion'] = _('Add region');
            $options['simulators'] = _('Simulators');
            if (!$remote) {
                $options['configure'] = _('Configure grid');
                $options['toggle'] = $enabled ? 'Disable this grid' : 'Enable this grid';
            }
            $options['back'] = _('Back');
            $options['quit'] = _('Quit');

            $choice = $this->ui->choose(
                'Grid: ' . $this->ui->entity($nick) . ($remote ? ' (Robust on another machine)' : ''),
                $options,
                array_key_first($options),
            );
            if ($choice === 'back' || $choice === 'quit') {
                return $choice === 'quit';
            }
            switch (true) {
                case $choice === 'configure':
                    $this->guard(fn() => (new NewGrid($this->ui))->run($nick));
                    break;
                case $choice === 'addregion':
                    $made = $this->guard(fn() => (new NewSimulator($this->ui))->addRegionToGrid($nick));
                    if ($made !== null && $this->simulatorScreen($made[0], $made[1])) {
                        return true;
                    }
                    break;
                case $choice === 'simulators':
                    if ($this->poolScreen($nick)) {
                        return true;
                    }
                    break;
                case $choice === 'toggle':
                    if ($this->act($enabled ? 'grid-disable' : 'grid-enable', ['nick' => $nick])) {
                        $this->ui->note(($enabled ? 'Disabled' : 'Enabled') . " $nick.");
                    }
                    break;
                default:
                    [, $slug, $name] = explode(':', $choice, 3);
                    if ($this->regionScreen($nick, $slug, $name)) {
                        return true;
                    }
            }
        }
    }

    /**
     * The simulators of a grid, the pool of servers that provide its regions.
     *
     * @return bool whether to quit the setup
     */
    private function poolScreen(string $nick): bool
    {
        while (true) {
            $etcRoot = (new InstallConfig())->profile()['EtcRoot'] ?? '';
            $simulators = $this->simulators($etcRoot, $nick);
            $options = [];
            foreach ($simulators as $slug) {
                $options["simulator:$slug"] =
                    $this->ui->entity($this->simulatorTitle($etcRoot, $nick, $slug)) .
                    ($this->grid($etcRoot, $nick)?->simulator($slug)?->isEnabled() ? '' : ' [disabled]');
            }
            $options['addsimulator'] = _('Add simulator');
            $options['back'] = _('Back');
            $options['quit'] = _('Quit');

            $choice = $this->ui->choose(
                'Simulators of ' . $this->ui->entity($nick),
                $options,
                $simulators !== [] ? "simulator:{$simulators[0]}" : 'addsimulator',
            );
            if ($choice === 'back' || $choice === 'quit') {
                return $choice === 'quit';
            }
            if ($choice === 'addsimulator') {
                $made = $this->guard(fn() => (new NewSimulator($this->ui))->run($nick));
                if ($made !== null && $this->simulatorScreen($made[0], $made[1])) {
                    return true;
                }
            } elseif ($this->simulatorScreen($nick, substr($choice, strlen('simulator:')))) {
                return true;
            }
        }
    }

    /**
     * A simulator and its regions. Adding a region is the usual next step, so it is the one
     * proposed, also after a region was added.
     *
     * @return bool whether to quit the setup
     */
    private function simulatorScreen(string $nick, string $slug): bool
    {
        while (true) {
            $etcRoot = (new InstallConfig())->profile()['EtcRoot'] ?? '';
            $enabled = $this->grid($etcRoot, $nick)?->simulator($slug)?->isEnabled() ?? false;
            $regions = Region::inDirectory(GridConfig::simulatorsDirectory("$etcRoot/grids/$nick") . "/$slug/regions");

            $options = [];
            foreach ($regions as $region) {
                $options['region:' . $region->name()] = $this->ui->entity($region->name()) . ($region->isEnabled() ? '' : ' [disabled]');
            }
            // In this simulator: not to be mistaken for the one of the grid, which asks where the region goes
            $options['addregion'] = sprintf(_('Add region in %s'), $this->simulatorName($nick, $slug));
            $options['reconfigure'] = _('Configure simulator');
            $options['toggle'] = $enabled ? 'Disable this simulator' : 'Enable this simulator';
            $options['back'] = _('Back');
            $options['quit'] = _('Quit');

            $choice = $this->ui->choose(
                'Simulator: ' . $this->ui->entity($this->simulatorTitle($etcRoot, $nick, $slug)),
                $options,
                'addregion',
            );
            if ($choice === 'back' || $choice === 'quit') {
                return $choice === 'quit';
            }
            switch (true) {
                case $choice === 'reconfigure':
                    $this->guard(fn() => (new NewSimulator($this->ui))->run($nick, $this->simulatorName($nick, $slug)));
                    break;
                case $choice === 'addregion':
                    $this->guard(fn() => (new NewSimulator($this->ui))->addRegion($nick, $this->simulatorName($nick, $slug)));
                    break;
                case $choice === 'toggle':
                    $done = $this->act($enabled ? 'simulator-disable' : 'simulator-enable', [
                        'slug' => $slug,
                        'ini' => GridConfig::simulatorsDirectory("$etcRoot/grids/$nick") . "/$slug.ini",
                    ]);
                    if ($done) {
                        $this->ui->note(($enabled ? 'Disabled' : 'Enabled') . " $slug.");
                    }
                    break;
                default:
                    if ($this->regionScreen($nick, $slug, substr($choice, strlen('region:')))) {
                        return true;
                    }
            }
        }
    }

    /** @return bool whether to quit the setup */
    private function regionScreen(string $nick, string $slug, string $name): bool
    {
        while (true) {
            $etcRoot = (new InstallConfig())->profile()['EtcRoot'] ?? '';
            $region = null;
            foreach (Region::inDirectory(GridConfig::simulatorsDirectory("$etcRoot/grids/$nick") . "/$slug/regions") as $candidate) {
                $candidate->name() === $name && $region = $candidate;
            }
            if ($region === null) {
                return false;
            }

            $choice = $this->ui->choose(
                'Region: ' . $this->ui->entity($name),
                [
                    'reconfigure' => _('Reconfigure'),
                    'toggle' => $region->isEnabled() ? 'Disable this region' : 'Enable this region',
                    'back' => _('Back'),
                    'quit' => _('Quit'),
                ],
                'back',
            );

            switch ($choice) {
                case 'reconfigure':
                    $this->guard(
                        fn() => (new NewSimulator($this->ui))->reconfigureRegion($nick, $this->simulatorName($nick, $slug), $name),
                    );
                    break;
                case 'toggle':
                    $done = $this->act($region->isEnabled() ? 'region-disable' : 'region-enable', [
                        'file' => $region->file(),
                        'name' => $name,
                        'instance' => $slug,
                    ]);
                    if ($done) {
                        $this->ui->note(
                            sprintf(
                                $region->isEnabled()
                                    ? _('Disabled %s: the simulator takes it into account when it restarts.')
                                    : _('Enabled %s: the simulator takes it into account when it restarts.'),
                                $name,
                            ),
                        );
                    }
                    break;
                default:
                    return $choice === 'quit';
            }
        }
    }

    /** The name of a simulator as it was given, else (its config has no mention of it) as its instance name tells it. */
    private function simulatorTitle(string $etcRoot, string $nick, string $slug): string
    {
        return SimulatorConfig::readName(GridConfig::simulatorsDirectory("$etcRoot/grids/$nick") . "/$slug.ini") ?? $this->simulatorName($nick, $slug);
    }

    /** The name of a simulator from the name of its instance: what follows the grid nick. */
    private function simulatorName(string $nick, string $slug): string
    {
        $etcRoot = (new InstallConfig())->profile()['EtcRoot'] ?? '';
        if (($name = SimulatorConfig::readName(GridConfig::simulatorsDirectory("$etcRoot/grids/$nick") . "/$slug.ini")) !== null) {
            return $name;
        }
        return Slug::simulatorOf($slug, $nick);
    }

    /** @return list<string> grid nicks under EtcRoot/grids */
    private function grids(string $etcRoot): array
    {
        return $this->dirNames($etcRoot === '' ? '' : "$etcRoot/grids");
    }

    /** @return list<string> instance names of the simulators of a grid */
    private function simulators(string $etcRoot, string $grid): array
    {
        return $this->grid($etcRoot, $grid)?->simulatorNames() ?? [];
    }

    /** The grid of this nick under EtcRoot, null when there is none. */
    private function grid(string $etcRoot, string $nick): ?Grid
    {
        return $etcRoot === '' ? null : Grid::find(InstallConfig::environmentOf(['EtcRoot' => $etcRoot]), $nick);
    }

    /** @return list<string> */
    private function dirNames(string $path): array
    {
        if ($path === '' || !is_dir($path)) {
            return [];
        }

        $names = [];
        foreach (glob("$path/*", GLOB_ONLYDIR) ?: [] as $dir) {
            $names[] = basename($dir);
        }

        return $names;
    }
}
