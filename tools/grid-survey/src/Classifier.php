<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * Puts a crawled grid in one of the systems of the w4os.org chart (the ids of grids.json), from the technologies
 * the fingerprints find in its pages. Order of the rules: what the grid itself serves (the OpenSim interfaces),
 * then the CMS or site builder of its website, then the sites with no known system, which are custom code.
 */
final class Classifier
{
    /** Why a grid is in no system (no chart row): the outcome of the crawl. */
    public const OUTCOMES = ['robots', 'unreachable', 'error', 'default_page'];

    /**
     * @param array<string, mixed> $classes signatures/classes.json
     * @param array<string, array<string, mixed>> $technologies to read the categories of the technologies found
     */
    public function __construct(
        private readonly Matcher $matcher,
        private readonly array $classes,
        private readonly array $technologies,
    ) {
    }

    /** A system that is a kind of another one: w4os is WordPress, jOpenSim is Joomla... */
    private const REFINES = [
        'wordpress_w4os' => 'wordpress',
        'jopensim' => 'joomla',
        'modlos' => 'other_cms',
        'other_cms_ui' => 'other_cms',
        'hosted' => 'other_cms',
        'dreamgrid' => 'diva_wifi',
    ];

    /**
     * A grid, from its site and from what the grid itself publishes. The site decides; the grid info decides when the
     * site has nothing known (custom code, or no answer); where both know something and differ, the site stays
     * and the result says to review it.
     *
     * @param array{status: string, note: string, pages: list<array<string, mixed>>, grid?: array<string, mixed>} $crawl
     * @return array<string, mixed> the classification of the site, with `by` (what each side says), `review`
     */
    public function classifyGrid(array $crawl): array
    {
        $site = $this->classify($crawl);
        $grid = $site['outcome'] === 'robots' ? null : $this->gridSide($crawl['grid'] ?? null);
        $prefix = fn (string $side, array $signals) => array_map(fn (string $s) => "$side: $s", $signals);
        $site['by'] = ['site' => $site['system'] ?? $site['outcome'], 'grid_info' => $grid['system'] ?? null];
        $site['review'] = null;
        $site['signals'] = $prefix('site', $site['signals']);
        if ($grid === null) {
            return $site;
        }
        $site['signals'] = array_merge($site['signals'], $prefix('grid info', $grid['signals']));
        $site['flags'] = array_values(array_unique(array_merge($site['flags'], $grid['flags'])));

        if ($site['system'] === null || $site['fallback']) {
            // the site says nothing known: the grid says what it runs
            return array_merge($site, ['system' => $grid['system'], 'outcome' => 'classified', 'confidence' => 'medium', 'fallback' => false]);
        }
        $agree = $site['system'] === $grid['system']
            || (self::REFINES[$site['system']] ?? null) === $grid['system']
            || (self::REFINES[$grid['system']] ?? null) === $site['system'];
        if ($agree) {
            $site['confidence'] = 'high';
        } else {
            $site['confidence'] = 'low';
            $site['review'] = "site says {$site['system']}, grid info says {$grid['system']}";
        }

        return $site;
    }

    /**
     * What the pages the grid announces say, only when they say something known.
     *
     * @param array<string, mixed>|null $grid
     * @return array<string, mixed>|null
     */
    private function gridSide(?array $grid): ?array
    {
        $pages = array_values(array_filter($grid['pages'] ?? [], fn ($p) => $p['status'] >= 200 && $p['status'] < 400));
        if ($pages === []) {
            return null;
        }
        // the published pages are read as the home pages of the grid
        $result = $this->classify(['status' => 'ok', 'note' => '', 'pages' => array_map(fn ($p) => ['role' => 'home'] + $p, $pages)]);

        return $result['system'] === null || $result['fallback'] ? null : $result;
    }

