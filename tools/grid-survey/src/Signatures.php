<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * The fingerprints: the technologies database of enthec/webappanalyzer (the maintained successor of Wappalyzer's
 * rules), pinned to a commit, plus our own entries for the OpenSim web interfaces (same format, signatures/own.json).
 */
final class Signatures
{
    private const RAW = 'https://raw.githubusercontent.com/enthec/webappanalyzer/%s/src/%s';

    /** @var array<string, array<string, mixed>> */
    private array $technologies = [];

    /** @var array<int, string> */
    private array $categories = [];

    /**
     * @param string $cache Folder of the downloaded database (not in git)
     * @param string $own   Folder of our own signatures
     */
    public function __construct(private readonly string $cache, private readonly string $own)
    {
    }

    /** The commit of the database in use, from signatures/webappanalyzer.sha. */
    public function pinned(): string
    {
        return trim((string) @file_get_contents($this->own . '/webappanalyzer.sha'));
    }

    /**
     * Downloads the database at a commit (the pinned one by default) into the cache and, when a new
     * commit is given, pins it.
     */
    public function download(?string $sha = null): string
    {
        $sha ??= $this->pinned();
        if ($sha === '') {
            throw new \RuntimeException('No commit pinned: pass one (signatures/webappanalyzer.sha)');
        }
        $dir = $this->cache . '/webappanalyzer';
        if (!is_dir($dir . '/technologies') && !mkdir($dir . '/technologies', 0775, true)) {
            throw new \RuntimeException("Cannot create $dir");
        }
        $files = ['categories.json'];
        foreach (array_merge(['_'], range('a', 'z')) as $letter) {
            $files[] = "technologies/$letter.json";
        }
        foreach ($files as $file) {
            $json = Http::plain(sprintf(self::RAW, $sha, $file));
            if ($json === null || json_decode($json) === null) {
                throw new \RuntimeException("Cannot download $file at $sha");
            }
            file_put_contents("$dir/$file", $json);
        }
        file_put_contents($dir . '/commit', $sha . "\n");
        file_put_contents($this->own . '/webappanalyzer.sha', $sha . "\n");

        return $sha;
    }

    /**
     * Loads the database (downloading the pinned commit when the cache is empty) and our own entries.
     * Ours win over the database for the same name.
     *
     * @param list<string>|null $only Names of the categories to keep (the others are not worth testing here)
     */
    public function load(?array $only = null, ?string $database = null): self
    {
        $dir = $database ?? $this->cache . '/webappanalyzer';
        if (!is_file($dir . '/categories.json')) {
            $this->download();
        }
        $categories = json_decode((string) file_get_contents($dir . '/categories.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($categories as $id => $category) {
            $this->categories[(int) $id] = $category['name'];
        }
        $this->categories += self::ownCategories();

        $technologies = [];
        foreach (glob($dir . '/technologies/*.json') ?: [] as $file) {
            $technologies += json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        }
        $own = $this->own . '/own.json';
        if (is_file($own)) {
            foreach (json_decode((string) file_get_contents($own), true, flags: JSON_THROW_ON_ERROR) as $name => $entry) {
                $technologies[$name] = ($entry['merge'] ?? false) && isset($technologies[$name])
                    ? self::merge($technologies[$name], $entry)
                    : $entry;
            }
        }

        if ($only !== null) {
            $keep = array_keys(array_filter($this->categories, fn (string $name) => in_array($name, $only, true)));
            $technologies = array_filter(
                $technologies,
                fn (array $tech) => array_intersect($tech['cats'] ?? [], $keep) !== [],
            );
        }
        $this->technologies = $technologies;

        return $this;
    }

    /**
     * Our additions to an entry of the database ("merge": true): patterns are added to its patterns, other fields
     * replace its own, so the rules we do not touch keep following the database.
     *
     * @param array<string, mixed> $upstream
     * @param array<string, mixed> $ours
     * @return array<string, mixed>
     */
    private static function merge(array $upstream, array $ours): array
    {
        unset($ours['merge']);
        foreach ($ours as $field => $value) {
            $current = $upstream[$field] ?? null;
            if (!in_array($field, ['url', 'html', 'text', 'scripts', 'scriptSrc', 'dom', 'meta', 'headers', 'cookies', 'implies'], true) || $current === null) {
                $upstream[$field] = $value;
            } elseif (is_array($value) && !array_is_list($value) || is_array($current) && !array_is_list($current)) {
                // keyed rules (meta, headers, cookies, dom with attributes)
                $upstream[$field] = array_replace(
                    is_array($current) && !array_is_list($current) ? $current : array_fill_keys((array) $current, ['exists' => '']),
                    is_array($value) && !array_is_list($value) ? $value : array_fill_keys((array) $value, ['exists' => '']),
                );
            } else {
                $upstream[$field] = array_values(array_unique(array_merge((array) $current, (array) $value)));
            }
        }

        return $upstream;
    }

    /** Category ids of our own entries, outside the range of the database. */
    public static function ownCategories(): array
    {
        return [
            1001 => 'OpenSim web interface',
            1002 => 'OpenSim hosting',
            1003 => 'OpenSim grid server',
            1004 => 'Default server page',
            1005 => 'Not OpenSim statement',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function technologies(): array
    {
        return $this->technologies;
    }

    /** @return list<string> */
    public function categoryNames(array $ids): array
    {
        return array_values(array_map(fn (int $id) => $this->categories[$id] ?? (string) $id, $ids));
    }
}
