<?php

namespace App\Domain\CocIntegration\Data;

use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;

/**
 * What PlayerLookup returns instead of throwing: `player` is set exactly when `status` is Found;
 * `failure` and `retryAfter` (seconds, when the API said) only when it is Unavailable.
 */
final readonly class PlayerLookupResult
{
    public function __construct(
        public PlayerTag $tag,
        public CocLookupStatus $status,
        public ?PlayerData $player = null,
        public ?CocFailureReason $failure = null,
        public ?int $retryAfter = null,
    ) {}
}
