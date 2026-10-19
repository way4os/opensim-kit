<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Runtime\Environment;
use OpenSim\Runtime\InstanceResult;
use OpenSimKit\Console\ConsoleReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command that asks the engine for something on this machine: it parses its arguments, calls the engine, prints the result.
 * The engine says what it does through a reporter on the error output.
 */
abstract class EngineCommand extends Command
{
    /**
     * @param \Closure(\OpenSim\Runtime\ReporterInterface):Environment $environment makes the environment of the engine for a reporter
     */
    public function __construct(private readonly \Closure $environment)
    {
        parent::__construct();
    }

    /** The environment of the engine, saying what it does on the error output. */
    protected function engine(OutputInterface $output): Environment
    {
        return ($this->environment)(new ConsoleReporter($output, debug: in_array(strtolower((string) getenv('DEBUG')), ['yes', 'true', '1'], true)));
    }

    /**
     * The status of the command for a list of results: a failure when one instance failed (one still loading did not).
     *
     * @param list<InstanceResult> $results
     */
    protected static function status(array $results): int
    {
        foreach ($results as $result) {
            if (!$result->ok()) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * `now` is the first of the targets of stop and restart: the users are not warned.
     *
     * @param list<string> $targets
     * @return array{0:bool,1:list<string>}
     */
    protected static function splitNow(array $targets): array
    {
        $now = ($targets[0] ?? '') === 'now';

        return [$now, $now ? array_slice($targets, 1) : $targets];
    }
}
