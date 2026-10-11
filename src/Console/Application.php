<?php

declare(strict_types=1);

namespace OpenSimKit\Console;

use OpenSim\Config\Layout;
use OpenSim\OpenSim;
use OpenSim\Runtime\Environment;
use OpenSim\Runtime\ReporterInterface;
use OpenSimKit\Console\Commands\CommandCommand;
use OpenSimKit\Console\Commands\ConsoleCommand;
use OpenSimKit\Console\Commands\DisableCommand;
use OpenSimKit\Console\Commands\EnableCommand;
use OpenSimKit\Console\Commands\RestartCommand;
use OpenSimKit\Console\Commands\BackupCommand;
use OpenSimKit\Console\Commands\ImportCommand;
use OpenSimKit\Console\Commands\InstallDotnetCommand;
use OpenSimKit\Console\Commands\LoadCommand;
use OpenSimKit\Console\Commands\NextCommand;
use OpenSimKit\Console\Commands\OarCommand;
use OpenSimKit\Console\Commands\PortsCommand;
use OpenSimKit\Console\Commands\ProfileCommand;
use OpenSimKit\Console\Commands\RestCommand;
use OpenSimKit\Console\Commands\SaveCommand;
use OpenSimKit\Console\Commands\SetupCommand;
use OpenSimKit\Console\Commands\WebCommand;
use OpenSimKit\Console\Commands\StartCommand;
use OpenSimKit\Console\Commands\StatusCommand;
use OpenSimKit\Console\Commands\StopCommand;
use OpenSimKit\System\SystemUser;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * The command line of the kit: `opensim <command> ...`. A command gets its arguments, calls the engine (`OpenSim\`), prints
 * the result: no logic of its own, so that the administration and the plugins do the same through the same classes.
 */
final class Application extends ConsoleApplication
{
    /** The commands that work on the instances: they run as the system user of the install, whoever types them. */
    private const SYSTEM_USER_COMMANDS = ['start', 'stop', 'restart', 'status', 'command', 'console', 'screen', 'enable', 'disable', 'save', 'load', 'backup'];

    /** @var array<string, true> names and aliases of the commands of the application, which are not the ones symfony adds (help, list) */
    private array $ported = [];

    private ?Layout $layout;

    public function __construct(
        private readonly string $root,
        ?Layout $layout = null,
        private readonly ?SystemUser $systemUser = null,
        private readonly ?\Closure $environment = null,
    ) {
        parent::__construct('opensim', self::version($root));
        $this->layout = $layout;
        $this->setAutoExit(false);
        $this->setCatchExceptions(true);
        foreach (self::commands($this->environment ?? fn(ReporterInterface $reporter): Environment => OpenSim::environment($reporter, $this->layout())) as $command) {
            $this->addCommand($command);
            foreach ([$command->getName(), ...$command->getAliases()] as $name) {
                $this->ported[(string) $name] = true;
            }
        }
    }

    /**
     * @param ?\Closure(ReporterInterface):Environment $environment makes the environment of the engine, for a reporter
     * @return list<Command> the commands ported to the application
     */
    public static function commands(?\Closure $environment = null): array
    {
        $environment ??= static fn(ReporterInterface $reporter): Environment => OpenSim::environment($reporter);

        return [
            new StartCommand($environment),
            new StopCommand($environment),
            new RestartCommand($environment),
            new StatusCommand($environment),
            new CommandCommand($environment),
            new ConsoleCommand($environment),
            new EnableCommand($environment),
            new DisableCommand($environment),
            new BackupCommand($environment),
            new ImportCommand($environment),
            new InstallDotnetCommand($environment),
            new LoadCommand($environment),
            new NextCommand($environment),
            new OarCommand($environment),
            new PortsCommand($environment),
            new ProfileCommand($environment),
            new RestCommand($environment),
            new SaveCommand($environment),
            new SetupCommand($environment),
            new WebCommand($environment),
        ];
    }

    /** How the install is laid out: its `opensim.conf`, else the folders next to the kit. */
    public function layout(): Layout
    {
        return $this->layout ??= Layout::load(null, ['EtcRoot' => $this->root . '/etc', 'CoreRoot' => $this->root . '/core']);
    }

    /**
     * Run the command line: the help of the program, or the command it names.
     *
     * @param list<string> $argv the command line, the name of the program first
     */
    public function dispatch(array $argv): int
    {
        $name = self::commandName($argv);
        // The help of the whole program, or of one command (`opensim help start` is `opensim start --help`)
        if ($name === null || $name === 'help' || $name === 'list') {
            $rest = $name === null ? [] : array_values(array_filter(array_slice($argv, 1), static fn(string $a): bool => $a !== '' && $a[0] !== '-' && $a !== $name));
            if ($name !== null && $rest !== []) {
                return $this->dispatch([$argv[0], $rest[0], '--help']);
            }
            if (in_array('-V', $argv, true) || in_array('--version', $argv, true)) {
                echo 'opensim ' . $this->getVersion() . "\n";

                return 0;
            }
            echo Help::text();

            return 0;
        }
        if (isset($this->ported[$name])) {
            if (in_array($name, self::SYSTEM_USER_COMMANDS, true)) {
                // Screen sessions are per user: the commands work as the user that runs the instances
                $status = ($this->systemUser ?? new SystemUser())->run($this->layout()->systemUser(), [PHP_BINARY, $this->root . '/bin/opensim', ...array_slice($argv, 1)]);
                if ($status !== null) {
                    return $status;
                }
            }

            return $this->run(new ArgvInput($argv));
        }

        fwrite(STDERR, "opensim: unknown command: $name (see 'opensim help')\n");

        return 1;
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
