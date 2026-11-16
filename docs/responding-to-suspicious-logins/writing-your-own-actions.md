---
title: Writing your own actions
weight: 3
---

An action is anything that should happen when a login is suspicious. Notifying the user and blocking the login are built in. For anything else, write your own action: alert your security team, ask for a second factor, or end other sessions.

An action extends `Spatie\SuspiciousLogin\Actions\Action` and implements `execute`. It receives the `LoginAttempt`, with its user, score, risk level and reasons:

```php
namespace App\SuspiciousLogin\Actions;

use Illuminate\Support\Facades\Log;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class LogToSecurityChannelAction extends Action
{
    public function execute(LoginAttempt $attempt): void
    {
        Log::channel('security')->warning('Suspicious login', [
            'user_id' => $attempt->authenticatable_id,
            'ip' => $attempt->ip_address,
            'risk_score' => $attempt->risk_score,
            'reasons' => collect($attempt->reasons)->pluck('reason')->all(),
        ]);
    }
}
```

Use it in your preset like any other action:

```php
public function actions(RiskLevel $riskLevel): array
{
    return match ($riskLevel) {
        RiskLevel::Medium => [NotifyUserAction::new()],
        RiskLevel::High => [NotifyUserAction::new(), LogToSecurityChannelAction::new()],
        default => [],
    };
}
```

Actions are resolved from the container by `new()`, so you can inject dependencies in the constructor. Add your own fluent methods to configure them, the way `BlockLoginAction` has `message()`.

## Asking for a second factor

Actions run during the login request, after Laravel has logged the user in. That means an action can still change what happens next. Here's an action that flags the session, so a middleware in your app can send the user to a verification page before they continue:

```php
class RequireVerificationAction extends Action
{
    public function execute(LoginAttempt $attempt): void
    {
        session()->put('login_needs_verification', $attempt->id);
    }
}
```

The middleware, the verification page and the email with the code are up to your app.

## Actions or events?

Actions belong to a preset and depend on the risk level, so they're the right place for the response to a suspicious login. If you want to react to every login, or to something that happens regardless of the preset, [listen for an event](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events) instead.
