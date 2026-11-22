<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * What Hypergrid Business publishes, found from its public pages each time: the list has a fixed address, the
 * monthly stats page and the monthly article do not.
 */
final class Hb
{
    public const LIST_URL = 'https://www.hypergridbusiness.com/statistics/active-grids/';
    public const STATS_INDEX = 'https://www.hypergridbusiness.com/statistics/';
    private const API = 'https://www.hypergridbusiness.com/wp-json/wp/v2/posts';

    /**
     * The newest "<Month> <year> OpenSim grid stats" page linked from the Statistics index, not later than a month.
     *
     * @return array{url: string, month: string}|null
     */
    public static function latestStats(string $indexHtml, ?string $notAfterMonth = null): ?array
    {
        $found = [];
        if (preg_match_all('~href=["\'](https?://[^"\']*/statistics/([a-z]+)-(\d{4})-opensim-grid-stats/?)["\']~i', $indexHtml, $all, PREG_SET_ORDER)) {
            foreach ($all as $m) {
                $time = strtotime("1 {$m[2]} {$m[3]}");
                if ($time) {
                    $month = date('Y-m', $time);
                    if ($notAfterMonth === null || $month <= $notAfterMonth) {
                        $found[$month] = $m[1];
                    }
                }
            }
        }
        if ($found === []) {
            return null;
        }
        krsort($found);

        return ['url' => reset($found), 'month' => array_key_first($found)];
    }

    /**
     * The totals the monthly article states (active users, regions, registered users), from its text.
     *
     * @return array{actives: ?int, regions: ?int, users: ?int}
     */
    public static function articleTotals(string $html): array
    {
        $text = preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $number = fn (string $pattern) => preg_match($pattern, $text, $m) ? (int) str_replace(',', '', $m[1]) : null;

        return [
            'actives' => $number('~([\d,]{4,}) active users~i'),
            'regions' => $number('~standard regions, to ([\d,]{4,})~i'),
            'users' => $number('~registered users went up by [\d,]+, to ([\d,]{4,})~i'),
        ];
    }

    /**
     * The monthly article published around the stats page, found through the public REST API of the site.
     * Returns null, and the run goes on, when it cannot be found.
     *
     * @return array{url: string, actives: ?int, regions: ?int, users: ?int}|null
     */
    public static function article(string $statsDate): ?array
    {
        $time = strtotime($statsDate);
        if (!$time) {
            return null;
        }
        $query = http_build_query([
            'search' => 'active users',
            'after' => date('Y-m-d\T00:00:00', $time - 4 * 86400),
            'before' => date('Y-m-d\T00:00:00', $time + 4 * 86400),
            'per_page' => 10,
            '_fields' => 'link,content',
        ]);
        $json = Http::plain(self::API . '?' . $query);
        foreach ((array) ($json ? json_decode($json, true) : []) as $post) {
            $totals = self::articleTotals($post['content']['rendered'] ?? '');
            if ($totals['actives'] !== null) {
                return ['url' => $post['link']] + $totals;
            }
        }

        return null;
    }

    /** "Last Update:" or the date of the stats page, from the REST API (the date the page was published). */
    public static function pageDate(string $slugUrl): ?string
    {
        $slug = basename(rtrim((string) parse_url($slugUrl, PHP_URL_PATH), '/'));
        $json = Http::plain('https://www.hypergridbusiness.com/wp-json/wp/v2/pages?' . http_build_query(['slug' => $slug, '_fields' => 'date']));
        $rows = $json ? json_decode($json, true) : null;

        return $rows[0]['date'] ?? null;
    }
}
