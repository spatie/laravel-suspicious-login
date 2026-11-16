<?php

namespace Spatie\SuspiciousLogin\Drivers\Geolocation;

readonly class Location
{
    public function __construct(
        public string $countryCode,
        public ?string $city = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function distanceInKilometersTo(Location $other): float
    {
        $earthRadiusInKilometers = 6371;

        $latitudeDelta = deg2rad($other->latitude - $this->latitude);
        $longitudeDelta = deg2rad($other->longitude - $this->longitude);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusInKilometers * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}
