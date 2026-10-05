<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One of the signed-in user's own accounts, for the attach flow and their own profile. Only the
 * owner ever sees this shape; other viewers get PlayerCards with P2-04. `canFeature` is false
 * for the featured row itself.
 */
#[TypeScript]
class OwnCocAccountData extends Data
{
    public function __construct(
        public string $ulid,
        public string $tag,
        public string $name,
        public CocAccountStatus $status,
        public string $statusLabel,
        public ?int $townHallLevel,
        public bool $featured,
        public bool $canFeature,
    ) {}
}
