<?php

use OpenSimKit\Ui\PresetUi;
use OpenSimKit\Ui\QuietUi;
use OpenSim\Provisioning\AccountList;
use OpenSim\Provisioning\GridPlan;
use OpenSim\Provisioning\SetupFile;
use OpenSim\Provisioning\SimPlan;

const SETUP_YAML = <<<'YAML'
grid:
  name: Test Grid
  hostname: play.example.org
  database: { user: opensim, password: secret1 }
owner: { name: Jane Doe, password: hunter22 }
simulators:
  - name: Welcome
    regions:
      - { name: Welcome, location: "1000,1000", roles: [default, default_hg] }
      - { name: Second Region, port: 9003 }
users:
  - { first: Bob, last: Roe, password: pw123456 }
  - { first: Cy, last: Doe, email: cy@example.org }
YAML;

test('reads a setup from YAML or from JSON', function () {
    $yaml = SetupFile::parse(SETUP_YAML);
    $json = SetupFile::parse(json_encode($yaml));

    expect($yaml['grid']['name'])
        ->toBe('Test Grid')
        ->and($json)
        ->toBe($yaml)
        ->and($yaml['simulators'][0]['regions'][1]['port'])
        ->toBe(9003);
});

test('says what is missing', function () {
    expect(fn() => SetupFile::parse("grid:\n  name: X\n"))->toThrow(
        InvalidArgumentException::class,
        'grid.database.user is required',
    );
    expect(
        fn() => SetupFile::parse(
            "grid: { name: X, database: { user: a, password: b } }\nowner: { name: Jane, password: abc }\n",
        ),
    )->toThrow(InvalidArgumentException::class, 'owner.name');
    expect(fn() => SetupFile::parse("- just\n- a list\n"))->toThrow(InvalidArgumentException::class);
});

test('requires no email', function () {
    $data = SetupFile::parse(SETUP_YAML);

    expect(SetupFile::problems($data))
        ->toBe([])
        ->and(isset($data['owner']['email']))
        ->toBeFalse();
});

test('gives the answers of the grid, by the names of its fields', function () {
    $values = SetupFile::gridValues(SetupFile::parse(SETUP_YAML));

    expect($values)->toBe([
        'name' => 'Test Grid',
        'hostname' => 'play.example.org',
        'database_user' => 'opensim',
        'database_password' => 'secret1',
    ]);
    expect(array_keys(SetupFile::gridValues(['grid' => ['public_port' => 8012, 'console' => 'rest', 'hypergrid' => false, 'database' => ['host' => 'db']]])))
        ->toBe(['hypergrid', 'public_port', 'console', 'database_host']);
});

test('gives the answers of a simulator, its owner and its regions', function () {
    $data = SetupFile::parse(SETUP_YAML);

    expect(SetupFile::simValues($data, 0))->toBe(['name' => 'Welcome', 'database_user' => 'opensim', 'database_password' => 'secret1'])
        // the owner of the file owns the estate, to make if the grid does not have it
        ->and(SetupFile::owner($data, 0))->toBe(['name' => 'Jane Doe', 'password' => 'hunter22', 'email' => ''])
        ->and(SetupFile::regionValues($data, 0, 0))->toBe(['name' => 'Welcome', 'location' => '1000,1000', 'roles' => ['DefaultRegion', 'DefaultHGRegion']])
        ->and(SetupFile::regionValues($data, 0, 1))->toBe(['name' => 'Second Region', 'port' => '9003']);

    $data['simulators'][0]['database'] = ['host' => 'sim-db', 'name' => 'own_db'];
    $data['simulators'][0]['estate'] = ['name' => 'My Estate', 'owner' => 'John Roe'];
    expect(SetupFile::simValues($data, 0))->toMatchArray(['database_host' => 'sim-db', 'database_name' => 'own_db', 'database_user' => 'opensim', 'estate_name' => 'My Estate'])
        ->and(SetupFile::owner($data, 0)['name'])->toBe('John Roe');
});

