<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\GameAssets\Data\GameAssetData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One unit in a progression grid (specs/18 §6): its asset, level and whether it is maxed. The API's
 * `maxLevel` is the unit's top level in the game; null when it was not sent.
 */
#[TypeScript]
class ProgressionUnitData extends Data
{
    public function __construct(
        public string $name,
        public GameAssetData $asset,
        public int $level,
        public ?int $maxLevel,
        public bool $maxed,
    ) {}
}
