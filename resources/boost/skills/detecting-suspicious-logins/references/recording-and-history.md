# Recording and history

## Manual recording and assessment

Use `Spatie\SuspiciousLogin\Facades\SuspiciousLogin`. The recording methods use the current request unless a `request:` argument is supplied:

```php
$attempt = SuspiciousLogin::recordSuccessfulLogin(
    $user,
    guard: 'api',
    properties: ['token_name' => 'mobile'],
);

SuspiciousLogin::recordFailedLogin(
    $user,
    identifier: $request->input('email'),
    guard: 'api',
);
```

`recordSuccessfulLogin()` looks up location, scores, saves, dispatches events, and runs actions. For API authentication call it after validating credentials, but before issuing a token. Use a preset whose actions support that flow; `BlockLoginAction` assumes a session guard. The `guard:` argument records a name, not authentication. `$user` may be `null` for a failed login on an unknown account. Never store passwords or tokens in properties.

`'enabled' => false` only disables automatic auth-event recording; explicit facade calls still work. Do not combine manual and automatic recording for the same login.

For scoring without recording, construct a `LoginAttempt` using the configured model and associate the user:

```php
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;
use Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter;

$model = config('suspicious-login.model');
$location = SuspiciousLogin::locate($request->ip());

$attempt = new $model([
    'successful' => true,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'device_fingerprint' => app(DeviceFingerprinter::class)->fingerprint($request),
    'country_code' => $location?->countryCode,
    'city' => $location?->city,
    'latitude' => $location?->latitude,
    'longitude' => $location?->longitude,
    'created_at' => now(),
]);

$attempt->authenticatable()->associate($user);

SuspiciousLogin::assess($attempt);
```

`assess()` mutates and returns the supplied attempt, filling `risk_score`, `risk_level`, and `reasons`. It neither saves nor populates fingerprints/locations, dispatches events, or runs actions.

## Custom properties

In a service provider's `boot()` method:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;

SuspiciousLogin::addPropertiesUsing(fn (Request $request, ?Authenticatable $user) => [
    'tenant_id' => $request->route('tenant'),
    'app_version' => $request->header('X-App-Version'),
]);
```

The callback runs for successful and failed recordings, before checks; the user can be null. Explicit `properties:` values override callback values with the same key. Read nested data with `$attempt->getProperty('device.model', 'Unknown')` or get the whole nullable array from `$attempt->properties`. These are stored JSON attributes; custom checks can inspect them. Adding `tenant_id` does not automatically scope history to that tenant.

## Query login attempts

Use the model from `suspicious-login.model` for application queries, particularly when it has a custom connection, table, or scopes:

```php
$model = config('suspicious-login.model');

$recentLogins = $model::query()
    ->whereMorphedTo('authenticatable', $user)
    ->trusted()
    ->latest()
    ->limit(10)
    ->get();
```

- `authenticatable` is a polymorphic Eloquent relation. History belongs to the user model and ID, across guards.
- `successful` means credentials authenticated, even if an action then blocked the login. Use `trusted()` for successful and unblocked logins, `failed()` for failed authentication, and `since($date)` for a time window.
- `$attempt->history()` queries other attempts of that user, excluding the current record and later timestamps. Combine with `trusted()`, `failed()`, and `since()`.
- `$attempt->isSuspicious()` is true at medium/high risk; failed/unassessed attempts have no risk level.
- `$attempt->location()` returns a `Location` or null. `reasons` contains arrays with `check`, `score`, and `reason` for flagged checks.

## Retention

Schedule pruning in `routes/console.php`; it is not scheduled automatically:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [config('suspicious-login.model')],
])->daily();
```

`prune_after_days` defaults to 365. Aggressive pruning also removes the history checks rely on; a forgotten device or country can look new again.
