---
title: How a login is scored
weight: 1
---

Every time someone tries to log in, the package stores a `LoginAttempt`. Failed attempts are only recorded, because there is nothing to protect yet. Successful logins go through a few more steps:

1. The package looks up where the IP address is, using your [geolocation driver](/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers).
2. It runs every check of the [preset](/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset). Each check compares the login with the user's earlier logins, or with what happened recently on the same IP address.
3. Each check that flags the login adds its score. The scores are added up, and the total is capped at 100.
4. The preset turns that score into a risk level: low, medium or high.
5. The attempt is saved with its score, level and reasons.
6. When the level is medium or high, the package runs the preset's actions for that level, such as notifying the user or blocking the login.

All of this happens during the login request, right after Laravel logs the user in. That's why an action can still block the login.

## Risk levels

A preset defines two thresholds. With the default `Balanced` preset, they are 40 and 70:

| Score | Risk level |
| --- | --- |
| 0 to 39 | `RiskLevel::Low` |
| 40 to 69 | `RiskLevel::Medium` |
| 70 to 100 | `RiskLevel::High` |

A low risk login is recorded and nothing else happens. Medium and high risk logins are considered suspicious.

## An example

Let's go back to Anna from the introduction. She has been logging in every morning from Antwerp on her MacBook. Tonight, someone logs in to her account from New York, using Firefox on Windows.

The `Balanced` preset runs its checks:

- `NewDeviceCheck` has never seen this device in one of Anna's logins. **+25**
- `NewIpCheck` has never seen this IP address. **+10**
- `NewCountryCheck` sees that Anna has never logged in from the United States. **+30**
- `ImpossibleTravelCheck` compares the location with her login this morning. Antwerp to New York in ten hours is possible by plane, so it passes.
- `FailedAttemptsCheck`, `CredentialStuffingCheck` and `SuspiciousUserAgentCheck` pass too.

The score is 65, which is medium risk. The `Balanced` preset notifies Anna, and the attempt is stored like this:

```php
$attempt->risk_score; // 65
$attempt->risk_level; // RiskLevel::Medium
$attempt->isSuspicious(); // true

$attempt->reasons;
// [
//     ['check' => NewDeviceCheck::class, 'score' => 25, 'reason' => 'Login from a new device'],
//     ['check' => NewIpCheck::class, 'score' => 10, 'reason' => 'Login from a new IP address (5.6.7.8)'],
//     ['check' => NewCountryCheck::class, 'score' => 30, 'reason' => 'Login from a new country (US)'],
// ]
```

## What counts as known

Checks like `NewDeviceCheck` only compare a login with earlier logins that were **successful and not blocked**. Failed attempts and blocked logins never make a device, IP address or country "known". Otherwise an attacker could fail to log in a few times from their laptop, and then log in with a device that already looks familiar.

The first login of a user is never flagged for a new device, IP address or country. There is nothing to compare it with yet.

## Logins that aren't recorded

Laravel also fires its `Login` event when it logs a user back in with a "remember me" cookie. Those aren't new logins, so the package skips them. Otherwise every visit of a remembered user would be scored.

To stop recording logins altogether, for example in a staging environment, set `SUSPICIOUS_LOGIN_ENABLED=false`.
