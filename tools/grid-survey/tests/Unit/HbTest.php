<?php

declare(strict_types=1);

use GridSurvey\Hb;

describe('Hypergrid Business pages, found each time', function () {
    $index = '<a href="https://www.hypergridbusiness.com/statistics/october-2026-opensim-grid-stats/">Oct</a>'
        . '<a href="https://www.hypergridbusiness.com/statistics/november-2026-opensim-grid-stats/">Nov</a>'
        . '<a href="https://www.hypergridbusiness.com/statistics/december-2025-opensim-grid-stats/">Dec 2025</a>'
        . '<a href="https://www.hypergridbusiness.com/statistics/active-grids/">Active</a>';

    test('the newest stats page is the one linked from the Statistics page', function () use ($index) {
        expect(Hb::latestStats($index))->toBe([
            'url' => 'https://www.hypergridbusiness.com/statistics/november-2026-opensim-grid-stats/',
            'month' => '2026-11',
        ]);
    });

    test('a stats page later than the list is not taken', function () use ($index) {
        expect(Hb::latestStats($index, '2026-10')['month'])->toBe('2026-10');
    });

    test('nothing found is null, not an error', function () {
        expect(Hb::latestStats('<a href="/x">x</a>'))->toBeNull();
    });

    test('reads the totals of the monthly article', function () {
        $html = '<p>OpenSim’s public grids reported a record 52,131 active users this month. Total land area grew by the equivalent of 4,863 standard regions, to 168,288, and the total number of registered users went up by 749, to 454,137.</p>';

        expect(Hb::articleTotals($html))->toBe(['actives' => 52131, 'regions' => 168288, 'users' => 454137]);
    });
});
