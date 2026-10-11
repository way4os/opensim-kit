<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\OpenSim;
use OpenSim\Runtime\Status;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim status: the enabled instances of this machine, running or not, what they use, and a summary line.
 */
final class StatusCommand extends EngineCommand
{
    protected function configure(): void
    {
        $this->setName('status')->setDescription('The enabled instances of this machine, running or not');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write(self::render(OpenSim::status($this->engine($output)), $output->isDecorated()));

        return Command::SUCCESS;
    }

    /** The table of the instances, then the summary line. */
    public static function render(Status $status, bool $color = false): string
    {
        $rows = [['INSTANCE', 'TYPE', 'PID', 'USER', '%CPU', '%MEM', 'RSS']];
        foreach ($status->instances as $item) {
            $rows[] = $item->process === null
                ? [$item->name(), $item->type(), '[down]']
                : [$item->name(), $item->type(), (string) $item->process->pid, $item->process->user, self::number($item->process->cpu), self::number($item->process->memory), (string) $item->process->rss];
        }
        $widths = [];
        foreach ($rows as $row) {
            foreach ($row as $column => $cell) {
                $widths[$column] = max($widths[$column] ?? 0, strlen($cell));
            }
        }
        $text = '';
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $column => $cell) {
                $cells[] = $column === count($row) - 1 ? $cell : str_pad($cell, $widths[$column] + 2);
            }
            $text .= implode('', $cells) . "\n";
        }
        $totals = $status->totals();

        return $text . self::summary($status->up(), $status->down(), sprintf('cpu %.1f%% - mem %.1f%% - rss %d', $totals['cpu'], $totals['memory'], $totals['rss']), $color) . "\n";
    }

    /** The summary line: the instances down only when there are some. */
    public static function summary(int $up, int $down, string $stats, bool $color = false): string
    {
        $line = $color ? "\033[32m$up up\033[0m" : "$up up";
        if ($down > 0) {
            $line .= $color ? ", \033[31m$down down\033[0m" : ", $down down";
        }

        return "$line - $stats";
    }

    private static function number(float $value): string
    {
        return sprintf('%.1f', $value);
    }
}
