<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props shared with every Inertia page (HandleInertiaRequests::share). Allowlist from specs/11.
 */
#[TypeScript]
class SharedPropsData extends Data
{
    /**
     * @param  array{success: string|null, error: string|null}  $flash
     * @param  array<string, bool>  $features  Client-safe feature flags only
     */
    public function __construct(
        public AuthData $auth,
        public array $flash,
        public ?int $unreadCount,
        public array $features,
    ) {}
}
