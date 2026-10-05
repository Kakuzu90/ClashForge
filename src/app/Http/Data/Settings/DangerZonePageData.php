<?php

namespace App\Http\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * `holds` says why a deletion would wait past the grace period (specs/23 §1), in words for the user.
 */
#[TypeScript]
class DangerZonePageData extends Data
{
    /**
     * @param  list<string>  $holds
     */
    public function __construct(public int $graceDays, public bool $canRequestDeletion, public array $holds) {}
}
