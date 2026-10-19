<?php

declare(strict_types=1);

/**
 * The command line of the kit: the commands are its own, the help is its own, what it does not know is refused.
 */

use OpenSim\Config\Layout;
use OpenSim\OpenSim;
use OpenSim\Testing\Machine;
use OpenSimKit\Console\Application;
use OpenSimKit\Console\Commands\StatusCommand;
use OpenSimKit\System\SystemUser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** An application for a kit in a temporary folder, its install the defaults. */
function console_application(): Application
{
    $root = test_tmp() . '/console-' . bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents("$root/.version", "3.0.0-beta.9\n");

    return new Application($root, new Layout(['EtcRoot' => "$root/etc"]));
}

describe('command line', function () {
    test('names its command by the first argument that is not an option', function (array $argv, ?string $name) {
        expect(Application::commandName($argv))->toBe($name);
    })->with([
        [['opensim', 'start', 'vonda'], 'start'],
        [['opensim', '-v', 'start'], 'start'],
        [['opensim', '-hv'], null],
        [['opensim'], null],
        [['opensim', '--', 'start'], null],
    ]);

    test('refuses what it does not know, with its status', function () {
        $app = console_application();

        expect($app->dispatch(['opensim', 'nosuchcommand']))->toBe(1);
    });

    test('has the version of the kit', function () {
        expect(console_application()->getVersion())->toBe('3.0.0-beta.9');
    });

    test('tells its commands when it is asked for help, or for nothing', function (array $argv) {
        $app = console_application();
        ob_start();
        $status = $app->dispatch($argv);
        $text = (string) ob_get_clean();

        expect($status)->toBe(0)->and($text)->toContain('usage: opensim')->toContain('start [--simulators] [target...]')
;
    })->with([
        [['opensim']],
        [['opensim', '-h']],
        [['opensim', '--help']],
        [['opensim', 'help']],
        [['opensim', 'list']],
    ]);

    test('gives the help of a command as symfony does', function () {
        ob_start();
        $status = console_application()->dispatch(['opensim', 'help', 'start']);
        ob_end_clean();

        expect($status)->toBe(0);
    });
});

describe('commands of the application', function () {
    test('are known by the application, and answer --help', function () {
        $app = console_application();

        foreach (Application::commands() as $command) {
            expect($app->has($command->getName()))->toBeTrue();
            $output = new BufferedOutput();
            $status = $app->run(new ArrayInput(['command' => $command->getName(), '--help' => true]), $output);

            expect($status)->toBe(0);
            expect($output->fetch())->toContain($command->getName());
        }
    });

    test('are all the ones of the help', function () {
        $names = array_map(static fn($c): string => $c->getName(), Application::commands());

        foreach (['start', 'stop', 'restart', 'status', 'enable', 'disable', 'console', 'command', 'ports', 'next', 'web', 'import', 'profile', 'oar', 'save', 'load', 'backup', 'rest', 'setup', 'install-dotnet'] as $command) {
            expect($names)->toContain($command);
        }
    });
});

describe('system user', function () {
    /** The application of an install whose instances belong to a user, and the commands it runs as that user. */
    function system_user_application(array &$ran, string $current, bool $root): Application
    {
        $dir = test_tmp() . '/system-user-' . bin2hex(random_bytes(4));
        mkdir($dir);

        return new Application(
            $dir,
            new Layout(['SystemUser' => 'opensim', 'EtcRoot' => $dir . '/etc']),
            new SystemUser(static function (array $command) use (&$ran): int {
                $ran = $command;

                return 5;
            }, $current, $root),
        );
    }

    test('runs the commands that work on the instances again as that user, with sudo', function () {
        $ran = [];
        $app = system_user_application($ran, 'olivier', false);

        expect($app->dispatch(['opensim', 'status']))
            ->toBe(5)
            ->and(array_slice($ran, 0, 4))->toBe(['sudo', '-H', '-u', 'opensim'])
            ->and(array_slice($ran, -2))->toBe([$app->layout()->etcRoot() === '' ? '' : dirname($app->layout()->etcRoot()) . '/bin/opensim', 'status']);
    });

    test('uses runuser as root', function () {
        $ran = [];

        system_user_application($ran, 'root', true)->dispatch(['opensim', 'restart', 'now', 'vonda']);

        expect(array_slice($ran, 0, 4))->toBe(['runuser', '-u', 'opensim', '--'])->and(array_slice($ran, -3))->toContain('restart', 'now', 'vonda');
    });

    test('does nothing for that user itself', function () {
        $ran = [];
        $app = system_user_application($ran, 'opensim', false);
        ob_start();
        $status = $app->dispatch(['opensim', 'start', '--help']);
        ob_end_clean();

        expect($status)->toBe(0)->and($ran)->toBe([]);
    });
});

