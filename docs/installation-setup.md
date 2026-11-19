---
title: Installation & setup
weight: 4
---

You can install the package via composer:

```bash
composer require spatie/laravel-suspicious-login
```

Next, run the install command:

```bash
php artisan suspicious-login:install
```

This command:

- Publishes the config file to `config/suspicious-login.php`.
- Publishes the migration that creates the `login_attempts` table.
- Asks whether to run your application's pending migrations. If you decline, run `php artisan migrate` yourself before using the package.
- Offers to star the repository on GitHub.

Once the migration has run, the package listens for Laravel's auth events, records login attempts in `login_attempts`, and scores successful logins with `DefaultPreset`. It skips logins restored from a remember-me cookie.

## The config file

This is the content of the published config file:

```php
return [
    /*
     * When disabled, Laravel's auth events will not record or assess logins.
     * Explicit calls to the SuspiciousLogin facade still work.
     */
    'enabled' => true,

    /*
     * The preset decides which checks run, how their scores map to a risk
     * level and which actions run for each risk level. The package ships
     * with DefaultPreset, MonitorOnlyPreset and NonePreset.
     * Extend a preset to build your own. Use
     * SuspiciousLogin::determinePresetUsing() to choose per login.
     */
    'preset' => Spatie\SuspiciousLogin\Presets\DefaultPreset::class,

    /*
     * The scores at which medium and high risk start. Presets can override
     * either threshold with their own $mediumRiskThreshold or $highRiskThreshold.
     */
    'risk_thresholds' => [
        'medium' => 40,
        'high' => 70,
    ],

    'geolocation' => [
        /*
         * The driver used to find the location of an IP address. Several checks,
         * such as NewCountryCheck and ImpossibleTravelCheck, need a location.
         *
         * By default, public IP addresses are sent to ipinfo.io without an API token.
         * Use "null" to disable lookups.
         *
         * Supported: "ipinfo", "cloudflare", "maxmind", "null"
         */
        'driver' => 'ipinfo',

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
            'database_path' => storage_path('app/geoip/GeoLite2-City.mmdb'),
        ],
    ],

    'ip_reputation' => [
        /*
         * The driver used to determine if an IP address is known to be abusive
         * or a Tor exit node. Only used by IpReputationCheck.
         *
         * Supported: "null", "tor", "abuseipdb"
         */
        'driver' => 'null',

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

## Publishing files manually

If you prefer to publish things yourself, these are the tags:

```bash
php artisan vendor:publish --tag="suspicious-login-config"
php artisan vendor:publish --tag="suspicious-login-migrations"
php artisan migrate
```

## Geolocation

The default `ipinfo` driver looks up public IP addresses using ipinfo.io without requiring an API token. Results are cached per IP for a week. Requests are subject to ipinfo's limits; if a lookup fails, checks that need the missing location data pass.

You can choose another [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers) in `config/suspicious-login.php`. For example, if your app runs behind Cloudflare:

```php
'geolocation' => [
    'driver' => 'cloudflare',
    // ...
],
```

Set the driver to `'null'` to disable location lookups and avoid sending IP addresses to an external service.

## Pruning old login attempts

Login attempts pile up. To delete the ones older than a year, schedule Laravel's `model:prune` command in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

Schedule::command('model:prune', ['--model' => [LoginAttempt::class]])->daily();
```

You can change how long attempts are kept with the `prune_after_days` config option. Keep in mind that the checks compare a login with earlier ones, so pruning very aggressively makes every device look new again.

## Laravel Boost

If your application uses [Laravel Boost](https://laravel.com/docs/boost), run:

```bash
php artisan boost:install
```

Select `spatie/laravel-suspicious-login` when Boost asks which third-party guidelines and skills to install. The package includes a short guideline and a `detecting-suspicious-logins` skill covering installation, presets, checks, actions, notifications, events, login history, drivers, and other extension points. No extra service provider or package configuration is needed.

After updating the package, run `php artisan boost:update` to refresh the installed guidance. If you installed Boost before adding this package, run `boost:install` again to select it.
