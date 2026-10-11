<?php

declare(strict_types=1);

namespace OpenSimKit\Console;

use OpenSimKit\Console\Commands\PortMappingCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * The command line of the kit: `opensim <command> ...`. A command gets its arguments, calls the engine (`OpenSim\`), prints
 * the result: no logic of its own, so that the administration and the plugins do the same through the same classes.
 *
 * The commands that are not ported yet are done by the legacy script, which is handed the command line as it was typed
 * (see LegacyScript): the application only takes what it knows.
 */
final class Application extends ConsoleApplication
{
    /** @var array<string, true> names and aliases of the commands of the application, which are not the ones symfony adds (help, list) */
    private array $ported = [];

    public function __construct(
        private readonly string $root,
        private readonly ?LegacyScript $legacy = null,
    ) {
        parent::__construct('opensim', self::version($root));
        $this->setAutoExit(false);
        $this->setCatchExceptions(true);
        foreach (self::commands() as $command) {
            $this->addCommand($command);
            foreach ([$command->getName(), ...$command->getAliases()] as $name) {
                $this->ported[(string) $name] = true;
            }
        }
    }

    /** @return list<Command> the commands ported to the application */
    public static function commands(): array
    {
        return [new PortMappingCommand()];
    }

    /**
     * Run the command line: the application when the command is one of its own, else the legacy script, which also
     * answers `help` and `list` until it has nothing left to do.
     *
     * @param list<string> $argv the command line, the name of the program first
     */
    public function dispatch(array $argv): int
    {
        $name = self::commandName($argv);
        if ($name !== null && isset($this->ported[$name])) {
            return $this->run(new ArgvInput($argv));
        }

        return ($this->legacy ?? new LegacyScript($this->root . '/libexec/opensim-legacy'))->run(array_slice($argv, 1));
    }

    /**
     * The command of a command line: the first argument that is not an option (`opensim -v start vonda` is start).
     *
     * @param list<string> $argv
     */
    public static function commandName(array $argv): ?string
    {
        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--') {
                return null;
            }
            if ($argument !== '' && $argument[0] !== '-') {
                return $argument;
            }
        }

        return null;
    }

    /** The version of the kit: the one of its release, else the one in progress. */
    private static function version(string $root): string
    {
        foreach (["$root/.version", "$root/VERSION"] as $file) {
            if (is_file($file) && ($version = trim((string) file_get_contents($file))) !== '') {
                return $version;
            }
        }

        return 'dev';
    }
}
