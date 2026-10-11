<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim setup: the setup wizard. It runs as the person who started it, who cannot read nor write the files of the grids (they
 * are for the system user, mode 640): it asks that user for them, with sudo. Say so before the questions, not after the answers.
 */
final class SetupCommand extends DelegateCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('setup')->setDescription('The setup wizard: grids, simulators, regions, accounts');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $user = $this->engine($out)->layout->systemUser();
        if ($user !== '' && self::whoami() !== $user && !self::isRoot()) {
            exec('sudo -u ' . escapeshellarg($user) . ' true 2>/dev/null', $lines, $code);
            if ($code !== 0) {
                self::say($err, "opensim: the setup needs sudo to read and write the files of the grids, which belong to $user: run it with sudo, or as a user allowed to run commands as $user\n");

                return 1;
            }
        }

        return self::passthrough([PHP_BINARY, self::root() . '/install/install.php', ...$args]);
    }

    private static function whoami(): string
    {
        return function_exists('posix_geteuid') && ($info = posix_getpwuid(posix_geteuid())) !== false ? $info['name'] : trim((string) shell_exec('id -un 2>/dev/null'));
    }

    private static function isRoot(): bool
    {
        return function_exists('posix_geteuid') ? posix_geteuid() === 0 : trim((string) shell_exec('id -u 2>/dev/null')) === '0';
    }
}
