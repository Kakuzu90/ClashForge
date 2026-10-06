<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\ClanData;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Data\TokenVerificationResult;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use Closure;
use SensitiveParameter;

/**
 * Guards every outbound call: the breaker first, so an open circuit spends no budget, then the
 * rate budget, then the call, whose outcome feeds the breaker (specs/09 §4, §7). There are no
 * in-request retries: a failure goes back to the caller at once, and background jobs retry
 * through their queue backoff (owner decision 2026-10-02).
 */
final class ThrottledCocApiClient implements CocApiClient
{
    public function __construct(
        private readonly CocApiClient $inner,
        private readonly CircuitBreaker $breaker,
        private readonly RateBudget $budget,
    ) {}

    public function player(PlayerTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false, ?int $timeout = null): PlayerData
    {
        return $this->guard($priority, fn (): PlayerData => $this->inner->player($tag, $priority, $fresh, $timeout));
    }

    public function clan(ClanTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false): ClanData
    {
        return $this->guard($priority, fn (): ClanData => $this->inner->clan($tag, $priority, $fresh));
    }

    public function verifyToken(PlayerTag $tag, #[SensitiveParameter] string $token): TokenVerificationResult
    {
        return $this->guard(CocPriority::Interactive, fn (): TokenVerificationResult => $this->inner->verifyToken($tag, $token));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    private function guard(CocPriority $priority, Closure $call): mixed
    {
        $probe = $this->breaker->allow();

        try {
            $this->budget->take($priority);
            $result = $call();
        } catch (TagNotFound $e) {
            // The API answered.
            $this->breaker->recordSuccess($probe);

            throw $e;
        } catch (CocApiFailure $e) {
            if (! $this->breaker->recordFailure($e, $probe) && $probe) {
                $this->breaker->releaseProbe();
            }

            throw $e;
        }

        $this->breaker->recordSuccess($probe);

        return $result;
    }
}
