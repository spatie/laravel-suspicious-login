<?php

use Spatie\SuspiciousLogin\Fingerprinters\UserAgentFingerprinter;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Notifications\SuspiciousLoginNotification;
use Spatie\SuspiciousLogin\Presets\Balanced;

return [
    /*
     * When disabled, Laravel's auth events will not record or assess logins.
     * Explicit calls to the SuspiciousLogin facade still work.
     */
    'enabled' => env('SUSPICIOUS_LOGIN_ENABLED', true),

    /*
     * The preset decides which checks run, how their scores map to a risk
     * level and which actions run for each risk level. The package ships
     * with Lenient, Balanced, Strict and MonitorOnly. Extend one of them,
     * or Spatie\SuspiciousLogin\Presets\Preset, to build your own.
     */
    'preset' => Balanced::class,

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
    'model' => LoginAttempt::class,

    /*
     * The class that turns a request into a device fingerprint. It must implement
     * Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter.
     */
    'device_fingerprinter' => UserAgentFingerprinter::class,

    /*
     * The notification that NotifyUserAction sends to the user.
     */
    'notification' => SuspiciousLoginNotification::class,

    /*
     * Login attempts older than this many days are deleted when you run
     * `php artisan model:prune --model="Spatie\SuspiciousLogin\Models\LoginAttempt"`.
     */
    'prune_after_days' => 365,
];
