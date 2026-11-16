---
title: Credential stuffing
weight: 8
---

In a credential stuffing attack, someone takes a list of email addresses and passwords leaked by another site, and tries them one after another on yours. Most of them fail. The few that work belong to people who reused their password.

`Spatie\SuspiciousLogin\Checks\CredentialStuffingCheck` recognizes that pattern. It flags a successful login from an IP address that recently failed to log in to many different accounts. Failed attempts on email addresses that don't exist count too.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\CredentialStuffingCheck;

CredentialStuffingCheck::new()->score(40),
```

The default score is 40.

### Customizing the threshold

By default, the check flags a login when the IP address failed on 5 or more different accounts in the last 60 minutes. Many failures on the same account don't count, that's what the [failed attempts check](failed-attempts) is for.

```php
CredentialStuffingCheck::new()
    ->threshold(10)
    ->withinMinutes(30),
```

If many of your users share one IP address, for example because they all work in the same office, raise the threshold.
