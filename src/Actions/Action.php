<?php

namespace Spatie\SuspiciousLogin\Actions;

use Spatie\SuspiciousLogin\Models\LoginAttempt;

abstract class Action
{
    public static function new(): static
    {
        return app(static::class);
    }

    abstract public function execute(LoginAttempt $attempt): void;
}
