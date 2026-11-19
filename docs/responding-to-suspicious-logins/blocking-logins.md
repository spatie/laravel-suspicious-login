---
title: Blocking logins
weight: 2
---

Sometimes telling the user afterwards isn't enough. `Spatie\SuspiciousLogin\Actions\BlockLoginAction` logs the user back out during the login request and rejects it with a validation error. `StrictPreset` uses it for high risk logins.

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

## Customizing the response

To change how a blocked login responds, extend `BlockLoginAction` and override its protected `respond` method:

```php
namespace App\SuspiciousLogin\Actions;

use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class BlockLoginWithForbiddenResponseAction extends BlockLoginAction
{
    protected function respond(LoginAttempt $attempt): never
    {
        abort(403, 'This login requires verification.');
    }
}
```

Use your action in the preset instead of `BlockLoginAction`:

```php
use App\SuspiciousLogin\Actions\BlockLoginWithForbiddenResponseAction;
use Spatie\SuspiciousLogin\Actions\Action;

public function onHighRisk(): Action|array
{
    return BlockLoginWithForbiddenResponseAction::new();
}
```

The attempt is already marked as blocked, the blocked event has been fired, and the user has been logged out when `respond` runs. The method receives the attempt so you can use its ID, risk level or reasons in your response.

The return type is `never`: your method must throw an exception or call `abort()`, not return a response. Actions run inside the login event listener, so returning a response would not send it to the browser. For example, use `abort(redirect('/login-verification'))` for a redirect, or `abort(response()->json(['message' => 'Login blocked.'], 403))` for JSON. Any verification flow is up to your app; the user has already been logged out.

The `errorKey()` and `message()` options configure the default validation response. An override must use them explicitly if it needs them.

## Letting a user in again

A blocked login never makes its device, IP address or country known. So when the real user tries again from the same laptop, the login is likely to be blocked again.

Once you have confirmed it was them, for example through the support desk, unblock the attempt:

```php
$attempt->update(['blocked' => false]);
```

From then on, that attempt counts as a normal successful login, and its device, IP address and country are known for the next one.
