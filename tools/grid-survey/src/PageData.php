<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * Updates the numbers of the w4os.org page data (grids.json) from a survey result. Only the grid counts of the
 * systems and the survey month are touched, by editing the text in place: the layout, the dates of the last
 * update of each interface and their sources (hand-kept) are left as they are, as is the list of grids.
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

        // the grids running w4os, for the list of the page (hand-kept: grids may ask not to be on it)
        $listed = array_map(fn (array $g) => self::host($g['url']), $data['grids']);
        foreach ($report['grids'] as $grid) {
            if ($grid['system'] === 'wordpress_w4os' && !in_array(self::host($grid['url']), $listed, true)) {
                $notes[] = "w4os detected on {$grid['name']} ({$grid['url']}), not in the list of the page";
            }
        }
        $found = array_map(fn (array $g) => self::host($g['url']), array_filter($report['grids'], fn ($g) => $g['system'] === 'wordpress_w4os'));
        foreach ($data['grids'] as $grid) {
            if (!in_array(self::host($grid['url']), $found, true)) {
                $notes[] = "{$grid['name']} is in the list of the page, w4os not detected in this survey";
            }
        }

        json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return ['json' => $json, 'changes' => $changes, 'notes' => $notes];
    }

    /** The site of a grid by its host alone: the address of the page and the one of the list may differ by their path. */
    private static function host(string $url): string
    {
        return preg_replace('~^www\.~', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }
}
