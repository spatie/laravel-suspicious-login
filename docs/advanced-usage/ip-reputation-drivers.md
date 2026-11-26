---
title: IP reputation drivers
weight: 3
---

The [IP reputation check](/docs/laravel-suspicious-login/v1/available-checks/ip-reputation) asks an IP reputation driver whether an IP address is a Tor exit node or a VPN, and how likely it is to be abusive. Only that check uses these drivers. The built-in presets do not include it.

To enable Tor detection, add the check to an application-owned preset in `app/SuspiciousLogin/MyPreset.php`:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Checks\IpReputationCheck;
use Spatie\SuspiciousLogin\Presets\DefaultPreset;

class MyPreset extends DefaultPreset
{
    public function checks(): array
    {
        return [...parent::checks(), IpReputationCheck::new()];
    }
}
```

Then select your preset and driver in `config/suspicious-login.php`, leaving the other options in place:

```php
'preset' => App\SuspiciousLogin\MyPreset::class,

'ip_reputation' => [
    'driver' => 'tor',
    // ...
],
```

| Driver | Tor exit nodes | VPNs | Abuse score | Needs |
| --- | --- | --- | --- | --- |
| `null` | no | no | no | nothing, this is the default |
| `tor` | yes | no | no | nothing |
| `abuseipdb` | yes | no | yes | an AbuseIPDB API key |
| `vpndetection` | yes | yes | no | nothing |

Selecting a driver does not add the check for you. The check adds points; it doesn't block a login on its own. This example keeps `DefaultPreset`'s notification actions; see [customizing presets](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets) to change them.

Results are cached per IP address for a day. The `ip_reputation.cache_ttl` config value is in seconds. Private and reserved IP addresses are never looked up.

Cached results are shared between drivers. To refresh a specific address after switching drivers:

```php
use Illuminate\Support\Facades\Cache;

Cache::forget('suspicious-login:ip-reputation:8.8.8.8');
```

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

When the API can't be reached or returns an unsuccessful response, this driver returns no Tor flag or abuse score. The reputation check then adds no points, but other checks can still flag or block the login.

## VPNDetection

The `vpndetection` driver calls the [VPNDetection](https://vpndetection.io) API, which returns whether the IP address is a VPN server or a Tor node. It needs no account or API key ([terms](https://vpndetection.io/terms), [privacy](https://vpndetection.io/privacy)):

```php
'ip_reputation' => [
    'driver' => 'vpndetection',
    // ...
],
```

Like the `abuseipdb` driver, it returns no flags when the API can't be reached.

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

Its results use the same per-IP cache as the built-in drivers. Add `IpReputationCheck::new()` to your preset as shown above; selecting a driver does not add the check for you.
