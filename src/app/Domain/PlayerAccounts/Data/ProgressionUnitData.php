<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\GameAssets\Data\GameAssetData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One unit in a progression grid (specs/18 §6): its asset, level and whether it is maxed. The API's
 * `maxLevel` is the unit's top level in the game; null when it was not sent. A hero lists its
 * equipment, as tapping it in the game does; every other unit's list is empty. `locked` is a
 * catalogue unit the account has not unlocked yet: level 0, no max level, never maxed.
 */
#[TypeScript]
class ProgressionUnitData extends Data
{
    /**
     * @param  list<ProgressionUnitData>  $equipment
     */
    public function __construct(
        public string $name,
        public GameAssetData $asset,
        public int $level,
        public ?int $maxLevel,
        public bool $maxed,
        public bool $locked = false,
        #[DataCollectionOf(ProgressionUnitData::class)]
        public array $equipment = [],
    ) {}
}
