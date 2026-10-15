<?php

declare(strict_types=1);

/**
 * The questions about a simulator and its first region: the fields of the models asked on screens, the plan the planners work out
 * from the answers. The grid is only planned (nothing of it is made, its database is not read), the user is a script.
 */

use OpenSim\Models\GridInfo;
use OpenSim\Provisioning\GridPlan;
use OpenSim\Models\Region;
use OpenSim\Provisioning\UiInterface;
use OpenSimKit\Setup\NewSim;

/** A user who accepts what is proposed, and answers the questions whose label contains the start of a key. */
final class SimUiFake implements UiInterface
{
    use \OpenSimKit\Ui\AsksForms;

    /** @var list<string> */
    public array $asked = [];
    /** @var list<string> */
    public array $notes = [];

    /** @param array<string,string|bool|list<string>> $answers */
    public function __construct(private array $answers = [])
    {
    }

    private function answer(string $label, mixed $default): mixed
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
    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array { return (array) $this->answer($label, $defaults); }
    public function text(string $label, string $default = '', ?Closure $validate = null, ?string $hint = null): string
    {
        $value = (string) $this->answer($label, $default);
        if ($validate !== null && ($problem = $validate($value)) !== null) {
            throw new RuntimeException("$label: $problem");
        }

        return $value;
    }
    public function secret(string $label, ?Closure $validate = null, ?string $hint = null): string { return (string) $this->answer($label, ''); }
    public function confirm(string $label, bool $default = true): bool { return (bool) $this->answer($label, $default); }
    public function spin(Closure $callback, string $message): mixed { return $callback(); }
}

/** A grid that is planned, in a temporary install with one core; the profile of the install. */
function sim_install(array $grid = []): array
{
    $root = test_tmp() . '/newsim-' . bin2hex(random_bytes(4));
    mkdir("$root/cores/0.9.3.0/bin", 0o755, true);
    touch("$root/cores/0.9.3.0/bin/OpenSim.dll");
    mkdir("$root/etc/grids", 0o755, true);
    $profile = ['CoreRoot' => "$root/cores", 'CoreDirectory' => "$root/cores/0.9.3.0", 'EtcRoot' => "$root/etc", 'DataRoot' => "$root/data", 'CacheRoot' => "$root/cache", 'LogsRoot' => "$root/logs"];

    $plan = new GridPlan();
    $plan->gridName = 'Garden World';
    $plan->gridNick = 'garden';
    $plan->gridDir = $plan->etcDirectory = "$root/etc/grids/garden";
    $plan->dataDirectory = "$root/data/garden";
    $plan->cacheDirectory = "$root/cache/garden";
    $plan->logsDirectory = "$root/logs";
    $plan->coreDirectory = $profile['CoreDirectory'];
    $plan->baseHostname = 'garden.example.org';
    $plan->dbHost = 'db.example.org';
    $plan->dbUser = 'gridaccount';
    $plan->dbPass = 'gridpass';
    $info = GridInfo::fromPlan($plan);
    foreach ($grid as $property => $value) {
        $info->$property = $value;
    }

    return [$info, $profile, $root];
}