test('answers what the application asks once the grid is made, and the administrator of the database', function () {
    $data = SetupFile::parse(SETUP_YAML);
    $data['grid']['database']['admin_user'] = 'dbroot';
    $data['grid']['database']['admin_password'] = 'rootpw';
    $data['grid']['start'] = false;

    expect(SetupFile::gridPrompts($data))->toBe(['Administrator user' => 'dbroot', 'Password of ' => 'rootpw', 'Enable grid' => true, 'Apply this configuration' => true, 'Start grid' => false])
        ->and(SetupFile::simPrompts($data, 0))->toMatchArray(['Administrator user' => 'dbroot', 'Enable simulator' => true, 'Start simulator' => true])
        ->and(SetupFile::regionPrompts($data, 0))->toBe(['Add region' => true, 'Load it now' => true]);

    $data['grid']['enable'] = false;
    expect(SetupFile::gridPrompts($data)['Start grid'])->toBeFalse();

    // A region added to a simulator that is not to be started is not loaded
    $data['simulators'][0]['enable'] = false;
    expect(SetupFile::regionPrompts($data, 0)['Load it now'])->toBeFalse();
    $data['simulators'][0]['enable'] = true;
    $data['simulators'][0]['start'] = false;
    expect(SetupFile::regionPrompts($data, 0)['Load it now'])->toBeFalse();
    unset($data['simulators'][0]['enable'], $data['simulators'][0]['start']);

    $ui = new PresetUi(new QuietUi(), SetupFile::simPrompts($data, 0));
    expect($ui->text('Administrator user', 'root'))->toBe('dbroot')
        ->and($ui->secret('Password of dbroot'))->toBe('rootpw')
        ->and($ui->confirm('Enable simulator (link into opensim.d)', false))->toBeTrue();
});

test('keeps what the setup did, to make it again', function () {
    $grid = new GridPlan();
    $grid->gridName = 'Test Grid';
    $grid->gridNick = 'test_grid';
    $grid->dbName = 'test_grid_robust';
    $grid->dbUser = 'opensim';
    $grid->dbPass = 'secret1';
    $data = SetupFile::withGrid([], $grid);

    $sim = new SimPlan();
    $sim->simName = 'Welcome';
    $sim->regionName = 'Welcome';
    $sim->estateOwner = 'Jane Doe';
    $sim->dbName = 'welcome_db';
    $sim->estateName = 'Test Estate';
    $sim->createOwner = true;
    $sim->ownerPassword = 'hunter22';
    $data = SetupFile::withSim($data, $sim);
    // one more region, later
    $more = new SimPlan();
    $more->simName = 'Welcome';
    $more->regionName = 'Second';
    $more->dbUser = '';
    $data = SetupFile::withSim($data, $more);

    expect($data['grid']['database']['name'])
        ->toBe('test_grid_robust')
        ->and($data['owner']['name'])
        ->toBe('Jane Doe')
        ->and($data['owner'])
        ->not->toHaveKey('password')
        ->and($data['owner']['password_hash'])
        ->toBe(\OpenSim\Provisioning\AccountWriter::passwordHash('hunter22', $data['owner']['password_salt']))
        ->and(array_column($data['simulators'][0]['regions'], 'name'))
        ->toBe(['Welcome', 'Second'])
        ->and(count($data['simulators']))
        ->toBe(1)
        // a region added later, which knows nothing of the database, does not erase it
        ->and($data['simulators'][0]['database']['name'])
        ->toBe('welcome_db')
        ->and($data['simulators'][0]['estate'])
        ->toBe(['name' => 'Test Estate', 'owner' => 'Jane Doe']);
    // and what it kept is a setup that reads
    expect(SetupFile::problems($data))->toBe([]);
});

test('writes the setup file of a grid for its owner and the group of the install', function () {
    $dir = test_tmp() . '/setup-' . uniqid();
    mkdir($dir);
    SetupFile::record($dir, static fn(array $d): array => $d + ['version' => 1]);

    expect(fileperms("$dir/setup.json") & 0o777)
        ->toBe(0o640)
        ->and(json_decode(file_get_contents("$dir/setup.json"), true))
        ->toBe(['version' => 1]);
});

test('gives the users of a setup file to the bulk import', function () {
    $list = AccountList::parse(SETUP_YAML, 'yaml');

    expect(array_column($list['accounts'], 'first'))
        ->toBe(['Bob', 'Cy'])
        ->and($list['errors'])
        ->toBe([])
        ->and(AccountList::parse((string) json_encode(SetupFile::parse(SETUP_YAML)), 'json')['accounts'])
        ->toHaveCount(2);
});
