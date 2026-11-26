<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

readonly class IpReputation
{
    /**
     * @param  ?int  $abuseScore  How likely the IP is abusive, from 0 to 100. Null when unknown.
     * @param  bool  $isVpn  Whether the IP is a known VPN server.
     */
    public function __construct(
        public bool $isTor = false,
        public ?int $abuseScore = null,
        public bool $isVpn = false,
    ) {}

    /**
     * Results cached by an earlier version have no VPN flag.
     *
     * @param  array{isTor?: bool, abuseScore?: ?int, isVpn?: bool}  $data
     */
    public function __unserialize(array $data): void
    {
        $this->isTor = $data['isTor'] ?? false;
        $this->abuseScore = $data['abuseScore'] ?? null;
        $this->isVpn = $data['isVpn'] ?? false;
    }
}
