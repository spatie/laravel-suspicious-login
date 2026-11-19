---
title: Recording logins manually
weight: 6
---

The package listens for Laravel's `Illuminate\Auth\Events\Login` and `Illuminate\Auth\Events\Failed` events. Laravel's session guard fires `Login` for successful calls to `Auth::attempt()` or `Auth::login()`, and `Failed` when `Auth::attempt()` rejects credentials. Starter kits like Breeze and Fortify use these events, so usually you don't need to do anything.

If your app authenticates users in a way that doesn't fire those events, for example by issuing API tokens in your own controller, you can record the logins yourself:

This example uses the default `BalancedPreset`, which notifies but never blocks. Use it after validating the request's email and password fields, and before issuing a token:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

The [per-login preset callback](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset#content-choosing-a-preset-per-login) also applies here. Returning `NonePreset::class` records the successful login without location lookups, scoring or actions.

For token authentication, record and assess the attempt before issuing the token. Use a preset whose actions support your authentication flow. `BlockLoginAction` calls `logout()` on a session guard; it cannot revoke an API token or log out a stateless guard. Write a [custom action](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/writing-your-own-actions) to mark the attempt as blocked and reject token issuance when needed. The `guard:` argument records the guard name; it does not authenticate the user.

Both recording methods work even when `suspicious-login.enabled` is `false`. That setting only disables the automatic auth-event listeners.

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

$attempt->risk_score; // the score for the supplied attributes and the user's history
$attempt->reasons; // the checks that flagged it
```

`assess` runs the checks and fills in the score, level and reasons, but doesn't save the attempt or run any actions. It only looks at the attributes you set, so fill in the fingerprint and location yourself, as above.

It selects the preset using the same callback as recording. With `NonePreset`, the score, level and reasons are set to `null`. You can pass a preset instance as the second argument to assess with that preset instead of selecting one.
