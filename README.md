<div align="left">
    <a href="https://spatie.be/open-source?utm_source=github&utm_medium=banner&utm_campaign=laravel-suspicious-login">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://spatie.be/packages/header/laravel-suspicious-login/html/dark.webp">
        <img alt="Logo for Laravel Suspicious Login" src="https://spatie.be/packages/header/laravel-suspicious-login/html/light.webp">
      </picture>
    </a>

<h1>Detect suspicious logins in your Laravel app</h1>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-suspicious-login.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-suspicious-login)
[![GitHub Tests Action Status](https://github.com/spatie/laravel-suspicious-login/actions/workflows/run-tests.yml/badge.svg)](https://github.com/spatie/laravel-suspicious-login/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/spatie/laravel-suspicious-login/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/spatie/laravel-suspicious-login/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-suspicious-login.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-suspicious-login)

</div>

Imagine someone logs in to your account from the other side of the world. If you're travelling, that might be perfectly normal. But if you're sitting at home in Antwerp, it's suspicious. Someone might have gotten hold of your credentials.

We've built this package to notify users about suspicious logins and let you decide what happens next. It checks the device, IP address, location and login history to give each successful login a risk score. Depending on that score, it can email the user, block the login or run your own actions.

Here's how a login from a new laptop in New York, to an account that always logs in from Antwerp, is scored with `DefaultPreset` and geolocation configured:

```
Login from a new device                  +25
Login from a new IP address (5.6.7.8)    +10
Login from a new country (US)            +30
                                         ----
Risk score                                65  → medium risk, the user gets an email
```

A preset lists the checks to run, how much each weighs, and which actions run at each risk level. It can also override the default risk thresholds from the config file. The package ships with `DefaultPreset`, `MonitorOnlyPreset` and `NonePreset`, and writing your own takes a few lines:

```php
namespace App\SuspiciousLogin;

use Spatie\SuspiciousLogin\Actions\Action;
use Spatie\SuspiciousLogin\Actions\BlockLoginAction;
use Spatie\SuspiciousLogin\Actions\NotifyUserAction;
use Spatie\SuspiciousLogin\Checks\CountryCheck;
use Spatie\SuspiciousLogin\Checks\ImpossibleTravelCheck;
use Spatie\SuspiciousLogin\Checks\NewDeviceCheck;
use Spatie\SuspiciousLogin\Presets\Preset;

class MyPreset extends Preset
{
    public function checks(): array
    {
        return [
            NewDeviceCheck::new()->score(30),
            ImpossibleTravelCheck::new()->maxSpeedInKilometersPerHour(1000)->score(60),
            CountryCheck::new()->onlyAllow(['BE', 'NL', 'FR'])->score(60),
        ];
    }

    public function onMediumRisk(): Action|array
    {
        return NotifyUserAction::new();
    }

    public function onHighRisk(): Action|array
    {
        return [NotifyUserAction::new(), BlockLoginAction::new()];
    }
}
```

Register your preset in `config/suspicious-login.php`:

```php
'preset' => App\SuspiciousLogin\MyPreset::class,
```

The default risk thresholds live in `config/suspicious-login.php`:

```php
'risk_thresholds' => [
    'medium' => 40,
    'high' => 70,
],
```

Optionally define `protected int $mediumRiskThreshold` or `protected int $highRiskThreshold` in your preset to override either config value. `DefaultPreset` and `MonitorOnlyPreset` use the configured defaults.

To choose a preset per login, register a callback in a service provider's `boot()` method. For example, apply stricter checks to admins and skip assessment for exempt users:

```php
use App\SuspiciousLogin\MyPreset;
use Spatie\SuspiciousLogin\Facades\SuspiciousLogin;
use Spatie\SuspiciousLogin\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Presets\DefaultPreset;
use Spatie\SuspiciousLogin\Presets\NonePreset;

SuspiciousLogin::determinePresetUsing(function (LoginAttempt $attempt): string {
    $user = $attempt->authenticatable;

    if ($user->is_admin) {
        return MyPreset::class;
    }

    if ($user->skip_login_checks) {
        return NonePreset::class;
    }

    return DefaultPreset::class;
});
```

The user attributes above are application-specific. The callback receives the attempt before location lookups, with its user, guard and custom properties available. Return a preset class name, never `null`. `NonePreset` still records the login and fires `LoginAttemptRecordedEvent`, but skips location lookups, scoring and actions. Unlike `MonitorOnlyPreset`, it leaves the risk score and level unset. See [choosing a preset per login](https://spatie.be/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset#content-choosing-a-preset-per-login).

Only the actions for the matching level run. List `BlockLoginAction` last because it logs the user out of their session guard and throws a validation exception.

An action is a small class with an `execute` method that receives the login attempt. There's no magic behind the ones the package ships with. Here's `BlockLoginAction` in full:

```php
class BlockLoginAction extends Action
{
    protected string $errorKey = 'email';

    protected string $message = 'We blocked this login because it looked suspicious. Please contact support if this was you.';

    public function errorKey(string $errorKey): static
    {
        $this->errorKey = $errorKey;

        return $this;
    }

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function execute(LoginAttempt $attempt): void
    {
        $attempt->update(['blocked' => true]);

        event(new SuspiciousLoginBlockedEvent($attempt));

        Auth::guard($attempt->guard)->logout();

        $this->respond($attempt);
    }

    protected function respond(LoginAttempt $attempt): never
    {
        throw ValidationException::withMessages([
            $this->errorKey => __($this->message),
        ]);
    }
}
```

`new()` comes from the base `Action` class and resolves the action from the container, so actions can inject dependencies in their constructor. The fluent methods configure it in your preset, like `BlockLoginAction::new()->message('...')`. Because the login happens inside `Auth::attempt()`, the validation exception comes out of your login controller like any other validation error.

To change the response without replacing the blocking and logout behavior, extend `BlockLoginAction` and override `protected function respond(LoginAttempt $attempt): never`. See [customizing the response](https://spatie.be/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/blocking-logins#content-customizing-the-response).

Writing your own action, for example to ask for a second factor or alert your security team, works the same way: extend `Action` and implement `execute`. Checks are built the same way too: extend `Check` and return `$this->pass()` or `$this->flag($reason)` from `evaluate`.

You can also add [`MultipleCountriesCheck`](https://spatie.be/docs/laravel-suspicious-login/v1/available-checks/multiple-countries) to your preset to flag logins from different countries within one hour, even when both countries are already known.

Checks, actions, geolocation and IP reputation drivers, device fingerprints and the model are all swappable, and the package fires events you can listen for.

The default device fingerprint groups browsers by user agent, ignoring version numbers. For web apps, opt into [cookie-based fingerprints](https://spatie.be/docs/laravel-suspicious-login/v1/advanced-usage/device-fingerprints#content-using-a-device-cookie) to distinguish browsers with identical user agents without JavaScript.

## Support us

[<img src="https://spatie.be/github-ad-image/laravel-suspicious-login.jpg" width="419px" />](https://spatie.be/github-ad-click/laravel-suspicious-login)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-suspicious-login
```

Run the install command:

```bash
php artisan suspicious-login:install
```

It publishes `config/suspicious-login.php` and the migration for the `login_attempts` table, then asks whether to run your application's pending migrations. If you decline, run `php artisan migrate` before using the package. It also offers to star the repository on GitHub.

That's it. The package listens for Laravel's `Login` and `Failed` auth events, so it works with Breeze, Jetstream, Fortify and your own login controllers without changes. Logins restored from a "remember me" cookie are not recorded.

Out of the box `DefaultPreset` is used, and the `ipinfo` driver looks up public IP addresses without requiring an API token. Lookups are cached for a week and subject to ipinfo's limits. You can choose another [geolocation driver](https://spatie.be/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers) in `config/suspicious-login.php`, or set it to `'null'` to disable lookups and avoid sending IP addresses to an external service.

The default mailable is sent to the user's `email` address and requires working mail configuration, not the `Notifiable` trait. It is queued, so run a queue worker unless you use the `sync` queue connection. Set `mailable` in `config/suspicious-login.php` to use your own mailable class.

You can [publish the email's Markdown view](https://spatie.be/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/notifying-users#content-customizing-the-email-view) to change its content or layout without replacing the mailable. Prefer Laravel notifications? [Use a custom action](https://spatie.be/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/notifying-users#content-using-laravel-notifications-instead).

For an existing application, consider starting with `Spatie\SuspiciousLogin\Presets\MonitorOnlyPreset`. It records scores and fires events without notifying or blocking users, so you can review the results before enabling actions.

## Documentation

All documentation is available [on our documentation site](https://spatie.be/docs/laravel-suspicious-login).

- [Get started with a complete example](https://spatie.be/docs/laravel-suspicious-login/v1/basic-usage/getting-started).
- [Choose a preset](https://spatie.be/docs/laravel-suspicious-login/v1/basic-usage/choosing-a-preset) or [customize one](https://spatie.be/docs/laravel-suspicious-login/v1/basic-usage/customizing-presets).
- [Query login attempts](https://spatie.be/docs/laravel-suspicious-login/v1/basic-usage/working-with-login-attempts) and [add custom properties](https://spatie.be/docs/laravel-suspicious-login/v1/basic-usage/adding-custom-properties).
- [Write checks](https://spatie.be/docs/laravel-suspicious-login/v1/advanced-usage/creating-custom-checks), [write actions](https://spatie.be/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/writing-your-own-actions), or [listen for events](https://spatie.be/docs/laravel-suspicious-login/v1/responding-to-suspicious-logins/using-events).

### Laravel Boost

The package ships guidelines and a `detecting-suspicious-logins` skill for [Laravel Boost](https://laravel.com/docs/boost). Run `php artisan boost:install` in your application and select this package to give your coding agent its APIs, extension points, and usage examples. See [the setup instructions](https://spatie.be/docs/laravel-suspicious-login/v1/installation-setup#content-laravel-boost).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
