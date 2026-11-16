---
title: Using events
weight: 4
---

The package fires events at the key moments of a login. Listen for them to build a dashboard, forward logins to your logging or security tools, or alert an admin, without touching your preset.

Every event has one public property, `loginAttempt`, that holds the `Spatie\SuspiciousLogin\Models\LoginAttempt`.

### LoginAttemptRecordedEvent

`Spatie\SuspiciousLogin\Events\LoginAttemptRecordedEvent` is fired for every login attempt, successful or failed, right after it is saved.

For a successful login, it's fired after the login has been scored, so `risk_score`, `risk_level` and `reasons` are filled in, but before the preset's actions run. For a failed login, those attributes are `null`.

This is the event to use with the `MonitorOnly` preset, because it also tells you about low risk logins.

### SuspiciousLoginDetectedEvent

`Spatie\SuspiciousLogin\Events\SuspiciousLoginDetectedEvent` is fired when a successful login is medium or high risk. It's fired right before the preset's actions run, and it's fired with every preset, `MonitorOnly` included.

### SuspiciousLoginBlockedEvent

`Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent` is fired by [`BlockLoginAction`](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/blocking-logins), after it has marked the attempt as blocked and before it logs the user out.

## Sample usage

Here's a listener that tells your admins in Slack when a login is blocked:

```php
namespace App\Listeners;

use App\Notifications\LoginBlockedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent;

class NotifyAdminsOfBlockedLogin
{
    public function handle(SuspiciousLoginBlockedEvent $event): void
    {
        Notification::route('slack', config('services.slack.security_channel'))
            ->notify(new LoginBlockedNotification($event->loginAttempt));
    }
}
```

Laravel discovers listeners in `app/Listeners` automatically. If you've turned event discovery off, register the listener in a service provider:

```php
use App\Listeners\NotifyAdminsOfBlockedLogin;
use Illuminate\Support\Facades\Event;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent;

Event::listen(SuspiciousLoginBlockedEvent::class, NotifyAdminsOfBlockedLogin::class);
```

## Listeners run during the login

The events are fired during the login request. A slow listener makes logging in slow, so let listeners that call external services implement `ShouldQueue`.

A listener that isn't queued can still affect the login. Throwing an exception from a `SuspiciousLoginDetectedEvent` listener stops the login request, just like `BlockLoginAction` does. For that kind of response, a [custom action](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/writing-your-own-actions) is usually clearer.
