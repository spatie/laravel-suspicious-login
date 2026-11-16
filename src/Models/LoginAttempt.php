<?php

namespace Spatie\SuspiciousLogin\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\SuspiciousLogin\Drivers\Geolocation\Location;
use Spatie\SuspiciousLogin\Enums\RiskLevel;

/**
 * @property ?string $authenticatable_type
 * @property int|string|null $authenticatable_id
 * @property ?string $guard
 * @property ?string $identifier
 * @property bool $successful
 * @property bool $blocked
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property ?string $device_fingerprint
 * @property ?string $country_code
 * @property ?string $city
 * @property ?float $latitude
 * @property ?float $longitude
 * @property ?int $risk_score
 * @property ?RiskLevel $risk_level
 * @property ?array<int, array{check: string, score: int, reason: string}> $reasons
 * @property CarbonInterface $created_at
 */
class LoginAttempt extends Model
{
    use MassPrunable;

    protected $table = 'login_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'blocked' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'risk_score' => 'integer',
            'risk_level' => RiskLevel::class,
            'reasons' => 'array',
        ];
    }

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function location(): ?Location
    {
        if (! $this->country_code) {
            return null;
        }

        return new Location($this->country_code, $this->city, $this->latitude, $this->longitude);
    }

    public function isSuspicious(): bool
    {
        if (! $this->risk_level) {
            return false;
        }

        return $this->risk_level !== RiskLevel::Low;
    }

    /**
     * The other login attempts of the same user, excluding this one.
     *
     * @return Builder<static>
     */
    public function history(): Builder
    {
        return static::query()
            ->where('authenticatable_type', $this->authenticatable_type)
            ->where('authenticatable_id', $this->authenticatable_id)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->where('created_at', '<=', $this->created_at ?? now());
    }

    /**
     * Successful logins that weren't blocked: the devices, IPs and places the user is known to use.
     *
     * @param  Builder<static>  $query
     */
    public function scopeTrusted(Builder $query): void
    {
        $query->where('successful', true)->where('blocked', false);
    }

    /** @param  Builder<static>  $query */
    public function scopeFailed(Builder $query): void
    {
        $query->where('successful', false);
    }

    /** @param  Builder<static>  $query */
    public function scopeSince(Builder $query, CarbonInterface $since): void
    {
        $query->where('created_at', '>=', $since);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(config('suspicious-login.prune_after_days')));
    }
}
