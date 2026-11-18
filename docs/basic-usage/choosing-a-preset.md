---
title: Choosing a preset
weight: 2
---

A preset decides which checks run, how much each one weighs, where the risk levels start, and what happens at each level. The package ships with four:

| Preset | Use it when |
| --- | --- |
| `Lenient` | You only want to bother users when several checks agree. It only notifies about high risk logins. |
| `Balanced` | The default. Notifies users about medium and high risk logins, and never blocks anyone. |
| `Strict` | You want to block high risk session logins. Adds unusual-time and IP-reputation checks to the `Balanced` checks, uses lower thresholds, and notifies at both suspicious levels. |
| `MonitorOnly` | You're trying the package out. Runs the `Balanced` checks and stores the results, but takes no action. |

You set the preset in `config/suspicious-login.php`:

```php
'preset' => Spatie\SuspiciousLogin\Presets\Strict::class,
```

## The same login, four presets

Here's how Anna's login from New York, ten hours after her usual morning login in Antwerp, plays out with each preset. She has logged in every morning for a few weeks, and a geolocation driver is configured.

| Preset | Flagged by | Score | Risk level | What happens |
| --- | --- | --- | --- | --- |
| `Lenient` | new device (20), new country (30) | 50 | medium | nothing |
| `Balanced` | new device (25), new IP (10), new country (30) | 65 | medium | Anna gets an email |
| `Strict` | new device (30), new IP (10), new country (30), unusual time (15) | 85 | high | Anna gets an email and the login is blocked |
| `MonitorOnly` | same as `Balanced` | 65 | medium | the attempt is stored, nothing else |

## Start with MonitorOnly

Adding the package to an app with existing users? Start with `MonitorOnly`. Let it run for a week or two, then look at the scores your real users get:

```php
use Spatie\SuspiciousLogin\Models\LoginAttempt;

LoginAttempt::query()
    ->whereNot('risk_level', 'low')
    ->latest()
    ->get(['authenticatable_type', 'authenticatable_id', 'ip_address', 'country_code', 'risk_score', 'reasons']);
```

If people who travel a lot or use a VPN show up as high risk, lower the weight of those checks before you switch to a preset that notifies or blocks. The next page shows how.

## What each preset contains

| | `Lenient` | `Balanced` | `Strict` |
| --- | --- | --- | --- |
| [`NewDeviceCheck`](/docs/laravel-suspicious-login/v1/available-checks/new-device) | 20 | 25 | 30 |
| [`NewIpCheck`](/docs/laravel-suspicious-login/v1/available-checks/new-ip) | | 10 | 10 |
| [`NewCountryCheck`](/docs/laravel-suspicious-login/v1/available-checks/new-country) | 30 | 30 | 30 |
| [`ImpossibleTravelCheck`](/docs/laravel-suspicious-login/v1/available-checks/impossible-travel) | 60 | 60 | 60 |
| [`FailedAttemptsCheck`](/docs/laravel-suspicious-login/v1/available-checks/failed-attempts) | 30, from 10 failures in 60 minutes | 30, from 5 failures in 15 minutes | 30, from 3 failures in 15 minutes |
| [`CredentialStuffingCheck`](/docs/laravel-suspicious-login/v1/available-checks/credential-stuffing) | 40, from 10 accounts | 40, from 5 accounts | 50, from 3 accounts |
| [`SuspiciousUserAgentCheck`](/docs/laravel-suspicious-login/v1/available-checks/suspicious-user-agent) | | 20 | 30 |
| [`UnusualTimeCheck`](/docs/laravel-suspicious-login/v1/available-checks/unusual-time) | | | 15 |
| [`IpReputationCheck`](/docs/laravel-suspicious-login/v1/available-checks/ip-reputation) | | | 50 |
| Medium risk from | 50 | 40 | 30 |
| High risk from | 80 | 70 | 60 |
| On medium risk | nothing | notify | notify |
| On high risk | notify | notify | notify and block |
