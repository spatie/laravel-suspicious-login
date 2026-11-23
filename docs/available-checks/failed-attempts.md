---
title: Failed attempts
weight: 7
---

`Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck` flags a successful login when the same account had many failed logins shortly before it. That's what a successful password guessing attack looks like.

Only failed attempts on this user's account count. Laravel only knows which account a failed login was for when the account exists, so attempts with an unknown email address don't count here. They do count for [credential stuffing](credential-stuffing).

## Usage

```php
use Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck;

FailedAttemptsCheck::new()->score(30),
```

The default score is 30.

### Customizing the threshold

By default, the check flags a login after 5 or more failed attempts in the last 15 minutes. You can change both:

```php
FailedAttemptsCheck::new()
    ->threshold(10)
    ->withinMinutes(60),
```

### Stopping at the threshold

By default, the check counts every matching failure and reports an exact total. For busy accounts, you can opt into counting only enough failures to decide whether to flag the login:

```php
FailedAttemptsCheck::new()
    ->threshold(5)
    ->limitToThreshold(),
```

The score, threshold, and time window stay the same. The reason says "at least 5 failed login attempts" instead of claiming an exact total. The database counts a limited subquery; putting a limit on an aggregate count itself would not limit the rows counted. Use `limitToThreshold(false)` to restore exact counting. This option is disabled by default, including in `DefaultPreset`.
