---
title: Getting started
weight: 0
---

After [installing the package](/docs/laravel-suspicious-login/v1/installation-setup), this example adds suspicious-login detection to a Laravel application with session-based authentication. We'll notify users when they log in from a new country, and block the login when the device is new too.

The package listens to Laravel's authentication events. You don't need to change your login controller when it already uses Laravel's authentication system.

## Create a preset

A preset chooses the checks to run, their scores, and the actions for medium and high risk. Create `app/SuspiciousLogin/MyPreset.php`:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\NewCountryCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Presets\Preset;

class MyPreset extends Preset
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

Each check adds its score when it flags the login. The configured thresholds default to 40 for medium risk and 70 for high risk. `NotifyUserAction` sends the user an email; `BlockLoginAction` marks the attempt as blocked, logs the user out, and throws a validation exception. Put blocking last because it ends the request. High risk runs only `onHighRisk()`, not the medium-risk actions as well.

You can also [choose a built-in preset](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset) instead of writing one. `DefaultPreset` notifies users without blocking them. See [customizing presets](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets) for extending a built-in preset.

Register the preset in `config/suspicious-login.php`:

```php
'preset' => App\SuspiciousLogin\MyPreset::class,
```

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

## Customize further

- [Inspect login attempts](/docs/laravel-suspicious-login/v1/basic-usage/working-with-login-attempts#content-inspecting-scores-and-reasons) to see recorded scores, reasons and blocked logins.
- [Choose a preset per login](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset#content-choosing-a-preset-per-login) to apply stricter checks to admins or skip assessment with `NonePreset`.
- [Add custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties) to store application context with each attempt.
- [Record logins manually](/docs/laravel-suspicious-login/v1/basic-usage/recording-logins-manually) when your authentication flow doesn't fire Laravel's events. Don't combine manual and automatic recording for the same login.
- [Write your own checks](/docs/laravel-suspicious-login/v1/advanced-usage/creating-custom-checks) for application-specific signals.
- [Write your own actions](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/writing-your-own-actions) or [listen for events](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events) to integrate with other services.
