<?php

namespace Spatie\SuspiciousLogin\Presets;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\CredentialStuffingCheck;
use Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\NewCountryCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Checks\NewIpCheck;
use Spatie\SuspiciousLogin\Checks\SuspiciousUserAgentCheck;

/**
 * Notifies users about suspicious logins, but never blocks them.
 */
class BalancedPreset extends Preset
{
    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(25),
            NewIpCheck::new()->score(10),
            NewCountryCheck::new()->score(30),
            ImpossibleTravelCheck::new()->score(60),
            FailedAttemptsCheck::new()->threshold(5)->withinMinutes(15)->score(30),
            CredentialStuffingCheck::new()->threshold(5)->score(40),
            SuspiciousUserAgentCheck::new()->score(20),
        ];
    }

    public function onMediumRisk(): Action|array
    {
        return NotifyUserAction::new();
    }

    public function onHighRisk(): Action|array
    {
        return NotifyUserAction::new();
    }
}
