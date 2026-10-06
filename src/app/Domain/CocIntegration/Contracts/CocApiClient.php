<?php

namespace App\Domain\CocIntegration\Contracts;

use App\Domain\CocIntegration\Data\ClanData;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Data\TokenVerificationResult;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use SensitiveParameter;

/**
 * The only way to the Clash of Clans API (specs/09 §1). Other modules call PlayerLookup,
 * ClanLookup and TokenVerifier, which turn these exceptions into results. `priority` decides the
 * rate budget and whether the cache is read; `fresh` skips the cache read (manual refresh).
 * `timeout` (seconds) replaces the total time limit for this one call; running out of it is a
 * `Deadline` failure, which the breaker does not count (P2-20).
 */
interface CocApiClient
{
    /**
     * @throws TagNotFound
     * @throws CocApiFailure
     */
    public function player(PlayerTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false, ?int $timeout = null): PlayerData;

    /**
     * @throws TagNotFound
     * @throws CocApiFailure
     */
    public function clan(ClanTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false): ClanData;

    /**
     * Ok or Invalid only. Always interactive and never cached. The token is used for this one call
     * and never stored, logged or queued.
     *
     * @throws TagNotFound
     * @throws CocApiFailure
     */
    public function verifyToken(PlayerTag $tag, #[SensitiveParameter] string $token): TokenVerificationResult;
}
