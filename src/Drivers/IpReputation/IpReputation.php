<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

readonly class IpReputation
{
    /**
     * @param  ?int  $abuseScore  How likely the IP is abusive, from 0 to 100. Null when unknown.
     */
    public function __construct(
        public bool $isTor = false,
        public ?int $abuseScore = null,
    ) {}
}
