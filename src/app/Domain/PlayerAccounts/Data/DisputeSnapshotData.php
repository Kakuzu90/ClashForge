<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One stored snapshot of the tag, with what changed since the one before (clan, Town Hall). The
 * in-game name has no history by design (specs/23 §2). `changes` holds `clan` and `th`.
 */
#[TypeScript]
class DisputeSnapshotData extends Data
{
    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        /** ISO 8601 */
        public string $capturedAt,
        public ?int $townHallLevel,
        public ?string $clanTag,
        public ?int $trophies,
        public array $changes,
    ) {}
}
