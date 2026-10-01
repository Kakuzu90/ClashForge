<?php

namespace App\Http\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Choices and limits for the sanction dialogs, from config and the reason taxonomy.
 */
#[TypeScript]
class SanctionFormData extends Data
{
    /**
     * @param  list<FilterOptionData>  $reasons
     */
    public function __construct(
        public array $reasons,
        public int $maxDays,
        public int $publicReasonMax,
        public int $noteMax,
    ) {}
}
