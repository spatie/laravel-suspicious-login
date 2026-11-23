<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class FailedAttemptsCheck extends Check
{
    protected int $score = 30;

    protected int $threshold = 5;

    protected int $minutes = 15;

    protected bool $limitToThreshold = false;

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

    public function limitToThreshold(bool $limit = true): static
    {
        $this->limitToThreshold = $limit;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $since = ($attempt->created_at ?? now())->copy()->subMinutes($this->minutes);

        $query = $attempt->history()->failed()->since($since);

        $failedAttempts = $this->limitToThreshold
            ? $attempt->getConnection()->query()
                ->fromSub($query->select($attempt->getKeyName())->limit(max(1, $this->threshold)), 'failed_attempts')
                ->count()
            : $query->count();

        if ($failedAttempts < $this->threshold) {
            return $this->pass();
        }

        $reason = $this->limitToThreshold
            ? 'at least :count failed login attempts in the last :minutes minutes'
            : ':count failed login attempts in the last :minutes minutes';

        return $this->flag(__($reason, [
            'count' => $failedAttempts,
            'minutes' => $this->minutes,
        ]));
    }
}
