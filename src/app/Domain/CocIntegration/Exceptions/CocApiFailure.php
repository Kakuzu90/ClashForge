<?php

namespace App\Domain\CocIntegration\Exceptions;

use App\Domain\CocIntegration\Enums\CocFailureReason;
use RuntimeException;

/**
 * The API could not answer (specs/09 §7). Internal to the module: the lookup services turn it into
 * an `unavailable` result, so no caller sees an exception and no page sees a 5xx.
 */
final class CocApiFailure extends RuntimeException
{
    public function __construct(
        public readonly CocFailureReason $reason,
        public readonly ?int $retryAfter = null,
        string $detail = '',
    ) {
        parent::__construct(trim("CoC API {$reason->value} {$detail}"));
    }

    public static function malformed(string $problem): self
    {
        return new self(CocFailureReason::Malformed, detail: $problem);
    }
}
