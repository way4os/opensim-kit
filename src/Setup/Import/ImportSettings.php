<?php

declare(strict_types=1);

namespace OpenSimKit\Setup\Import;

use OpenSim\Config\IniReader;
use OpenSim\Models\GridInfo;
use OpenSim\Provisioning\Import\RobustImporter;
use OpenSim\Provisioning\Import\SimImporter;
use OpenSim\Provisioning\SetupFailedException;
use OpenSim\Provisioning\SimConfig;
use OpenSimKit\Setup\Actions;
use OpenSimKit\Setup\InstallConfig;
use OpenSimKit\Setup\NewGrid;
use OpenSimKit\Setup\NewSim;
use OpenSimKit\System\Elevated;
use OpenSimKit\Ui\QuietUi;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The settings of a grid or of a simulator that was not made by the kit, ported without changing its files (`opensim import`,
 * for an ini file).
 *
 *   opensim import [NICK] FILE [--grid]       the config of a grid (a Robust ini)
 *   opensim import GRID FILE [--simulator]    the config of a simulator (an OpenSim ini)
 *   --name NAME  --core DIR  --apply
 *
 * What the setup asks is detected in the config (name, ports, database, console...) and goes through the generators of the kit: a
 * standard config, valid for the core chosen, in /etc/opensim/grids/<nick>/. The other values of the original that differ from
 * the defaults of the core are injected into it, so the settings of the user are kept; the places of the data stay where they are
 * (paths made absolute). A simulator's regions are copied, with their UUID. Nothing is written without --apply: the plan and the
 * settings that would be kept are told.
 *
 * Nothing is enabled or started: compare, then `opensim enable <instance>`.
 */
final class ImportSettings
{
    private const USAGE = "usage: opensim import [NICK] robust FILE [--core DIR] [--apply]\n       opensim import GRID sim FILE [--name NAME] [--core DIR] [--apply]\n";

    private OutputInterface $out;

