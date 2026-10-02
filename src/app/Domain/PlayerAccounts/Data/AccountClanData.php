<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\GameAssets\Data\GameAssetData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The ClanChip on a PlayerCard (specs/18 §4): the clan's own badge, unmodified, its name and level,
 * and the account's role in it.
 */
#[TypeScript]
class AccountClanData extends Data
{
    public function __construct(
        public string $tag,
        public string $name,
        public ?int $level,
        public ?string $roleLabel,
        public GameAssetData $badge,
    ) {}
}
