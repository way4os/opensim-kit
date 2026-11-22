<?php

declare(strict_types=1);

namespace GridSurvey;

/** The command line: `run`, `update-page` and `signatures`. */
final class Cli
{
    private const HELP = <<<'TXT'
    grid-survey: the web systems of the OpenSim grids listed by Hypergrid Business

    Usage:
      grid-survey run [--list SOURCE] [options]          read the sites and the grids, classify, write the result (JSON)
      grid-survey update-page --result FILE --grids-json FILE [--write]
                                                          the numbers of the w4os.org page, as a diff
      grid-survey signatures [--update [SHA]]            the fingerprints in use, or fetch a new commit of them
      grid-survey --help | <command> --help

    Public data only. Only what a site or a grid serves to any visitor and its robots.txt allows: plain GET requests,
    no login, no form, no account, no cookie kept, no guessed address, nothing sent. Sites that forbid crawling are left
    alone and counted as such. Only grid-level facts are kept (name, address, system found, counts), never personal
    data and no copy of the pages. The user agent says what the tool is and where to reach its author.
    TXT;

    private const HELP_RUN = <<<'TXT'
    grid-survey run [options]

    For each grid: the grid info it publishes at its login address (/get_grid_info, as every viewer reads it), the root
    of that address and the pages it announces (register, welcome, password, about), then its website (home page, first sign-up link). The two
    are combined; where they differ the result says "review". Writes a dated result (classification of each grid and
    totals) as JSON. Public data only: see grid-survey --help.

      --list SOURCE     the "Active OpenSim Grids" page of Hypergrid Business: its URL (default, the fixed address)
                        or a saved file. Its "Last Update" date is read from the page; with a file that has no date
                        pass --list-date.
      --list-date DATE  the date of the list (YYYY-MM-DD) when the page does not carry it
      --stats SOURCE    the monthly "<Month> <year> OpenSim grid stats" page (active users): URL or file. With the
                        list read from its URL, the newest one is found from the Statistics page of Hypergrid
                        Business each time. Without stats there are no shares by active users.
      --no-stats        do not look for the stats page
      --no-cross-check  do not compare the totals with the monthly article of Hypergrid Business
      --month YYYY-MM   the month of the survey (default: the month of the list). Names the result.
      --out FILE        the result (default: docs/market/grid-web-systems-<month>.json in the kit)
      --keep-pages DIR  keep the pages fetched, to classify again with --offline (not kept by default: they may hold
                        personal data, and are not to be published)
      --offline         classify the kept pages (--keep-pages DIR), fetch nothing
      --probe-w4os      on WordPress sites, also read /wp-content/plugins/w4os*/readme.txt, a public file at an
                        address that is guessed, not published: off by default
      --only A,B        only the grids whose name or address contains one of these words (a dry run)
      --limit N         only the first N grids of the list
      --cache DIR       where the fingerprint database is kept (default: tools/grid-survey/cache)
      --quiet           no progress lines

    Lists kept by hand (tools/grid-survey/lists): excluded.json (grids left out, with reason, source and date) and
    included.json (grids that ask to be in the survey and are not in the Hypergrid Business list). Both are shown
    in the summary and in the result.

    Rules kept: 15 s timeout, 0.5-1 s pause between two requests to a site, one request at a time per site,
    robots.txt first, redirects followed, unreachable sites retried once.
    TXT;

    private const HELP_UPDATE = <<<'TXT'
    grid-survey update-page --result FILE --grids-json FILE [--write]

    Compares the counts of a survey result with the page data of w4os.org (pages/03.features/01.grids/grids.json)
    and prints the changes as a diff. Nothing is written unless --write is given. Only the grid counts and the
    month of the list (`surveyed`) change: the last-update dates of the interfaces, their sources, the order and the
    list of grids stay as they are. The text of the page names the month in each language: change it by hand.
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
        $source = is_string($o['list'] ?? null) ? $o['list'] : Hb::LIST_URL;
        $live = preg_match('~^https?://~i', $source) === 1;
        $log = isset($o['quiet']) ? null : fn (string $line) => fwrite(STDERR, $line . "\n");

