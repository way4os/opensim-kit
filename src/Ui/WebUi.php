<?php

declare(strict_types=1);

namespace OpenSimKit\Ui;

use OpenSim\Provisioning\UiInterface;
use OpenSimKit\Web\Mailbox;

/**
 * The setup in a browser. The flow runs in the process of the setup, as it does in the terminal, and waits for each answer as
 * it does there: a question is a screen put in the mailbox, the web server shows it, and the answer comes back the same way.
 * The checks of the answers (validate, when) stay here, where the flow is: a wrong answer is the same screen again, with the
 * message.
 *
 * Escape and Ctrl-Q have their buttons: Back and Quit end the screen with the same exceptions as in the terminal.
 */
final class WebUi implements UiInterface, CapturesOutput
{
    /** The marks around the name of an entity in a text, which the page makes visible. */
    public const ENTITY_START = "\u{E000}";
    public const ENTITY_END = "\u{E001}";

    /**
     * @param ?\Closure():void $idle called while waiting for an answer (to see the server is still there)
     * @param int $pollMicros how long to sleep between two looks at the mailbox
     * @param ?float $timeout seconds to wait for one answer before giving up, null to wait for ever
     */
    public function __construct(
        private readonly Mailbox $mailbox,
        private readonly ?\Closure $idle = null,
        private readonly int $pollMicros = 50_000,
        private readonly ?float $timeout = null,
    ) {}

    public function intro(string $title): void
    {
        $this->mailbox->say('intro', $title);
    }

    public function note(string $message): void
    {
        $this->mailbox->say('note', $message);
    }

    public function entity(string $name): string
    {
        return self::ENTITY_START . $name . self::ENTITY_END;
    }

    public function warn(string $message): void
    {
        $this->mailbox->say('warning', $message);
    }

    public function error(string $message): void
    {
        $this->mailbox->say('error', $message);
    }

    public function outro(string $message): void
    {
        $this->mailbox->publish(['kind' => 'done', 'message' => $message]);
    }

