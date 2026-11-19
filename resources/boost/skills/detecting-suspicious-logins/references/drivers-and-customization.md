# Drivers and customization

## Geolocation

Set `geolocation.driver` directly in `config/suspicious-login.php`, for example `'driver' => 'cloudflare'`:

- `ipinfo` (default): calls ipinfo.io without requiring a token, subject to its request limits. Configure `geolocation.ipinfo.token` for an optional token. Keep the secret environment-backed in config.
- `cloudflare`: reads `CF-IPCountry`; city and coordinates require Cloudflare's "Add visitor location headers" managed transform. Use only behind Cloudflare with properly configured trusted proxies and an origin that prevents spoofed location headers.
- `maxmind`: reads a local GeoLite2 City or GeoIP2 City database. Install `geoip2/geoip2` and set `geolocation.maxmind.database_path`, default `storage_path('app/geoip/GeoLite2-City.mmdb')`. Keep the database updated yourself.
- `null`: disables lookups, including external IP sharing. Location-dependent checks pass.

The facade's `locate(string $ip): ?Location` caches locations per public IP for a week (`geolocation.cache_ttl` in seconds). Private/reserved IPs are not looked up. Drivers return null when no location can be found; missing MaxMind dependencies or database configuration can throw an explanatory configuration exception.

For a custom driver, implement `Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationDriver::locate(string $ip): ?Location`, and register it in a service provider's `boot()`:

```php
use App\SuspiciousLogin\MyGeolocationDriver;
use Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationManager;

app(GeolocationManager::class)->extend(
    'custom',
    fn () => app(MyGeolocationDriver::class),
);
```

Set `geolocation.driver` to `custom`. Return `new Location(countryCode: 'BE', city: 'Antwerp', latitude: 51.2194, longitude: 4.4025)` using the package's `Drivers\Geolocation\Location` class; all fields except country code are optional. Return null for an unknown location. The facade applies the same caching to custom drivers.

## IP reputation

Set `ip_reputation.driver` directly in `config/suspicious-login.php`, for example `'driver' => 'tor'`:

- `null` (default): no reputation data.
- `tor`: downloads the Tor Project's exit-node list and caches that list for an hour; no API key.
- `abuseipdb`: requires `ip_reputation.abuseipdb.key`, returns Tor status and abuse confidence. Keep the secret environment-backed in config.

`SuspiciousLogin::ipReputation(string $ip): IpReputation` caches results per public IP for a day (`ip_reputation.cache_ttl` in seconds). Private/reserved IPs are not checked. Remote lookup failures yield no reputation evidence, not a reason to block. Selecting a driver does not add `IpReputationCheck`; StrictPreset includes it, other built-in presets do not.

For a custom driver implement `Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputationDriver::check(string $ip): IpReputation`. Return the package's `Drivers\IpReputation\IpReputation` value object, e.g. `new IpReputation(isTor: false, abuseScore: 75)`. Register through `IpReputationManager::extend()` like geolocation, then select its name in config.

## Device fingerprints

The default `UserAgentFingerprinter` hashes the user agent with version numbers removed. Browser upgrades should not look like new devices, but identical browser/OS user agents share a fingerprint. This is a heuristic, not proof of a unique physical device or a secure trusted-device token.

Implement `Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter::fingerprint(Request $request): string` and configure `device_fingerprinter` with your class to use an application-managed device cookie or identifier. It resolves through the container. Changing algorithms changes existing fingerprints and can flag known users' next logins.

## Custom model

Extend `Spatie\SuspiciousLogin\Models\LoginAttempt` and configure `'model' => App\Models\LoginAttempt::class`. Set a custom connection/table or add relations, scopes, and traits on that class rather than adding separate package config options. The package creates this configured model, uses its query/connection for checks, and passes it to actions and events. Set up its database table on that connection and pass the custom class when scheduling `model:prune`.
