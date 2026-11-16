<?php

namespace Spatie\SuspiciousLogin\Presets;

use Spatie\SuspiciousLogin\Enums\RiskLevel;

/**
 * Runs the Balanced checks and records the results, but takes no action.
 * Use it to tune checks and thresholds before you notify or block anyone.
 */
class MonitorOnly extends Balanced
{
    public function actions(RiskLevel $riskLevel): array
    {
        return [];
    }
}
