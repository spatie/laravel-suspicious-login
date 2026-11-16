<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

/**
 * Flags a login from an IP address that recently failed to log in to many different accounts.
 */
class CredentialStuffingCheck extends Check
{
    protected int $score = 40;

    protected int $threshold = 5;

    protected int $minutes = 60;

    public function threshold(int $accounts): static
    {
        $this->threshold = $accounts;

        return $this;
    }

    public function withinMinutes(int $minutes): static
    {
        $this->minutes = $minutes;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->ip_address) {
            return $this->pass();
        }

        $since = ($attempt->created_at ?? now())->copy()->subMinutes($this->minutes);

        $accounts = $attempt->newQuery()
            ->failed()
            ->since($since)
            ->where('ip_address', $attempt->ip_address)
            ->distinct()
            ->count('identifier');

        if ($accounts < $this->threshold) {
            return $this->pass();
        }

        return $this->flag(__('This IP address failed to log in to :count accounts in the last :minutes minutes', [
            'count' => $accounts,
            'minutes' => $this->minutes,
        ]));
    }
}
