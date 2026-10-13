<?php

use OpenSimKit\Web\WizardServer;

/**
 * The browser, in PHP: a request to the web server of the setup, with the cookie, without following the redirections.
 *
 * @return array{0:int,1:string,2:array<string,string>} the status, the page, the headers
 */
function browse(string $url, array $post = [], string $cookie = ''): array
{
    $context = stream_context_create(['http' => [
        'method' => $post === [] ? 'GET' : 'POST',
        // As a browser sends a form: its cookie, where the page comes from (the origin is "null" when a page asks for no referrer)
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . ($cookie !== '' ? 'Cookie: ' . WizardServer::COOKIE . "=$cookie\r\n" : '') .
            ($post === [] ? '' : "Origin: null\r\nSec-Fetch-Site: same-origin\r\n"),
        'content' => http_build_query($post),
        'follow_location' => 0,
        'ignore_errors' => true,
        'timeout' => 90,
    ]]);
    $page = (string) file_get_contents($url, false, $context);
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

/** The id of the screen a page is for. */
function screenId(string $page): int
{
    return preg_match('/name="id" value="(\d+)"/', $page, $m) ? (int) $m[1] : 0;
}

/** The setup side: the temporary folder is its own, so the exchanges of another run of the tests are not mistaken for its own. */
function wizardDriver(string $tmp, string $open = ''): array
{
    $process = proc_open([PHP_BINARY, __DIR__ . '/wizard-driver.php'], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, null, ['TMPDIR' => $tmp, 'WIZARD_TEST_OPEN' => $open] + (getenv() ?: []));
    stream_set_timeout($pipes[1], 90);

    return [$process, $pipes];
}

describe('the setup in a browser, with the real web server', function () {
    test('the questions go through the pages, the answers come back, the server stops at the end', function () {
        [$process, $pipes] = wizardDriver(sys_get_temp_dir());
        $address = trim((string) fgets($pipes[1]));
        expect($address)->toStartWith('http://127.0.0.1:');
        $base = substr($address, 0, (int) strpos($address, '/?t='));

        // The address works once: it gives the cookie, a second use is refused, and no cookie is no page
        [$status, , $headers] = browse($address);
        preg_match('/' . WizardServer::COOKIE . '=([0-9a-f]+)/', $headers['set-cookie'] ?? '', $m);
        $cookie = $m[1] ?? '';
        expect($status)->toBe(303)->and($cookie)->not->toBe('')
            ->and(browse($address)[0])->toBe(403)
            ->and(browse("$base/")[0])->toBe(403);

        // The first question, with what was said before it, escaped
        [, $page] = browse("$base/", [], $cookie);
        expect($page)->toContain('Your name')->toContain('Hello &lt;from&gt; the setup');

        // A refused answer comes back with the message and what was typed
        browse("$base/answer", ['id' => screenId($page), 'v' => ['value' => 'bad']], $cookie);
        [, $page] = browse("$base/", [], $cookie);
        expect($page)->toContain('Not that one')->toContain('value="bad"');

        // The form, which has the field that follows the console on the page, hidden
        browse("$base/answer", ['id' => screenId($page), 'v' => ['value' => 'Bob']], $cookie);
        [, $page] = browse("$base/", [], $cookie);
        expect($page)->toContain('Console')->toContain('data-condition')->toContain(' hidden>');
        browse("$base/answer", ['id' => screenId($page), 'v' => ['mode' => 'rest', 'port' => '8010']], $cookie);

        // The work being done, then the menu
        $deadline = microtime(true) + 90;
        do {
            [, $page] = browse("$base/", [], $cookie);
            str_contains($page, 'Go on') || usleep(100_000);
        } while (!str_contains($page, 'Go on') && microtime(true) < $deadline);
        expect($page)->toContain('Go on');
        browse("$base/answer", ['id' => screenId($page), 'v' => ['value' => 'go']], $cookie);

        // The end, shown to the browser, and what the setup got
        $deadline = microtime(true) + 90;
        do {
            [, $page] = browse("$base/", [], $cookie);
            str_contains($page, 'Finished') || usleep(100_000);
        } while (!str_contains($page, 'Finished') && microtime(true) < $deadline);
        expect($page)->toContain('Finished');

        $said = json_decode((string) fgets($pipes[1]), true);
        proc_close($process);
        expect($said)->toBe([
            'name' => 'Bob',
            'form' => ['mode' => 'rest', 'port' => '8010'],
            'spun' => 'worked',
            'choice' => 'go',
        ]);
    });

    test('Quit in the browser ends the setup, and the folder of the exchanges is removed', function () {
        $tmp = sys_get_temp_dir() . '/wizard-test-' . bin2hex(random_bytes(4));
        mkdir($tmp);
        [$process, $pipes] = wizardDriver($tmp);
        $address = trim((string) fgets($pipes[1]));
        $base = substr($address, 0, (int) strpos($address, '/?t='));
        [, , $headers] = browse($address);
        preg_match('/' . WizardServer::COOKIE . '=([0-9a-f]+)/', $headers['set-cookie'], $m);
        [, $page] = browse("$base/", [], $m[1]);

        browse("$base/answer", ['id' => screenId($page), 'action' => 'quit'], $m[1]);
        $said = json_decode((string) fgets($pipes[1]), true);
        proc_close($process);

        expect($said)->toHaveKey('left')
            ->and(glob("$tmp/opensim-wizard-*"))->toBe([]);
        rmdir($tmp);
    });

    test('open to the network for chosen clients: they are let in, the others are not', function () {
        $machine = gethostbyname((string) gethostname());
        [$process, $pipes] = wizardDriver(sys_get_temp_dir(), '127.0.0.1');
        $address = trim((string) fgets($pipes[1]));
        $port = (int) parse_url($address, PHP_URL_PORT);

        // From here (127.0.0.1) the address works, from the address of this machine (another client) it does not, even with the token
        $refused = $machine !== '127.0.0.1' && !str_starts_with($machine, '127.') ? browse("http://$machine:$port/?t=" . substr($address, (int) strpos($address, '?t=') + 3))[0] : 403;
        [$status, , $headers] = browse($address);
        preg_match('/' . WizardServer::COOKIE . '=([0-9a-f]+)/', $headers['set-cookie'] ?? '', $m);
        [, $page] = browse(substr($address, 0, (int) strpos($address, '/?t=')) . '/', [], $m[1] ?? '');
        browse(substr($address, 0, (int) strpos($address, '/?t=')) . '/answer', ['id' => screenId($page), 'action' => 'quit'], $m[1] ?? '');
        proc_close($process);

        expect($refused)->toBe(403)
            ->and($status)->toBe(303)
            ->and($page)->toContain('Your name');
    });
});

