<?php

namespace App\Http\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Settings/EmailChangeConfirm. `outcome` is an EmailChangeOutcome value. While it is
 * `pending`, `username` names the account, `newEmail` is the masked address and `confirmUrl` is
 * where the button posts.
 */
#[TypeScript]
class EmailChangeConfirmPageData extends Data
{
    public function __construct(
        public string $outcome,
        public string $message,
        public ?string $username,
        public ?string $newEmail,
        public ?string $confirmUrl,
    ) {}
}
