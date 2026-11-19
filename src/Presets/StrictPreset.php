<?php

namespace Spatie\SuspiciousLogin\Presets;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\CredentialStuffingCheck;
use Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\IpReputationCheck;
use Spatie\SuspiciousLogin\Checks\NewCountryCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Checks\NewIpCheck;
use Spatie\SuspiciousLogin\Checks\SuspiciousUserAgentCheck;
use Spatie\SuspiciousLogin\Checks\UnusualTimeCheck;

/**
 * Adds time and IP reputation checks to BalancedPreset, with lower thresholds,
 * and blocks high risk session logins.
 */
class StrictPreset extends Preset
{
    protected int $mediumRiskThreshold = 30;

    protected int $highRiskThreshold = 60;

    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(30),
            NewIpCheck::new()->score(10),
            NewCountryCheck::new()->score(30),
            ImpossibleTravelCheck::new()->score(60),
            FailedAttemptsCheck::new()->threshold(3)->withinMinutes(15)->score(30),
            CredentialStuffingCheck::new()->threshold(3)->score(50),
            SuspiciousUserAgentCheck::new()->score(30),
            UnusualTimeCheck::new()->score(15),
            IpReputationCheck::new()->score(50),
        ];
    }

    public function onMediumRisk(): Action|array
    {
        return NotifyUserAction::new();
    }

    public function onHighRisk(): Action|array
    {
        return [NotifyUserAction::new(), BlockLoginAction::new()];
    }
}
