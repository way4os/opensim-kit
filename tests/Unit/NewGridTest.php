<?php

declare(strict_types=1);

/**
 * The questions about a grid: the fields of the model asked on three screens, the plan the planner works out from the answers.
 * The user is a script: it accepts what is proposed, except for the answers it is given by label.
 */

use OpenSim\Provisioning\UiInterface;
use OpenSimKit\Setup\NewGrid;

/** A user who accepts what is proposed, and answers the questions whose label contains the start of a key. */
final class GridUiFake implements UiInterface
{
    use \OpenSimKit\Ui\AsksForms;

    /** @var list<string> the labels of the questions, in order */
    public array $asked = [];
    /** @var list<string> */
    public array $notes = [];

    /** @param array<string,string|bool> $answers */
    public function __construct(private array $answers = [])
    {
    }

    private function answer(string $label, string|bool $default): string|bool
    {
        $this->asked[] = $label;
        foreach ($this->answers as $match => $answer) {
            if (str_contains($label, (string) $match)) {
                return $answer;
            }
        }

        return $default;
    }

    public function intro(string $title): void {}
    public function note(string $message): void { $this->notes[] = $message; }
    public function entity(string $name): string { return $name; }
    public function warn(string $message): void { $this->notes[] = "warn: $message"; }
    public function error(string $message): void { $this->notes[] = "error: $message"; }
    public function outro(string $message): void {}
    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        return $label === 'Is this right?' ? 'ok' : (string) $this->answer($label, $default ?? (string) array_key_first($options));
    }
    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array { return $defaults; }
    public function text(string $label, string $default = '', ?Closure $validate = null, ?string $hint = null): string
    {
        $value = (string) $this->answer($label, $default);
        if ($validate !== null && ($problem = $validate($value)) !== null) {
            throw new RuntimeException("$label: $problem");
        }

        return $value;
    }
    public function secret(string $label, ?Closure $validate = null, ?string $hint = null): string { return ''; }
    public function confirm(string $label, bool $default = true): bool { return (bool) $this->answer($label, $default); }
    public function spin(Closure $callback, string $message): mixed { return $callback(); }
}

/** A user whose install is in a temporary tree, with one core. */
function grid_install(): string
{
    $root = test_tmp() . '/newgrid-' . bin2hex(random_bytes(4));
    mkdir("$root/home", 0o755, true);
    mkdir("$root/cores/0.9.3.0/bin", 0o755, true);
    touch("$root/cores/0.9.3.0/bin/OpenSim.dll");
    mkdir("$root/etc/grids", 0o755, true);
    file_put_contents("$root/home/.opensim.conf", "[Defaults]\nCoreRoot = $root/cores\nCoreDirectory = $root/cores/0.9.3.0\nEtcRoot = $root/etc\nDataRoot = $root/data\nCacheRoot = $root/cache\nLogsRoot = $root/logs\n");

    return $root;
}

/** Run a setup of the grid as the user of the temporary install. */
function grid_prepare(string $root, GridUiFake $ui, ?string $modify = null, ?array $values = null): ?array
{
    $home = getenv('HOME');
    putenv("HOME=$root/home");
    try {
        return (new NewGrid($ui))->prepare($modify, $values);
    } finally {
        putenv('HOME=' . $home);
    }
}

