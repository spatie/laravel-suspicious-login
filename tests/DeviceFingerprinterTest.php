<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;
use Spatie\SuspiciousLogin\Fingerprinters\UserAgentFingerprinter;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\DefaultPreset;
use Spatie\SuspiciousLogin\Presets\MonitorOnlyPreset;

beforeEach(function () {
    config()->set('suspicious-login.preset', MonitorOnlyPreset::class);

    $this->user = createUser();
});

it('recognizes a browser across updates and distinguishes another browser with the same user agent by default', function () {
    URL::forceScheme('https');
    $this->freezeTime();

    $response = attemptLogin()->assertOk();
    $cookie = $response->getCookie('suspicious_login_device');
    $encryptedCookie = $response->getCookie('suspicious_login_device', decrypt: false)->getValue();
    $fingerprint = latestAttempt()->device_fingerprint;

    expect(Str::isUuid($cookie->getValue()))->toBeTrue()
        ->and($encryptedCookie)->not->toBe($cookie->getValue())
        ->and($fingerprint)->toBe(hash('sha256', $cookie->getValue()))
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBe(now()->addDays(365)->getTimestamp());

    $this->travel(1)->day();
    $this->withUnencryptedCookie('suspicious_login_device', $encryptedCookie);

    $response = attemptLogin(userAgent: str_replace('141.0.7390.54', '142.0.7444.12', CHROME_ON_MAC))->assertOk();

    expect(latestAttempt()->device_fingerprint)->toBe($fingerprint)
        ->and(latestAttempt()->risk_score)->toBe(0)
        ->and($response->getCookie('suspicious_login_device')->getExpiresTime())->toBe(now()->addDays(365)->getTimestamp());

    $this->withUnencryptedCookie('suspicious_login_device', '');

    attemptLogin()->assertOk();

    expect(latestAttempt()->device_fingerprint)->not->toBe($fingerprint)
        ->and(latestAttempt()->risk_score)->toBe(25);
});

it('can explicitly use user agents without a device cookie', function () {
    config()->set('suspicious-login.device_fingerprinter', UserAgentFingerprinter::class);

    attemptLogin()->assertOk()->assertCookieMissing('suspicious_login_device');
    $fingerprint = latestAttempt()->device_fingerprint;

    attemptLogin(userAgent: str_replace('141.0.7390.54', '142.0.7444.12', CHROME_ON_MAC))
        ->assertOk()
        ->assertCookieMissing('suspicious_login_device');

    expect(latestAttempt()->device_fingerprint)->toBe($fingerprint)
        ->and(latestAttempt()->risk_score)->toBe(0);

    attemptLogin(userAgent: FIREFOX_ON_WINDOWS)->assertOk();

    expect(latestAttempt()->device_fingerprint)->not->toBe($fingerprint)
        ->and(latestAttempt()->risk_score)->toBe(25);
});

it('replaces forged or malformed device cookies', function (string $invalidCookie) {
    attemptLogin()->assertOk();
    $fingerprint = latestAttempt()->device_fingerprint;

    $this->withUnencryptedCookie('suspicious_login_device', $invalidCookie);

    $response = attemptLogin()->assertOk();

    expect(Str::isUuid($response->getCookie('suspicious_login_device')->getValue()))->toBeTrue()
        ->and(latestAttempt()->device_fingerprint)->not->toBe($fingerprint)
        ->and(latestAttempt()->risk_score)->toBe(25);
})->with([
    'unencrypted identifier' => '4d72a91c-e183-4f63-bfc7-50a1fdedbc84',
    'invalid ciphertext' => 'not-an-encrypted-cookie',
]);

it('uses the same identity when recording multiple attempts in one request', function () {
    Route::post('record-twice', function (Request $request) {
        SuspiciousLogin::recordSuccessfulLogin($this->user, request: $request);
        SuspiciousLogin::recordSuccessfulLogin($this->user, request: $request);

        return response('Recorded');
    })->middleware('web');

    $response = $this->post('record-twice')->assertOk();

    expect(LoginAttempt::count())->toBe(2)
        ->and(LoginAttempt::query()->pluck('device_fingerprint')->unique()->all())
        ->toBe([hash('sha256', $response->getCookie('suspicious_login_device')->getValue())]);
});

it('keeps a blocked browser identity without treating it as trusted', function () {
    config()->set('suspicious-login.preset', CookieBlockingPreset::class);

    attemptLogin()->assertOk();
    $response = attemptLogin()->assertSessionHasErrors('email');
    $fingerprint = latestAttempt()->device_fingerprint;

    $this->withUnencryptedCookie('suspicious_login_device', $response->getCookie('suspicious_login_device', decrypt: false)->getValue());

    attemptLogin()->assertSessionHasErrors('email');

    expect(latestAttempt()->device_fingerprint)->toBe($fingerprint)
        ->and(latestAttempt()->risk_score)->toBe(25)
        ->and(latestAttempt()->blocked)->toBeTrue();
});

class CookieBlockingPreset extends DefaultPreset
{
    protected int $mediumRiskThreshold = 1;

    public function onMediumRisk(): Action|array
    {
        return BlockLoginAction::new();
    }
}
