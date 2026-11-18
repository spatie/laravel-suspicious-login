---
title: Adding custom properties
weight: 5
---

Your app probably knows things about a login that the package doesn't: which tenant the user logged in to, which version of your mobile app they used, or whether the request came through your API. You can store those with every login attempt as custom properties.

Register a callback in the `boot` method of a service provider. It receives the request and the user, and returns the properties as an array:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;

SuspiciousLogin::addPropertiesUsing(fn (Request $request, ?Authenticatable $user) => [
    'tenant_id' => $request->route('tenant'),
    'app_version' => $request->header('X-App-Version'),
]);
```

The callback runs for every login attempt, successful or failed. For a failed login on an account that doesn't exist, `$user` is `null`.

Properties are stored as JSON in the `properties` column of the login attempt. Read them with `getProperty`, which also understands dot notation for nested values:

```php
$attempt->getProperty('tenant_id'); // 5
$attempt->getProperty('device.model'); // 'iPhone'
$attempt->getProperty('missing', 'default'); // 'default'

$attempt->properties; // all properties as an array, or null when there are none
```

Because they're plain JSON, you can query them too:

```php
LoginAttempt::query()->where('properties->tenant_id', 5)->get();
```

## Using properties in a check

The callback runs while the login attempt is being built, before any check runs. That means a [custom check](/docs/laravel-suspicious-login/v1/advanced-usage/creating-custom-checks) can use your properties. Here's one that flags a login to a tenant the user doesn't belong to:

```php
namespace App\SuspiciousLogin\Checks;

use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Models\LoginAttempt;

class ForeignTenantCheck extends Check
{
    protected int $score = 50;

    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        $tenantId = $attempt->getProperty('tenant_id');

        if (! $tenantId) {
            return $this->pass();
        }

        if ($attempt->authenticatable?->tenants()->whereKey($tenantId)->exists()) {
            return $this->pass();
        }

        return $this->flag('Login to a tenant the user does not belong to');
    }
}
```

## Passing properties when recording manually

When you [record logins yourself](/docs/laravel-suspicious-login/v1/basic-usage/recording-logins-manually), pass properties for that one login with the `properties:` argument:

```php
SuspiciousLogin::recordSuccessfulLogin($user, guard: 'api', properties: ['token_name' => $token->name]);
```

These are merged with the properties of your callback. When both set the same key, the value you pass wins.

## Keep personal data out

Properties end up in your database for as long as you keep login attempts. Only store what you need to detect suspicious logins or to investigate one later, and never store passwords, tokens or other secrets.
