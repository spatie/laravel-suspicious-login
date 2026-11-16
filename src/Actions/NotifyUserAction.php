<?php

namespace Spatie\SuspiciousLogin\Actions;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

class NotifyUserAction extends Action
{
    public function execute(LoginAttempt $attempt): void
    {
        $user = $attempt->authenticatable;

        if (! method_exists($user, 'notify')) {
            return;
        }

        $notificationClass = config('suspicious-login.notification');

        $user->notify(new $notificationClass($attempt));
    }
}
