<?php

declare(strict_types=1);

namespace GridSurvey;

use Spatie\Robots\RobotsTxt;

/**
 * Reads the public pages of one grid, read-only, from two sides:
 * - its site: robots.txt first (a site that forbids crawling is left alone), the listed address (following a
 *   redirect), the first registration-like link and the credits link of the same site;
 * - the grid itself: the grid info every viewer reads at the login address (`/get_grid_info`) and the pages it
 *   announces (register, welcome, password, about).
 * Only addresses the site or the grid publishes are read, never a guessed one, except the readme.txt of the w4os
 * plugin on a WordPress site, and only when asked (--probe-w4os). No login, no form, nothing sent but GET requests.
 */
final class Crawler
{
    public const ROBOTS_AGENT = 'grid-web-survey';

    /** Link texts or addresses that lead to a sign-up page, the surest first (a login page is the last resort) */
    private const REGISTER = [
        3 => '~register|sign.?up|create.?(?:an\s+)?account|new.?account|inscri|registrier|/wifi/user/account~i',
        2 => '~join|/wifi/?$~i',
        1 => '~log.?in|sign.?in|anmeld|portal|grid.?status~i',
    ];

    /** The plugin folders of the two w4os distributions (WordPress.org, GitHub) */
    private const W4OS_PROBES = ['w4os-opensimulator-web-interface', 'w4os'];

    /** @var array<string, ?RobotsTxt> */
    private array $robots = [];

    /** The addresses of the grid info that are websites (the others are services for the viewer) */
    private const PUBLISHED = ['register', 'welcome', 'password', 'about'];

    public function __construct(private readonly Fetcher $http, private readonly bool $probeW4os = false)
    {
    }

    /**
     * @param array{name: string, url: string, login_uri?: string} $grid
     * @return array{status: string, note: string, pages: list<array<string, mixed>>, grid: array<string, mixed>}
     *   status of the site: ok, robots (forbidden), unreachable (no answer) or error (an HTTP error or an empty page)
     */
    public function crawl(array $grid): array
    {
        $site = $this->crawlSite($grid);
        $site['grid'] = ($grid['login_uri'] ?? '') !== ''
            ? $this->crawlGrid($grid['login_uri'], $site['pages'])
            : ['status' => 'none', 'note' => 'no login address', 'info' => [], 'pages' => []];

        return $site;
    }

    /** @return array{status: string, note: string, pages: list<array<string, mixed>>} */
    private function crawlSite(array $grid): array
    {
        $url = $grid['url'];
        if (!$this->allowed($url)) {
            return ['status' => 'robots', 'note' => 'robots.txt forbids crawling', 'pages' => []];
        }

        $home = $this->http->get($url);
        $home['role'] = 'home';
        $pages = [$home];
        if ($home['error'] !== null && $home['status'] === 0) {
            return ['status' => 'unreachable', 'note' => (string) $home['error'], 'pages' => $pages];
        }

        // a meta refresh or a script redirect is one the HTTP client does not follow
        if (($target = $this->pageRedirect($home)) !== null && $this->allowed($target)) {
            $refreshed = $this->http->get($target);
            $refreshed['role'] = 'home';
            $pages = [$refreshed];
            $home = $refreshed;
            if ($home['error'] !== null && $home['status'] === 0) {
                return ['status' => 'unreachable', 'note' => (string) $home['error'], 'pages' => $pages];
            }
        }
        if ($home['status'] >= 400 || trim($home['body']) === '') {
            $note = $home['status'] >= 400 ? 'HTTP ' . $home['status'] : 'empty page';

            return ['status' => 'error', 'note' => $note, 'pages' => $pages];
        }

        $base = $home['final_url'] ?: $home['url'];
        foreach (['register' => $this->registerLink($home['body'], $base), 'credits' => $this->creditsLink($home['body'], $base)] as $role => $link) {
            if ($link !== null && $link !== $base && $this->allowed($link)) {
                $page = $this->http->get($link);
                $page['role'] = $role;
                $pages[] = $page;
            }
        }

        if ($this->probeW4os && str_contains($home['body'], '/wp-content/')) {
            foreach ($this->probes($home['body'], $base) as $probe) {
                if ($this->allowed($probe)) {
                    $page = $this->http->get($probe);
                    $page['role'] = 'probe';
                    $pages[] = $page;
                }
            }
        }

        return ['status' => 'ok', 'note' => '', 'pages' => $pages];
    }

    /**
     * The grid info of the login address (the one the list gives, nothing guessed but the scheme: http, then https)
     * and the pages it announces.
     *
     * @param list<array<string, mixed>> $sitePages
     * @return array{status: string, note: string, info: array<string, string>, pages: list<array<string, mixed>>}
     */
    private function crawlGrid(string $login, array $sitePages): array
    {
        $login = rtrim(trim($login), '/');
        $bases = preg_match('~^https?://~i', $login) ? [$login] : ["http://$login", "https://$login"];
        $info = null;
        $base = $bases[0];
        foreach ($bases as $base) {
            $url = $base . '/get_grid_info';
            if (!$this->allowed($url)) {
                return ['status' => 'robots', 'note' => 'robots.txt forbids the grid info', 'info' => [], 'pages' => []];
            }
            $page = $this->http->get($url);
            if ($page['status'] === 0) {
                continue;
            }
            $info = $page['status'] === 200 ? $this->gridInfo($page['body']) : null;
            break;
        }
        if ($info === null) {
            return ['status' => 'none', 'note' => 'no grid info', 'info' => [], 'pages' => []];
        }

        $seen = array_map(fn (array $p) => ListReader::key($p['final_url'] ?: $p['url']), $sitePages);
        $pages = [];
        // the root of the login address: Robust itself answers an HTML page on some installs (Diva Wifi, DreamGrid)
        $announced = $info + ['login' => $base . '/'];
        foreach (['login', ...self::PUBLISHED] as $key) {
            $url = $announced[$key] ?? '';
            if (!preg_match('~^https?://~i', $url) || in_array(ListReader::key($url), $seen, true) || !$this->allowed($url)) {
                continue;
            }
            $seen[] = ListReader::key($url);
            $page = $this->http->get($url);
            $page['role'] = 'published';
            $page['key'] = $key;
            $pages[] = $page;
        }

        return ['status' => 'ok', 'note' => '', 'info' => $info, 'pages' => $pages];
    }

