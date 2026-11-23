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

### Stopping at the threshold

By default, the check counts all distinct non-null identifiers and reports an exact total. You can opt into stopping once enough distinct identifiers have been found:

```php
CredentialStuffingCheck::new()
    ->threshold(5)
    ->limitToThreshold(),
```

The reason then says "at least 5 accounts". Repeated failures for the same identifier still count once, and null identifiers do not count. The score and window are unchanged. Use `limitToThreshold(false)` to restore exact counting. This option is disabled by default, including in `DefaultPreset`.

This limits the distinct identifiers counted, not necessarily the records examined. If an IP has a million failures for one identifier, the database may still inspect all of them to discover that it has not reached the threshold. This check remains independent of `history_lookback_days`. When evaluating an older attempt, its existing query also includes failures after that attempt's timestamp; enabling limited counting does not change that behavior.
