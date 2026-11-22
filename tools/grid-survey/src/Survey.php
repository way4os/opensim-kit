<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * One survey: crawl (or read the saved pages of) every grid of the list, classify, and total.
 */
final class Survey
{
    /**
     * @param list<array<string, mixed>> $excluded lists/excluded.json
     * @param callable(string): void|null $log progress lines
     */
    public function __construct(
        private readonly Crawler $crawler,
        private readonly Classifier $classifier,
        private readonly ?Store $store = null,
        private readonly array $excluded = [],
        private $log = null,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $grids the list joined to the sizes
     * @param bool $offline classify the saved pages only, crawl nothing
     * @return list<array<string, mixed>> the grids with their classification
     */
    public function run(array $grids, bool $offline = false): array
    {
        $crawls = [];
        foreach ($grids as $i => $grid) {
            if ($this->exclusion($grid) !== null) {
                continue;
            }
            $crawls[$i] = $offline ? $this->store?->load($grid) : $this->crawl($grid, $i + 1, count($grids));
            if ($crawls[$i] === null) {
                $crawls[$i] = ['status' => 'error', 'note' => 'no saved pages', 'pages' => []];
            }
        }

        // second pass: the sites that did not answer are tried once more
        if (!$offline) {
            foreach ($crawls as $i => $crawl) {
                if ($crawl['status'] === 'unreachable') {
                    $this->say('retry ' . $grids[$i]['name']);
                    $crawls[$i] = $this->crawl($grids[$i], $i + 1, count($grids), false);
                }
            }
        }

        $out = [];
        foreach ($grids as $i => $grid) {
            if (($why = $this->exclusion($grid)) !== null) {
                $out[] = $grid + ['system' => null, 'outcome' => 'excluded', 'confidence' => 'n/a', 'signals' => [], 'flags' => [], 'note' => $why];
                continue;
            }
            $class = $this->classifier->classifyGrid($crawls[$i]);
            // grid-level facts only: the signals and the addresses the grid announces, never the pages
            unset($class['detected'], $class['fallback']);
            $out[] = $grid + $class + ['grid_info' => $crawls[$i]['grid']['info'] ?? [], 'note' => $crawls[$i]['note']];
        }

        return $out;
    }

    /** The reason a grid is left out of the survey, or null. */
    public function exclusion(array $grid): ?string
    {
        $host = (string) parse_url($grid['url'], PHP_URL_HOST);
        foreach ($this->excluded as $rule) {
            if (strtolower($host) === $rule['host'] || str_ends_with(strtolower($host), '.' . $rule['host'])) {
                return $rule['reason'];
            }
        }

        return null;
    }

    /** @return array{status: string, note: string, pages: list<array<string, mixed>>} */
    private function crawl(array $grid, int $n, int $count, bool $announce = true): array
    {
        if ($announce) {
            $this->say("[$n/$count] {$grid['name']} {$grid['url']}");
        }
        $crawl = $this->crawler->crawl($grid);
        // the pages are kept only when asked (--keep-pages), to classify again without crawling
        $this->store?->save($grid, $crawl);

        return $crawl;
    }

    private function say(string $line): void
    {
        if ($this->log !== null) {
            ($this->log)($line);
        }
    }
}
