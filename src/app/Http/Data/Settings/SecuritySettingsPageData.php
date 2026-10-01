<?php

namespace App\Http\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Settings/Security. The session list is a deferred prop (`sessions`), so the page
 * shows its skeleton while it loads.
 */
#[TypeScript]
class SecuritySettingsPageData extends Data
{
    public function __construct(
        public int $passwordMinLength,
    ) {}
}
