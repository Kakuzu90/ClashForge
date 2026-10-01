<?php

namespace App\Http\Data\Notifications;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One category filter of the notification centre; `value` null is "All".
 */
#[TypeScript]
class NotificationTabData extends Data
{
    public function __construct(
        public ?string $value,
        public string $label,
    ) {}
}
