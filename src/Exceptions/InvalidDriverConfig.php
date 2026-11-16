<?php

namespace Spatie\SuspiciousLogin\Exceptions;

use Exception;

class InvalidDriverConfig extends Exception
{
    public static function maxmindNotInstalled(): self
    {
        return new self('To use the maxmind geolocation driver, install it via `composer require geoip2/geoip2`.');
    }

    public static function maxmindDatabaseNotFound(string $path): self
    {
        return new self("The MaxMind database could not be found at `{$path}`. Download a GeoLite2 City database and set `suspicious-login.geolocation.maxmind.database_path`.");
    }

    public static function missingAbuseIpDbKey(): self
    {
        return new self('To use the abuseipdb driver, set `suspicious-login.ip_reputation.abuseipdb.key` (ABUSEIPDB_KEY).');
    }
}
