<?php

namespace App\Domain\CocIntegration\Data;

use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use Carbon\CarbonImmutable;

/**
 * Outcome of a token check (specs/09 §8). It never carries the token (specs/09 §9). `verifiedAt`
 * is set only when the API answered ok.
 */
final readonly class TokenVerificationResult
{
    public function __construct(
        public PlayerTag $tag,
        public TokenVerificationStatus $status,
        public ?CarbonImmutable $verifiedAt = null,
        public ?CocFailureReason $failure = null,
        public ?int $retryAfter = null,
    ) {}
}
