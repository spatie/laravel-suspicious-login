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
| `successful` | Whether authentication succeeded, even if a preset action then blocked the login. Use `trusted()` for successful logins that were not blocked. |
| `blocked` | Whether the login was [blocked](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/blocking-logins). |
| `ip_address`, `user_agent` | Taken from the request. |
| `device_fingerprint` | A hash that identifies the device. See [device fingerprints](/docs/laravel-suspicious-login/v1/advanced-usage/device-fingerprints). |
| `country_code`, `city`, `latitude`, `longitude` | The location, when a [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers) found one. |
| `risk_score`, `risk_level` | The score from 0 to 100 and the `RiskLevel` enum. `null` for failed logins and logins using `NonePreset`. |
| `reasons` | An array with the check, score and human readable reason for every check that flagged the login. `null` when the login wasn't assessed. |
| `properties` | Your [custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties), or `null` when there are none. |

## Inspecting scores and reasons

Query a user's latest attempts through the `LoginAttempt` model:

```php
use Spatie\SuspiciousLogin\Models\LoginAttempt;

$attempts = LoginAttempt::query()
    ->whereMorphedTo('authenticatable', $user)
    ->latest()
    ->limit(10)
    ->get();

$summaries = $attempts->map(fn (LoginAttempt $attempt) => [
    'score' => $attempt->risk_score,
    'level' => $attempt->risk_level?->value,
    'reasons' => $attempt->reasons,
    'blocked' => $attempt->blocked,
]);
```

`get()` returns an Eloquent collection. Here, `map()` turns each attempt into a summary you can display or return from a controller. Each attempt's `reasons` contains the flagged checks, their scores, and their explanations. `risk_level` is a `RiskLevel` enum on assessed attempts; failed logins and logins using `NonePreset` have no risk level. `blocked` tells you whether a blocking action rejected the login after authentication succeeded.

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

History is scoped to the user, not the guard. Logins to different guards share history when they use the same user model and ID.

## Limiting history lookback

By default, `history()` uses all retained attempts up to and including the current attempt's timestamp, excluding the current record itself. You can opt into a shorter history in `config/suspicious-login.php`:

```php
'history_lookback_days' => 30,
```

Use a positive integer number of days, or `null` to keep the default. Invalid values throw an `InvalidArgumentException` when history is queried. The lower boundary is inclusive and relative to the attempt's timestamp, not today's date. This also works when reassessing older attempts.

Enabling this changes what the checks know:

- `NewDeviceCheck`, `NewIpCheck`, and `NewCountryCheck` forget matches outside the lookback. An established device can look new if the user has other trusted history inside the lookback. If no relevant trusted history remains, these checks pass as they do for a first login.
- `ImpossibleTravelCheck` only compares with a located trusted login inside the lookback. If none remains, it passes. This can hide travel that a custom speed threshold would otherwise flag.
- `UnusualTimeCheck` has fewer records to learn from and may no longer meet its minimum number of logins.
- `FailedAttemptsCheck` and `MultipleCountriesCheck` use the intersection of their own windows and the lookback. A one-day lookback does not shorten their default 15-minute and 60-minute windows, but it can shorten custom windows longer than a day.
- `CredentialStuffingCheck` queries failures by IP independently of `history()`, so this setting does not change its window.

Custom checks and application queries that call `history()` also inherit the lookback. It does not delete records, change `since()` itself, or change queries built directly with `LoginAttempt::query()`. Keep it `null` if long-term recognition matters more than restricting the history searched.

## Query costs on large tables

Publish and run the [history-index migration](/docs/laravel-suspicious-login/v1/installation-setup#content-adding-history-indexes-to-an-existing-installation) when upgrading an existing installation. The indexes support account-specific failure windows, trusted history ordered by time, device/country/IP recognition, and pruning by timestamp. They use ordinary compound indexes rather than database-specific partial or expression indexes. The original account index remains available for compact account-only queries, and the global IP/time index still supports credential-stuffing queries.

Indexes and lookbacks do not impose a fixed limit on database work. A million attempts can fit inside a short window. Travel checks can still scan many recent trusted logins without coordinates. Exact failure counts still inspect all matching failures. Optional `limitToThreshold()` on [failed attempts](/docs/laravel-suspicious-login/v1/available-checks/failed-attempts) and [credential stuffing](/docs/laravel-suspicious-login/v1/available-checks/credential-stuffing) avoids counting beyond the configured threshold, but finding enough distinct identifiers can still scan the entire IP window. Query plans also depend on your database's optimizer and data distribution.

When you [use your own model](/docs/laravel-suspicious-login/v1/advanced-usage/using-your-own-model), import that model in your application queries instead of the package model.
