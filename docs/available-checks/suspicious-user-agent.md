---
title: Suspicious user agent
weight: 10
---

`Spatie\SuspiciousLogin\Checks\SuspiciousUserAgentCheck` flags logins that don't come from a browser a person uses. People log in with Chrome, Safari or Firefox. Scripts log in with curl, Python's requests library or a headless browser.

The check flags a login without a user agent, and a login whose user agent contains one of these: `curl`, `wget`, `python-requests`, `python-urllib`, `go-http-client`, `java/`, `okhttp`, `libwww-perl`, `scrapy`, `headlesschrome`, `phantomjs`, `selenium`, `puppeteer` or `playwright`.

A careful attacker sends a real browser's user agent, so this check only catches the lazy ones. That's why its default score is low.

## Usage

```php
use Spatie\SuspiciousLogin\Checks\SuspiciousUserAgentCheck;

SuspiciousUserAgentCheck::new()->score(20),
```

The default score is 20.

### Changing the list

If your app has a native client built on okhttp, extend the check and override the list:

```php
use Spatie\SuspiciousLogin\Checks\SuspiciousUserAgentCheck;

class MySuspiciousUserAgentCheck extends SuspiciousUserAgentCheck
{
    protected array $patterns = ['curl', 'wget', 'python-requests', 'headlesschrome'];
}
```
