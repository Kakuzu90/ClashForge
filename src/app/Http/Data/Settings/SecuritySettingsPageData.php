<?php

namespace App\Http\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Settings/Security. The session list is a deferred prop (`sessions`), so the page
 * shows its skeleton while it loads. Addresses are masked (specs/11 "Data exposure via page
 * props"); `pendingEmail` is the change waiting for its link.
 */
#[TypeScript]
class SecuritySettingsPageData extends Data
{
    public function __construct(
        public int $passwordMinLength,
        public string $email,
        public ?string $pendingEmail,
        public int $linkMinutes,
    ) {}
}
