<?php

declare(strict_types=1);

namespace OpenSimKit\Ui;

use OpenSim\Provisioning\UiInterface;

/**
 * The setup asked by a script (setup.sh). The flow runs in the process of the setup and decides everything: the questions, their
 * order, the checks of the answers, what is done with them. The script only shows what it is told and brings back what the person
 * answered, so nothing of the logic is written twice.
 *
 * One line per message, the fields separated by the unit separator (SEPARATOR: unlike a tab, a shell reads empty fields between
 * two of them), written as the engine says (escape()). The engine tells:
 *
 *     intro TITLE | note TEXT | warn TEXT | error TEXT | outro TEXT | out LINE | busy TEXT | ready | bye STATUS
 *     ask ID KIND LABEL DEFAULT HINT ERROR [KEY LABEL]...
 *
 * KIND is text, secret, confirm (the default is yes or no), choose (the default is a key), or checklist (the default is the keys
 * checked, joined by commas); the pairs are the options of choose and checklist. ERROR says what was wrong with the last answer to
 * the same question, which is asked again. The script answers, to the question it was asked:
 *
 *     answer ID VALUE | back ID | quit ID
 *
 * VALUE is the text, yes or no, the key chosen, or the keys checked joined by commas.
 */
final class ScriptUi implements UiInterface, CapturesOutput
{
    use AsksForms {
        form as private reviewedForm;
    }

    public const VERSION = 1;

    /** Between the fields of a line. */
    public const SEPARATOR = "\x1f";

    private int $id = 0;
    private string $partial = '';
    private string $pending = '';

    /**
     * @param resource $out where the engine writes what it tells
     * @param resource $in where the script writes its answers
     * @param ?\Closure():void $idle called while waiting for an answer, at least every $idleSeconds
     */
    public function __construct(
        private $out,
        private $in,
        private readonly ?\Closure $idle = null,
        private readonly int $idleSeconds = 30,
    ) {
        // A pipe opened as a file gives what it has only when its whole chunk is there, unless it is asked not to wait
        stream_set_blocking($this->in, false);
        $this->send('hello', (string) self::VERSION);
    }

