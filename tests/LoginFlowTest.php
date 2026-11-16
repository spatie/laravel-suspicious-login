<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\SuspiciousLogin\Enums\RiskLevel;
use Spatie\SuspiciousLogin\Events\LoginAttemptRecordedEvent;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginDetectedEvent;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Notifications\SuspiciousLoginNotification;
use Spatie\SuspiciousLogin\Presets\MonitorOnly;
use Spatie\SuspiciousLogin\Presets\Strict;

beforeEach(function () {
    Notification::fake();

    $this->user = createUser();
});

it('records a successful login', function () {
    attemptLogin()->assertOk();

    $attempt = latestAttempt();

    expect($attempt->successful)->toBeTrue()
        ->and($attempt->authenticatable->is($this->user))->toBeTrue()
        ->and($attempt->ip_address)->toBe('81.82.83.84')
        ->and($attempt->user_agent)->toBe(CHROME_ON_MAC)
        ->and($attempt->risk_level)->toBe(RiskLevel::Low);
});

it('records a failed login with the identifier but not the password', function () {
    attemptLogin(password: 'wrong')->assertUnauthorized();

    $attempt = latestAttempt();

    expect($attempt->successful)->toBeFalse()
        ->and($attempt->identifier)->toBe('freek@spatie.be')
        ->and($attempt->authenticatable->is($this->user))->toBeTrue()
        ->and(json_encode($attempt->toArray()))->not->toContain('wrong');
});

it('records a failed login for an unknown account', function () {
    attemptLogin(email: 'nobody@example.com')->assertUnauthorized();

    expect(latestAttempt())
        ->identifier->toBe('nobody@example.com')
        ->authenticatable_id->toBeNull();
});

it('does not flag the first login of a user', function () {
    attemptLogin(userAgent: 'curl/8.0')->assertOk();

    expect(latestAttempt()->reasons)->toHaveCount(1)
        ->and(latestAttempt()->reasons[0]['reason'])->toContain('automated client');

    Notification::assertNothingSent();
});

it('does not flag a login from a known device and ip', function () {
    attemptLogin();
    attemptLogin();

    expect(latestAttempt()->risk_score)->toBe(0);

    Notification::assertNothingSent();
});

it('fires an event for every recorded attempt, after a successful one is scored', function () {
    Event::fake([LoginAttemptRecordedEvent::class]);

    attemptLogin(password: 'wrong');
    attemptLogin(userAgent: 'curl/8.0');

    $recordedAttempts = [];

    Event::assertDispatched(LoginAttemptRecordedEvent::class, function (LoginAttemptRecordedEvent $event) use (&$recordedAttempts) {
        $recordedAttempts[] = [$event->loginAttempt->exists, $event->loginAttempt->successful, $event->loginAttempt->risk_score];

        return true;
    });

    expect($recordedAttempts)->toBe([
        [true, false, null],
        [true, true, 20],
    ]);
});

it('notifies the user about a suspicious login with the balanced preset', function () {
    Event::fake([SuspiciousLoginDetectedEvent::class, SuspiciousLoginBlockedEvent::class]);

    attemptLogin();

    foreach (range(1, 5) as $i) {
        attemptLogin(ip: '5.6.7.8', userAgent: FIREFOX_ON_WINDOWS, password: 'wrong');
    }

    attemptLogin(ip: '5.6.7.8', userAgent: FIREFOX_ON_WINDOWS)->assertOk();

    $attempt = latestAttempt();

    expect($attempt->risk_score)->toBe(65)
        ->and($attempt->risk_level)->toBe(RiskLevel::Medium)
        ->and(collect($attempt->reasons)->pluck('reason')->all())->toBe([
            'Login from a new device',
            'Login from a new IP address (5.6.7.8)',
            '5 failed login attempts in the last 15 minutes',
        ]);

    Event::assertDispatched(SuspiciousLoginDetectedEvent::class, fn ($event) => $event->loginAttempt->is($attempt));

    Notification::assertSentTo($this->user, SuspiciousLoginNotification::class);

    Event::assertNotDispatched(SuspiciousLoginBlockedEvent::class);
});

it('does not trust a device that was only used in failed or blocked logins', function () {
    attemptLogin();

    attemptLogin(userAgent: FIREFOX_ON_WINDOWS, password: 'wrong');

    LoginAttempt::create([
        'authenticatable_type' => $this->user->getMorphClass(),
        'authenticatable_id' => $this->user->id,
        'successful' => true,
        'blocked' => true,
        'ip_address' => '81.82.83.84',
        'user_agent' => FIREFOX_ON_WINDOWS,
        'device_fingerprint' => latestAttempt()->device_fingerprint,
    ]);

    attemptLogin(userAgent: FIREFOX_ON_WINDOWS);

    expect(collect(latestAttempt()->reasons)->pluck('reason'))->toContain('Login from a new device');
});

it('treats a browser update as the same device', function () {
    attemptLogin();

    attemptLogin(userAgent: str_replace('141.0.7390.54', '142.0.7444.12', CHROME_ON_MAC));

    expect(latestAttempt()->risk_score)->toBe(0);
});

it('blocks a high risk login with the strict preset', function () {
    Event::fake([SuspiciousLoginBlockedEvent::class]);

    config()->set('suspicious-login.preset', Strict::class);

    fakeIpinfoLocations([
        '81.82.83.84' => ['BE', 'Antwerp', '51.2194,4.4025'],
        '5.6.7.8' => ['US', 'New York', '40.7128,-74.0060'],
    ]);

    attemptLogin();

    $this->travel(30)->minutes();

    attemptLogin(ip: '5.6.7.8', userAgent: FIREFOX_ON_WINDOWS)
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    $attempt = latestAttempt();

    expect($attempt->blocked)->toBeTrue()
        ->and($attempt->risk_level)->toBe(RiskLevel::High)
        ->and($attempt->country_code)->toBe('US')
        ->and(collect($attempt->reasons)->pluck('check')->map(fn ($check) => class_basename($check))->all())
        ->toContain('NewCountryCheck', 'ImpossibleTravelCheck');

    $this->assertGuest();

    Event::assertDispatched(SuspiciousLoginBlockedEvent::class, fn ($event) => $event->loginAttempt->is($attempt) && $event->loginAttempt->blocked);

    Notification::assertSentTo($this->user, SuspiciousLoginNotification::class);
});

it('takes no action with the monitor only preset', function () {
    config()->set('suspicious-login.preset', MonitorOnly::class);

    attemptLogin();

    foreach (range(1, 5) as $i) {
        attemptLogin(ip: '5.6.7.8', userAgent: FIREFOX_ON_WINDOWS, password: 'wrong');
    }

    attemptLogin(ip: '5.6.7.8', userAgent: FIREFOX_ON_WINDOWS)->assertOk();

    expect(latestAttempt()->risk_level)->toBe(RiskLevel::Medium);

    Notification::assertNothingSent();
});

it('records nothing when disabled', function () {
    config()->set('suspicious-login.enabled', false);

    attemptLogin();
    attemptLogin(password: 'wrong');

    expect(LoginAttempt::count())->toBe(0);
});

it('does not record a login restored from the remember me cookie', function () {
    $response = attemptLogin(remember: true);

    $recallerName = auth()->guard('web')->getRecallerName();

    $recaller = $response->getCookie($recallerName)->getValue();

    $this->flushSession();

    test()->withServerVariables(['REMOTE_ADDR' => '81.82.83.84'])
        ->withCookie($recallerName, $recaller)
        ->get('me')
        ->assertOk()
        ->assertSee('freek@spatie.be');

    expect(LoginAttempt::count())->toBe(1);
});
