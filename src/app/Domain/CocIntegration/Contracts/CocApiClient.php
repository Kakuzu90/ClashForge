<?php

namespace App\Domain\CocIntegration\Contracts;

use App\Domain\CocIntegration\Data\ClanData;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Data\TokenVerificationResult;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use SensitiveParameter;

/**
 * The only way to the Clash of Clans API (specs/09 §1). Other modules call PlayerLookup,
 * ClanLookup and TokenVerifier, which turn these exceptions into results.
 */
interface CocApiClient
{
    /**
     * @throws TagNotFound
     * @throws CocApiFailure
     */
    public function player(PlayerTag $tag): PlayerData;

    /**
     * @throws TagNotFound
     * @throws CocApiFailure
     */
    public function clan(ClanTag $tag): ClanData;

    /**
     * Ok or Invalid only. The token is used for this one call and never stored, logged or queued.
     *
     * @throws TagNotFound
     * @throws CocApiFailure
     */
    public function verifyToken(PlayerTag $tag, #[SensitiveParameter] string $token): TokenVerificationResult;
}
