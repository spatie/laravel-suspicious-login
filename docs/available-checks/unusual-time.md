---
title: Unusual time
weight: 9
---

`Spatie\SuspiciousLogin\Checks\UnusualTimeCheck` flags a login at an hour of the day at which the user never logs in. Someone who always logs in during office hours probably isn't the one logging in at 3:00.

The check looks at the user's last 100 successful logins. It only judges once there are enough of them: with too little history, everything looks unusual.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\UnusualTimeCheck;

UnusualTimeCheck::new()->score(15),
```

The default score is 15. Time of day is a weak signal, so keep it low.

### Customizing the check

```php
UnusualTimeCheck::new()
    ->minimumLogins(20)
    ->toleranceInHours(3)
    ->timezone('Europe/Brussels'),
```

- `minimumLogins` is the number of earlier logins needed before the check judges anything. The default is 10.
- `toleranceInHours` is how far from an earlier login a login may be. The default is 2, so a user who logs in at 9:00 can also log in between 7:00 and 11:00. The check knows that 23:00 and 1:00 are two hours apart.
- `timezone` is the timezone in which hours are compared. The default is your app's timezone. If your users live in many timezones, this check works the same in every timezone, because it compares a user only with themselves.
