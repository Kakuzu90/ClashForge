<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The stat blocks on the public profile, from `user_stats` (specs/18 §6).
 */
#[TypeScript]
class ProfileStatsData extends Data
{
    public function __construct(
        public int $basesPublished,
        public int $likesReceived,
        public int $copies,
    ) {}
}
