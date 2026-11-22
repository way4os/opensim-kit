<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * The pages of a crawl, one JSON file per grid, so a classification can be redone (new rules, new fingerprints)
 * without crawling again.
 */
final class Store
{
    public function __construct(private readonly string $dir)
    {
    }

    /** @param array<string, mixed> $crawl */
    public function save(array $grid, array $crawl): void
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true)) {
            throw new \RuntimeException("Cannot create {$this->dir}");
        }
        file_put_contents(
            $this->path($grid),
            json_encode(['listed' => $grid] + $crawl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    /** @return array<string, mixed>|null */
    public function load(array $grid): ?array
    {
        $file = $this->path($grid);

        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    private function path(array $grid): string
    {
        $slug = trim((string) preg_replace('~[^a-z0-9]+~', '-', ListReader::key($grid['url'])), '-');

        return $this->dir . '/' . $slug . '.json';
    }
}
