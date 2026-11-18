<?php

namespace Spatie\SuspiciousLogin\Drivers\Geolocation;

use Illuminate\Support\Manager;

/** @method GeolocationDriver driver(?string $driver = null) */
class GeolocationManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('suspicious-login.geolocation.driver', 'ipinfo');
    }

    public function createNullDriver(): NullDriver
    {
        return new NullDriver;
    }

    public function createCloudflareDriver(): CloudflareDriver
    {
        return new CloudflareDriver;
    }

    public function createIpinfoDriver(): IpinfoDriver
    {
        return new IpinfoDriver($this->config->get('suspicious-login.geolocation.ipinfo.token'));
    }

    public function createMaxmindDriver(): MaxmindDriver
    {
        return new MaxmindDriver($this->config->get('suspicious-login.geolocation.maxmind.database_path'));
    }
}
