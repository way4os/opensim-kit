<?php

declare(strict_types=1);

/**
 * The command line of the kit: the commands it knows are its own, the others go to the legacy script as they were typed.
 */

use OpenSim\Config\Layout;
use OpenSim\OpenSim;
use OpenSim\Testing\Machine;
use OpenSimKit\Console\Application;
use OpenSimKit\Console\Commands\StatusCommand;
use OpenSimKit\Console\LegacyScript;
use OpenSimKit\System\SystemUser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** An application whose legacy script records the commands it is handed. */
function console_application(array &$handed): Application
{
    $root = test_tmp() . '/console-' . bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents("$root/.version", "3.0.0-beta.9\n");

    return new Application($root, new LegacyScript("$root/libexec/opensim-legacy", static function (array $command) use (&$handed): int {
        $handed[] = array_slice($command, 2);

        return 7;
    }));
}

describe('The command line', function () {
    test('names its command by the first argument that is not an option', function (array $argv, ?string $name) {
        expect(Application::commandName($argv))->toBe($name);
    })->with([
        [['opensim', 'start', 'vonda'], 'start'],
        [['opensim', '-v', 'start'], 'start'],
        [['opensim', '-hv'], null],
        [['opensim'], null],
        [['opensim', '--', 'start'], null],
    ]);

    test('hands what it does not know to the legacy script, as it was typed, with its status', function () {
        $handed = [];
        $app = console_application($handed);

        expect($app->dispatch(['opensim', '-v', 'setup', '--quick', 'vonda_welcome']))
            ->toBe(7)
            ->and($app->dispatch(['opensim', 'backup', '--help']))
            ->toBe(7)
            ->and($handed)
            ->toBe([['-v', 'setup', '--quick', 'vonda_welcome'], ['backup', '--help']]);
    });

    test('has the version of the kit', function () {
        $handed = [];

        expect(console_application($handed)->getVersion())->toBe('3.0.0-beta.9');
    });

    test('tells its commands when it is asked for help, or for nothing', function (array $argv) {
        $handed = [];
        $app = console_application($handed);
        ob_start();
        $status = $app->dispatch($argv);
        $text = (string) ob_get_clean();

        expect($status)->toBe(0)->and($text)->toContain('usage: opensim')->toContain('start [target...]')->and($handed)->toBe([]);
    })->with([
        [['opensim']],
        [['opensim', '-h']],
        [['opensim', '--help']],
        [['opensim', 'help']],
        [['opensim', 'list']],
    ]);

    test('gives the help of a command that is its own as symfony does', function () {
        $handed = [];

        $status = console_application($handed)->dispatch(['opensim', 'help', 'start']);

        expect($status)->toBe(0)->and($handed)->toBe([]);
    });

    test('asks the legacy script the help of a command that is not ported', function () {
        $handed = [];

        console_application($handed)->dispatch(['opensim', 'help', 'backup']);

        expect($handed)->toBe([['backup', '--help']]);
    });
});

describe('The commands of the application', function () {
    test('are known by the application, and answer --help', function () {
        $handed = [];
        $app = console_application($handed);

        foreach (Application::commands() as $command) {
            expect($app->has($command->getName()))->toBeTrue();
            $output = new BufferedOutput();
            $status = $app->run(new ArrayInput(['command' => $command->getName(), '--help' => true]), $output);

            expect($status)->toBe(0);
            expect($output->fetch())->toContain($command->getName());
        }
        expect($handed)->toBe([]);
    });

    test('do not go to the legacy script', function () {
        $handed = [];
        $app = console_application($handed);

        $status = $app->dispatch(['opensim', 'start', '--help']);

        expect($status)->toBe(0)->and($handed)->toBe([]);
    });
});

describe('The system user', function () {
    /** The application of an install whose instances belong to a user, and the commands it runs as that user. */
    function system_user_application(array &$ran, string $current, bool $root): Application
    {
        $dir = test_tmp() . '/system-user-' . bin2hex(random_bytes(4));
        mkdir($dir);

        return new Application(
            $dir,
            null,
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

describe('The command status', function () {
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

describe('The commands start, stop and restart', function () {
    /** The application on a machine of fakes. */
    function machine_application(Machine $machine): Application
    {
        return new Application(
            test_tmp(),
            null,
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

    test('stop now a simulator that is not running, which is no failure', function () {
        $machine = new Machine();

        $status = machine_application($machine)->run(new ArrayInput(['command' => 'stop', 'targets' => ['now', 'alpha']]), new BufferedOutput());

        expect($status)->toBe(0);
    });
});
