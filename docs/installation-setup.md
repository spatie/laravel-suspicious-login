---
title: Installation & setup
weight: 4
---

You can install the package via composer:

```bash
composer require spatie/laravel-suspicious-login
```

Next, run the install command. It publishes the config file and the migration, and offers to run the migration for you.

```bash
php artisan suspicious-login:install
```

That's all you need. From now on, every login and failed login is recorded in the `login_attempts` table, and every successful login is scored with the `Balanced` preset.

If you prefer to publish things yourself, these are the tags:

```bash
php artisan vendor:publish --tag="suspicious-login-migrations"
php artisan migrate

php artisan vendor:publish --tag="suspicious-login-config"
```

## Turning on location checks

Out of the box, the package doesn't know where a login comes from, so the checks that need a location (new country, impossible travel, allowed countries) don't flag anything. To turn them on, pick a [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers). If your app runs behind Cloudflare, this is all it takes:

```bash
SUSPICIOUS_LOGIN_GEOLOCATION_DRIVER=cloudflare
```

## Pruning old login attempts

Login attempts pile up. To delete the ones older than a year, schedule Laravel's `model:prune` command in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

Schedule::command('model:prune', ['--model' => [LoginAttempt::class]])->daily();
```

You can change how long attempts are kept with the `prune_after_days` config option. Keep in mind that the checks compare a login with earlier ones, so pruning very aggressively makes every device look new again.

## The config file

This is the content of the published config file:

```php
return [
    /*
     * When disabled, logins will not be recorded or assessed.
     */
    'enabled' => env('SUSPICIOUS_LOGIN_ENABLED', true),

    /*
     * The preset decides which checks run, how their scores map to a risk
     * level and which actions run for each risk level. The package ships
     * with Lenient, Balanced, Strict and MonitorOnly. Extend one of them,
     * or Spatie\SuspiciousLogin\Presets\Preset, to build your own.
     */
    'preset' => Spatie\SuspiciousLogin\Presets\Balanced::class,

    'geolocation' => [
        /*
         * The driver used to find the location of an IP address. Several checks,
         * such as NewCountryCheck and ImpossibleTravelCheck, need a location.
         *
         * Supported: "null", "cloudflare", "ipinfo", "maxmind"
         */
        'driver' => env('SUSPICIOUS_LOGIN_GEOLOCATION_DRIVER', 'null'),

        /*
         * How long the location of an IP address is cached, in seconds.
         */
        'cache_ttl' => 60 * 60 * 24 * 7,

        'ipinfo' => [
            'token' => env('IPINFO_TOKEN'),
        ],

        'maxmind' => [
            /*
             * The path to a GeoLite2 City or GeoIP2 City database.
             * Requires `composer require geoip2/geoip2`.
             */
            'database_path' => env('MAXMIND_DATABASE_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),
        ],
    ],

    'ip_reputation' => [
        /*
         * The driver used to determine if an IP address is known to be abusive
         * or a Tor exit node. Only used by IpReputationCheck.
         *
         * Supported: "null", "tor", "abuseipdb"
         */
        'driver' => env('SUSPICIOUS_LOGIN_IP_REPUTATION_DRIVER', 'null'),

        /*
         * How long the reputation of an IP address is cached, in seconds.
         */
        'cache_ttl' => 60 * 60 * 24,

        'tor' => [
            'exit_node_list_url' => 'https://check.torproject.org/torbulkexitlist',
        ],

        'abuseipdb' => [
            'key' => env('ABUSEIPDB_KEY'),
        ],
    ],

    /*
     * The model used to store login attempts. You can use your own model,
     * as long as it extends Spatie\SuspiciousLogin\Models\LoginAttempt.
     */
    'model' => Spatie\SuspiciousLogin\Models\LoginAttempt::class,

    /*
     * The class that turns a request into a device fingerprint. It must implement
     * Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter.
     */
    'device_fingerprinter' => Spatie\SuspiciousLogin\Fingerprinters\UserAgentFingerprinter::class,

    /*
     * The notification that NotifyUserAction sends to the user.
     */
    'notification' => Spatie\SuspiciousLogin\Notifications\SuspiciousLoginNotification::class,

    /*
     * Login attempts older than this many days are deleted when you run
     * `php artisan model:prune --model="Spatie\SuspiciousLogin\Models\LoginAttempt"`.
     */
    'prune_after_days' => 365,
];
```
