<?php

namespace OpenSimKit\Setup;

use OpenSimKit\System\Elevated;
use OpenSim\Config\ConfigReader;
use OpenSim\Models\Core;
use OpenSim\Models\GridInfo;
use OpenSim\Network\Ports;
use OpenSim\Provisioning\DatabaseService;
use OpenSim\Provisioning\GridPlan;
use OpenSim\Provisioning\LocationFinder;
use OpenSim\Provisioning\SetupFailedException;
use OpenSim\Provisioning\Slug;
use OpenSim\Provisioning\UiInterface;
use OpenSim\Provisioning\Web\HelpersConfig;
use OpenSim\Provisioning\Web\Services;
use OpenSim\Provisioning\Web\Snippets;
use OpenSim\Runtime\ShellService;

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

    /** @return ?string the nick of the grid configured, null when nothing was */
    public function run(?string $modifyNick = null): ?string
    {
        $profile = (new InstallConfig())->profile();
        if ($profile === [] || ($profile['EtcRoot'] ?? '') === '') {
            $this->ui->error(_('No installed framework found. Install an OpenSim core first.'));

            return null;
        }

        $plan = $this->gather($profile, $modifyNick);
        if ($plan === null) {
            return null; // abandoned
        }

        return $this->complete($plan, $profile, false);
    }

    /**
     * The questions of the setup, nothing is made: the plan, to show and to accept with the one of the first
     * simulator (the quick setup), then complete().
     *
     * @return ?array{0:GridPlan,1:array<string,mixed>} the plan and the install profile, null when abandoned
     */
    public function prepare(?string $modifyNick = null): ?array
    {
        $profile = (new InstallConfig())->profile();
        if ($profile === [] || ($profile['EtcRoot'] ?? '') === '') {
            $this->ui->error(_('No installed framework found. Install an OpenSim core first.'));

            return null;
        }
        $plan = $this->gather($profile, $modifyNick);

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
            $this->askDatabase($plan, [
                'dbHost' => $plan->dbHost,
                'dbName' => $plan->dbName,
                'dbUser' => $plan->dbUser,
                'dbPass' => $plan->dbPass,
            ]);
        }

        if (!$accepted) {
            $this->showPlan($plan);
        }
        // What to do once written is asked here, while the user is at the keyboard (writing may be done by another
        // process), with the confirmation of the plan
        $v = $this->ui->form(
            [
                [
                    'key' => 'apply',
                    'label' => sprintf(_("Apply this configuration to grid '%s'?"), $plan->gridNick),
                    'type' => 'confirm',
                    'default' => 'yes',
                    'when' => static fn(array $v): bool => !$accepted,
                ],
                [
                    'key' => 'enable',
                    'label' => _('Enable grid (link into robust.d)'),
                    'type' => 'confirm',
                    'default' => 'yes',
                    'when' => static fn(array $v): bool => ($v['apply'] ?? 'yes') === 'yes',
                ],
                [
                    'key' => 'start',
                    'label' => _('Start grid now'),
                    'type' => 'confirm',
                    'default' => 'yes',
                    'when' => static fn(array $v): bool => ($v['apply'] ?? 'yes') === 'yes' && $v['enable'] === 'yes',
                ],
            ],
            _('Apply'),
        );
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







    private function gather(array $profile, ?string $modifyNick): ?GridPlan
    {
        $required = static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null;
        $numeric = static fn(string $v): ?string => ctype_digit(trim($v)) ? null : _('Enter a port number.');

        $etcRoot = $profile['EtcRoot'];

        // The core to run the grid (multi-version aware)
        $coreRoot = $profile['CoreRoot'] ?? '';
        $cores = Core::choices($coreRoot);
        if ($cores === []) {
            $this->ui->error(sprintf(_('No OpenSim core found under %s.'), $coreRoot));

            return null;
        }

        // Identify the grid and load its existing config, if any.
        $existing = null;
        $current = [];
        $nick = $modifyNick;
        $defaultName = $modifyNick === null ? self::defaultName() : '';
        if ($modifyNick !== null) {
            $existing = $this->findExisting("$etcRoot/grids/$nick");
            $current = $existing !== null ? $this->parseExisting($existing) : [];
        }
        $identity = function (array $current, ?string $existing, string $nick = '') use (
            $required,
            $cores,
            $profile,
            $defaultName,
            $modifyNick,
        ): array {
            $name = $current['gridName'] ?? ($modifyNick !== null ? ucfirst($nick) : $defaultName);

            return $this->ui->form(
                [
                    ['key' => 'name', 'label' => _('Grid name'), 'default' => $name, 'validate' => $required],
                    // The nick names the folder, the database and the instances: it is not changed afterwards
                    [
                        'key' => 'nick',
                        'label' => _('Grid nick (snake_case)'),
                        'default' => $nick !== '' ? $nick : Slug::nick($defaultName),
                        'hint' => _('Names its folder, its database and its instances'),
                        'validate' => $required,
                        'when' => static fn(array $v): bool => $modifyNick === null,
                    ],
                    [
                        'key' => 'hypergrid',
                        'label' => _('Enable Hypergrid?'),
                        'type' => 'confirm',
                        'default' =>
                            $existing !== null ? (str_contains(basename($existing), '.HG.') ? 'yes' : 'no') : 'yes',
                    ],
                    [
                        'key' => 'spacing',
                        'label' => _('Free blocks between regions (0: side by side)'),
                        'default' => (string) ($current['regionSpacing'] ?? 0),
                        'validate' => static fn(string $v): ?string => ctype_digit(trim($v)) && (int) $v <= 50
                            ? null
                            : _('A number of blocks, 0 to 50.'),
                    ],
                    [
                        'key' => 'core',
                        'label' => _('OpenSim core to run this grid'),
                        'type' => 'choice',
                        'options' => $cores,
                        'default' => (string) ($profile['CoreDirectory'] ?? ''),
                        'when' => static fn(array $v): bool => count($cores) > 1,
                    ],
                ],
                _('Grid'),
            );
        };
        $v = $identity($current, $existing, (string) $nick);
        if ($modifyNick === null) {
            // A nick left as proposed follows the name
            $nick = $v['nick'] === Slug::nick($defaultName) ? Slug::nick($v['name']) : $v['nick'];
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
                $current = $this->parseExisting($existing);
            }
        }
        $gridDir = "$etcRoot/grids/$nick";

        $plan = new GridPlan();
        $plan->gridName = $v['name'];
        $plan->gridNick = $nick;
        $plan->gridSlug = Slug::slug($v['name']);
        $plan->gridDir = $gridDir;
        $plan->etcDirectory = $gridDir;
        $plan->dataDirectory = "{$profile['DataRoot']}/$nick";
        $plan->cacheDirectory = "{$profile['CacheRoot']}/$nick";
        $plan->logsDirectory = $profile['LogsRoot'] ?? '';
        $plan->enableHypergrid = $v['hypergrid'] === 'yes';
        // The rule that places the regions: free blocks between them
        $plan->regionSpacing = (int) $v['spacing'];
        $plan->coreDirectory = $v['core'] !== '' ? $v['core'] : (string) array_key_first($cores);
        $plan->binDir = $plan->coreDirectory . '/bin';

        // The network, the web side and the console, on one screen
        $defaultHost = $current['baseHostname'] ?? self::defaultHost();
        $defaultPublic = (int) ($current['publicPort'] ?? self::defaultPublicPort());
        // The ports of an instance are a block of ten, the first free one (see Ports):
        // public ends with 2, private with 3, the console with 4
        $privateFor = static fn(int $public): int => $public % 10 === 2 ? $public + 1 : Ports::next($public + 1);
        $consoleFor = static fn(int $public, int $private): int => $public % 10 === 2
            ? $public + 2
            : Ports::next($private + 1);
        $defaultPrivate = (int) ($current['privatePort'] ?? $privateFor($defaultPublic));
        $defaultConsole = (int) ($current['consolePort'] ?? $consoleFor($defaultPublic, $defaultPrivate));
        $defaultWeb = $current['webUrl'] ?? "https://$defaultHost";
        $saved = ConfigReader::file("$gridDir/$nick.conf")['Grid']['Center'] ?? '';
        $defaultCenter =
            trim((string) $saved, " \t\"") !== '' ? trim((string) $saved, " \t\"") : "$defaultPublic,$defaultPublic";
        $v = $this->ui->form(
            [
                ['key' => 'host', 'label' => _('Base hostname'), 'default' => $defaultHost, 'validate' => $required],
                [
                    'key' => 'public',
                    'label' => _('Public port'),
                    'default' => (string) $defaultPublic,
                    'validate' => $numeric,
                ],
                [
                    'key' => 'private',
                    'label' => _('Private port'),
                    'default' => (string) $defaultPrivate,
                    'validate' => $numeric,
                ],
                [
                    'key' => 'center',
                    'label' => _('Center of the grid (x,y)'),
                    'default' => $defaultCenter,
                    'hint' => _('Where new regions are searched from, kept'),
                    'validate' => static fn(string $v): ?string => LocationFinder::parse($v) === null
                        ? _('Use x,y (e.g. 8002,8002).')
                        : null,
                ],
                [
                    'key' => 'console',
                    'label' => _('Console of the grid'),
                    'type' => 'choice',
                    'options' => [
                        'rest' => _('Remote REST console (recommended)'),
                        'screen' => _('Screen session (on this machine)'),
                    ],
                    'default' => isset($current['consoleUser']) || $current === [] ? 'rest' : 'screen',
                ],
                [
                    'key' => 'console_port',
                    'label' => _('Console port'),
                    'default' => (string) $defaultConsole,
                    'validate' => $numeric,
                    'when' => static fn(array $v): bool => $v['console'] === 'rest',
                ],
                ['key' => 'web', 'label' => _('Web URL'), 'default' => $defaultWeb, 'validate' => $required],
                [
                    'key' => 'helpers',
                    'label' => _('Serve the economy and the search of the grid with opensim-helpers?'),
                    'type' => 'confirm',
                    'default' => is_file(HelpersConfig::path($gridDir)) || is_dir(Snippets::WEBROOT) ? 'yes' : 'no',
                ],
                [
                    'key' => 'helpers_path',
                    'label' => _('Helpers path'),
                    'default' => HelpersConfig::basePath($gridDir),
                    'hint' => _('After the Web URL: the viewers add the name of the script'),
                    'validate' => static fn(string $v): ?string => preg_match('#^/?[A-Za-z0-9._/-]*$#', trim($v))
                        ? null
                        : _('A path such as /helpers.'),
                    'when' => static fn(array $v): bool => $v['helpers'] === 'yes',
                ],
            ],
            _('Network'),
        );
        $plan->baseHostname = $v['host'];
        $plan->publicPort = (int) $v['public'];
        // What was left as proposed follows what was changed
        $plan->privatePort =
            (int) $v['private'] === $defaultPrivate && $plan->publicPort !== $defaultPublic
                ? $privateFor($plan->publicPort)
                : (int) $v['private'];
        // The center follows the port when it was left as proposed, else it is what was confirmed
        $plan->center =
            $v['center'] === $defaultCenter && $plan->publicPort !== $defaultPublic && $saved === ''
                ? "{$plan->publicPort},{$plan->publicPort}"
                : $v['center'];
        $plan->webUrl =
            $v['web'] === $defaultWeb && $v['host'] !== $defaultHost ? "https://{$plan->baseHostname}" : $v['web'];
        $plan->helpers = $v['helpers'] === 'yes';
        if ($plan->helpers) {
            // The URL of the web site is given, the path after it is the choice of the operator
            $plan->helpersPath = Services::normalize($v['helpers_path']);
            $this->ui->note(
                sprintf(
                    _('Helpers URL: %s (the viewers add the name of the script)'),
                    rtrim($plan->webUrl, '/') . $plan->helpersPath,
                ),
            );
        }
        $plan->consoleMode = $v['console'];
        if ($plan->consoleMode === 'rest') {
            $plan->consolePort =
                (int) ($v['console_port'] === (string) $defaultConsole && $plan->publicPort !== $defaultPublic
                    ? $consoleFor($plan->publicPort, $plan->privatePort)
                    : $v['console_port']);
            // As the helpers make theirs: 12 lower case letters, 32 letters and digits
            $plan->consoleHost = (string) ($current['consoleHost'] ?? $plan->baseHostname);
            $plan->consoleUser = (string) ($current['consoleUser'] ?? $this->randomLetters(12));
            $plan->consolePass = (string) ($current['consolePass'] ?? $this->randomPassword(32));
        }

        // Database: reuse a found password, otherwise generate one (never changeme).
        $foundPass = $current['dbPass'] ?? '';
        $this->askDatabase($plan, [
            'dbHost' => $current['dbHost'] ?? ($profile['DataSource'] ?? 'localhost'),
            'dbName' => $current['dbName'] ?? strtolower($nick) . '_robust',
            'dbUser' => $current['dbUser'] ?? 'opensim',
            'dbPass' => $foundPass !== '' && $foundPass !== 'changeme' ? $foundPass : $this->randomPassword(),
        ]);

        return $plan;
    }

    /**
     * The database settings, with these defaults.
     *
     * @param array{dbHost:string,dbName:string,dbUser:string,dbPass:string} $defaults
     */
    private function askDatabase(GridPlan $plan, array $defaults): void
    {
        $required = static fn(string $v): ?string => trim($v) === '' ? 'This field is required.' : null;

        // An account keeps its password for the whole session, entered or
        // generated once: an attempt started again proposes the same one
        $password = DatabaseService::recall($defaults['dbHost'], $defaults['dbUser']) ?? $defaults['dbPass'];
        $v = $this->ui->form(
            [
                [
                    'key' => 'host',
                    'label' => _('Database host'),
                    'default' => $defaults['dbHost'],
                    'validate' => $required,
                ],
                [
                    'key' => 'name',
                    'label' => _('Database name'),
                    'default' => $defaults['dbName'],
                    'validate' => $required,
                ],
                [
                    'key' => 'user',
                    'label' => _('Database user'),
                    'default' => $defaults['dbUser'],
                    'validate' => $required,
                ],
                ['key' => 'pass', 'label' => _('Database password'), 'default' => $password, 'validate' => $required],
            ],
            _('Database'),
        );
        [$plan->dbHost, $plan->dbName, $plan->dbUser, $plan->dbPass] = [$v['host'], $v['name'], $v['user'], $v['pass']];
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

    /** Default grid name: the short hostname, capitalised (amy.magiiic.com -> Amy). */
    /** The host name a grid is proposed: the one of the machine. */
    public static function defaultHost(): string
    {
        return trim(ShellService::capture('hostname -f')[1]) ?: 'localhost';
    }

    /** The public port a grid is proposed: the one of the first block of ten ports that is free, ending with 2. */
    public static function defaultPublicPort(): int
    {
        return Ports::nextBlock(8000) + 2;
    }

    /** The name a grid is proposed: the one of the machine. */
    public static function defaultName(): string
    {
        [, $host] = ShellService::capture('hostname -s');
        $short = trim($host);
        if ($short === '') {
            [, $fqdn] = ShellService::capture('hostname -f');
            $short = strtok(trim($fqdn), '.') ?: 'My';
        }

        return ucfirst($short);
    }

    /** Extract current settings from an existing Robust config. */
    private function parseExisting(string $path): array
    {
        return GridInfo::parse($path);
    }

    private function randomLetters(int $length): string
    {
        $letters = '';
        for ($i = 0; $i < $length; $i++) {
            $letters .= chr(random_int(97, 122));
        }

        return $letters;
    }

    private function randomPassword(int $length = 20): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
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
            "  Database:    {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser})",
            "  Robust ini:  {$plan->robustIni()}",
            "  Grid dir:    {$plan->etcDirectory}",
        ];
        return _('Grid plan:') . "\n" . implode("\n", $lines);
    }
}
