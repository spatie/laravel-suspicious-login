<?php

namespace Spatie\SuspiciousLogin\Drivers\IpReputation;

use Illuminate\Support\Manager;

/** @method IpReputationDriver driver(?string $driver = null) */
class IpReputationManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('suspicious-login.ip_reputation.driver', 'null');
    }

    public function createNullDriver(): NullDriver
    {
        return new NullDriver;
    }

    public function createTorDriver(): TorDriver
    {
        return new TorDriver($this->config->get('suspicious-login.ip_reputation.tor.exit_node_list_url'));
    }

    public function createAbuseipdbDriver(): AbuseIpDbDriver
    {
        return new AbuseIpDbDriver($this->config->get('suspicious-login.ip_reputation.abuseipdb.key'));
    }

    public function createVpndetectionDriver(): VpnDetectionDriver
    {
        return new VpnDetectionDriver;
    }
}
