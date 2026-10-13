<?php

declare(strict_types=1);

/**
 * Router of the web server of the setup (php -S): every request goes to WizardServer, which finds the setup through the folder
 * named by OPENSIM_WIZARD_DIR. The server listens on 127.0.0.1 only, it is started and stopped by the setup.
 */

use OpenSimKit\Web\Mailbox;
use OpenSimKit\Web\WizardServer;

require dirname(__DIR__) . '/vendor/autoload.php';

$port = (string) getenv('OPENSIM_WIZARD_PORT');
// Open to the network (the setup says so): any host name, and only the clients it names; else this machine only
$open = getenv('OPENSIM_WIZARD_OPEN') === '1';
$allow = (string) getenv('OPENSIM_WIZARD_ALLOW');
$server = new WizardServer(
    new Mailbox((string) getenv('OPENSIM_WIZARD_DIR')),
    $open ? null : ["127.0.0.1:$port", "localhost:$port"],
    allow: $allow === '' ? null : array_map(WizardServer::address(...), explode(',', $allow)),
);

$headers = array_change_key_case(array_filter(getallheaders() ?: [], 'is_string'), CASE_LOWER);
[$status, $out, $body] = $server->handle(
    $_SERVER['REQUEST_METHOD'],
    (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    $_GET,
    $_POST,
    $_COOKIE,
    $headers,
    (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
);

http_response_code($status);
foreach ($out as $name => $value) {
    header("$name: $value");
}
echo $body;

return true;
