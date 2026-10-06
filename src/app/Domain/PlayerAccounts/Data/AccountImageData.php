<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\Media\Data\MediaVariantData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One custom image on an account page (FR-COC-11, P2-23). `card` and `full` are set once it is
 * ready; until then only the owner gets the item, `processing` or `failed` (a failed or quarantined
 * upload still takes a place until they remove it). No storage keys or user ids.
 */
#[TypeScript]
class AccountImageData extends Data
{
    public function __construct(
        public string $ulid,
        public ?MediaVariantData $card,
        public ?MediaVariantData $full,
        public bool $processing,
        public bool $failed,
    ) {}
}
