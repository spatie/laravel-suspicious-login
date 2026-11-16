<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

abstract class Check
{
    /**
     * The points this check adds to the risk score of a login when it flags it.
     */
    protected int $score = 25;

    public static function new(): static
    {
        return app(static::class);
    }

    public function score(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    abstract public function evaluate(LoginAttempt $attempt): CheckResult;

    protected function pass(): CheckResult
    {
        return CheckResult::passed(static::class);
    }

    protected function flag(string $reason): CheckResult
    {
        return CheckResult::suspicious(static::class, $this->score, $reason);
    }
}
