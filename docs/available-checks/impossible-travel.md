---
title: Impossible travel
weight: 5
---

`Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck` compares the location of a login with the location of the user's previous login. When the user would have had to travel faster than a plane to get there, at least one of the two logins wasn't them.

Logging in from Antwerp at 9:00 and from New York at 19:00 is fine: that's about 5,900 km in ten hours. Logging in from New York at 9:30 is not.

This check needs a geolocation driver that returns coordinates, such as `ipinfo`, `maxmind`, or `cloudflare` with the visitor location headers turned on. See [geolocation drivers](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers).

## Usage

```php
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;

ImpossibleTravelCheck::new()->score(60),
```

The default score is 60. That's high on purpose: impossible travel is a strong signal on its own.

### Customizing the speed

By default, anything faster than 900 km/h, the cruising speed of an airliner, is impossible. You can change that:

```php
ImpossibleTravelCheck::new()->maxSpeedInKilometersPerHour(1000),
```

### Ignoring short distances

IP geolocation is often off by tens or even hundreds of kilometers. A user whose IP address is located in Brussels one minute and in Antwerp the next hasn't traveled at all. That's why distances below 300 km are never considered impossible travel. You can change that distance:

```php
ImpossibleTravelCheck::new()->minimumDistanceInKilometers(500),
```
