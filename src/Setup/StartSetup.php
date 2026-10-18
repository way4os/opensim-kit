<?php

namespace OpenSimKit\Setup;

use OpenSim\Forms\FieldSet;
use OpenSim\Models\Grid;
use OpenSim\Provisioning\GridAccounts;
use OpenSim\Provisioning\GridPlanner;
use OpenSim\Provisioning\Presets;
use OpenSim\Provisioning\UiInterface;
use OpenSimKit\Ui\FieldForm;

/**
 * The start of the setup, the same for everyone: what to set up, the few things it needs (the name of the grid, where it is, who owns
 * it), the options each with its default, then quick (the usual settings for the rest, shown before anything is written) or advanced
 * (every question, with these answers proposed). The questions of a grid are the fields of the engine (Models\Grid::fields()).
 */
final class StartSetup
{
    public const GRID = 'grid';
    public const SIMULATOR = 'simulator';
    public const QUICK = 'quick';
    public const ADVANCED = 'advanced';

    /** The options of a new grid, in the order they are asked (fields of Models\Grid). */
    public const OPTIONS = ['hypergrid', 'search', 'economy', 'ossl', 'web_server', 'web_site'];

    public function __construct(private UiInterface $ui) {}

    /**
     * @return array{type:string,mode:string,data:array<string,mixed>} the setup (see SetupFile; a simulator that joins a grid has
     *         `join`: its preset and its address), and how to go on
     */
    public function ask(): array
    {
        $type = $this->ui->choose(
            _('What do you want to set up?'),
            [
                self::GRID => _('A grid: its own services and its simulators'),
                self::SIMULATOR => _('A simulator that joins a grid that exists'),
            ],
            self::GRID,
        );
        $data = $type === self::GRID ? $this->grid() : $this->simulator();
        $mode = $this->ui->choose(
            _('How do you want to go on?'),
            [
                self::QUICK => _('Quick setup: the usual settings for the rest'),
                self::ADVANCED => _('Advanced config: every setting, one by one'),
            ],
            self::QUICK,
        );

        return ['type' => $type, 'mode' => $mode, 'data' => $data];
    }

    /** @return array<string,mixed> */
    private function grid(): array
    {
        $v = $this->ui->form(
            [
                ['key' => 'name', 'label' => _('Grid name'), 'default' => GridPlanner::defaultName()],
                [
                    'key' => 'login',
                    'label' => _('Grid URL'),
                    'default' => 'http://' . GridPlanner::defaultHost() . ':' . GridPlanner::defaultPublicPort(),
                    'hint' => _('host:port, the address the viewers log in to'),
                    'validate' => self::validLogin(...),
                ],
                [
                    'key' => 'owner',
                    'label' => _('Grid owner'),
                    'hint' => _('First Last: the first account, owner of the first estate'),
                    'validate' => static fn(string $v): ?string => GridAccounts::validName(trim($v))
                        ? null
                        : _('First and last name, e.g. Jane Doe.'),
                ],
                [
                    'key' => 'password',
                    'label' => _('Owner password'),
                    'type' => 'secret',
                    'validate' => static fn(string $v): ?string => preg_match('/^[^"\r\n]{6,}$/', $v)
                        ? null
                        : _('At least 6 characters, without double quote.'),
                ],
                [
                    'key' => 'email',
                    'label' => _('Owner email'),
                    'required' => false,
                    'hint' => _('Optional'),
                    'validate' => static fn(string $v): ?string => preg_match('/^[^\s"\'\\\\]+@[^\s"\'\\\\]+$/', trim($v))
                        ? null
                        : _('An email address, or nothing.'),
                ],
            ],
            _('Your grid'),
        );

        [$host, $port] = self::login($v['login']) ?? ['localhost', 8002];
        $owner = ['name' => $v['owner'], 'password' => $v['password']];
        if ($v['email'] !== '') {
            $owner['email'] = $v['email'];
        }

        return [
            'grid' => ['name' => $v['name'], 'hostname' => $host, 'public_port' => $port] + $this->options(),
            'owner' => $owner,
            // The first simulator and its region: the defaults of the setup
            'simulators' => [[]],
        ];
    }

    /**
     * The options, each with the answer proposed (what the machine and the engine propose).
     *
     * @return array<string,mixed> the answers, by the names of the fields
     */
    private function options(): array
    {
        $profile = (new InstallConfig())->profile();
        $proposed = GridPlanner::forMachine($profile)->resolve();
        $fields = new FieldSet(array_values(array_filter(
            Grid::fields(),
            static fn(array $field): bool => in_array($field['name'], self::OPTIONS, true),
        )));

        // The web options are for a machine that has the helpers: they are the conditions of the fields
        return FieldForm::ask($this->ui, $fields, $proposed, _('Options'), ['helpers' => $proposed['helpers']]);
    }

    /** @return array<string,mixed> */
    private function simulator(): array
    {
        $profile = (new InstallConfig())->profile();
        $presets = (new Presets([], (string) ($profile['EtcRoot'] ?? '')))->names();
        $preset = '';
        if ($presets !== []) {
            $choices = [];
            foreach ($presets as $name => $gridName) {
                $choices[$name] = $gridName === $name ? $name : "$name ($gridName)";
            }
            $choices['-'] = _('Another grid');
            $preset = $this->ui->choose(_('Which grid does it join?'), $choices, (string) array_key_first($choices));
            $preset = $preset === '-' ? '' : $preset;
        }
        $address = '';
        if ($preset === '') {
            $address = $this->ui->text(
                _('Grid URL'),
                '',
                self::validLogin(...),
                _('host:port, the address the viewers log in to'),
            );
        }
        $owner = $this->ui->text(
            _('Grid owner'),
            '',
            static fn(string $v): ?string => GridAccounts::validName(trim($v)) ? null : _('First and last name, e.g. Jane Doe.'),
            _('First Last: your account on that grid, owner of the estate'),
        );
        [$host, $port] = self::login($address) ?? ['', 0];

        return [
            'join' => ['preset' => $preset, 'address' => $host !== '' ? "$host:$port" : ''],
            'owner' => ['name' => $owner],
            'simulators' => [[]],
        ];
    }

    private static function validLogin(string $v): ?string
    {
        return self::login($v) !== null ? null : _('host:port, e.g. play.example.org:8002.');
    }

    /**
     * The host and the port of a login URI, given as host:port or with the scheme.
     *
     * @return ?array{0:string,1:int}
     */
    public static function login(string $uri): ?array
    {
        return preg_match('~^(?:https?://)?([A-Za-z0-9][A-Za-z0-9.-]*):(\d{2,5})/?$~', trim($uri), $m) === 1 &&
            (int) $m[2] <= 65535
            ? [$m[1], (int) $m[2]]
            : null;
    }
}
