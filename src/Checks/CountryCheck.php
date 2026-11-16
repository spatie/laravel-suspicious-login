<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class CountryCheck extends Check
{
    protected int $score = 50;

    /** @var array<int, string> */
    protected array $allowedCountries = [];

    /** @var array<int, string> */
    protected array $blockedCountries = [];

    /** @param  array<int, string>  $countryCodes */
    public function onlyAllow(array $countryCodes): static
    {
        $this->allowedCountries = array_map('strtoupper', $countryCodes);

        return $this;
    }

    /** @param  array<int, string>  $countryCodes */
    public function block(array $countryCodes): static
    {
        $this->blockedCountries = array_map('strtoupper', $countryCodes);

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->country_code) {
            return $this->pass();
        }

        if (in_array($attempt->country_code, $this->blockedCountries)) {
            return $this->flag(__('Login from a blocked country (:country)', ['country' => $attempt->country_code]));
        }

        if ($this->allowedCountries === []) {
            return $this->pass();
        }

        if (in_array($attempt->country_code, $this->allowedCountries)) {
            return $this->pass();
        }

        return $this->flag(__('Login from a country that is not allowed (:country)', ['country' => $attempt->country_code]));
    }
}
