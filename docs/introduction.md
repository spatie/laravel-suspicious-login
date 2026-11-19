---
title: Introduction
weight: 1
---

A login with the correct password can still come from an attacker. This package checks the device, IP address, location and login history for suspicious activity.

Each suspicious signal adds points to a risk score. Your preset decides which checks run and whether to notify the user, block the login or run your own actions.

For example, Anna usually logs in from Antwerp on her MacBook. Someone signs in to her account from a new device in New York. With `DefaultPreset` and its ipinfo location lookup:

```
Login from a new device                  +25
Login from a new IP address (5.6.7.8)    +10
Login from a new country (US)            +30
                                         ----
Risk score                                65  → medium risk
```

A medium risk login emails Anna with those reasons. `DefaultPreset` never blocks logins. You can [choose a built-in preset](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset) or [write your own](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets) to block high risk logins or run other actions.

Here's what a custom preset looks like:

```php
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Presets\Preset;

class MyPreset extends Preset
{
    protected int $mediumRiskThreshold = 40;

    protected int $highRiskThreshold = 70;

    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(30),
            ImpossibleTravelCheck::new()
                ->maxSpeedInKilometersPerHour(1000)
                ->score(60),
            CountryCheck::new()->onlyAllow(['BE', 'NL', 'FR'])->score(60),
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

This preset sets medium risk to start at 40 and high risk at 70. Both threshold properties are optional; leave them out to use the values in `config/suspicious-login.php`. Checks add their scores when they flag a login; only the actions for the resulting risk level run. Put `BlockLoginAction` last because it ends the login request. You can [customize the preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets), or [choose a different preset per login](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset#content-choosing-a-preset-per-login).

The package listens for Laravel's login events, so it works with Breeze, Jetstream, Fortify and your own session login controllers. Checks, actions and drivers are replaceable, and [events](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events) let you react to login attempts yourself.

Follow [getting started](/docs/laravel-suspicious-login/v1/basic-usage/getting-started) for installation, a complete preset and an example login flow.
