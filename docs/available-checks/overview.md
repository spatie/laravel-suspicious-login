---
title: Overview
weight: 1
---

A check looks at one aspect of a successful login and decides whether it is suspicious. When it is, the check adds points to the risk score and stores a reason on the login attempt. If the preset sends a notification, the default email includes those reasons.

Checks don't notify or block users themselves. Your [preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets) decides which checks run and what actions follow the combined score. See [getting started](/docs/laravel-suspicious-login/v1/basic-usage/getting-started) for a complete example.

These are the checks that ship with the package:

| Check | Flags a login when | Default score |
| --- | --- | --- |
| [New device](new-device) | the device was never used in one of the user's logins | 25 |
| [New IP address](new-ip) | the IP address was never used in one of the user's logins | 10 |
| [New country](new-country) | the user never logged in from this country | 30 |
| [Multiple countries](multiple-countries) | the user also logged in from another country within the last hour | 40 |
| [Impossible travel](impossible-travel) | the user would have had to travel faster than a plane since the previous login | 60 |
| [Allowed and blocked countries](country) | the country is blocked, or isn't on your allow list | 50 |
| [Failed attempts](failed-attempts) | the account had many failed logins recently | 30 |
| [Credential stuffing](credential-stuffing) | the IP address recently failed to log in to many different accounts | 40 |
| [Unusual time](unusual-time) | the user never logs in around this hour | 15 |
| [Suspicious user agent](suspicious-user-agent) | there is no user agent, or it belongs to a script or headless browser | 20 |
| [IP reputation](ip-reputation) | the IP address is a Tor exit node or reported for abuse | 50 |

The scores above are the checks' own defaults. Built-in presets can [assign different scores](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset#what-each-preset-contains). `CountryCheck` and `MultipleCountriesCheck` are not enabled by any built-in preset; add them to your own preset if you need them.

Every check is created with `new()` and has a `score()` method to change how many points it adds. For example, after importing `Spatie\SuspiciousLogin\Checks\NewDeviceCheck`, add this entry to the array returned by your preset's `checks()` method:

```php
NewDeviceCheck::new()->score(40),
```

Each check's page lists its other options.

For application-specific signals, [create your own check](/docs/laravel-suspicious-login/v1/advanced-usage/creating-custom-checks).
