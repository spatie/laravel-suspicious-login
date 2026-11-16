<?php

namespace Spatie\SuspiciousLogin\Fingerprinters;

use Illuminate\Http\Request;

interface DeviceFingerprinter
{
    public function fingerprint(Request $request): string;
}
