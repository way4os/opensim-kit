<?php

use OpenSimKit\Ui\Back;
use OpenSimKit\Ui\Quit;
use OpenSimKit\Ui\WebUi;
use OpenSimKit\Web\Mailbox;

require_once __DIR__ . '/web-browser-lib.php';

afterEach(fn() => webCleanUp());

describe('single questions', function () {
    test('choose gives the key chosen and asks again for one that is not there', function () {
        [$ui] = webSetup([['values' => ['value' => 'nope']], ['values' => ['value' => 'b']]], $screens);

        expect($ui->choose('Which', ['a' => 'A', 'b' => 'B'], 'a'))->toBe('b')
            ->and($screens)->toHaveCount(2)
            ->and($screens[0]['default'])->toBe('a');
    });

    test('confirm and checklist keep to the options', function () {
        [$ui] = webSetup([['values' => ['value' => 'no']], ['values' => ['value' => ['', 'b', 'zz']]]]);

        expect($ui->confirm('Sure?'))->toBeFalse()
            ->and($ui->checklist('Some', ['a' => 'A', 'b' => 'B']))->toBe(['b']);
    });

    test('a text that is refused is asked again with the message and what was typed', function () {
        [$ui] = webSetup([['values' => ['value' => ' ab ']], ['values' => ['value' => 'abcd']]], $screens);

        $value = $ui->text('Name', 'x', static fn(string $v): ?string => strlen($v) < 3 ? 'Too short' : null);

        expect($value)->toBe('abcd')
            ->and($screens[1]['error'])->toBe('Too short')
            ->and($screens[1]['value'])->toBe('ab');
    });

    test('a secret is kept as typed', function () {
        [$ui] = webSetup([['values' => ['value' => ' pa ss ']]]);

        expect($ui->secret('Password'))->toBe(' pa ss ');
    });

    test('Back and Quit end the screen as Escape and Ctrl-Q do', function () {
        [$ui] = webSetup([['action' => 'back'], ['action' => 'quit']]);

        expect(fn() => $ui->text('One'))->toThrow(Back::class)
            ->and(fn() => $ui->text('Two'))->toThrow(Quit::class);
    });

    test('nobody answering is an error, not a wait for ever', function () {
        $ui = new WebUi(webMailbox(), null, 0, 0.05);

        expect(fn() => $ui->text('Alone'))->toThrow(RuntimeException::class);
    });

    test('answer is not kept once it is read', function () {
        [$ui, $mailbox] = webSetup([['values' => ['value' => 'secret']]]);
        $ui->text('Name');

        expect(file_exists("{$mailbox->dir}/answer.json"))->toBeFalse();
    });
});

describe('forms', function () {
    $fields = [
        ['key' => 'name', 'label' => 'Name', 'default' => 'My grid', 'validate' => static fn(string $v): ?string => str_contains($v, '!') ? 'No bang' : null],
        ['key' => 'secret', 'label' => 'Password', 'type' => 'secret', 'default' => 'made-for-you'],
        ['key' => 'mode', 'label' => 'Mode', 'type' => 'confirm', 'default' => 'yes'],
        ['key' => 'extra', 'label' => 'Extra', 'condition' => ['mode' => ['yes']], 'when' => static fn(array $v): bool => ($v['mode'] ?? '') === 'yes'],
    ];

    test('screen has the fields, without the password that is proposed', function () use ($fields) {
        [$ui, $mailbox] = webSetup([['values' => ['name' => 'Home', 'secret' => '', 'mode' => 'yes', 'extra' => 'more']]], $screens);

        $values = $ui->form($fields, 'Grid');

        expect($values)->toBe(['name' => 'Home', 'secret' => 'made-for-you', 'mode' => 'yes', 'extra' => 'more'])
            ->and(json_encode($screens))->not->toContain('made-for-you')
            ->and($screens[0]['fields'][1]['hasDefault'])->toBeTrue()
            ->and($screens[0]['fields'][3]['condition'])->toBe(['mode' => ['yes']]);
    });

    test('what is wrong is shown on its field and the values typed are kept', function () use ($fields) {
        [$ui] = webSetup([
            ['values' => ['name' => 'Bad!', 'secret' => 'x', 'mode' => 'yes', 'extra' => '']],
            ['values' => ['name' => 'Good', 'secret' => 'x', 'mode' => 'no']],
        ], $screens);

        $values = $ui->form($fields, 'Grid');

        expect($screens[1]['fields'][0]['error'])->toBe('No bang')
            ->and($screens[1]['fields'][0]['value'])->toBe('Bad!')
            ->and($screens[1]['fields'][3]['error'])->toBe('This field is required.')
            ->and($values)->toBe(['name' => 'Good', 'secret' => 'x', 'mode' => 'no', 'extra' => '']);
    });

    test('a field that the other answers made appear is shown before it is asked for', function () {
        $fields = [
            ['key' => 'kind', 'label' => 'Kind', 'type' => 'choice', 'options' => ['a' => 'A', 'b' => 'B'], 'default' => 'a'],
            ['key' => 'more', 'label' => 'More', 'when' => static fn(array $v): bool => ($v['kind'] ?? '') === 'b'],
        ];
        [$ui] = webSetup([
            ['values' => ['kind' => 'b']],
            ['values' => ['kind' => 'b', 'more' => 'yes']],
        ], $screens);

        $values = $ui->form($fields);

        expect($screens[0]['fields'][1]['rendered'])->toBeFalse()
            ->and($screens[1]['fields'][1]['rendered'])->toBeTrue()
            ->and($values)->toBe(['kind' => 'b', 'more' => 'yes']);
    });

    test('a field counts the value another one has, even when that one is not asked', function () {
        // As the questions of NewGrid::complete(): the plan is accepted already, so 'apply' is not asked, but 'enable' follows it
        $accepted = true;
        $fields = [
            ['key' => 'apply', 'label' => 'Apply', 'type' => 'confirm', 'default' => 'yes', 'when' => static fn(array $v): bool => !$accepted],
            ['key' => 'enable', 'label' => 'Enable', 'type' => 'confirm', 'default' => 'yes', 'when' => static fn(array $v): bool => ($v['apply'] ?? 'yes') === 'yes', 'condition' => ['apply' => ['yes']]],
        ];
        [$ui] = webSetup([['values' => ['enable' => 'no']]], $screens);

        $values = $ui->form($fields, 'Apply');

        expect($screens[0]['fields'][0]['shown'])->toBeFalse()
            ->and($screens[0]['fields'][1]['shown'])->toBeTrue()
            ->and($screens[0]['fields'][1]['condition'])->toBeNull() // the field it follows is not on the page
            ->and($values)->toBe(['apply' => '', 'enable' => 'no']);
    });

    test('a checklist posted empty is empty', function () {
        $fields = [['key' => 'some', 'label' => 'Some', 'type' => 'checklist', 'options' => ['a' => 'A', 'b' => 'B'], 'default' => 'a']];
        [$ui] = webSetup([['values' => ['some' => ['']]]]);

        expect($ui->form($fields))->toBe(['some' => '']);
    });
});

