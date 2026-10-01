<?php

namespace App\Http\Data\Settings;

use App\Domain\Notifications\Data\EmailPreferencesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EmailPreferencesPageData extends Data
{
    public function __construct(public EmailPreferencesData $settings) {}
}
