<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * Updates the w4os.org page data (grids.json) from a survey result: the grid counts of the systems, the survey month
 * and the list of the grids that use w4os, by editing the text in place. The layout, the dates of the last update of
 * each interface and their sources, the years a development ended (hand-kept) are left as they are.
 */
final class PageData
{
    /**
     * @param array<string, mixed> $report a survey result
     * @return array{json: string, changes: list<string>, notes: list<string>}
     */
    public static function propose(string $json, array $report): array
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $counts = [];
        foreach ($report['totals']['groups'] as $systems) {
            foreach ($systems as $id => $row) {
                $counts[$id] = $row['grids'];
            }
        }

        $changes = [];
        $notes = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['systems'] as $system) {
                $id = $system['id'];
                if (!isset($counts[$id])) {
                    $notes[] = "$id: not in the survey result, left as it is";
                    continue;
                }
                if ($counts[$id] !== $system['grids']) {
                    $pattern = '~("id"\s*:\s*"' . preg_quote($id, '~') . '"\s*,\s*"grids"\s*:\s*)' . $system['grids'] . '\b~';
                    $json = preg_replace($pattern, '${1}' . $counts[$id], $json, 1, $n);
                    if ($n !== 1) {
                        throw new \RuntimeException("Cannot find the line of $id in the page data");
                    }
                    $changes[] = "$id: {$system['grids']} -> {$counts[$id]}";
                }
            }
        }

        // the month of the survey, a key of its own
        $month = $report['source']['month'];
        if (($data['surveyed'] ?? null) !== $month) {
            if (isset($data['surveyed'])) {
                $json = preg_replace('~("surveyed"\s*:\s*)"[^"]*"~', '${1}"' . $month . '"', $json, 1);
            } else {
                $json = preg_replace('~^\{\s*~', "{\n  \"surveyed\": \"$month\",\n  ", $json, 1);
            }
            $changes[] = 'surveyed: ' . ($data['surveyed'] ?? 'none') . " -> $month";
        }

        // the names of the grids that use w4os, written from the result
        [$json, $listChanges, $listNotes] = self::names($json, $data['grids'], $report['grids']);
        $changes = array_merge($changes, $listChanges);
        $notes = array_merge($notes, $listNotes);

        json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return ['json' => $json, 'changes' => $changes, 'notes' => $notes];
    }

    /**
     * The name a grid gives itself, cleaned: URL-decoded (+ and %xx), trimmed, spaces collapsed, nothing else.
     * Empty when it has no letter (the name of the list is then used).
     */
    private static function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('~\s+~u', ' ', urldecode($name)));

        return preg_match('~\p{L}~u', $name) === 1 ? $name : '';
    }

    /** The site of a grid by its host alone: the address of the page and the one of the list may differ by their path. */
    private static function host(string $url): string
    {
        return preg_replace('~^www\.~', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    /**
     * The list of the grids that use w4os (name and address), from every grid the survey put in the w4os row, in
     * alphabetical order. The name is the one the grid gives itself when it has one (the list's name is shown when it
     * differs); a grid already on the page keeps its address when the site is the same; a grid whose site gives an error
     * but whose announced pages carry w4os takes the address of that page.
     *
     * @param list<array{name: string, url: string}> $current
     * @param list<array<string, mixed>> $grids
     * @return array{0: string, 1: list<string>, 2: list<string>} the new text, the changes, the notes
     */
    private static function names(string $json, array $current, array $grids): array
    {
        $byHost = [];
        foreach ($current as $entry) {
            $byHost[self::host($entry['url'])] = $entry;
        }

        $notes = [];
        $list = [];
        foreach ($grids as $grid) {
            if ($grid['system'] !== 'wordpress_w4os' || $grid['outcome'] === 'excluded') {
                continue;
            }
            $own = self::cleanName((string) ($grid['grid_info']['gridname'] ?? ''));
            $written = $byHost[self::host($grid['url'])] ?? null;
            if ($written !== null) {
                // a grid already on the page keeps its name; the grid's own is only shown
                $name = $written['name'];
                if ($own !== '' && $own !== $name) {
                    $notes[] = "name: the page has '$name', the grid says '$own' (the page's is kept)";
                }
            } else {
                $name = $own !== '' ? $own : $grid['name'];
                if ($own !== '' && $own !== $grid['name']) {
                    $notes[] = "name: the list says '{$grid['name']}', the grid says '$own' (the grid's is used)";
                }
            }

            $url = $grid['url'];
            $site = $grid['by']['site'] ?? 'wordpress_w4os';
            $page = $grid['decisive']['page'] ?? null;
            $announced = $page !== null ? ($grid['grid_info'][$page] ?? '') : '';
            if (!in_array($site, ['wordpress_w4os', 'wordpress', 'custom', 'custom_site', 'other_cms', 'other_cms_ui', 'builders', 'joomla'], true) && $announced !== '') {
                $parts = parse_url($announced);
                $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
            }
            // a grid already on the page keeps the address written there when the site is the same
            $url = isset($byHost[self::host($url)]) ? $byHost[self::host($url)]['url'] : $url;
            $list[self::host($url)] = ['name' => $name, 'url' => $url];
        }
        usort($list, fn (array $a, array $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

        $changes = [];
        $before = array_column($current, 'name', 'url');
        $now = array_column($list, 'name', 'url');
        foreach ($list as $entry) {
            $old = $byHost[self::host($entry['url'])] ?? null;
            if ($old === null) {
                $changes[] = 'names: + ' . $entry['name'];
            } elseif ($old['name'] !== $entry['name']) {
                $changes[] = "names: {$old['name']} -> {$entry['name']}";
            }
        }
        $kept = array_map(fn (array $e) => self::host($e['url']), $list);
        foreach ($current as $entry) {
            if (!in_array(self::host($entry['url']), $kept, true)) {
                $changes[] = 'names: - ' . $entry['name'];
            }
        }
        if ($before === $now) {
            return [$json, $changes, $notes];
        }

        $lines = array_map(
            fn (array $e) => '    {"name": ' . json_encode($e['name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . ', "url": ' . json_encode($e['url'], JSON_UNESCAPED_SLASHES) . '}',
            $list,
        );
        $new = preg_replace_callback(
            '~("grids"\s*:\s*\[)(.*?)(\n\s*\])~s',
            fn (array $m) => $m[1] . "\n" . implode(",\n", $lines) . $m[3],
            $json,
            1,
            $count,
        );
        if ($count !== 1 || $new === null) {
            throw new \RuntimeException('Cannot find the list of the grids in the page data');
        }

        return [$new, $changes, $notes];
    }
}
