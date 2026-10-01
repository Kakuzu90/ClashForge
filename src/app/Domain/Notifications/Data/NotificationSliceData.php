<?php

namespace App\Domain\Notifications\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One page of the notification centre, newest first.
 */
#[TypeScript]
class NotificationSliceData extends Data
{
    /**
     * @param  list<NotificationItemData>  $entries
     */
    public function __construct(
        public array $entries,
        public ?string $newerCursor,
        public ?string $olderCursor,
    ) {}
}
