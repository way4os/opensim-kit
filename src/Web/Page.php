<?php

declare(strict_types=1);

namespace OpenSimKit\Web;

use OpenSimKit\Ui\WebUi;

/**
 * The HTML of the screens of the setup: plain markup with its own small style (no framework, nothing loaded from elsewhere,
 * the page works on a machine that has no network), and a few lines of script that only follow the conditions of the fields.
 */
final class Page
{
    /**
     * @param array<string,mixed>|null $screen the screen being asked, null before the first one
     * @param list<array{after:int,level:string,message:string}> $log
     * @param bool $working the answer to this screen is given and the next one is not there yet
     */
    public static function render(?array $screen, array $log, bool $working): string
    {
        $id = (int) ($screen['id'] ?? 0);
        $kind = $working || $screen === null ? 'working' : (string) $screen['kind'];
        $title = 'OpenSim setup';
        foreach ($log as $entry) {
            $entry['level'] === 'intro' && $title = $entry['message'];
        }

        // What was said since the last screen goes above this one, the rest is kept below
        $before = $working || $kind === 'working' ? $id : $id - 1;
        $recent = array_filter($log, static fn(array $e): bool => $e['level'] !== 'intro' && $e['after'] >= $before);
        $earlier = array_filter($log, static fn(array $e): bool => $e['level'] !== 'intro' && $e['after'] < $before);

        $body = match ($kind) {
            'working' => self::working($screen, $working),
            'done' => self::done($screen),
            default => self::question($screen, $kind),
        };
        $messages = self::messages($recent);
        $history =
            $earlier === []
                ? ''
                : '<details class="history"><summary>' . self::e(_('Earlier messages')) . '</summary>' . self::messages($earlier) . '</details>';

        return self::layout($title, $messages . $body . $history, $kind === 'working');
    }

    /** The page of an address that is of no use: already used, or not the right one. */
    public static function refused(): string
    {
        return self::layout(
            'OpenSim setup',
            '<section><h2>' . self::e(_('This address cannot be used')) . '</h2><p>' .
            self::e(_('It was used already, or it is not the one the setup gave. Start the setup again to get a new one.')) . '</p></section>',
            false,
        );
    }

    /** @param array<string,mixed> $screen */
    private static function question(array $screen, string $kind): string
    {
        $head = '<h2>' . self::text((string) ($screen['label'] ?? $screen['title'] ?? '')) . '</h2>';
        $hint = isset($screen['hint']) && $screen['hint'] !== '' ? '<p class="hint">' . self::text((string) $screen['hint']) . '</p>' : '';
        $error = isset($screen['error']) ? '<p class="error">' . self::text((string) $screen['error']) . '</p>' : '';
        $next = '<button class="primary" type="submit" name="action" value="next">' . self::e(_('Continue')) . '</button>';

        $inner = match ($kind) {
            'choose' => self::choices($screen),
            'confirm' => self::confirmation($screen),
            'checklist' => self::checklist($screen) . self::buttons($next),
            'text' => '<input type="text" name="v[value]" value="' . self::e((string) ($screen['value'] ?? '')) . '" autofocus>' . self::buttons($next),
            'secret' => '<input type="password" name="v[value]" autocomplete="new-password" autofocus>' . self::buttons($next),
            'form' => self::fields($screen) . self::buttons($next),
            default => '',
        };
        if ($kind === 'form') {
            $head = $screen['title'] !== '' ? '<h2>' . self::text((string) $screen['title']) . '</h2>' : '';
        }

        return '<section>' . $head . $hint . $error . '<form method="post" action="/answer" id="screen">' .
            '<input type="hidden" name="id" value="' . (int) $screen['id'] . '">' . $inner . '</form>' .
            self::back((int) $screen['id']) . '</section>' . self::script();
    }

    /** @param array<string,mixed> $screen */
    private static function choices(array $screen): string
    {
        $out = '<div class="choices">';
        foreach ($screen['options'] as $key => $label) {
            $class = (string) $key === (string) ($screen['default'] ?? '') ? 'primary' : '';
            $out .= '<button type="submit" name="v[value]" value="' . self::e((string) $key) . '" class="' . $class . '">' . self::text((string) $label) . '</button>';
        }

        return $out . '</div>';
    }

    /** @param array<string,mixed> $screen */
    private static function confirmation(array $screen): string
    {
        $yes = ($screen['default'] ?? true) === true;

        return '<div class="choices inline">' .
            '<button type="submit" name="v[value]" value="yes" class="' . ($yes ? 'primary' : '') . '">' . self::e(_('Yes')) . '</button>' .
            '<button type="submit" name="v[value]" value="no" class="' . ($yes ? '' : 'primary') . '">' . self::e(_('No')) . '</button></div>';
    }

