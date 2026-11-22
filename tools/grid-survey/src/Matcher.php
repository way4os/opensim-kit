<?php

declare(strict_types=1);

namespace GridSurvey;

use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Runs webappanalyzer-format rules on saved pages (no browser). Handles the fields a saved page can answer:
 * url, headers, cookies, meta, html, text, scripts, scriptSrc, dom; with `\;version:` and `\;confidence:`,
 * `implies` and `excludes`. The fields that need a live browser (js, xhr, dns, probe...) are ignored.
 */
final class Matcher
{
    private CssSelectorConverter $css;

    /** @param array<string, array<string, mixed>> $technologies */
    public function __construct(private readonly array $technologies)
    {
        $this->css = new CssSelectorConverter();
    }

    /**
     * Technologies found in a set of pages (one fetched response each).
     *
     * @param list<array{url: string, headers: array<string, string>, cookies: list<string>, body: string}> $pages
     * @return array<string, array{confidence: int, version: string, pages: list<string>}> by technology name
     */
    public function detect(array $pages): array
    {
        $found = [];
        foreach ($pages as $page) {
            $facts = $this->facts($page);
            foreach ($this->technologies as $name => $tech) {
                $hit = $this->match($tech, $facts);
                if ($hit === null) {
                    continue;
                }
                $found[$name] ??= ['confidence' => 0, 'version' => '', 'evidence' => '', 'pages' => []];
                $found[$name]['evidence'] = $found[$name]['evidence'] ?: $hit['evidence'];
                $found[$name]['confidence'] = min(100, $found[$name]['confidence'] + $hit['confidence']);
                $found[$name]['version'] = $found[$name]['version'] ?: $hit['version'];
                $found[$name]['pages'][] = $page['url'];
            }
        }

        // implies / excludes
        foreach (array_keys($found) as $name) {
            foreach ((array) ($this->technologies[$name]['implies'] ?? []) as $implied) {
                [$impliedName, $options] = $this->split($implied);
                if (isset($this->technologies[$impliedName]) && !isset($found[$impliedName])) {
                    $found[$impliedName] = [
                        'confidence' => (int) ($options['confidence'] ?? 100),
                        'version' => '',
                        'pages' => $found[$name]['pages'],
                    ];
                }
            }
        }
        foreach (array_keys($found) as $name) {
            foreach ((array) ($this->technologies[$name]['excludes'] ?? []) as $excluded) {
                unset($found[$excluded]);
            }
        }

        return $found;
    }

    /** @return array<string, mixed> */
    private function facts(array $page): array
    {
        $html = $page['body'];
        $dom = null;
        $meta = [];
        $scriptSrc = [];
        $scripts = [];
        if (str_contains($html, '<')) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();
            foreach ($dom->getElementsByTagName('meta') as $node) {
                $key = strtolower($node->getAttribute('name') ?: $node->getAttribute('property'));
                if ($key !== '') {
                    $meta[$key][] = $node->getAttribute('content');
                }
            }
            foreach ($dom->getElementsByTagName('script') as $node) {
                $src = $node->getAttribute('src');
                if ($src !== '') {
                    $scriptSrc[] = $src;
                } elseif ($node->textContent !== '') {
                    $scripts[] = $node->textContent;
                }
            }
        }

