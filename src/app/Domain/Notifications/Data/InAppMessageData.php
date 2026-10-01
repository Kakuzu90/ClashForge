<?php

namespace App\Domain\Notifications\Data;

use App\Domain\Notifications\Enums\NotificationType;
use Spatie\LaravelData\Data;

/**
 * What one in-app notification stores: its type and the parameters it is rendered from. Scalars
 * only. `groupKey` is kept for aggregation (specs/16 §3), which no Phase 1 type uses.
 */
class InAppMessageData extends Data
{
    /**
     * @param  array<string, string|int|bool|null>  $params
     */
    public function __construct(
        public NotificationType $type,
        public array $params = [],
        public ?string $groupKey = null,
    ) {}
}