describe('command status', function () {
    test('lists the enabled instances with what they use, then the summary', function () {
        $machine = new Machine();
        $machine->processes->add('dotnet OpenSim.dll -inifile=' . $machine->etc . '/opensim.d/alpha.ini', 'opensim', 1.5, 2.25, 100000);

        $text = StatusCommand::render(OpenSim::status($machine->environment));

        expect($text)->toBe(<<<'TEXT'
            INSTANCE  TYPE     PID     USER     %CPU  %MEM  RSS
            demo      robust   [down]
            alpha     opensim  4000    opensim  1.5   2.2   100000
            beta      opensim  [down]
            1 up, 2 down - cpu 1.5% - mem 2.2% - rss 100000

            TEXT);
    });

    test('counts the instances down only when there are some', function () {
        expect(StatusCommand::summary(2, 1, 'cpu 1.0%'))
            ->toBe('2 up, 1 down - cpu 1.0%')
            ->and(StatusCommand::summary(3, 0, 'cpu 1.0%'))->toBe('3 up - cpu 1.0%')
            ->and(StatusCommand::summary(1, 1, 'x', true))->toBe("\033[32m1 up\033[0m, \033[31m1 down\033[0m - x");
    });
});

describe('commands start, stop and restart', function () {
    /** The application on a machine of fakes. */
    function machine_application(Machine $machine): Application
    {
        return new Application(
            test_tmp(),
            $machine->environment->layout,
            new SystemUser(static fn(array $c): int => 99, 'opensim', false),
            static fn($reporter) => $machine->environment->reporting($reporter),
        );
    }

    test('fail when what they are asked for does not exist, and say it', function () {
        $machine = new Machine();
        $output = new BufferedOutput();

        $status = machine_application($machine)->run(new ArrayInput(['command' => 'start', 'targets' => ['nosuch']]), $output);

        expect($status)->toBe(1);
    });

    test('take --simulators for the simulators of the grid, which a grid alone leaves out', function () {
        $machine = new Machine();
        $machine->processes->add('dotnet OpenSim.dll -inifile=' . $machine->etc . '/opensim.d/alpha.ini');
        $machine->consoles->console('alpha')->onSend = fn(string $l) => $l === 'shutdown' && $machine->processes->remove(4000);

        $status = machine_application($machine)->run(new ArrayInput(['command' => 'stop', 'targets' => ['now', 'demo'], '--simulators' => true]), new BufferedOutput());

        expect($status)->toBe(0)->and($machine->processes->all())->toBe([]);
    });

    test('stop now a simulator that is not running, which is no failure', function () {
        $machine = new Machine();

        $status = machine_application($machine)->run(new ArrayInput(['command' => 'stop', 'targets' => ['now', 'alpha']]), new BufferedOutput());

        expect($status)->toBe(0);
    });
});

describe('command api', function () {
    test('calls the API of the engine with the words typed, prints the document, and fails with the problem', function () {
        $machine = new Machine();
        $command = new \OpenSimKit\Console\Commands\ApiCommand(static fn($reporter) => $machine->environment);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);

        $tester->execute(['args' => ['GET', '/simulators/alpha']]);
        $found = json_decode($tester->getDisplay(), true);
        $failed = $tester->execute(['args' => ['/simulators/nobody']]);
        $grid = $tester->execute(['args' => ['--grid=demo', 'GET', '/monitor', 'format=text']]);

        expect($found['data']['type'])
            ->toBe('simulators')
            ->and($failed)
            ->toBe(1)
            ->and($grid)
            ->toBe(0)
            ->and($tester->getDisplay())
            ->toContain('Demo');
    });
});

describe('command api-key', function () {
    test('makes a key shown once, lists the keys without what opens them, and revokes one', function () {
        $machine = new Machine();
        $command = new \OpenSimKit\Console\Commands\ApiKeyCommand(static fn($reporter) => $machine->environment);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);

        $created = $tester->execute(['args' => ['create', 'the', 'manager']], ['capture_stderr_separately' => true]);
        $key = trim($tester->getDisplay());
        $said = $tester->getErrorOutput();
        $tester->execute(['args' => ['list']]);
        $list = $tester->getDisplay();
        $revoked = $tester->execute(['args' => ['revoke', 'the manager']]);
        $tester->execute(['args' => ['list']]);

        expect($created)
            ->toBe(0)
            ->and($key)
            ->toStartWith('osk_')
            ->and($said)
            ->toContain("made for 'the manager'", 'not shown again')
            ->and($list)
            ->toContain('the manager')
            ->toContain('active')
            ->not->toContain($key)
            ->and($revoked)
            ->toBe(0)
            ->and($tester->getDisplay())
            ->toContain('revoked')
            ->and($tester->execute(['args' => ['create']]))
            ->toBe(2)
            ->and($tester->execute(['args' => ['revoke', 'nobody']]))
            ->toBe(1)
            ->and($tester->execute(['args' => ['frobnicate']]))
            ->toBe(2);
    });
});
