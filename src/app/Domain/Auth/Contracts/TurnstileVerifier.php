<?php

namespace App\Domain\Auth\Contracts;

/**
 * Cloudflare Turnstile, the invisible captcha on registration and the reset-link form (FR-AUTH-11,
 * specs/11 "API abuse"). Bound to the Cloudflare implementation; tests bind a fake.
 */
interface TurnstileVerifier
{
    public function passes(?string $token, ?string $ip): bool;
}
