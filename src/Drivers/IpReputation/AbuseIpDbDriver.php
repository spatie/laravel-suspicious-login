<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\SuspiciousLogin\Exceptions\InvalidDriverConfig;

class AbuseIpDbDriver implements IpReputationDriver
{
    public function __construct(
        protected ?string $key,
    ) {
        if (! $this->key) {
            throw InvalidDriverConfig::missingAbuseIpDbKey();
        }
    }

    public function check(string $ip): IpReputation
    {
        try {
            $response = Http::timeout(2)
                ->withHeaders(['Key' => $this->key])
                ->acceptJson()
                ->get('https://api.abuseipdb.com/api/v2/check', [
                    'ipAddress' => $ip,
                    'maxAgeInDays' => 90,
                ]);
        } catch (ConnectionException) {
            return new IpReputation;
        }

        if (! $response->successful()) {
            return new IpReputation;
        }

        return new IpReputation(
            isTor: (bool) $response->json('data.isTor'),
            abuseScore: $response->json('data.abuseConfidenceScore'),
        );
    }
}
