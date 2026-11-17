<?php

namespace Spatie\SuspiciousLogin\Presets;

use Spatie\SuspiciousLogin\Actions\Action;

/**
 * Runs the Balanced checks and records the results, but takes no action.
 * Use it to tune checks and thresholds before you notify or block anyone.
 */
class MonitorOnly extends Balanced
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
