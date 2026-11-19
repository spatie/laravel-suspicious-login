<?php

namespace Spatie\SuspiciousLogin\Fingerprinters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class CookieFingerprinter implements DeviceFingerprinter
{
    public function fingerprint(Request $request): string
    {
        $cookieName = 'suspicious_login_device';
        $deviceId = $request->cookie($cookieName);

        if (! Str::isUuid($deviceId)) {
            $deviceId = (string) Str::uuid();
            $request->cookies->set($cookieName, $deviceId);
        }

        Cookie::queue(Cookie::make(
            $cookieName,
            $deviceId,
            60 * 24 * 365,
            secure: $request->isSecure() || config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return hash('sha256', $deviceId);
    }
}
