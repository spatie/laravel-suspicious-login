---
title: New device
weight: 2
---

`Spatie\SuspiciousLogin\Checks\NewDeviceCheck` flags a login with a fingerprint the user has never logged in with before.

A device is recognized by its [device fingerprint](/docs/laravel-suspicious-login/v1/advanced-usage/device-fingerprints). By default that's the user agent with version numbers removed, so a browser update doesn't make a device new. Browsers with identical user agents share a fingerprint; use [CookieFingerprinter](/docs/laravel-suspicious-login/v1/advanced-usage/device-fingerprints#content-using-a-device-cookie) to distinguish them in web apps.

Only earlier logins that were successful and not blocked count. The first login of a user is never flagged.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;

NewDeviceCheck::new()->score(25),
```

The default score is 25.
