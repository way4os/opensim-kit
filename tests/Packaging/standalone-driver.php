<?php

declare(strict_types=1);

use OpenSim\Api\Api;
use OpenSim\Api\Router;
use OpenSim\Models\Grid;
use OpenSim\Scheduling\Scheduler;
use OpenSim\OpenSim;
use OpenSim\Provisioning\DatabaseService;
use OpenSim\Provisioning\GridPlanner;
use OpenSim\Provisioning\GridProvisioner;
use OpenSim\Provisioning\RegionPlanner;
use OpenSim\Provisioning\SimulatorPlanner;
use OpenSim\Provisioning\SimulatorProvisioner;
use OpenSim\Provisioning\UiInterface;
use OpenSim\Provisioning\UiReporter;
use OpenSim\Exceptions\SetupFailedException;

/**
 * Makes a standalone with the planners and the provisioners of the engine, in the container of standalone-check.sh:
 *
 *   standalone-driver.php grid       the grid, with no Robust
 *   standalone-driver.php simulator  its simulator and its first region, started
 *   standalone-driver.php again      a second simulator, which is refused
 *   standalone-driver.php monitor    the monitor of the grid, as text
 *   standalone-driver.php stop       stop the grid, now
 *   standalone-driver.php start      start the grid
 *
 * The layout comes from the environment (OPENSIM_ETC_ROOT...), the answers from TEST_* variables.
 */

require ($argv[2] ?? '/work/engine') . '/vendor/autoload.php';

/** The interface of the setup, with the answers already given: it says what the provisioners say. */
final class SaidUi implements UiInterface
{
    public function intro(string $title): void {}

    public function note(string $message): void
    {
        echo "note: $message\n";
    }

    public function entity(string $name): string
    {
        return $name;
    }

    public function warn(string $message): void
    {
        echo "warn: $message\n";
    }

    public function error(string $message): void
    {
        echo "error: $message\n";
    }

    public function outro(string $message): void {}

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        return $default ?? array_key_first($options);
    }

    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array
    {
        return $defaults;
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        return $default;
    }

    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string
    {
        return '';
    }

    public function form(array $fields, string $title = ''): array
    {
        return [];
    }

    public function confirm(string $label, bool $default = true): bool
    {
        return false;
    }

    public function spin(\Closure $callback, string $message): mixed
    {
        return $callback();
    }
}

$ui = new SaidUi();
$environment = OpenSim::environment(new UiReporter($ui));
$value = static fn(string $name, string $default = ''): string => getenv($name) !== false ? (string) getenv($name) : $default;
$die = static function (array $problems): never {
    foreach ($problems as $field => $problem) {
        echo "problem: $field: $problem\n";
    }
    exit(2);
};

try {
    switch ($argv[1] ?? '') {
        case 'grid':
            $answers = [
                'name' => $value('TEST_GRID', 'Solo'),
                'nick' => $value('TEST_NICK', 'solo'),
                'standalone' => true,
                'hostname' => 'localhost',
                'public_port' => (int) $value('TEST_PORT', '9000'),
                'console' => 'rest',
                'helpers' => (bool) $value('TEST_HELPERS'),
                'helpers_path' => '/helpers',
                'web_url' => 'https://www.example.org',
                'hypergrid' => true,
                'offline' => $value('TEST_OFFLINE', getenv('TEST_HELPERS') ? 'helpers' : 'robust'),
                'database_host' => 'localhost',
                'database_name' => $value('TEST_DB_NAME', 'solo'),
                'database_user' => 'os',
                'database_password' => 'pw',
            ];
            $planner = GridPlanner::forMachine($environment);
            $problems = $planner->problems($answers);
            $problems === [] || $die($problems);
            $plan = $planner->plan($answers);
            $plan->enable = false;
            $plan->start = false;
            (new GridProvisioner($ui))->apply($plan, $environment);
            break;

        case 'simulator':
        case 'again':
            $grid = Grid::find($environment, $value('TEST_NICK', 'solo'));
            $grid !== null || $die(['grid' => 'not found']);
            $database = new DatabaseService($ui);
            $planner = SimulatorPlanner::forGrid($grid, $database, $ui, null, $argv[1] === 'again' ? 'Second' : 'Welcome');
            $answers = [
                'name' => $argv[1] === 'again' ? 'Second' : 'Welcome',
                'estate_owner' => '+',
                'new_name' => 'Ada Love',
                'new_password' => 'secret123',
                'new_email' => 'ada@example.org',
                'console' => 'rest',
            ];
            $problems = $planner->problems($answers);
            $problems === [] || $die($problems);
            $plan = $planner->plan($answers);
            $regions = RegionPlanner::forSimulator($plan, $grid, $database);
            $regions->apply($plan, ['name' => $answers['name']]);
            $plan->start = true;
            (new SimulatorProvisioner($ui))->apply($plan, $environment);
            break;

        case 'monitor':
            $router = new Router(new Api($environment, Scheduler::for($environment)), grid: $value('TEST_NICK', 'solo'));
            echo $router->get('/monitor', ['format' => 'text'])->body();
            break;

        case 'stop':
        case 'start':
            $results = $argv[1] === 'stop'
                ? OpenSim::stop($value('TEST_NICK', 'solo'), true, $environment)
                : OpenSim::start($value('TEST_NICK', 'solo'), $environment);
            foreach ($results as $result) {
                echo ($result->ok() ? 'ok' : 'failed') . ': ' . json_encode($result->message ?? '') . "\n";
            }
            exit(array_filter($results, static fn($r): bool => !$r->ok()) === [] ? 0 : 1);

        default:
            fwrite(STDERR, "usage: standalone-driver.php grid|simulator|again|monitor|stop|start [engine]\n");
            exit(64);
    }
} catch (SetupFailedException $e) {
    echo "setup failed: {$e->getMessage()}\n";
    exit(1);
}
