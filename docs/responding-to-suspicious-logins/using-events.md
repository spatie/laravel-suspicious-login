---
title: Using events
weight: 4
---

The package fires events at the key moments of a login. Listen for them to build a dashboard, forward logins to your logging or security tools, or alert an admin, without touching your preset.

Every event has one public property, `loginAttempt`, that holds the `Spatie\SuspiciousLogin\Models\LoginAttempt`.

## LoginAttemptRecordedEvent

`Spatie\SuspiciousLogin\Events\LoginAttemptRecordedEvent` is fired for every login attempt, successful or failed, right after it is saved.

For a successful login, it's fired after the login has been scored, so `risk_score`, `risk_level` and `reasons` are filled in, but before the preset's actions run. For a failed login, those attributes are `null`.

This is the event to use with the `MonitorOnly` preset, because it also tells you about low risk logins.

## SuspiciousLoginDetectedEvent

`Spatie\SuspiciousLogin\Events\SuspiciousLoginDetectedEvent` is fired when a successful login is medium or high risk. It's fired right before the preset's actions run, and it's fired with every preset, `MonitorOnly` included.

## SuspiciousLoginBlockedEvent

`Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent` is fired by [`BlockLoginAction`](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/blocking-logins), after it has marked the attempt as blocked and before it logs the user out.

## Notifying admins of blocked logins

If your app already has a `LoginBlockedNotification` configured for Slack, create `app/Listeners/NotifyAdminsOfBlockedLogin.php`:

```php
namespace App\Listeners;

use App\Notifications\LoginBlockedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent;

class NotifyAdminsOfBlockedLogin implements ShouldQueue
{
    public function handle(SuspiciousLoginBlockedEvent $event): void
    {
        Notification::route('slack', config('services.slack.security_channel'))
            ->notify(new LoginBlockedNotification($event->loginAttempt));
    }
}
```

`LoginBlockedNotification` is an application notification, not a class supplied by this package. It should accept the attempt in its constructor. Install and configure Laravel's Slack notification channel and set `services.slack.security_channel` to the routing value that channel expects. Use an asynchronous queue connection and run a queue worker so delivery happens outside the login request.

Laravel discovers listeners in `app/Listeners` automatically. If you've turned event discovery off, register the listener in a service provider:

```php
use App\Listeners\NotifyAdminsOfBlockedLogin;
use Illuminate\Support\Facades\Event;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent;

Event::listen(SuspiciousLoginBlockedEvent::class, NotifyAdminsOfBlockedLogin::class);
```

## Listeners run during the login

The events are fired during the login request. A slow listener makes logging in slow, so let listeners that call external services implement `ShouldQueue`.

A listener that isn't queued can still affect the login. Throwing an exception stops the request, but does not log the user out or mark the attempt as blocked. To reject a session login, use [`BlockLoginAction`](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/blocking-logins). For other authentication flows, write a [custom action](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/writing-your-own-actions) that rejects the login and cleans up any authentication state it created.
