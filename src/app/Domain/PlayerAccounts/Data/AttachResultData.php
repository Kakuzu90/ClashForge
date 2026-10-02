<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a preview or an attach answered. `player` comes with `ready`, `attached` and
 * `verified_elsewhere`; `accountUlid` with `attached` and `already_attached`; `holderUsername` with
 * `verified_elsewhere` (the conflict card, specs/13 §4); `retryAfter` (seconds) with `rate_limited`
 * and, when the API said, `unavailable`.
 */
#[TypeScript]
class AttachResultData extends Data
{
    public function __construct(
        public AttachOutcome $outcome,
        public string $tag,
        public ?CocPlayerPreviewData $player = null,
        public ?string $accountUlid = null,
        public ?string $holderUsername = null,
        public ?int $retryAfter = null,
    ) {}
}
