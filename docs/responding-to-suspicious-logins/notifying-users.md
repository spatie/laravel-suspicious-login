---
title: Notifying users
weight: 1
---

`Spatie\SuspiciousLogin\Actions\NotifyUserAction` emails the user about a suspicious login. `Balanced` and `Strict` notify on medium and high risk; `Lenient` only notifies on high risk. To notify on both levels in your own preset:

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

The template uses Laravel's `<x-mail::message>` component and translation strings.

## Customizing the subject

Publishing the view does not change the email's subject. To change only the subject, create `app/Notifications/SuspiciousLoginNotification.php`:

```php
namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Spatie\SuspiciousLogin\Notifications\SuspiciousLoginNotification as BaseNotification;

class SuspiciousLoginNotification extends BaseNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->subject('Was this you?');
    }
}
```

Select your notification in `config/suspicious-login.php`:

```php
'notification' => App\Notifications\SuspiciousLoginNotification::class,
```

This keeps the default content, published view and queue behavior.

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

The example's `account.logins` route must exist in your application and show only the authenticated user's activity. See [working with login attempts](/docs/laravel-suspicious-login/v1/basic-usage/working-with-login-attempts) for a user-scoped query. For Slack or SMS, install and configure the Laravel notification channel you choose; the package only supplies the mail notification.
