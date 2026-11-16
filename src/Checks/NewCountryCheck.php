<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class NewCountryCheck extends Check
{
    protected int $score = 30;

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->country_code) {
            return $this->pass();
        }

        $trustedLoginsWithCountry = $attempt->history()->trusted()->whereNotNull('country_code');

        if (! $trustedLoginsWithCountry->exists()) {
            return $this->pass();
        }

        if ($trustedLoginsWithCountry->where('country_code', $attempt->country_code)->exists()) {
            return $this->pass();
        }

        return $this->flag(__('Login from a new country (:country)', ['country' => $attempt->country_code]));
    }
}
