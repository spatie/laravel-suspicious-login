<?php

namespace Spatie\SuspiciousLogin\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Spatie\SuspiciousLogin\Drivers\Geolocation\Location;
use Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputation;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\Preset;
use Spatie\SuspiciousLogin\SuspiciousLogin as SuspiciousLoginService;

/**
 * @method static Preset preset()
 * @method static LoginAttempt recordSuccessfulLogin(Authenticatable $user, ?string $guard = null, ?Request $request = null)
 * @method static LoginAttempt recordFailedLogin(?Authenticatable $user, ?string $identifier = null, ?string $guard = null, ?Request $request = null)
 * @method static LoginAttempt assess(LoginAttempt $attempt)
 * @method static ?Location locate(string $ip)
 * @method static IpReputation ipReputation(string $ip)
 *
 * @see SuspiciousLoginService
 */
class SuspiciousLogin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SuspiciousLoginService::class;
    }
}
