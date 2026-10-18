<?php

namespace OpenSimKit\Setup;

use OpenSimKit\System\Elevated;
use OpenSimKit\Ui\FieldForm;
use OpenSim\Forms\FieldSet;
use OpenSim\Config\GridFileService;
use OpenSim\Console\InstanceConsoleService;
use OpenSim\Models\Grid;
use OpenSim\Models\GridInfo;
use OpenSim\Models\Region;
use OpenSim\Models\Simulator;
use OpenSim\Provisioning\DatabaseService;
use OpenSim\Provisioning\LocationFinder;
use OpenSim\Provisioning\RegionPlanner;
use OpenSim\Provisioning\RemoteGridPlanner;
use OpenSim\Exceptions\SetupFailedException;
use OpenSim\Provisioning\SimPlan;
use OpenSim\Provisioning\SimPlanner;
use OpenSim\Provisioning\UiInterface;
use OpenSim\Runtime\Environment;

/**
 * Create (or reconfigure) one simulator of a grid, with its first region.
 *
 * Same shape as NewGrid: the questions, and the database with the rights of
 * the user who started the setup, are handled here; the files and the
 * instances belong to the system user of the install (see Elevated).
 *
 * A simulator has its own database (its regions, its estates) and an estate
 * whose owner is an account of the grid: OpenSimulator cannot create it, so
 * the account is chosen among the grid's, or created in the grid first.
 */
final class NewSim
{
    private const LANDING = 'At least a default landing region should be created.';
    private const FIRST = 'Welcome';

    public function __construct(private UiInterface $ui) {}

    /**
     * @param ?string $gridNick the grid it joins, asked when there are several and none is given
     * @param ?string $simName  the simulator to modify, asked when none is given
     * @param ?array{simulator?:array<string,mixed>,owner?:array<string,string>,region?:array<string,mixed>} $values the answers of a setup
     *        file, by the names of the fields (SetupFile::simValues()...): nothing is asked unless an answer is refused; with $edit,
     *        every question is asked with them as the answers proposed
     * @return ?array{0:string,1:string} the grid and the instance of the simulator configured, null when nothing was
     */
    public function run(?string $gridNick = null, ?string $simName = null, bool $external = false, ?array $values = null, bool $edit = false): ?array
    {
        $profile = (new InstallConfig())->profile();
        $etcRoot = $profile['EtcRoot'] ?? '';
        if ($etcRoot === '') {
            $this->ui->error(_('No installed framework found. Install an OpenSim core first.'));

            return null;
        }

        $grid = $this->grid($profile, $gridNick, $external);
        if ($grid === null) {
            return null;
        }

        $plan = $this->gather($grid, $profile, new DatabaseService($this->ui), $simName, null, $values, $edit);
        if ($plan === null) {
            return null;
        }

        return $this->complete($plan, $grid, $profile, false);
    }

    /**
     * The questions of a simulator and of its first region, nothing is made. The grid can be one that is only planned
     * (GridInfo::fromPlan): the plan of the simulator is then shown with the one of the grid (the quick setup).
     */
    public function prepare(GridInfo $grid, array $profile, ?string $simName = null, ?array $values = null, bool $edit = false): ?SimPlan
    {
        return $this->gather($grid, $profile, new DatabaseService($this->ui), $simName, null, $values, $edit);
    }

    /** What the plan will do, as the setup shows it. */
    public function describe(SimPlan $plan): string
    {
        return $this->planText($plan);
    }

