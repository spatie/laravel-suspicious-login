<?php

namespace Spatie\SuspiciousLogin\Presets;

use Spatie\SuspiciousLogin\Actions\Action;

/**
 * Runs the BalancedPreset checks and records the results, but takes no action.
 * Use it to tune checks and thresholds before you notify or block anyone.
 */
class MonitorOnlyPreset extends BalancedPreset
{
    public function onMediumRisk(): Action|array
    {
        return [];
    }

    public function onHighRisk(): Action|array
    {
        return [];
    }
}
