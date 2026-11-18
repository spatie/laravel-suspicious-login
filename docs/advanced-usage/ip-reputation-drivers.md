---
title: IP reputation drivers
weight: 3
---

The [IP reputation check](/docs/laravel-suspicious-login/v1/available-checks/ip-reputation) asks an IP reputation driver whether an IP address is a Tor exit node, and how likely it is to be abusive. Only that check uses these drivers, and only the `Strict` preset contains it.

Choose the driver in `config/suspicious-login.php`:

```php
'ip_reputation' => [
    'driver' => 'tor',
    // ...
],
```

| Driver | Tor exit nodes | Abuse score | Needs |
| --- | --- | --- | --- |
| `null` | no | no | nothing, this is the default |
| `tor` | yes | no | nothing |
| `abuseipdb` | yes | yes | an AbuseIPDB API key |

Results are cached per IP address for a day. You can change that with the `ip_reputation.cache_ttl` config option. Private IP addresses are never looked up.

## Tor

The `tor` driver downloads [the list of Tor exit nodes](https://check.torproject.org/torbulkexitlist) published by the Tor Project, and keeps it in your cache for an hour. It costs nothing and needs no account.

## AbuseIPDB

The `abuseipdb` driver calls the [AbuseIPDB](https://www.abuseipdb.com) API. It returns whether the IP address is a Tor exit node, and an abuse confidence score from 0 to 100 based on what other sites reported in the last 90 days. Create a free account, generate an API key and configure it in `config/suspicious-login.php`:

```php
'ip_reputation' => [
    'driver' => 'abuseipdb',
    'abuseipdb' => [
        'key' => env('ABUSEIPDB_KEY'),
    ],
    // ...
],
```

Keep the key in your deployment's secrets or `.env` file rather than committing it to source control.

When the API can't be reached, the IP address counts as clean, so an outage never blocks your users.

## Creating your own driver

A driver implements `Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputationDriver` and returns an `IpReputation`:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputation;
use Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputationDriver;

class MyIpReputationDriver implements IpReputationDriver
{
    public function check(string $ip): IpReputation
    {
        $result = MyReputationService::check($ip);

        return new IpReputation(
            isTor: $result->isTor,
            abuseScore: $result->riskPercentage,
        );
    }
}
```

Register it in the `boot` method of a service provider:

```php
use App\SuspiciousLogin\MyIpReputationDriver;
use Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputationManager;

app(IpReputationManager::class)->extend('my-driver', fn () => new MyIpReputationDriver);
```

Then select the driver in `config/suspicious-login.php`:

```php
'ip_reputation' => [
    'driver' => 'my-driver',
    // ...
],
```

Its results use the same per-IP cache as the built-in drivers. Add `IpReputationCheck::new()` to your preset if you are not using `Strict`; selecting a driver does not add the check for you.
