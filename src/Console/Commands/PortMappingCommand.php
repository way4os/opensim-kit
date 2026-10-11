<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSimKit\Setup\InstallConfig;
use OpenSim\Network\PortMapping\PortMappingService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim portmapping start|stop <instance> <config file>: the port mapping of an instance, called by the launcher before
 * an instance starts and after it stops. Not for the administrator: the engine does it (see PortMappingService), and this
 * is until the launcher is PHP. Always succeeds: an instance starts, and stops, whatever happens to its mapping.
 */
final class PortMappingCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('portmapping')
            ->setDescription('Map the ports of an instance before it starts, close them after it stops')
            ->setHidden(true)
            ->addArgument('action', InputArgument::REQUIRED, 'start or stop')
            ->addArgument('instance', InputArgument::REQUIRED, 'the name of the instance')
            ->addArgument('ini', InputArgument::REQUIRED, 'the config file of the instance');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = (string) $input->getArgument('action');
        $ini = (string) $input->getArgument('ini');
        if (!in_array($action, ['start', 'stop'], true) || !is_file($ini)) {
            $output->writeln('<error>usage: opensim portmapping start|stop <instance> <config file></error>');

            return Command::INVALID;
        }

        $profile = (new InstallConfig())->profile();
        $var = ($profile['VarRoot'] ?? '') !== '' ? $profile['VarRoot'] : ($profile['DataRoot'] ?? sys_get_temp_dir());
        $error = $output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $service = new PortMappingService(
            "$var/portmap",
            // As the launcher says it: "opensim: <message>", on the error output for a problem
            static fn(string $message, bool $problem) => ($problem ? $error : $output)->writeln("opensim: $message", OutputInterface::OUTPUT_RAW),
        );
        $action === 'start'
            ? $service->start((string) $input->getArgument('instance'), $ini)
            : $service->stop((string) $input->getArgument('instance'), $ini);

        return Command::SUCCESS;
    }
}
