<?php

namespace Spatie\SuspiciousLogin\Checks;

use Carbon\CarbonInterface;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

/**
 * Flags a login at an hour of the day at which the user has never logged in before.
 */
class UnusualTimeCheck extends Check
{
    protected int $score = 15;

    protected int $minimumLogins = 10;

    protected int $toleranceInHours = 2;

    protected ?string $timezone = null;

    public function minimumLogins(int $logins): static
    {
        $this->minimumLogins = $logins;

        return $this;
    }

    public function toleranceInHours(int $hours): static
    {
        $this->toleranceInHours = $hours;

        return $this;
    }

    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $previousLoginHours = $attempt->history()
            ->trusted()
            ->latest()
            ->limit(100)
            ->pluck('created_at')
            ->map(fn (CarbonInterface $createdAt) => $this->hourOf($createdAt));

        if ($previousLoginHours->count() < $this->minimumLogins) {
            return $this->pass();
        }

        $hour = $this->hourOf($attempt->created_at ?? now());

        $hasLoggedInAroundThisHour = $previousLoginHours->contains(function (int $previousHour) use ($hour) {
            $difference = abs($previousHour - $hour);

            return min($difference, 24 - $difference) <= $this->toleranceInHours;
        });

        if ($hasLoggedInAroundThisHour) {
            return $this->pass();
        }

        return $this->flag(__('Login at an unusual time (:hour:00)', ['hour' => $hour]));
    }

    protected function hourOf(CarbonInterface $time): int
    {
        return $time->copy()->setTimezone($this->timezone ?? config('app.timezone'))->hour;
    }
}
