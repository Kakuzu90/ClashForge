<?php

namespace App\Domain\Operations\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One job class among the kept failures, with every queue it failed on. `name` is null when the
 * payload carries no readable class.
 */
#[TypeScript]
class FailedJobGroupData extends Data
{
    /**
     * @param  list<string>  $queues
     */
    public function __construct(
        public ?string $name,
        public array $queues,
        public int $count,
        public int $lastHour,
        public string $firstFailedAt,
        public string $lastFailedAt,
    ) {}
}
