---
title: Recording logins manually
weight: 6
---

The package listens for Laravel's `Illuminate\Auth\Events\Login` and `Illuminate\Auth\Events\Failed` events. Every login that goes through `Auth::attempt()`, `Auth::login()` or a starter kit like Breeze or Fortify fires them, so usually you don't need to do anything.

If your app authenticates users in a way that doesn't fire those events, for example by issuing API tokens in your own controller, you can record the logins yourself:

```php
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;

$user = User::firstWhere('email', $request->email);

if (! $user || ! Hash::check($request->password, $user->password)) {
    SuspiciousLogin::recordFailedLogin($user, identifier: $request->email, guard: 'api');

    abort(401);
}

$attempt = SuspiciousLogin::recordSuccessfulLogin($user, guard: 'api');

if ($attempt->isSuspicious()) {
    // ...
}
```

`recordSuccessfulLogin` scores the login and runs the preset's actions, exactly like a login through Laravel's events. Both methods take the current request by default. Pass a `request:` argument to record a login for a different request, and a `properties:` argument to store [custom properties](/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties) with it.

Don't record a login manually when Laravel already fires its events for it, or it will be stored twice.

## Scoring without saving

To find out how risky a login would be without recording it, create an unsaved attempt and pass it to `assess`:

```php
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;
use Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

$location = SuspiciousLogin::locate($request->ip());

$attempt = new LoginAttempt([
    'successful' => true,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'device_fingerprint' => app(DeviceFingerprinter::class)->fingerprint($request),
    'country_code' => $location?->countryCode,
    'latitude' => $location?->latitude,
    'longitude' => $location?->longitude,
    'created_at' => now(),
]);

$attempt->authenticatable()->associate($user);

SuspiciousLogin::assess($attempt);

$attempt->risk_score; // 35
$attempt->reasons; // the checks that flagged it
```

`assess` runs the checks and fills in the score, level and reasons, but doesn't save the attempt or run any actions. It only looks at the attributes you set, so fill in the fingerprint and location yourself, as above.
