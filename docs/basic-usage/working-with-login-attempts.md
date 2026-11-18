---
title: Working with login attempts
weight: 4
---

Every login and failed login is stored as a `Spatie\SuspiciousLogin\Models\LoginAttempt`. You can use these records to show users their recent activity, to build an admin overview, or to see how your preset scores real logins.

## What is stored

| Attribute | Contains |
| --- | --- |
| `authenticatable` | The user, as a polymorphic relation. `null` for a failed login on an account that doesn't exist. |
| `guard` | The auth guard used for the login. |
| `identifier` | For failed logins: the email address or username that was tried. Passwords are never stored. |
| `successful` | Whether the login succeeded. |
| `blocked` | Whether the login was [blocked](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/blocking-logins). |
| `ip_address`, `user_agent` | Taken from the request. |
| `device_fingerprint` | A hash that identifies the device. See [device fingerprints](/docs/laravel-suspicious-login/v1/advanced-usage/device-fingerprints). |
| `country_code`, `city`, `latitude`, `longitude` | The location, when a [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers) found one. |
| `risk_score`, `risk_level` | The score from 0 to 100 and the `RiskLevel` enum. `null` for failed logins. |
| `reasons` | An array with the check, score and human readable reason for every check that flagged the login. |
| `properties` | Your [custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties), or `null` when there are none. |

## Showing users their recent logins

```php
use Spatie\SuspiciousLogin\Models\LoginAttempt;

$recentLogins = LoginAttempt::query()
    ->whereMorphedTo('authenticatable', $request->user())
    ->where('successful', true)
    ->latest()
    ->limit(10)
    ->get();
```

```blade
@foreach($recentLogins as $login)
    <li>
        {{ $login->created_at->diffForHumans() }} from {{ $login->city ?? $login->country_code ?? $login->ip_address }}

        @if($login->isSuspicious())
            <strong>Suspicious:</strong> {{ collect($login->reasons)->pluck('reason')->implode(', ') }}
        @endif
    </li>
@endforeach
```

## Useful methods and scopes

```php
$attempt->isSuspicious(); // true for medium and high risk logins
$attempt->location(); // a Location object, or null
$attempt->getProperty('tenant_id'); // a custom property, or null

$attempt->history(); // a query for the other attempts of the same user, up to this one

LoginAttempt::query()->trusted(); // successful logins that weren't blocked
LoginAttempt::query()->failed(); // failed logins
LoginAttempt::query()->since(now()->subDay());
```

The scopes combine with `history()`, which is what the checks themselves use:

```php
$failedAttemptsToday = $attempt->history()->failed()->since(now()->startOfDay())->count();
```

If you want to add your own methods or relations, you can [use your own model](/docs/laravel-suspicious-login/v1/advanced-usage/using-your-own-model).
