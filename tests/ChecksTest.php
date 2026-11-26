<?php

use Illuminate\Support\Facades\Http;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Checks\CredentialStuffingCheck;
use Spatie\SuspiciousLogin\Checks\FailedAttemptsCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\IpReputationCheck;
use Spatie\SuspiciousLogin\Checks\MultipleCountriesCheck;
use Spatie\SuspiciousLogin\Checks\NewCountryCheck;
use Spatie\SuspiciousLogin\Checks\SuspiciousUserAgentCheck;
use Spatie\SuspiciousLogin\Checks\UnusualTimeCheck;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Tests\TestSupport\User;

beforeEach(function () {
    $this->user = createUser();

    $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
});

it('flags impossible travel', function (string $previousCoordinates, int $minutesLater, bool $expectedSuspicious) {
    [$latitude, $longitude] = explode(',', $previousCoordinates);

    storeAttempt($this->user, ['latitude' => $latitude, 'longitude' => $longitude, 'country_code' => 'XX', 'created_at' => now()]);

    $result = ImpossibleTravelCheck::new()->evaluate(
        newAttempt($this->user, ['latitude' => 51.2194, 'longitude' => 4.4025, 'country_code' => 'BE', 'created_at' => now()->addMinutes($minutesLater)]),
    );

    expect($result->suspicious)->toBe($expectedSuspicious);
})->with([
    'New York to Antwerp in an hour' => ['40.7128,-74.0060', 60, true],
    'New York to Antwerp in ten hours' => ['40.7128,-74.0060', 600, false],
    'Brussels to Antwerp in a minute, within geolocation error' => ['50.8503,4.3517', 1, false],
    'Paris to Antwerp in ten minutes' => ['48.8566,2.3522', 10, true],
]);

it('flags credential stuffing only when an ip fails against many different accounts', function () {
    foreach (range(1, 10) as $i) {
        storeAttempt(null, ['successful' => false, 'identifier' => 'same@example.com', 'ip_address' => '5.6.7.8']);
    }

    $attempt = newAttempt($this->user, ['ip_address' => '5.6.7.8']);

    expect(CredentialStuffingCheck::new()->threshold(5)->evaluate($attempt)->suspicious)->toBeFalse();

    foreach (range(1, 4) as $i) {
        storeAttempt(null, ['successful' => false, 'identifier' => "user{$i}@example.com", 'ip_address' => '5.6.7.8']);
    }

    expect(CredentialStuffingCheck::new()->threshold(5)->evaluate($attempt)->suspicious)->toBeTrue()
        ->and(CredentialStuffingCheck::new()->threshold(5)->evaluate(newAttempt($this->user, ['ip_address' => '9.9.9.9']))->suspicious)->toBeFalse();
});

it('only counts failed attempts of the same user inside the time window', function () {
    $otherUser = createUser('other@example.com');

    storeAttempt($this->user, ['successful' => false, 'created_at' => now()->subMinutes(20)]);
    storeAttempt($this->user, ['successful' => false, 'created_at' => now()->subMinutes(10)]);
    storeAttempt($this->user, ['successful' => false, 'created_at' => now()->subMinutes(5)]);
    storeAttempt($otherUser, ['successful' => false, 'created_at' => now()->subMinutes(5)]);

    $check = FailedAttemptsCheck::new()->withinMinutes(15);

    expect($check->threshold(2)->evaluate(newAttempt($this->user))->suspicious)->toBeTrue()
        ->and($check->threshold(3)->evaluate(newAttempt($this->user))->suspicious)->toBeFalse();
});

it('considers the hours around midnight close to each other', function (int $previousHour, int $hour, bool $expectedSuspicious) {
    foreach (range(1, 10) as $day) {
        storeAttempt($this->user, ['created_at' => now()->subDays($day)->setTime($previousHour, 30)]);
    }

    $result = UnusualTimeCheck::new()
        ->timezone('UTC')
        ->evaluate(newAttempt($this->user, ['created_at' => now()->setTime($hour, 0)]));

    expect($result->suspicious)->toBe($expectedSuspicious);
})->with([
    'usual 23:00, now 01:00' => [23, 1, false],
    'usual 23:00, now 02:00' => [23, 2, true],
    'usual 09:00, now 15:00' => [9, 15, true],
]);

