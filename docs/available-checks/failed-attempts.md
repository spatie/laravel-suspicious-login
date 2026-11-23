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
