<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

/**
 * opensim disable <instance>... | <grid> search|economy|profiles: see ToggleCommand.
 */
final class DisableCommand extends ToggleCommand
{
    protected function verb(): string
    {
        return 'disable';
    }
}
