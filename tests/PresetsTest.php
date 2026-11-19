<?php

use Illuminate\Support\Facades\Notification;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Enums\RiskLevel;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\DefaultPreset;
use Spatie\SuspiciousLogin\Presets\MonitorOnlyPreset;
use Spatie\SuspiciousLogin\Presets\Preset;

it('maps scores to risk levels at the thresholds of the preset', function (int $score, RiskLevel $expectedLevel) {
    expect((new DefaultPreset)->riskLevel($score))->toBe($expectedLevel);
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
})->with([DefaultPreset::class, MonitorOnlyPreset::class])->with([
    [21, RiskLevel::Low],
    [22, RiskLevel::Medium],
    [62, RiskLevel::Medium],
    [63, RiskLevel::High],
]);

it('uses preset thresholds instead of configured defaults', function () {
    config()->set('suspicious-login.risk_thresholds', ['medium' => 22, 'high' => 63]);

    $preset = new class extends DefaultPreset
    {
        protected int $mediumRiskThreshold = 30;

        protected int $highRiskThreshold = 80;
    };

    expect($preset->riskLevel(29))->toBe(RiskLevel::Low)
        ->and($preset->riskLevel(30))->toBe(RiskLevel::Medium)
        ->and($preset->riskLevel(79))->toBe(RiskLevel::Medium)
        ->and($preset->riskLevel(80))->toBe(RiskLevel::High);
});

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

    expect((new DefaultPreset)->riskLevel(0))->toBe(RiskLevel::Medium);
});

it('uses a custom preset with custom checks and actions', function () {
    Notification::fake();

    config()->set('suspicious-login.preset', BlockLoginsFromNorthKoreaPreset::class);

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
    config()->set('suspicious-login.preset', DefaultWithAlwaysSuspiciousCheckPreset::class);

    createUser();

    attemptLogin();

    expect(latestAttempt()->reasons)->toBe([
        ['check' => AlwaysSuspiciousCheck::class, 'score' => 45, 'reason' => 'Always suspicious'],
    ])->and(latestAttempt()->risk_level)->toBe(RiskLevel::Medium);
});

class BlockLoginsFromNorthKoreaPreset extends Preset
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

class DefaultWithAlwaysSuspiciousCheckPreset extends DefaultPreset
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
