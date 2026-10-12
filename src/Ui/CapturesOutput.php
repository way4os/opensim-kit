<?php

declare(strict_types=1);

namespace OpenSimKit\Ui;

/**
 * A frontend that is not the terminal of the process: what the programs started by the setup say does not go to the screen
 * by itself, it is given to the frontend to show.
 */
interface CapturesOutput
{
    /** What a program said, as it came. */
    public function stream(string $chunk): void;
}
