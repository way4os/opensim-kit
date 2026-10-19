<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Security\ApiKeys;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim api-key: the keys that open the API of the engine on the web (see Security\ApiKeys). A key is shown once, when it is made.
 *
 *   opensim api-key create LABEL     make a key
 *   opensim api-key list             the keys, without what opens them
 *   opensim api-key revoke ID|LABEL  close a key
 */
final class ApiKeyCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim api-key create LABEL\n       opensim api-key list\n       opensim api-key revoke ID|LABEL\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('api-key')->setDescription('Make, list and revoke the keys that open the API');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $keys = ApiKeys::for($this->engine($out));
        $verb = array_shift($args);
        switch ($verb) {
            case 'create':
                $label = implode(' ', $args);
                if (trim($label) === '') {
                    self::say($err, "api-key: a key needs a label (what it is for)\n" . self::USAGE);

                    return 2;
                }
                try {
                    $made = $keys->create($label);
                } catch (\RuntimeException $e) {
                    self::say($err, 'api-key: ' . $e->getMessage() . "\n");

                    return 1;
                }
                self::say($out, $made['key'] . "\n");
                self::say($err, "key {$made['id']} made for '$label': keep it now, it is not shown again. Send it as: Authorization: Bearer <key>\n");

                return self::ok();
            case 'list':
                $rows = [['ID', 'LABEL', 'CREATED', 'STATE']];
                foreach ($keys->all() as $key) {
                    $rows[] = [
                        $key['id'],
                        $key['label'],
                        date('Y-m-d H:i', $key['created']),
                        $key['revoked'] === null ? 'active' : 'revoked ' . date('Y-m-d H:i', $key['revoked']),
                    ];
                }
                $widths = [];
                foreach ($rows as $row) {
                    foreach ($row as $column => $cell) {
                        $widths[$column] = max($widths[$column] ?? 0, strlen($cell));
                    }
                }
                foreach ($rows as $row) {
                    $cells = [];
                    foreach ($row as $column => $cell) {
                        $cells[] = $column === count($row) - 1 ? $cell : str_pad($cell, $widths[$column] + 2);
                    }
                    self::say($out, implode('', $cells) . "\n");
                }

                return self::ok();
            case 'revoke':
                $which = implode(' ', $args);
                if ($which === '' || !$keys->revoke($which)) {
                    self::say($err, 'api-key: ' . ($which === '' ? 'revoke which key?' : "no active key called '$which'") . "\n" . self::USAGE);

                    return $which === '' ? 2 : 1;
                }
                self::say($err, "key '$which' is revoked\n");

                return self::ok();
        }
        self::say($err, self::USAGE);

        return 2;
    }
}
