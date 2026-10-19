<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Api\Api;
use OpenSim\Api\Cli;
use OpenSim\Api\Router;
use OpenSim\Scheduling\Scheduler;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim api: the API of the engine on the command line, the same addresses and answers as the web (see the contract,
 * docs/api/ of the engine).
 *
 *   opensim api [GET|POST|PATCH|DELETE] PATH [key=value...] [--data=JSON|@FILE|-] [--grid=NICK]
 *
 *   opensim api GET /grids/demo/monitor format=text
 *   opensim api PATCH /simulators/alpha enabled=false
 *   opensim api --grid=demo GET /monitor
 */
final class ApiCommand extends ScriptCommand
{
    protected const USAGE = Cli::USAGE . "       --grid=NICK    the grid the addresses without /grids/{grid} are about\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('api')->setDescription('Call the API of the engine: opensim api GET /grids/demo/monitor');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $grid = null;
        foreach ($args as $index => $arg) {
            if (str_starts_with($arg, '--grid=')) {
                $grid = substr($arg, 7);
                unset($args[$index]);
            }
        }
        $environment = $this->engine($out);
        $api = new Api($environment, Scheduler::for($environment));
        $router = new Router($api, grid: $grid);
        [$status, $output, $error] = (new Cli($router))->run(array_values($args), static fn(): string => (string) stream_get_contents(STDIN));
        self::say($out, $output);
        self::say($err, $error);
        $router->terminate();

        return $status;
    }
}
