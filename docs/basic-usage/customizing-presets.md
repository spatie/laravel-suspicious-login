---
title: Customizing presets
weight: 3
---

A preset is a plain PHP class. To change how the package behaves, you extend one of the shipped presets or write your own, and point the config file at it.

## Configuring risk thresholds

The default thresholds live in `config/suspicious-login.php`:

```php
'risk_thresholds' => [
    'medium' => 40,
    'high' => 70,
],
```

`BalancedPreset`, `MonitorOnlyPreset` and custom presets use these values unless they define their own thresholds. `StrictPreset` overrides them with 30 and 60; `LenientPreset` uses 50 and 80.

To override either threshold in a preset, define `$mediumRiskThreshold` or `$highRiskThreshold`. A property takes precedence over the config value. Leave a property out to inherit the parent's threshold, or the config value when the parent doesn't define one.

## Tweaking a shipped preset

Let's say you like `BalancedPreset`, but you want to block logins from one country and you consider a new IP address meaningless because your users are mostly on mobile networks. Extend the preset and override what you need:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Checks\NewIpCheck;
use Spatie\SuspiciousLogin\Presets\BalancedPreset;

class MyPreset extends BalancedPreset
{
    protected int $highRiskThreshold = 60;

    public function checks(): array
    {
        return collect(parent::checks())
            ->reject(fn (Check $check) => $check instanceof NewIpCheck)
            ->push(CountryCheck::new()->block(['KP'])->score(100))
            ->values()
            ->all();
    }

    public function onHighRisk(): Action|array
    {
        return [NotifyUserAction::new(), BlockLoginAction::new()];
    }
}
```

Then register it in `config/suspicious-login.php`:

```php
'preset' => App\SuspiciousLogin\MyPreset::class,
```

## Writing your own preset

To start from scratch, extend `Spatie\SuspiciousLogin\Presets\Preset`. It has one method you must implement, and two optional ones:

- `checks()` returns the checks to run, each configured with its score and options.
- `onMediumRisk()` and `onHighRisk()` return what happens to a login at that level: a single action, or an array of actions that run in order. Leave one out and that level takes no action.

The `$mediumRiskThreshold` and `$highRiskThreshold` properties are optional. The example below overrides both; omit them to use the configured defaults.

Only the actions for the matching risk level run. A high risk login does not also run `onMediumRisk()`. When extending a shipped preset, omitting an action method keeps the parent's behavior. Override it and return `[]` to disable those actions.

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Presets\Preset;

class AdminPanelPreset extends Preset
{
    protected int $mediumRiskThreshold = 20;

    protected int $highRiskThreshold = 50;

    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(30),
            ImpossibleTravelCheck::new()->score(60),
            FailedAttemptsCheck::new()->threshold(3)->withinMinutes(30)->score(30),
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
            BlockLoginAction::new()->message('This login was blocked. Please contact support if this was you.'),
        ];
    }
}
```

## Choosing scores and thresholds

Scores are points, not percentages. A few guidelines that work well:

- Give a check that is suspicious on its own, such as impossible travel or a blocked country, a score at or above the high risk threshold.
- Give weak signals, such as a new IP address, a low score. They only matter when they add up with other signals.
- The total is capped at 100, so scores above 100 behave the same as 100.

If you want a completely different mapping from score to risk level, override the `riskLevel(int $score): RiskLevel` method of your preset. `RiskLevel` is the `Spatie\SuspiciousLogin\Enums\RiskLevel` enum, with the cases `Low`, `Medium` and `High`.
