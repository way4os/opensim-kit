<?php

declare(strict_types=1);

namespace OpenSimKit\Setup;

use OpenSim\Provisioning\UiInterface;

/**
 * Where the setup is gone through: in the terminal, or in a page of the browser served by the setup itself. Chosen by an
 * option (--terminal, --web) or asked, when someone is there to answer.
 */
final class SetupMode
{
    public const TERMINAL = 'terminal';
    public const WEB = 'web';

    /**
     * @param list<string> $args the options of the command
     * @param ?\Closure():string $ask the question, null when nobody can answer it (the terminal is the answer then)
     */
    public static function resolve(array $args, ?\Closure $ask): string
    {
        return match (true) {
            in_array('--terminal', $args, true) => self::TERMINAL,
            in_array('--web', $args, true) => self::WEB,
            $ask !== null => $ask(),
            default => self::TERMINAL,
        };
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
     */
    public static function instructions(string $url, int $port, bool $opened, bool $remote): string
    {
        $lines = [$opened ? _('The setup is opening in your browser. If it does not, this is the address:') : _('Open this address in your browser:'), "  $url", '', _('It works once, and only from this machine.')];
        if ($remote) {
            $lines[] = sprintf(_('This is a remote session: from your own computer, run `ssh -L %1$d:127.0.0.1:%1$d <this server>`, then open the address there.'), $port);
        }
        $lines[] = _('Ctrl-C here stops the setup.');

        return implode("\n", $lines);
    }
}