describe('The questions about a new grid', function () {
    test('are three screens, the grid, its network, its database, with a plan made of what is proposed', function () {
        $root = grid_install();
        $ui = new GridUiFake(['Grid name' => 'Garden World', 'Base hostname' => 'garden.example.org', 'Public port' => '8012']);

        [$plan, $profile] = grid_prepare($root, $ui);

        expect($ui->asked)->toContain('Grid name', 'Grid nick (snake_case)', 'Enable Hypergrid?', 'Base hostname', 'Public port', 'Console of the grid', 'Web URL', 'Database host', 'Database password')
            ->and($ui->asked)->not->toContain('OpenSim core to run this grid')
            ->and($plan->gridName)->toBe('Garden World')
            ->and($plan->gridNick)->toBe('garden_world')
            ->and($plan->baseHostname)->toBe('garden.example.org')
            // What was left as proposed follows what was changed
            ->and([$plan->publicPort, $plan->privatePort, $plan->consolePort])->toBe([8012, 8013, 8014])
            ->and($plan->center)->toBe('8012,8012')
            ->and($plan->webUrl)->toBe('https://garden.example.org')
            ->and($plan->dbName)->toBe('garden_world_robust')
            ->and($plan->gridDir)->toBe("$root/etc/grids/garden_world")
            ->and($plan->coreDirectory)->toBe("$root/cores/0.9.3.0")
            ->and($profile['EtcRoot'])->toBe("$root/etc");
    });

    test('keep what the person typed over what follows, the database password included', function () {
        $root = grid_install();
        $ui = new GridUiFake(['Grid name' => 'Garden', 'Grid nick' => 'mine', 'Public port' => '8012', 'Private port' => '9999', 'Console of the grid' => 'screen', 'Database password' => 'typed-secret', 'Database name' => 'my_db']);

        [$plan] = grid_prepare($root, $ui);

        expect($plan->gridNick)->toBe('mine')
            ->and($plan->privatePort)->toBe(9999)
            ->and($plan->consoleMode)->toBe('screen')
            ->and($plan->consolePort)->toBe(0)
            ->and($plan->dbPass)->toBe('typed-secret')
            ->and($plan->dbName)->toBe('my_db');
    });

    test('ask for the helpers path only when the helpers are served, and say where they will be', function () {
        $root = grid_install();
        $ui = new GridUiFake(['Grid name' => 'Garden', 'Serve the economy' => true, 'Web URL' => 'https://web.example.org']);

        [$plan] = grid_prepare($root, $ui);

        expect($ui->asked)->toContain('Helpers path')
            ->and($plan->helpers)->toBeTrue()
            ->and($plan->helpersPath)->toBe('/helpers')
            ->and(implode("\n", $ui->notes))->toContain('Helpers URL: https://web.example.org/helpers');

        $ui = new GridUiFake(['Grid name' => 'Garden', 'Serve the economy' => false]);
        grid_prepare($root, $ui);
        expect($ui->asked)->not->toContain('Helpers path');
    });

    test('say there is no core when the install has none', function () {
        $root = grid_install();
        unlink("$root/cores/0.9.3.0/bin/OpenSim.dll");
        $ui = new GridUiFake();

        expect(grid_prepare($root, $ui))->toBeNull()->and($ui->notes[0])->toContain('No OpenSim core found');
    });
});

describe('The questions about a grid that exists', function () {
    test('propose its settings, and keep its nick', function () {
        $root = grid_install();
        mkdir("$root/etc/grids/old", 0o755, true);
        file_put_contents("$root/etc/grids/old/Robust.ini", "[Const]\n    BaseHostname = \"old.example.org\"\n    PublicPort = 8102\n    PrivatePort = 8105\n[GridInfoService]\n    gridname = \"Old World\"\n");
        $ui = new GridUiFake();

        [$plan] = grid_prepare($root, $ui, 'old');

        expect($ui->asked)->not->toContain('Grid nick (snake_case)')
            ->and($plan->gridNick)->toBe('old')
            ->and($plan->gridName)->toBe('Old World')
            ->and($plan->enableHypergrid)->toBeFalse()
            ->and($plan->baseHostname)->toBe('old.example.org')
            ->and([$plan->publicPort, $plan->privatePort])->toBe([8102, 8105]);
    });

    test('are asked again by the nick of a new grid that already exists, to modify it or to leave it', function () {
        $root = grid_install();
        mkdir("$root/etc/grids/garden", 0o755, true);
        file_put_contents("$root/etc/grids/garden/Robust.HG.ini", "[Const]\n    BaseHostname = \"garden.old.org\"\n    PublicPort = 8102\n");

        $ui = new GridUiFake(['Grid name' => 'Garden', 'is already configured' => 'abandon']);
        expect(grid_prepare($root, $ui))->toBeNull()->and($ui->notes)->toContain('Left unchanged.');

        $ui = new GridUiFake(['Grid name' => 'Garden']);
        [$plan] = grid_prepare($root, $ui);
        expect($plan->gridNick)->toBe('garden')->and($plan->baseHostname)->toBe('garden.old.org')->and($plan->publicPort)->toBe(8102);
    });
});