    /**
     * @param list<string> $args [GRID] robust|sim FILE and the options, as ImportFile hands them
     */
    public function run(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $this->out = $out;
        // [GRID] robust|sim FILE: the grid comes first, as the instance does in the other commands (the nick of the grid to
        // make, or the grid the simulator joins)
        $grid = '';
        if (!in_array($args[0] ?? '', ['robust', 'sim'], true) && !str_starts_with($args[0] ?? '-', '-')) {
            $grid = array_shift($args);
        }
        $what = $args[0] ?? '';
        if (!in_array($what, ['robust', 'sim'], true)) {
            $err->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        array_shift($args);

        $options = ['nick' => $what === 'robust' ? $grid : '', 'core' => '', 'grid' => $what === 'sim' ? $grid : '', 'name' => ''];
        $apply = false;
        $words = [];
        for ($i = 0; $i < count($args); $i++) {
            if (preg_match('/^--(nick|core|grid|name)(?:=(.*))?$/', $args[$i], $m)) {
                $options[$m[1]] = $m[2] ?? ($args[++$i] ?? throw new ImportFailure("--{$m[1]} needs a value", 2));
            } elseif ($args[$i] === '--apply') {
                $apply = true;
            } else {
                $words[] = $args[$i];
            }
        }
        if (count($words) !== 1 || !is_readable($words[0])) {
            $err->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        $path = (string) realpath($words[0]);

        $profile = (new InstallConfig())->profile();
        if (($profile['EtcRoot'] ?? '') === '') {
            throw new ImportFailure('no installed framework found: install an OpenSim core first', 2);
        }
        $raw = IniReader::load($path);

        return $what === 'robust'
            ? $this->robust($raw, $path, $profile, $options, $apply)
            : $this->simulator($raw, $path, $profile, $options, $apply);
    }

    /**
     * @param array<string,array<string,string>> $raw
     * @param array<string,string> $profile
     * @param array<string,string> $options
     */
    private function robust(array $raw, string $path, array $profile, array $options, bool $apply): int
    {
        $core = $options['core'] !== '' ? rtrim($options['core'], '/') : (string) ($profile['CoreDirectory'] ?? '');
        if ($core === '' || !is_dir("$core/bin")) {
            throw new ImportFailure('choose the core the grid will run with: --core DIR (the folder that has bin/)', 2);
        }
        $plan = RobustImporter::plan($raw, $path, $profile, $options['nick'] ?: null, $core);
        if (is_string($plan)) {
            throw new ImportFailure($plan, 2);
        }
        $exampleFile = "{$plan->binDir}/" . ($plan->enableHypergrid ? 'Robust.HG.ini.example' : 'Robust.ini.example');
        if (!is_file($exampleFile)) {
            throw new ImportFailure("the core has no $exampleFile", 2);
        }
        if (is_file($plan->robustIni())) {
            throw new ImportFailure(
                "the grid {$plan->gridNick} is already configured ({$plan->robustIni()}): choose another nick: opensim import <nick> robust FILE",
                2,
            );
        }

        $customizations = RobustImporter::customizations(
            $raw,
            IniReader::parse(RobustImporter::generate($plan)),
            IniReader::load($exampleFile),
            dirname($path),
            (string) file_get_contents($exampleFile),
            IniReader::expand($raw),
        );
        $report = self::report(
            [
                "Grid {$plan->gridName} ({$plan->gridNick}), imported from $path",
                "Core: $core",
                "Database: {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser}), kept as it is",
                "Ports: public {$plan->publicPort}, private {$plan->privatePort}" . ($plan->consoleMode === 'rest' ? ", console {$plan->consolePort}" : ''),
                'Hypergrid: ' . ($plan->enableHypergrid ? 'yes' : 'no'),
            ],
            $customizations,
        );
        $this->say($report);
        if (!$apply) {
            $this->say("\nNothing was written: --apply writes the grid {$plan->gridNick} in {$plan->gridDir}.\n");

            return 0;
        }

        try {
            if (!Elevated::needed($profile)) {
                (new NewGrid(new QuietUi()))->apply($plan, $profile);
            } else {
                Elevated::run(new QuietUi(), '--apply-grid', ['plan' => $plan->toArray(), 'profile' => $profile], $profile['SystemUser']);
            }
        } catch (SetupFailedException) {
            throw new ImportFailure('the grid could not be written');
        }
        $done = $this->act($profile, 'inject-config', [
            'path' => $plan->robustIni(),
            'customizations' => $customizations,
            'report_path' => "{$plan->gridDir}/import-report.txt",
            'report' => $report,
        ]);
        if (!$done) {
            throw new ImportFailure("the grid is written, but its settings could not be added to {$plan->robustIni()}");
        }
        $this->say("\nWritten: {$plan->robustIni()}\nReport:  {$plan->gridDir}/import-report.txt\nCompare it with the original, then: opensim enable {$plan->gridNick}\n");

        return 0;
    }

    /**
     * A simulator of a grid the kit knows.
     *
     * @param array<string,array<string,string>> $raw
     * @param array<string,string> $profile
     * @param array<string,string> $options
     */
    private function simulator(array $raw, string $path, array $profile, array $options, bool $apply): int
    {
        $grid = $options['grid'] === '' ? null : GridInfo::load($profile, $options['grid']);
        if ($grid === null) {
            throw new ImportFailure('the grid of the simulator is one the kit knows (opensim setup, or opensim import for its Robust config): opensim import <grid> FILE', 2);
        }
        $core = $options['core'] !== ''
            ? rtrim($options['core'], '/')
            : ($grid->coreDirectory !== '' ? $grid->coreDirectory : (string) ($profile['CoreDirectory'] ?? ''));
        if ($core === '' || !is_dir("$core/bin")) {
            throw new ImportFailure('choose the core the simulator will run with: --core DIR (the folder that has bin/)', 2);
        }
        $plan = SimImporter::plan($raw, $path, $grid, $profile, $options['name'] ?: null, $core);
        if (is_string($plan)) {
            throw new ImportFailure($plan, 2);
        }
        if (is_file($plan->iniPath())) {
            throw new ImportFailure("the simulator {$plan->simName} is already configured ({$plan->iniPath()}): choose another name with --name", 2);
        }
        $regions = SimImporter::regionFiles($raw, $path);
        $plan->externalHost = SimImporter::externalHost($regions);

        // What the core has by default: its defaults file, then the example of OpenSim.ini
        $defaults = IniReader::load("{$plan->binDir()}/OpenSimDefaults.ini");
        $example = IniReader::load("{$plan->binDir()}/OpenSim.ini.example");
        $exampleText = (string) @file_get_contents("{$plan->binDir()}/OpenSimDefaults.ini") . "\n" . (string) @file_get_contents("{$plan->binDir()}/OpenSim.ini.example");
        $default = array_replace_recursive($defaults, $example);
        $customizations = RobustImporter::customizations(
            $raw,
            IniReader::parse((new SimConfig())->render($plan)),
            $default,
            dirname($path),
            $exampleText,
            IniReader::expand($raw),
        );
        $report = self::report(
            [
                "Simulator {$plan->simName} ({$plan->slug}) of the grid {$grid->nick}, imported from $path",
                "Core: $core",
                "Database: {$plan->dbName} @ {$plan->dbHost} (user {$plan->dbUser}), kept as it is",
                "HTTP port: {$plan->httpPort}" . ($plan->consoleMode === 'rest' ? ", console {$plan->consolePort}" : ''),
                'Regions: ' . count($regions) . ' file(s) copied with their UUID' . ($regions === [] ? ' (none found: regionload_regionsdir of [Startup], else Regions/ next to bin/)' : ''),
            ],
            $customizations,
        );
        $this->say($report);
        if (!$apply) {
            $this->say("\nNothing was written: --apply writes the simulator {$plan->slug} in {$plan->gridDir}/sims/.\n");

            return 0;
        }

        try {
            if (!Elevated::needed($profile)) {
                (new NewSim(new QuietUi()))->apply($plan, $profile);
            } else {
                Elevated::run(new QuietUi(), '--apply-sim', ['plan' => $plan->toArray(), 'profile' => $profile], $profile['SystemUser']);
            }
        } catch (SetupFailedException) {
            throw new ImportFailure('the simulator could not be written');
        }
        $copied = $regions === [] || $this->act($profile, 'copy-regions', ['files' => $regions, 'to' => $plan->regionsDir()]);
        $done = $this->act($profile, 'inject-config', [
            'path' => $plan->iniPath(),
            'customizations' => $customizations,
            'report_path' => "{$plan->gridDir}/sims/{$plan->slug}.import-report.txt",
            'report' => $report,
        ]);
        if (!$copied || !$done) {
            throw new ImportFailure('the simulator is written, but its regions or its settings could not be added');
        }
        $this->say("\nWritten: {$plan->iniPath()}\nRegions: {$plan->regionsDir()}\nReport:  {$plan->gridDir}/sims/{$plan->slug}.import-report.txt\nCompare it with the original, then: opensim enable {$plan->slug}\n");

        return 0;
    }

    /**
     * Do something to the install as the user who owns it.
     *
     * @param array<string,string> $profile
     * @param array<string,mixed> $args
     */
    private function act(array $profile, string $op, array $args): bool
    {
        if (!Elevated::needed($profile)) {
            return (new Actions(new QuietUi()))->perform($op, $args, $profile);
        }
        try {
            Elevated::run(new QuietUi(), '--apply-action', ['op' => $op, 'args' => $args, 'profile' => $profile], $profile['SystemUser']);
        } catch (SetupFailedException) {
            return false;
        }

        return true;
    }

    /**
     * The text that says what was found.
     *
     * @param list<string> $lines what the plan says
     * @param list<array{section:string,key:string,value:string,unknown:bool}> $customizations
     */
    private static function report(array $lines, array $customizations): string
    {
        $text = implode("\n", $lines) . "\n\n" . count($customizations) . " setting(s) of the original kept in the new config:\n";
        foreach ($customizations as $c) {
            $text .= sprintf("  [%s] %s = %s%s\n", $c['section'], $c['key'], $c['value'], $c['unknown'] ? '   (not in the example of this core: it may be ignored)' : '');
        }
        $unknown = count(array_filter($customizations, static fn(array $c): bool => $c['unknown']));

        return $text . ($unknown > 0 ? "\n$unknown of them are not in the example of this core: look at them first.\n" : '');
    }

    private function say(string $text): void
    {
        $this->out->write($text, false, OutputInterface::OUTPUT_RAW);
    }
}
