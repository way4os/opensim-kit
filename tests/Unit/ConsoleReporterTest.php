<?php

declare(strict_types=1);

/**
 * What the engine says on the error output of the command.
 */

use OpenSimKit\Console\ConsoleReporter;
use Symfony\Component\Console\Output\BufferedOutput;

describe('The reporter of the command line', function () {
    test('says what is done as opensim: <message>, and the problems', function () {
        $output = new BufferedOutput();
        $reporter = new ConsoleReporter($output, false);

        $reporter->info('starting demo');
        $reporter->error('demo failed [to] start');

        expect($output->fetch())->toBe("opensim: starting demo\nopensim: demo failed [to] start\n");
    });

    test('keeps the details for -v', function () {
        $quiet = new BufferedOutput();
        (new ConsoleReporter($quiet, false))->debug('the command');
        $verbose = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);
        (new ConsoleReporter($verbose, false))->debug('the command');

        expect($quiet->fetch())->toBe('')->and($verbose->fetch())->toBe("opensim: the command\n");
    });

    test('shows a step once on a line of its own when it is not a terminal', function () {
        $output = new BufferedOutput();
        $reporter = new ConsoleReporter($output, false);

        $reporter->step('  Stopping x in 80s');
        $reporter->step('  Stopping x in 80s');
        $reporter->step('  Stopping x in 40s');
        $reporter->stepEnd();

        expect($output->fetch())->toBe("  Stopping x in 80s\n  Stopping x in 40s\n");
    });

    test('takes the place of the previous step on a terminal, and closes the line', function () {
        $output = new BufferedOutput();
        $reporter = new ConsoleReporter($output, true, 20);

        $reporter->step('first step');
        $reporter->step('second step that is very long');
        $reporter->info('done');

        expect($output->fetch())->toBe("\r\033[2Kfirst step\r\033[2Ksecond step that is\nopensim: done\n");
    });

    test('says nothing of the steps when quiet', function () {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_QUIET);
        (new ConsoleReporter($output, false))->step('x');

        expect($output->fetch())->toBe('');
    });
});
