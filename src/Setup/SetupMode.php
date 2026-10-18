<?php

declare(strict_types=1);

namespace OpenSimKit\Setup;

use OpenSim\Provisioning\UiInterface;

/**
 * Where the setup is gone through: in the terminal, in a page of the browser served by the setup itself, or by a script that asks
 * the questions the setup gives it (setup.sh). Chosen by an option (--terminal, --web, --frontend) or asked, when someone is there
 * to answer.
 */
final class SetupMode
{
    public const TERMINAL = 'terminal';
    public const WEB = 'web';
    public const SCRIPT = 'script';

    /**
     * @param list<string> $args the options of the command
     * @param ?\Closure():string $ask the question, null when nobody can answer it (the terminal is the answer then)
     */
    public static function resolve(array $args, ?\Closure $ask): string
    {
        return match (true) {
            self::frontend($args) !== null => self::SCRIPT,
            in_array('--terminal', $args, true) => self::TERMINAL,
            in_array('--web', $args, true) => self::WEB,
            $ask !== null => $ask(),
            default => self::TERMINAL,
        };
    }

    /**
     * The folder of the script that asks the questions (--frontend DIR or --frontend=DIR): it has the two named pipes the setup
     * talks through, `questions` (what the setup tells) and `answers` (what the script brings back).
     *
     * @param list<string> $args the options of the command
     */
    public static function frontend(array $args): ?string
    {
        foreach ($args as $i => $arg) {
            $value = str_starts_with($arg, '--frontend=') ? substr($arg, 11) : ($arg === '--frontend' ? ($args[$i + 1] ?? '') : null);
            if ($value !== null) {
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * The port the web side listens on, when the person chose it (--port 8123 or --port=8123): known beforehand, it lets
     * them open the tunnel to a remote machine before the setup starts.
     *
     * @param list<string> $args the options of the command
     */
    public static function port(array $args): ?int
    {
        foreach ($args as $i => $arg) {
            $value = str_starts_with($arg, '--port=') ? substr($arg, 7) : ($arg === '--port' ? ($args[$i + 1] ?? '') : null);
            if ($value !== null) {
                return ctype_digit($value) && (int) $value >= 1024 && (int) $value <= 65535 ? (int) $value : null;
            }
        }

        return null;
    }

    /**
     * Where the web side listens and who may come, from the options and from the connection the setup runs in. Over SSH (no screen
     * in front of the machine) the person's browser is somewhere else: the server then listens on every interface, for the address
     * they connected from only, and the page is given at the address they connected to, so nothing has to be tunnelled. On the
     * machine itself, nothing leaves it. --listen (local, all, or an address), --allow (addresses, or any) and --host (the name in the
     * address given) say otherwise.
     *
     * @param list<string> $args the options of the command
     * @param array<string,string> $env the environment (SSH_CONNECTION: client address and port, server address and port)
     * @return array{listen:string,allow:?list<string>,host:string,open:bool}
     * @throws \InvalidArgumentException when what is asked cannot be done or is not safe to guess
     */
    public static function network(array $args, array $env, string $machine = ''): array
    {
        $option = static function (string $name) use ($args): ?string {
            foreach ($args as $i => $arg) {
                if (str_starts_with($arg, "--$name=")) {
                    return substr($arg, strlen($name) + 3);
                }
                if ($arg === "--$name") {
                    return $args[$i + 1] ?? '';
                }
            }

            return null;
        };
        $ssh = preg_split('/\s+/', trim($env['SSH_CONNECTION'] ?? '')) ?: [];
        $client = count($ssh) === 4 ? $ssh[0] : null;
        $server = count($ssh) === 4 ? $ssh[2] : null;

        $listen = $option('listen') ?? ($client !== null ? 'all' : 'local');
        $listen = match ($listen) {
            'local' => '127.0.0.1',
            'all' => '0.0.0.0',
            default => filter_var($listen, FILTER_VALIDATE_IP) !== false ? $listen : throw new \InvalidArgumentException(sprintf(_('--listen: local, all or an address, not %s'), $listen)),
        };
        $open = !in_array($listen, ['127.0.0.1', '::1'], true);

        $allow = null;
        if ($open) {
            $given = $option('allow');
            $list = $given === null ? ($client !== null ? [$client] : null) : ($given === 'any' ? [] : array_values(array_filter(array_map('trim', explode(',', $given)))));
            if ($list === null) {
                throw new \InvalidArgumentException(_('The setup is open to the network: say who may connect, --allow ADDRESS (the one of your browser), or --allow any.'));
            }
            foreach ($list as $address) {
                filter_var($address, FILTER_VALIDATE_IP) !== false || throw new \InvalidArgumentException(sprintf(_('--allow: an address, not %s'), $address));
            }
            $allow = $list === [] ? null : array_map(\OpenSimKit\Web\WizardServer::address(...), $list);
        }

        $host = $option('host') ?? ($open ? ($server ?? $machine) : '127.0.0.1');
        if ($host === '') {
            throw new \InvalidArgumentException(_('--host: the name or the address of this machine, as your browser reaches it'));
        }

        return ['listen' => $listen, 'allow' => $allow, 'host' => str_contains($host, ':') && !str_starts_with($host, '[') ? "[$host]" : $host, 'open' => $open];
    }

    public static function ask(UiInterface $ui): string
    {
        return $ui->choose(
            _('How do you want to go through the setup?'),
            [
                self::TERMINAL => _('Here, in the terminal'),
                self::WEB => _('In your browser (a page that only this machine can open)'),
            ],
            self::TERMINAL,
        );
    }

    /**
     * What to tell the person, in the terminal, once the web side is there.
     *
     * @param bool $opened the browser of this machine was told to open the address
     * @param bool $remote the setup runs on another machine, over a connection
     * @param bool $open the server is open to the network (see network())
     * @param ?list<string> $allow the clients let in, when the server is open
     */
    public static function instructions(string $url, int $port, bool $opened, bool $remote, bool $open = false, ?array $allow = null): string
    {
        $lines = [$opened ? _('The setup is opening in your browser. If it does not, this is the address:') : _('Open this address in your browser:'), "  $url", '', _('It works once.')];
        if ($open) {
            $lines[] = $allow !== null
                ? sprintf(_('Only %s can reach it.'), implode(', ', $allow))
                : _('Anyone who has the address can reach it, until it is used.');
            $lines[] = _('The page is not encrypted: what you type, the passwords included, crosses the network as it is. On a network you do not trust, start again with --listen local and open a tunnel: ssh -L PORT:127.0.0.1:PORT.');
            $lines[] = sprintf(_('If the page does not open, a firewall may be blocking the port %d.'), $port);
        } else {
            $lines[] = _('Only from this machine.');
            if ($remote) {
                $lines[] = sprintf(_('This is a remote session: from your own computer, run `ssh -L %1$d:127.0.0.1:%1$d <this server>`, then open the address there.'), $port);
            }
        }
        $lines[] = _('Ctrl-C here stops the setup.');

        return implode("\n", $lines);
    }
}
