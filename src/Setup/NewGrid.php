<?php

namespace OpenSimKit\Setup;

use OpenSimKit\System\Elevated;
use OpenSimKit\Ui\FieldForm;
use OpenSim\Forms\FieldSet;
use OpenSim\Models\Grid;
use OpenSim\Provisioning\DatabaseService;
use OpenSim\Provisioning\GridPlan;
use OpenSim\Provisioning\GridPlanner;
use OpenSim\Exceptions\SetupFailedException;
use OpenSim\Provisioning\UiInterface;

/**
 * Configure (or reconfigure) one grid on top of an installed framework.
 *
 * Reusable flow driven through an InstallerUi: the setup hub calls run() with a
 * grid nick to modify, or null to create a new one. Returns to the caller when
 * done (never exits the process). Phase 2a: gather only.
 */
final class NewGrid
{
    public function __construct(private UiInterface $ui) {}

    /**
     * @param ?array<string,mixed> $values the answers of a setup file, by the names of the fields (SetupFile::gridValues()): nothing is
     *        asked unless an answer is refused; with $edit, every question is asked with them as the answers proposed
     * @return ?string the nick of the grid configured, null when nothing was
     */
    public function run(?string $modifyNick = null, ?array $values = null, bool $edit = false): ?string
    {
        $profile = (new InstallConfig())->profile();
        if ($profile === [] || ($profile['EtcRoot'] ?? '') === '') {
            $this->ui->error(_('No installed framework found. Install an OpenSim core first.'));

            return null;
        }

        $plan = $this->gather($profile, $modifyNick, $values, $edit);
        if ($plan === null) {
            return null; // abandoned
        }

        return $this->complete($plan, $profile, false);
    }

    /**
     * The questions of the setup, nothing is made: the plan, to show and to accept with the one of the first
     * simulator (the quick setup), then complete().
     *
     * @param ?array<string,mixed> $values see run()
     * @return ?array{0:GridPlan,1:array<string,mixed>} the plan and the install profile, null when abandoned
     */
    public function prepare(?string $modifyNick = null, ?array $values = null, bool $edit = false): ?array
    {
        $profile = (new InstallConfig())->profile();
        if ($profile === [] || ($profile['EtcRoot'] ?? '') === '') {
            $this->ui->error(_('No installed framework found. Install an OpenSim core first.'));

            return null;
        }
        $plan = $this->gather($profile, $modifyNick, $values, $edit);

        return $plan === null ? null : [$plan, $profile];
    }

    /**
     * What the plan will do, as the setup shows it.
     */
    public function describe(GridPlan $plan): string
    {
        return $this->planText($plan);
    }

    /**
     * Make the grid of a plan: the database, then the files and the instance. The plan is shown and has to be
     * accepted, unless it was already ($accepted).
     *
     * @param array<string,mixed> $profile
     * @return ?string the nick of the grid configured, null when nothing was
     */
    public function complete(GridPlan $plan, array $profile, bool $accepted): ?string
    {
        // Nothing is written before the database is known to work. A problem of
        // access leaves the error on screen and asks the settings again; a creation
        // that failed ends the setup.
        $database = new DatabaseService($this->ui);
        DatabaseService::applyRemembered($plan);
        $attempts = 0;
        while (($result = $database->ensure($plan)) !== DatabaseService::OK) {
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
        }

        if (!$accepted) {
            $this->showPlan($plan);
        }
        // What to do once written is asked here, while the user is at the keyboard (writing may be done by another
        // process), with the confirmation of the plan
        $v = $this->ui->form(self::applyQuestions($plan->gridNick, $accepted), _('Apply'));
        if (($v['apply'] ?? 'yes') === 'no') {
            $this->ui->note(_('Aborted — nothing changed.'));

            return null;
        }
        $plan->enable = $v['enable'] === 'yes';
        $plan->start = $plan->enable && $v['start'] === 'yes';

        $this->write($plan, $profile);

        return $plan->gridNick;
    }

