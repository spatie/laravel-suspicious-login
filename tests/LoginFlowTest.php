<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\Check;
use Spatie\SuspiciousLogin\Checks\CheckResult;
use Spatie\SuspiciousLogin\Checks\SuspiciousUserAgentCheck;
use Spatie\SuspiciousLogin\Enums\RiskLevel;
use Spatie\SuspiciousLogin\Events\LoginAttemptRecordedEvent;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginBlockedEvent;
use Spatie\SuspiciousLogin\Events\SuspiciousLoginDetectedEvent;
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Notifications\SuspiciousLoginNotification;
use Spatie\SuspiciousLogin\Presets\DefaultPreset;
use Spatie\SuspiciousLogin\Presets\MonitorOnlyPreset;
use Spatie\SuspiciousLogin\Presets\NonePreset;

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
        ->and($attempt->risk_level)->toBe(RiskLevel::Low)
        ->and($attempt->properties)->toBeNull();
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

it('notifies the user about a suspicious login with the default preset', function () {
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

it('renders the login details and reasons in the notification email', function () {
    config()->set('app.name', 'My app');

    $attempt = new LoginAttempt([
        'created_at' => '2026-11-18 19:30:00',
        'ip_address' => '81.82.83.84',
        'country_code' => 'BE',
        'city' => 'Antwerp',
        'user_agent' => '<script>alert("device")</script>',
        'reasons' => [
            ['check' => 'custom', 'score' => 50, 'reason' => 'Unusual <b>device</b>'],
        ],
    ]);

    $mail = (new SuspiciousLoginNotification($attempt))->toMail($this->user);
    $html = $mail->render()->toHtml();

    expect($mail->subject)->toBe('Suspicious login to your My app account')
        ->and(html_entity_decode(strip_tags($html)))
        ->toContain('Time: Wed, Nov 18, 2026 7:30 PM', 'IP address: 81.82.83.84', 'Location: Antwerp, BE')
        ->and($html)
        ->toContain('&lt;script&gt;', 'Unusual &lt;b&gt;device&lt;/b&gt;', 'change your password right away.')
        ->not->toContain('<script>', '<b>device</b>');
});

it('renders the notification email without location, device or reasons', function () {
    $attempt = new LoginAttempt([
        'created_at' => '2026-11-18 19:30:00',
        'ip_address' => '81.82.83.84',
    ]);

    $mail = (new SuspiciousLoginNotification($attempt))->toMail($this->user);

    expect(html_entity_decode(strip_tags($mail->render()->toHtml())))
        ->toContain('Location: Unknown', 'Device: Unknown', 'change your password right away.')
        ->not->toContain('Why this login looks unusual');
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

it('blocks a high risk login with a custom preset', function () {
    Event::fake([SuspiciousLoginBlockedEvent::class]);

    config()->set('suspicious-login.preset', BlockingPreset::class);

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

it('allows overriding the blocked login response without replacing the blocking behavior', function () {
    Event::fake([SuspiciousLoginBlockedEvent::class]);

    config()->set('suspicious-login.preset', BlockingPreset::class);

    app()->bind(BlockLoginAction::class, fn () => new class extends BlockLoginAction
    {
        protected function respond(LoginAttempt $attempt): never
        {
            abort(403, "Login {$attempt->getKey()} requires verification.");
        }
    });

    attemptLogin()->assertOk();

    $response = attemptLogin(userAgent: 'curl/8.0');

    $attempt = latestAttempt();

    $response->assertForbidden()
        ->assertSee("Login {$attempt->getKey()} requires verification.");

    expect($attempt->blocked)->toBeTrue();

    $this->assertGuest();

    Event::assertDispatched(SuspiciousLoginBlockedEvent::class, fn ($event) => $event->loginAttempt->is($attempt) && $event->loginAttempt->blocked);
});

it('takes no action with the monitor only preset', function () {
    config()->set('suspicious-login.preset', MonitorOnlyPreset::class);

    attemptLogin();

    foreach (range(1, 5) as $i) {
        attemptLogin(ip: '5.6.7.8', userAgent: FIREFOX_ON_WINDOWS, password: 'wrong');
    }

    attemptLogin(ip: '5.6.7.8', userAgent: FIREFOX_ON_WINDOWS)->assertOk();

    expect(latestAttempt()->risk_level)->toBe(RiskLevel::Medium);

    Notification::assertNothingSent();
});

it('disables automatic recording without disabling explicit facade calls', function () {
    config()->set('suspicious-login.enabled', false);

    attemptLogin();
    attemptLogin(password: 'wrong');

    expect(LoginAttempt::count())->toBe(0);

    $successfulAttempt = SuspiciousLogin::recordSuccessfulLogin($this->user);
    $failedAttempt = SuspiciousLogin::recordFailedLogin(null, identifier: 'unknown@example.com');

    expect(LoginAttempt::count())->toBe(2)
        ->and($successfulAttempt->risk_level)->toBe(RiskLevel::Low)
        ->and($failedAttempt->identifier)->toBe('unknown@example.com')
        ->and($failedAttempt->risk_level)->toBeNull();
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

it('adds custom properties to every attempt before the checks run', function () {
    config()->set('suspicious-login.preset', DefaultWithTenantCheckPreset::class);

    SuspiciousLogin::addPropertiesUsing(fn (Request $request, ?Authenticatable $user) => [
        'tenant' => $request->header('X-Tenant'),
        'user_id' => $user?->getAuthIdentifier(),
    ]);

    attemptLogin(email: 'nobody@example.com', headers: ['X-Tenant' => 'acme']);

    expect(latestAttempt()->properties)->toBe(['tenant' => 'acme', 'user_id' => null]);

    attemptLogin(headers: ['X-Tenant' => 'acme']);

    expect(latestAttempt())
        ->properties->toBe(['tenant' => 'acme', 'user_id' => $this->user->id])
        ->and(collect(latestAttempt()->reasons)->pluck('reason')->all())->toBe(['Login to tenant acme']);
});

it('lets properties passed when recording manually override the callback', function () {
    SuspiciousLogin::addPropertiesUsing(fn () => ['tenant' => 'acme', 'region' => ['code' => 'eu']]);

    $attempt = SuspiciousLogin::recordSuccessfulLogin($this->user, properties: ['tenant' => 'other', 'source' => 'api']);

    expect($attempt->fresh())
        ->getProperty('tenant')->toBe('other')
        ->getProperty('source')->toBe('api')
        ->getProperty('region.code')->toBe('eu')
        ->getProperty('missing', 'default')->toBe('default');
});

it('assesses a login without saving it, firing events or running actions', function () {
    Event::fake([LoginAttemptRecordedEvent::class, SuspiciousLoginDetectedEvent::class]);

    config()->set('suspicious-login.preset', BlockingPreset::class);

    $attempt = new LoginAttempt(['user_agent' => 'curl/8.0']);

    $attempt->authenticatable()->associate($this->user);

    expect(SuspiciousLogin::assess($attempt))->toBe($attempt)
        ->risk_score->toBe(30)
        ->risk_level->toBe(RiskLevel::Medium)
        ->reasons->toBe([
            ['check' => SuspiciousUserAgentCheck::class, 'score' => 30, 'reason' => 'Login from an automated client (curl)'],
        ])
        ->and(LoginAttempt::count())->toBe(0);

    Event::assertNothingDispatched();
    Notification::assertNothingSent();
});

it('skips notifying a user that no longer exists', function () {
    $attempt = SuspiciousLogin::recordSuccessfulLogin($this->user);

    $this->user->delete();

    NotifyUserAction::new()->execute($attempt->fresh());

    Notification::assertNothingSent();
});

it('selects a preset per user for automatic logins', function () {
    createUser('member@example.com');

    SuspiciousLogin::determinePresetUsing(fn (LoginAttempt $attempt) => $attempt->authenticatable->email === 'freek@spatie.be'
        ? BlockingPreset::class
        : config('suspicious-login.preset'));

    attemptLogin(userAgent: 'curl/8.0')->assertOk();

    expect(latestAttempt()->risk_score)->toBe(30)
        ->and(latestAttempt()->risk_level)->toBe(RiskLevel::Medium);

    Notification::assertSentTo($this->user, SuspiciousLoginNotification::class);

    attemptLogin(email: 'member@example.com', userAgent: 'curl/8.0')->assertOk();

    expect(latestAttempt()->risk_score)->toBe(20)
        ->and(latestAttempt()->risk_level)->toBe(RiskLevel::Low);

    Notification::assertCount(1);
});

it('uses the selected preset for actions even after the attempt is saved', function () {
    SuspiciousLogin::determinePresetUsing(fn (LoginAttempt $attempt) => $attempt->exists
        ? NonePreset::class
        : BlockingPreset::class);

    attemptLogin(userAgent: 'curl/8.0')->assertOk();

    expect(latestAttempt()->risk_level)->toBe(RiskLevel::Medium);

    Notification::assertSentTo($this->user, SuspiciousLoginNotification::class);
});

it('records without assessment or lookups with the none preset', function (bool $useResolver) {
    Event::fake([LoginAttemptRecordedEvent::class, SuspiciousLoginDetectedEvent::class]);

    fakeIpinfoLocations(['81.82.83.84' => ['BE', 'Antwerp', '51.2194,4.4025']]);

    if ($useResolver) {
        SuspiciousLogin::addPropertiesUsing(fn () => ['skip_checks' => true]);

        SuspiciousLogin::determinePresetUsing(fn (LoginAttempt $attempt) => $attempt->guard === 'web' && $attempt->getProperty('skip_checks')
            ? NonePreset::class
            : BlockingPreset::class);
    }

    if (! $useResolver) {
        config()->set('suspicious-login.preset', NonePreset::class);
    }

    attemptLogin(userAgent: 'curl/8.0')->assertOk();

    expect(latestAttempt())
        ->successful->toBeTrue()
        ->blocked->toBeFalse()
        ->risk_score->toBeNull()
        ->risk_level->toBeNull()
        ->reasons->toBeNull()
        ->country_code->toBeNull()
        ->and(latestAttempt()->authenticatable->is($this->user))->toBeTrue()
        ->and(latestAttempt()->isSuspicious())->toBeFalse();

    Http::assertNothingSent();
    Notification::assertNothingSent();
    Event::assertDispatched(LoginAttemptRecordedEvent::class, fn ($event) => $event->loginAttempt->is(latestAttempt()));
    Event::assertNotDispatched(SuspiciousLoginDetectedEvent::class);
})->with([true, false]);

it('selects presets for manual recording and standalone assessment', function () {
    SuspiciousLogin::determinePresetUsing(fn (LoginAttempt $attempt) => $attempt->guard === 'api'
        ? NonePreset::class
        : BlockingPreset::class);

    $attempt = SuspiciousLogin::recordSuccessfulLogin($this->user, guard: 'api');

    expect($attempt->fresh()->risk_level)->toBeNull();

    $attempt = new LoginAttempt(['user_agent' => 'curl/8.0']);

    SuspiciousLogin::assess($attempt);

    expect($attempt->risk_score)->toBe(30)
        ->and($attempt->risk_level)->toBe(RiskLevel::Medium);

    $attempt->guard = 'api';

    SuspiciousLogin::assess($attempt);

    expect($attempt->risk_score)->toBeNull()
        ->and($attempt->risk_level)->toBeNull()
        ->and($attempt->reasons)->toBeNull();
});

it('records failed attempts without selecting a preset', function () {
    SuspiciousLogin::determinePresetUsing(function () {
        throw new RuntimeException('Failed attempts should not select a preset.');
    });

    attemptLogin(password: 'wrong')->assertUnauthorized();

    expect(latestAttempt()->successful)->toBeFalse()
        ->and(latestAttempt()->risk_level)->toBeNull();
});

class BlockingPreset extends DefaultPreset
{
    protected int $mediumRiskThreshold = 30;

    protected int $highRiskThreshold = 50;

    public function checks(): array
    {
        return collect(parent::checks())
            ->map(fn (Check $check) => $check instanceof SuspiciousUserAgentCheck ? $check->score(30) : $check)
            ->all();
    }

    public function onHighRisk(): Action|array
    {
        return [NotifyUserAction::new(), BlockLoginAction::new()];
    }
}

class DefaultWithTenantCheckPreset extends DefaultPreset
{
    public function checks(): array
    {
        return [new TenantCheck];
    }
}

class TenantCheck extends Check
{
    public function evaluate(LoginAttempt $attempt): CheckResult
    {
        if (! $attempt->getProperty('tenant')) {
            return $this->pass();
        }

        return $this->flag("Login to tenant {$attempt->getProperty('tenant')}");
    }
}
