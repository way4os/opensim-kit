<?php

declare(strict_types=1);

namespace OpenSimKit\Web;

/**
 * What the web server of the setup answers. It shows the screen in the mailbox and brings the answers back to it; it knows
 * nothing of the setup itself. The address given by the setup works once (it gives the browser a secret, kept in a cookie), and
 * only this machine can reach it: the host name of a request must be the one of the server, so a page of another site cannot
 * speak to it through the name of a machine it controls.
 */
final class WizardServer
{
    public const COOKIE = 'opensim_wizard';

    /**
     * @param list<string> $hosts the host names (with the port) the server answers to
     * @param int $waitSeconds how long an answer waits for the next screen before showing the page of the work being done
     * @param int $pollMicros how long to sleep between two looks at the mailbox
     */
    public function __construct(
        private readonly Mailbox $mailbox,
        private readonly array $hosts,
        private readonly int $waitSeconds = 20,
        private readonly int $pollMicros = 50_000,
    ) {}

    /**
     * @param array<string,string> $query
     * @param array<string,mixed> $post
     * @param array<string,string> $cookies
     * @param array<string,string> $headers names in lower case (host, origin)
     * @return array{0:int,1:array<string,string>,2:string} the status, the headers, the page
     */
    public function handle(string $method, string $path, array $query, array $post, array $cookies, array $headers): array
    {
        if (!in_array(strtolower($headers['host'] ?? ''), $this->hosts, true)) {
            return self::plain(403, 'Forbidden');
        }
        if (isset($query['t'])) {
            // The address of the setup: it works once, then the browser shows its secret
            $secret = $this->mailbox->admit($query['t']);

            return $secret === null
                ? self::html(403, Page::refused())
                : [303, ['Location' => '/', 'Set-Cookie' => self::COOKIE . "=$secret; Path=/; HttpOnly; SameSite=Strict"], ''];
        }
        if (!$this->mailbox->admitted($cookies[self::COOKIE] ?? '')) {
            return self::html(403, Page::refused());
        }

        if ($method === 'POST' && $path === '/answer') {
            return $this->answer($post, $headers);
        }
        if ($method === 'GET' && $path === '/') {
            return $this->screen();
        }

        return self::plain($path === '/favicon.ico' ? 204 : 404, '');
    }

    /** @return array{0:int,1:array<string,string>,2:string} */
    private function screen(): array
    {
        $screen = $this->mailbox->screen();
        $id = (int) ($screen['id'] ?? 0);
        // Answered, and the setup is on it: the page of the work being done, which looks again
        $working = $screen !== null && ($this->mailbox->answered($id) !== null || $this->mailbox->consumed($id));
        $page = Page::render($screen, $this->mailbox->log(), $working, $this->mailbox->progressLine());
        ($screen['kind'] ?? '') === 'done' && $this->mailbox->seen($id);

        return self::html(200, $page);
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,string> $headers
     * @return array{0:int,1:array<string,string>,2:string}
     */
    private function answer(array $post, array $headers): array
    {
        $origin = $headers['origin'] ?? '';
        if ($origin !== '' && !in_array(strtolower((string) parse_url($origin, PHP_URL_HOST) . ':' . (string) parse_url($origin, PHP_URL_PORT)), $this->hosts, true)) {
            return self::plain(403, 'Forbidden');
        }
        $id = (int) ($post['id'] ?? 0);
        $action = in_array($post['action'] ?? 'next', ['back', 'quit'], true) ? (string) $post['action'] : 'next';
        // An answer to a screen that is not the current one (a second click, an old page) is of no use
        if ($id === $this->mailbox->current() && $this->mailbox->answered($id) === null && !$this->mailbox->consumed($id)) {
            $this->mailbox->answer($id, ['action' => $action, 'values' => is_array($post['v'] ?? null) ? $post['v'] : []]);
            // The next screen is most often there in a moment: wait for it, the page shows the work being done if it is long
            $deadline = microtime(true) + $this->waitSeconds;
            while ($this->mailbox->current() === $id && microtime(true) < $deadline) {
                usleep($this->pollMicros);
            }
        }

        return [303, ['Location' => '/'], ''];
    }

    /** @return array{0:int,1:array<string,string>,2:string} */
    private static function html(int $status, string $page): array
    {
        return [$status, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer'], $page];
    }

    /** @return array{0:int,1:array<string,string>,2:string} */
    private static function plain(int $status, string $text): array
    {
        return [$status, ['Content-Type' => 'text/plain; charset=utf-8'], $text];
    }
}
