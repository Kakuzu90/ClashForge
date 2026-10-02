<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\PlayerLookupResult;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a player for other modules (specs/09 §1). Never throws for an API problem: the caller
 * gets `unavailable` and degrades (specs/09 §7).
 */
class PlayerLookup
{
    public function __construct(private readonly CocApiClient $client) {}

    public function find(PlayerTag $tag): PlayerLookupResult
    {
        try {
            return new PlayerLookupResult($tag, CocLookupStatus::Found, $this->client->player($tag));
        } catch (TagNotFound) {
            // Our normaliser accepted it and the API did not: logged to tune the normaliser (specs/23 §2).
            Log::info('coc.tag_not_found', ['kind' => 'player', 'tag' => $tag->value]);

            return new PlayerLookupResult($tag, CocLookupStatus::NotFound);
        } catch (CocApiFailure $e) {
            return new PlayerLookupResult($tag, CocLookupStatus::Unavailable, failure: $e->reason, retryAfter: $e->retryAfter);
        }
    }
}
