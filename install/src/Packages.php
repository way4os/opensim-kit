<?php

namespace OpenSim\Installer;

use OpenSim\Provisioning\UiInterface;
use OpenSim\Runtime\ShellService;

/**
 * OS package manager abstraction (brew on macOS, apt on Debian/Ubuntu).
 */
final class Packages
{
    public function __construct(private UiInterface $ui) {}

    /** Install packages; returns the command exit code. */
    public function install(string ...$packages): int
    {
        $list = implode(' ', array_map([ShellService::class, 'arg'], $packages));

        return match (PHP_OS_FAMILY) {
            'Darwin' => ShellService::run("brew install $list"),
            'Linux' => ShellService::run("sudo apt-get install -y $list"),
            default => 1,
        };
    }

    /**
     * Ensure a command is available, offering to install its package if not.
     * Aborts the wizard if the user declines and it is required.
     */
    public function ensure(string $command, string $package, bool $required = true): void
    {
        if (ShellService::commandExists($command)) {
            return;
        }

        if ($this->ui->confirm(sprintf(_('%s is not installed. Install %s now?'), $command, $package), true)) {
            // Judge success by the binary appearing, not the package manager's
            // exit code (post-install warnings can make it non-zero).
            $this->install($package);
            if (!ShellService::commandExists($command)) {
                $this->ui->error(sprintf(_('Could not install %s.'), $package));
                exit(1);
            }
        } elseif ($required) {
            $this->ui->error(sprintf(_('%s is required to continue.'), $command));
            exit(1);
        }
    }
}