    /**
     * Make the simulator of a plan: its database, then the files and the instance. The plan is shown and has to be
     * accepted, unless it was already ($accepted).
     *
     * @param array<string,mixed> $profile
     * @return ?array{0:string,1:string} the grid and the instance of the simulator configured, null when nothing was
     */
    public function complete(SimPlan $plan, GridInfo $grid, array $profile, bool $accepted): ?array
    {
        $database = new DatabaseService($this->ui);
        // The account may have had its password entered since this plan was made: that one is the password of the account
        DatabaseService::applyRemembered($plan);

        // Nothing is written before the database of the simulator works
        $check = $plan->databasePlan();
        $attempts = 0;
        while (($result = $database->ensure($check)) !== DatabaseService::OK) {
            if ($result === DatabaseService::ABORT) {
                $this->ui->error(_('Stopped, nothing was changed: OpenSim cannot run without its database.'));

                throw new SetupFailedException('database');
            }
            // The error is on screen: the settings are asked again at once, they may be what is wrong
            if (++$attempts > 10) {
                $this->ui->error(_('Stopped, nothing was changed: OpenSim cannot run without its database.'));

                throw new SetupFailedException('database');
            }
            $this->askDatabase($plan);
            $check = $plan->databasePlan();
        }

        if (!$accepted) {
            $this->showPlan($plan);
        }
        // A simulator already linked may run, with users in it: restarting it warns them, and takes two minutes
        $simulator = new Simulator(Environment::forProfile($profile), $plan->iniPath());
        $wasEnabled = $simulator->isEnabled();
        $running = $wasEnabled && InstanceConsoleService::running($simulator->link());
        // A simulator without a region waits at its first start for the answers of a region to create, on a console nobody
        // reads: it is not started before it has one
        $hasRegion = $plan->createRegion || (glob("{$plan->regionsDir()}/*.ini") ?: []) !== [];
        if (!$hasRegion) {
            $this->ui->note(_('This simulator has no region yet: it is not started before it has one (Add region).'));
        }
        // What to do once written is asked here, while the user is at the keyboard (writing may be done by another
        // process), with the confirmation of the plan
        $v = $this->ui->form(self::applyQuestions($plan->simName, $accepted, $hasRegion, $running), _('Apply'));
        if (($v['apply'] ?? 'yes') === 'no') {
            $this->ui->note(_('Aborted — nothing changed.'));

            return null;
        }
        $plan->enable = $v['enable'] === 'yes';
        $plan->start = $plan->enable && ($v['start'] ?? 'no') === 'yes';
        $plan->warnUsers = $plan->start && $wasEnabled && $running && ($v['warn'] ?? 'no') === 'yes';

        $this->write($plan, $profile);

        return [$grid->nick, $plan->slug];
    }

    /**
     * What to do once the simulator is written: confirm it (unless the plan was accepted already), enable it, start it, and
     * warn the users when it is running. A question that is not asked counts as a yes for the ones that depend on it.
     *
     * @return list<array<string,mixed>>
     */
    public static function applyQuestions(string $name, bool $accepted, bool $hasRegion, bool $running): array
    {
        $go = static fn(array $v): bool => ($v['apply'] ?? '') !== 'no';

        return [
            [
                'key' => 'apply',
                'label' => sprintf(_("Apply this configuration to simulator '%s'?"), $name),
                'type' => 'confirm',
                'default' => 'yes',
                'when' => static fn(array $v): bool => !$accepted,
            ],
            [
                'key' => 'enable',
                'label' => _('Enable simulator (link into opensim.d)'),
                'type' => 'confirm',
                'default' => 'yes',
                'when' => $go,
                'condition' => ['apply' => ['yes']],
            ],
            [
                'key' => 'start',
                'label' => _('Start simulator now'),
                'type' => 'confirm',
                'default' => 'yes',
                'when' => static fn(array $v): bool => $go($v) && ($v['enable'] ?? 'yes') === 'yes' && $hasRegion,
                'condition' => ['apply' => ['yes'], 'enable' => ['yes']],
            ],
            [
                'key' => 'warn',
                'label' => _('The simulator is running: warn its users and wait two minutes before restarting it?'),
                'type' => 'confirm',
                'default' => 'no',
                'when' => static fn(array $v): bool => $go($v) && ($v['start'] ?? 'no') === 'yes' && $running,
                'condition' => ['apply' => ['yes'], 'start' => ['yes']],
            ],
        ];
    }

