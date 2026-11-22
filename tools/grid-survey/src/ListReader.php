<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * Reads the lists Hypergrid Business publishes: the "Active OpenSim Grids" page (grid, site, login URI)
 * and the monthly "<Month> <year> OpenSim grid stats" page (regions, users, active users).
 * Both are plain HTML tables, so one reader serves both.
 */
final class ListReader
{
    /**
     * Grids of the active-grids page.
     *
     * @return array{updated: ?string, month: ?string, total: ?int, grids: list<array{name: string, url: string, login_uri: string, features: string}>}
     */
    public static function activeGrids(string $html): array
    {
        $grids = [];
        foreach (self::rows($html) as $row) {
            if (count($row['cells']) < 2 || $row['url'] === null) {
                continue;
            }
            $grids[] = [
                'name' => $row['cells'][0],
                'url' => $row['url'],
                'login_uri' => $row['cells'][1],
                'features' => $row['cells'][2] ?? '',
            ];
        }

        // "Last Update: 11/16/2026 • Total grids: 369" (US date)
        $updated = null;
        $total = null;
        // the label and the date are separated by tags: look through them, on the raw page
        $gap = '(?:\s|&nbsp;|<[^>]*>)*';
        if (preg_match("~Last Update:$gap(\\d{1,2})/(\\d{1,2})/(\\d{4})~i", $html, $m)) {
            $updated = sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
        }
        if (preg_match("~Total grids:$gap(\\d+)~i", $html, $m)) {
            $total = (int) $m[1];
        }

        return ['updated' => $updated, 'month' => $updated ? substr($updated, 0, 7) : null, 'total' => $total, 'grids' => $grids];
    }

    /**
     * Sizes of the monthly stats page, by grid.
     *
     * @return array{month: ?string, grids: list<array{name: string, url: string, regions: ?int, users: ?int, actives: ?int}>}
     */
    public static function stats(string $html): array
    {
        $grids = [];
        foreach (self::rows($html) as $row) {
            // Grid, Regions, Regions Change, Total Users, Users Change, Active within past month, Actives Change
            if (count($row['cells']) < 6 || $row['url'] === null) {
                continue;
            }
            $grids[] = [
                'name' => $row['cells'][0],
                'url' => $row['url'],
                'regions' => self::int($row['cells'][1]),
                'users' => self::int($row['cells'][3]),
                'actives' => self::int($row['cells'][5]),
            ];
        }

        // "October 2026 OpenSim grid stats" in the title
        $month = null;
        if (preg_match('~<title>\s*([A-Za-z]+)\s+(\d{4})\s+OpenSim~i', $html, $m)) {
            $time = strtotime("1 {$m[1]} {$m[2]}");
            $month = $time ? date('Y-m', $time) : null;
        }

        return ['month' => $month, 'grids' => $grids];
    }

    /**
     * Joins the sizes to the grids, by site address, then by name. A grid without figures keeps null.
     *
     * @param list<array<string, mixed>> $grids
     * @param list<array<string, mixed>> $stats
     * @return list<array<string, mixed>>
     */
    public static function join(array $grids, array $stats): array
    {
        $byUrl = [];
        $byName = [];
        foreach ($stats as $row) {
            $byUrl[self::key($row['url'])] = $row;
            $byName[mb_strtolower($row['name'])] = $row;
        }
        foreach ($grids as &$grid) {
            $row = $byUrl[self::key($grid['url'])] ?? $byName[mb_strtolower($grid['name'])] ?? null;
            $grid['regions'] = $row['regions'] ?? null;
            $grid['users'] = $row['users'] ?? null;
            $grid['actives'] = $row['actives'] ?? null;
        }

        return $grids;
    }

    /** Host, port and path without scheme, "www." or trailing slash, to match the same site written twice. */
    public static function key(string $url): string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? $url);
        $host = preg_replace('~^www\.~', '', $host);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $host . $port . rtrim($parts['path'] ?? '', '/');
    }

    /** @return list<array{url: ?string, cells: list<string>}> */
    private static function rows(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        $rows = [];
        foreach ((new \DOMXPath($dom))->query('//table//tbody/tr') as $tr) {
            $cells = [];
            $url = null;
            foreach ($tr->getElementsByTagName('td') as $i => $td) {
                $cells[] = trim(preg_replace('~\s+~u', ' ', $td->textContent));
                if ($i === 0) {
                    $a = $td->getElementsByTagName('a')->item(0);
                    $url = $a ? trim($a->getAttribute('href')) : null;
                }
            }
            $rows[] = ['url' => $url ?: null, 'cells' => $cells];
        }

        return $rows;
    }

    private static function int(string $value): ?int
    {
        $digits = preg_replace('~[^\d-]~', '', $value);

        return $digits === '' ? null : (int) $digits;
    }
}
