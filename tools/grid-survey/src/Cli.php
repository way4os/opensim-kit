<?php

declare(strict_types=1);

namespace GridSurvey;

/** The command line: `run`, `update-page` and `signatures`. */
final class Cli
{
    private const HELP = <<<'TXT'
    grid-survey: the web systems of the OpenSim grids listed by Hypergrid Business

    Usage:
      grid-survey run --list SOURCE [options]            crawl the sites, classify, write the result (JSON)
      grid-survey update-page --result FILE --grids-json FILE [--write]
                                                          the numbers of the w4os.org page, as a diff
      grid-survey signatures [--update [SHA]]            the fingerprints in use, or fetch a new commit of them
      grid-survey --help | <command> --help

    Read-only: robots.txt is respected, GET only, no login, no form. One request at a time per site, with a pause.
    TXT;

    private const HELP_RUN = <<<'TXT'
    grid-survey run --list SOURCE [options]

    Crawls the sites of the grids of the list, classifies each one and writes a dated result (per-grid
    classification and totals) as JSON.

      --list SOURCE     the "Active OpenSim Grids" page of Hypergrid Business: its URL or a saved file
      --stats SOURCE    the monthly "<Month> <year> OpenSim grid stats" page (active users): URL or file.
                        Without it the shares by active users are empty.
      --month YYYY-MM   the month of the survey (default: this month). Names the result and the pages folder.
      --out FILE        the result (default: docs/market/grid-web-systems-<month>.json in the kit)
      --pages DIR       where the fetched pages are kept, to redo the classification without crawling
                        (default: tools/grid-survey/results/<month>/pages)
      --offline         classify the saved pages, fetch nothing from the sites
      --only A,B        only the grids whose name or address contains one of these words (a dry run)
      --limit N         only the first N grids of the list
      --include-excluded  do not apply the list of excluded grids (signatures/excluded.json)
      --cache DIR       where the fingerprint database is kept (default: tools/grid-survey/cache)
      --quiet           no progress lines

    Rules kept: user agent "grid-web-survey/1.0; read-only market study", 15 s timeout, 0.5-1 s pause between
    two requests to a site, robots.txt first, meta refresh followed, unreachable sites retried once.
    TXT;

    private const HELP_UPDATE = <<<'TXT'
    grid-survey update-page --result FILE --grids-json FILE [--write]

    Compares the counts of a survey result with the page data of w4os.org (pages/03.features/01.grids/grids.json)
    and prints the changes as a diff. Nothing is written unless --write is given. Only the grid counts and the
    survey month change: the last-update dates of the interfaces, their sources, the order and the list of grids
    stay as they are. The text of the page names the survey month in each language: change it by hand.
    TXT;

    private string $root;

    public function __construct(private readonly string $tool)
    {
        $this->root = dirname($tool, 2);
    }

