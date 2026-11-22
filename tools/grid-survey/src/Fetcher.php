<?php

declare(strict_types=1);

namespace GridSurvey;

/** Something that fetches a page by GET: the network in a run, saved pages in the tests. */
interface Fetcher
{
    /**
     * @return array{url: string, final_url: string, status: int, headers: array<string, string>, cookies: list<string>, body: string, error: ?string}
     */
    public function get(string $url): array;
}
