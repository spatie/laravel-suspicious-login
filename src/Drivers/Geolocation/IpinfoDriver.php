<?php

namespace Spatie\SuspiciousLogin\Drivers\Geolocation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class IpinfoDriver implements GeolocationDriver
{
    public function __construct(
        protected ?string $token,
    ) {}

    public function locate(string $ip): ?Location
    {
        try {
            $response = Http::timeout(2)
                ->when($this->token, fn ($request) => $request->withToken($this->token))
                ->get("https://ipinfo.io/{$ip}/json");
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $countryCode = $response->json('country');

        if (! $countryCode) {
            return null;
        }

        [$latitude, $longitude] = array_pad(explode(',', (string) $response->json('loc')), 2, null);

        return new Location(
            countryCode: $countryCode,
            city: $response->json('city'),
            latitude: is_numeric($latitude) ? (float) $latitude : null,
            longitude: is_numeric($longitude) ? (float) $longitude : null,
        );
    }
}
