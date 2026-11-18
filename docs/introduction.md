---
title: Introduction
weight: 1
---

A login with the correct password can still come from an attacker. This package checks the device, IP address, location and login history for suspicious activity.

Each suspicious signal adds points to a risk score. Your preset decides which checks run and whether to notify the user, block the login or run your own actions.

For example, Anna usually logs in from Antwerp on her MacBook. Someone signs in to her account from a new device in New York. With the default `Balanced` preset and its ipinfo location lookup:

```
Login from a new device                  +25
Login from a new IP address (5.6.7.8)    +10
Login from a new country (US)            +30
                                         ----
Risk score                                65  → medium risk
```

A medium risk login emails Anna with those reasons. `Balanced` never blocks logins; `Strict` also blocks high risk session logins. You can [choose a built-in preset](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset) or [write your own](/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets).

The package listens for Laravel's login events, so it works with Breeze, Jetstream, Fortify and your own session login controllers. Checks, actions and drivers are replaceable, and [events](/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events) let you react to login attempts yourself.

Follow [getting started](/docs/laravel-suspicious-login/v1/basic-usage/getting-started) for installation, a complete preset and an example login flow.
