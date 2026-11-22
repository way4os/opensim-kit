<?php

declare(strict_types=1);

namespace GridSurvey;

/** The result of a survey: the grids and their totals, as the JSON the w4os.org page reads. */
final class Report
{
    /**
     * Totals by system and by group. Shares are over the grids that have a system (a readable website), and
     * over the active users of those grids when the list gives them.
     *
     * @param list<array<string, mixed>> $grids
     * @param array<string, list<string>> $groups group id => system ids, in the order of the chart
     * @return array<string, mixed>
     */
    public static function totals(array $grids, array $groups): array
    {
        $outcomes = [];
        $sources = ['list' => 0, 'requested' => 0];
        $systems = [];
        $reachable = 0;
        $actives = 0;
        foreach ($grids as $grid) {
            if ($grid['system'] === null) {
                $outcomes[$grid['outcome']] = ($outcomes[$grid['outcome']] ?? 0) + 1;
                continue;
            }
            $reachable++;
            $sources[$grid['requested'] ?? false ? 'requested' : 'list'] = ($sources[$grid['requested'] ?? false ? 'requested' : 'list'] ?? 0) + 1;
            $actives += (int) ($grid['actives'] ?? 0);
            $systems[$grid['system']]['grids'] = ($systems[$grid['system']]['grids'] ?? 0) + 1;
            $systems[$grid['system']]['actives'] = ($systems[$grid['system']]['actives'] ?? 0) + (int) ($grid['actives'] ?? 0);
        }

        $rows = [];
        foreach ($groups as $group => $ids) {
            foreach ($ids as $id) {
                $n = $systems[$id]['grids'] ?? 0;
                $a = $systems[$id]['actives'] ?? 0;
                $rows[$group][$id] = [
                    'grids' => $n,
                    'share_grids' => $reachable ? round($n / $reachable * 100, 1) : 0.0,
                    'actives' => $a,
                    'share_actives' => $actives ? round($a / $actives * 100, 1) : 0.0,
                ];
            }
        }
        // a system the rules produced but the groups do not know would silently drop out of the chart
        $unknown = array_diff(array_keys($systems), array_merge(...array_values($groups)));

        return [
            'listed' => count($grids),
            'reachable' => $reachable,
            'actives' => $actives,
            'outcomes' => $outcomes,
            // where the grids with a system come from: the Hypergrid Business list, or lists/included.json
            'sources' => $sources,
            'groups' => $rows,
            'unknown_systems' => array_values($unknown),
        ];
    }

    /** @param array<string, mixed> $report */
    public static function write(string $file, array $report): void
    {
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0775, true)) {
            throw new \RuntimeException('Cannot create ' . dirname($file));
        }
        file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }
}
