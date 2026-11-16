---
title: Geolocation drivers
weight: 2
---

Three checks need to know where a login comes from: [new country](/docs/laravel-suspicious-login/v1/available-checks/new-country), [impossible travel](/docs/laravel-suspicious-login/v1/available-checks/impossible-travel) and [allowed and blocked countries](/docs/laravel-suspicious-login/v1/available-checks/country). A geolocation driver turns an IP address into a country, and if it can, a city and coordinates.

You pick the driver with an environment variable:

```bash
SUSPICIOUS_LOGIN_GEOLOCATION_DRIVER=ipinfo
```

| Driver | Country | Coordinates | Needs |
| --- | --- | --- | --- |
| `null` | no | no | nothing, this is the default |
| `cloudflare` | yes | with visitor location headers | your app behind Cloudflare |
| `ipinfo` | yes | yes | optionally an ipinfo.io token |
| `maxmind` | yes | yes | `geoip2/geoip2` and a database file |

Locations are cached per IP address for a week. You can change that with the `geolocation.cache_ttl` config option. Private and reserved IP addresses, such as `127.0.0.1` in local development, are never looked up.

When a driver can't find a location, for example because an API is down, the login is scored without one. The location checks then pass, and nothing breaks.

## Cloudflare

When your app runs behind Cloudflare, every request already carries the visitor's country in the `CF-IPCountry` header. The `cloudflare` driver reads it, so it's free and adds no time to a login.

For the city and coordinates, which impossible travel needs, turn on the "Add visitor location headers" managed transform in your Cloudflare dashboard.

Make sure Laravel [trusts Cloudflare as a proxy](https://laravel.com/docs/requests#configuring-trusted-proxies). Otherwise `$request->ip()` returns the address of a Cloudflare server instead of the visitor's, every login seems to come from a handful of IP addresses, and the location cached for one of them is used for everybody behind it.

## ipinfo

The `ipinfo` driver calls the [ipinfo.io](https://ipinfo.io) API. It works without an account, up to ipinfo's free limit. For more requests, create a token and set it:

```bash
IPINFO_TOKEN=your-token
```

Requests time out after two seconds, so a slow API never holds up a login for long.

## MaxMind

The `maxmind` driver reads a local GeoLite2 City or GeoIP2 City database. Lookups don't leave your server, which makes it the fastest and most private option.

Install MaxMind's reader:

```bash
composer require geoip2/geoip2
```

Then [download a GeoLite2 City database](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) with a free MaxMind account. By default, the driver looks for it in `storage/app/geoip/GeoLite2-City.mmdb`. To use another path:

```bash
MAXMIND_DATABASE_PATH=/path/to/GeoLite2-City.mmdb
```

MaxMind updates its databases every week. Their `geoipupdate` tool keeps your copy current.

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

And use it:

```bash
SUSPICIOUS_LOGIN_GEOLOCATION_DRIVER=my-driver
```

Results of your driver are cached like those of the built-in ones.
