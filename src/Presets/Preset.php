<?php

namespace Spatie\SuspiciousLogin\Presets;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Enums\RiskLevel;

abstract class Preset
{
    /*
     * The risk score of a login is the sum of the scores of the checks that flagged it, capped at 100.
     */
    protected int $mediumRiskThreshold = 40;

    protected int $highRiskThreshold = 70;

    /** @return array<int, Check> */
    abstract public function checks(): array;

    /** @return array<int, Action> */
    abstract public function actions(RiskLevel $riskLevel): array;

    public function riskLevel(int $score): RiskLevel
    {
        if ($score >= $this->highRiskThreshold) {
            return RiskLevel::High;
        }

        if ($score >= $this->mediumRiskThreshold) {
            return RiskLevel::Medium;
        }

        return RiskLevel::Low;
    }
}
