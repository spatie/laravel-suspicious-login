---
title: IP reputation
weight: 11
---

`Spatie\SuspiciousLogin\Checks\IpReputationCheck` flags logins from IP addresses with a bad reputation: Tor exit nodes, and addresses that other sites reported for abuse.

This check needs an [IP reputation driver](/docs/laravel-suspicious-login/v1/advanced-usage/ip-reputation-drivers). With the default `null` driver, it never flags anything.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\IpReputationCheck;

IpReputationCheck::new()->score(50),
```

The default score is 50.

### Customizing the check

The `abuseipdb` driver returns an abuse score from 0 to 100. By default, the check flags IP addresses with an abuse score of 50 or more:

```php
IpReputationCheck::new()->minimumAbuseScore(75),
```

Some people use Tor to protect their privacy. If your app should welcome them, stop flagging Tor exit nodes:

```php
IpReputationCheck::new()->allowTor(),
```
