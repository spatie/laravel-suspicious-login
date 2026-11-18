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
use App\SuspiciousLogin\Actions\LogToSecurityChannelAction;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;

public function onMediumRisk(): Action|array
{
    return NotifyUserAction::new();
}

public function onHighRisk(): Action|array
{
    return [NotifyUserAction::new(), LogToSecurityChannelAction::new()];
}
```

These methods belong in your [preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets). Configure a `security` log channel in `config/logging.php`, or change the action to use an existing channel.

Actions are resolved from the container by `new()`, so you can inject dependencies in the constructor. Add your own fluent methods to configure them, the way `BlockLoginAction` has `message()`.

## Sending a webhook

To send suspicious logins to an external service, create an action that queues a webhook. Queue the delivery so a slow or unavailable endpoint does not hold up the login or prevent later actions from running:

```php
namespace App\SuspiciousLogin\Actions;

use Illuminate\Support\Facades\Http;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class SendWebhookAction extends Action
{
    public function execute(LoginAttempt $attempt): void
    {
        $payload = [
            'event' => 'suspicious_login',
            'login_attempt_id' => $attempt->getKey(),
            'risk_score' => $attempt->risk_score,
            'risk_level' => $attempt->risk_level->value,
            'reasons' => $attempt->reasons,
        ];

        dispatch(function () use ($payload): void {
            Http::timeout(5)
                ->withToken(config('services.security_webhook.token'))
                ->post(config('services.security_webhook.url'), $payload)
                ->throw();
        })->afterCommit();
    }
}
```

Configure an HTTPS endpoint and its bearer token in `config/services.php`:

```php
'security_webhook' => [
    'url' => 'https://security.example.com/webhooks/suspicious-login',
    'token' => env('SECURITY_WEBHOOK_TOKEN'),
],
```

Replace the example URL with your endpoint. Keep the token in your deployment's secrets or `.env` file, not in source control.

Then add your action to the preset:

```php
use App\SuspiciousLogin\Actions\SendWebhookAction;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;

public function onHighRisk(): Action|array
{
    return [SendWebhookAction::new(), BlockLoginAction::new()];
}
```

Use an asynchronous queue connection, such as `database` or `redis`, and run a queue worker. With `sync`, the HTTP request runs during the login instead. The HTTP client throws on unsuccessful responses so the queue worker can handle failed deliveries. Configure worker retries for your needs, and let the receiver use `login_attempt_id` to deduplicate deliveries.

The payload captures the assessment before the remaining actions run; it does not confirm whether the login was subsequently blocked. Only send data the receiving service needs. Reasons from custom checks may contain personal data.

## Asking for a second factor

Actions run during the login request, after Laravel has logged the user in. That means an action can still change what happens next. Here's an action that flags the session, so a middleware in your app can send the user to a verification page before they continue:

```php
namespace App\SuspiciousLogin\Actions;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

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
