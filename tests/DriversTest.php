<?php

use Illuminate\Support\Facades\Http;
use Spatie\SuspiciousLogin\Drivers\Geolocation\CloudflareDriver;
use Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationDriver;
use Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationManager;
use Spatie\SuspiciousLogin\Drivers\Geolocation\Location;
use Spatie\SuspiciousLogin\Exceptions\InvalidDriverConfig;
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;

it('looks up login locations with the default driver without a token', function () {
    config()->set('suspicious-login', require __DIR__.'/../config/suspicious-login.php');
    config()->set('suspicious-login.geolocation.ipinfo.token', null);

    Http::fake(['ipinfo.io/*' => Http::response([
        'country' => 'BE',
        'city' => 'Antwerp',
        'loc' => '51.2194,4.4025',
    ])]);

    createUser();

    attemptLogin()->assertOk();

    expect(latestAttempt())
        ->country_code->toBe('BE')
        ->city->toBe('Antwerp')
        ->latitude->toBe(51.2194)
        ->longitude->toBe(4.4025);
});

it('reads the location from cloudflare headers', function () {
    config()->set('suspicious-login.geolocation.driver', 'cloudflare');

    createUser();

    attemptLogin(headers: ['CF-IPCountry' => 'be', 'CF-IPCity' => 'Antwerp', 'CF-IPLatitude' => '51.2194', 'CF-IPLongitude' => '4.4025']);

    expect(latestAttempt())
        ->country_code->toBe('BE')
        ->city->toBe('Antwerp')
        ->latitude->toBe(51.2194)
        ->longitude->toBe(4.4025);
});

it('returns no location for an unknown cloudflare country', function () {
    request()->headers->set('CF-IPCountry', 'XX');

    expect((new CloudflareDriver)->locate('81.82.83.84'))->toBeNull();
});

it('caches locations and skips private ips', function () {
    fakeIpinfoLocations(['81.82.83.84' => ['BE', 'Antwerp', '51.2194,4.4025']]);

    expect(SuspiciousLogin::locate('81.82.83.84')->city)->toBe('Antwerp')
        ->and(SuspiciousLogin::locate('81.82.83.84')->city)->toBe('Antwerp')
        ->and(SuspiciousLogin::locate('192.168.1.10'))->toBeNull()
        ->and(SuspiciousLogin::locate('127.0.0.1'))->toBeNull();

    Http::assertSentCount(1);
});

it('returns no location when ipinfo fails', function () {
    config()->set('suspicious-login.geolocation.driver', 'ipinfo');

    Http::fake(['ipinfo.io/*' => Http::response(status: 429)]);

    expect(SuspiciousLogin::locate('81.82.83.84'))->toBeNull();
});

it('can use a custom geolocation driver', function () {
    app(GeolocationManager::class)->extend('fixed', fn () => new class implements GeolocationDriver
    {
        public function locate(string $ip): ?Location
        {
            return new Location('NL', 'Amsterdam');
        }
    });

    config()->set('suspicious-login.geolocation.driver', 'fixed');

    expect(SuspiciousLogin::locate('81.82.83.84')->countryCode)->toBe('NL');
});

it('explains how to fix a missing maxmind database', function () {
    config()->set('suspicious-login.geolocation.driver', 'maxmind');
    config()->set('suspicious-login.geolocation.maxmind.database_path', '/does/not/exist.mmdb');

    SuspiciousLogin::locate('81.82.83.84');
})->throws(InvalidDriverConfig::class, '/does/not/exist.mmdb');
