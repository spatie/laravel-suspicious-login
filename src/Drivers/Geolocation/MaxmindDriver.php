<?php

namespace Spatie\SuspiciousLogin\Drivers\Geolocation;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Spatie\SuspiciousLogin\Exceptions\InvalidDriverConfig;

class MaxmindDriver implements GeolocationDriver
{
    protected Reader $reader;

    public function __construct(string $databasePath)
    {
        if (! class_exists(Reader::class)) {
            throw InvalidDriverConfig::maxmindNotInstalled();
        }

        if (! file_exists($databasePath)) {
            throw InvalidDriverConfig::maxmindDatabaseNotFound($databasePath);
        }

        $this->reader = new Reader($databasePath);
    }

    public function locate(string $ip): ?Location
    {
        try {
            $record = $this->reader->city($ip);
        } catch (AddressNotFoundException) {
            return null;
        }

        if (! $record->country->isoCode) {
            return null;
        }

        return new Location(
            countryCode: $record->country->isoCode,
            city: $record->city->name,
            latitude: $record->location->latitude,
            longitude: $record->location->longitude,
        );
    }
}
