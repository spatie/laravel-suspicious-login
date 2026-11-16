# Detect suspicious logins in your Laravel app

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-suspicious-login.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-suspicious-login)
[![GitHub Tests Action Status](https://github.com/spatie/laravel-suspicious-login/actions/workflows/run-tests.yml/badge.svg)](https://github.com/spatie/laravel-suspicious-login/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/spatie/laravel-suspicious-login/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/spatie/laravel-suspicious-login/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-suspicious-login.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-suspicious-login)

A stolen password rarely looks like an attack: to your app, it's a normal login. This package looks at everything around that login (the device, the IP address, where it comes from, how many attempts failed before it) and gives it a risk score. Depending on the score, it can notify the user, block the login, or do whatever you want.

Here's how a login from a new laptop in New York, to an account that always logs in from Antwerp, is scored with the default preset:

```
Login from a new device                  +25
Login from a new IP address (5.6.7.8)    +10
Login from a new country (US)            +30
                                         ----
Risk score                                65  → medium risk, the user gets an email
```

Everything that decides how the package behaves lives in a preset: which checks run, how much each weighs, where the risk levels start, and which actions run at each level. The package ships with `Lenient`, `Balanced`, `Strict` and `MonitorOnly` presets, and writing your own takes a few lines:

```php
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

    public function actions(RiskLevel $riskLevel): array
    {
        return match ($riskLevel) {
            RiskLevel::Medium => [NotifyUserAction::new()],
            RiskLevel::High => [NotifyUserAction::new(), BlockLoginAction::new()],
            default => [],
        };
    }
}
```

Checks, actions, geolocation and IP reputation drivers, device fingerprints and the model are all swappable, and the package fires events you can listen for.

## Support us

[<img src="https://spatie.be/github-ad-image/laravel-suspicious-login.jpg" width="419px" />](https://spatie.be/github-ad-click/laravel-suspicious-login)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-suspicious-login
```

Then publish the config file and the migration, and run the migration:

```bash
php artisan suspicious-login:install
```

That's it. The package listens for Laravel's `Login` and `Failed` auth events, so it works with Breeze, Jetstream, Fortify and your own login controllers without changes. Logins restored from a "remember me" cookie are not recorded.

Out of the box the `Balanced` preset is used, and locations are not looked up. To use the location checks, pick a [geolocation driver](https://spatie.be/docs/laravel-suspicious-login/v1/advanced-usage/geolocation-drivers).

## Documentation

All documentation is available [on our documentation site](https://spatie.be/docs/laravel-suspicious-login).

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
