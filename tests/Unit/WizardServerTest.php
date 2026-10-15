<?php

use OpenSimKit\Ui\WebUi;
use OpenSimKit\Web\Mailbox;
use OpenSimKit\Web\WizardServer;

require_once __DIR__ . '/web-browser-lib.php';

const WIZARD_HOST = '127.0.0.1:8123';

/** @return array{0:WizardServer,1:Mailbox,2:string} the server, the mailbox, the cookie of a browser let in */
function wizardServer(): array
{
    $mailbox = webMailbox();
    $mailbox->allow('good-token');
    $server = new WizardServer($mailbox, [WIZARD_HOST, 'localhost:8123'], 0, 0);
    $reply = $server->handle('GET', '/', ['t' => 'good-token'], [], [], ['host' => WIZARD_HOST]);
    preg_match('/' . WizardServer::COOKIE . '=([0-9a-f]+)/', $reply[1]['Set-Cookie'], $m);

    return [$server, $mailbox, $m[1]];
}

function wizardGet(WizardServer $server, string $cookie, string $path = '/'): array
{
    return $server->handle('GET', $path, [], [], [WizardServer::COOKIE => $cookie], ['host' => WIZARD_HOST]);
}

function wizardPost(WizardServer $server, string $cookie, array $post, array $headers = []): array
{
    return $server->handle('POST', '/answer', [], $post, [WizardServer::COOKIE => $cookie], $headers + ['host' => WIZARD_HOST]);
}

afterEach(fn() => webCleanUp());

describe('who is let in', function () {
    test('address works once, it gives the browser a secret that is kept in a cookie', function () {
        $mailbox = webMailbox();
        $mailbox->allow('good-token');
        $server = new WizardServer($mailbox, [WIZARD_HOST], 0, 0);

        $first = $server->handle('GET', '/', ['t' => 'good-token'], [], [], ['host' => WIZARD_HOST]);
        $again = $server->handle('GET', '/', ['t' => 'good-token'], [], [], ['host' => WIZARD_HOST]);

        expect($first[0])->toBe(303)
            ->and($first[1]['Set-Cookie'])->toContain('HttpOnly')->toContain('SameSite=Strict')
            ->and($again[0])->toBe(403);
    });

    test('a wrong token, or no cookie, is refused', function () {
        [$server, , $cookie] = wizardServer();

        expect($server->handle('GET', '/', ['t' => 'bad'], [], [], ['host' => WIZARD_HOST])[0])->toBe(403)
            ->and($server->handle('GET', '/', [], [], [], ['host' => WIZARD_HOST])[0])->toBe(403)
            ->and($server->handle('GET', '/', [], [], [WizardServer::COOKIE => 'bad'], ['host' => WIZARD_HOST])[0])->toBe(403)
            ->and(wizardGet($server, $cookie)[0])->toBe(200);
    });

    test('a host name that is not the server is refused, whatever the cookie', function () {
        [$server, , $cookie] = wizardServer();

        $reply = $server->handle('GET', '/', [], [], [WizardServer::COOKIE => $cookie], ['host' => 'evil.example:8123']);

        expect($reply[0])->toBe(403);
    });

    test('answer of a form of the page is accepted as the browser sends it, with the origin of the page or none at all', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $id = $mailbox->publish(['kind' => 'text', 'label' => 'Name', 'value' => '']);

        // Chrome and Firefox send "null" as the origin of a form when the page asks for no referrer; the page does not, but the
        // browser also says the request comes from the same site
        $reply = wizardPost($server, $cookie, ['id' => $id, 'v' => ['value' => 'x']], ['origin' => 'null', 'sec-fetch-site' => 'same-origin']);

        expect($reply[0])->toBe(303)
            ->and($mailbox->answered($id)['values'])->toBe(['value' => 'x'])
            ->and(wizardGet($server, $cookie)[1]['Referrer-Policy'])->toBe('same-origin');
    });

    test('an answer coming from another site is refused', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $id = $mailbox->publish(['kind' => 'text', 'label' => 'Name', 'value' => '']);

        $reply = wizardPost($server, $cookie, ['id' => $id, 'v' => ['value' => 'x']], ['origin' => 'http://evil.example', 'sec-fetch-site' => 'cross-site']);
        $nullOrigin = wizardPost($server, $cookie, ['id' => $id, 'v' => ['value' => 'x']], ['origin' => 'null']);
        $own = wizardPost($server, $cookie, ['id' => $id, 'v' => ['value' => 'x']], ['origin' => 'http://127.0.0.1:8123']);

        expect($reply[0])->toBe(403)
            ->and($nullOrigin[0])->toBe(403)
            ->and($own[0])->toBe(303)
            ->and($mailbox->answered($id)['values'])->toBe(['value' => 'x']);
    });
});