    /**
     * What to do once the grid is written: confirm it (unless the plan was accepted already), enable it, start it. A question
     * that is not asked counts as a yes for the ones that depend on it.
     *
     * @return list<array<string,mixed>>
     */
    public static function applyQuestions(string $nick, bool $accepted): array
    {
        $go = static fn(array $v): bool => ($v['apply'] ?? '') !== 'no';

        return [
            [
                'key' => 'apply',
                'label' => sprintf(_("Apply this configuration to grid '%s'?"), $nick),
                'type' => 'confirm',
                'default' => 'yes',
                'when' => static fn(array $v): bool => !$accepted,
            ],
            [
                'key' => 'enable',
                'label' => _('Enable grid (link into robust.d)'),
                'type' => 'confirm',
                'default' => 'yes',
                'when' => $go,
                'condition' => ['apply' => ['yes']],
            ],
            [
                'key' => 'start',
                'label' => _('Start grid now'),
                'type' => 'confirm',
                'default' => 'yes',
                'when' => static fn(array $v): bool => $go($v) && ($v['enable'] ?? 'yes') === 'yes',
                'condition' => ['apply' => ['yes'], 'enable' => ['yes']],
            ],
        ];
    }

    /**
     * Write the grid as the user who owns the install. The questions, and the
     * database (with the rights of the user who started the setup, whatever
     * they are), are handled by this process; only the files and the
     * instances belong to the system user of the install (the packages). A
     * process of that user does the writing, unless this one already is that
     * user, or root, or the install has none.
     */
    private function write(GridPlan $plan, array $profile): void
    {
        if (!Elevated::needed($profile)) {
            $this->apply($plan, $profile);

            return;
        }

        Elevated::run(
            $this->ui,
            '--apply-grid',
            ['plan' => $plan->toArray(), 'profile' => $profile],
            $profile['SystemUser'],
        );
    }

    /** The writing itself, done by GridProvisioner. */
    public function apply(GridPlan $plan, array $profile): void
    {
        (new \OpenSim\Provisioning\GridProvisioner($this->ui))->apply($plan, $profile);
    }







    private function gather(array $profile, ?string $modifyNick, ?array $values = null, bool $edit = false): ?GridPlan
    {
        $etcRoot = $profile['EtcRoot'];
        $given = $values ?? [];
        $planner = GridPlanner::forMachine($profile, $modifyNick);
        if ($planner->cores() === []) {
            $this->ui->error(sprintf(_('No OpenSim core found under %s.'), $profile['CoreRoot'] ?? ''));

            return null;
        }
        $fields = $planner->fields();

        if ($values !== null && !$edit) {
            // A setup that answers: the grid it names is changed if it exists, nothing is asked unless an answer is refused
            $nick = $planner->resolve($given)['nick'];
            if ($modifyNick === null && $this->findExisting("$etcRoot/grids/$nick") !== null) {
                $planner = $planner->forNick($nick);
            }
            if ($planner->problems($given) === []) {
                return $this->planned($planner->plan($given), false);
            }
            $edit = true;
        }

        // The grid: the screens ask the fields of Grid::fields(), the planner works out the rest
        $answers = FieldForm::ask($this->ui, $fields->group('grid'), $planner->resolve($given), _('Grid'), [], $given === [] ? null : $planner->resolve());
        if ($modifyNick === null) {
            $nick = $planner->resolve($answers + $given)['nick'];
            $existing = $this->findExisting("$etcRoot/grids/$nick");
            if ($existing !== null) {
                $action = $this->ui->choose(
                    sprintf(_("Grid '%s' is already configured (%s)."), $nick, $existing),
                    ['modify' => _('Modify its settings'), 'abandon' => _('Abandon, keep it unchanged')],
                    'modify',
                );
                if ($action === 'abandon') {
                    $this->ui->note(_('Left unchanged.'));

                    return null;
                }
                // Its settings are what is proposed now
                $planner = $planner->forNick($nick);
            }
        }

        // The network, the web side and the console, on one screen
        $answers += FieldForm::ask(
            $this->ui,
            $fields->group('network'),
            $planner->resolve($answers + $given),
            _('Network'),
            $answers,
            $given === [] ? null : $planner->resolve($answers),
        );

        // The options of a new grid (search, currency, OSSL, web): not asked for a grid that exists
        $options = $fields->group('options');
        if (array_filter($options->all(), static fn(array $field): bool => ($field['enable'] ?? true) !== false) !== []) {
            $answers += FieldForm::ask(
                $this->ui,
                $options,
                $planner->resolve($answers + $given),
                _('Options'),
                $answers,
                $given === [] ? null : $planner->resolve($answers),
            );
        }

        return $this->planned($planner->plan($answers + $given), true);
    }

