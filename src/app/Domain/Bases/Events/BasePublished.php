<?php

namespace App\Domain\Bases\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A base became visible (specs/05 §4): at once, or when its last media item was ready. Dispatched
 * after commit; search indexing and notifications listen (P3-04, P3-05).
 */
final class BasePublished
{
    use Dispatchable;

    public function __construct(
        public readonly string $baseUlid,
        public readonly int $userId,
    ) {}
}
