---
title: Customizing presets
weight: 3
---

A preset is a plain PHP class. To change how the package behaves, you extend one of the shipped presets or write your own, and point the config file at it.

## Tweaking a shipped preset

Let's say you like `Balanced`, but you want to block logins from one country and you consider a new IP address meaningless because your users are mostly on mobile networks. Extend the preset and override what you need:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Checks\NewIpCheck;
use Spatie\SuspiciousLogin\Presets\Balanced;

class MyPreset extends Balanced
{
    protected int $highRiskThreshold = 60;

    public function checks(): array
    {
        $checks = array_filter(
            parent::checks(),
            fn (Check $check) => ! $check instanceof NewIpCheck,
        );

        return [
            ...$checks,
            CountryCheck::new()->block(['KP'])->score(100),
        ];
    }
}
```

Then register it in `config/suspicious-login.php`:

```php
'preset' => App\SuspiciousLogin\MyPreset::class,
```

## Writing your own preset

To start from scratch, extend `Spatie\SuspiciousLogin\Presets\Preset`. It has two methods to implement:

- `checks()` returns the checks to run, each configured with its score and options.
- `actions(RiskLevel $riskLevel)` returns the actions to run for a suspicious login of that risk level. It is only called for medium and high risk logins.

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Enums\RiskLevel;
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

    public function actions(RiskLevel $riskLevel): array
    {
        return match ($riskLevel) {
            RiskLevel::Medium => [NotifyUserAction::new()],
            RiskLevel::High => [
                NotifyUserAction::new(),
                BlockLoginAction::new()->message('This login was blocked. Ask a colleague to unlock your account.'),
            ],
            default => [],
        };
    }
}
```

## Choosing scores and thresholds

Scores are points, not percentages. A few guidelines that work well:

- Give a check that is suspicious on its own, such as impossible travel or a blocked country, a score at or above the high risk threshold.
- Give weak signals, such as a new IP address, a low score. They only matter when they add up with other signals.
- The total is capped at 100, so scores above 100 behave the same as 100.

If you want a completely different mapping from score to risk level, override the `riskLevel(int $score): RiskLevel` method of your preset.