describe('The questions about a new simulator', function () {
    test('are the simulator, its database, its estate and its first region, with a plan made of what is proposed', function () {
        [$grid, $profile] = sim_install();
        $ui = new SimUiFake(['Name of the new account' => 'Ann Poe', 'Password of the new account' => 'secret1']);

        $plan = (new NewSim($ui))->prepare($grid, $profile);

        expect($ui->asked)->toContain('Simulator name', 'Simulator HTTP port', 'Console of the simulator', 'Database host', 'Estate name', 'Name of the new account (First Last)', 'Region name', 'Region location (x,y)', 'Region port')
            ->and($plan->simName)->toBe('Welcome')
            ->and($plan->slug)->toBe('garden_welcome')
            ->and($plan->gridNick)->toBe('garden')
            ->and($plan->httpPort % 10)->toBe(0)
            ->and($plan->consolePort)->toBe($plan->httpPort + 4)
            ->and($plan->coreDirectory)->toBe($profile['CoreDirectory'])
            ->and([$plan->dbHost, $plan->dbName, $plan->dbUser, $plan->dbPass])->toBe(['db.example.org', 'garden_welcome', 'gridaccount', 'gridpass'])
            ->and($plan->estateName)->toBe('Garden World Estate')
            ->and($plan->createOwner)->toBeTrue()
            ->and([$plan->estateOwner, $plan->ownerPassword])->toBe(['Ann Poe', 'secret1'])
            // The first region has the name of the simulator, the roles the grid lacks (not the fallback), the place of the center
            ->and($plan->createRegion)->toBeTrue()
            ->and($plan->regionName)->toBe('Welcome')
            ->and($plan->regionRoles)->toBe([Region::flagName(Region::DEFAULT_REGION), Region::flagName(Region::DEFAULT_HGREGION)])
            ->and($plan->regionPort)->toBeGreaterThan($plan->httpPort)
            ->and(strlen($plan->regionUuid))->toBe(36);
    });

    test('keep what the person typed: the name, the port, the console, the region, its roles', function () {
        [$grid, $profile] = sim_install();
        $ui = new SimUiFake([
            'Simulator name' => 'Garden Sim', 'Simulator HTTP port' => '9020', 'Console of the simulator' => 'screen',
            'Name of the new account' => 'Ann Poe', 'Password of the new account' => 'secret1',
            'Region name' => 'Roses', 'Role of this region' => [Region::flagName(Region::FALLBACK_REGION)], 'Region location' => '8100,8100',
        ]);

        $plan = (new NewSim($ui))->prepare($grid, $profile);

        expect($plan->simName)->toBe('Garden Sim')
            ->and($plan->slug)->toBe('garden_garden_sim')
            ->and($plan->httpPort)->toBe(9020)
            ->and($plan->consoleMode)->toBe('screen')
            ->and($plan->consolePort)->toBe(9024)
            ->and($plan->regionName)->toBe('Roses')
            ->and($plan->regionRoles)->toBe([Region::flagName(Region::FALLBACK_REGION)])
            ->and($plan->regionLocation)->toBe('8100,8100');
    });

    test('leave the first region out when the person says so', function () {
        [$grid, $profile] = sim_install();
        $ui = new SimUiFake(['Name of the new account' => 'Ann Poe', 'Password of the new account' => 'secret1', 'Create the first region' => false]);

        $plan = (new NewSim($ui))->prepare($grid, $profile);

        expect($plan->createRegion)->toBeFalse()->and($ui->asked)->not->toContain('Region name');
    });

    test('ask for the owner of the estate when the accounts of the grid cannot be read, who has to exist', function () {
        [$grid, $profile] = sim_install(['planned' => false, 'dbName' => 'nowhere', 'dbHost' => '127.0.0.1:1']);
        $ui = new SimUiFake(['Estate owner' => 'Far Away']);

        $plan = (new NewSim($ui))->prepare($grid, $profile);

        expect($ui->asked)->toContain('Estate owner (an account of the grid)')->and($ui->asked)->not->toContain('Name of the new account (First Last)')
            ->and($plan->estateOwner)->toBe('Far Away')
            ->and($plan->createOwner)->toBeFalse()
            ->and(implode("\n", $ui->notes))->toContain('cannot be read: the owner must be an existing account');
    });

    test('say there is no core when the install has none', function () {
        [$grid, $profile, $root] = sim_install();
        unlink("$root/cores/0.9.3.0/bin/OpenSim.dll");
        $ui = new SimUiFake();

        expect((new NewSim($ui))->prepare($grid, $profile))->toBeNull()->and($ui->notes)->toContain('error: No OpenSim core found.');
    });

    test('abandon a simulator that exists when the person says so, and modify it when they do not', function () {
        [$grid, $profile] = sim_install();
        mkdir("{$grid->dir}/sims", 0o755, true);
        file_put_contents("{$grid->dir}/sims/garden_welcome.ini", "[Network]\nhttp_listener_port = 9050\n[Estates]\nDefaultEstateName = \"Old Estate\"\nDefaultEstateOwnerName = \"Jane Doe\"\n");

        $ui = new SimUiFake(['is already configured' => 'abandon', 'Simulator name' => 'Welcome']);
        expect((new NewSim($ui))->prepare($grid, $profile))->toBeNull()->and($ui->notes)->toContain('Left unchanged.');

        $ui = new SimUiFake(['Simulator name' => 'Welcome', 'Name of the new account' => 'Ann Poe', 'Password of the new account' => 'secret1']);
        $plan = (new NewSim($ui))->prepare($grid, $profile);
        // Its own settings are the ones proposed
        expect($plan->httpPort)->toBe(9050)->and($plan->estateName)->toBe('Old Estate');
    });
});

