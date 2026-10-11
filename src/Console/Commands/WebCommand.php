<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Models\GridState;
use OpenSim\Provisioning\Web\HelpersConfig;
use OpenSim\Provisioning\Web\Services;
use OpenSim\Provisioning\Web\Snippets;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim web: the web side of a grid, the helpers (economy, search, offline messages) its viewers use.
 *
 *   opensim web [GRID] [show]            where the services are, and what the grid tells its viewers
 *   opensim web [GRID] snippet <caddy|nginx|apache> [--docroot DIR] [--socket PATH]
 *                                        what the web server needs to serve them, to include in its config
 *   opensim web [GRID] check             asks each service on the web, tells what answers
 *
 * The grid is the first word, as the instance is in other commands; it can be left out when the machine has only one
 * (--grid GRID works too). The path of the helpers is the one of the economy of the Robust config of the grid (see
 * HelpersConfig).
 */
final class WebCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim web [GRID] [show]\n       opensim web [GRID] snippet <caddy|nginx|apache> [--docroot DIR] [--socket PATH]\n       opensim web [GRID] check\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('web')->setDescription('The web side of a grid: the helpers, the snippets for the web server');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $fail = static function (string $message, int $code = 1) use ($err): int {
            self::say($err, "web: $message\n");

            return $code;
        };

        $options = ['grid' => '', 'docroot' => Snippets::DOCROOT, 'socket' => Snippets::SOCKET];
        $words = [];
        for ($i = 0; $i < count($args); $i++) {
            if (preg_match('/^--(grid|docroot|socket)(?:=(.*))?$/', $args[$i], $m)) {
                if (isset($m[2])) {
                    $options[$m[1]] = $m[2];
                } elseif (isset($args[$i + 1])) {
                    $options[$m[1]] = $args[++$i];
                } else {
                    return $fail("--{$m[1]} needs a value", 2);
                }
            } else {
                $words[] = $args[$i];
            }
        }

        // opensim web [GRID] [show|check|snippet SERVER]: the grid comes first, as the instance does in the other commands
        $actions = ['show', 'snippet', 'check'];
        $command = 'show';
        if ($words !== [] && !in_array($words[0], $actions, true)) {
            $options['grid'] = $options['grid'] ?: array_shift($words);
        }
        if ($words !== []) {
            $command = array_shift($words);
            if (!in_array($command, $actions, true)) {
                return $fail("unknown action $command\n" . self::USAGE, 2);
            }
        }

        $etc = $this->engine($out)->layout->etcRoot();
        $grids = [];
        foreach (glob("$etc/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
            if (GridState::robustIni($etc, basename($dir)) !== null) {
                $grids[] = basename($dir);
            }
        }
        if ($options['grid'] === '' && count($grids) === 1) {
            $options['grid'] = $grids[0];
        }
        if ($options['grid'] === '' || !in_array($options['grid'], $grids, true)) {
            return $fail(
                $grids === []
                    ? 'no grid of this machine: make one with `opensim setup`'
                    : ($options['grid'] === ''
                        ? 'several grids, say which: opensim web <grid> (' . implode(', ', $grids) . ')'
                        : "no grid {$options['grid']} (" . implode(', ', $grids) . ')'),
                2,
            );
        }

        $nick = $options['grid'];
        $gridDir = "$etc/grids/$nick";
        $services = HelpersConfig::services($gridDir);
        $webUrl = HelpersConfig::webUrl($gridDir);

        if ($command === 'snippet') {
            try {
                self::say($out, Snippets::render($words[0] ?? '', $nick, $options['docroot'], $options['socket'], $webUrl));
            } catch (\InvalidArgumentException $e) {
                return $fail($e->getMessage() . "\n" . self::USAGE, 2);
            }

            return self::ok();
        }

        $rows = [];
        foreach (array_keys(Services::SCRIPTS) as $service) {
            $rows[$service] = [$services->url($webUrl, $service), Services::SCRIPTS[$service]];
        }
        foreach (array_keys(Services::PAGES) as $page) {
            $rows[$page] = [$services->url($webUrl, $page), "@$page"];
        }

        if ($command === 'check') {
            $failed = false;
            foreach ($rows as $service => [$url]) {
                $headers = @get_headers($url, false, stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]));
                $status = $headers === false ? 'no answer' : (string) ($headers[0] ?? '?');
                // A script that answers, even with an error of its own (a query without arguments), is served
                $ok = $headers !== false && !preg_match('# (404|403|50[234]) #', $status);
                $failed = $failed || !$ok;
                self::say($out, sprintf("%-15s %-50s %s\n", $service, $url, $ok ? "ok ($status)" : "FAILED ($status)"));
            }

            return $failed ? 1 : 0;
        }

        self::say($out, "Grid:      $nick\n");
        self::say($out, 'Web URL:   ' . ($webUrl !== '' ? $webUrl : '(none: WebURL of the Robust config)') . "\n");
        self::say($out, "Helpers:   {$services->base()}  (the economy of the Robust config, the path of the services by default)\n");
        self::say($out, 'Docroot:   ' . $options['docroot'] . (is_dir(Snippets::WEBROOT) ? '' : '  (the helpers are not installed: apt install opensim-helpers)') . "\n\n");
        foreach ($rows as $service => [$url, $script]) {
            self::say($out, sprintf("  %-15s %s\n", $service, $url));
        }
        self::say($out, "\nThe web server needs: opensim web $nick snippet <caddy|nginx|apache>\n");

        return self::ok();
    }
}
