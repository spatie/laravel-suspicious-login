<?php

namespace Spatie\SuspiciousLogin\Presets;

use Illuminate\Support\Arr;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Enums\RiskLevel;

abstract class Preset
{
    protected int $mediumRiskThreshold;

    protected int $highRiskThreshold;

    /** @return array<int, Check> */
    abstract public function checks(): array;

    /**
     * The actions to run for a medium risk login.
     *
     * @return Action|array<int, Action>
     */
    public function onMediumRisk(): Action|array
    {
        return [];
    }

    /**
     * The actions to run for a high risk login.
     *
     * @return Action|array<int, Action>
     */
    public function onHighRisk(): Action|array
    {
        return [];
    }

    /**
     * Only the actions for the given level run; high risk does not also run medium risk actions.
     *
     * @return array<int, Action>
     */
    public function actions(RiskLevel $riskLevel): array
    {
        return Arr::wrap(match ($riskLevel) {
            RiskLevel::Low => [],
            RiskLevel::Medium => $this->onMediumRisk(),
            RiskLevel::High => $this->onHighRisk(),
        });
    }

    public function riskLevel(int $score): RiskLevel
    {
        if ($score >= ($this->highRiskThreshold ?? config('suspicious-login.risk_thresholds.high'))) {
            return RiskLevel::High;
        }

        if ($score >= ($this->mediumRiskThreshold ?? config('suspicious-login.risk_thresholds.medium'))) {
            return RiskLevel::Medium;
        }

        return RiskLevel::Low;
    }
}