        $list = ListReader::activeGrids($this->read($source));
        if ($list['updated'] === null && is_string($o['list-date'] ?? null) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $o['list-date'])) {
            $list['updated'] = $o['list-date'];
            $list['month'] = substr($o['list-date'], 0, 7);
        }
        $month = is_string($o['month'] ?? null) ? $o['month'] : ($list['month'] ?? date('Y-m'));
        if (!preg_match('~^\d{4}-\d{2}$~', $month)) {
            return $this->fail('--month is YYYY-MM');
        }

        // the grids of the list, and the ones that asked to be in the survey
        $included = Lists::included($this->tool . '/lists/included.json');
        $excluded = Lists::excluded($this->tool . '/lists/excluded.json');
        $grids = Lists::merge($list['grids'], $included);

        $statsSource = is_string($o['stats'] ?? null) ? $o['stats'] : null;
        if ($statsSource === null && $live && !isset($o['no-stats'])) {
            $found = Hb::latestStats(Http::plain(Hb::STATS_INDEX) ?? '', $month);
            $statsSource = $found['url'] ?? null;
            $log && $log($found ? "stats page: {$found['url']}" : 'no stats page found, no active users');
        }
        $stats = $statsSource ? ListReader::stats($this->read($statsSource)) : null;
        $grids = ListReader::join($grids, $stats['grids'] ?? []);

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
        $keep = is_string($o['keep-pages'] ?? null) ? new Store($o['keep-pages']) : null;
        if (isset($o['offline']) && $keep === null) {
            return $this->fail('--offline needs --keep-pages DIR, the pages of an earlier run');
        }
        $survey = new Survey(
            new Crawler(new Http(), isset($o['probe-w4os'])),
            new Classifier($matcher, $classes, $signatures->technologies()),
            $keep,
            $excluded,
            $log,
        );
        $classified = $survey->run($grids, isset($o['offline']));

        $totals = Report::totals($classified, $classes['systems']);
        $crossCheck = null;
        if ($stats && $live && !isset($o['no-cross-check']) && !isset($o['only']) && !isset($o['limit'])) {
            $crossCheck = $this->crossCheck($statsSource, $stats);
        }
        $report = [
            'tool' => 'grid-survey',
            'source' => [
                'month' => $month,
                'list' => [
                    'from' => $source,
                    'updated' => $list['updated'],
                    'month' => $list['month'],
                    'announced_total' => $list['total'],
                    'rows' => count($list['grids']),
                ],
                'stats' => $stats ? ['from' => $statsSource, 'month' => $stats['month'], 'rows' => count($stats['grids'])] : null,
                'cross_check' => $crossCheck,
                'fingerprints' => 'enthec/webappanalyzer@' . $signatures->pinned() . ' + signatures/own.json',
                'user_agent' => Http::USER_AGENT,
                'probe_w4os' => isset($o['probe-w4os']),
                'partial' => isset($o['only']) || isset($o['limit']),
            ],
            'excluded' => array_values(array_map(
                fn (array $g) => ['name' => $g['name'], 'url' => $g['url'], 'reason' => $g['note']],
                array_filter($classified, fn (array $g) => $g['outcome'] === 'excluded'),
            )),
            'requested' => array_values(array_map(
                fn (array $g) => ['name' => $g['name'], 'url' => $g['url'], 'in_list' => in_array(ListReader::key($g['url']), array_map(fn ($l) => ListReader::key($l['url']), $list['grids']), true)],
                array_filter($classified, fn (array $g) => $g['requested'] ?? false),
            )),
            'review' => array_values(array_map(
                fn (array $g) => ['name' => $g['name'], 'url' => $g['url'], 'why' => $g['review'] ?? implode(', ', $g['flags'])],
                array_filter($classified, fn (array $g) => ($g['review'] ?? null) !== null || ($g['flags'] ?? []) !== []),
            )),
            'totals' => $totals,
            'grids' => $classified,
        ];
        $out = is_string($o['out'] ?? null) ? $o['out'] : $this->root . "/docs/market/grid-web-systems-$month.json";
        Report::write($out, $report);
        $this->summary($report, $out);

        return 0;
    }

    /**
     * Compares the totals of the stats page with the monthly article of Hypergrid Business. Never fails the run.
     *
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function crossCheck(string $statsUrl, array $stats): array
    {
        $mine = [
            'actives' => array_sum(array_column($stats['grids'], 'actives')),
            'regions' => array_sum(array_column($stats['grids'], 'regions')),
            'users' => array_sum(array_column($stats['grids'], 'users')),
        ];
        $date = Hb::pageDate($statsUrl);
        $article = $date ? Hb::article($date) : null;
        if ($article === null) {
            return ['status' => 'skipped', 'note' => 'the monthly article was not found', 'stats_page_totals' => $mine];
        }

        return [
            'status' => $article['actives'] === $mine['actives'] ? 'match' : 'differs',
            'article' => $article['url'],
            'article_totals' => ['actives' => $article['actives'], 'regions' => $article['regions'], 'users' => $article['users']],
            'stats_page_totals' => $mine,
        ];
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
        $line(sprintf(
            '%d grids listed (list of %s), %d with a system. Result: %s',
            $t['listed'],
            $report['source']['list']['updated'] ?? 'unknown date',
            $t['reachable'],
            $out,
        ));
        $line('No system: ' . json_encode($t['outcomes']));
        foreach ($t['groups'] as $group => $systems) {
            $line("[$group]");
            foreach ($systems as $id => $row) {
                $line(sprintf('  %-16s %4d grids  %5.1f%%  %6d actives', $id, $row['grids'], $row['share_grids'], $row['actives']));
            }
        }
        $line(sprintf(
            'decided by: site %d, grid info %d, both %d; grid info pages %s; w4os only from grid pages: %d',
            $t['decided_by']['site'],
            $t['decided_by']['grid_info'],
            $t['decided_by']['both'],
            json_encode($t['grid_info_pages']),
            $t['w4os_only_from_grid_pages'],
        ));
        $line('excluded: ' . count($report['excluded']) . ', see lists/excluded.json');
        foreach ($report['excluded'] as $e) {
            $line("  {$e['name']}: {$e['reason']}");
        }
        $line('added by request: ' . count($report['requested']) . ', see lists/included.json');
        if ($report['review']) {
            $line('to review by hand: ' . count($report['review']));
            foreach ($report['review'] as $r) {
                $line("  {$r['name']}: {$r['why']}");
            }
        }
        if ($cc = $report['source']['cross_check']) {
            $line(sprintf(
                'cross-check with the monthly article: %s (actives: stats page %s, article %s)',
                $cc['status'],
                number_format($cc['stats_page_totals']['actives']),
                isset($cc['article_totals']['actives']) ? number_format($cc['article_totals']['actives']) : 'n/a',
            ));
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
