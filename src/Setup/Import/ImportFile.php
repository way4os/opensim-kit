<?php

declare(strict_types=1);

namespace OpenSimKit\Setup\Import;

use OpenSim\Exceptions\SetupFailedException;
use OpenSim\Provisioning\SetupFile;
use OpenSimKit\Setup\SetupRunner;
use OpenSimKit\Ui\PromptsUi;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * `opensim import`: bring what a file holds into the install, a grid, its simulators, its accounts.
 *
 *   opensim import [GRID] FILE [--grid] [--simulator] [--users] [--apply] [--core DIR] [--name NAME]
 *                              [--keep-going] [--result FILE] [--format csv|json|yaml]
 *
 * FILE is what it is: the config of a grid that was not made by the kit (a Robust ini), of a simulator (an OpenSim ini), a setup
 * file of the kit (JSON or YAML: the grid, its simulators and regions, the accounts), or a list of accounts (CSV, JSON or YAML).
 * Everything it holds is imported, or only what --grid, --simulator and --users ask for.
 *
 * GRID, first, is the nick of the grid to make, or the grid the simulators and accounts join. It can be left out when FILE tells
 * it (a setup file does) or when the machine has only one grid.
 *
 * Nothing is written without --apply: what would be made is told.
 */
final class ImportFile
{
    public const USAGE = "usage: opensim import [GRID] FILE [--grid] [--simulator] [--users] [--apply] [--core DIR] [--name NAME]\n";

    /**
     * @param list<string> $args the words and the options as typed
     */
    public function run(array $args, OutputInterface $out, OutputInterface $err): int
    {
        try {
            return $this->import($args, $out, $err);
        } catch (ImportFailure $failure) {
            $err->write('import: ' . $failure->getMessage() . "\n", false, OutputInterface::OUTPUT_RAW);

            return $failure->status;
        }
    }

