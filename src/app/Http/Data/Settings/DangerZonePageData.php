<?php

namespace App\Http\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DangerZonePageData extends Data
{
    public function __construct(public int $graceDays, public bool $canRequestDeletion) {}
}
