<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

/**
 * opensim load iar|oar: see ArchiveCommand.
 */
final class LoadCommand extends ArchiveCommand
{
    protected function verb(): string
    {
        return 'load';
    }
}
