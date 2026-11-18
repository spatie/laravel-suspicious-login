---
title: Creating custom checks
weight: 1
---

Write a custom check when you want a signal the [built-in checks](/docs/laravel-suspicious-login/v1/available-checks/overview) don't cover, such as a login to an admin account or an account that has been inactive for months.

A check is a class that extends `Spatie\SuspiciousLogin\Checks\Check` and implements `evaluate`. It receives the `LoginAttempt` that is being scored and returns either `$this->pass()` or `$this->flag($reason)`:

```php
namespace App\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class AdminLoginCheck extends Check
{
    protected int $score = 20;

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->authenticatable?->is_admin) {
            return $this->pass();
        }

        return $this->flag('Login to an admin account');
    }
}
```

The `$score` property is the default score. Like every check, yours gets a `score()` method to change it in a preset. The reason you pass to `flag` is stored in the `reasons` of the login attempt and shown in the email to the user, so write it for a person.

To add it to the existing `Balanced` checks, create `app/SuspiciousLogin/MyPreset.php`:

```php
namespace App\SuspiciousLogin;

use App\SuspiciousLogin\Checks\AdminLoginCheck;
use Spatie\SuspiciousLogin\Presets\Balanced;

class MyPreset extends Balanced
{
    public function checks(): array
    {
        return [
            ...parent::checks(),
            AdminLoginCheck::new()->score(30),
        ];
    }
}
```

Then select it in `config/suspicious-login.php`:

```php
'preset' => App\SuspiciousLogin\MyPreset::class,
```

This keeps `Balanced`'s notification actions. To choose your own actions and thresholds, see [customizing presets](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets).

## Looking at earlier logins

Most interesting signals compare a login with what came before. `$attempt->history()` returns a query for the other login attempts of the same user, up to this one. Combine it with the `trusted()`, `failed()` and `since()` scopes.

Here's a check that flags the first login to an account that hasn't been used in months, with an option to configure how many:

```php
namespace App\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class DormantAccountCheck extends Check
{
    protected int $score = 20;

    protected int $days = 90;

    public function inactiveForDays(int $days): static
    {
        $this->days = $days;

        return $this;
    }

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $previousLogin = $attempt->history()->trusted()->latest()->first();

        if (! $previousLogin) {
            return $this->pass();
        }

        if ($previousLogin->created_at->diffInDays($attempt->created_at) < $this->days) {
            return $this->pass();
        }

        return $this->flag("First login in more than {$this->days} days");
    }
}
```

```php
DormantAccountCheck::new()->inactiveForDays(180)->score(25),
```

A check can also read [custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties) you add to every login, with `$attempt->getProperty()`.

Use `trusted()` when you compare with what the user normally does. It only returns successful logins that weren't blocked, so an attacker can't shape the history by failing to log in.

## Injecting dependencies

Checks are resolved from the container by `new()`, so you can type-hint dependencies in the constructor:

```php
use App\Services\BreachedPasswords;

class BreachedPasswordCheck extends Check
{
    public function __construct(
        protected BreachedPasswords $breachedPasswords,
    ) {}

    // ...
}
```

Checks run during the login request. If your check calls an external service, give it a short timeout and cache the result, the way the [geolocation drivers](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers) do.
