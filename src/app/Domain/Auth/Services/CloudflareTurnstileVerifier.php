<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\TurnstileVerifier;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks Cloudflare's siteverify about a widget token. If Cloudflare cannot be reached in time the
 * form is let through and `auth.turnstile_unavailable` logged, as the compromised-password check
 * does: the honeypot, fill time and limiter still stand (owner decision, 2026-10-01). A missing
 * secret outside local and test runs fails closed.
 */
class CloudflareTurnstileVerifier implements TurnstileVerifier
{
    public function passes(?string $token, ?string $ip): bool
    {
        $secret = config('services.turnstile.secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('auth.turnstile_misconfigured');

            return false;
        }

        if ($token === null || $token === '' || strlen($token) > (int) config('services.turnstile.max_token_length')) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout'))
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            return $this->unavailable($ip, $e->getMessage());
        }

        if ($response->serverError()) {
            return $this->unavailable($ip, 'status '.$response->status());
        }

        $passed = $response->json('success') === true;

        if (! $passed) {
            Log::channel('security')->info('auth.turnstile_failed', [
                'codes' => array_values(array_filter((array) $response->json('error-codes'), 'is_string')),
                'ip_hash' => IpHash::of($ip),
            ]);
        }

        return $passed;
    }

    private function unavailable(?string $ip, string $error): bool
    {
        Log::warning('auth.turnstile_unavailable', ['error' => $error, 'ip_hash' => IpHash::of($ip)]);

        return true;
    }
}