    /** @param array<string,mixed> $screen */
    private static function checklist(array $screen): string
    {
        return '<input type="hidden" name="v[value][]" value="">' . self::checks('v[value]', $screen['options'], $screen['defaults'] ?? []);
    }

    /**
     * @param array<string,string> $options
     * @param list<string> $checked
     */
    private static function checks(string $name, array $options, array $checked): string
    {
        $out = '<div class="checks">';
        foreach ($options as $key => $label) {
            $out .= '<label><input type="checkbox" name="' . self::e($name) . '[]" value="' . self::e((string) $key) . '"' .
                (in_array((string) $key, array_map('strval', $checked), true) ? ' checked' : '') . '> ' . self::text((string) $label) . '</label>';
        }

        return $out . '</div>';
    }

    /** @param array<string,mixed> $screen */
    private static function fields(array $screen): string
    {
        $out = '';
        foreach ($screen['fields'] as $field) {
            if (!$field['rendered']) {
                continue;
            }
            $key = (string) $field['key'];
            $name = 'v[' . $key . ']';
            $value = (string) $field['value'];
            $input = match ($field['type']) {
                'secret' => '<input type="password" name="' . self::e($name) . '" autocomplete="new-password"' .
                    ($field['hasDefault'] ? ' placeholder="' . self::e(_('Made for you, leave empty to keep it')) . '"' : '') . '>',
                'choice' => self::select($name, $field['options'], $value),
                'confirm' => self::select($name, ['yes' => _('Yes'), 'no' => _('No')], $value === 'yes' ? 'yes' : 'no'),
                'checklist' => '<input type="hidden" name="' . self::e($name) . '[]" value="">' .
                    self::checks($name, $field['options'], array_filter(explode(',', $value))),
                default => '<input type="text" name="' . self::e($name) . '" value="' . self::e($value) . '">',
            };
            $mark = $field['required'] && !in_array($field['type'], ['confirm', 'choice', 'checklist'], true) ? ' <span class="required">*</span>' : '';
            $out .= '<div class="field' . ($field['error'] !== null ? ' invalid' : '') . '"' .
                ($field['condition'] !== null ? ' data-condition="' . self::e(json_encode($field['condition'], JSON_THROW_ON_ERROR)) . '"' : '') .
                ($field['shown'] ? '' : ' hidden') . '>' .
                '<label>' . self::text((string) $field['label']) . $mark . $input . '</label>' .
                ($field['hint'] !== null ? '<small>' . self::text((string) $field['hint']) . '</small>' : '') .
                ($field['error'] !== null ? '<p class="error">' . self::text((string) $field['error']) . '</p>' : '') .
                '</div>';
        }

        return $out;
    }

    /** @param array<string,string> $options */
    private static function select(string $name, array $options, string $current): string
    {
        $out = '<select name="' . self::e($name) . '">';
        foreach ($options as $key => $label) {
            $out .= '<option value="' . self::e((string) $key) . '"' . ((string) $key === $current ? ' selected' : '') . '>' . self::text((string) $label) . '</option>';
        }

        return $out . '</select>';
    }

    /** The first button is the one Enter presses; the style puts it on the right. */
    private static function buttons(string $next): string
    {
        return '<div class="buttons">' . $next . '</div>';
    }

    private static function back(int $id): string
    {
        return '<form method="post" action="/answer" class="side"><input type="hidden" name="id" value="' . $id . '">' .
            '<button type="submit" name="action" value="back" formnovalidate>' . self::e(_('Back')) . '</button>' .
            '<button type="submit" name="action" value="quit" class="quit" onclick="return confirm(\'' . self::e(addslashes(_('Leave the setup?'))) . '\')">' .
            self::e(_('Quit the setup')) . '</button></form>';
    }

    /** @param ?array<string,mixed> $screen */
    private static function working(?array $screen, bool $answered): string
    {
        $message = $answered || $screen === null || ($screen['kind'] ?? '') !== 'working' ? _('Working...') : (string) $screen['message'];

        return '<section class="working"><div class="spinner" aria-hidden="true"></div><p>' . self::text($message) . '</p></section>';
    }

    /** @param array<string,mixed> $screen */
    private static function done(array $screen): string
    {
        return '<section><h2>' . self::text((string) $screen['message']) . '</h2><p>' . self::e(_('You can close this page.')) . '</p></section>';
    }

    /** @param iterable<array{after:int,level:string,message:string}> $entries */
    private static function messages(iterable $entries): string
    {
        $out = '';
        $lines = [];
        $flush = static function () use (&$out, &$lines): void {
            $lines === [] || $out .= '<pre class="output">' . self::text(implode("\n", $lines)) . '</pre>';
            $lines = [];
        };
        foreach ($entries as $entry) {
            if ($entry['level'] === 'output') {
                $lines[] = $entry['message'];
                continue;
            }
            $flush();
            $class = ['note' => 'note', 'warning' => 'warning', 'error' => 'error'][$entry['level']] ?? 'note';
            $out .= '<pre class="' . $class . '">' . self::text($entry['message']) . '</pre>';
        }
        $flush();

        return $out;
    }

