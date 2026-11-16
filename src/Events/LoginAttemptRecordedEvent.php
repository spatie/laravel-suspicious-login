<?php

namespace Spatie\SuspiciousLogin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class LoginAttemptRecordedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public LoginAttempt $loginAttempt,
    ) {}
}
