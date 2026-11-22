<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * The two lists kept by hand next to the Hypergrid Business one (lists/):
 * - excluded.json: grids left out of the survey, with the reason, the source of the statement and the date
 *   (a grid that says it is not OpenSim, or that asks to be removed);
 * - included.json: grids that are not in the Hypergrid Business list but ask to be in ours, with how and when they
 *   asked and who recorded it.
 */
final class Lists
{
    /** @return list<array{name: string, host: string, reason: string, source: string, date: string}> */
    public static function excluded(string $file): array
    {
        return self::read($file, ['name', 'host', 'reason', 'source', 'date']);
    }

    /**
     * `w4os: true` says the grid uses w4os without it being visible from outside (helpers only, for instance); it then
     * says where the claim comes from in `w4os_source`.
     *
     * @return list<array{name: string, url: string, asked: string, recorded_by: string, date: string, login_uri?: string, w4os?: bool, w4os_source?: string}>
     */
    public static function included(string $file): array
    {
        $rows = self::read($file, ['name', 'url', 'asked', 'recorded_by', 'date']);
        foreach ($rows as $i => $row) {
            if (($row['w4os'] ?? false) === true && ($row['w4os_source'] ?? '') === '') {
                throw new \RuntimeException(basename($file) . ': entry ' . ($i + 1) . ' has no "w4os_source"');
            }
        }

        return $rows;
    }

    /**
     * Adds the grids that asked to be in the survey. A grid already in the list is counted once (it only gets the
     * flag); the others come after the list, flagged as requested.
     *
     * @param list<array<string, mixed>> $grids
     * @param list<array<string, mixed>> $included
     * @return list<array<string, mixed>>
     */
    public static function merge(array $grids, array $included): array
    {
        foreach ($grids as &$grid) {
            $grid['requested'] = false;
        }
        unset($grid);
        $known = array_flip(array_map(fn (array $g) => ListReader::key($g['url']), $grids));
        foreach ($included as $entry) {
            $key = ListReader::key($entry['url']);
            $claim = ($entry['w4os'] ?? false) === true ? $entry['w4os_source'] : null;
            if (isset($known[$key])) {
                foreach ($grids as &$grid) {
                    if (ListReader::key($grid['url']) === $key) {
                        $grid['requested'] = true;
                        $claim === null || $grid['w4os_claim'] = $claim;
                    }
                }
                unset($grid);
                continue;
            }
            $known[$key] = true;
            $grids[] = [
                'name' => $entry['name'],
                'url' => $entry['url'],
                'login_uri' => $entry['login_uri'] ?? '',
                'features' => '',
                'requested' => true,
            ] + ($claim === null ? [] : ['w4os_claim' => $claim]);
        }

        return $grids;
    }

    /** @return list<array<string, mixed>> */
    private static function read(string $file, array $required): array
    {
        if (!is_file($file)) {
            return [];
        }
        $rows = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        foreach ($rows as $i => $row) {
            foreach ($required as $field) {
                if (!isset($row[$field]) || $row[$field] === '') {
                    throw new \RuntimeException(basename($file) . ': entry ' . ($i + 1) . " has no \"$field\"");
                }
            }
        }

        return $rows;
    }
}
