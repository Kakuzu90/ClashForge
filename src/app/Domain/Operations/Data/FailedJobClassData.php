<?php

namespace App\Domain\Operations\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One job class among the last day's failures. `name` is null when the payload carries no
 * readable class.
 */
#[TypeScript]
class FailedJobClassData extends Data
{
    public function __construct(
        public ?string $name,
        public int $count,
        public string $lastFailedAt,
    ) {}
}