    /** The plan, once the database is asked (or not): reuse a found password, otherwise one was made (never changeme). */
    private function planned(GridPlan $plan, bool $ask): GridPlan
    {
        if ($plan->helpers) {
            // The URL of the web site is given, the path after it is the choice of the operator
            $this->ui->note(
                sprintf(
                    _('Helpers URL: %s (the viewers add the name of the script)'),
                    rtrim($plan->webUrl, '/') . $plan->helpersPath,
                ),
            );
        }
        if ($ask) {
            $this->askDatabase($plan);
        } else {
            DatabaseService::remember($plan->dbHost, $plan->dbUser, $plan->dbPass);
        }

        return $plan;
    }

    /**
     * The database settings of the plan, asked: what the plan has is what is proposed.
     */
    private function askDatabase(GridPlan $plan): void
    {
        // An account keeps its password for the whole session, entered or generated once: an attempt started again
        // proposes the same one
        $proposed = [
            'database_host' => $plan->dbHost,
            'database_name' => $plan->dbName,
            'database_user' => $plan->dbUser,
            'database_password' => DatabaseService::recall($plan->dbHost, $plan->dbUser) ?? $plan->dbPass,
        ];
        $fields = (new FieldSet(Grid::fields()))->group('database');
        $v = FieldForm::ask($this->ui, $fields, $proposed, _('Database')) + $proposed;
        [$plan->dbHost, $plan->dbName, $plan->dbUser, $plan->dbPass] = [$v['database_host'], $v['database_name'], $v['database_user'], $v['database_password']];
        DatabaseService::remember($plan->dbHost, $plan->dbUser, $plan->dbPass);
    }

    /** Existing Robust config for a grid (HG preferred), or null. */
    private function findExisting(string $gridDir): ?string
    {
        foreach (['Robust.HG.ini', 'Robust.ini'] as $file) {
            if (is_file("$gridDir/$file")) {
                return "$gridDir/$file";
            }
        }

        return null;
    }

    private function showPlan(GridPlan $plan): void
    {
        $this->ui->note($this->planText($plan));
    }

    private function planText(GridPlan $plan): string
    {
        $lines = [
            "  Grid:        {$plan->gridName}  ({$plan->gridNick})",
            '  Hypergrid:   ' . ($plan->enableHypergrid ? 'yes' : 'no'),
            '  Regions:     ' .
            ($plan->regionSpacing === 0 ? 'side by side' : "{$plan->regionSpacing} free block(s) between them"),
            "  Core:        {$plan->coreDirectory}",
            "  Hostname:    {$plan->baseHostname}",
            "  Ports:       public {$plan->publicPort} / private {$plan->privatePort}",
            '  Console:     ' .
            ($plan->consoleMode === 'rest'
                ? "remote, port {$plan->consolePort}, user {$plan->consoleUser}"
                : 'screen session'),
            "  Web URL:     {$plan->webUrl}",
            '  Helpers:     ' .
            ($plan->helpers ? "served at {$plan->webUrl}{$plan->helpersPath}" : 'not served by this web site'),
            '  Search:      ' . ['internal' => 'this grid (opensim-helpers)', 'shared' => 'shared provider (2do.directory)', 'disabled' => 'none'][$plan->search],
            '  Currency:    ' . ($plan->economy === 'gloebit' ? 'Gloebit' : 'BetaGridLikeMoneyModule'),
            '  OSSL:        ' . ($plan->ossl ? 'more functions for parcel owners (NPC, animations, drawing...)' : 'the defaults of the core'),
            ...$plan->helpers ? ['  Web server:  ' . ['caddy' => 'Caddy', 'apache' => 'Apache', 'nginx' => 'nginx'][$plan->webServer] . ($plan->webSite === 'website' ? ', with the mini website' : ', the helpers only')] : [],
            "  Database:    {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser})",
            "  Robust ini:  {$plan->robustIni()}",
            "  Grid dir:    {$plan->etcDirectory}",
        ];
        return _('Grid plan:') . "\n" . implode("\n", $lines);
    }
}
