<?php

namespace App\Domain\Operations\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The newest failed jobs of one class (`name`, null for unreadable payloads), at most
 * `platform.admin.failed_jobs_list_max`; `total` is how many the class has.
 */
#[TypeScript]
class FailedJobListData extends Data
{
    /**
     * @param  list<FailedJobRowData>  $jobs
     */
    public function __construct(
        public ?string $name,
        public int $total,
        #[DataCollectionOf(FailedJobRowData::class)]
        public array $jobs,
    ) {}
}
