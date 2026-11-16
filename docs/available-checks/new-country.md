---
title: New country
weight: 4
---

`Spatie\SuspiciousLogin\Checks\NewCountryCheck` flags a login from a country the user has never logged in from before.

This check needs a [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers). When the location of a login is unknown, the check passes. Earlier logins without a known country are ignored, so turning on geolocation in an existing app doesn't flag everybody's next login.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\NewCountryCheck;

NewCountryCheck::new()->score(30),
```

The default score is 30.
