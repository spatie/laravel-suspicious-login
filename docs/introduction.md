---
title: Introduction
weight: 1
---

A stolen password rarely looks like an attack. The attacker types the right email address and the right password, and to your app that's a perfectly normal login.

This package looks at everything around that login: the device, the IP address, where in the world it comes from, how many attempts failed before it, and what the user usually does. Each suspicious signal adds points to a risk score, and the risk score decides what happens next.

Here's an example. Anna logs in to your app every morning from Antwerp, on the same MacBook. One evening, someone logs in to her account from a Windows laptop in New York. With the default `Balanced` preset and a [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers) configured, the package scores that login like this:

```
Login from a new device                  +25
Login from a new IP address (5.6.7.8)    +10
Login from a new country (US)            +30
                                         ----
Risk score                                65  → medium risk
```

A medium risk login sends Anna an email that lists those reasons, so she can change her password if it wasn't her.

If that login had happened thirty minutes after her morning login in Antwerp, nobody could have traveled that far in time. The impossible travel check would add 60 points, and the login would be high risk. With the `Strict` preset, the package blocks a high risk login right away.

The package listens for Laravel's own login events, so it works with Breeze, Jetstream, Fortify and your own login controllers without any changes.

## Everything is customizable

What the package checks and how it responds is decided by a preset. A preset is a small PHP class that lists the checks to run, how much each one weighs, where the risk levels start, and which actions run for each level. The package ships with four presets, and writing your own takes a few lines:

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
    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(30),
            ImpossibleTravelCheck::new()->maxSpeedInKilometersPerHour(1000)->score(60),
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

This preset uses the default risk thresholds from `config/suspicious-login.php`: 40 for medium risk and 70 for high risk. You can change the config values, or [override either threshold in your preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets#configuring-risk-thresholds).

Checks, actions, geolocation and IP reputation lookups, device fingerprints and the model are all swappable. And when you want to react to something yourself, the package [fires events](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events) you can listen for.
