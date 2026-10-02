<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\ClanLookupResult;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a clan for other modules (specs/09 §1), with the same no-throw contract as PlayerLookup.
 */
class ClanLookup
{
    public function __construct(private readonly CocApiClient $client) {}

    public function find(ClanTag $tag): ClanLookupResult
    {
        try {
            return new ClanLookupResult($tag, CocLookupStatus::Found, $this->client->clan($tag));
        } catch (TagNotFound) {
            Log::info('coc.tag_not_found', ['kind' => 'clan', 'tag' => $tag->value]);

            return new ClanLookupResult($tag, CocLookupStatus::NotFound);
        } catch (CocApiFailure $e) {
            return new ClanLookupResult($tag, CocLookupStatus::Unavailable, failure: $e->reason, retryAfter: $e->retryAfter);
        }
    }
}
