<?php

namespace Tests\Support\Auth;

use App\Domain\Auth\Services\RegistrationGuard;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

/**
 * Submits the registration form the way a person would: a form token from ten seconds ago,
 * an empty bot trap and a Turnstile token (the fake in TestCase passes it).
 */
trait RegistersAccounts
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function registrationInput(array $overrides = []): array
    {
        Date::setTestNow(Date::now()->subSeconds(10));
        $started = RegistrationGuard::startToken();
        Date::setTestNow(Date::now()->addSeconds(10));

        return [
            'email' => 'newcomer@example.com',
            'username' => 'newcomer',
            'password' => 'a-long-passphrase',
            'password_confirmation' => 'a-long-passphrase',
            'turnstile_token' => 'token',
            'website' => '',
            'started' => $started,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function register(array $overrides = []): TestResponse
    {
        return $this->from('/register')->post('/register', $this->registrationInput($overrides));
    }
}
