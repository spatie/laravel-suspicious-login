<?php

namespace Spatie\SuspiciousLogin\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

/**
 * Logs the user out again and fails the login with a validation error.
 * This ends the request, so list it after the other actions.
 */
class BlockLoginAction extends Action
{
    protected string $errorKey = 'email';

    protected string $message = 'We blocked this login because it looked suspicious. Please contact support if this was you.';

    public function errorKey(string $errorKey): static
    {
        $this->errorKey = $errorKey;

        return $this;
    }

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function execute(LoginAttempt $attempt): void
    {
        $attempt->update(['blocked' => true]);

        event(new SuspiciousLoginBlockedEvent($attempt));

        Auth::guard($attempt->guard)->logout();

        $this->respond($attempt);
    }

    protected function respond(LoginAttempt $attempt): never
    {
        throw ValidationException::withMessages([
            $this->errorKey => __($this->message),
        ]);
    }
}
