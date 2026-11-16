<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class NewDeviceCheck extends Check
{
    protected int $score = 25;

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $trustedLogins = $attempt->history()->trusted();

        if (! $trustedLogins->exists()) {
            return $this->pass();
        }

        if ($trustedLogins->where('device_fingerprint', $attempt->device_fingerprint)->exists()) {
            return $this->pass();
        }

        return $this->flag(__('Login from a new device'));
    }
}