    /** Write as the user who owns the install (see Elevated). */
    private function write(SimPlan $plan, array $profile): void
    {
        if (!Elevated::needed($profile)) {
            $this->apply($plan, $profile);

            return;
        }
        Elevated::run(
            $this->ui,
            '--apply-sim',
            ['plan' => $plan->toArray(), 'profile' => $profile],
            $profile['SystemUser'],
        );
    }

    /**
     * Add a region to a grid: the region first, then the simulator that takes it, a new one or one of the pool of the grid.
     *
     * @return ?array{0:string,1:string} the grid and the instance of the simulator that has the region, null when nothing was
     */
    public function addRegionToGrid(string $gridNick): ?array
    {
        $profile = (new InstallConfig())->profile();
        $grid = $this->grid($profile, $gridNick);
        if ($grid === null) {
            return null;
        }
        $database = new DatabaseService($this->ui);
        $pool = [];
        foreach (Grid::at(Environment::forProfile($profile), $grid->dir)->simulatorNames() as $slug) {
            $pool[$slug] = $this->simTitle($grid, $slug);
        }

        // The ports of a region are in the block of its simulator: the first of the pool is the one proposed
        $probe =
            $pool === []
                ? new SimPlan()
                : $this->regionPlan($grid, $this->simNameOf($grid, (string) array_key_first($pool)));
        if ($probe === null) {
            return null;
        }
        if ($pool === []) {
            $probe->gridNick = $grid->nick;
            $probe->gridDir = $grid->dir;
        }
        $planner = RegionPlanner::forSim(
            $probe,
            $grid,
            $database,
            false,
            $pool,
            '',
            static fn(string $instance): int => (int) (GridInfo::parse("{$grid->dir}/sims/$instance.ini")['httpPort'] ?? 0),
        );
        $answers = $this->regionAnswers($planner, $grid);

        if (($answers['sim'] ?? '+') === '+') {
            $plan = $this->gather($grid, $profile, $database, null, $answers);

            return $plan === null ? null : $this->complete($plan, $grid, $profile, false);
        }

        $slug = $answers['sim'];
        $plan = $this->regionPlan($grid, $this->simNameOf($grid, $slug));
        if ($plan === null) {
            return null;
        }
        RegionPlanner::forSim($plan, $grid, $database)->apply($plan, $answers);
        $this->ui->note(
            sprintf(
                _("Region %s at %s, port %s, for simulator '%s'."),
                $plan->regionName,
                $plan->regionLocation,
                $plan->regionPort,
                $pool[$slug],
            ),
        );
        $v = $this->ui->form(
            [
                [
                    'key' => 'add',
                    'label' => sprintf(_("Add region '%s'?"), $plan->regionName),
                    'type' => 'confirm',
                    'default' => 'yes',
                ],
                [
                    'key' => 'start',
                    'label' => _('Load it now (starting the simulator when it is not running)?'),
                    'type' => 'confirm',
                    'default' => 'yes',
                    'when' => static fn(array $v): bool => $v['add'] === 'yes',
                ],
            ],
            _('Apply'),
        );
        if ($v['add'] === 'no') {
            $this->ui->note(_('Aborted — nothing changed.'));

            return null;
        }
        $plan->start = $v['start'] === 'yes';
        $this->write($plan, $profile);

        return [$grid->nick, $slug];
    }

    /** A simulator of the pool as the person sees it: its name and the regions it has */
    private function simTitle(GridInfo $grid, string $slug): string
    {
        $regions = array_map(static fn(Region $region): string => $region->name(), Region::inDirectory("{$grid->dir}/sims/$slug/regions"));

        return $this->simNameOf($grid, $slug) . ($regions !== [] ? ' (' . implode(', ', $regions) . ')' : '');
    }

    /** The name a simulator was given, from its instance name (the grid's nick and an underscore start it) */
    private function simNameOf(GridInfo $grid, string $slug): string
    {
        $prefix = GridInfo::instanceName($grid->nick) . '_';

        return str_starts_with($slug, $prefix) ? substr($slug, strlen($prefix)) : $slug;
    }

