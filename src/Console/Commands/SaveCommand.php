<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

/**
 * opensim save iar|oar: see ArchiveCommand.
 */
final class SaveCommand extends ArchiveCommand
{
    protected function verb(): string
    {
        return 'save';
    }
}
