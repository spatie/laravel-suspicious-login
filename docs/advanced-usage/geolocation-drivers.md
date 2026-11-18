---
title: Geolocation drivers
weight: 2
---

Four checks need to know where a login comes from: [new country](/docs/laravel-suspicious-login/v1/available-checks/new-country), [multiple countries](/docs/laravel-suspicious-login/v1/available-checks/multiple-countries), [impossible travel](/docs/laravel-suspicious-login/v1/available-checks/impossible-travel) and [allowed and blocked countries](/docs/laravel-suspicious-login/v1/available-checks/country). A geolocation driver turns an IP address into a country, and if it can, a city and coordinates.

The default `ipinfo` driver works without an API token. It sends public IP addresses to ipinfo.io, subject to its request limits. Choose the driver in `config/suspicious-login.php`:

```php
'geolocation' => [
    'driver' => 'ipinfo',
    // ...
],
```

| Driver | Country | Coordinates | Needs |
| --- | --- | --- | --- |
| `ipinfo` (default) | yes | yes | optionally an ipinfo.io token |
| `cloudflare` | yes | with visitor location headers | your app behind Cloudflare |
| `maxmind` | yes | yes | `geoip2/geoip2` and a database file |
| `null` | no | no | nothing; disables location lookups |

Locations are cached per IP address for a week. To cache them for one day instead, set `cache_ttl` in seconds in `config/suspicious-login.php`:

```php
'geolocation' => [
    'driver' => 'ipinfo',
    'cache_ttl' => 60 * 60 * 24,
    // ...
],
```

Private and reserved IP addresses, such as `127.0.0.1` in local development, are never looked up. When no location is available, the location checks pass. Other checks still contribute to the score.

Cached locations are shared between drivers. After switching drivers, an IP address can keep its old location until the cache entry expires. To refresh a specific address:

```php
use Illuminate\Support\Facades\Cache;

Cache::forget('suspicious-login:location:8.8.8.8');
```

## ipinfo

The `ipinfo` driver calls the [ipinfo.io](https://ipinfo.io) API. It works without an account, up to ipinfo's free limit. For more requests, create a token and configure it in `config/suspicious-login.php`:

```php
'geolocation' => [
    'driver' => 'ipinfo',
    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],
    // ...
],
```

Keep the token in your deployment's secrets or `.env` file rather than committing it to source control.

Requests time out after two seconds, so a slow API never holds up a login for long.

## Cloudflare

The `cloudflare` driver reads location headers from the current request without calling an external API. Select it in `config/suspicious-login.php`:

```php
'geolocation' => [
    'driver' => 'cloudflare',
    // ...
],
```

When your app runs behind Cloudflare, the `CF-IPCountry` header supplies the visitor's country. For the city and coordinates, which impossible travel needs, turn on the "Add visitor location headers" managed transform in your Cloudflare dashboard.

Make sure Laravel [trusts Cloudflare as a proxy](https://laravel.com/docs/requests#configuring-trusted-proxies). Otherwise `$request->ip()` returns the address of a Cloudflare server instead of the visitor's, and a location cached for that address can be reused for other visitors. Only accept these headers from Cloudflare; requests sent directly to your server must not be able to supply their own location headers.

## MaxMind

The `maxmind` driver reads a local GeoLite2 City or GeoIP2 City database. Lookups don't leave your server, which makes it the fastest and most private option.

Install MaxMind's reader:

```bash
composer require geoip2/geoip2
```

Then [download a GeoLite2 City database](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) with a free MaxMind account. By default, the driver looks for it in `storage/app/geoip/GeoLite2-City.mmdb`. Select the driver and, if needed, change the database path in `config/suspicious-login.php`:

```php
'geolocation' => [
    'driver' => 'maxmind',
    'maxmind' => [
        'database_path' => storage_path('app/geoip/GeoLite2-City.mmdb'),
    ],
    // ...
],
```

Use MaxMind's `geoipupdate` tool to keep your database current.

## Disabling location lookups

To avoid sending IP addresses to an external service without setting up another driver, select the string `'null'` in `config/suspicious-login.php`:

```php
'geolocation' => [
    'driver' => 'null',
    // ...
],
```

This stops new lookups. Previously cached locations can still be used until they expire; once no cached location remains, location fields stay empty and location checks pass. Existing attempts keep their stored locations.

## Creating your own driver

A driver implements `Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationDriver`. It gets an IP address and returns a `Location`, or `null` when it doesn't know:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationDriver;
use Spatie\SuspiciousLogin\Drivers\Geolocation\Location;

class MyGeolocationDriver implements GeolocationDriver
{
    public function locate(string $ip): ?Location
    {
        $result = MyGeoService::lookup($ip);

        if (! $result) {
            return null;
        }

        return new Location(
            countryCode: $result->country,
            city: $result->city,
            latitude: $result->latitude,
            longitude: $result->longitude,
        );
    }
}
```

Register it in the `boot` method of a service provider:

```php
use App\SuspiciousLogin\MyGeolocationDriver;
use Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationManager;

app(GeolocationManager::class)->extend('my-driver', fn () => new MyGeolocationDriver);
```

Then select it in `config/suspicious-login.php`:

```php
'geolocation' => [
    'driver' => 'my-driver',
    // ...
],
```

Results of your driver are cached like those of the built-in ones.
