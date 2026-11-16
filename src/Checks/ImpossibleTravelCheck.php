<?php

namespace Spatie\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class ImpossibleTravelCheck extends Check
{
    protected int $score = 60;

    protected int $maxSpeedInKilometersPerHour = 900;

    /*
     * IP geolocation is often off by tens or hundreds of kilometers,
     * so short distances are never considered impossible travel.
     */
    protected int $minimumDistanceInKilometers = 300;

    public function maxSpeedInKilometersPerHour(int $speed): static
    {
        $this->maxSpeedInKilometersPerHour = $speed;

        return $this;
    }

    public function minimumDistanceInKilometers(int $distance): static
    {
        $this->minimumDistanceInKilometers = $distance;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $location = $attempt->location();

        if (! $location?->hasCoordinates()) {
            return $this->pass();
        }

        $previousLogin = $attempt->history()
            ->trusted()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest()
            ->first();

        if (! $previousLogin) {
            return $this->pass();
        }

        $distance = $previousLogin->location()->distanceInKilometersTo($location);

        if ($distance < $this->minimumDistanceInKilometers) {
            return $this->pass();
        }

        $hours = max($previousLogin->created_at->diffInSeconds($attempt->created_at ?? now(), absolute: true), 1) / 3600;

        if ($distance / $hours <= $this->maxSpeedInKilometersPerHour) {
            return $this->pass();
        }

        return $this->flag(__('Impossible travel: :distance km from the previous login (:place) in :minutes minutes', [
            'distance' => round($distance),
            'place' => $previousLogin->city ?? $previousLogin->country_code,
            'minutes' => round($hours * 60),
        ]));
    }
}