it('does not judge the time of day without enough history', function () {
    foreach (range(1, 9) as $day) {
        storeAttempt($this->user, ['created_at' => now()->subDays($day)->setTime(9, 0)]);
    }

    expect(UnusualTimeCheck::new()->evaluate(newAttempt($this->user, ['created_at' => now()->setTime(3, 0)]))->suspicious)->toBeFalse();
});

it('only compares countries with earlier logins that have a country', function () {
    storeAttempt($this->user, ['country_code' => null]);

    expect(NewCountryCheck::new()->evaluate(newAttempt($this->user, ['country_code' => 'US']))->suspicious)->toBeFalse();

    storeAttempt($this->user, ['country_code' => 'BE']);

    expect(NewCountryCheck::new()->evaluate(newAttempt($this->user, ['country_code' => 'BE']))->suspicious)->toBeFalse()
        ->and(NewCountryCheck::new()->evaluate(newAttempt($this->user, ['country_code' => 'US']))->suspicious)->toBeTrue();
});

it('flags multiple countries within the configured time window', function (int $secondsAgo, ?int $minutes, bool $expectedSuspicious) {
    $time = now()->subDay();

    storeAttempt($this->user, ['country_code' => 'BE', 'created_at' => $time->copy()->subDays(2)]);
    storeAttempt($this->user, ['country_code' => 'NL', 'created_at' => $time->copy()->subSeconds($secondsAgo)]);

    $attempt = storeAttempt($this->user, ['country_code' => 'BE', 'created_at' => $time]);
    $check = MultipleCountriesCheck::new();

    if ($minutes !== null) {
        $check->withinMinutes($minutes);
    }

    $result = $check->evaluate($attempt);

    expect($result->suspicious)->toBe($expectedSuspicious)
        ->and($result->score)->toBe($expectedSuspicious ? 40 : 0)
        ->and($result->reason)->toBe($expectedSuspicious
            ? 'Logins from multiple countries in the last '.($minutes ?? 60).' minutes'
            : null)
        ->and(NewCountryCheck::new()->evaluate($attempt)->suspicious)->toBeFalse();
})->with([
    'within the default hour' => [1800, null, true],
    'exactly one hour ago' => [3600, null, true],
    'just outside the default hour' => [3601, null, false],
    'inside a shorter window' => [600, 15, true],
    'outside a shorter window' => [901, 15, false],
    'inside a longer window' => [5400, 120, true],
]);

it('only compares known countries in earlier successful unblocked logins of the same user', function () {
    $otherUser = createUser('other@example.com');

    storeAttempt($otherUser, ['country_code' => 'US']);
    storeAttempt($this->user, ['country_code' => 'US', 'successful' => false]);
    storeAttempt($this->user, ['country_code' => 'US', 'blocked' => true]);
    storeAttempt($this->user, ['country_code' => 'US', 'created_at' => now()->addMinute()]);
    storeAttempt($this->user, ['country_code' => null]);
    storeAttempt($this->user, ['country_code' => 'BE']);

    $attempt = newAttempt($this->user, ['country_code' => 'BE']);

    expect(MultipleCountriesCheck::new()->evaluate($attempt)->suspicious)->toBeFalse();

    storeAttempt($this->user, ['country_code' => 'NL']);

    $result = MultipleCountriesCheck::new()->score(55)->evaluate($attempt);

    expect($result->suspicious)->toBeTrue()
        ->and($result->score)->toBe(55)
        ->and(MultipleCountriesCheck::new()->evaluate(newAttempt($this->user))->suspicious)->toBeFalse();
});

it('does not flag multiple countries without earlier logins', function () {
    expect(MultipleCountriesCheck::new()->evaluate(newAttempt($this->user, ['country_code' => 'BE']))->suspicious)->toBeFalse();
});

it('can allow or block countries', function () {
    $attemptFrom = fn (string $country) => newAttempt($this->user, ['country_code' => $country]);

    expect(CountryCheck::new()->onlyAllow(['be', 'nl'])->evaluate($attemptFrom('NL'))->suspicious)->toBeFalse()
        ->and(CountryCheck::new()->onlyAllow(['be', 'nl'])->evaluate($attemptFrom('US'))->suspicious)->toBeTrue()
        ->and(CountryCheck::new()->block(['kp'])->evaluate($attemptFrom('KP'))->suspicious)->toBeTrue()
        ->and(CountryCheck::new()->block(['kp'])->evaluate($attemptFrom('US'))->suspicious)->toBeFalse();
});

