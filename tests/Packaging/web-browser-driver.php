<?php

/**
 * A browser for the scenarios: it goes through the pages of the setup in a browser (opensim setup --web) as a person would, with HTTP
 * requests. On each page it answers what the fixture says, and accepts what the page proposes for the rest (the button that stands
 * out in a menu, the values of a form). It says what it did, page by page, and how many pages the setup needed.
 *
 * Usage: php web-browser-driver.php ADDRESS FIXTURE.php
 *
 * The fixture returns ['fill' => label (a part of it) => value, 'click' => heading (a part of it) => button (a part of its label)].
 * Exit status: 0 when the setup ends, 1 when a page is refused, does not come, or the setup goes round in circles.
 */

[, $address, $fixtureFile] = $argv + [null, null, null];
$fixture = require $fixtureFile;

$base = substr($address, 0, (int) strpos($address, '/?t='));
$cookie = '';

/** @return array{0:int,1:string,2:array<string,string>} */
function request(string $url, array $post = []): array
{
    global $cookie;
    $context = stream_context_create(['http' => [
        'method' => $post === [] ? 'GET' : 'POST',
        // As a browser sends a form: its cookie and where the page comes from
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . ($cookie !== '' ? "Cookie: opensim_wizard=$cookie\r\n" : '') .
            ($post === [] ? '' : 'Origin: ' . parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT) . "\r\nSec-Fetch-Site: same-origin\r\n"),
        'content' => http_build_query($post),
        'follow_location' => 0,
        'ignore_errors' => true,
        'timeout' => 60,
    ]]);
    $page = (string) @file_get_contents($url, false, $context);
    $headers = [];
    foreach ($http_response_header ?? [] as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower($name)] = trim($value);
        }
    }
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);

    return [(int) ($m[1] ?? 0), $page, $headers];
}

function text(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) ?? '');
}

function fail(string $message, string $page = ''): never
{
    fwrite(STDOUT, "FAILED: $message\n");
    $page === '' || fwrite(STDOUT, '   | ' . substr(text($page), 0, 1200) . "\n");
    exit(1);
}

/** The button a fixture says to click on a page: a part of its label; a list is a queue, one for each time the page comes back. */
function wanted(array &$clicks, string $where): ?string
{
    foreach ($clicks as $part => $value) {
        if (stripos($where, (string) $part) === false) {
            continue;
        }
        if (is_array($value)) {
            return array_shift($clicks[$part]);
        }

        return (string) $value;
    }

    return null;
}

/** The label a fixture answer is given for matches: a part of it, whatever its case. */
function given(array $answers, string $label): ?string
{
    foreach ($answers as $part => $value) {
        if (stripos($label, (string) $part) !== false) {
            return (string) $value;
        }
    }

    return null;
}

[$status, , $headers] = request($address);
preg_match('/opensim_wizard=([0-9a-f]+)/', $headers['set-cookie'] ?? '', $m);
$cookie = $m[1] ?? '';
$status === 303 && $cookie !== '' || fail("the address does not give the cookie (status $status)");

