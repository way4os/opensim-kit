<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Models\Grid;
use OpenSim\Controllers\OpenSimController;
use OpenSim\Controllers\RobustController;
use OpenSim\Provisioning\Slug;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim ports: the ports the enabled instances listen on, with what each one is for and who has to reach it (see
 * the ports of OpenSim\Controllers\AbstractController).
 *
 *   opensim ports [instance...]               the table
 *   opensim ports --publish [instance...]     the -p options of podman or docker
 *   opensim ports --ufw [instance...]         the rules of the firewall
 *   --private-address=ADDRESS                 where the private and console ports are published (default 127.0.0.1)
 */
final class PortsCommand extends ScriptCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('ports')->setDescription('The ports the enabled instances listen on, and who has to reach them');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $mode = 'table';
        $address = '127.0.0.1';
        $names = [];
        foreach ($args as $arg) {
            if ($arg === '--publish' || $arg === '--ufw') {
                $mode = substr($arg, 2);
            } elseif (str_starts_with($arg, '--private-address=')) {
                $address = substr($arg, strlen('--private-address='));
            } else {
                $names[] = Slug::snake($arg);
            }
        }

        $engine = $this->engine($out);
        $etc = $engine->layout->etcRoot();
        $instances = [];
        foreach (['robust' => 'robust.d', 'opensim' => 'opensim.d'] as $type => $folder) {
            foreach (glob("$etc/$folder/*.ini") ?: [] as $ini) {
                $name = Slug::snake(basename($ini, '.ini'));
                if ($names === [] || in_array($name, $names, true)) {
                    $instances[] = [$name, $type, $ini];
                }
            }
        }
        if ($instances === []) {
            self::say($err, 'ports: no enabled instance' . ($names !== [] ? ' of that name' : '') . "\n");

            return 1;
        }

        $rows = [];
        foreach ($instances as [$name, $type, $ini]) {
            $controller = $type === 'robust' ? new RobustController($engine, $ini) : new OpenSimController($engine, $ini);
            foreach ($controller->ports() as $use) {
                $rows[] = $use + ['instance' => $name, 'type' => $type];
            }
        }

        switch ($mode) {
            case 'publish':
                // Public ports for everyone; private and console ones only where they are asked for: they have no protection
                // of their own
                $options = [];
                foreach ($rows as $row) {
                    if ($row['use'] === 'off') {
                        continue;
                    }
                    $bind = $row['use'] === 'public' ? '' : "$address:";
                    $options[] = "-p $bind{$row['port']}:{$row['port']}" . ($row['proto'] === 'udp' ? '/udp' : '');
                }
                self::say($out, implode(' ', array_unique($options)) . "\n");

                break;

            case 'ufw':
                foreach ($rows as $row) {
                    if ($row['use'] === 'off') {
                        continue;
                    }
                    self::say($out, $row['use'] === 'public'
                        ? "ufw allow {$row['port']}/{$row['proto']}    # {$row['instance']}: {$row['role']}\n"
                        : "# {$row['port']}/{$row['proto']} ({$row['instance']}: {$row['role']}): only from the machines that need it, e.g. ufw allow from <address> to any port {$row['port']} proto {$row['proto']}\n");
                }

                break;

            default:
                $line = static fn(array $cells): string => sprintf('%-22s %-8s %-6s %-5s %-8s %s', ...$cells);
                self::say($out, $line(['INSTANCE', 'TYPE', 'PORT', 'PROTO', 'USE', 'FOR']) . "\n");
                foreach ($rows as $row) {
                    self::say($out, $line([$row['instance'], $row['type'], $row['port'], $row['proto'], $row['use'], $row['role']]) . "\n");
                }
        }

        return self::ok();
    }
}
