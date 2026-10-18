<?php

use OpenSimKit\Ui\Back;
use OpenSimKit\Ui\Quit;
use OpenSimKit\Ui\ScriptUi;

/**
 * The setup asked by a script: what it tells, line by line, and the answers it takes back.
 *
 * @param list<string> $answers the lines the script writes (written beforehand: the first question is 1, the next 2...)
 * @return array{0:ScriptUi,1:\Closure():list<list<string>>,2:resource} the interface, what the engine told since (unescaped), the end of the script
 */
function script_ui(array $answers = [], bool $closed = false): array
{
    static $kept = []; // the ends stay open as long as the test: a pipe with no reader is a script that is gone
    $engine = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $script = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $kept[] = [$engine, $script];
    foreach ($answers as $line) {
        fwrite($script[1], str_replace("\t", ScriptUi::SEPARATOR, $line) . "\n"); // the tab is how the tests write it
    }
    $closed && fclose($script[1]);
    $ui = new ScriptUi($engine[0], $script[0]);
    $told = static function () use ($engine): array {
        stream_set_blocking($engine[1], false);
        $text = (string) stream_get_contents($engine[1]);

        return array_map(
            static fn(string $line): array => array_map(ScriptUi::unescape(...), explode(ScriptUi::SEPARATOR, $line)),
            array_values(array_filter(explode("\n", $text), static fn(string $l): bool => $l !== '')),
        );
    };

    return [$ui, $told, $script[1]];
}

describe('lines of the script protocol', function () {
    test('keep tabs, new lines and backslashes', function (string $text) {
        expect(ScriptUi::unescape(ScriptUi::escape($text)))->toBe($text)
            ->and(ScriptUi::escape($text))->not->toContain("\t")->not->toContain("\n")->not->toContain(ScriptUi::SEPARATOR);
    })->with([
        'plain' => ['plain text'],
        'tab' => ["a\tb"],
        'separator' => ["a" . ScriptUi::SEPARATOR . "b"],
        'lines' => ["one\ntwo\r\nthree"],
        'backslashes' => ['C:\\path\\n and \\t'],
        'password' => ["p\\a\tss\\\\word"],
    ]);

    test('start with the version, and say what the setup says', function () {
        [$ui, $told] = script_ui();

        $ui->intro('Setup');
        $ui->note("A\tnote\non two lines");
        $ui->warn('careful');
        $ui->error('oops');
        $ui->outro('bye');
        $ui->close(3);

        expect($told())->toBe([
            ['hello', '1'],
            ['intro', 'Setup'],
            ['note', "A\tnote\non two lines"],
            ['warn', 'careful'],
            ['error', 'oops'],
            ['outro', 'bye'],
            ['bye', '3'],
        ])->and($ui->entity('Alpha'))->toBe('Alpha');
    });
});

describe('questions of the script', function () {
    test('a text is asked with its default and its hint, and the answer is trimmed', function () {
        [$ui, $told] = script_ui(["answer\t1\t  Ada  "]);

        expect($ui->text('Name', 'Bob', null, 'a hint'))->toBe('Ada')
            ->and($told()[1])->toBe(['ask', '1', 'text', 'Name', 'Bob', 'a hint', '']);
    });

    test('a text that is refused is asked again with the message and what was typed', function () {
        [$ui, $told] = script_ui(["answer\t1\tab", "answer\t2\tabcd"]);

        $value = $ui->text('Code', '', static fn(string $v): ?string => strlen($v) < 3 ? 'Too short.' : null);

        expect($value)->toBe('abcd')
            ->and($told()[2])->toBe(['ask', '2', 'text', 'Code', 'ab', '', 'Too short.']);
    });

    test('a secret is kept as typed', function () {
        [$ui, $told] = script_ui(["answer\t1\t pa\\tss\\\\ "]);

        expect($ui->secret('Password'))->toBe(" pa\tss\\ ")
            ->and($told()[1][2])->toBe('secret');
    });

    test('a confirmation is yes or no', function () {
        [$ui, $told] = script_ui(["answer\t1\tno", "answer\t2\tyes"]);

        expect($ui->confirm('Sure?', true))->toBeFalse()
            ->and($ui->confirm('Really?', false))->toBeTrue()
            ->and($told()[1])->toBe(['ask', '1', 'confirm', 'Sure?', 'yes', '', ''])
            ->and($told()[2] ?? null)->toBeNull();
    });

    test('a choice is a key of the options, asked again for one that is not', function () {
        [$ui, $told] = script_ui(["answer\t1\tnope", "answer\t2\tb"]);

        expect($ui->choose('Which', ['a' => 'Option A', 'b' => 'Option B'], 'a'))->toBe('b');
        $asked = $told();
        expect($asked[1])->toBe(['ask', '1', 'choose', 'Which', 'a', '', '', 'a', 'Option A', 'b', 'Option B'])
            ->and($asked[2][1])->toBe('2');
    });

    test('a checklist gives the keys that are options', function () {
        [$ui, $told] = script_ui(["answer\t1\ta,zz,,c"]);

        expect($ui->checklist('Some', ['a' => 'A', 'b' => 'B', 'c' => 'C'], ['a', 'b']))->toBe(['a', 'c'])
            ->and($told()[1][4])->toBe('a,b');
    });

    test('an answer to another question is not the answer', function () {
        [$ui] = script_ui(["answer\t7\twrong", "answer\t1\tright"]);

        expect($ui->text('Name'))->toBe('right');
    });

    test('going back and leaving are the exceptions of the other frontends', function () {
        [$ui] = script_ui(["back\t1", "quit\t2"]);

        expect(fn() => $ui->text('Name'))->toThrow(Back::class)
            ->and(fn() => $ui->text('Name'))->toThrow(Quit::class);
    });

    test('a script that is gone ends the setup', function () {
        [$ui] = script_ui([], true);

        expect(fn() => $ui->confirm('Still there?'))->toThrow(Quit::class);
    });

    test('a form is its fields one at a time, then the review', function () {
        [$ui, $told] = script_ui(["answer\t1\tAda", "answer\t2\tok"]);

        $values = $ui->form([['key' => 'name', 'label' => 'Name']], 'Who');

        expect($values)->toBe(['name' => 'Ada']);
        $asked = $told();
        expect(array_column($asked, 0))->toBe(['hello', 'ask', 'note', 'ask'])
            ->and($asked[2][1])->toBe('Name: Ada');
    });
});

describe('what happens meanwhile', function () {
    test('the output of the programs is said line by line, without colours, a rewritten line as its last version', function () {
        [$ui, $told] = script_ui();

        $ui->stream("\e[32mgreen\e[0m line\nprogress 10%\rprogress 100%\npartial");
        $ui->stream(" end\n");

        expect(array_slice($told(), 1))->toBe([['out', 'green line'], ['out', 'progress 100%'], ['out', 'partial end']]);
    });

    test('a long job is announced and ended, with the output it said before', function () {
        [$ui, $told] = script_ui();

        $result = $ui->spin(static function () use ($ui): string {
            $ui->stream("half a line");

            return 'done';
        }, 'Working');

        expect($result)->toBe('done')
            ->and(array_slice($told(), 1))->toBe([['busy', 'Working'], ['out', 'half a line'], ['ready']]);

        [$failing, $toldFailing] = script_ui();
        expect(fn() => $failing->spin(static fn() => throw new RuntimeException('boom'), 'Working'))->toThrow(RuntimeException::class)
            ->and(array_column($toldFailing(), 0))->toBe(['hello', 'busy', 'ready']);
    });
});
