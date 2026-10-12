<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Models\Grid;
use OpenSim\Moderation\UserBan;
use OpenSim\Provisioning\DatabaseService;
use OpenSimKit\Ui\QuietUi;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim ban: the statements that ban users from a grid and delete their data.
 *
 *   opensim ban [GRID] NAME|UUID... [--output DIR]
 *
 * Users are found by name ("First Last", First.Last, First.Last@grid for a visitor of another grid) or UUID in the database
 * of the grid. Nothing is changed: the statements are written in ban.cleanup (accounts, inventory, assets and objects) and
 * ban.ban (estate ban lists), with the users found (ban.users) and their hypergrid traveling data (ban.hg_traveling_data),
 * for the administrator of the databases to review and run, with the simulators stopped.
 */
final class BanCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim ban [GRID] NAME|UUID... [--output DIR]\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('ban')->setDescription('The statements that ban users from a grid and delete their data');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $fail = static function (string $message, int $code = 1) use ($err): int {
            self::say($err, "ban: $message\n");

            return $code;
        };

        $output = getcwd() ?: '.';
        $words = [];
        for ($i = 0; $i < count($args); $i++) {
            if (preg_match('/^--output(?:=(.*))?$/', $args[$i], $m)) {
                $output = $m[1] ?? ($args[++$i] ?? '');
            } else {
                $words[] = $args[$i];
            }
        }

        $environment = $this->engine($err);
        $grids = array_values(array_filter(Grid::all($environment), static fn(Grid $grid): bool => !$grid->isRemote()));
        $nicks = array_map(static fn(Grid $grid): string => $grid->nick(), $grids);
        $grid = null;
        if ($words !== [] && in_array($words[0], $nicks, true)) {
            $grid = $grids[array_search(array_shift($words), $nicks, true)];
        } elseif (count($grids) === 1) {
            $grid = $grids[0];
        }
        if ($grid === null) {
            return $fail($grids === [] ? 'no grid of this machine' : 'several grids, say which: opensim ban <grid> NAME|UUID... (' . implode(', ', $nicks) . ')', 2);
        }
        if ($words === [] || $output === '') {
            self::say($err, self::USAGE);

            return 2;
        }
        if (!is_dir($output) || !is_writable($output)) {
            return $fail(sprintf(_('Cannot write in %s'), $output));
        }

        [$robust, $others] = UserBan::databases($grid);
        if ($robust === null) {
            return $fail(sprintf(_('No database in the Robust config of %s'), $grid->nick()));
        }
        $database = new DatabaseService(new QuietUi());
        if ($database->query($robust, 'SELECT 1') === null) {
            return $fail(sprintf(_('Cannot read the database %s of %s (%s@%s)'), $robust->database, $grid->nick(), $robust->user, $robust->source));
        }

        $plan = (UserBan::with($database))->plan($words, $robust, $others);
        foreach ($plan->problems as $problem) {
            self::say($err, "ban: $problem\n");
        }
        if ($plan->users === []) {
            return 1;
        }

        $files = [];
        foreach ($plan->files(date('Y-m-d H:i:s')) as $suffix => $text) {
            file_put_contents($files[$suffix] = rtrim($output, '/') . "/ban.$suffix", $text);
        }
        foreach ($plan->users as $user) {
            self::say($out, sprintf("%s\t%s\n", $user->uuid, $user->label()));
        }
        $deletes = $plan->deletes();
        $bans = $plan->bans();
        self::say($out, sprintf(_("%d delete and %d ban statements in %s and %s (the users in %s, their hypergrid data in %s)\n"), $deletes, $bans, $files['cleanup'], $files['ban'], $files['users'], $files['hg_traveling_data']));
        if ($deletes + $bans > 0) {
            self::say($out, sprintf(_("The databases are not changed. Back them up, stop the simulators, read the files, then run:\n    cat %s %s | mysql\n"), $files['cleanup'], $files['ban']));
        }

        return self::ok();
    }
}
