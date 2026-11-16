<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

interface IpReputationDriver
{
    public function check(string $ip): IpReputation;
}