    /**
     * Add a region to a simulator already configured: loaded at once when the
     * simulator runs, else the simulator is started.
     */
    public function addRegion(string $gridNick, string $simName, ?array $values = null, bool $edit = false): void
    {
        $profile = (new InstallConfig())->profile();
        $grid = $this->grid($profile, $gridNick);
        $plan = $grid === null ? null : $this->regionPlan($grid, $simName);
        if ($plan === null) {
            return;
        }

        $this->askRegion($plan, $grid, new DatabaseService($this->ui), false, $values, $values !== null && !$edit);
        if (is_file($plan->regionIni())) {
            $this->ui->error(sprintf(_('A region is already described in %s.'), $plan->regionIni()));

            return;
        }
        $this->ui->note(
            sprintf(
                _("Region %s at %s, port %s, for simulator '%s'."),
                $plan->regionName,
                $plan->regionLocation,
                $plan->regionPort,
                $simName,
            ),
        );
        $v = $this->ui->form(
            [
                [
                    'key' => 'add',
                    'label' => sprintf(_("Add region '%s'?"), $plan->regionName),
                    'type' => 'confirm',
                    'default' => 'yes',
                ],
                [
                    'key' => 'start',
                    'label' => _('Load it now (starting the simulator when it is not running)?'),
                    'type' => 'confirm',
                    'default' => 'yes',
                    'when' => static fn(array $v): bool => $v['add'] === 'yes',
                ],
            ],
            _('Apply'),
        );
        if ($v['add'] === 'no') {
            $this->ui->note(_('Aborted — nothing changed.'));

            return;
        }
        $plan->start = $v['start'] === 'yes';
        $this->write($plan, $profile);
    }

    /** The plan of a region of a simulator already configured, null when it is not. */
    private function regionPlan(GridInfo $grid, string $simName): ?SimPlan
    {
        $plan = new SimPlan();
        $plan->gridNick = $grid->nick;
        $plan->gridName = $grid->name;
        $plan->gridDir = $grid->dir;
        $plan->hypergrid = $grid->hypergrid;
        $plan->logsDirectory = $grid->logsDirectory;
        $plan->simName = $simName;
        $plan->slug = GridInfo::instanceName($grid->nick . '_' . $simName);
        $plan->regionOnly = true;
        $plan->createRegion = true;
        if (!is_file($plan->iniPath())) {
            $this->ui->error(sprintf(_("Simulator '%s' is not configured."), $simName));

            return null;
        }
        $plan->httpPort = (int) (GridInfo::parse($plan->iniPath())['httpPort'] ?? 0);
        // The address the regions of this simulator already announce
        foreach (glob("{$plan->regionsDir()}/*.ini") ?: [] as $file) {
            if (preg_match('/^\s*ExternalHostName\s*=\s*(\S+)/m', GridFileService::read($file), $m)) {
                $plan->externalHost = $m[1];
                break;
            }
        }

        return $plan;
    }

