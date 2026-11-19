<?php

namespace Spatie\SuspiciousLogin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class SuspiciousLoginMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public LoginAttempt $loginAttempt,
    ) {}

    public function build(): static
    {
        $attempt = $this->loginAttempt;

        $location = $attempt->location();

        $place = $location
            ? collect([$location->city, $location->countryCode])->filter()->implode(', ')
            : __('Unknown');

        return $this
            ->subject(__('Suspicious login to your :app account', ['app' => config('app.name')]))
            ->markdown('suspicious-login::mail.suspicious-login', [
                'loginAttempt' => $attempt,
                'place' => $place,
            ]);
    }
}
