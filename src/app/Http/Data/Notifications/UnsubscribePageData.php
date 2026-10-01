<?php

namespace App\Http\Data\Notifications;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UnsubscribePageData extends Data
{
    public function __construct(public string $outcome, public string $message, public ?string $confirmUrl) {}
}