    /** @param list<string> $argv */
    public function main(array $argv): int
    {
        array_shift($argv);
        $command = array_shift($argv);
        if ($command === null || in_array($command, ['-h', '--help', 'help'], true)) {
            echo self::HELP . "\n";

            return 0;
        }
        $options = $this->options($argv);
        if (isset($options['help'])) {
            echo match ($command) {
                'run' => self::HELP_RUN,
                'update-page' => self::HELP_UPDATE,
                default => self::HELP,
            } . "\n";

            return 0;
        }

        try {
            return match ($command) {
                'run' => $this->run($options),
                'update-page' => $this->updatePage($options),
                'signatures' => $this->signatures($options),
                default => $this->fail("Unknown command: $command (try --help)"),
            };
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    /** @param array<string, string|true> $o */
    private function run(array $o): int
    {
        if (!isset($o['list']) || $o['list'] === true) {
            return $this->fail('--list is required (try run --help)');
        }
        $month = is_string($o['month'] ?? null) ? $o['month'] : date('Y-m');
        if (!preg_match('~^\d{4}-\d{2}$~', $month)) {
            return $this->fail('--month is YYYY-MM');
        }
        $log = isset($o['quiet']) ? null : fn (string $line) => fwrite(STDERR, $line . "\n");

        $list = ListReader::activeGrids($this->read((string) $o['list']));
        $stats = isset($o['stats']) && is_string($o['stats']) ? ListReader::stats($this->read($o['stats'])) : null;
        $grids = ListReader::join($list['grids'], $stats['grids'] ?? []);
        if (isset($o['only']) && is_string($o['only'])) {
            $words = array_filter(array_map('trim', explode(',', mb_strtolower($o['only']))));
            $grids = array_values(array_filter($grids, function (array $g) use ($words) {
                foreach ($words as $word) {
                    if (str_contains(mb_strtolower($g['name'] . ' ' . $g['url']), $word)) {
                        return true;
                    }
                }

                return false;
            }));
        }
        if (isset($o['limit']) && is_string($o['limit'])) {
            $grids = array_slice($grids, 0, (int) $o['limit']);
        }
        if ($grids === []) {
            return $this->fail('No grid to survey');
        }

        $signatures = new Signatures($this->cache($o), $this->tool . '/signatures');
        $classes = json_decode((string) file_get_contents($this->tool . '/signatures/classes.json'), true, flags: JSON_THROW_ON_ERROR);
        $signatures->load($classes['scope']);
        $matcher = new Matcher($signatures->technologies());
        $excluded = isset($o['include-excluded'])
            ? []
            : json_decode((string) file_get_contents($this->tool . '/signatures/excluded.json'), true, flags: JSON_THROW_ON_ERROR);
        $store = new Store(is_string($o['pages'] ?? null) ? $o['pages'] : $this->tool . "/results/$month/pages");
        $survey = new Survey(
            new Crawler(new Http()),
            new Classifier($matcher, $classes, $signatures->technologies()),
            $store,
            $excluded,
            $log,
        );
        $classified = $survey->run($grids, isset($o['offline']));

        $report = [
            'tool' => 'grid-survey',
            'source' => [
                'month' => $month,
                'list' => ['from' => $o['list'], 'updated' => $list['updated'], 'announced_total' => $list['total'], 'rows' => count($list['grids'])],
                'stats' => $stats ? ['from' => $o['stats'], 'month' => $stats['month'], 'rows' => count($stats['grids'])] : null,
                'fingerprints' => 'enthec/webappanalyzer@' . $signatures->pinned() . ' + signatures/own.json',
                'user_agent' => Http::USER_AGENT,
                'partial' => isset($o['only']) || isset($o['limit']),
            ],
            'excluded' => array_values(array_map(
                fn (array $g) => ['name' => $g['name'], 'url' => $g['url'], 'reason' => $g['note']],
                array_filter($classified, fn (array $g) => $g['outcome'] === 'excluded'),
            )),
            'totals' => Report::totals($classified, $classes['systems']),
            'grids' => $classified,
        ];
        $out = is_string($o['out'] ?? null) ? $o['out'] : $this->root . "/docs/market/grid-web-systems-$month.json";
        Report::write($out, $report);
        $this->summary($report, $out);

        return 0;
    }

    /** @param array<string, string|true> $o */
    private function updatePage(array $o): int
    {
        foreach (['result', 'grids-json'] as $need) {
            if (!isset($o[$need]) || $o[$need] === true) {
                return $this->fail("--$need is required (try update-page --help)");
            }
        }
        $report = json_decode((string) file_get_contents((string) $o['result']), true, flags: JSON_THROW_ON_ERROR);
        $file = (string) $o['grids-json'];
        $old = (string) file_get_contents($file);
        if ($report['source']['partial'] ?? false) {
            return $this->fail('This result is from a partial run (--only or --limit): its counts are not the whole survey');
        }
        $proposal = PageData::propose($old, $report);

        foreach ($proposal['notes'] as $note) {
            fwrite(STDERR, "note: $note\n");
        }
        if ($proposal['changes'] === []) {
            echo "Nothing to change.\n";

            return 0;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'grids');
        file_put_contents($tmp, $proposal['json']);
        passthru('diff -u ' . escapeshellarg($file) . ' ' . escapeshellarg($tmp));
        unlink($tmp);
        if (isset($o['write'])) {
            file_put_contents($file, $proposal['json']);
            echo "Written: $file\n";
        } else {
            echo "\nNot written. Run again with --write to apply.\n";
        }

        return 0;
    }

    /** @param array<string, string|true> $o */
    private function signatures(array $o): int
    {
        $signatures = new Signatures($this->cache($o), $this->tool . '/signatures');
        if (isset($o['update'])) {
            $sha = is_string($o['update']) ? $o['update'] : $this->latestCommit();
            echo 'Fetched enthec/webappanalyzer@' . $signatures->download($sha) . " (pinned in signatures/webappanalyzer.sha)\n";

            return 0;
        }
        echo 'enthec/webappanalyzer@' . $signatures->pinned() . " + signatures/own.json\n";

        return 0;
    }

    private function latestCommit(): string
    {
        $json = Http::plain('https://api.github.com/repos/enthec/webappanalyzer/commits/main');
        $sha = $json ? (json_decode($json, true)['sha'] ?? null) : null;

        return $sha ?: throw new \RuntimeException('Cannot read the latest commit of enthec/webappanalyzer');
    }

    /** @param array<string, string|true> $o */
    private function cache(array $o): string
    {
        return is_string($o['cache'] ?? null) ? $o['cache'] : $this->tool . '/cache';
    }

    /** A URL or a file. */
    private function read(string $source): string
    {
        if (preg_match('~^https?://~i', $source)) {
            return Http::plain($source) ?? throw new \RuntimeException("Cannot fetch $source");
        }

        return is_file($source) ? (string) file_get_contents($source) : throw new \RuntimeException("No such file: $source");
    }

    /** @param array<string, mixed> $report */
    private function summary(array $report, string $out): void
    {
        $t = $report['totals'];
        $line = fn (string $s) => fwrite(STDERR, $s . "\n");
        $line('');
        $line(sprintf('%d grids listed, %d with a readable website. Result: %s', $t['listed'], $t['reachable'], $out));
        $line('No system: ' . json_encode($t['outcomes']));
        foreach ($t['groups'] as $group => $systems) {
            $line("[$group]");
            foreach ($systems as $id => $row) {
                $line(sprintf('  %-16s %4d grids  %5.1f%%  %6d actives', $id, $row['grids'], $row['share_grids'], $row['actives']));
            }
        }
        foreach ($report['excluded'] as $e) {
            $line("Excluded: {$e['name']}: {$e['reason']}");
        }
        if ($t['unknown_systems']) {
            $line('WARNING: systems missing from the groups: ' . implode(', ', $t['unknown_systems']));
        }
    }

    /** @return array<string, string|true> */
    private function options(array $args): array
    {
        $options = [];
        for ($i = 0; $i < count($args); $i++) {
            if (!str_starts_with($args[$i], '--')) {
                continue;
            }
            $name = substr($args[$i], 2);
            if (str_contains($name, '=')) {
                [$name, $value] = explode('=', $name, 2);
                $options[$name] = $value;
            } elseif (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '--')) {
                $options[$name] = $args[++$i];
            } else {
                $options[$name] = true;
            }
        }

        return $options;
    }

    private function fail(string $message): int
    {
        fwrite(STDERR, "grid-survey: $message\n");

        return 1;
    }
}
