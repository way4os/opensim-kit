<?php

declare(strict_types=1);

/**
 * The command line of the kit: the commands it knows are its own, the others go to the legacy script as they were typed.
 */

use OpenSimKit\Console\Application;
use OpenSimKit\Console\LegacyScript;
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

        expect($app->dispatch(['opensim', '-v', 'start', '--wait', 'vonda_welcome']))
            ->toBe(7)
            ->and($app->dispatch(['opensim']))
            ->toBe(7)
            ->and($app->dispatch(['opensim', '--help']))
            ->toBe(7)
            ->and($app->dispatch(['opensim', 'help']))
            ->toBe(7)
            ->and($app->dispatch(['opensim', 'list']))
            ->toBe(7)
            ->and($handed)
            ->toBe([['-v', 'start', '--wait', 'vonda_welcome'], [], ['--help'], ['help'], ['list']]);
    });

    test('has the version of the kit', function () {
        $handed = [];

        expect(console_application($handed)->getVersion())->toBe('3.0.0-beta.9');
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

        $status = $app->dispatch(['opensim', 'portmapping', '--help']);

        expect($status)->toBe(0)->and($handed)->toBe([]);
    });
});

describe('The portmapping command', function () {
    test('refuses what it does not understand, with the usage, and the status of an invalid command', function () {
        $handed = [];
        $app = console_application($handed);
        $output = new BufferedOutput();

        $status = $app->run(new ArrayInput(['command' => 'portmapping', 'action' => 'restart', 'instance' => 'demo', 'ini' => '/nowhere/demo.ini']), $output);

        expect($status)->toBe(2)->and($output->fetch())->toContain('usage: opensim portmapping start|stop');
    });
});
