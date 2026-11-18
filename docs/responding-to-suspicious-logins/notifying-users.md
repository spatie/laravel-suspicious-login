---
title: Notifying users
weight: 1
---

The person who knows best whether a login was them is the user. `Spatie\SuspiciousLogin\Actions\NotifyUserAction` sends them an email about the suspicious login, so they can change their password if it wasn't them. The `Lenient`, `Balanced` and `Strict` presets all use it.

```php
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;

public function onMediumRisk(): Action|array
{
    return NotifyUserAction::new();
}

public function onHighRisk(): Action|array
{
    return NotifyUserAction::new();
}
```

The email lists the time, IP address, location and device of the login, together with the reasons it looked suspicious. It's a regular Laravel notification, so your user model needs the `Notifiable` trait. Users without a `notify()` method, and attempts whose user no longer exists, are skipped.

The notification is queued, so make sure a queue worker is running. With the `sync` queue connection, it's sent right away.

## Customizing the email view

To change the email's content or layout, publish its Markdown view:

```bash
php artisan vendor:publish --tag="suspicious-login-views"
```

Edit `resources/views/vendor/suspicious-login/mail/suspicious-login.blade.php`. Laravel uses your published view instead of the package's view. You don't need to create a notification or change the config.

The view receives:

- `$loginAttempt`: the `LoginAttempt` model, including the IP address, user agent, location, risk score and reasons.
- `$place`: the city and country joined into a string, or the translated `Unknown` when no location is available.

The template uses Laravel's `<x-mail::message>` component and translation strings. The notification still sets the email's subject. To change the subject, override `toMail()` in your own notification and return `parent::toMail($notifiable)->subject('Was this you?')`.

## Using your own notification

For more control over the email, or to notify through Slack, SMS or any other channel, create your own notification. It receives the `LoginAttempt` in its constructor:

```php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class SuspiciousLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LoginAttempt $loginAttempt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Was this you?')
            ->line("Someone logged in to your account from {$this->loginAttempt->ip_address}.")
            ->action('Review your recent logins', route('account.logins'));
    }
}
```

Then register it in `config/suspicious-login.php`:

```php
'notification' => App\Notifications\SuspiciousLoginNotification::class,
```

You can also extend `Spatie\SuspiciousLogin\Notifications\SuspiciousLoginNotification` and only override the methods you want to change.
