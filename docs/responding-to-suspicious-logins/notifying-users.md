---
title: Notifying users
weight: 1
---

`Spatie\SuspiciousLogin\Actions\NotifyUserAction` emails the user about a suspicious login. `DefaultPreset` notifies on medium and high risk. To notify on both levels in your own preset:

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

The email lists the time, IP address, location and device of the login, together with the reasons it looked suspicious. The action sends a regular Laravel mailable to the user's `email` address. The `Notifiable` trait is not required. Users without an email address, and attempts whose user no longer exists, are skipped.

The default `Spatie\SuspiciousLogin\Mail\SuspiciousLoginMail` implements `ShouldQueue`, so make sure mail is configured and a queue worker is running. With the `sync` queue connection, it's sent right away. Actions still run in preset order; sending an email does not mean the login was blocked.

## Customizing the email view

To change the email's content or layout, publish its Markdown view:

```bash
php artisan vendor:publish --tag="suspicious-login-views"
```

Edit `resources/views/vendor/suspicious-login/mail/suspicious-login.blade.php`. Laravel uses your published view instead of the package's view. You don't need to create a mailable or change the config.

The view receives:

- `$loginAttempt`: the `LoginAttempt` model, including the IP address, user agent, location, risk score and reasons.
- `$place`: the city and country joined into a string, or the translated `Unknown` when no location is available.

The template uses Laravel's `<x-mail::message>` component and translation strings.

## Customizing the subject

Publishing the view does not change the email's subject. To change only the subject, create `app/Mail/SuspiciousLoginMail.php`:

```php
namespace App\Mail;

use Spatie\SuspiciousLogin\Mail\SuspiciousLoginMail as BaseMail;

class SuspiciousLoginMail extends BaseMail
{
    public function build(): static
    {
        return parent::build()->subject('Was this you?');
    }
}
```

Select your mailable in `config/suspicious-login.php`:

```php
'mailable' => App\Mail\SuspiciousLoginMail::class,
```

This keeps the default content, published view and queue behavior.

## Using your own mailable

You can also use any Laravel mailable that accepts a `LoginAttempt` in its constructor. For example, create `app/Mail/LoginAlertMail.php`:

```php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class LoginAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public LoginAttempt $loginAttempt,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Was this you?')
            ->markdown('mail.login-alert');
    }
}
```

Create its view at `resources/views/mail/login-alert.blade.php`. Public properties on the mailable are available in the view:

```blade
<x-mail::message>
# Was this you?

Someone logged in to your account from {{ $loginAttempt->ip_address }}.

If this wasn't you, change your password right away.
</x-mail::message>
```

Then register it in `config/suspicious-login.php`:

```php
'mailable' => App\Mail\LoginAlertMail::class,
```

The action uses `Mail::send()`, which Laravel queues when the mailable implements `ShouldQueue`. Omit that interface for synchronous delivery. Configure queue names, connections and retries on your mailable rather than in the package config.

## Using Laravel notifications instead

To use your application's notification, create a custom action. For example, if `App\Notifications\LoginAlertNotification` accepts a `LoginAttempt` in its constructor, create `app/SuspiciousLogin/Actions/SendLoginNotificationAction.php`:

```php
namespace App\SuspiciousLogin\Actions;

use App\Notifications\LoginAlertNotification;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class SendLoginNotificationAction extends Action
{
    public function execute(LoginAttempt $attempt): void
    {
        $attempt->authenticatable?->notify(new LoginAlertNotification($attempt));
    }
}
```

Use that action instead of `NotifyUserAction` in your preset:

```php
use App\SuspiciousLogin\Actions\SendLoginNotificationAction;
use Spatie\SuspiciousLogin\Actions\Action;

public function onMediumRisk(): Action|array
{
    return SendLoginNotificationAction::new();
}

public function onHighRisk(): Action|array
{
    return SendLoginNotificationAction::new();
}
```

Your user model needs Laravel's `Notifiable` trait for this custom action. Define the channels, routing and queue behavior on your notification. For Slack or SMS, install and configure the channel you choose. The package's `mailable` config only affects `NotifyUserAction`, not your custom notification action.
