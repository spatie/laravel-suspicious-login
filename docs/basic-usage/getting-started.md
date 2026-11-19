---
title: Getting started
weight: 0
---

This example adds suspicious-login detection to a Laravel application with session-based authentication. We'll notify users when they log in from a new country, and block the login when the device is new too.

The package listens to Laravel's authentication events. You don't need to change your login controller when it already uses Laravel's authentication system.

## Install the package

```bash
composer require spatie/laravel-suspicious-login
php artisan suspicious-login:install
```

The install command publishes `config/suspicious-login.php` and the migration for the `login_attempts` table. Accept its offer to run migrations, or run `php artisan migrate` yourself. See [installation and setup](/docs/laravel-suspicious-login/v1/installation-setup) for the complete config and manual publishing commands.

## Create a preset

A preset chooses the checks to run, their scores, and the actions for medium and high risk. Create `app/SuspiciousLogin/ProtectLoginsPreset.php`:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\NewCountryCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Presets\Preset;

class ProtectLoginsPreset extends Preset
{
    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(30),
            NewCountryCheck::new()->score(40),
        ];
    }

    public function onMediumRisk(): Action|array
    {
        return NotifyUserAction::new();
    }

    public function onHighRisk(): Action|array
    {
        return [
            NotifyUserAction::new(),
            BlockLoginAction::new(),
        ];
    }
}
```

Each check adds its score when it flags the login. `NotifyUserAction` sends the user an email; `BlockLoginAction` marks the attempt as blocked, logs the user out, and throws a validation exception. Put blocking last because it ends the request. High risk runs only `onHighRisk()`, not the medium-risk actions as well.

You can also [choose a built-in preset](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset) instead of writing one. The default `BalancedPreset` notifies users without blocking them. See [customizing presets](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets) for extending a built-in preset.

## Configure the package

In the published `config/suspicious-login.php`, set these values, leaving the other options in place:

```php
'preset' => App\SuspiciousLogin\ProtectLoginsPreset::class,

'risk_thresholds' => [
    'medium' => 40,
    'high' => 70,
],

'geolocation' => [
    'driver' => 'ipinfo',
    'cache_ttl' => 60 * 60 * 24 * 7,

    'ipinfo' => [
        'token' => null,
    ],
],
```

The `ipinfo` driver is the default and works without a token, subject to ipinfo's limits. It sends public IP addresses to ipinfo.io and caches locations for a week. If the lookup fails, the country check passes. You can [use Cloudflare, MaxMind, or another geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers) instead. Setting the driver to `'null'` disables location lookups.

To receive the notification, your user model needs Laravel's `Notifiable` trait and your application needs working mail configuration. The notification is queued, so run a queue worker unless your queue connection is `sync`. See [notifying users](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/notifying-users) for customizing the email, and [blocking logins](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/blocking-logins) for changing the rejection response.

## What happens at login

Suppose Anna has already logged in successfully from her usual browser in Belgium. With this preset:

| Login | Score | Risk | Result |
| --- | --- | --- | --- |
| Known device, known country | 0 | low | Record the attempt; no action |
| New device, known country | 30 | low | Record the attempt; no action |
| Known device, new country | 40 | medium | Record the attempt and notify Anna |
| New device, new country | 70 | high | Record the attempt, notify Anna, and block the login |

The first login has no history to compare against, so these two checks don't flag it. Failed logins are recorded but aren't scored, and failed or blocked attempts don't make a device or country familiar. VPNs and legitimate travel can trigger the country check; choose scores and actions that suit your application.

Read [how a login is scored](/docs/laravel-suspicious-login/v1/basic-usage/how-a-login-is-scored) for the full lifecycle and [available checks](/docs/laravel-suspicious-login/v1/available-checks/overview) for other signals you can add.

## Inspect login attempts

Query the recorded attempts through the `LoginAttempt` model:

```php
use Spatie\SuspiciousLogin\Models\LoginAttempt;

$attempts = LoginAttempt::query()
    ->whereMorphedTo('authenticatable', $user)
    ->latest()
    ->limit(10)
    ->get();

foreach ($attempts as $attempt) {
    $attempt->risk_score;
    $attempt->risk_level;
    $attempt->reasons;
    $attempt->blocked;
}
```

`reasons` contains the flagged checks, their scores, and their explanations. `risk_level` is a `RiskLevel` enum on assessed attempts; failed logins and logins using `NonePreset` have no risk level. See [working with login attempts](/docs/laravel-suspicious-login/v1/basic-usage/working-with-login-attempts) for history queries and pruning.

## Customize further

- [Choose a preset per login](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset#content-choosing-a-preset-per-login) to apply stricter checks to admins or skip assessment with `NonePreset`.
- [Add custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties) to store application context with each attempt.
- [Record logins manually](/docs/laravel-suspicious-login/v1/basic-usage/recording-logins-manually) when your authentication flow doesn't fire Laravel's events. Don't combine manual and automatic recording for the same login.
- [Write your own checks](/docs/laravel-suspicious-login/v1/advanced-usage/creating-custom-checks) for application-specific signals.
- [Write your own actions](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/writing-your-own-actions) or [listen for events](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events) to integrate with other services.