    /**
     * The addresses of a grid info document (`<gridinfo><register>...`), null when it is not one.
     *
     * @return array<string, string>|null
     */
    private function gridInfo(string $xml): ?array
    {
        if (!str_contains($xml, '<gridinfo')) {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);
        if ($doc === false) {
            return null;
        }
        $info = [];
        // the helpers addresses (economy, search, message) are kept, not read
        foreach (['gridname', 'login', 'register', 'welcome', 'password', 'about', 'help', 'search', 'economy', 'message'] as $key) {
            if (isset($doc->{$key}) && trim((string) $doc->{$key}) !== '') {
                $info[$key] = trim((string) $doc->{$key});
            }
        }

        return $info;
    }

    /** @return list<string> the readme.txt of the w4os plugin, from the WordPress root the page shows */
    private function probes(string $html, string $base): array
    {
        $root = rtrim($this->origin($base), '/');
        // the WordPress folder the page itself shows, when it is on the same host (a page may load the files of another site)
        if (preg_match_all('~(?:src|href)=["\']([^"\']*?)/wp-content/~i', $html, $all)) {
            foreach ($all[1] as $path) {
                $candidate = rtrim($this->absolute($path === '' ? '/' : $path, $base), '/');
                if ($this->sameSite($candidate, $base)) {
                    $root = $candidate;
                    break;
                }
            }
        }

        return array_map(fn (string $dir) => "$root/wp-content/plugins/$dir/readme.txt", self::W4OS_PROBES);
    }

    private function allowed(string $url): bool
    {
        $origin = $this->origin($url);
        if (!array_key_exists($origin, $this->robots)) {
            $file = $this->http->get($origin . '/robots.txt');
            $this->robots[$origin] = $file['status'] === 200 && $file['error'] === null && !str_contains(strtolower($file['body']), '<html')
                ? RobotsTxt::create($file['body'])
                : null;
        }

        return $this->robots[$origin]?->allows($url, self::ROBOTS_AGENT) ?? true;
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * The target of a `<meta http-equiv="refresh" content="0; url=...">`, or of a page that is only a script
     * (`location.href = '...'`), as an absolute address.
     */
    private function pageRedirect(array $page): ?string
    {
        $target = null;
        if (preg_match('~<meta[^>]+http-equiv=["\']?refresh["\']?[^>]+content=["\']?\s*\d+\s*;\s*url=([^"\'>]+)~i', $page['body'], $m)) {
            $target = html_entity_decode(trim($m[1]));
        } elseif (strlen($page['body']) < 500 && preg_match('~location(?:\.href)?\s*=\s*["\']([^"\']+)["\']~i', $page['body'], $m)) {
            $target = trim($m[1]);
        }

        return $target === null ? null : $this->absolute($target, $page['final_url'] ?: $page['url']);
    }

    private function registerLink(string $html, string $base): ?string
    {
        return $this->link($html, $base, function (string $text, string $href): int {
            foreach (self::REGISTER as $score => $pattern) {
                if (preg_match($pattern, $text . ' ' . $href) === 1) {
                    return $score;
                }
            }

            return 0;
        });
    }

    private function creditsLink(string $html, string $base): ?string
    {
        return $this->link($html, $base, fn (string $text) => (int) (preg_match('~site\s+credits~i', $text) === 1));
    }

    /** The link of the same site (same host and port) the best score goes to (0: not wanted), the first one on a tie. */
    private function link(string $html, string $base, callable $score): ?string
    {
        $best = null;
        $bestScore = 0;
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || preg_match('~^(?:#|mailto:|javascript:|tel:)~i', $href)) {
                continue;
            }
            $points = $score(trim(preg_replace('~\s+~u', ' ', $a->textContent)), $href);
            if ($points > $bestScore && $this->sameSite($url = $this->absolute($href, $base), $base)) {
                $best = $url;
                $bestScore = $points;
            }
        }

        return $best;
    }

    /** The same host, on any port: the sign-up of a grid is often on its grid port (:8002/wifi). */
    private function sameSite(string $a, string $b): bool
    {
        return strtolower((string) parse_url($a, PHP_URL_HOST)) === strtolower((string) parse_url($b, PHP_URL_HOST));
    }

    /** Resolves a link against the page address. */
    private function absolute(string $href, string $base): string
    {
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $parts = parse_url($base);
        $origin = $this->origin($base);
        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'http') . ':' . $href;
        }
        $path = str_starts_with($href, '/') ? $href : preg_replace('~[^/]*$~', '', $parts['path'] ?? '/') . $href;

        // resolve ./ and ../ (the query string and fragment stay as they are)
        [$path, $rest] = array_pad(preg_split('~(?=[?#])~', $path, 2), 2, '');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }

        return $origin . preg_replace('~^/*~', '/', implode('/', $segments)) . $rest;
    }
}
