<?php

declare(strict_types=1);

namespace OpenSimKit\Setup\Import;

use OpenSim\Models\GridInfo;
use OpenSim\Provisioning\AccountImporter;
use OpenSim\Provisioning\AccountList;
use OpenSim\Provisioning\DatabaseService;
use OpenSimKit\Setup\InstallConfig;
use OpenSimKit\Ui\QuietUi;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The accounts of a list, made in the database of a grid (`opensim import`, for a list of accounts).
 *
 *   opensim import [GRID] FILE [--users] [--apply] [--keep-going] [--result FILE] [--format csv|json|yaml]
 *
 * Makes the accounts of a list, CSV (first, last, email, password; a header line is accepted) or JSON (a list of objects with the
 * same keys), directly in the database of the grid, as Robust does when `create user` is typed in its console (see
 * AccountWriter). Nothing is written without --apply: the list is checked and what would be made is told. An account whose name
 * exists is skipped; one without a password gets one, written in the result file (mode 600) and never on the screen.
 */
final class ImportUsers
{
    private const USAGE = "usage: opensim import [GRID] FILE [--users] [--apply] [--keep-going] [--result FILE] [--format csv|json|yaml]\n";

    /**
     * @param list<string> $args [GRID] import FILE and the options, as ImportFile hands them
     */
    public function run(array $args, OutputInterface $out, OutputInterface $err): int
    {
        // GRID import FILE: the grid comes first, as the instance does in the other commands
        $grid = '';
        if (($args[0] ?? '') !== 'import' && !str_starts_with($args[0] ?? '-', '-')) {
            $grid = array_shift($args);
        }
        if (($args[0] ?? '') !== 'import') {
            $err->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        array_shift($args);

        $options = ['grid' => $grid, 'result' => '', 'format' => ''];
        $flags = ['apply' => false, 'keep-going' => false];
        $words = [];
        for ($i = 0; $i < count($args); $i++) {
            if (preg_match('/^--(grid|result|format)(?:=(.*))?$/', $args[$i], $m)) {
                $options[$m[1]] = $m[2] ?? ($args[++$i] ?? throw new ImportFailure("--{$m[1]} needs a value", 2));
            } elseif (preg_match('/^--(apply|keep-going)$/', $args[$i], $m)) {
                $flags[$m[1]] = true;
            } else {
                $words[] = $args[$i];
            }
        }
        if (count($words) !== 1) {
            $err->write(self::USAGE, false, OutputInterface::OUTPUT_RAW);

            return 2;
        }
        if (!is_readable($words[0])) {
            throw new ImportFailure("cannot read {$words[0]}", 2);
        }
        if ($options['format'] === '' && preg_match('/\.ya?ml$/i', $words[0])) {
            $options['format'] = 'yaml';
        }
        if ($options['format'] !== '' && !in_array($options['format'], ['csv', 'json', 'yaml'], true)) {
            throw new ImportFailure('the format is csv, json or yaml', 2);
        }

        $profile = (new InstallConfig())->profile();
        $etc = $profile['EtcRoot'] ?? '';
        $grids = array_map('basename', glob("$etc/grids/*", GLOB_ONLYDIR) ?: []);
        if ($options['grid'] === '' && count($grids) === 1) {
            $options['grid'] = $grids[0];
        }
        $grid = $options['grid'] === '' ? null : GridInfo::load($profile, $options['grid']);
        if ($grid === null || $grid->remote || $grid->dbName === '') {
            throw new ImportFailure(
                $options['grid'] === ''
                    ? 'say which grid: opensim import <grid> FILE ... (' . implode(', ', $grids) . ')'
                    : "no grid {$options['grid']} on this machine, with a database to write to",
                2,
            );
        }

        $list = AccountList::parse((string) file_get_contents($words[0]), $options['format'] ?: null);
        foreach ($list['errors'] as $error) {
            $err->write("import: $error\n", false, OutputInterface::OUTPUT_RAW);
        }
        if ($list['accounts'] === []) {
            throw new ImportFailure('nothing to make' . ($list['errors'] === [] ? '' : ': every line has a problem'), 2);
        }

        $done = AccountImporter::forDatabase(new DatabaseService(new QuietUi()))->run($grid, $list['accounts'], $flags['apply'], $flags['keep-going']);
        if ($done['error'] !== null) {
            throw new ImportFailure($done['error']);
        }

        $counts = [];
        foreach ($done['results'] as $row) {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
            $out->write(sprintf("%-14s %s %s%s\n", $row['status'], $row['first'], $row['last'], $row['detail'] !== '' ? " ({$row['detail']})" : ''), false, OutputInterface::OUTPUT_RAW);
        }
        $out->write("\n" . implode(', ', array_map(static fn(string $s, int $n): string => "$n $s", array_keys($counts), $counts)) . "\n", false, OutputInterface::OUTPUT_RAW);
        if (!$flags['apply']) {
            $out->write("Nothing was written: --apply makes the accounts.\n", false, OutputInterface::OUTPUT_RAW);
        }

        $generated = array_filter($done['results'], static fn(array $row): bool => $row['generated'] && $row['status'] !== AccountImporter::EXISTS);
        $resultFile = $options['result'] !== '' ? $options['result'] : ($flags['apply'] ? 'users-result-' . date('Ymd-His') . '.csv' : '');
        if ($resultFile !== '' && ($flags['apply'] || $generated !== [])) {
            $umask = umask(0o077);
            file_put_contents($resultFile, AccountImporter::resultCsv($done['results']));
            umask($umask);
            chmod($resultFile, 0o600);
            $out->write("Result (with the passwords made here): $resultFile\n", false, OutputInterface::OUTPUT_RAW);
        }

        $failed = ($counts[AccountImporter::FAILED] ?? 0) + ($counts[AccountImporter::NOT_TRIED] ?? 0);

        return $failed > 0 || $list['errors'] !== [] ? 1 : 0;
    }
}
