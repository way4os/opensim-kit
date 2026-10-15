<?php

declare(strict_types=1);

/**
 * At the start of the setup: the core of the install can run, or what is missing is said and, as root, installed.
 */

use OpenSim\Provisioning\UiInterface;
use OpenSim\Runtime\RuntimeProbe;
use OpenSimKit\Setup\RuntimeCheck;

/** An interface that keeps what it was told and agrees to every question. */
final class RuntimeUiFake implements UiInterface
{
    use \OpenSimKit\Ui\AsksForms;

    /** @var list<string> */
    public array $notes = [];

    public function intro(string $title): void {}
    public function note(string $message): void { $this->notes[] = $message; }
    public function entity(string $name): string { return $name; }
    public function warn(string $message): void { $this->notes[] = "warn: $message"; }
    public function error(string $message): void { $this->notes[] = "error: $message"; }
    public function outro(string $message): void {}
    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string { return $default ?? ''; }
    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array { return $defaults; }
    public function text(string $label, string $default = '', ?Closure $validate = null, ?string $hint = null): string { return $default; }
    public function secret(string $label, ?Closure $validate = null, ?string $hint = null): string { return ''; }
    public function confirm(string $label, bool $default = true): bool { return $default; }
    public function spin(Closure $callback, string $message): mixed { return $callback(); }
}

/** A core of OpenSim 0.9.3 in a folder, which asks for .NET 8. */
function runtime_core(): string
{
    $dir = test_tmp() . '/runtime-core-' . bin2hex(random_bytes(4));
    mkdir("$dir/bin", 0o755, true);
    touch("$dir/bin/OpenSim.dll");
    file_put_contents("$dir/bin/OpenSim.runtimeconfig.json", '{"runtimeOptions":{"framework":{"name":"Microsoft.NETCore.App","version":"8.0.0"}}}');

    return $dir;
}

/** A machine that has these runtimes of dotnet (none: no dotnet at all). */
function runtime_probe(string $runtimes = ''): RuntimeProbe
{
    return new RuntimeProbe(static fn(string $command): string => match (true) {
        str_contains($command, 'command -v') => $runtimes === '' ? '' : '/usr/bin/dotnet',
        str_contains($command, '--list-runtimes') => $runtimes,
        default => '',
    });
}

describe('runtime of the core at the start of the setup', function () {
    test('is nothing to say when the machine has it, a newer one included', function () {
        $ui = new RuntimeUiFake();
        $installed = [];
        $check = new RuntimeCheck($ui, runtime_probe("Microsoft.NETCore.App 9.0.1 [/x]\n"), true, static function (int $major) use (&$installed): bool { $installed[] = $major; return true; });

        expect($check->ensure(runtime_core()))->toBeTrue()->and($ui->notes)->toBe([])->and($installed)->toBe([]);
    });

    test('is nothing to check before there is a core', function () {
        $ui = new RuntimeUiFake();

        expect((new RuntimeCheck($ui, runtime_probe(), true))->ensure('/nonexistent'))->toBeTrue()
            ->and((new RuntimeCheck($ui, runtime_probe(), true))->ensure(''))->toBeTrue();
    });

    test('is installed by root who is asked, the major version the core asks for', function () {
        $ui = new RuntimeUiFake();
        $installed = [];

        $ok = (new RuntimeCheck($ui, runtime_probe(), true, static function (int $major) use (&$installed): bool { $installed[] = $major; return true; }))->ensure(runtime_core());

        expect($ok)->toBeTrue()->and($installed)->toBe([8])->and($ui->notes[0])->toContain('warn: dotnet is not installed')->toContain('sudo opensim install-dotnet 8');
    });

    test('is only said to a person who is not root, with the command to run', function () {
        $ui = new RuntimeUiFake();
        $installed = [];

        $ok = (new RuntimeCheck($ui, runtime_probe("Microsoft.NETCore.App 6.0.1 [/x]\n"), false, static function (int $major) use (&$installed): bool { $installed[] = $major; return true; }))->ensure(runtime_core());

        expect($ok)->toBeFalse()->and($installed)->toBe([])->and($ui->notes[0])->toContain('needs Microsoft.NETCore.App 8 or newer')->toContain('sudo opensim install-dotnet 8');
    });
});