it('flags clients that people do not log in with', function (?string $userAgent, bool $expectedSuspicious) {
    expect(SuspiciousUserAgentCheck::new()->evaluate(newAttempt($this->user, ['user_agent' => $userAgent]))->suspicious)
        ->toBe($expectedSuspicious);
})->with([
    'chrome' => [CHROME_ON_MAC, false],
    'curl' => ['curl/8.4.0', true],
    'headless chrome' => ['Mozilla/5.0 HeadlessChrome/141.0.0.0 Safari/537.36', true],
    'no user agent' => [null, true],
]);

it('flags tor exit nodes with the tor driver', function () {
    config()->set('suspicious-login.ip_reputation.driver', 'tor');

    Http::fake(['check.torproject.org/*' => Http::response("185.220.101.1\n185.220.101.2\n")]);

    expect(IpReputationCheck::new()->evaluate(newAttempt($this->user, ['ip_address' => '185.220.101.2']))->suspicious)->toBeTrue()
        ->and(IpReputationCheck::new()->evaluate(newAttempt($this->user, ['ip_address' => '81.82.83.84']))->suspicious)->toBeFalse()
        ->and(IpReputationCheck::new()->allowTor()->evaluate(newAttempt($this->user, ['ip_address' => '185.220.101.2']))->suspicious)->toBeFalse();

    Http::assertSentCount(1);
});

it('flags ips with a high abuse score with the abuseipdb driver', function () {
    config()->set('suspicious-login.ip_reputation.driver', 'abuseipdb');
    config()->set('suspicious-login.ip_reputation.abuseipdb.key', 'test-key');

    Http::fake([
        'api.abuseipdb.com/*' => Http::sequence()
            ->push(['data' => ['isTor' => false, 'abuseConfidenceScore' => 80]])
            ->push(['data' => ['isTor' => false, 'abuseConfidenceScore' => 10]]),
    ]);

    expect(IpReputationCheck::new()->evaluate(newAttempt($this->user, ['ip_address' => '45.1.1.1']))->suspicious)->toBeTrue()
        ->and(IpReputationCheck::new()->evaluate(newAttempt($this->user, ['ip_address' => '45.2.2.2']))->suspicious)->toBeFalse();

    Http::assertSent(fn ($request) => $request->hasHeader('Key', 'test-key'));
});

it('flags vpns and tor nodes with the vpndetection driver', function () {
    config()->set('suspicious-login.ip_reputation.driver', 'vpndetection');

    Http::fake([
        'api.vpndetection.io/185.65.135.230' => Http::response(['is_vpn' => true, 'is_tor' => false]),
        'api.vpndetection.io/185.220.101.2' => Http::response(['is_vpn' => false, 'is_tor' => true]),
        'api.vpndetection.io/*' => Http::response(['is_vpn' => false, 'is_tor' => false]),
    ]);

    expect(IpReputationCheck::new()->evaluate(newAttempt($this->user, ['ip_address' => '185.65.135.230']))->suspicious)->toBeTrue()
        ->and(IpReputationCheck::new()->evaluate(newAttempt($this->user, ['ip_address' => '185.220.101.2']))->suspicious)->toBeTrue()
        ->and(IpReputationCheck::new()->evaluate(newAttempt($this->user, ['ip_address' => '81.82.83.84']))->suspicious)->toBeFalse()
        ->and(IpReputationCheck::new()->allowVpn()->evaluate(newAttempt($this->user, ['ip_address' => '185.65.135.230']))->suspicious)->toBeFalse();
});

/** @param  array<string, mixed>  $attributes */
function newAttempt(?User $user, array $attributes = []): LoginAttempt
{
    return new LoginAttempt([
        'authenticatable_type' => $user?->getMorphClass(),
        'authenticatable_id' => $user?->id,
        'successful' => true,
        'blocked' => false,
        'ip_address' => '81.82.83.84',
        'user_agent' => CHROME_ON_MAC,
        'device_fingerprint' => 'device',
        'created_at' => now(),
        ...$attributes,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function storeAttempt(?User $user, array $attributes = []): LoginAttempt
{
    $attempt = newAttempt($user, ['created_at' => now()->subMinute(), ...$attributes]);

    $attempt->save();

    return $attempt;
}
