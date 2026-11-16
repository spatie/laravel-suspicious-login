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

        $message = (new MailMessage)
            ->subject(__('Suspicious login to your :app account', ['app' => config('app.name')]))
            ->line(__('We noticed a login to your account that looks unusual.'))
            ->line(__('Time: :time', ['time' => $attempt->created_at->toDayDateTimeString()]))
            ->line(__('IP address: :ip', ['ip' => $attempt->ip_address]))
            ->line(__('Location: :place', ['place' => $place]))
            ->line(__('Device: :device', ['device' => $attempt->user_agent ?: __('Unknown')]));

        foreach ($attempt->reasons ?? [] as $reason) {
            $message->line("- {$reason['reason']}");
        }

        return $message->line(__('If this was you, you can ignore this email. If it wasn\'t, change your password right away.'));
    }
}
