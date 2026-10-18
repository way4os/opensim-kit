<?php

use OpenSim\Exceptions\SetupFailedException;
use OpenSim\Runtime\ShellService;
use OpenSimKit\Setup\Actions;
use OpenSimKit\Setup\Hub;
use OpenSimKit\Setup\NewGrid;
use OpenSimKit\Setup\NewSimulator;
use OpenSimKit\Setup\RuntimeCheck;
use OpenSimKit\Setup\SetupMode;
use OpenSimKit\Ui\Back;
use OpenSimKit\Ui\Quit;
use OpenSimKit\Ui\ScriptUi;
use OpenSimKit\Web\WizardLauncher;
use OpenSimKit\Ui\PromptsUi;
use OpenSim\Provisioning\GridPlan;
use OpenSim\Provisioning\SimulatorPlan;

/**
 * OpenSim Debian — setup hub (PHP wizard).
 *
 * Single entry point: detect the current state and route to the relevant
 * action (install a core, create/modify a grid, …). The flow lives in classes
 * (Hub, Installer, Grid\NewGrid) driven through an InstallerUi, so a web
 * frontend can reuse them later.
 */

require dirname(__DIR__) . '/bootstrap.php';

// A failure is already explained on screen: end with an error code, rather
// than going back to the menu
try {
    if (($argv[1] ?? '') === '--apply-grid') {
        // The writing of a grid, run as the system user by the setup of
        // another user, which sends the plan on the standard input
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        (new NewGrid(new PromptsUi()))->apply(GridPlan::fromArray($data['plan']), $data['profile']);
    } elseif (($argv[1] ?? '') === '--apply-simulator') {
        // The same for a simulator
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        (new NewSimulator(new PromptsUi()))->apply(SimulatorPlan::fromArray($data['plan']), $data['profile']);
    } elseif (($argv[1] ?? '') === '--apply-action') {
        // What a menu does to the install (enable, disable, restart), as the system user
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        exit((new Actions(new PromptsUi()))->perform($data['op'], $data['args'], $data['profile']) ? 0 : 1);
    } else {
        // In the terminal, or in a page of the browser: the flow is the same, only the interface changes
        $terminal = new PromptsUi();
        $mode = SetupMode::resolve(array_slice($argv, 1), stream_isatty(STDIN) ? static fn(): string => SetupMode::ask($terminal) : null);
        $session = null;
        $script = null;
        $ending = _('The setup stopped.');
        $status = 0;
        try {
            $ui = $terminal;
            if ($mode === SetupMode::WEB) {
                try {
                    $network = SetupMode::network(array_slice($argv, 1), ['SSH_CONNECTION' => (string) getenv('SSH_CONNECTION')], (string) gethostname());
                } catch (\InvalidArgumentException $e) {
                    // What was asked of the web side cannot be done, or is not safe to guess
                    $terminal->error($e->getMessage());
                    exit(2);
                }
                $session = WizardLauncher::start(SetupMode::port(array_slice($argv, 1)), $network);
                $opened = WizardLauncher::open($session->url);
                $terminal->note(SetupMode::instructions($session->url, $session->port, $opened, getenv('SSH_CONNECTION') !== false, $network['open'], $network['allow']));
                $ui = $session->ui;
                // What the commands of the setup say (downloads, installs) is shown on the page, not in the terminal
                ShellService::showOutputWith($ui->stream(...));
            }
            if ($mode === SetupMode::SCRIPT) {
                // The questions go to the script that started the setup (setup.sh), through the two pipes it made
                $dir = (string) SetupMode::frontend(array_slice($argv, 1));
                $questions = @fopen("$dir/questions", 'w');
                $answers = @fopen("$dir/answers", 'r');
                if ($questions === false || $answers === false) {
                    $terminal->error(sprintf(_('The pipes of the script are not in %s.'), $dir));
                    exit(2);
                }
                $kept = time();
                $ui = $script = new ScriptUi($questions, $answers, static function () use (&$kept): void {
                    // The person may take their time: the rights to act as the user of the install (sudo) do not expire meanwhile
                    if (time() - $kept > 240) {
                        $kept = time();
                        WizardLauncher::keepSudo();
                    }
                });
                ShellService::showOutputWith($ui->stream(...));
            }
            (new Hub($ui, new RuntimeCheck($ui)))->run();
            $ending = _('The setup is finished.');
        } catch (Back | Quit) {
            $ending = _('You left the setup.');
        } catch (SetupFailedException) {
            $status = 1;
        } finally {
            ShellService::showOutputWith(null);
            $session?->stop($ending);
            if ($script !== null) {
                try {
                    if ($status !== 0 || $ending !== _('The setup is finished.')) {
                        $script->note($ending);
                    }
                    $script->close($status);
                } catch (Quit) {
                    // The script is gone
                }
            }
        }
        exit($status);
    }
} catch (SetupFailedException) {
    exit(1);
}
