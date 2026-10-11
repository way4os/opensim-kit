<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Console\RestConsole;
use OpenSim\OpenSim;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim command <instance> <text>|-: send commands to the console of a running instance, the given text or each line of the
 * standard input (an empty line answers a prompt with its default). Through its remote console when it has one, else its
 * screen session.
 */
final class CommandCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('command')
            ->setDescription('Send commands to the console of a running instance')
            ->addArgument('instance', InputArgument::REQUIRED, 'the instance (a simulator, the Robust of a grid by its name)')
            ->addArgument('text', InputArgument::IS_ARRAY, 'the command, or - for each line of the standard input (-- before a text that starts with a dash)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $engine = $this->engine($output);
        $name = (string) $input->getArgument('instance');
        $instance = OpenSim::instance($engine, $name);
        $text = (array) $input->getArgument('text');
        if ($instance === null) {
            $engine->reporter->error("no ini file found for $name");

            return Command::FAILURE;
        }
        $console = $instance->console();
        $stdin = $text === ['-'];
        $lines = $stdin ? $this->standardInput() : [implode(' ', $text)];

        if ($console instanceof RestConsole) {
            $answer = $console->lines($lines);
            if ($answer === null) {
                $engine->reporter->error("{$instance->name()} is not running");

                return Command::FAILURE;
            }
            foreach ($answer as $line) {
                $output->writeln($line, OutputInterface::OUTPUT_RAW);
            }

            return Command::SUCCESS;
        }
        if (!$console->available()) {
            $engine->reporter->error("{$instance->name()} is not running");

            return Command::FAILURE;
        }
        foreach ($lines as $line) {
            $console->send($line);
            $stdin && $engine->clock->sleep(1);
        }

        return Command::SUCCESS;
    }

    /** @return list<string> the lines of the standard input */
    private function standardInput(): array
    {
        $lines = [];
        while (($line = fgets(STDIN)) !== false) {
            $lines[] = rtrim($line, "\r\n");
        }

        return $lines;
    }
}
