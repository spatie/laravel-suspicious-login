<?php

namespace Spatie\SuspiciousLogin\Presets;

use Illuminate\Support\Arr;
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

    /** @return array<int, Action> */
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
        if ($score >= $this->highRiskThreshold) {
            return RiskLevel::High;
        }

        if ($score >= $this->mediumRiskThreshold) {
            return RiskLevel::Medium;
        }

        return RiskLevel::Low;
    }
}
