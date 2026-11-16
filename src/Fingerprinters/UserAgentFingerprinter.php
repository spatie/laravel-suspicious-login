<?php

namespace Spatie\SuspiciousLogin\Fingerprinters;

use Illuminate\Http\Request;

class UserAgentFingerprinter implements DeviceFingerprinter
{
    public function fingerprint(Request $request): string
    {
        $userAgentWithoutVersions = preg_replace('/\d+([._]\d+)+/', '', (string) $request->userAgent());

        return hash('sha256', $userAgentWithoutVersions);
    }
}
