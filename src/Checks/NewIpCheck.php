<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class NewIpCheck extends Check
{
    protected int $score = 10;

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $trustedLogins = $attempt->history()->trusted();

        if (! $trustedLogins->exists()) {
            return $this->pass();
        }

        if ($trustedLogins->where('ip_address', $attempt->ip_address)->exists()) {
            return $this->pass();
        }

        return $this->flag(__('Login from a new IP address (:ip)', ['ip' => $attempt->ip_address]));
    }
}