    /**
     * Change the place and the port of a region that exists: its name and its UUID
     * are its identity in the grid, they stay. Taken into account when the simulator
     * starts again.
     */
    public function reconfigureRegion(string $gridNick, string $simName, string $regionName): void
    {
        $profile = (new InstallConfig())->profile();
        $grid = $this->grid($profile, $gridNick);
        $plan = $grid === null ? null : $this->regionPlan($grid, $simName);
        if ($plan === null) {
            return;
        }
        $region = null;
        foreach (Region::inDirectory($plan->regionsDir()) as $candidate) {
            $candidate->name() === $regionName && $region = $candidate;
        }
        if ($region === null || !$region->isEnabled()) {
            $this->ui->warn(sprintf(_("Region '%s' is disabled or unknown: enable it first."), $regionName));

            return;
        }

        $plan->regionName = $regionName;
        $plan->regionUuid = $region->uuid() ?? RegionPlanner::uuid();
        $plan->regionSize = $region->width();
        $plan->externalHost = $region->externalHost() ?? $plan->externalHost;
        $plan->overwriteRegion = true;
        $plan->start = false;

        // Its place and its port: the rest of the region is its identity in the grid
        $database = new DatabaseService($this->ui);
        $planner = RegionPlanner::forSim($plan, $grid, $database)->fixed();
        $fields = new FieldSet([
            ...$planner->fields()->all(),
            ['name' => 'apply', 'type' => 'boolean', 'label' => sprintf(_("Change region '%s'?"), $regionName)],
        ]);
        $proposed = ['location' => $current['Location'] ?? '', 'port' => $current['InternalPort'] ?? (string) $plan->regionPort, 'apply' => true];
        $v = FieldForm::ask($this->ui, $fields, $proposed, sprintf(_("Region '%s'"), $regionName)) + $proposed;
        if ($v['apply'] === false) {
            $this->ui->note(_('Aborted — nothing changed.'));

            return;
        }
        $plan->regionLocation = $this->confirmPlace($planner, $grid, $v['location'], $current['Location'] ?? null);
        $plan->regionPort = (int) $v['port'];
        $this->write($plan, $profile);
        $this->ui->note(
            sprintf(
                _("The simulator '%s' restarts to apply it when you quit the setup, or now: opensim restart %s"),
                $simName,
                $plan->slug,
            ),
        );
    }

    /** The writing itself, done by SimulatorProvisioner. */
    public function apply(SimPlan $plan, array $profile): void
    {
        (new \OpenSim\Provisioning\SimulatorProvisioner($this->ui))->apply($plan, $profile);
    }












    /**
     * The grid the simulator joins: the given one, the only one, or a choice among
     * the known ones and "another grid", whose Robust is on another machine. With
     * no grid at all, the question is the address of that Robust.
     */
    private function grid(array $profile, ?string $nick, bool $external = false): ?GridInfo
    {
        $etcRoot = $profile['EtcRoot'];
        if ($external) {
            return $this->remoteGrid($profile);
        }
        if ($nick === null) {
            $known = [];
            foreach (glob("$etcRoot/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if (Grid::find(Environment::forProfile($profile), $name)?->robustIni() !== null) {
                    $known[$name] = $name;
                } elseif (GridInfo::isRemote($etcRoot, $name)) {
                    $known[$name] = "$name (Robust on another machine)";
                }
            }
            if ($known === []) {
                return $this->remoteGrid($profile);
            }
            $known['+'] = 'Another grid, whose Robust is on another machine';
            $nick = $this->ui->choose(_('Grid of the simulator'), $known, (string) array_key_first($known));
            if ($nick === '+') {
                return $this->remoteGrid($profile);
            }
        }

        $grid = GridInfo::load($profile, $nick);
        if ($grid === null) {
            $this->ui->error(sprintf(_("Grid '%s' is not known here."), $nick));
        }

        return $grid;
    }

    /**
     * A grid whose Robust runs elsewhere (another machine, another container): only what a simulator needs to join it is asked, and
     * kept for the next ones. The grid tells its name and nick itself. A grid there is a preset for is joined with what the preset
     * says, nothing else to ask.
     */
    private function remoteGrid(array $profile): ?GridInfo
    {
        $planner = RemoteGridPlanner::forMachine($profile);
        $preset = '';
        $presets = $planner->presets();
        if ($presets !== []) {
            $options = [];
            foreach ($presets as $name => $gridName) {
                $options[$name] = $gridName === $name ? $name : "$name ($gridName)";
            }
            $options['-'] = _('Another grid');
            $preset = $this->ui->choose(_('Grid to join'), $options, '-');
            $preset = $preset === '-' ? '' : $preset;
        }
        $answers = $preset !== '' ? $planner->presetAnswers($preset) : null;
        if ($answers === null) {
            $preset = '';
            $this->ui->note(
                _(
                    'The simulator joins a grid whose Robust server runs on another machine (or in another container): it needs its address and its ports.',
                ),
            );
            $answers = FieldForm::ask($this->ui, $planner->fields(), $planner->resolve(), _('Remote grid'));
        }

        $result = $planner->plan($answers, $preset);
        foreach ($result['warnings'] as $warning) {
            $this->ui->warn($warning);
        }
        foreach ($result['notes'] as $note) {
            $this->ui->note($note);
        }
        $result['error'] === null || $this->ui->error($result['error']);

        return $result['grid'];
    }


