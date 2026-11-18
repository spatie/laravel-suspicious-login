<?php

namespace Spatie\SuspiciousLogin\Notifications;

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

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $attempt = $this->loginAttempt;

        $location = $attempt->location();

        $place = $location
            ? collect([$location->city, $location->countryCode])->filter()->implode(', ')
            : __('Unknown');

        return (new MailMessage)
            ->subject(__('Suspicious login to your :app account', ['app' => config('app.name')]))
            ->markdown('suspicious-login::mail.suspicious-login', [
                'loginAttempt' => $attempt,
                'place' => $place,
            ]);
    }
}
