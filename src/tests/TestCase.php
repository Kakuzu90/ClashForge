<?php

namespace Tests;

use App\Domain\Auth\Contracts\TurnstileVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Auth\FakeTurnstile;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test reaches the network: every outbound call is faked or fails loudly (specs/09 §10).
        Http::preventStrayRequests();

        $this->app->instance(TurnstileVerifier::class, new FakeTurnstile);
    }

    protected function turnstile(bool $pass = true): FakeTurnstile
    {
        $fake = new FakeTurnstile($pass);
        $this->app->instance(TurnstileVerifier::class, $fake);

        return $fake;
    }
}
