<?php

namespace App\Domain\Operations\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One queue on the System Health page (specs/20 §6). `waiting` jobs are runnable now, `delayed`
 * ones become runnable later, `reserved` ones are held by a worker. `maxWaitSeconds` is null for a
 * queue with no wait alert line.
 */
#[TypeScript]
class QueueStatData extends Data
{
    public function __construct(
        public string $name,
        public int $waiting,
        public int $delayed,
        public int $reserved,
        public ?int $oldestWaitSeconds,
        public ?int $maxWaitSeconds,
        public int $maxDepth,
        public bool $overWait,
        public bool $overDepth,
    ) {}
}
