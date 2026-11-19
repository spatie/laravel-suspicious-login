---
title: New device
weight: 2
---

`Spatie\SuspiciousLogin\Checks\NewDeviceCheck` flags a login with a fingerprint the user has never logged in with before.

A device is recognized by its [device fingerprint](/docs/laravel-suspicious-login/v1/advanced-usage/device-fingerprints). By default that's a hash of a browser identifier stored in an encrypted cookie, so browser updates don't make a device new and browsers with identical user agents can be distinguished. Clearing cookies or using private browsing creates a new identity.

Only earlier logins that were successful and not blocked count. The first login of a user is never flagged.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;

NewDeviceCheck::new()->score(25),
```

The default score is 25.