    /** A field of a line: separators, tabs, new lines and backslashes written out. */
    public static function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', self::SEPARATOR => '\\s', "\t" => '\\t', "\n" => '\\n', "\r" => '\\r']);
    }

    /** What escape() wrote. */
    public static function unescape(string $text): string
    {
        return (string) preg_replace_callback(
            '/\\\\(.)/s',
            static fn(array $m): string => ['s' => self::SEPARATOR, 't' => "\t", 'n' => "\n", 'r' => "\r"][$m[1]] ?? $m[1],
            $text,
        );
    }

    /** The end of the setup: the script stops with it. */
    public function close(int $status): void
    {
        $this->endLine();
        $this->send('bye', (string) $status);
    }

    public function intro(string $title): void
    {
        $this->send('intro', $title);
    }

    public function note(string $message): void
    {
        $this->send('note', $message);
    }

    public function entity(string $name): string
    {
        return $name;
    }

    public function warn(string $message): void
    {
        $this->send('warn', $message);
    }

    public function error(string $message): void
    {
        $this->send('error', $message);
    }

    public function outro(string $message): void
    {
        $this->endLine();
        $this->send('outro', $message);
    }

    public function stream(string $chunk): void
    {
        // What a program says on its own: without the colours of a terminal, a line at a time; a line it rewrites (a download)
        // is its last version
        $this->partial .= preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', str_replace("\r\n", "\n", $chunk)) ?? $chunk;
        $lines = explode("\n", $this->partial);
        $this->partial = array_pop($lines);
        foreach ($lines as $line) {
            $line = rtrim(substr((string) strrchr("\r$line", "\r"), 1));
            $line !== '' && $this->send('out', $line);
        }
    }

    /**
     * A form is its fields, asked one after the other, each checked as it is given: the review of the answers is the plan the setup
     * shows before it writes anything, not a question after every screen.
     */
    public function form(array $fields, string $title = ''): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field['key']] = $this->visible($field, $values) ? $this->askField($field, $field['default'] ?? '') : '';
        }

        return $values;
    }

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        $default ??= (string) array_key_first($options);
        while (true) {
            $value = $this->ask('choose', $label, $default, $hint, null, $options);
            if (isset($options[$value])) {
                return $value;
            }
        }
    }

    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array
    {
        $value = $this->ask('checklist', $label, implode(',', $defaults), $hint, null, $options);

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn(string $key): bool => $key !== '' && isset($options[$key]),
        ));
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        $value = $default;
        $error = null;
        while (true) {
            $value = trim($this->ask('text', $label, $value, $hint, $error));
            $error = $validate !== null ? $validate($value) : null;
            if ($error === null) {
                return $value;
            }
        }
    }

    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string
    {
        $error = null;
        while (true) {
            $value = $this->ask('secret', $label, '', $hint, $error);
            $error = $validate !== null ? $validate($value) : null;
            if ($error === null) {
                return $value;
            }
        }
    }

    public function confirm(string $label, bool $default = true): bool
    {
        return $this->ask('confirm', $label, $default ? 'yes' : 'no') === 'yes';
    }

    public function spin(\Closure $callback, string $message): mixed
    {
        $this->endLine();
        $this->send('busy', $message);
        try {
            return $callback();
        } finally {
            $this->endLine();
            $this->send('ready');
        }
    }

    /** What a program said without ending the line is a line, when what comes next is not the program's. */
    private function endLine(): void
    {
        $this->partial !== '' && $this->stream("\n");
    }

    /**
     * Ask a question and wait for its answer.
     *
     * @param array<string,string> $options
     * @throws Back the person went back
     * @throws Quit the person ended the setup
     */
    private function ask(string $kind, string $label, string $default, ?string $hint = null, ?string $error = null, array $options = []): string
    {
        $this->endLine();
        $id = ++$this->id;
        $pairs = [];
        foreach ($options as $key => $text) {
            $pairs[] = (string) $key;
            $pairs[] = $text;
        }
        $this->send('ask', (string) $id, $kind, $label, $default, $hint ?? '', $error ?? '', ...$pairs);

        while (true) {
            $line = $this->line();
            $fields = array_map(self::unescape(...), explode(self::SEPARATOR, $line));
            if (($fields[1] ?? '') !== (string) $id) {
                continue; // an answer to another question
            }
            if ($fields[0] === 'answer') {
                return $fields[2] ?? '';
            }
            if ($fields[0] === 'back') {
                throw new Back();
            }
            if ($fields[0] === 'quit') {
                throw new Quit();
            }
        }
    }

    /**
     * The next line the script wrote, waiting for it as long as it takes.
     *
     * @throws Quit the script is gone
     */
    private function line(): string
    {
        while (true) {
            if (($end = strpos($this->pending, "\n")) !== false) {
                $line = substr($this->pending, 0, $end);
                $this->pending = substr($this->pending, $end + 1);

                return rtrim($line, "\r");
            }
            $read = [$this->in];
            $write = $except = null;
            if (@stream_select($read, $write, $except, $this->idleSeconds) === 0) {
                $this->idle !== null && ($this->idle)();

                continue;
            }
            $chunk = fread($this->in, 8192);
            if ($chunk === false || $chunk === '') {
                throw new Quit(); // the script closed its end: nobody is there to answer
            }
            $this->pending .= $chunk;
        }
    }

    private function send(string ...$fields): void
    {
        $line = implode(self::SEPARATOR, array_map(self::escape(...), $fields)) . "\n";
        if (@fwrite($this->out, $line) === false || !@fflush($this->out)) {
            throw new Quit(); // the script closed its end
        }
    }
}
