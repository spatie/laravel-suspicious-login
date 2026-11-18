<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class MultipleCountriesCheck extends Check
{
    protected int $score = 40;

    protected int $minutes = 60;

    public function withinMinutes(int $minutes): static
    {
        $this->minutes = $minutes;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->country_code) {
            return $this->pass();
        }

        $since = ($attempt->created_at ?? now())->copy()->subMinutes($this->minutes);

        $loggedInFromAnotherCountry = $attempt->history()
            ->trusted()
            ->since($since)
            ->where('country_code', '!=', $attempt->country_code)
            ->exists();

        if (! $loggedInFromAnotherCountry) {
            return $this->pass();
        }

        return $this->flag(__('Logins from multiple countries in the last :minutes minutes', [
            'minutes' => $this->minutes,
        ]));
    }
}
