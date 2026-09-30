<?php

namespace App\Domain\Auth\Support;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\NotPwnedVerifier;
use Throwable;

/**
 * The compromised-password check behind `Password::uncompromised()` (specs/04 §4): synchronous,
 * a short timeout, and fail-open when Have I Been Pwned cannot be reached, logged so an outage is
 * visible rather than silent.
 */
class HibpVerifier extends NotPwnedVerifier
{
    public function __construct(Factory $factory, int $timeout)
    {
        parent::__construct($factory, $timeout);
    }

    /**
     * @param  string  $hashPrefix
     * @return Collection<int, string>
     */
    protected function search($hashPrefix)
    {
        try {
            $response = $this->factory->withHeaders(['Add-Padding' => 'true'])
                ->timeout($this->timeout)
                ->get('https://api.pwnedpasswords.com/range/'.$hashPrefix);

            if ($response->successful()) {
                /** @var Collection<int, string> $lines */
                $lines = Str::of($response->body())->trim()->explode("\n")->filter(fn (string $line): bool => str_contains($line, ':'))->values();

                return $lines;
            }

            Log::warning('auth.hibp_unavailable', ['status' => $response->status()]);
        } catch (Throwable $e) {
            Log::warning('auth.hibp_unavailable', ['error' => $e::class]);
        }

        return new Collection;
    }
}
