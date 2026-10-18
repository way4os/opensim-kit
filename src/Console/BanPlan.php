<?php

declare(strict_types=1);

namespace OpenSimKit\Console;

use OpenSim\Config\GridConfig;
use OpenSim\Exceptions\ChannelUnavailable;
use OpenSim\Models\Avatar;
use OpenSim\Models\Estate;

/**
 * What it takes to ban people from a grid: the people found, the statements that delete what they left and the ones that ban them in
 * every estate. Nothing is run: the statements are given to be reviewed and run by the administrator of the databases.
 */
final class BanPlan
{
    /** @var list<Avatar> */
    public array $users = [];

    /** @var list<string> the lines of the file that deletes what the people left (statements and their comments) */
    public array $cleanup = [];

    /** @var list<string> the lines of the file that bans the people in the estates */
    public array $ban = [];

    /** @var list<string> the rows of hg_traveling_data of the people, for information */
    public array $traveling = [];

    /** @var list<string> what could not be done */
    public array $problems = [];

    /**
     * The plan to ban these people from the grid of a config.
     *
     * @param list<string> $targets "First Last", "First.Last", "First.Last@grid" or a UUID
     */
    public static function make(GridConfig $config, array $targets): self
    {
        $plan = new self();
        foreach ($targets as $target) {
            try {
                $found = Avatar::lookup($config, $target);
            } catch (\InvalidArgumentException $e) {
                $plan->problems[] = $e->getMessage();

                continue;
            }
            $found === [] && $plan->problems[] = sprintf("no user found for '%s'", trim($target));
            foreach ($found as $avatar) {
                if (!in_array($avatar->uuid(), array_map(static fn(Avatar $a): string => $a->uuid(), $plan->users), true)) {
                    $plan->users[] = $avatar;
                }
            }
        }

        try {
            $estates = Estate::all($config)->items();
        } catch (ChannelUnavailable $e) {
            $estates = [];
            $plan->problems[] = 'no estate can be read, nobody is banned: ' . $e->getMessage();
        }
        foreach ($plan->users as $user) {
            $leftovers = $user->leftovers();
            $plan->cleanup[] = '-- ' . $user->label() . ' (' . $user->uuid() . ')';
            array_push($plan->cleanup, ...$user->purgeSql($leftovers));
            foreach ($leftovers as $left) {
                $problem = sprintf('the database of %s is not known: nothing is deleted from it', $left['table']);
                if ($left['database'] === null && !in_array($problem, $plan->problems, true)) {
                    $plan->problems[] = $problem;
                }
            }
            foreach ($estates as $estate) {
                $statement = $estate->banSql($user->uuid());
                if ($statement !== null) {
                    $plan->ban[] = sprintf('-- %s in estate %s of %s', $user->label(), $estate->id(), $estate->connection()?->database ?? '?');
                    $plan->ban[] = $statement;
                }
            }
            foreach ($user->travelingData() as $row) {
                $plan->traveling[] = implode("\t", array_map('strval', $row));
            }
        }

        return $plan;
    }

    /** The number of statements that delete. */
    public function deletes(): int
    {
        return self::statements($this->cleanup);
    }

    /** The number of statements that ban. */
    public function bans(): int
    {
        return self::statements($this->ban);
    }

    /**
     * The files of the plan, by suffix.
     *
     * @param string $date the date written in their first line
     * @return array<string,string>
     */
    public function files(string $date): array
    {
        $lines = static fn(array $lines): string => $lines === [] ? '' : implode("\n", $lines) . "\n";

        return [
            'users' => $lines(array_map(
                static fn(Avatar $u): string => implode("\t", [$u->uuid(), $u->firstName(), $u->lastName(), $u->grid()]),
                $this->users,
            )),
            'cleanup' => $lines(["-- Delete accounts and data $date", ...$this->cleanup]),
            'ban' => $lines(["-- Ban users $date", ...$this->ban]),
            'hg_traveling_data' => $lines(["-- Hypergrid traveling data of the users $date", ...$this->traveling]),
        ];
    }

    /** @param list<string> $lines */
    private static function statements(array $lines): int
    {
        return count(array_filter($lines, static fn(string $line): bool => $line !== '' && !str_starts_with($line, '--')));
    }
}
