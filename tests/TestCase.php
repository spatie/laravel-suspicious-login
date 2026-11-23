<?php

namespace Spatie\SuspiciousLogin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\SuspiciousLogin\SuspiciousLoginServiceProvider;
use Spatie\SuspiciousLogin\Tests\TestSupport\User;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            SuspiciousLoginServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('cache.default', 'array');
        config()->set('auth.providers.users.model', User::class);
        config()->set('suspicious-login.geolocation.driver', 'null');
    }

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        (include __DIR__.'/../database/migrations/create_login_attempts_table.php.stub')->up();
        (include __DIR__.'/../database/migrations/add_history_indexes_to_login_attempts_table.php.stub')->up();
    }

    protected function defineRoutes($router)
    {
        Route::post('login', function (Request $request) {
            if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
                return response('Invalid credentials', 401);
            }

            return response('Logged in');
        })->middleware('web');

        Route::get('me', fn (Request $request) => $request->user()->email)->middleware(['web', 'auth']);
    }
}
