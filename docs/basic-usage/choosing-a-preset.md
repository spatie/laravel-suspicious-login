---
title: Choosing a preset
weight: 2
---

A preset decides which checks run, how much each one weighs, where the risk levels start, and what happens at each level. The package ships with five:

| Preset | Use it when |
| --- | --- |
| `LenientPreset` | You only want to bother users when several checks agree. It only notifies about high risk logins. |
| `BalancedPreset` | The default. Notifies users about medium and high risk logins, and never blocks anyone. |
| `StrictPreset` | You want to block high risk session logins. Adds unusual-time and IP-reputation checks to the `BalancedPreset` checks, uses lower thresholds, and notifies at both suspicious levels. |
| `MonitorOnlyPreset` | You're trying the package out. Runs the `BalancedPreset` checks and stores the results, but takes no action. |
| `NonePreset` | You only want to record the login. Skips location lookups, checks and actions, leaving the score, risk level and reasons unset. |

You set the preset in `config/suspicious-login.php`:

```php
'preset' => Spatie\SuspiciousLogin\Presets\StrictPreset::class,
```

## Choosing a preset per login

Register a callback in your service provider's `boot()` method to choose a preset based on the user, guard or [custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties):

```php
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\NonePreset;
use Spatie\SuspiciousLogin\Presets\StrictPreset;

SuspiciousLogin::determinePresetUsing(function (LoginAttempt $attempt): string {
    $user = $attempt->authenticatable;

    if ($user->is_admin) {
        return StrictPreset::class;
    }

    if ($user->skip_login_checks) {
        return NonePreset::class;
    }

    return config('suspicious-login.preset');
});
```

`is_admin` and `skip_login_checks` are example attributes on your application's user model. The callback receives the unsaved attempt with its user, guard, IP address, user agent and custom properties, before any location lookup. Return a preset class name, not `null`. It is resolved through Laravel's container once per successful login, and the same instance supplies both checks and actions. Without a callback, every successful login uses the configured preset.

The callback also applies to manual recording and `assess()`. Failed logins are still recorded without selecting a preset. With `NonePreset`, `LoginAttemptRecordedEvent` still fires, but `SuspiciousLoginDetectedEvent` does not. Unlike `MonitorOnlyPreset`, it does not calculate a risk score. Successful, unblocked logins recorded with `NonePreset` still count as trusted history for future checks.

## The same login, five presets

Here's how Anna's login from New York, ten hours after her usual morning login in Antwerp, plays out with each preset. She has logged in every morning for a few weeks, and a geolocation driver is configured.

| Preset | Flagged by | Score | Risk level | What happens |
| --- | --- | --- | --- | --- |
| `LenientPreset` | new device (20), new country (30) | 50 | medium | nothing |
| `BalancedPreset` | new device (25), new IP (10), new country (30) | 65 | medium | Anna gets an email |
| `StrictPreset` | new device (30), new IP (10), new country (30), unusual time (15) | 85 | high | Anna gets an email and the login is blocked |
| `MonitorOnlyPreset` | same as `BalancedPreset` | 65 | medium | the attempt is stored, nothing else |
| `NonePreset` | no checks run | unset | unset | the attempt is stored without assessment |

## Start with MonitorOnlyPreset

For an app with existing users, set `MonitorOnlyPreset` in `config/suspicious-login.php` to collect scores without notifying or blocking anyone:

```php
'preset' => Spatie\SuspiciousLogin\Presets\MonitorOnlyPreset::class,
```

It still fires events, so any listeners you registered continue to run. Let it run for a week or two, then look at the scores your real users get:

```php
use Spatie\SuspiciousLogin\Models\LoginAttempt;

LoginAttempt::query()
    ->whereNot('risk_level', 'low')
    ->latest()
    ->get(['authenticatable_type', 'authenticatable_id', 'ip_address', 'country_code', 'risk_score', 'reasons']);
```

If people who travel a lot or use a VPN show up as high risk, [lower the weight of those checks](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets) before switching to a preset that notifies or blocks.

## What each preset contains

| | `LenientPreset` | `BalancedPreset` | `StrictPreset` |
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

The table uses the default config. `BalancedPreset` and `MonitorOnlyPreset` read their risk thresholds from `risk_thresholds` in `config/suspicious-login.php`. `LenientPreset` and `StrictPreset` define their own thresholds, which take precedence over the config.
