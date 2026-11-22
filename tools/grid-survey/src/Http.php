<?php

declare(strict_types=1);

namespace GridSurvey;

/**
 * Read-only HTTP fetching of public pages: GET only, no cookie kept, a fixed user agent that says what the tool is and
 * where to reach its author, a timeout, a body size cap, a pause between
 * two requests to the same host. Nothing is ever sent but the request itself (no cookie, no form, no login).
 */
final class Http implements Fetcher
{
    public const USER_AGENT = 'grid-web-survey/1.0; read-only market study; +https://w4os.org/contact';
    public const TIMEOUT = 15;
    public const MAX_BODY = 524288;

    /** @var array<string, float> time of the last request, by host */
    private array $last = [];

    /**
     * @param float $pauseMin Seconds to wait between two requests to one host (random between min and max)
     */
    public function __construct(private readonly float $pauseMin = 0.5, private readonly float $pauseMax = 1.0)
    {
    }

    /**
     * Fetches a page.
     *
     * @return array{url: string, final_url: string, status: int, headers: array<string, string>, cookies: list<string>, body: string, error: ?string}
     */
    public function get(string $url): array
    {
        $this->pause($url);

        $headers = [];
        $cookies = [];
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.5'],
            // Sites with a broken certificate are still read: only the page is looked at, nothing is sent
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers, &$cookies): int {
                $length = strlen($line);
                if (str_starts_with($line, 'HTTP/')) {
                    // a redirect starts a new response: keep the last one only
                    $headers = [];
                    $cookies = [];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    $value = trim($value);
                    if ($name === 'set-cookie') {
                        // only the name is kept: the value is a session of the visitor
                        $cookies[] = $value = trim(explode('=', $value, 2)[0]);
                    }
                    $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
                }

                return $length;
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body): int {
                $body .= $chunk;

                // stop reading past the cap, the first part of a page is enough
                return strlen($body) > self::MAX_BODY ? 0 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $ok === false && !($errno === CURLE_WRITE_ERROR && strlen($body) > self::MAX_BODY)
            ? curl_strerror($errno) . ' (' . $errno . ')'
            : null;
        $result = [
            'url' => $url,
            'final_url' => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'headers' => $headers,
            'cookies' => $cookies,
            'body' => substr($body, 0, self::MAX_BODY),
            'error' => $error,
        ];
        curl_close($ch);
        $this->last[self::host($url)] = microtime(true);

        return $result;
    }

    /** A plain fetch with no pause, for the files of this tool's own data (GitHub). */
    public static function plain(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $body !== false && $status === 200 ? (string) $body : null;
    }

    public static function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST)) . ':' . (parse_url($url, PHP_URL_PORT) ?? '');
    }

    private function pause(string $url): void
    {
        $wait = $this->last[self::host($url)] ?? null;
        if ($wait === null) {
            return;
        }
        $delay = $this->pauseMin + mt_rand() / mt_getrandmax() * ($this->pauseMax - $this->pauseMin);
        $left = $wait + $delay - microtime(true);
        if ($left > 0) {
            usleep((int) ($left * 1_000_000));
        }
    }
}
