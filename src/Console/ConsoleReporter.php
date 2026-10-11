<?php

declare(strict_types=1);

namespace OpenSimKit\Console;

use OpenSim\Runtime\ReporterInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * What the engine says, on the error output of the command as `opensim: <message>`: what is done, the problems in red, the
 * details with -v. The progress of a wait takes one line on a terminal, each step a line of its own elsewhere.
 */
final class ConsoleReporter implements ReporterInterface
{
    private readonly OutputInterface $err;
    private bool $open = false;
    private string $last = '';

    /**
     * @param ?bool $terminal whether the error output is a terminal, null to find out
     * @param ?int $columns the width of the terminal, null to find out
     */
    public function __construct(
        OutputInterface $output,
        private readonly ?bool $terminal = null,
        private readonly ?int $columns = null,
        private readonly bool $debug = false,
    ) {
        $this->err = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }

    public function info(string $message): void
    {
        $this->stepEnd();
        $this->err->writeln('<comment>opensim:</comment> ' . OutputFormatter::escape($message), OutputInterface::VERBOSITY_NORMAL);
    }

    public function error(string $message): void
    {
        $this->stepEnd();
        // A problem is told even in quiet mode
        $this->err->writeln('<fg=red>opensim:</> ' . OutputFormatter::escape($message), OutputInterface::VERBOSITY_QUIET);
    }

    public function debug(string $message): void
    {
        if ($this->debug || $this->err->isVerbose()) {
            $this->stepEnd();
            $this->err->writeln('<comment>opensim:</comment> ' . OutputFormatter::escape($message), OutputInterface::VERBOSITY_QUIET);
        }
    }

    public function step(string $text): void
    {
        if ($this->err->isQuiet()) {
            return;
        }
        if ($this->isTerminal()) {
            $width = $this->columns ?? (int) (getenv('COLUMNS') ?: 80);
            $this->err->write("\r\033[2K" . substr($text, 0, max(1, $width - 1)), false, OutputInterface::OUTPUT_RAW);
            $this->open = true;

            return;
        }
        if ($text !== $this->last) {
            $this->err->writeln($text, OutputInterface::OUTPUT_RAW);
            $this->last = $text;
        }
    }

    public function stepEnd(): void
    {
        $this->last = '';
        if ($this->open) {
            $this->open = false;
            $this->err->write("\n");
        }
    }

    public function isTerminal(): bool
    {
        return $this->terminal ?? (function_exists('posix_isatty') && @posix_isatty(STDERR));
    }
}
