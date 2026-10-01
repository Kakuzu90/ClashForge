<?php

namespace App\Domain\Auth\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One signed-in browser in the session list (FR-AUTH-7). `key` is an HMAC of the session id, so
 * the page never carries a value that could be replayed as a session cookie. No IP, raw user agent
 * or payload.
 */
#[TypeScript]
class SessionData extends Data
{
    public function __construct(
        public string $key,
        public string $deviceLabel,
        public ?string $country,
        /** ISO 8601 */
        public string $lastActiveAt,
        /** ISO 8601, null for sessions created before the column existed */
        public ?string $signedInAt,
        public bool $isCurrent,
    ) {}
}
