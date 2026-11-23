<?php

use Illuminate\Support\Facades\Schema;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

it('adds history indexes to existing installations without changing stored attempts', function () {
    Schema::drop('login_attempts');
    (include __DIR__.'/../database/migrations/create_login_attempts_table.php.stub')->up();

    $attempt = LoginAttempt::create(['successful' => false, 'identifier' => 'existing@example.test']);
    $attributes = $attempt->fresh()->getAttributes();

    (include __DIR__.'/../database/migrations/add_history_indexes_to_login_attempts_table.php.stub')->up();

    expect($attempt->fresh()->getAttributes())->toBe($attributes)
        ->and(Schema::hasIndex('login_attempts', ['created_at']))->toBeTrue()
        ->and(Schema::hasIndex('login_attempts', ['authenticatable_type', 'authenticatable_id', 'successful', 'created_at']))->toBeTrue();
});
