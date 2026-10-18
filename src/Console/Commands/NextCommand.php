<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Runtime\Environment;
use OpenSimKit\Ui\QuietUi;
use OpenSim\Models\Grid;
use OpenSim\Network\Ports;
use OpenSim\Provisioning\DatabaseService;
use OpenSim\Provisioning\GridRegistry;
use OpenSim\Provisioning\LocationFinder;
use OpenSim\Provisioning\Places;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim next: the next free port or place, by the rules of the setup (the same code, so that every command gives the same
 * answer).
 *
 *   opensim next port [-e PORT] [MIN] [COUNT]
 *       COUNT free ports from MIN (default 9010), none of them in a config file, bound on this machine, or given with -e
 *   opensim next GRID location [X,Y] [COUNT] [-e X,Y]
 *       COUNT free places nearest to X,Y (default: the center of the grid) in the grid, by its rule (the free blocks it leaves
 *       between its regions); the places given with -e are taken
 *
 * -e can be given several times.
 */
final class NextCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim next port [-e PORT] [MIN] [COUNT]\n       opensim next GRID location [X,Y] [COUNT] [-e X,Y]\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('next')->setDescription('The next free port or place, by the rules of the setup');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $what = $args[0] ?? '';
        // opensim next GRID location ...: the grid comes first, as the instance does in the other commands
        $gridFirst = null;
        $rest = array_slice($args, 1);
        if ($what !== '' && !in_array($what, ['port', 'location'], true) && ($args[1] ?? '') === 'location') {
            $gridFirst = $what;
            $what = 'location';
            $rest = array_slice($args, 2);
        }
        if (!in_array($what, ['port', 'location'], true)) {
            return $this->usage($err, $what === '' ? 'port or location?' : "unknown: $what");
        }

        $exclude = [];
        $words = $gridFirst === null ? [] : [$gridFirst];
        while ($rest !== []) {
            $arg = array_shift($rest);
            if ($arg === '-e') {
                $exclude[] = (string) array_shift($rest);
            } else {
                $words[] = $arg;
            }
        }
        $number = static fn(?string $value, int $default): int => $value === null ? $default : (ctype_digit($value) ? (int) $value : -1);
        $profile = $this->engine($out)->layout->all();

        if ($what === 'port') {
            $min = $number($words[0] ?? null, 9010);
            $count = $number($words[1] ?? null, 1);
            if ($min < 1 || $count < 1 || count($words) > 2 || array_filter($exclude, static fn(string $e): bool => !ctype_digit($e)) !== []) {
                return $this->usage($err, 'MIN, COUNT and the ports given with -e are numbers');
            }
            // What is taken is what the config files of this machine say, what listens here, and the ports of the regions the
            // grids of this machine know, the ones of other machines too
            $known = (new GridRegistry(new DatabaseService(new QuietUi())))->knownPorts($profile);
            self::say($out, implode("\n", Ports::nextFree($min, $count, array_merge(array_map('intval', $exclude), $known))) . "\n");

            return self::ok();
        }

        $nick = $words[0] ?? '';
        $wish = isset($words[1]) ? LocationFinder::parse($words[1]) : [];
        $count = $number($words[2] ?? null, 1);
        if ($nick === '' || $wish === null || $count < 1 || count($words) > 3) {
            return $this->usage($err, 'a grid, then a place as x,y and a number');
        }
        $grid = Grid::find(Environment::forProfile($profile), $nick);
        if ($grid === null) {
            self::say($err, "next: no grid named '$nick'\n");

            return 1;
        }
        $known = (new GridRegistry(new DatabaseService(new QuietUi())))->locations($grid);
        foreach ($exclude as $place) {
            if (($taken = LocationFinder::parse($place)) === null) {
                return $this->usage($err, "'$place' is not a place, use x,y");
            }
            LocationFinder::take($known, $taken[0], $taken[1]);
        }
        $wish = $wish ?: $grid->centerPlace();
        $unreachable = false;
        for ($i = 0; $i < $count; $i++) {
            [$x, $y] = Places::nearestFree($grid, $known, $wish[0], $wish[1], null, $unreachable);
            self::say($out, "$x,$y\n");
            LocationFinder::take($known, $x, $y);
        }
        if ($unreachable) {
            self::say($err, "next: the grid does not answer at {$grid->baseHostname()}:{$grid->privatePort()}, its regions are not all known here\n");
        }

        return self::ok();
    }

    /** Say what is wrong. */
    private function usage(OutputInterface $err, string $message): int
    {
        self::say($err, "next: $message\n" . self::USAGE);

        return 2;
    }
}
