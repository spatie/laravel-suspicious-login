---
title: Overview
weight: 1
---

A check looks at one aspect of a login and decides whether it is suspicious. When it is, the check adds its score to the login's risk score and gives a reason, which ends up in the `reasons` of the login attempt and in the email to the user.

These are the checks that ship with the package:

| Check | Flags a login when | Default score |
| --- | --- | --- |
| [New device](new-device) | the device was never used in one of the user's logins | 25 |
| [New IP address](new-ip) | the IP address was never used in one of the user's logins | 10 |
| [New country](new-country) | the user never logged in from this country | 30 |
| [Impossible travel](impossible-travel) | the user would have had to travel faster than a plane since the previous login | 60 |
| [Allowed and blocked countries](country) | the country is blocked, or isn't on your allow list | 50 |
| [Failed attempts](failed-attempts) | the account had many failed logins recently | 30 |
| [Credential stuffing](credential-stuffing) | the IP address recently failed to log in to many different accounts | 40 |
| [Unusual time](unusual-time) | the user never logs in around this hour | 15 |
| [Suspicious user agent](suspicious-user-agent) | there is no user agent, or it belongs to a script or headless browser | 20 |
| [IP reputation](ip-reputation) | the IP address is a Tor exit node or reported for abuse | 50 |

Every check is created with `new()` and has a `score()` method to change how many points it adds:

```php
NewDeviceCheck::new()->score(40),
```

Most checks have a few more options, which you'll find on their own page. You use checks inside a [preset](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets).

Need something else? You can [create your own check](/docs/laravel-suspicious-login/v1/advanced-usage/creating-custom-checks) in a few lines.