    private function gather(
        GridInfo $grid,
        array $profile,
        DatabaseService $database,
        ?string $simName,
        ?array $region = null,
        ?array $values = null,
        bool $edit = false,
    ): ?SimPlan {
        if ($simName === null && $grid->needsLandingRegion()) {
            $this->ui->note(self::LANDING);
        }
        $given = $values['simulator'] ?? [];
        // A simulator made for a region is named after it, as the first of a grid is
        $planner = SimPlanner::forGrid($grid, $profile, $database, $this->ui, $simName, $region['name'] ?? ($values['region']['name'] ?? null));
        if ($planner->cores() === []) {
            $this->ui->error(_('No OpenSim core found.'));

            return null;
        }
        $fields = $planner->fields();

        if ($values !== null && !$edit) {
            // A setup that answers: the simulator it names is changed if it exists, nothing is asked unless an answer is refused
            $planner = $planner->forSim($planner->resolve($given)['name']);
            $given += $planner->ownerAnswers($values['owner'] ?? []);
            if ($planner->problems($given) === []) {
                $plan = $planner->plan($given);
                DatabaseService::remember($plan->dbHost, $plan->dbUser, $plan->dbPass);

                return $this->withRegion($plan, $grid, $database, $region, $values['region'] ?? [], true);
            }
            $edit = true;
        }

        // The simulator on one screen: its name, the core, its ports, the public address of this machine (what the regions
        // announce, the viewers connect to it: SYSTEMIP is the first interface, which is not the world behind a NAT or in
        // a container) and its console
        $derived = $given === [] ? null : $planner->resolve();
        $answers = FieldForm::ask($this->ui, $fields->group('simulator'), $planner->resolve($given), _('Simulator'), [], $derived);
        $name = $planner->resolve($answers + $given)['name'];
        $planner = $planner->forSim($name);
        $iniPath = "{$grid->dir}/sims/" . $planner->resolve($answers + $given)['slug'] . '.ini';
        if ($simName === null && is_file($iniPath)) {
            $action = $this->ui->choose(
                sprintf(_("Simulator '%s' is already configured (%s)."), $name, $iniPath),
                [
                    'modify' => _('Modify its settings (its regions are kept)'),
                    'abandon' => _('Abandon, keep it unchanged'),
                ],
                'modify',
            );
            if ($action === 'abandon') {
                $this->ui->note(_('Left unchanged.'));

                return null;
            }
            // Its own settings are the ones to propose
            $answers = FieldForm::ask($this->ui, $fields->group('simulator'), $planner->resolve(['name' => $name] + $given), _('Simulator'), [], $derived === null ? null : $planner->resolve());
        }
        $answers += $given;

        // Its own database: the account of the grid, a database of its own. The account of the first simulator of the grid is the
        // one of the next ones, and so is its password
        $answers += $this->databaseAnswers($planner->resolve($answers));

        // The estate and its owner, then the first region, each on one screen
        $given += $planner->ownerAnswers($values['owner'] ?? []);
        $answers += $this->askEstate($planner, $grid, $answers + $given);

        return $this->withRegion($planner->plan($answers), $grid, $database, $region, $values['region'] ?? null, false);
    }

