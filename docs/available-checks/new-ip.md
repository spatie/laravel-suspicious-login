---
title: New IP address
weight: 3
---

`Spatie\SuspiciousLogin\Checks\NewIpCheck` flags a login from an IP address the user has never logged in from before.

On its own, a new IP address means little. Mobile networks, coffee shop wifi and home routers hand out new addresses all the time. That's why its default score is low: it only matters when it adds up with other signals, such as a new device.

Only earlier logins that were successful and not blocked count. The first login of a user is never flagged.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\NewIpCheck;

NewIpCheck::new()->score(10),
```

The default score is 10.
