<?php

namespace Tests;

use App\Domain\Auth\Contracts\TurnstileVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\Auth\FakeTurnstile;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(TurnstileVerifier::class, new FakeTurnstile);
    }

    protected function turnstile(bool $pass = true): FakeTurnstile
    {
        $fake = new FakeTurnstile($pass);
        $this->app->instance(TurnstileVerifier::class, $fake);

        return $fake;
    }
}