    /**
     * The plan with its first region: the one that was asked first, or the one of the setup, or the one asked now.
     *
     * @param ?array<string,mixed> $region the answers about a region asked before the simulator
     * @param ?array<string,mixed> $given the answers of a setup file about the first region
     */
    private function withRegion(SimPlan $plan, GridInfo $grid, DatabaseService $database, ?array $region, ?array $given, bool $answered): SimPlan
    {
        if ($region !== null) {
            // The region was asked first, the simulator is the one that takes it
            RegionPlanner::forSim($plan, $grid, $database)->apply($plan, $region);

            return $plan;
        }
        $plan->createRegion = (glob("{$plan->regionsDir()}/*.ini") ?: []) === [];
        if ($plan->createRegion) {
            $this->askRegion($plan, $grid, $database, true, $given, $answered);
        }

        return $plan;
    }

    /** The database settings of the plan, asked again: what the plan has is what is proposed. */
    private function askDatabase(SimPlan $plan): void
    {
        $v = $this->databaseAnswers([
            'database_host' => $plan->dbHost,
            'database_name' => $plan->dbName,
            'database_user' => $plan->dbUser,
            'database_password' => $plan->dbPass,
        ]);
        [$plan->dbHost, $plan->dbName, $plan->dbUser, $plan->dbPass] = [$v['database_host'], $v['database_name'], $v['database_user'], $v['database_password']];
    }

    /**
     * The four answers about the database, asked. An account keeps its password for the whole session: the one of the grid's account is
     * proposed.
     *
     * @param array<string,string> $proposed
     * @return array<string,string>
     */
    private function databaseAnswers(array $proposed): array
    {
        $proposed['database_password'] = DatabaseService::recall($proposed['database_host'], $proposed['database_user']) ?? $proposed['database_password'];
        $fields = (new FieldSet(Simulator::fields()))->group('database');
        $v = FieldForm::ask($this->ui, $fields, $proposed, _('Database')) + $proposed;
        DatabaseService::remember($v['database_host'], $v['database_user'], $v['database_password']);

        return array_intersect_key($v, array_flip(['database_host', 'database_name', 'database_user', 'database_password']));
    }


    /**
     * The estate and its owner, on one screen: an account of the grid, chosen or created (its name, password and email are asked when
     * it is a new one).
     *
     * @param array<string,mixed> $answers what was answered before
     * @return array<string,mixed>
     */
    private function askEstate(SimPlanner $planner, GridInfo $grid, array $answers): array
    {
        $accounts = $planner->accounts();
        if ($accounts === null) {
            // Not an account to make: the owner has to exist, its database is not read
            $this->ui->note(
                $grid->remote
                    ? sprintf(
                        _(
                            "The estate is owned by an account of the grid '%s', which has to exist already (its administrator makes it on its Robust).",
                        ),
                        $grid->nick,
                    )
                    : sprintf(
                        _("The accounts of the grid '%s' cannot be read: the owner must be an existing account."),
                        $grid->nick,
                    ),
            );
        } elseif ($accounts === []) {
            $this->ui->note(
                sprintf(
                    _("The grid '%s' has no account yet: the owner of the estate is the first one to create."),
                    $grid->nick,
                ),
            );
        }

        return FieldForm::ask($this->ui, $planner->fields()->group('estate'), $planner->resolve($answers), _('Estate'), $answers);
    }


    /**
     * The first region of a simulator, or the next ones, on one screen: its name, its roles, its place and its port. When
     * it is optional (the first region of a simulator) the screen starts with the question whether to create it.
     *
     * @param ?array<string,mixed> $given the answers of a setup file: the screen proposes them, or is not shown with $answered
     */
    private function askRegion(SimPlan $plan, GridInfo $grid, DatabaseService $database, bool $optional = false, ?array $given = null, bool $answered = false): void
    {
        $planner = RegionPlanner::forSim($plan, $grid, $database, $optional);
        if ($answered && $planner->problems($given ?? []) === []) {
            // The place is the free one nearest to the place asked
            $planner->apply($plan, $given ?? []);

            return;
        }
        $answers = $this->regionAnswers($planner, $grid, $given ?? []);
        if ($optional && ($answers['create'] ?? true) === false) {
            $plan->createRegion = false;

            return;
        }
        $planner->apply($plan, $answers);
    }

