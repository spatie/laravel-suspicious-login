<?php

namespace Spatie\SuspiciousLogin\Presets;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\CredentialStuffingCheck;
use Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\NewCountryCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;

/**
 * Only notifies users about logins that trip several checks at once.
 */
class Lenient extends Preset
{
    protected int $mediumRiskThreshold = 50;

    protected int $highRiskThreshold = 80;

    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(20),
            NewCountryCheck::new()->score(30),
            ImpossibleTravelCheck::new()->score(60),
            FailedAttemptsCheck::new()->threshold(10)->withinMinutes(60)->score(30),
            CredentialStuffingCheck::new()->threshold(10)->score(40),
        ];
    }

    public function onHighRisk(): Action|array
    {
        return NotifyUserAction::new();
    }
}