describe('A setup file that answers the questions about a grid', function () {
    test('is not asked anything when its answers are right, and the rest follows what it says', function () {
        $root = grid_install();
        $ui = new GridUiFake();

        [$plan] = grid_prepare($root, $ui, null, ['name' => 'Garden World', 'hostname' => 'garden.example.org', 'public_port' => '8012', 'database_user' => 'opensim', 'database_password' => 'secret1']);

        expect($ui->asked)->toBe([])
            ->and($plan->gridNick)->toBe('garden_world')
            ->and([$plan->publicPort, $plan->privatePort, $plan->consolePort])->toBe([8012, 8013, 8014])
            ->and($plan->webUrl)->toBe('https://garden.example.org')
            ->and([$plan->dbUser, $plan->dbPass, $plan->dbName])->toBe(['opensim', 'secret1', 'garden_world_robust']);
    });

    test('has the screens shown, its answers proposed, when one of them is refused', function () {
        $root = grid_install();
        $ui = new GridUiFake(['Public port' => '8012']);

        [$plan] = grid_prepare($root, $ui, null, ['name' => 'Garden World', 'hostname' => 'garden.example.org', 'public_port' => 'abc']);

        expect($ui->asked)->toContain('Public port')
            ->and($plan->publicPort)->toBe(8012)
            ->and($plan->baseHostname)->toBe('garden.example.org')
            ->and($plan->gridName)->toBe('Garden World');
    });

    test('is edited with every question asked, its answers being the ones proposed and kept as they are', function () {
        $root = grid_install();
        $ui = new GridUiFake();
        $home = getenv('HOME');
        putenv("HOME=$root/home");
        try {
            [$plan] = (new NewGrid($ui))->prepare(null, ['name' => 'Garden World', 'hostname' => 'garden.example.org', 'public_port' => '8012', 'private_port' => '9999', 'database_password' => 'secret1'], true);
        } finally {
            putenv('HOME=' . $home);
        }

        expect($ui->asked)->toContain('Grid name', 'Base hostname', 'Public port', 'Private port', 'Database password')
            ->and([$plan->publicPort, $plan->privatePort, $plan->consolePort])->toBe([8012, 9999, 8014])
            ->and($plan->dbPass)->toBe('secret1')
            ->and($plan->gridName)->toBe('Garden World');
    });

    test('changes the grid that exists when it names it', function () {
        $root = grid_install();
        mkdir("$root/etc/grids/garden", 0o755, true);
        file_put_contents("$root/etc/grids/garden/Robust.ini", "[Const]\n    BaseHostname = \"old.example.org\"\n    PublicPort = 8102\n    PrivatePort = 8105\n");
        $ui = new GridUiFake();

        [$plan] = grid_prepare($root, $ui, null, ['name' => 'Garden', 'database_user' => 'opensim', 'database_password' => 'x']);

        expect($ui->asked)->toBe([])->and($plan->baseHostname)->toBe('old.example.org')->and($plan->privatePort)->toBe(8105);
    });
});

describe('The same questions in a browser', function () {
    afterEach(function () {
        foreach (glob(sys_get_temp_dir() . '/opensim-wizard-*') ?: [] as $dir) {
            (new \OpenSimKit\Web\Mailbox($dir))->remove();
        }
    });

    test('give the plan the terminal gives, from the same fields', function () {
        require_once __DIR__ . '/web-browser-lib.php';
        $answers = ['Grid name' => 'Garden World', 'Base hostname' => 'garden.example.org', 'Public port' => '8012'];
        $root = grid_install();
        [$expected] = grid_prepare($root, new GridUiFake($answers));

        [$ui] = webAccepting($answers, $screens);
        $home = getenv('HOME');
        putenv("HOME=$root/home");
        try {
            [$plan] = (new NewGrid($ui))->prepare();
        } finally {
            putenv('HOME=' . $home);
        }

        $differ = array_keys(array_diff_assoc($plan->toArray(), $expected->toArray()));
        // The passwords are made at random each time
        expect(array_diff($differ, ['dbPass', 'consoleUser', 'consolePass']))->toBe([])
            ->and(array_column($screens, 'title'))->toContain('Grid', 'Network', 'Database')
            ->and($plan->gridNick)->toBe('garden_world');
    });
});