describe('a server open to the network', function () {
    test('lets in the addresses it was told, whatever name they use, and refuses the others', function () {
        $mailbox = webMailbox();
        $mailbox->allow('good-token');
        $server = new WizardServer($mailbox, null, 0, 0, ['203.0.113.7']);
        $headers = ['host' => 'grid.example.org:8123'];

        $stranger = $server->handle('GET', '/', ['t' => 'good-token'], [], [], $headers, '198.51.100.9');
        $mapped = $server->handle('GET', '/', ['t' => 'good-token'], [], [], $headers, '::ffff:203.0.113.7');

        // The stranger did not use up the address: the person who may come can
        expect($stranger[0])->toBe(403)
            ->and($mapped[0])->toBe(303)
            ->and($server->handle('GET', '/', [], [], [], [], '203.0.113.7')[0])->toBe(403);
    });

    test('takes the answers of its own pages, from any name, and not those of another site', function () {
        $mailbox = webMailbox();
        $mailbox->allow('t');
        $server = new WizardServer($mailbox, null, 0, 0, null);
        $reply = $server->handle('GET', '/', ['t' => 't'], [], [], ['host' => '198.51.100.20:8123'], '203.0.113.7');
        preg_match('/' . WizardServer::COOKIE . '=([0-9a-f]+)/', $reply[1]['Set-Cookie'], $m);
        $id = $mailbox->publish(['kind' => 'text', 'label' => 'Name', 'value' => '']);
        $post = static fn(array $headers): int => $server->handle('POST', '/answer', [], ['id' => $id, 'v' => ['value' => 'x']], [WizardServer::COOKIE => $m[1]], $headers + ['host' => '198.51.100.20:8123'], '203.0.113.7')[0];

        expect($post(['origin' => 'http://evil.example']))->toBe(403)
            ->and($post(['origin' => 'http://198.51.100.20:8123']))->toBe(303);
    });
});