describe('A setup file that answers the questions about a simulator', function () {
    /** What the file says: the simulator, the owner of its estate, its first region. */
    function sim_values(array $more = []): array
    {
        return $more + [
            'simulator' => ['name' => 'Garden Sim', 'http_port' => '9020', 'database_user' => 'opensim', 'database_password' => 'secret1'],
            'owner' => ['name' => 'Ann Poe', 'password' => 'secret2', 'email' => 'ann@example.org'],
            'region' => ['name' => 'Roses', 'location' => '8100,8100', 'roles' => [Region::flagName(Region::DEFAULT_REGION)]],
        ];
    }

    test('is not asked anything when its answers are right, and the account of the owner is made', function () {
        [$grid, $profile] = sim_install();
        $ui = new SimUiFake();

        $plan = (new NewSim($ui))->prepare($grid, $profile, null, sim_values());

        expect($ui->asked)->toBe([])
            ->and($plan->simName)->toBe('Garden Sim')
            ->and($plan->httpPort)->toBe(9020)
            ->and($plan->consolePort)->toBe(9024)
            ->and([$plan->dbUser, $plan->dbPass])->toBe(['opensim', 'secret1'])
            ->and($plan->createOwner)->toBeTrue()
            ->and([$plan->estateOwner, $plan->ownerPassword, $plan->ownerEmail])->toBe(['Ann Poe', 'secret2', 'ann@example.org'])
            ->and($plan->createRegion)->toBeTrue()
            ->and($plan->regionName)->toBe('Roses')
            ->and($plan->regionLocation)->toBe('8100,8100')
            ->and($plan->regionRoles)->toBe([Region::flagName(Region::DEFAULT_REGION)]);
    });

    test('has the owner of the file as the owner when the grid has the account, and no account to make', function () {
        [$grid, $profile] = sim_install(['planned' => false, 'dbName' => 'nowhere', 'dbHost' => '127.0.0.1:1']);
        $ui = new SimUiFake();

        $plan = (new NewSim($ui))->prepare($grid, $profile, null, sim_values());

        // The accounts of this grid cannot be read here: the owner has to exist
        expect($ui->asked)->toBe([])->and($plan->estateOwner)->toBe('Ann Poe')->and($plan->createOwner)->toBeFalse();
    });

    test('has the screens shown, its answers proposed, when one of them is refused', function () {
        [$grid, $profile] = sim_install();
        $ui = new SimUiFake(['Simulator HTTP port' => '9030']);
        $values = sim_values(['simulator' => ['name' => 'Garden Sim', 'http_port' => 'abc', 'database_user' => 'opensim', 'database_password' => 'secret1']]);

        $plan = (new NewSim($ui))->prepare($grid, $profile, null, $values);

        expect($ui->asked)->toContain('Simulator HTTP port')
            ->and($plan->httpPort)->toBe(9030)
            ->and($plan->simName)->toBe('Garden Sim')
            ->and($plan->estateOwner)->toBe('Ann Poe');
    });

    test('has the region taken at the free place nearest to the one it asks, without asking', function () {
        [$grid, $profile] = sim_install();
        $ui = new SimUiFake();
        $values = sim_values(['region' => ['name' => 'Roses', 'roles' => []]]);

        $plan = (new NewSim($ui))->prepare($grid, $profile, null, $values);

        expect($ui->asked)->toBe([])->and($plan->regionName)->toBe('Roses')->and($plan->regionRoles)->toBe([])->and($plan->regionLocation)->not->toBe('');
    });

    test('is edited with every question asked, its answers being the ones proposed', function () {
        [$grid, $profile] = sim_install();
        $ui = new SimUiFake();

        $plan = (new NewSim($ui))->prepare($grid, $profile, null, sim_values(), true);

        expect($ui->asked)->toContain('Simulator name', 'Simulator HTTP port', 'Database user', 'Estate name', 'Region name')
            ->and($plan->simName)->toBe('Garden Sim')
            ->and($plan->httpPort)->toBe(9020)
            ->and([$plan->dbUser, $plan->dbPass])->toBe(['opensim', 'secret1'])
            ->and($plan->estateOwner)->toBe('Ann Poe')
            ->and($plan->regionName)->toBe('Roses');
    });
});
