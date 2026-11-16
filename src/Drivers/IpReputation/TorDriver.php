<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TorDriver implements IpReputationDriver
{
    public function __construct(
        protected string $exitNodeListUrl,
    ) {}

    public function check(string $ip): IpReputation
    {
        return new IpReputation(
            isTor: isset($this->exitNodes()[$ip]),
        );
    }

    /** @return array<string, int> */
    protected function exitNodes(): array
    {
        return Cache::remember('suspicious-login:tor-exit-nodes', now()->addHour(), function () {
            try {
                $response = Http::timeout(5)->get($this->exitNodeListUrl);
            } catch (ConnectionException) {
                return [];
            }

            if (! $response->successful()) {
                return [];
            }

            $exitNodes = preg_split('/\R/', trim($response->body()));

            return array_flip(array_filter($exitNodes));
        });
    }
}