    /**
     * The classification of the site alone.
     *
     * @param array{status: string, note: string, pages: list<array<string, mixed>>} $crawl
     * @return array<string, mixed>
     */
    public function classify(array $crawl): array
    {
        // a probe that got an error page found nothing, whatever the error page says
        $pages = array_values(array_filter($crawl['pages'], fn ($p) => $p['role'] !== 'probe' || $p['status'] === 200));
        $home = array_values(array_filter($pages, fn ($p) => $p['role'] === 'home'));
        $all = $this->matcher->detect($pages);
        $onHome = $this->matcher->detect($home);
        $signals = array_map(
            fn (string $name, array $d) => $name . ($d['version'] !== '' ? ' ' . $d['version'] : ''),
            array_keys($all),
            $all,
        );

        $flags = isset($all['Not OpenSim statement']) ? ['says it is not OpenSim'] : [];
        $result = fn (?string $system, string $outcome, string $confidence, bool $own = false) => [
            'system' => $system,
            'outcome' => $outcome,
            'confidence' => $confidence,
            'signals' => $signals,
            'own_interface' => $own,
            'detected' => $all,
            'flags' => $flags,
            // nothing known was recognised: custom code, or the grid service alone
            'fallback' => in_array($system, ['custom', 'custom_site', 'grid_server'], true),
            'w4os_signal' => null,
        ];

        // No answer, or no readable site: the outcome of the crawl, unless the grid server itself answers
        $serverOnly = isset($onHome['OpenSim grid server']) && count($onHome) === 1;
        if ($crawl['status'] !== 'ok') {
            if ($crawl['status'] === 'error' && $serverOnly) {
                return $result('grid_server', 'classified', 'low');
            }

            return $result(null, $crawl['status'], 'n/a');
        }

        // 1. interfaces added to a CMS, or the hosted panels
        $interfaces = $this->classes['interfaces'];
        // every use of the w4os family is one row (the signal that decided is kept, to tell them apart later)
        foreach ($this->classes['w4os'] as $name) {
            if (isset($all[$name])) {
                return array_merge($result('wordpress_w4os', 'classified', $all[$name]['confidence'] >= 90 ? 'high' : 'medium'), ['w4os_signal' => $name]);
            }
        }
        foreach (['jOpenSim', 'Modlos'] as $name) {
            if (isset($all[$name])) {
                return $result($interfaces[$name], 'classified', $all[$name]['confidence'] >= 90 ? 'high' : 'medium');
            }
        }
        foreach ($this->classes['hosted'] as $name) {
            if (isset($all[$name])) {
                return $result('hosted', 'classified', 'high');
            }
        }

        // 2. what the grid itself serves: osWebInterface, DreamGrid, Diva Wifi
        foreach (['osWebInterface', 'DreamGrid', 'Diva Wifi'] as $name) {
            if (isset($onHome[$name])) {
                return $result($interfaces[$name], 'classified', $onHome[$name]['confidence'] >= 90 ? 'high' : 'low');
            }
        }

        // 3. the CMS or site builder of the website
        $found = array_keys($onHome);
        $cms = $this->classes['cms'];
        foreach ($cms as $name => $system) {
            if (isset($onHome[$name])) {
                return $result($system, 'classified', $onHome[$name]['confidence'] >= 90 ? 'high' : 'medium');
            }
        }
        foreach ($found as $name) {
            if (in_array($name, $this->classes['builders'], true)) {
                return $result('builders', 'classified', 'high');
            }
        }
        $own = $this->ownInterface($pages, $all);
        foreach ($found as $name) {
            $framework = in_array($name, $this->classes['frameworks'], true);
            if ($framework || $this->isCms($name)) {
                // a CMS product has a sign-up of its own: it counts only when it asks for an avatar, a framework site's own code does
                $ui = $framework ? $own : $own && $this->opensimSignup($pages);

                return $result($ui ? 'other_cms_ui' : 'other_cms', 'classified', $onHome[$name]['confidence'] >= 90 ? 'high' : 'medium', $ui);
            }
        }

        // 4. nothing known: the page of a web server nobody set up, the grid server alone, or the grid's own code
        if (isset($onHome['Default server page'])) {
            return $result(null, 'default_page', 'low');
        }
        if ($serverOnly) {
            return $result('grid_server', 'classified', 'low');
        }

        return $result($own ? 'custom' : 'custom_site', 'classified', $own ? 'medium' : 'low', $own);
    }

    /** A technology of the CMS category that is not a site builder, a forum or one of our own. */
    private function isCms(string $name): bool
    {
        $categories = $this->technologies[$name]['cats'] ?? [];

        return in_array(1, $categories, true) || in_array(21, $categories, true);
    }

    /** The sign-up or login page asks for what an OpenSim account has (an avatar, a first and last name). */
    private function opensimSignup(array $pages): bool
    {
        foreach ($pages as $page) {
            if ($page['role'] === 'register' && preg_match('~avatar|first.?name|last.?name|opensim|hypergrid|vorname|nachname~i', strip_tags($page['body']) . ' ' . $page['body'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The site carries its own sign-up or login: a registration page found, a password field on the home or
     * registration page, or a registration through an OpenSim interface (Diva Wifi) the site links to.
     */
    private function ownInterface(array $pages, array $detected): bool
    {
        foreach ($pages as $page) {
            if (in_array($page['role'], ['home', 'register'], true) && preg_match('~<input[^>]+type=["\']?password~i', $page['body'])) {
                return true;
            }
            if ($page['role'] === 'register' && $page['status'] > 0 && $page['status'] < 400) {
                return true;
            }
        }

        return isset($detected['Diva Wifi']) || isset($detected['DreamGrid']);
    }
}
