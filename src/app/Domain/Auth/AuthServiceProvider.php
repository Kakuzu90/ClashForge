<?php

namespace App\Domain\Auth;

use App\Domain\Auth\Listeners\LogSecurityEvents;
use App\Domain\Auth\Listeners\RecordLogin;
use App\Domain\Auth\Support\HibpVerifier;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // extend(), not bind(): the framework's deferred ValidationServiceProvider binds its own
        // verifier when it loads, which would silently replace ours.
        $this->app->extend(UncompromisedVerifier::class, fn (UncompromisedVerifier $verifier, $app): HibpVerifier => new HibpVerifier(
            $app->make(Factory::class),
            (int) config('platform.auth.hibp_timeout'),
        ));
    }

    public function boot(): void
    {
        // specs/04 §4: at least 10 characters, no composition rules, not in a known breach.
        Password::defaults(fn (): Password => Password::min((int) config('platform.auth.min_password_length'))->uncompromised());

        Event::listen(Login::class, RecordLogin::class);
        Event::subscribe(LogSecurityEvents::class);
    }
}
