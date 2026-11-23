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

    protected bool $limitToThreshold = false;

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

    public function limitToThreshold(bool $limit = true): static
    {
        $this->limitToThreshold = $limit;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->ip_address) {
            return $this->pass();
        }

        $since = ($attempt->created_at ?? now())->copy()->subMinutes($this->minutes);

        $query = $attempt->newQuery()
            ->failed()
            ->since($since)
            ->where('ip_address', $attempt->ip_address)
            ->distinct();

        $accounts = $this->limitToThreshold
            ? $attempt->getConnection()->query()
                ->fromSub($query->select('identifier')->whereNotNull('identifier')->limit(max(1, $this->threshold)), 'failed_accounts')
                ->count()
            : $query->count('identifier');

        if ($accounts < $this->threshold) {
            return $this->pass();
        }

        $reason = $this->limitToThreshold
            ? 'This IP address failed to log in to at least :count accounts in the last :minutes minutes'
            : 'This IP address failed to log in to :count accounts in the last :minutes minutes';

        return $this->flag(__($reason, [
            'count' => $accounts,
            'minutes' => $this->minutes,
        ]));
    }
}
