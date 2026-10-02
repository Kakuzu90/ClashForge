<?php

namespace App\Domain\Clans\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a ClanChip shows (specs/18 §4). `badgeUrls` are the API's own URLs, size name → URL,
 * rendered unmodified through `GameAssetResolver`; empty when the clan has no badge.
 */
#[TypeScript]
class ClanSummaryData extends Data
{
    /**
     * @param  array<string, string>  $badgeUrls
     */
    public function __construct(
        public string $tag,
        public string $name,
        public ?int $level,
        public array $badgeUrls,
    ) {}
}
