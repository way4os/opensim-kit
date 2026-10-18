<?php

declare(strict_types=1);

use OpenSimKit\Ui\Back;
use OpenSimKit\Ui\Quit;
use OpenSimKit\Ui\ScriptUi;

/**
 * Asks a few questions through ScriptUi, as the setup does, and writes what it was answered in RESULT (a JSON file): the other
 * half of tests/Unit/setup-frontend-test.sh. Usage: script-driver.php [scenario] --frontend DIR
 */
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$dir = $argv[array_search('--frontend', $argv, true) + 1];
$scenario = $argv[1] !== '--frontend' ? $argv[1] : 'questions';
$ui = new ScriptUi(fopen("$dir/questions", 'w'), fopen("$dir/answers", 'r'));
$result = [];

try {
    $ui->intro('Test setup');
    $ui->note("Line one\nline two");
    $ui->warn('A warning');
    if ($scenario === 'back') {
        try {
            $ui->text('Name');
        } catch (Back) {
            $result['back'] = true;
        }
        try {
            $ui->text('Name again');
        } catch (Quit) {
            $result['quit'] = true;
        }
    } elseif ($scenario === 'fail') {
        $ui->error('It failed');
        $ui->close(3);
        exit(3);
    } else {
        $result['name'] = $ui->text('Name', 'Ada', static fn(string $v): ?string => strlen($v) < 3 ? 'Too short.' : null);
        $result['password'] = $ui->secret('Password');
        $result['sure'] = $ui->confirm('Sure?', true);
        $result['which'] = $ui->choose('Which', ['a' => 'Option A', 'b' => 'Option B'], 'a');
        $result['some'] = $ui->checklist('Some', ['x' => 'X', 'y' => 'Y', 'z' => 'Z'], ['x']);
        $ui->spin(static function () use ($ui): void {
            $ui->stream("working on it\n");
        }, 'Busy');
    }
    $ui->outro('All done');
} finally {
    file_put_contents((string) getenv('RESULT'), json_encode($result));
}
$ui->close(0);