        return [
            'url' => $page['url'],
            'headers' => $page['headers'],
            'cookies' => $page['cookies'],
            'html' => $html,
            'text' => $dom ? (string) $dom->textContent : $html,
            'meta' => $meta,
            'scriptSrc' => $scriptSrc,
            'scripts' => $scripts,
            'dom' => $dom,
            'xpath' => $dom ? new \DOMXPath($dom) : null,
        ];
    }

    /** @return array{confidence: int, version: string, evidence: string}|null */
    private function match(array $tech, array $facts): ?array
    {
        $confidence = 0;
        $version = '';
        $evidence = '';
        $add = function (?array $hit) use (&$confidence, &$version, &$evidence): void {
            if ($hit !== null) {
                $confidence += $hit['confidence'];
                $version = $version ?: $hit['version'];
                $evidence = $evidence ?: ($hit['evidence'] ?? '');
            }
        };

        foreach ($this->list($tech['url'] ?? []) as $pattern) {
            $add($this->test($pattern, $facts['url']));
        }
        foreach ((array) ($tech['headers'] ?? []) as $header => $pattern) {
            $value = $facts['headers'][strtolower($header)] ?? null;
            if ($value !== null) {
                $add($this->test((string) $pattern, $value));
            }
        }
        foreach ((array) ($tech['cookies'] ?? []) as $cookie => $pattern) {
            foreach ($facts['cookies'] as $name) {
                if (strcasecmp($name, $cookie) === 0 || @preg_match('~^' . str_replace('~', '\~', $cookie) . '$~i', $name) === 1) {
                    $add($this->test((string) $pattern, $name) ?? ['confidence' => 100, 'version' => '']);
                    break;
                }
            }
        }
        foreach ((array) ($tech['meta'] ?? []) as $key => $pattern) {
            foreach ($facts['meta'][strtolower($key)] ?? [] as $content) {
                foreach ($this->list($pattern) as $one) {
                    $add($this->test($one, $content));
                }
            }
        }
        foreach (['html', 'text'] as $field) {
            foreach ($this->list($tech[$field] ?? []) as $pattern) {
                $add($this->test($pattern, $facts[$field]));
            }
        }
        foreach ($this->list($tech['scriptSrc'] ?? []) as $pattern) {
            foreach ($facts['scriptSrc'] as $src) {
                $add($this->test($pattern, $src));
            }
        }
        foreach ($this->list($tech['scripts'] ?? []) as $pattern) {
            foreach ($facts['scripts'] as $script) {
                $add($this->test($pattern, $script));
            }
        }
        if (isset($tech['dom']) && $facts['xpath'] !== null) {
            $add($this->dom($tech['dom'], $facts['xpath']));
        }

        return $confidence > 0 ? ['confidence' => min(100, $confidence), 'version' => $version, 'evidence' => $evidence] : null;
    }

    /** @return array{confidence: int, version: string}|null */
    private function dom(mixed $rule, \DOMXPath $xpath): ?array
    {
        $rules = is_string($rule) ? [$rule => ['exists' => '']] : (array_is_list($rule) ? array_fill_keys($rule, ['exists' => '']) : $rule);
        foreach ($rules as $selector => $checks) {
            try {
                $nodes = $xpath->query($this->css->toXPath($selector));
            } catch (\Throwable) {
                continue;
            }
            if (!$nodes || $nodes->length === 0) {
                continue;
            }
            if (array_key_exists('exists', (array) $checks)) {
                return ['confidence' => 100, 'version' => ''];
            }
            foreach ($nodes as $node) {
                foreach ((array) ($checks['attributes'] ?? []) as $attribute => $pattern) {
                    if ($node->hasAttributes() && $node->attributes->getNamedItem($attribute) !== null
                        && ($hit = $this->test((string) $pattern, $node->attributes->getNamedItem($attribute)->nodeValue))) {
                        return $hit;
                    }
                }
                if (isset($checks['text']) && ($hit = $this->test((string) $checks['text'], $node->textContent))) {
                    return $hit;
                }
            }
        }

        return null;
    }

    /**
     * Tests one pattern, `regex\;version:\1\;confidence:50`. An empty regex matches when the field exists.
     *
     * @return array{confidence: int, version: string}|null
     */
    private function test(string $pattern, string $subject): ?array
    {
        [$regex, $options] = $this->split($pattern);
        $compiled = '~' . str_replace('~', '\~', $regex) . '~i';
        if ($regex === '') {
            $matches = [];
            $ok = 1;
        } else {
            $matches = [];
            $ok = @preg_match($compiled, $subject, $matches);
        }
        if ($ok !== 1) {
            return null;
        }
        $version = '';
        if (isset($options['version'])) {
            $version = preg_replace_callback(
                '~\\\\(\d)(?:\?([^:]*):(.*))?~',
                fn (array $m) => isset($m[2]) ? (($matches[(int) $m[1]] ?? '') !== '' ? $m[2] : ($m[3] ?? '')) : ($matches[(int) $m[1]] ?? ''),
                $options['version'],
            );
        }

        // the words that matched, to say what the signal rests on
        $evidence = $regex === '' ? '' : mb_substr(trim(preg_replace('~\s+~u', ' ', (string) ($matches[0] ?? ''))), 0, 60);

        return ['confidence' => (int) ($options['confidence'] ?? 100), 'version' => trim((string) $version), 'evidence' => $evidence];
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function split(string $pattern): array
    {
        $parts = explode('\;', $pattern);
        $regex = array_shift($parts);
        $options = [];
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$key, $value] = explode(':', $part, 2);
                $options[$key] = $value;
            }
        }

        return [$regex, $options];
    }

    /** @return list<string> */
    private function list(mixed $value): array
    {
        return array_map('strval', is_array($value) ? array_values($value) : [$value]);
    }
}
