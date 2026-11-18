---
name: detecting-suspicious-logins
description: "Installs and customizes spatie/laravel-suspicious-login. Use when working with login risk scoring, presets, checks, blocking, notifications, login history, device fingerprints, or geolocation and IP reputation drivers in this package."
---

# Detecting suspicious logins

Use `Spatie\SuspiciousLogin` as the namespace and `Spatie\SuspiciousLogin\Facades\SuspiciousLogin` as the facade. This package records successful and failed authentication attempts, scores successful logins, and runs actions for the resulting `RiskLevel` enum (`Low`, `Medium`, `High`). It does not provide a login UI, MFA challenges, session management UI, or API token revocation.

## Choose the integration

1. Inspect `config/suspicious-login.php`, the selected preset, the app's auth flow, and any existing package customizations before changing behavior.
2. For Laravel authentication that fires `Illuminate\Auth\Events\Login` and `Failed`, use automatic recording. Do not also record manually; that stores the login twice. Restoring a session with a remember-me cookie is ignored.
3. For custom authentication, read [recording and history](references/recording-and-history.md). Record and assess before issuing API tokens; `BlockLoginAction` only logs out session guards.
4. For scoring alone, use `assess()`. It fills score, level, and reasons without saving, firing events, or executing actions. Supply the user, fingerprint, and location yourself.

## Install and configure

The package requires PHP 8.4+ and supports Laravel 11, 12, and 13.

```bash
composer require spatie/laravel-suspicious-login
php artisan suspicious-login:install
```

The install command publishes config and migrations and offers to run migrations. On an existing app, start with `'preset' => Spatie\SuspiciousLogin\Presets\MonitorOnly::class` to observe scores without preset actions. Events still fire. For notifications, the user needs `Notifiable`, configured mail, and a queue worker unless the queue connection is `sync`.

Configure behavior in `config/suspicious-login.php`; use direct config values for driver selection, enabled state, and database paths. Keep secrets such as API tokens in environment-backed config values. Geolocation defaults to `ipinfo`, which sends public IPs to ipinfo.io without a token, subject to its request limits. IP reputation defaults to `null`. Read [drivers and customization](references/drivers-and-customization.md) for credentials, caching, custom implementations, and proxy requirements.

## Presets and risk levels

- Configure a preset class with `suspicious-login.preset`. Extend `Preset`, or extend a built-in preset and append to `parent::checks()`.
- `Balanced` notifies at medium and high risk; `Lenient` only notifies at high risk; `Strict` notifies at both levels and blocks high-risk session logins; `MonitorOnly` runs the Balanced checks without actions.
- The default config thresholds are medium 40 and high 70. `Balanced` and `MonitorOnly` inherit them. `Strict` overrides them with 30/60 and `Lenient` with 50/80. A custom preset may define either `protected int $mediumRiskThreshold` or `protected int $highRiskThreshold`; omitted values come from config.
- Return checks from `checks(): array`. `Check::new()` resolves through the container, and `score()` sets the points added when that check flags a login. Scores are points, not probabilities; the total is capped at 100.
- `onMediumRisk(): Action|array` and `onHighRisk(): Action|array` are optional and default to no actions. Return one action or an array. High risk only runs the high-risk actions, not the medium-risk actions too. Low risk runs none.

For example, place this preset in `app/SuspiciousLogin/DetectSuspiciousLogins.php`:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\MultipleCountriesCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Presets\Preset;

class DetectSuspiciousLogins extends Preset
{
    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(30),
            MultipleCountriesCheck::new()->withinMinutes(60)->score(50),
        ];
    }

    public function onMediumRisk(): Action|array
    {
        return NotifyUserAction::new();
    }

    public function onHighRisk(): Action|array
    {
        return [NotifyUserAction::new(), BlockLoginAction::new()];
    }
}
```

Register it in `config/suspicious-login.php`:

```php
'preset' => App\SuspiciousLogin\DetectSuspiciousLogins::class,
```

## Checks and actions

Read [checks and actions](references/checks-and-actions.md) before choosing checks, changing their options, writing a custom check or action, or overriding the blocked-login response. It lists all built-in checks and their defaults, and explains notification customization and events.

Choose scores with overlapping signals in mind. A country change can trigger new-country, multiple-countries, and impossible-travel checks together. VPNs and legitimate travel can trigger country checks.

## History and extension points

- Read [recording and history](references/recording-and-history.md) for manual authentication, assessment, custom properties, model queries, and pruning. History belongs to the user across guards, not to a guard or tenant automatically.
- Read [drivers and customization](references/drivers-and-customization.md) for replacing the model, device fingerprinter, geolocation driver, or reputation driver. Use these extension points instead of adding small config switches or changing vendor classes.
- The package has no built-in webhook action. Implement an application-owned action that queues delivery if needed; synchronous HTTP calls delay the login request.

Full documentation: https://spatie.be/docs/laravel-suspicious-login/v1/introduction
