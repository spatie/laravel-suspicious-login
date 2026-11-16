<?php

use Illuminate\Support\Facades\Notification;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Enums\RiskLevel;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\Balanced;
use Spatie\SuspiciousLogin\Presets\Preset;

it('maps scores to risk levels at the thresholds of the preset', function (int $score, RiskLevel $expectedLevel) {
    expect((new Balanced)->riskLevel($score))->toBe($expectedLevel);
})->with([
    [0, RiskLevel::Low],
    [39, RiskLevel::Low],
    [40, RiskLevel::Medium],
    [69, RiskLevel::Medium],
    [70, RiskLevel::High],
]);

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

    public function actions(RiskLevel $riskLevel): array
    {
        return $riskLevel === RiskLevel::High ? [new RecordAction] : [];
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
