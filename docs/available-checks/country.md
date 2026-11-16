---
title: Allowed and blocked countries
weight: 6
---

`Spatie\SuspiciousLogin\Checks\CountryCheck` flags logins from countries you block, or from countries that aren't on your allow list. Unlike the other checks, it doesn't look at the user's history.

None of the shipped presets use this check, because which countries make sense depends on your app. Add it to [your own preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets).

This check needs a [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers). When the country of a login is unknown, the check passes.

## Usage

To flag every login from outside a list of countries, use `onlyAllow`:

```php
use Spatie\SuspiciousLogin\Checks\CountryCheck;

CountryCheck::new()->onlyAllow(['BE', 'NL', 'LU'])->score(60),
```

To flag logins from specific countries, use `block`:

```php
CountryCheck::new()->block(['KP'])->score(100),
```

Countries are ISO 3166-1 alpha-2 codes, in any case. When you use both, blocked countries are checked first.

The default score is 50.
