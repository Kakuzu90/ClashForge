<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Data\TokenVerificationResult;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use SensitiveParameter;

/**
 * Checks an in-game API token against a tag (specs/09 §9). The token lives only inside this call:
 * it is not returned, logged, cached or queued. Recording the attempt is the caller's job
 * (`coc_account_claims`, P2-02).
 */
class TokenVerifier
{
    public function __construct(private readonly CocApiClient $client) {}

    public function verify(PlayerTag $tag, #[SensitiveParameter] string $token): TokenVerificationResult
    {
        $token = trim($token);

        if ($token === '') {
            return new TokenVerificationResult($tag, TokenVerificationStatus::Invalid);
        }

        try {
            return $this->client->verifyToken($tag, $token);
        } catch (TagNotFound) {
            return new TokenVerificationResult($tag, TokenVerificationStatus::NotFound);
        } catch (CocApiFailure $e) {
            return new TokenVerificationResult($tag, TokenVerificationStatus::Unavailable, failure: $e->reason, retryAfter: $e->retryAfter);
        }
    }
}
