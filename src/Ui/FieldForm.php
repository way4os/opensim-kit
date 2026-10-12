<?php

declare(strict_types=1);

namespace OpenSimKit\Ui;

use OpenSim\Forms\FieldSet;
use OpenSim\Provisioning\UiInterface;

/**
 * The terminal side of the fields of the forms: a screen of fields in the standard format (see FieldSet) is asked through the
 * interface of the setup, which has its own way of describing a question (key, hint, validate, when...).
 *
 * What it gives back are the answers, by the names of the fields, as a person would give them to any other form: each field that
 * is shown, except the ones that follow others (a port that is worked out from another, the nick from the name) and were left as
 * proposed, which the planner works out again from what the others turned out to be.
 */
final class FieldForm
{
    /**
     * @param array<string,mixed> $proposed name => the value proposed
     * @param array<string,mixed> $before the answers of the screens before, which the conditions may need
     * @return array<string,mixed> name => answer
     */
    public static function ask(UiInterface $ui, FieldSet $fields, array $proposed, string $title, array $before = []): array
    {
        $questions = [];
        foreach ($fields->all() as $field) {
            if (($field['enable'] ?? true) === false) {
                continue;
            }
            $name = $field['name'];
            $type = $field['type'] ?? 'string';
            $several = $type === 'checkbox' && ($field['multiple'] ?? false) === true;
            $question = [
                'key' => $name,
                'label' => (string) ($field['label'] ?? $name),
                'type' => $several ? 'checklist' : (['boolean' => 'confirm', 'select' => 'choice', 'secret' => 'secret'][$type] ?? 'text'),
                'default' => self::shown($proposed[$name] ?? ($field['default'] ?? ''), $type),
                'required' => $field['required'] ?? false,
            ];
            if (isset($field['description'])) {
                $question['hint'] = (string) $field['description'];
            }
            if ($type === 'select' || $several) {
                $question['options'] = $field['options'] ?? [];
            }
            $callback = $field['callback'] ?? ($field['validation'] ?? null);
            if (is_callable($callback)) {
                $question['validate'] = static fn(string $value): ?string => $callback($value, $before, $field);
            }
            if (isset($field['condition'])) {
                $question['when'] = static fn(array $values): bool => $fields->visible($name, $before + self::typed($values, $fields));
            }
            $questions[] = $question;
        }

        $values = $ui->form($questions, $title);
        $typed = $before + self::typed($values, $fields);
        $answers = [];
        foreach ($questions as $question) {
            $name = $question['key'];
            if (!$fields->visible($name, $typed)) {
                continue;
            }
            $follows = ($fields->get($name)['follows'] ?? []) !== [];
            if ($follows && $values[$name] === $question['default']) {
                continue;
            }
            $answers[$name] = $typed[$name];
        }

        return $answers;
    }

    /** What the interface shows of a value: yes or no for a boolean. */
    private static function shown(mixed $value, string $type): string
    {
        return match (true) {
            $type === 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no',
            is_array($value) => implode(',', $value),
            default => (string) $value,
        };
    }

    /**
     * The values of the interface as the answers they are: a yes is true.
     *
     * @param array<string,string> $values
     * @return array<string,mixed>
     */
    private static function typed(array $values, FieldSet $fields): array
    {
        $typed = [];
        foreach ($values as $name => $value) {
            $field = $fields->get($name) ?? [];
            $typed[$name] = match (true) {
                ($field['type'] ?? '') === 'boolean' => $value === 'yes',
                ($field['type'] ?? '') === 'checkbox' && ($field['multiple'] ?? false) === true => array_values(array_filter(explode(',', $value))),
                default => $value,
            };
        }

        return $typed;
    }
}