$seen = [];
$clicks = $fixture['click'] ?? [];
$last = '';
$pages = 0;
for ($turn = 0; $turn < 120; $turn++) {
    // The next page: what is being done is waited for
    $deadline = microtime(true) + 900;
    do {
        [$status, $page] = request("$base/");
        $working = str_contains($page, 'class="spinner"');
        $working && usleep(500_000);
    } while ($working && microtime(true) < $deadline);
    $status === 200 || fail("the page answers $status", $page);
    if (str_contains($page, 'You can close this page')) {
        fwrite(STDOUT, 'done: ' . text((string) preg_replace('#^.*?<section><h2>(.*?)</h2>.*$#s', '$1', $page)) . "\npages: $pages\n");
        // What went wrong, then everything the setup said, for the scenario to look in
        $page = (string) preg_replace('#<(style|script)\b.*?</\1>#s', '', $page);
        preg_match_all('#<pre class="(error|warning)">(.*?)</pre>#s', $page, $problems, PREG_SET_ORDER);
        foreach ($problems as $problem) {
            fwrite(STDOUT, "$problem[1]: " . text($problem[2]) . "\n");
        }
        fwrite(STDOUT, 'said: ' . text($page) . "\n");
        exit(0);
    }
    preg_match('/name="id" value="(\d+)"/', $page, $m) || fail('no screen on this page', $page);
    $id = (int) $m[1];
    $heading = preg_match('#<section>(?:(?!</section>).)*?<h2>(.*?)</h2>#s', $page, $m) ? text($m[1]) : '';
    // A screen that comes back after the answer is an answer that was refused
    if ($id === ($seen[count($seen) - 1] ?? 0) || isset($seen[$id])) {
        fail("the screen '$heading' was refused", $page);
    }
    $seen[$id] = $id;
    $pages++;

    $post = ['id' => $id, 'action' => 'next'];
    if (str_contains($page, '<div class="field')) {
        // A form: what the fixture answers, the values proposed for the rest
        $values = [];
        $said = [];
        $boxes = [];
        foreach (preg_split('/(?=<div class="field)/', (string) strstr($page, '<div class="field')) as $block) {
            if (!str_contains($block, '<label>')) {
                continue;
            }
            $label = text(preg_replace('#<(input|select|div class="checks").*$#s', '', substr($block, strpos($block, '<label>') + 7)) ?? '');
            $label = preg_replace('/ \*$/', '', $label);
            $answer = given($fixture['fill'] ?? [], $label);
            if (preg_match('/<select name="v\[(\w+)\]">(.*?)<\/select>/s', $block, $f)) {
                $current = preg_match('/<option value="([^"]*)" selected/', $f[2], $o) ? $o[1] : (preg_match('/<option value="([^"]*)"/', $f[2], $o) ? $o[1] : '');
                $values[$f[1]] = $answer ?? $current;
            } elseif (preg_match('/<input type="(?:text|password)" name="v\[(\w+)\]"(?: autocomplete="[^"]*")?(?: value="([^"]*)")?/', $block, $f)) {
                $values[$f[1]] = $answer ?? html_entity_decode($f[2] ?? '', ENT_QUOTES);
            } elseif (preg_match_all('/<input type="checkbox" name="v\[(\w+)\]\[\]" value="([^"]*)"( checked)?> ([^<]*)<\/label>/', $block, $all, PREG_SET_ORDER)) {
                $values[$all[0][1]] = array_merge([''], array_values(array_map(static fn(array $c): string => $c[2], array_filter($all, static fn(array $c): bool => ($c[3] ?? '') !== ''))));
                $boxes[] = $label . ': ' . implode(', ', array_map(static fn(array $c): string => (($c[3] ?? '') !== '' ? '[x] ' : '[ ] ') . trim(html_entity_decode($c[4])), $all));
            }
            $answer === null || $said[] = "$label=" . (str_contains(strtolower($label), 'password') ? '***' : $answer);
        }
        $post['v'] = $values;
        fwrite(STDOUT, sprintf("page %d: %s, a form of %d fields%s\n", $pages, $heading, count($values), $said === [] ? '' : ' (' . implode(', ', $said) . ')'));
        foreach ($boxes as $box) {
            fwrite(STDOUT, "   boxes: $box\n");
        }
    } elseif (preg_match_all('#<button type="submit" name="v\[value\]" value="([^"]*)" class="([^"]*)">(.*?)</button>#s', $page, $buttons, PREG_SET_ORDER)) {
        // A menu, or a yes or no: the button the fixture names, otherwise the one that stands out
        $want = wanted($clicks, $heading . ' ' . text((string) preg_replace('#<form.*$#s', '', (string) strstr($page, '<section>'))));
        $chosen = null;
        foreach ($buttons as $button) {
            if ($want !== null && stripos(text($button[3]), $want) !== false) {
                $chosen = $button;
            }
        }
        $chosen ??= array_values(array_filter($buttons, static fn(array $b): bool => str_contains($b[2], 'primary')))[0] ?? $buttons[0];
        $post['v'] = ['value' => html_entity_decode($chosen[1], ENT_QUOTES)];
        fwrite(STDOUT, sprintf("page %d: %s -> %s\n", $pages, $heading, text($chosen[3])));
    } elseif (preg_match('/<input type="(?:text|password)" name="v\[value\]"(?: autocomplete="[^"]*")?(?: value="([^"]*)")?/', $page, $f)) {
        $post['v'] = ['value' => given($fixture['fill'] ?? [], $heading) ?? html_entity_decode($f[1] ?? '', ENT_QUOTES)];
        fwrite(STDOUT, sprintf("page %d: %s, a question\n", $pages, $heading));
    } else {
        fail("a screen the browser does not know: $heading", $page);
    }
    [$status] = request("$base/answer", $post);
    $status === 303 || fail("the answer to '$heading' is refused ($status)");
}
fail('the setup goes round in circles');
