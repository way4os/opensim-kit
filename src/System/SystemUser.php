<?php

declare(strict_types=1);

namespace OpenSimKit\System;

/**
 * The instances belong to the system user of the install (opensim for the packages): screen sessions being per user, a command
 * that works on them runs as that user whoever types it. The command line is run again as the system user, with runuser as root,
 * with sudo otherwise.
 */
final class SystemUser
{
    /**
     * @param ?\Closure(list<string>):int $runner runs a command with the terminal of the caller, gives its status
     * @param ?string $current the user running now (default: the effective user)
     * @param ?bool $root whether the user running now is root (default: the effective user is 0)
     */
    public function __construct(
        private readonly ?\Closure $runner = null,
        private readonly ?string $current = null,
        private readonly ?bool $root = null,
    ) {
    }

    /**
     * The command that runs a command line as the system user, null when it already runs as it or the install has none.
     *
     * @param list<string> $command the command to run: the program, then its arguments
     * @return list<string>|null
     */
    public static function command(string $user, string $current, bool $root, array $command): ?array
    {
        if ($user === '' || $user === $current) {
            return null;
        }

        return $root ? ['runuser', '-u', $user, '--', ...$command] : ['sudo', '-H', '-u', $user, ...$command];
    }

    /**
     * Run the command as the system user, with the terminal of the caller.
     *
     * @param list<string> $command
     * @return int|null its status, null when it is not needed (this process already is the system user)
     */
    public function run(string $user, array $command): ?int
    {
        $switch = self::command($user, $this->current ?? self::whoami(), $this->root ?? self::isRoot(), $command);
        if ($switch === null) {
            return null;
        }

        return ($this->runner ?? self::passthrough(...))($switch);
    }

    /** @param list<string> $command */
    private static function passthrough(array $command): int
    {
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);

        return is_resource($process) ? proc_close($process) : 1;
    }

    private static function whoami(): string
    {
        if (function_exists('posix_geteuid') && ($info = posix_getpwuid(posix_geteuid())) !== false) {
            return $info['name'];
        }

        return trim((string) shell_exec('id -un 2>/dev/null'));
    }

    private static function isRoot(): bool
    {
        return function_exists('posix_geteuid') ? posix_geteuid() === 0 : trim((string) shell_exec('id -u 2>/dev/null')) === '0';
    }
}
