<?php

namespace Spatie\SuspiciousLogin;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\SuspiciousLogin\Drivers\Geolocation\GeolocationManager;
use Spatie\SuspiciousLogin\Drivers\IpReputation\IpReputationManager;
use Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter;
use Spatie\SuspiciousLogin\Listeners\RecordFailedLoginListener;
use Spatie\SuspiciousLogin\Listeners\RecordSuccessfulLoginListener;

class SuspiciousLoginServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-suspicious-login')
            ->hasConfigFile()
            ->hasMigration('create_login_attempts_table')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('spatie/laravel-suspicious-login');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(GeolocationManager::class);
        $this->app->singleton(IpReputationManager::class);
        $this->app->singleton(SuspiciousLogin::class);

        $this->app->bind(DeviceFingerprinter::class, fn () => $this->app->make(config('suspicious-login.device_fingerprinter')));
    }

    public function packageBooted(): void
    {
        Event::listen(Login::class, RecordSuccessfulLoginListener::class);
        Event::listen(Failed::class, RecordFailedLoginListener::class);
    }
}
