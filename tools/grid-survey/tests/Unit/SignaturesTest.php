<?php

declare(strict_types=1);

describe('fingerprints', function () {
    test('our entries extend the database entries with "merge", keeping its own rules', function () {
        $wordpress = signatures()->technologies()['WordPress'];

        expect($wordpress['meta']['generator'])->toContain('WordPress')
            ->and($wordpress['html'])->toContain('/wp-content/(?:uploads|themes|plugins)/');
    });

    test('our entries without "merge" are added as they are', function () {
        expect(signatures()->technologies())->toHaveKeys(['w4os', 'DreamGrid', 'Diva Wifi', 'jOpenSim', 'Modlos', 'osWebInterface', 'Hosting4Opensim', 'Kitely']);
    });

    test('only the categories in scope are kept', function () {
        expect(signatures()->technologies())->not->toHaveKey('Bootstrap2')
            ->and(array_unique(array_merge(...array_column(signatures()->technologies(), 'cats'))))->not->toContain(27);
    });

    test('the database is pinned to a commit', function () {
        expect(trim(file_get_contents(tool() . '/signatures/webappanalyzer.sha')))->toMatch('~^[0-9a-f]{40}$~');
    });
});
