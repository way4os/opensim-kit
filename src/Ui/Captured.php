<?php

declare(strict_types=1);

namespace OpenSimKit\Ui;

use OpenSim\Provisioning\UiInterface;

/**
 * Which interface takes what the programs started by the setup say, behind the ones that only answer for it (PresetUi).
 */
final class Captured
{
    /** @return ?CapturesOutput the interface that shows the output of programs, null when it is the terminal */
    public static function by(UiInterface $ui): ?CapturesOutput
    {
        while ($ui instanceof PresetUi) {
            $ui = $ui->inner();
        }

        return $ui instanceof CapturesOutput ? $ui : null;
    }
}
