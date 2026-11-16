<?php

namespace Spatie\SuspiciousLogin\Drivers\Geolocation;

/**
 * Reads the location headers Cloudflare adds to proxied requests. CF-IPCountry is
 * always present. City and coordinates need the "Add visitor location headers"
 * managed transform.
 */
class CloudflareDriver implements GeolocationDriver
{
    public function locate(string $ip): ?Location
    {
        $countryCode = request()->header('CF-IPCountry');

        if (! $countryCode) {
            return null;
        }

        if ($countryCode === 'XX') {
            return null;
        }

        $latitude = request()->header('CF-IPLatitude');
        $longitude = request()->header('CF-IPLongitude');

        return new Location(
            countryCode: strtoupper($countryCode),
            city: request()->header('CF-IPCity'),
            latitude: is_numeric($latitude) ? (float) $latitude : null,
            longitude: is_numeric($longitude) ? (float) $longitude : null,
        );
    }
}