describe('what is said', function () {
    test('notes, warnings and errors are kept in order, after the screen they follow', function () {
        [$ui, $mailbox] = webSetup([['values' => ['value' => 'x']]]);
        $ui->note('first');
        $ui->text('Question');
        $ui->warn('second');
        $ui->error('third');

        expect($mailbox->log())->toBe([
            ['after' => 0, 'level' => 'note', 'message' => 'first'],
            ['after' => 1, 'level' => 'warning', 'message' => 'second'],
            ['after' => 1, 'level' => 'error', 'message' => 'third'],
        ]);
    });

    test('what a program says is shown line by line without its colours', function () {
        [$ui, $mailbox] = webSetup([]);
        $ui->stream("\e[32mdone\e[0m\r\nnext\r\n\r\n");

        expect(array_column($mailbox->log(), 'message'))->toBe(['done', 'next']);
    });

    test('a line a program rewrites is the progress, it is a line of the log when it ends', function () {
        [$ui, $mailbox] = webSetup([]);

        $ui->stream("10%\r20%\r30%");
        $partial = $mailbox->progressLine();
        $ui->stream("\r100%\nnext");

        expect($partial)->toBe('30%')
            ->and(array_column($mailbox->log(), 'message'))->toBe(['100%'])
            ->and($mailbox->progressLine())->toBe('next');
    });

    test('what a program left unfinished is a line when the setup goes on', function () {
        [$ui, $mailbox] = webSetup([['values' => ['value' => 'x']]]);

        $ui->stream('Password:');
        $ui->text('Question');

        expect(array_column($mailbox->log(), 'message'))->toBe(['Password:'])
            ->and($mailbox->progressLine())->toBe('');
    });

    test('work being done is a screen, and its result is returned', function () {
        [$ui, $mailbox] = webSetup([]);

        $result = $ui->spin(static fn(): int => 42, 'Downloading');

        expect($result)->toBe(42)
            ->and($mailbox->screen()['kind'])->toBe('working')
            ->and($mailbox->screen()['message'])->toBe('Downloading');
    });

    test('end of the setup is a screen', function () {
        [$ui, $mailbox] = webSetup([]);
        $ui->outro('All done');

        expect($mailbox->screen())->toMatchArray(['kind' => 'done', 'message' => 'All done']);
    });

    test('an entity is marked, to be shown in bold', function () {
        [$ui] = webSetup([]);

        expect($ui->entity('home'))->toBe("\u{E000}home\u{E001}");
    });
});

describe('what the programs of the setup say', function () {
    test('goes to the page, also when the interface is answered from a table in front of it', function () {
        [$ui] = webSetup([]);
        $preset = new \OpenSimKit\Ui\PresetUi(new \OpenSimKit\Ui\PresetUi($ui, []), []);

        expect(\OpenSimKit\Ui\Captured::by($preset))->toBe($ui)
            ->and(\OpenSimKit\Ui\Captured::by(new \OpenSimKit\Ui\PresetUi(new \OpenSimKit\Ui\QuietUi(), [])))->toBeNull();
    });
});
