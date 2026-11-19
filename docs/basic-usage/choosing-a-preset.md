---
title: Choosing a preset
weight: 2
---

A preset decides which checks run, how much each one weighs, where the risk levels start, and what happens at each level. The package ships with three:

| Preset | Use it when |
| --- | --- |
| `DefaultPreset` | Notifies users about medium and high risk logins, and never blocks anyone. This is the default. |
| `MonitorOnlyPreset` | You're trying the package out. Runs the `DefaultPreset` checks and stores the results, but takes no action. |
| `NonePreset` | You only want to record the login. Skips location lookups, checks and actions, leaving the score, risk level and reasons unset. |

You set the preset in `config/suspicious-login.php`:

```php
'preset' => Spatie\SuspiciousLogin\Presets\DefaultPreset::class,
```

## Choosing a preset per login

Register a callback in your service provider's `boot()` method to choose a preset based on the user, guard or [custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties):

```php
use App\SuspiciousLogin\AdminPanelPreset;
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\DefaultPreset;
use Spatie\SuspiciousLogin\Presets\NonePreset;

SuspiciousLogin::determinePresetUsing(function (LoginAttempt $attempt): string {
    $user = $attempt->authenticatable;

    if ($user->is_admin) {
        return AdminPanelPreset::class;
    }

    if ($user->skip_login_checks) {
        return NonePreset::class;
    }

    return DefaultPreset::class;
});
```

`AdminPanelPreset` is a [custom preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets#content-writing-your-own-preset); `is_admin` and `skip_login_checks` are example user attributes. Return a preset class name, using `NonePreset` to skip checks. The callback also applies to manual recording and `assess()`, but not failed logins.

## Checks in DefaultPreset

`DefaultPreset` and `MonitorOnlyPreset` run the same checks:

| Check | Score when flagged |
| --- | --- |
| [`NewDeviceCheck`](/docs/laravel-suspicious-login/v1/available-checks/new-device) | 25 |
| [`NewIpCheck`](/docs/laravel-suspicious-login/v1/available-checks/new-ip) | 10 |
| [`NewCountryCheck`](/docs/laravel-suspicious-login/v1/available-checks/new-country) | 30 |
| [`ImpossibleTravelCheck`](/docs/laravel-suspicious-login/v1/available-checks/impossible-travel) | 60 |
| [`FailedAttemptsCheck`](/docs/laravel-suspicious-login/v1/available-checks/failed-attempts) | 30, from 5 failures in 15 minutes |
| [`CredentialStuffingCheck`](/docs/laravel-suspicious-login/v1/available-checks/credential-stuffing) | 40, from 5 accounts |
| [`SuspiciousUserAgentCheck`](/docs/laravel-suspicious-login/v1/available-checks/suspicious-user-agent) | 20 |

Both presets read their risk thresholds from `risk_thresholds` in `config/suspicious-login.php`: medium risk starts at 40 and high risk at 70 by default. To change weights, add checks, override thresholds or block logins, [create a custom preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets).
