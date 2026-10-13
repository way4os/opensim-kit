<?php

use OpenSimKit\Setup\QuickSetup;
use OpenSimKit\Ui\AsksForms;
use OpenSimKit\Ui\PresetUi;
use OpenSim\Provisioning\UiInterface;

/** An interface that answers what is asked of it, and keeps the questions. */

final class AskingUi implements UiInterface
{
    use AsksForms;

    public array $asked = [];

    public function __construct(private array $answers) {}

    public function intro(string $title): void {}
    public function note(string $message): void {}
    public function entity(string $name): string
    {
        return $name;
    }
    public function warn(string $message): void {}
    public function error(string $message): void {}
    public function outro(string $message): void {}
    public function spin(\Closure $callback, string $message): mixed
    {
        return $callback();
    }

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        $this->asked[] = [$label, $default, array_keys($options)];

        return (string) array_shift($this->answers);
    }

    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array
    {
        return $defaults;
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        $this->asked[] = [$label, $default];

        return (string) (array_shift($this->answers) ?? $default);
    }

    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string
    {
        return '';
    }

    public function confirm(string $label, bool $default = true): bool
    {
        $this->asked[] = [$label, $default];

        return (bool) array_shift($this->answers);
    }
}

function askingUi(array $answers): AskingUi
{
    return new AskingUi($answers);
}

test('does not ask the plan of a setup from a file', function () {
    $inner = askingUi([]);
    $ui = new PresetUi($inner, ['Apply this configuration' => true]);

    expect($ui->confirm("Apply this configuration to grid 'x'?", false))->toBeTrue()->and($inner->asked)->toBe([]);
});

test('asks every question to edit a setup', function () {
    $inner = askingUi([null, null]);
    $ui = new PresetUi($inner, ['Grid name' => 'Vonda'], [], true);

    expect($ui->text('Grid name', 'Other'))
        ->toBe('Vonda')
        ->and($inner->asked[0])
        ->toBe(['Grid name', 'Vonda'])
        ->and($ui->text('Region name', 'Welcome'))
        ->toBe('Welcome')
        ->and($inner->asked[1])
        ->toBe(['Region name', 'Welcome']);
});

test('asks the database again after a failure', function () {
    $inner = askingUi(['other']);
    $ui = new PresetUi($inner, []);

    expect($ui->text('Database user', 'opensim'))->toBe('opensim')->and($inner->asked)->toBe([]);
    $ui->error('Access denied');
    expect($ui->text('Database user', 'opensim'))->toBe('other');
});

test('reads a login URI as host:port, with or without the scheme', function () {
    expect(QuickSetup::login('play.example.org:8002'))
        ->toBe(['play.example.org', 8002])
        ->and(QuickSetup::login(' http://vonda:8012/ '))
        ->toBe(['vonda', 8012])
        ->and(QuickSetup::login('play.example.org'))
        ->toBeNull()
        ->and(QuickSetup::login('host:99999'))
        ->toBeNull();
});

test('answers what is also to be asked', function () {
    // "Password of " asks the password of a database administrator; the password of the new account is not that
    $inner = askingUi([]);
    $ui = new PresetUi($inner, ['Password of the new account' => 'hunter22'], ['Password of ']);

    expect($ui->secret('Password of the new account'))->toBe('hunter22')->and($inner->asked)->toBe([]);
});

test('answers a form field by field', function () {
    $ui = new PresetUi(askingUi([]), ['Console of' => 'screen', 'Start it' => false]);
    $v = $ui->form([
        [
            'key' => 'mode',
            'label' => 'Console of the thing',
            'type' => 'choice',
            'options' => ['rest' => 'REST', 'screen' => 'Screen'],
            'default' => 'rest',
        ],
        [
            'key' => 'port',
            'label' => 'Console port',
            'default' => '9004',
            'when' => fn(array $v) => $v['mode'] === 'rest',
        ],
        ['key' => 'start', 'label' => 'Start it now', 'type' => 'confirm', 'default' => 'yes'],
        [
            'key' => 'roles',
            'label' => 'Roles',
            'type' => 'checklist',
            'options' => ['a' => 'A', 'b' => 'B'],
            'default' => 'a,b',
        ],
    ]);

    expect($v)->toBe(['mode' => 'screen', 'port' => '', 'start' => 'no', 'roles' => 'a,b']);
});

/** An interface that shows each form as one screen, and keeps what it was given. */
final class ScreenUi implements UiInterface
{
    /** @var list<array{0:string,1:list<string>}> the title and the labels of each form shown */
    public array $screens = [];

    public function intro(string $title): void {}
    public function note(string $message): void {}
    public function entity(string $name): string { return $name; }
    public function warn(string $message): void {}
    public function error(string $message): void {}
    public function outro(string $message): void {}
    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string { return (string) $default; }
    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array { return $defaults; }
    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string { return $default; }
    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string { return ''; }
    public function confirm(string $label, bool $default = true): bool { return $default; }
    public function spin(\Closure $callback, string $message): mixed { return $callback(); }
    public function form(array $fields, string $title = ''): array
    {
        $this->screens[] = [$title, array_column($fields, 'label')];

        return array_column(array_map(static fn(array $f): array => ['k' => $f['key'], 'v' => (string) ($f['default'] ?? '')], $fields), 'v', 'k');
    }
}

describe('the settings of the database asked again after a failure', function () {
    $database = [
        ['key' => 'database_host', 'label' => 'Database host', 'default' => 'localhost'],
        ['key' => 'database_user', 'label' => 'Database user', 'default' => 'opensim'],
        ['key' => 'database_password', 'label' => 'Database password', 'default' => 'typed'],
    ];

    test('are one screen, not a question for each field', function () use ($database) {
        $inner = new ScreenUi();
        $ui = new PresetUi($inner, []);

        $first = $ui->form($database, 'Database');
        $ui->error('User exists but the password is rejected');
        $again = $ui->form($database, 'Database');

        // The first time the table answers by itself, after the failure the person is asked, once, with everything
        expect($first)->toBe(['database_host' => 'localhost', 'database_user' => 'opensim', 'database_password' => 'typed'])
            ->and($again)->toBe($first)
            ->and($inner->screens)->toBe([['Database', ['Database host', 'Database user', 'Database password']]]);
    });

    test('and the forms after it are answered from the table again', function () use ($database) {
        $inner = new ScreenUi();
        $ui = new PresetUi($inner, []);
        $ui->error('failed');
        $ui->form($database, 'Database');
        $ui->form($database, 'Database');

        expect($inner->screens)->toHaveCount(1);
    });
});
