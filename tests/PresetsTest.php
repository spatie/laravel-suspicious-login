<?php

use Illuminate\Support\Facades\Notification;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Enums\RiskLevel;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\Balanced;
use Spatie\SuspiciousLogin\Presets\Lenient;
use Spatie\SuspiciousLogin\Presets\MonitorOnly;
use Spatie\SuspiciousLogin\Presets\Preset;
use Spatie\SuspiciousLogin\Presets\Strict;

it('maps scores to risk levels at the thresholds of the preset', function (int $score, RiskLevel $expectedLevel) {
    expect((new Balanced)->riskLevel($score))->toBe($expectedLevel);
})->with([
    [0, RiskLevel::Low],
    [39, RiskLevel::Low],
    [40, RiskLevel::Medium],
    [69, RiskLevel::Medium],
    [70, RiskLevel::High],
]);

it('uses configured risk thresholds when the preset does not define them', function (string $presetClass, int $score, RiskLevel $expectedLevel) {
    config()->set('suspicious-login.risk_thresholds', ['medium' => 22, 'high' => 63]);

    expect((new $presetClass)->riskLevel($score))->toBe($expectedLevel);
})->with([Balanced::class, MonitorOnly::class])->with([
    [21, RiskLevel::Low],
    [22, RiskLevel::Medium],
    [62, RiskLevel::Medium],
    [63, RiskLevel::High],
]);

it('uses preset thresholds instead of configured defaults', function (string $presetClass, int $medium, int $high) {
    config()->set('suspicious-login.risk_thresholds', ['medium' => 22, 'high' => 63]);

    $preset = new $presetClass;

    expect($preset->riskLevel($medium - 1))->toBe(RiskLevel::Low)
        ->and($preset->riskLevel($medium))->toBe(RiskLevel::Medium)
        ->and($preset->riskLevel($high - 1))->toBe(RiskLevel::Medium)
        ->and($preset->riskLevel($high))->toBe(RiskLevel::High);
})->with([
    [Strict::class, 30, 60],
    [Lenient::class, 50, 80],
]);

it('allows a preset to override only one threshold', function () {
    config()->set('suspicious-login.risk_thresholds', ['medium' => 22, 'high' => 63]);

    $preset = new class extends Preset
    {
        protected int $highRiskThreshold = 85;

        public function checks(): array
        {
            return [];
        }
    };

    expect($preset->riskLevel(21))->toBe(RiskLevel::Low)
        ->and($preset->riskLevel(22))->toBe(RiskLevel::Medium)
        ->and($preset->riskLevel(63))->toBe(RiskLevel::Medium)
        ->and($preset->riskLevel(85))->toBe(RiskLevel::High);
});

it('allows a configured threshold of zero', function () {
    config()->set('suspicious-login.risk_thresholds', ['medium' => 0, 'high' => 63]);

    expect((new Balanced)->riskLevel(0))->toBe(RiskLevel::Medium);
});

it('uses a custom preset with custom checks and actions', function () {
    Notification::fake();

    config()->set('suspicious-login.preset', BlockLoginsFromNorthKorea::class);

    config()->set('suspicious-login.geolocation.driver', 'cloudflare');

    createUser();

    attemptLogin(headers: ['CF-IPCountry' => 'KP'])->assertOk();

    expect(latestAttempt())
        ->risk_score->toBe(100)
        ->risk_level->toBe(RiskLevel::High)
        ->and(RecordAction::$executedFor)->toBe([latestAttempt()->id]);

    attemptLogin(ip: '5.6.7.8', headers: ['CF-IPCountry' => 'BE'])->assertOk();

    expect(latestAttempt()->risk_level)->toBe(RiskLevel::Low)
        ->and(RecordAction::$executedFor)->toHaveCount(1);
});

it('extends a shipped preset with an extra check', function () {
    config()->set('suspicious-login.preset', BalancedWithAlwaysSuspiciousCheck::class);

    createUser();

    attemptLogin();

    expect(latestAttempt()->reasons)->toBe([
        ['check' => AlwaysSuspiciousCheck::class, 'score' => 45, 'reason' => 'Always suspicious'],
    ])->and(latestAttempt()->risk_level)->toBe(RiskLevel::Medium);
});

class BlockLoginsFromNorthKorea extends Preset
{
    public function checks(): array
    {
        return [
            CountryCheck::new()->block(['KP'])->score(80),
            AlwaysSuspiciousCheck::new()->score(30),
        ];
    }

    public function onHighRisk(): Action|array
    {
        return new RecordAction;
    }
}

class BalancedWithAlwaysSuspiciousCheck extends Balanced
{
    public function checks(): array
    {
        return [...parent::checks(), AlwaysSuspiciousCheck::new()->score(45)];
    }
}

class AlwaysSuspiciousCheck extends Check
{
    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        return $this->flag('Always suspicious');
    }
}

class RecordAction extends Action
{
    /** @var array<int, int> */
    public static array $executedFor = [];

    public function execute(LoginAttempt $attempt): void
    {
        static::$executedFor[] = $attempt->id;
    }
}
