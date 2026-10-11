<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command whose arguments are its own to read: the words and the options as they were typed (`opensim next port -e 9010`,
 * `opensim save iar mygrid Jane Doe / secret --noassets`), which a fixed definition of arguments would not fit. The command
 * reads them, calls the engine, prints.
 */
abstract class ScriptCommand extends EngineCommand
{
    /** What the command is typed with, as its help says it. */
    protected const USAGE = '';

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        static::USAGE === '' || $this->setHelp(static::USAGE);
        $this->addArgument('args', InputArgument::IS_ARRAY, 'see the usage');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $tokens = $input instanceof ArgvInput ? $input->getRawTokens(true) : array_map('strval', (array) $input->getArgument('args'));

        return $this->main(array_values($tokens), $output, $error);
    }

    /**
     * @param list<string> $args the words and the options as typed, the name of the command left out
     * @return int the status
     */
    abstract protected function main(array $args, OutputInterface $out, OutputInterface $err): int;

    /** Write a text as it is. */
    protected static function say(OutputInterface $to, string $text): void
    {
        $to->write($text, false, OutputInterface::OUTPUT_RAW);
    }

    protected static function ok(): int
    {
        return Command::SUCCESS;
    }
}
