---
title: Device fingerprints
weight: 4
---

To know whether a login comes from a new device, the package stores a fingerprint of the device with every login attempt. The [new device check](/docs/laravel-suspicious-login/v1/available-checks/new-device) compares fingerprints.

By default, `UserAgentFingerprinter` hashes the user agent with version numbers removed. Browser updates don't make a device new, but two browsers with the same user agent share a fingerprint. Comparisons are scoped to the same user's successful, unblocked logins.

## Using a device cookie

For web apps, `CookieFingerprinter` distinguishes browsers with identical user agents. Enable it in `config/suspicious-login.php`:

```php
'device_fingerprinter' => Spatie\SuspiciousLogin\Fingerprinters\CookieFingerprinter::class,
```

It creates a random `suspicious_login_device` cookie and stores its SHA-256 hash with each attempt. The cookie lasts 365 days, renewed on each login attempt. It is HTTP-only, uses `SameSite=Lax`, and is secure on HTTPS requests or when `session.secure` is enabled. Its path and domain follow Laravel's session cookie settings.

Your login route must use Laravel's `web` middleware group, including `EncryptCookies` and `AddQueuedCookiesToResponse`. Keep this cookie encrypted; don't add it to the encryption exceptions. Configure trusted proxies correctly when HTTPS terminates at a proxy.

Browser updates keep the same fingerprint. Clearing cookies, using private browsing or switching browsers creates a new one. This is a browser identifier, not proof of a physical device or authentication: someone who copies the cookie can reuse it. Without cookies, each request gets a new fingerprint. Keep the default fingerprinter or use your own identifier for stateless APIs.

Switching fingerprinters changes existing fingerprints, so known users can be flagged as using a new device on their next login.

## Using your own fingerprints

If your app already identifies devices, implement `Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter` with a `fingerprint(Request $request): string` method and register it in `config/suspicious-login.php`:

```php
'device_fingerprinter' => App\SuspiciousLogin\MyDeviceFingerprinter::class,
```

The fingerprinter is resolved from the container, so you can inject dependencies in its constructor.
