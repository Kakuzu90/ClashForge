<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\GameAssets\Enums\Village;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One progression grid on the account page: heroes, pets, troops and so on, in catalogue order,
 * under the village tab it belongs to (specs/18 §6, P2-04). Empty groups are left out.
 */
#[TypeScript]
class ProgressionGroupData extends Data
{
    /**
     * @param  list<ProgressionUnitData>  $units
     */
    public function __construct(
        public string $key,
        public string $label,
        public Village $village,
        #[DataCollectionOf(ProgressionUnitData::class)]
        public array $units,
    ) {}
}
