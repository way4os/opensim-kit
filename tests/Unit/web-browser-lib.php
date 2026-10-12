<?php

/**
 * A browser for the tests of what is shown to the person: it answers the screens the setup puts in the mailbox.
 */

use OpenSimKit\Ui\WebUi;
use OpenSimKit\Web\Mailbox;

/**
 * The idle of a WebUi that answers each screen once, with what $answer makes of it (null: the screen is not answered).
 * The screens are kept in $screens.
 *
 * @param \Closure(array):array $answer
 */
function webBrowser(Mailbox $mailbox, \Closure $answer, ?array &$screens): \Closure
{
    $screens = [];

    return static function () use ($mailbox, $answer, &$screens): void {
        $screen = $mailbox->screen();
        if ($screen === null || $mailbox->answered($screen['id']) !== null) {
            return;
        }
        $screens[] = $screen;
        $mailbox->answer($screen['id'], $answer($screen));
    };
}

/**
 * A WebUi and its mailbox, with a browser that gives the answers of the list one after the other (an array, or a function
 * of the screen).
 *
 * @return array{0:WebUi,1:Mailbox}
 */
function webSetup(array $answers, ?array &$screens = null): array
{
    $mailbox = Mailbox::create();
    $browser = webBrowser(
        $mailbox,
        static function (array $screen) use (&$answers): array {
            $next = array_shift($answers) ?? throw new RuntimeException('nothing to answer to ' . json_encode($screen));

            return is_callable($next) ? $next($screen) : $next;
        },
        $screens,
    );

    return [new WebUi($mailbox, $browser, 0, 2.0), $mailbox];
}

/**
 * A WebUi whose browser accepts what each screen proposes, except the answers it is given by label (a part of it).
 *
 * @param array<string,string> $answers
 * @return array{0:WebUi,1:Mailbox}
 */
function webAccepting(array $answers = [], ?array &$screens = null): array
{
    $mailbox = Mailbox::create();
    $browser = webBrowser(
        $mailbox,
        static function (array $screen) use ($answers): array {
            $given = static function (string $label) use ($answers): ?string {
                foreach ($answers as $match => $value) {
                    if (str_contains($label, (string) $match)) {
                        return (string) $value;
                    }
                }

                return null;
            };
            if ($screen['kind'] === 'form') {
                $values = [];
                foreach ($screen['fields'] as $field) {
                    $values[$field['key']] = $given($field['label']) ?? $field['value'];
                }

                return ['values' => $values];
            }
            $label = (string) ($screen['label'] ?? '');
            $default = $screen['default'] ?? $screen['value'] ?? '';

            return ['values' => ['value' => $given($label) ?? (is_bool($default) ? ($default ? 'yes' : 'no') : $default)]];
        },
        $screens,
    );

    return [new WebUi($mailbox, $browser, 0, 2.0), $mailbox];
}
