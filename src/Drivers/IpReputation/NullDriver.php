<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

class NullDriver implements IpReputationDriver
{
    public function check(string $ip): IpReputation
    {
        return new IpReputation;
    }
}
