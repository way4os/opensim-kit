<?php

namespace OpenSimKit\Setup;

use OpenSim\Config\DirectoryLayout as Layouts;

/**
 * The layouts of an install as the setup proposes them: the engine knows them (OpenSim\Config\DirectoryLayout), the setup only
 * adds where a flat install goes by default, next to the kit.
 */
final class DirectoryLayout
{
    /** @var array<string,string> key => menu label */
    public const LABELS = Layouts::LABELS;

    /** Whether this layout needs a base install path from the user. */
    public static function needsBase(string $layout): bool
    {
        return Layouts::needsBase($layout);
    }

    public static function defaultBase(string $layout): string
    {
        return $layout === Layouts::FLAT ? dirname(__DIR__, 2) . '/core' : Layouts::defaultBase($layout);
    }

    /** Fill $plan with this layout's default locations (before user edits). */
    public static function applyDefaults(InstallPlan $plan): void
    {
        $roots = Layouts::roots($plan->layout, $plan->installPath, $plan->version);
        $plan->coreRoot = $roots['CoreRoot'] ?? '';
        $plan->coreDirectory = $roots['CoreDirectory'] ?? '';
        $plan->etcRoot = $roots['EtcRoot'] ?? '';
        $plan->varRoot = $roots['VarRoot'] ?? '';
        $plan->logsRoot = $roots['LogsRoot'] ?? '';
        $plan->cacheRoot = $roots['CacheRoot'] ?? '';
        $plan->dataRoot = $roots['DataRoot'] ?? '';
    }
}
