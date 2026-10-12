<?php

declare(strict_types=1);

namespace OpenSimKit\Setup;

use OpenSim\Models\Core;
use OpenSim\Provisioning\UiInterface;
use OpenSim\Runtime\DotnetInstaller;
use OpenSim\Runtime\RuntimeProbe;

/**
 * At the start of the setup: can the core of the install run on this machine? The packages of the releases bring their .NET runtime
 * where the system has one for them (Ubuntu), else it is missing, and a grid made now could not start. Says what is missing, and
 * installs it when this process may (root); otherwise says the command to run.
 */
final class RuntimeCheck
{
    /**
     * @param ?RuntimeProbe $probe how the machine is looked at (default: the shell)
     * @param ?bool $root whether this process is root (default: it is told)
     * @param ?\Closure(int):bool $install installs the .NET runtime of a major version (default: DotnetInstaller)
     */
    public function __construct(
        private readonly UiInterface $ui,
        private readonly ?RuntimeProbe $probe = null,
        private readonly ?bool $root = null,
        private readonly ?\Closure $install = null,
    ) {
    }

    /**
     * @param ?string $coreDirectory the core to check (default: the one of the default install profile)
     * @return bool whether the core can run, once what could be installed was
     */
    public function ensure(?string $coreDirectory = null): bool
    {
        $directory = $coreDirectory ?? (new InstallConfig())->profile()['CoreDirectory'] ?? '';
        // No core yet: the setup installs it, and checks then
        $core = $directory !== '' && is_dir($directory) ? Core::at($directory) : null;
        if ($core === null || !$core->exists()) {
            return true;
        }

        $assembly = $core->executable('OpenSim');
        $problem = ($this->probe ?? new RuntimeProbe())->problem($core, $assembly);
        if ($problem === null) {
            return true;
        }
        $this->ui->warn($problem);
        if ($core->runtime('OpenSim') !== 'dotnet' || !$this->isRoot()) {
            return false;
        }

        $major = (int) explode('.', $core->dotnetRequired($assembly))[0];
        if (!$this->ui->confirm(sprintf(_('Install the .NET %d runtime now?'), $major), true)) {
            return false;
        }

        return $this->install !== null ? ($this->install)($major) : (new DotnetInstaller($this->ui))->install($major);
    }

    private function isRoot(): bool
    {
        return $this->root ?? (function_exists('posix_geteuid') && posix_geteuid() === 0);
    }
}
