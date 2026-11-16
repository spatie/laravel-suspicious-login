<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Tests\TestCase;
use Spatie\SuspiciousLogin\Tests\TestSupport\User;

uses(TestCase::class)->in(__DIR__);

const CHROME_ON_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.54 Safari/537.36';
const FIREFOX_ON_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0';

function createUser(string $email = 'freek@spatie.be'): User
{
    return User::create(['email' => $email, 'password' => 'secret']);
}

/** @param  array<string, string>  $headers */
function attemptLogin(
    string $email = 'freek@spatie.be',
    string $password = 'secret',
    string $ip = '81.82.83.84',
    string $userAgent = CHROME_ON_MAC,
    array $headers = [],
    bool $remember = false,
): TestResponse {
    $response = test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['User-Agent' => $userAgent, ...$headers])
        ->post('login', ['email' => $email, 'password' => $password, 'remember' => $remember]);

    Auth::forgetGuards();

    return $response;
}

function latestAttempt(): LoginAttempt
{
    return LoginAttempt::query()->latest('id')->firstOrFail();
}

/**
 * @param  array<string, array{0: string, 1: string, 2: string}>  $locations  IP => [country, city, "lat,long"]
 */
function fakeIpinfoLocations(array $locations): void
{
    config()->set('suspicious-login.geolocation.driver', 'ipinfo');

    $responses = [];

    foreach ($locations as $ip => [$country, $city, $coordinates]) {
        $responses["ipinfo.io/{$ip}/json"] = Http::response(['ip' => $ip, 'country' => $country, 'city' => $city, 'loc' => $coordinates]);
    }

    Http::fake($responses);
}
