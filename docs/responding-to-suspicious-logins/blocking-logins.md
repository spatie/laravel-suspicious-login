---
title: Blocking logins
weight: 2
---

Sometimes telling the user afterwards isn't enough. `Spatie\SuspiciousLogin\Actions\BlockLoginAction` logs the user back out during the login request and rejects it with a validation error. The `Strict` preset uses it for high risk logins.

```php
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;

public function onMediumRisk(): Action|array
{
    return NotifyUserAction::new();
}

public function onHighRisk(): Action|array
{
    return [NotifyUserAction::new(), BlockLoginAction::new()];
}
```

When it runs, the action:

1. marks the login attempt as `blocked`,
2. fires a [`SuspiciousLoginBlockedEvent`](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events),
3. logs the user out of the guard they just logged in to,
4. throws a `ValidationException`.

Because the login happens inside `Auth::attempt()`, that exception comes out of your login controller like any other validation error. A regular form redirects back with an error on the `email` field, and a JSON request gets a 422 response. Breeze, Jetstream and Fortify show the error without any changes.

The exception ends the request, so always put `BlockLoginAction` last in the array.

This action requires a session guard with a `logout()` method. It does not revoke API tokens or support stateless guards. For token-based authentication, use a [custom action](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/writing-your-own-actions) and reject the attempt before issuing a token.

The user has already been authenticated when the action runs. It does not undo other login listeners or revoke existing sessions. Throwing an exception from a listener alone is not a replacement for logging the user out.

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
