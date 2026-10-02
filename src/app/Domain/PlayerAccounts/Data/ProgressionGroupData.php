<?php

namespace App\Domain\PlayerAccounts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One progression grid on the account page: heroes, equipment, troops and so on, in catalogue
 * order (specs/18 §6, P2-04). Empty groups are left out.
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
        #[DataCollectionOf(ProgressionUnitData::class)]
        public array $units,
    ) {}
}
