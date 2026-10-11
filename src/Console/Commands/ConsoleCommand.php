<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Console\RestConsole;
use OpenSim\Console\ScreenInterface;
use OpenSim\OpenSim;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim console <instance>: attach to the console of a running instance, its screen session, or, for an instance with a
 * remote console, a prompt sending each line to it.
 */
final class ConsoleCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('console')
            ->setAliases(['screen'])
            ->setDescription('Attach to the console of a running instance')
            ->addArgument('instance', InputArgument::REQUIRED, 'the instance (a simulator, robust:<grid>)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $engine = $this->engine($output);
        $name = (string) $input->getArgument('instance');
        $instance = OpenSim::instance($engine, $name);
        if ($instance === null) {
            $engine->reporter->error("no ini file found for $name");

            return Command::FAILURE;
        }
        $screen = $engine->consoles->screen($instance->name());
        if ($screen->exists()) {
            $engine->reporter->info("console of {$instance->name()}, Ctrl-A D to leave it running");

            return $screen->attach();
        }
        // The remote console: a prompt, every line typed goes to it
        $console = $instance->console();
        $session = $console instanceof RestConsole ? $console->session() : null;
        if ($session === null) {
            $engine->reporter->error("{$instance->name()} is not running");

            return Command::FAILURE;
        }
        $engine->reporter->info("remote console of {$instance->name()}, Ctrl-D to leave it running");
        while (true) {
            $output->write($session->prompt !== '' ? $session->prompt : '> ');
            $line = fgets(STDIN);
            if ($line === false) {
                $output->writeln('');

                break;
            }
            $result = $session->command(rtrim($line, "\r\n"), RestConsole::WAIT);
            foreach ($result['lines'] as $text) {
                $output->writeln($text, OutputInterface::OUTPUT_RAW);
            }
            if ($result['closed']) {
                break;
            }
        }
        $session->close();

        return Command::SUCCESS;
    }
}
