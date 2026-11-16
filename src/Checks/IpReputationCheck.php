<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\SuspiciousLogin;

/**
 * Uses the configured IP reputation driver to flag Tor exit nodes and IPs reported for abuse.
 */
class IpReputationCheck extends Check
{
    protected int $score = 50;

    protected int $minimumAbuseScore = 50;

    protected bool $flagTor = true;

    public function __construct(
        protected SuspiciousLogin $suspiciousLogin,
    ) {}

    public function minimumAbuseScore(int $abuseScore): static
    {
        $this->minimumAbuseScore = $abuseScore;

        return $this;
    }

    public function allowTor(): static
    {
        $this->flagTor = false;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->ip_address) {
            return $this->pass();
        }

        $reputation = $this->suspiciousLogin->ipReputation($attempt->ip_address);

        if ($this->flagTor) {
            if ($reputation->isTor) {
                return $this->flag(__('Login from a Tor exit node'));
            }
        }

        if (($reputation->abuseScore ?? 0) >= $this->minimumAbuseScore) {
            return $this->flag(__('Login from an IP address reported for abuse (score :score)', ['score' => $reputation->abuseScore]));
        }

        return $this->pass();
    }
}
