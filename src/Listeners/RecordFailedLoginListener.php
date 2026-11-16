<?php

namespace Spatie\SuspiciousLogin\Listeners;

use Illuminate\Auth\Events\Failed;
use Spatie\SuspiciousLogin\SuspiciousLogin;

class RecordFailedLoginListener
{
    public function __construct(
        protected SuspiciousLogin $suspiciousLogin,
    ) {}

    public function handle(Failed $event): void
    {
        if (! config('suspicious-login.enabled')) {
            return;
        }

        $identifier = collect($event->credentials)
            ->except('password')
            ->first(fn (mixed $value) => is_string($value));

        $this->suspiciousLogin->recordFailedLogin($event->user, $identifier, $event->guard);
    }
}
