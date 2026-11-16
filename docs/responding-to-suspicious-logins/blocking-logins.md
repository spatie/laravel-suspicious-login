---
title: Blocking logins
weight: 2
---

Sometimes telling the user afterwards isn't enough. `Spatie\SuspiciousLogin\Actions\BlockLoginAction` stops a suspicious login before the user gets in. The `Strict` preset uses it for high risk logins.

```php
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Enums\RiskLevel;

public function actions(RiskLevel $riskLevel): array
{
    return match ($riskLevel) {
        RiskLevel::Medium => [NotifyUserAction::new()],
        RiskLevel::High => [NotifyUserAction::new(), BlockLoginAction::new()],
        default => [],
    };
}
```

When it runs, the action:

1. marks the login attempt as `blocked`,
2. fires a [`SuspiciousLoginBlockedEvent`](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events),
3. logs the user out of the guard they just logged in to,
4. throws a `ValidationException`.

Because the login happens inside `Auth::attempt()`, that exception comes out of your login controller like any other validation error. A regular form redirects back with an error on the `email` field, and a JSON request gets a 422 response. Breeze, Jetstream and Fortify show the error without any changes.

The exception ends the request, so always put `BlockLoginAction` after the other actions.

## Customizing the error

```php
BlockLoginAction::new()
    ->errorKey('username')
    ->message('We blocked this login because it looked suspicious. Check your email.'),
```

The message is passed through Laravel's `__()` helper, so you can translate it.

## Letting a user in again

A blocked login never makes its device, IP address or country known. So when the real user tries again from the same laptop, the login is likely to be blocked again.

Once you have confirmed it was them, for example through the support desk, unblock the attempt:

```php
$attempt->update(['blocked' => false]);
```

From then on, that attempt counts as a normal successful login, and its device, IP address and country are known for the next one.
