---
title: Device fingerprints
weight: 4
---

To know whether a login comes from a new device, the package stores a fingerprint of the device with every login attempt. The [new device check](/docs/laravel-suspicious-login/v1/available-checks/new-device) compares fingerprints.

By default, the fingerprint is a hash of the user agent with all version numbers removed. Chrome 141 and Chrome 142 on the same Mac give the same fingerprint, so a browser update doesn't make a device new. The downside: two people with the same browser on the same operating system share a fingerprint too.

## Using your own fingerprints

If your app already identifies devices, for example with a long-lived cookie it sets itself, you can use that instead. Implement `Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter`:

```php
namespace App\SuspiciousLogin;

use Illuminate\Http\Request;
use Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter;

class DeviceCookieFingerprinter implements DeviceFingerprinter
{
    public function fingerprint(Request $request): string
    {
        $deviceId = $request->cookie('device_id') ?? $request->userAgent();

        return hash('sha256', (string) $deviceId);
    }
}
```

And register it in `config/suspicious-login.php`:

```php
'device_fingerprinter' => App\SuspiciousLogin\DeviceCookieFingerprinter::class,
```

The fingerprinter is resolved from the container, so you can inject dependencies in its constructor.

Changing the fingerprinter changes the fingerprint of every device. Right after the switch, every user's next login comes from a "new device" once.
