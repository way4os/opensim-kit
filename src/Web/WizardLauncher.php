<?php

declare(strict_types=1);

namespace OpenSimKit\Web;

use OpenSimKit\Ui\WebUi;

/**
 * Starts the web side of the setup: a web server of PHP (php -S) on this machine only, on a port that is free, that the setup
 * itself starts and stops, and the interface the flow of the setup talks to. The address it gives works once.
 */
final class WizardLauncher
{
    /**
     * @param ?int $port the port to listen on, a free one when null
     * @throws \RuntimeException when the server does not start
     */
    public static function start(?int $port = null): WebSession
    {
        $mailbox = Mailbox::create();
        $token = bin2hex(random_bytes(16));
        $mailbox->allow($token);
        $port ??= self::freePort();

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname(__DIR__, 2) . '/web', dirname(__DIR__, 2) . '/web/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', "{$mailbox->dir}/server.log", 'w'], 2 => ['redirect', 1]],
            $pipes,
            null,
            ['OPENSIM_WIZARD_DIR' => $mailbox->dir, 'OPENSIM_WIZARD_PORT' => (string) $port] + (getenv() ?: []),
        );
        if (!is_resource($process)) {
            $mailbox->remove();
            throw new \RuntimeException(_('The web server of the setup does not start.'));
        }

        $deadline = microtime(true) + 5;
        while (!($socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2))) {
            if (microtime(true) > $deadline || !(proc_get_status($process)['running'] ?? false)) {
                $log = trim((string) @file_get_contents("{$mailbox->dir}/server.log"));
                proc_terminate($process);
                proc_close($process);
                $mailbox->remove();
                throw new \RuntimeException(_('The web server of the setup does not start.') . ($log !== '' ? "\n$log" : ''));
            }
            usleep(100_000);
        }
        fclose($socket);

        $session = null;
        $kept = time();
        $ui = new WebUi($mailbox, static function () use (&$session, &$kept): void {
            if ($session !== null && !$session->running()) {
                throw new \RuntimeException(_('The web server of the setup stopped.'));
            }
            // The person may take their time with a form: the rights to act as the user of the install (sudo) do not expire meanwhile
            if (time() - $kept > 240) {
                $kept = time();
                self::keepSudo();
            }
        });
        $session = new WebSession($mailbox, $ui, "http://127.0.0.1:$port/?t=$token", $port, $process);
        self::stopWith($session);

        return $session;
    }

    /** Open the address in the browser of this machine, when there is one in front of the person. */
    public static function open(string $url): bool
    {
        if (getenv('SSH_CONNECTION') !== false || getenv('SSH_TTY') !== false) {
            return false; // the browser is on the other side of the connection
        }
        $command = match (true) {
            PHP_OS_FAMILY === 'Darwin' => 'open',
            PHP_OS_FAMILY === 'Linux' && (getenv('DISPLAY') !== false || getenv('WAYLAND_DISPLAY') !== false) => 'xdg-open',
            default => null,
        };
        if ($command === null) {
            return false;
        }
        $process = @proc_open([$command, $url], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

        return is_resource($process) && proc_close($process) === 0;
    }

    /** The server and the exchanges do not outlive the setup, however it ends (Ctrl-C, an error, the end). */
    private static function stopWith(WebSession $session): void
    {
        register_shutdown_function(static fn() => $session->stop());
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach ([SIGINT, SIGTERM, SIGHUP] as $signal) {
                pcntl_signal($signal, static function () use ($session): never {
                    $session->stop();
                    exit(130);
                });
            }
        }
    }

    /** Renew the sudo rights already given, without ever asking for a password. */
    private static function keepSudo(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            @exec('sudo -n -v >/dev/null 2>&1');
        }
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException("no port is free: $error");
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }
}
