<?php

namespace App\Domain\Notifications\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the notification centre, already rendered. Opening it marks it read and goes to its
 * target, when it has one.
 */
#[TypeScript]
class NotificationItemData extends Data
{
    public function __construct(
        public string $id,
        public ?string $category,
        public string $title,
        public string $body,
        public bool $hasTarget,
        public bool $read,
        public string $createdAt,
    ) {}
}