    /** @param list<string> $args */
    private function import(array $args, OutputInterface $out, OutputInterface $err): int
    {
        if ($args === [] || in_array($args[0], ['-h', '--help'], true)) {
            ($args === [] ? $err : $out)->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return $args === [] ? 2 : 0;
        }

        $options = ['core' => '', 'name' => '', 'result' => '', 'format' => ''];
        $only = ['grid' => false, 'simulator' => false, 'users' => false];
        $apply = false;
        $keepGoing = false;
        $words = [];
        for ($i = 0; $i < count($args); $i++) {
            if (preg_match('/^--(core|name|result|format)(?:=(.*))?$/', $args[$i], $m)) {
                $options[$m[1]] = $m[2] ?? ($args[++$i] ?? throw new ImportFailure("--{$m[1]} needs a value", 2));
            } elseif (preg_match('/^--(grid|simulator|users)$/', $args[$i], $m)) {
                $only[$m[1]] = true;
            } elseif ($args[$i] === '--apply') {
                $apply = true;
            } elseif ($args[$i] === '--keep-going') {
                $keepGoing = true;
            } else {
                $words[] = $args[$i];
            }
        }
        if ($options['format'] !== '' && !in_array($options['format'], ['csv', 'json', 'yaml'], true)) {
            throw new ImportFailure('the format is csv, json or yaml', 2);
        }

        // [GRID] FILE
        $grid = '';
        if (count($words) === 2) {
            [$grid, $file] = $words;
        } elseif (count($words) === 1 && is_readable($words[0])) {
            $file = $words[0];
        } else {
            $err->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        $path = (string) realpath($file);
        $kind = $this->kind($path, $options['format']);
        $all = !in_array(true, $only, true);

        // What the file holds, and what was asked of it
        $holds = match ($kind) {
            'robust' => ['grid'],
            'sim' => ['simulator'],
            'accounts' => ['users'],
            default => ['grid', 'simulator', 'users'],
        };
        $wanted = array_values(array_filter($holds, static fn(string $part): bool => $all || $only[$part]));
        if ($wanted === []) {
            throw new ImportFailure(
                "nothing to import: $file holds " .
                    ($kind === 'robust' ? 'the config of a grid' : ($kind === 'sim' ? 'the config of a simulator' : 'a list of accounts')) .
                    ', not what ' . implode(' and ', array_keys(array_filter($only))) . ' asks for',
                2,
            );
        }

        $extra = $apply ? ['--apply'] : [];
        if ($kind === 'robust') {
            return (new ImportSettings())->run(
                array_merge($grid !== '' ? [$grid] : [], ['robust', $path], $options['core'] !== '' ? ['--core', $options['core']] : [], $extra),
                $out,
                $err,
            );
        }
        if ($kind === 'sim') {
            return (new ImportSettings())->run(
                array_merge(
                    $grid !== '' ? [$grid] : [],
                    ['sim', $path],
                    $options['core'] !== '' ? ['--core', $options['core']] : [],
                    $options['name'] !== '' ? ['--name', $options['name']] : [],
                    $extra,
                ),
                $out,
                $err,
            );
        }
        if ($kind === 'accounts') {
            return (new ImportUsers())->run(
                array_merge(
                    $grid !== '' ? [$grid] : [],
                    ['import', $path],
                    $options['result'] !== '' ? ['--result', $options['result']] : [],
                    $options['format'] !== '' ? ['--format', $options['format']] : [],
                    $keepGoing ? ['--keep-going'] : [],
                    $extra,
                ),
                $out,
                $err,
            );
        }

        // A setup file of the kit: its grid, its simulators, its accounts
        try {
            $data = SetupFile::load($path);
        } catch (\InvalidArgumentException $e) {
            throw new ImportFailure($e->getMessage(), 2);
        }
        if (!in_array('grid', $wanted, true)) {
            // The grid is one that is made already: the one named, or the one the file names
            $nick = $grid !== '' ? $grid : (string) ($data['grid']['nick'] ?? '');
            $data['grid'] = $nick !== '' ? ['nick' => $nick] : [];
            if ($data['grid'] === []) {
                unset($data['grid']);
            }
        } elseif ($grid !== '') {
            $data['grid']['nick'] = $grid;
        }
        if (!in_array('simulator', $wanted, true)) {
            unset($data['simulators']);
        }
        if (!in_array('users', $wanted, true)) {
            unset($data['users']);
        }
        if (!in_array('grid', $wanted, true) && !in_array('simulator', $wanted, true)) {
            unset($data['owner']);
        }

        $problems = SetupFile::problems($data);
        if ($problems !== []) {
            throw new ImportFailure(implode("\n", $problems), 2);
        }

        if (!$apply) {
            $made = [];
            if (trim((string) ($data['grid']['name'] ?? '')) !== '') {
                $made[] = "the grid {$data['grid']['name']}";
            }
            $regions = array_sum(array_map(static fn(array $s): int => max(1, count($s['regions'] ?? [])), $data['simulators'] ?? []));
            if (!empty($data['simulators'])) {
                $made[] = count($data['simulators']) . ' simulator(s) with ' . $regions . ' region(s)';
            }
            if (!empty($data['owner']['name'])) {
                $made[] = "the account {$data['owner']['name']}, owner of the estate";
            }
            if (!empty($data['users'])) {
                $made[] = count($data['users']) . ' account(s)';
            }
            $out->write('It would make: ' . implode(', ', $made) . ".\nNothing was made: --apply makes it.\n", false, OutputInterface::OUTPUT_RAW);

            return 0;
        }

        try {
            $made = (new SetupRunner(new PromptsUi()))->run($data, [], $options['result']);
        } catch (SetupFailedException) {
            return 1;
        }

        return $made === null ? 1 : 0;
    }

    /** What kind of file it is: robust (a Robust ini), sim (an OpenSim ini), setup (a setup of the kit) or accounts. */
    private function kind(string $path, string $format): string
    {
        $text = (string) file_get_contents($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($format === 'csv' || ($format === '' && $ext === 'csv')) {
            return 'accounts';
        }
        $isYaml = in_array($format ?: $ext, ['yaml', 'yml'], true);
        $json = in_array($format ?: $ext, ['json'], true) || preg_match('/^\s*[\[{]/', $text) === 1 ? json_decode($text, true) : null;
        // An ini starts with a [Section], which a list of JSON does too: it is JSON when it reads as JSON
        if ($isYaml || $format === 'json' || $ext === 'json' || is_array($json)) {
            $data = $isYaml ? $this->yaml($text) : $json;
            if (!is_array($data)) {
                throw new ImportFailure("$path is not a list of settings or of accounts", 2);
            }

            return array_is_list($data) || (isset($data['accounts']) && count($data) === 1) || (isset($data['users']) && count($data) === 1) ? 'accounts' : 'setup';
        }
        if (preg_match('/^\s*\[(?:ServiceList|GridInfoService|LoginService|Hypergrid|GridService)\]/mi', $text) === 1) {
            return 'robust';
        }
        if (preg_match('/^\s*\[(?:Startup|Estates|Network|Architecture)\]/mi', $text) === 1) {
            return 'sim';
        }
        if (preg_match('/^\s*\[\w+\]\s*$/m', $text) !== 1) {
            return 'accounts'; // a list with no sections: the lines of a spreadsheet
        }

        throw new ImportFailure("$path is not the config of a grid or of a simulator, nor a setup, nor a list of accounts", 2);
    }

    private function yaml(string $text): mixed
    {
        try {
            return Yaml::parse($text);
        } catch (ParseException) {
            return null;
        }
    }
}
