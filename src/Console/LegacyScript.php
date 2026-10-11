<?php

declare(strict_types=1);

namespace OpenSimKit\Console;

/**
 * The commands of `opensim` that are not ported to the application yet are still done by the script they come from
 * (`libexec/opensim-legacy`, bash). The application hands them the arguments as they were given and leaves them the
 * terminal, so that they work as they always did (screen, consoles, prompts). Gone with the last command ported.
 */
final class LegacyScript
{
    /** @param ?\Closure(list<string>):int $run runs a command with the terminal, gives its status; default, the real thing */
    public function __construct(
        private readonly string $path,
        private readonly ?\Closure $run = null,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Run the script with the arguments of the command line, and give its exit status.
     *
     * @param list<string> $arguments what follows `opensim`
     */
    public function run(array $arguments): int
    {
        $command = ['bash', $this->path, ...$arguments];
        if ($this->run !== null) {
            return ($this->run)($command);
        }

        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
        if (!is_resource($process)) {
            fwrite(STDERR, "opensim: cannot run {$this->path}\n");

            return 127;
        }

        return proc_close($process);
    }
}
