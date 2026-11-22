<?php

declare(strict_types=1);

use GridSurvey\Classifier;
use GridSurvey\Matcher;
use GridSurvey\Signatures;

/** The fingerprints of the tests: a few real entries of the webappanalyzer database, and our own. */
function tool(): string
{
    return dirname(__DIR__);
}

function sample(string $name): string
{
    return __DIR__ . '/fixtures/' . $name;
}

/** The whole database when it is downloaded, the saved subset otherwise: the tests never use the network. */
function signatures(): Signatures
{
    static $signatures;
    if ($signatures === null) {
        $classes = json_decode(file_get_contents(tool() . '/signatures/classes.json'), true);
        $signatures = (new Signatures(sys_get_temp_dir() . '/grid-survey-tests', tool() . '/signatures'))
            ->load($classes['scope'], sample('webappanalyzer'));
    }

    return $signatures;
}

function classifier(): Classifier
{
    $classes = json_decode(file_get_contents(tool() . '/signatures/classes.json'), true);

    return new Classifier(new Matcher(signatures()->technologies()), $classes, signatures()->technologies());
}

/** A saved crawl of a grid (tests/fixtures/crawls). */
function crawl(string $slug): array
{
    return json_decode(file_get_contents(sample("crawls/$slug.json")), true);
}

/** One saved page, for the crawls written inline. */
function page(string $role, string $url, string $body, array $headers = [], array $cookies = [], int $status = 200): array
{
    return [
        'role' => $role, 'url' => $url, 'final_url' => $url, 'status' => $status,
        'headers' => $headers, 'cookies' => $cookies, 'body' => $body, 'error' => null,
    ];
}
