<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class FailedAttemptsCheck extends Check
{
    protected int $score = 30;

    protected int $threshold = 5;

    protected int $minutes = 15;

    public function threshold(int $failedAttempts): static
    {
        $this->threshold = $failedAttempts;

        return $this;
    }

    public function withinMinutes(int $minutes): static
    {
        $this->minutes = $minutes;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $since = ($attempt->created_at ?? now())->copy()->subMinutes($this->minutes);

        $failedAttempts = $attempt->history()->failed()->since($since)->count();

        if ($failedAttempts < $this->threshold) {
            return $this->pass();
        }

        return $this->flag(__(':count failed login attempts in the last :minutes minutes', [
            'count' => $failedAttempts,
            'minutes' => $this->minutes,
        ]));
    }
}
