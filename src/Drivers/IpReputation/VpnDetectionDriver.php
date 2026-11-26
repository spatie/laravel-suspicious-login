<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class VpnDetectionDriver implements IpReputationDriver
{
    public function check(string $ip): IpReputation
    {
        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->get('https://api.vpndetection.io/'.rawurlencode($ip));
        } catch (ConnectionException) {
            return new IpReputation;
        }

        if (! $response->successful()) {
            return new IpReputation;
        }

        return new IpReputation(
            isTor: (bool) $response->json('is_tor'),
            isVpn: (bool) $response->json('is_vpn'),
        );
    }
}