    public function stream(string $chunk): void
    {
        // What a program says on its own: without the colours of a terminal, a line at a time
        $text = preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', str_replace("\r\n", "\n", $chunk)) ?? $chunk;
        foreach (preg_split('/\r|\n/', rtrim($text)) ?: [] as $line) {
            trim($line) !== '' && $this->mailbox->say('output', rtrim($line));
        }
    }

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        while (true) {
            $value = (string) ($this->ask([
                'kind' => 'choose',
                'label' => $label,
                'options' => $options,
                'default' => $default ?? array_key_first($options),
                'hint' => $hint,
            ])['value'] ?? '');
            if (isset($options[$value])) {
                return $value;
            }
        }
    }

    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array
    {
        $values = $this->ask(['kind' => 'checklist', 'label' => $label, 'options' => $options, 'defaults' => $defaults, 'hint' => $hint]);

        return array_values(array_filter(
            array_map('strval', (array) ($values['value'] ?? [])),
            static fn(string $key): bool => isset($options[$key]),
        ));
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        $value = $default;
        $error = null;
        while (true) {
            $given = $this->ask(['kind' => 'text', 'label' => $label, 'value' => $value, 'hint' => $hint, 'error' => $error]);
            $value = trim((string) ($given['value'] ?? ''));
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
            $value = (string) ($this->ask(['kind' => 'secret', 'label' => $label, 'hint' => $hint, 'error' => $error])['value'] ?? '');
            $error = $validate !== null ? $validate($value) : null;
            if ($error === null) {
                return $value;
            }
        }
    }

    public function confirm(string $label, bool $default = true): bool
    {
        return ($this->ask(['kind' => 'confirm', 'label' => $label, 'default' => $default])['value'] ?? '') === 'yes';
    }

    public function spin(\Closure $callback, string $message): mixed
    {
        $this->mailbox->publish(['kind' => 'working', 'message' => $message]);

        return $callback();
    }

    public function form(array $fields, string $title = ''): array
    {
        $values = [];
        $secrets = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $default = (string) ($field['default'] ?? '');
            // A password that is proposed (one that was made) is not sent to the page: nothing typed keeps it
            if (($field['type'] ?? 'text') === 'secret') {
                $secrets[$key] = $default;
                $default = '';
            }
            $values[$key] = $default;
        }
        $errors = [];

        while (true) {
            // As in the terminal, a field is there or not for all the values as they are now
            $shown = $this->shown($fields, $values, $secrets);
            $screen = ['kind' => 'form', 'title' => $title, 'fields' => []];
            foreach ($fields as $field) {
                $key = $field['key'];
                $condition = self::condition($field, $fields, $shown);
                $screen['fields'][] = [
                    'key' => $key,
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'options' => $field['options'] ?? [],
                    'value' => $values[$key],
                    'hint' => $field['hint'] ?? null,
                    'required' => $field['required'] ?? true,
                    'hasDefault' => ($secrets[$key] ?? '') !== '',
                    'error' => $errors[$key] ?? null,
                    // The page follows the answers by itself when the condition is data; otherwise the field is there or not
                    'condition' => $condition,
                    'shown' => $shown[$key],
                    'rendered' => $condition !== null || $shown[$key],
                ];
            }
            $given = $this->ask($screen);

            foreach ($screen['fields'] as $i => $question) {
                $key = $question['key'];
                $posted = $given[$key] ?? null;
                if (!$question['rendered'] || $posted === null) {
                    continue; // not asked, or not there when it was: what it was
                }
                $values[$key] = match (true) {
                    is_array($posted) => implode(',', array_filter(array_map('strval', $posted), static fn(string $v): bool => $v !== '')),
                    ($fields[$i]['type'] ?? 'text') === 'secret' => (string) $posted,
                    default => trim((string) $posted),
                };
            }
            $now = $this->shown($fields, $values, $secrets);
            $errors = [];
            $again = false;
            $result = [];
            foreach ($screen['fields'] as $i => $question) {
                $field = $fields[$i];
                $key = $question['key'];
                $type = $field['type'] ?? 'text';
                if (!$now[$key]) {
                    $result[$key] = '';
                    continue;
                }
                $value = $type === 'secret' && $values[$key] === '' ? $secrets[$key] : $values[$key];
                $result[$key] = $value;
                if (!$question['rendered']) {
                    $again = true; // it is there now, because of the other answers: shown, to be filled
                } elseif (($field['required'] ?? true) && $value === '' && !in_array($type, ['checklist', 'confirm', 'choice'], true)) {
                    $errors[$key] = _('This field is required.');
                } elseif ($value !== '' && isset($field['validate']) && ($message = $field['validate']($value)) !== null) {
                    $errors[$key] = $message;
                }
            }
            if ($errors === [] && !$again) {
                return $result;
            }
        }
    }

    /**
     * Which fields are there for these values (a proposed password counts for the value it is).
     *
     * @param list<array<string,mixed>> $fields
     * @param array<string,string> $values
     * @param array<string,string> $secrets
     * @return array<string,bool>
     */
    private function shown(array $fields, array $values, array $secrets): array
    {
        $effective = $values;
        foreach ($secrets as $key => $default) {
            $effective[$key] === '' && $effective[$key] = $default;
        }
        $shown = [];
        foreach ($fields as $field) {
            $shown[$field['key']] = !isset($field['when']) || (bool) $field['when']($effective);
        }

        return $shown;
    }

    /**
     * The condition of a field as data the page can follow: [field => list of the values it must have], when all of
     * these fields are on the screen.
     *
     * @param array<string,mixed> $field
     * @param list<array<string,mixed>> $fields
     * @param array<string,bool> $shown
     * @return ?array<string,list<string>>
     */
    private static function condition(array $field, array $fields, array $shown): ?array
    {
        if (!isset($field['condition'])) {
            return null;
        }
        $keys = array_column($fields, 'key');
        $condition = [];
        foreach ($field['condition'] as $other => $wanted) {
            if (!in_array($other, $keys, true) || !$shown[$other]) {
                return null;
            }
            $condition[$other] = array_map('strval', array_values((array) $wanted));
        }

        return $condition;
    }

    /**
     * Put a screen in the mailbox and wait for its answer.
     *
     * @param array<string,mixed> $screen
     * @return array<string,mixed> the values posted
     * @throws Back the person went back
     * @throws Quit the person ended the setup
     */
    private function ask(array $screen): array
    {
        $id = $this->mailbox->publish($screen);
        $start = microtime(true);
        while (($answer = $this->mailbox->answered($id)) === null) {
            if ($this->timeout !== null && microtime(true) - $start > $this->timeout) {
                throw new \RuntimeException('Nobody answered in the browser');
            }
            $this->idle !== null && ($this->idle)();
            $this->pollMicros > 0 && usleep($this->pollMicros);
        }
        $this->mailbox->clearAnswer();

        return match ($answer['action'] ?? 'next') {
            'quit' => throw new Quit(),
            'back' => throw new Back(),
            default => (array) ($answer['values'] ?? []),
        };
    }
}
