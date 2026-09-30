<?php

namespace Tests\Support\Auth;

use Illuminate\Support\Facades\Http;

/**
 * Fakes the Have I Been Pwned range API behind Password::uncompromised(); no test calls the real one.
 */
trait FakesHibp
{
    /**
     * @param  list<string>  $breached  passwords the fake reports as leaked
     */
    protected function fakeHibp(array $breached = []): void
    {
        $lines = array_map(function (string $password): string {
            return substr(strtoupper(sha1($password)), 5).':42';
        }, $breached);

        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response(implode("\r\n", ['0018A45C4D1DEF81644B54AB7F969B88D65:1', ...$lines])),
            // Anything else (the SSR renderer) fails, so pages fall back to client rendering.
            '*' => Http::response('', 503),
        ]);
    }

    protected function hibpDown(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
    }
}
