<?php

namespace Spatie\SuspiciousLogin\Drivers\Geolocation;

interface GeolocationDriver
{
    public function locate(string $ip): ?Location;
}
