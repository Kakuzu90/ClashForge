<?php

namespace App\Domain\CocIntegration\Data;

use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;

/**
 * What ClanLookup returns instead of throwing: `clan` is set exactly when `status` is Found.
 */
final readonly class ClanLookupResult
{
    public function __construct(
        public ClanTag $tag,
        public CocLookupStatus $status,
        public ?ClanData $clan = null,
        public ?CocFailureReason $failure = null,
        public ?int $retryAfter = null,
    ) {}
}
