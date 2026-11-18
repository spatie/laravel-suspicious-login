---
title: Multiple countries
weight: 12
---

`Spatie\SuspiciousLogin\Checks\MultipleCountriesCheck` flags a login when the same user also logged in from another country within the last hour. Both countries can be familiar: unlike [new country](new-country), this check detects a change of country over a short period, not a country the user has never visited.

The check needs a [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers), but only uses country codes, not coordinates. It passes when the current country is unknown. Earlier failed or blocked logins, logins without a country, and logins from other accounts do not count. The time window ends at the current login's timestamp and includes its lower boundary.

## Usage

Add it to your [preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets):

```php
use Spatie\SuspiciousLogin\Checks\MultipleCountriesCheck;

MultipleCountriesCheck::new()->score(40),
```

The defaults are 60 minutes and a score of 40. You can change both:

```php
MultipleCountriesCheck::new()
    ->withinMinutes(30)
    ->score(50),
```

The built-in presets do not enable this check. It can flag legitimate travel across nearby borders or a change of VPN location. Consider its weight alongside [impossible travel](impossible-travel) and [new country](new-country), since the same login can trigger all three checks.