    private static function layout(string $title, string $content, bool $refresh): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' .
            ($refresh ? '<meta http-equiv="refresh" content="1">' : '') .
            '<title>' . self::e($title) . '</title><style>' . self::style() . '</style></head><body><main><header><h1>' . self::e($title) . '</h1></header>' .
            $content . '</main></body></html>';
    }

    /** What a text holds: escaped, the names of the entities in bold. */
    private static function text(string $text): string
    {
        return str_replace(
            [WebUi::ENTITY_START, WebUi::ENTITY_END],
            ['<strong>', '</strong>'],
            self::e($text),
        );
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function script(): string
    {
        return <<<'JS'
<script>
(function () {
  var form = document.getElementById('screen');
  function value(key) {
    var els = form.querySelectorAll('[name="v[' + key + ']"]:not(:disabled), [name="v[' + key + '][]"]:not(:disabled)');
    var out = [];
    els.forEach(function (el) {
      if (el.type === 'checkbox' || el.type === 'radio') { el.checked && out.push(el.value); } else if (el.value !== '') { out.push(el.value); }
    });
    return out;
  }
  function update() {
    form.querySelectorAll('[data-condition]').forEach(function (box) {
      var condition = JSON.parse(box.dataset.condition);
      var ok = Object.keys(condition).every(function (key) {
        var now = value(key);
        return condition[key].some(function (wanted) { return now.indexOf(wanted) !== -1; });
      });
      box.hidden = !ok;
      box.querySelectorAll('input, select').forEach(function (el) { el.disabled = !ok; });
    });
  }
  form.addEventListener('input', update);
  update();
})();
</script>
JS;
    }

    private static function style(): string
    {
        return <<<'CSS'
:root { --bg: #f5f6f8; --card: #fff; --text: #1d2330; --muted: #5d6677; --line: #d8dce4; --accent: #2f6fed; --accent-text: #fff; --error: #b3261e; --warn: #8a5a00; --note-bg: #eef1f6; }
@media (prefers-color-scheme: dark) { :root { --bg: #14171d; --card: #1d222b; --text: #e6e9ef; --muted: #98a1b3; --line: #343b49; --accent: #5b8def; --accent-text: #0d1117; --error: #ff8a80; --warn: #f0b955; --note-bg: #252b36; } }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, sans-serif; }
main { max-width: 42rem; margin: 0 auto; padding: 1.5rem 16px 4rem; }
h1 { font-size: 1.1rem; color: var(--muted); font-weight: 600; margin: 0 0 1rem; }
h2 { font-size: 1.35rem; margin: 0 0 .5rem; }
section { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 1.25rem; margin: 0 0 1rem; }
pre { margin: 0 0 1rem; padding: .75rem 1rem; border-radius: 8px; background: var(--note-bg); white-space: pre-wrap; word-break: break-word; font: .9rem/1.45 ui-monospace, monospace; }
pre.warning { border-left: 4px solid var(--warn); }
pre.error { border-left: 4px solid var(--error); color: var(--error); }
pre.output { opacity: .85; }
.hint, small { color: var(--muted); }
small { display: block; margin-top: .25rem; }
.error { color: var(--error); margin: .25rem 0 0; }
.required { color: var(--error); }
.field { margin: 0 0 1rem; }
.field label { display: block; font-weight: 600; }
.field.invalid input, .field.invalid select { border-color: var(--error); }
input[type=text], input[type=password], select { width: 100%; padding: .55rem .7rem; margin-top: .3rem; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); color: var(--text); font: inherit; }
.checks label { display: block; font-weight: 400; margin: .2rem 0; }
.checks input { margin-right: .4rem; }
button { font: inherit; padding: .55rem 1.1rem; border-radius: 8px; border: 1px solid var(--line); background: var(--card); color: var(--text); cursor: pointer; }
button.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-text); font-weight: 600; }
.choices { display: grid; gap: .5rem; margin-top: .75rem; }
.choices.inline { display: flex; }
.choices button { text-align: left; }
.buttons { display: flex; justify-content: flex-end; margin-top: 1rem; }
.side { display: flex; justify-content: space-between; margin-top: 1rem; }
button.quit { border-color: transparent; background: none; color: var(--muted); }
details.history { color: var(--muted); }
details.history summary { cursor: pointer; margin: 0 0 .5rem; }
.working { text-align: center; }
.spinner { width: 2rem; height: 2rem; margin: .5rem auto 1rem; border: 3px solid var(--line); border-top-color: var(--accent); border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
[hidden] { display: none !important; }
CSS;
    }
}
