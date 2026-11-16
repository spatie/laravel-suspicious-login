<?php

namespace Spatie\SuspiciousLogin\Drivers\Geolocation;

class NullDriver implements GeolocationDriver
{
    public function locate(string $ip): ?Location
    {
        return null;
    }
}
