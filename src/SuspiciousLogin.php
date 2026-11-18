<?php

namespace Spatie\SuspiciousLogin;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationManager;
use Spatie\SuspiciousLogin\Drivers\Geolocation\Location;
use Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputation;
use Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputationManager;
use Spatie\SuspiciousLogin\Events\LoginAttemptRecordedEvent;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginDetectedEvent;
use Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\Preset;

class SuspiciousLogin
{
    /** @var ?Closure(Request, ?Authenticatable): array<string, mixed> */
    protected ?Closure $propertiesResolver = null;

    public function __construct(
        protected GeolocationManager $geolocation,
        protected IpReputationManager $ipReputation,
        protected DeviceFingerprinter $deviceFingerprinter,
    ) {}

    /**
     * Adds the properties the callback returns to every recorded login attempt,
     * before the checks run.
     *
     * @param  Closure(Request, ?Authenticatable): array<string, mixed>  $callback
     */
    public function addPropertiesUsing(Closure $callback): void
    {
        $this->propertiesResolver = $callback;
    }

    public function preset(): Preset
    {
        return app(config('suspicious-login.preset'));
    }

    /**
     * Records and assesses a successful login, then runs the actions
     * of the preset for its risk level.
     */
    /** @param  array<string, mixed>  $properties */
    public function recordSuccessfulLogin(Authenticatable $user, ?string $guard = null, ?Request $request = null, array $properties = []): LoginAttempt
    {
        $request ??= request();

        $attempt = $this->newAttempt($request, $user, $guard, successful: true, properties: $properties);

        if ($location = $this->locate((string) $request->ip())) {
            $attempt->fill([
                'country_code' => $location->countryCode,
                'city' => $location->city,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
            ]);
        }

        $this->assess($attempt)->save();

        event(new LoginAttemptRecordedEvent($attempt));

        if (! $attempt->isSuspicious()) {
            return $attempt;
        }

        event(new SuspiciousLoginDetectedEvent($attempt));

        foreach ($this->preset()->actions($attempt->risk_level) as $action) {
            $action->execute($attempt);
        }

        return $attempt;
    }

    /** @param  array<string, mixed>  $properties */
    public function recordFailedLogin(?Authenticatable $user, ?string $identifier = null, ?string $guard = null, ?Request $request = null, array $properties = []): LoginAttempt
    {
        $attempt = $this->newAttempt($request ?? request(), $user, $guard, successful: false, properties: $properties);

        $attempt->identifier = $identifier;

        $attempt->save();

        event(new LoginAttemptRecordedEvent($attempt));

        return $attempt;
    }

    /**
     * Runs the checks of the preset and stores the risk score, risk level and reasons on the attempt, without saving it.
     */
    public function assess(LoginAttempt $attempt): LoginAttempt
    {
        $preset = $this->preset();

        $flaggedResults = collect($preset->checks())
            ->map(fn (Check $check) => $check->evaluate($attempt))
            ->filter(fn (CheckResult $result) => $result->suspicious)
            ->values();

        $score = min($flaggedResults->sum('score'), 100);

        $attempt->risk_score = $score;
        $attempt->risk_level = $preset->riskLevel($score);
        $attempt->reasons = $flaggedResults
            ->map(fn (CheckResult $result) => [
                'check' => $result->check,
                'score' => $result->score,
                'reason' => $result->reason,
            ])
            ->all();

        return $attempt;
    }

    public function locate(string $ip): ?Location
    {
        if (! $this->isPublicIp($ip)) {
            return null;
        }

        return Cache::remember(
            "suspicious-login:location:{$ip}",
            config('suspicious-login.geolocation.cache_ttl'),
            fn () => $this->geolocation->driver()->locate($ip),
        );
    }

    public function ipReputation(string $ip): IpReputation
    {
        if (! $this->isPublicIp($ip)) {
            return new IpReputation;
        }

        return Cache::remember(
            "suspicious-login:ip-reputation:{$ip}",
            config('suspicious-login.ip_reputation.cache_ttl'),
            fn () => $this->ipReputation->driver()->check($ip),
        );
    }

    /** @param  array<string, mixed>  $properties */
    protected function newAttempt(Request $request, ?Authenticatable $user, ?string $guard, bool $successful, array $properties): LoginAttempt
    {
        $modelClass = config('suspicious-login.model');

        $properties = [
            ...($this->propertiesResolver ? ($this->propertiesResolver)($request, $user) : []),
            ...$properties,
        ];

        /** @var LoginAttempt $attempt */
        $attempt = new $modelClass([
            'guard' => $guard,
            'successful' => $successful,
            'blocked' => false,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_fingerprint' => $this->deviceFingerprinter->fingerprint($request),
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);

        if ($user instanceof Model) {
            $attempt->authenticatable()->associate($user);
        }

        return $attempt;
    }

    protected function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
