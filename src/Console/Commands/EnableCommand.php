<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

/**
 * opensim enable <instance>... | <grid> search|economy|profiles: see ToggleCommand.
 */
final class EnableCommand extends ToggleCommand
{
    protected function verb(): string
    {
        return 'enable';
    }
}