describe('navigation of the setup', function () {
    /** The page of a screen, and its navigation row. */
    $page = static function (array $screen, int $before = 0): array {
        [$server, $mailbox, $cookie] = wizardServer();
        for ($i = 0; $i < $before; $i++) {
            $mailbox->publish(['kind' => 'text', 'label' => 'Before', 'value' => '']);
        }
        $mailbox->publish($screen);
        $html = wizardGet($server, $cookie)[2];
        preg_match('#<div class="nav">(.*?)</div></form>#s', $html, $nav);
        preg_match('#<div class="choices">(.*?)</div>#s', $html, $choices);

        return [$html, $nav[1] ?? '', $choices[1] ?? ''];
    };
    $form = ['kind' => 'form', 'title' => 'Grid', 'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'options' => [], 'value' => '', 'hint' => null, 'required' => true, 'hasDefault' => false, 'error' => null, 'condition' => null, 'shown' => true, 'rendered' => true]]];

    test('a menu has its Back and Quit in the navigation, not among its choices, and not twice', function () use ($page) {
        [$html, $nav, $choices] = $page(['kind' => 'choose', 'label' => 'Grid', 'options' => ['configure' => 'Configure grid', 'back' => 'Back', 'quit' => 'Quit'], 'default' => 'configure', 'hint' => null], 1);

        expect($choices)->toContain('value="configure"')->not->toContain('value="back"')->not->toContain('value="quit"')
            ->and($nav)->toContain('name="v[value]" value="back"')->toContain('name="v[value]" value="quit"')
            ->and(substr_count($html, '>Back<'))->toBe(1)->and(substr_count($html, '>Quit<'))->toBe(1)
            ->and(substr_count($html, '<form'))->toBe(1);
    });

    test('home menu has no Back, only its Quit', function () use ($page) {
        [, $nav] = $page(['kind' => 'choose', 'label' => 'OpenSim — setup', 'options' => ['add' => 'Add grid', 'quit' => 'Quit'], 'default' => 'add', 'hint' => null]);

        expect($nav)->toContain('value="quit"')->not->toContain('value="back"')->not->toContain('Cancel');
    });

    test('choice that is proposed stays a choice, whatever its key: "Finish setup" is the one to press', function () use ($page) {
        [, $nav, $choices] = $page(['kind' => 'choose', 'label' => 'Setup', 'options' => ['quit' => 'Finish setup', 'more' => 'Continue setup'], 'default' => 'quit', 'hint' => null], 4);

        expect($choices)->toContain('value="quit" class="primary">Finish setup')->toContain('value="more"')
            ->and($nav)->not->toContain('Finish setup');
    });

    test('a form has Cancel, Quit and Continue in one row, in the same form as its fields; Cancel gives up the screen, it does not go back', function () use ($page, $form) {
        [$html, $nav] = $page($form, 2);

        expect($nav)->toContain('>Continue<')->toContain('>Cancel<')->toContain('Quit the setup')
            ->and($nav)->toContain('name="action" value="back"')->toContain('name="action" value="quit"')
            ->and(substr_count($html, '<form'))->toBe(1)
            ->and($html)->not->toContain('>Back<');
        // Continue is the first button of the page, the one Enter presses
        expect(strpos($nav, 'Continue'))->toBeLessThan((int) strpos($nav, 'Cancel'));
    });

    test('and on the first screen there is no Cancel, which would be the same as Quit', function () use ($page, $form) {
        [, $nav] = $page($form);

        expect($nav)->toContain('>Continue<')->toContain('Quit the setup')->not->toContain('Cancel');
    });
});

describe('screens', function () {
    test('page shows the screen, what was said before it, and escapes what comes from outside', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $mailbox->say('note', "Plan <b>\n  Grid: " . "\u{E000}home\u{E001}");
        $mailbox->publish(['kind' => 'text', 'label' => 'Name <script>', 'value' => 'a"b']);

        $page = wizardGet($server, $cookie)[2];

        expect($page)->toContain('Name &lt;script&gt;')
            ->toContain('value="a&quot;b"')
            ->toContain('Plan &lt;b&gt;')
            ->toContain('<strong>home</strong>')
            ->not->toContain('<script>Name');
    });

    test('before the first screen, and once answered, the page is the one of the work being done', function () {
        [$server, $mailbox, $cookie] = wizardServer();

        expect(wizardGet($server, $cookie)[2])->toContain('http-equiv="refresh"');

        $id = $mailbox->publish(['kind' => 'text', 'label' => 'Name', 'value' => '']);
        wizardPost($server, $cookie, ['id' => $id, 'v' => ['value' => 'x']]);

        expect(wizardGet($server, $cookie)[2])->toContain('spinner')->not->toContain('name="v[value]"');
    });

    test('a screen the setup took the answer of is still answered while the setup works, and takes no other answer', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $id = $mailbox->publish(['kind' => 'text', 'label' => 'Name', 'value' => '']);
        wizardPost($server, $cookie, ['id' => $id, 'v' => ['value' => 'x']]);

        $mailbox->consume($id); // the setup read the answer and goes on, for as long as it takes
        $page = wizardGet($server, $cookie)[2];
        wizardPost($server, $cookie, ['id' => $id, 'v' => ['value' => 'again']]);

        expect($page)->toContain('spinner')->not->toContain('name="v[value]"')
            ->and($mailbox->answered($id))->toBeNull()
            ->and(file_exists("{$mailbox->dir}/answer.json"))->toBeFalse();
    });

    test('a choice is a list of buttons, the default one marked', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $mailbox->publish(['kind' => 'choose', 'label' => 'What', 'options' => ['grid' => 'A grid', 'sim' => 'A simulator'], 'default' => 'sim', 'hint' => null]);

        $page = wizardGet($server, $cookie)[2];

        expect($page)->toContain('name="v[value]" value="grid" class=""')
            ->toContain('name="v[value]" value="sim" class="primary"');
    });

    test('a form keeps the condition of its fields as data for the page, and hides the ones that are not there', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $fields = [
            ['key' => 'mode', 'label' => 'Mode', 'type' => 'confirm', 'default' => 'no'],
            ['key' => 'extra', 'label' => 'Extra', 'required' => false, 'condition' => ['mode' => ['yes']], 'when' => static fn(array $v): bool => ($v['mode'] ?? '') === 'yes'],
        ];
        $pages = [];
        $watch = new WebUi($mailbox, static function () use ($mailbox, $server, $cookie, &$pages): void {
            $pages[] = wizardGet($server, $cookie)[2];
            $mailbox->answer($mailbox->current(), ['values' => ['mode' => 'no']]);
        }, 0, 1.0);
        $watch->form($fields, 'Grid');

        expect($pages[0])->toContain('data-condition="{&quot;mode&quot;:[&quot;yes&quot;]}"')->toContain(' hidden>');
    });

    test('an answer to a screen that is not the current one is of no use', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $old = $mailbox->publish(['kind' => 'text', 'label' => 'One', 'value' => '']);
        $current = $mailbox->publish(['kind' => 'text', 'label' => 'Two', 'value' => '']);

        wizardPost($server, $cookie, ['id' => $old, 'v' => ['value' => 'late']]);

        expect($mailbox->answered($old))->toBeNull()
            ->and($mailbox->answered($current))->toBeNull();
    });

    test('Back and Quit are answers like the others', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $id = $mailbox->publish(['kind' => 'text', 'label' => 'One', 'value' => '']);

        wizardPost($server, $cookie, ['id' => $id, 'action' => 'quit']);

        expect($mailbox->answered($id)['action'])->toBe('quit');
    });

    test('page that ends the setup is noted as seen', function () {
        [$server, $mailbox, $cookie] = wizardServer();
        $id = $mailbox->publish(['kind' => 'done', 'message' => 'All done']);

        $page = wizardGet($server, $cookie)[2];

        expect($page)->toContain('All done')
            ->and($mailbox->hasSeen($id))->toBeTrue();
    });
});