    /**
     * The screen of a region: the answers, the place being one that is free or that the person accepted in place of the one asked.
     *
     * @return array<string,mixed>
     */
    private function regionAnswers(RegionPlanner $planner, GridInfo $grid, array $given = []): array
    {
        // A grid run from this machine needs a landing region: until it exists nobody can log in
        if ($grid->needsLandingRegion()) {
            $this->ui->note(self::LANDING);
        }
        $answers = FieldForm::ask($this->ui, $planner->fields(), $planner->resolve($given), _('Region'));
        if (isset($answers['location'])) {
            $answers['location'] = $this->confirmPlace($planner, $grid, $answers['location']);
        }

        return $answers;
    }


    /**
     * The place of a region: the one asked, or, when it is taken (or too close to a region, by the rule of the grid: the free blocks
     * it leaves between its regions), the free one nearest to it, which the person accepts or replaces by another.
     */
    private function confirmPlace(RegionPlanner $planner, GridInfo $grid, string $asked, ?string $current = null): string
    {
        while (true) {
            $settled = $planner->settle($asked, $current);
            if ($settled['unreachable']) {
                $this->ui->warn(
                    sprintf(
                        _(
                            'The grid does not answer at %s:%s: its regions are not known here, ask its owner which place is free.',
                        ),
                        $grid->baseHostname,
                        $grid->privatePort,
                    ),
                );
            }
            if (!$settled['moved']) {
                return $settled['free'];
            }
            $why =
                $grid->regionSpacing > 0
                    ? "taken or too close to a region (the grid leaves {$grid->regionSpacing} free block(s) between them)"
                    : 'taken';
            [$x, $y] = explode(',', $settled['asked']);
            [$freeX, $freeY] = explode(',', $settled['free']);
            $this->ui->note(
                sprintf(_('The place %s,%s is %s: the nearest free one is %s,%s.'), $x, $y, $why, $freeX, $freeY),
            );
            if ($this->ui->confirm(sprintf(_('Use %s,%s?'), $freeX, $freeY), true)) {
                return $settled['free'];
            }
            // Another place is asked, in the same words as the form
            $asked = $this->ui->text(
                _('Region location (x,y)'),
                $settled['free'],
                static fn(string $v): ?string => LocationFinder::parse($v) === null ? 'Use x,y (e.g. 8002,8002).' : null,
            );
            $asked = LocationFinder::parse($asked) === null ? $settled['free'] : $asked;
        }
    }

    private function showPlan(SimPlan $plan): void
    {
        $this->ui->note($this->planText($plan));
    }

    private function planText(SimPlan $plan): string
    {
        $lines = [
            "  Simulator:   {$plan->simName}  ({$plan->slug})",
            "  Grid:        {$plan->gridName}  ({$plan->gridNick})",
            "  Core:        {$plan->coreDirectory}",
            "  HTTP port:   {$plan->httpPort}",
            '  Console:     ' .
            ($plan->consoleMode === 'rest'
                ? "remote, port {$plan->consolePort}, user {$plan->consoleUser}"
                : 'screen session'),
            "  Database:    {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser})",
            "  Estate:      {$plan->estateName}, owner {$plan->estateOwner}" .
            ($plan->createOwner ? ' (account to create)' : ''),
        ];
        if ($plan->createRegion) {
            $lines[] = "  Region:      {$plan->regionName} at {$plan->regionLocation}, port {$plan->regionPort}";
            if ($plan->regionRoles !== []) {
                $lines[] = '  Roles:       ' . implode(', ', $plan->regionRoles);
            }
        }
        $lines[] = "  Config:      {$plan->iniPath()}";
        return _('Simulator plan:') . "\n" . implode("\n", $lines);
    }
}
