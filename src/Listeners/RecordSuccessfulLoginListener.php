<?php

namespace Spatie\SuspiciousLogin\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Spatie\SuspiciousLogin\SuspiciousLogin;

class RecordSuccessfulLoginListener
{
    public function __construct(
        protected SuspiciousLogin $suspiciousLogin,
    ) {}

    public function handle(Login $event): void
    {
        if (! config('suspicious-login.enabled')) {
            return;
        }

        if ($this->isRestoredFromRememberCookie($event)) {
            return;
        }

        $this->suspiciousLogin->recordSuccessfulLogin($event->user, $event->guard);
    }

    protected function isRestoredFromRememberCookie(Login $event): bool
    {
        $guard = Auth::guard($event->guard);

        if (! $guard instanceof SessionGuard) {
            return false;
        }

        return $guard->viaRemember();
    }
}
