<?php

/**
 * The setup side of the web wizard test: the real web server (php -S) is started, its address is the first line said, then
 * a few questions are asked through it and the answers are said as JSON. Run by WizardWebTest.
 */

use OpenSimKit\Ui\Back;
use OpenSimKit\Ui\Quit;
use OpenSimKit\Web\WizardLauncher;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// OPEN=<addresses>: the server is open to the network for these clients only (see SetupMode::network())
$open = (string) getenv('WIZARD_TEST_OPEN');
$session = $open === ''
    ? WizardLauncher::start()
    : WizardLauncher::start(null, ['listen' => '0.0.0.0', 'allow' => explode(',', $open), 'host' => '127.0.0.1', 'open' => true]);
fwrite(STDOUT, $session->url . "\n");
$ui = $session->ui;
$out = [];
try {
    $ui->note('Hello <from> the setup');
    $out['name'] = $ui->text('Your name', 'Ann', static fn(string $v): ?string => $v === 'bad' ? 'Not that one' : null);
    $out['form'] = $ui->form(
        [
            ['key' => 'mode', 'label' => 'Console', 'type' => 'choice', 'options' => ['screen' => 'Screen', 'rest' => 'REST'], 'default' => 'screen'],
            ['key' => 'port', 'label' => 'Port', 'default' => '8007', 'condition' => ['mode' => ['rest']], 'when' => static fn(array $v): bool => ($v['mode'] ?? '') === 'rest'],
        ],
        'Grid',
    );
    $out['spun'] = $ui->spin(static fn(): string => 'worked', 'Working');
    $out['choice'] = $ui->choose('Menu', ['go' => 'Go on', 'quit' => 'Quit'], 'go');
    fwrite(STDOUT, json_encode($out) . "\n");
    $session->stop('Finished');
} catch (Back | Quit $e) {
    fwrite(STDOUT, json_encode(['left' => $e::class]) . "\n");
    $session->stop('You left');
}
