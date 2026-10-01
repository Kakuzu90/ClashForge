<?php

namespace Tests\Support\Auth;

use App\Domain\Auth\Contracts\TurnstileVerifier;

/**
 * Stands in for Cloudflare in every test (bound in TestCase), so no test calls the real service.
 * Tests of the Cloudflare verifier itself use it directly with Http::fake().
 */
class FakeTurnstile implements TurnstileVerifier
{
    /**
     * @var list<?string>
     */
    public array $tokens = [];

    public function __construct(public bool $pass = true) {}

    public function passes(?string $token, ?string $ip): bool
    {
        $this->tokens[] = $token;

        return $this->pass;
    }
}
