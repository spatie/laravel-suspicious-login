<?php

namespace Spatie\SuspiciousLogin\Actions;

use Illuminate\Support\Facades\Mail;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class NotifyUserAction extends Action
{
    public function execute(LoginAttempt $attempt): void
    {
        $email = $attempt->authenticatable?->getAttribute('email');

        if (! $email) {
            return;
        }

        $mailableClass = config('suspicious-login.mailable');

        Mail::to($email)->send(new $mailableClass($attempt));
    }
}
