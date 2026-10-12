<?php

declare(strict_types=1);

namespace OpenSimKit\Setup\Import;

/**
 * What stops an import, with the status the command ends with.
 */
final class ImportFailure extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 1)
    {
        parent::__construct($message);
    }
}
