<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Data\CocKeyStatusData;
use App\Domain\CocIntegration\Data\KeyPoolStatusData;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Support\CocApiKey;
use App\Domain\CocIntegration\Support\CocCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The keys from `COC_API_TOKENS`, used round-robin (specs/09 §3), each within its own budget of
 * `coc.rate.per_key_per_second` (specs/09 §4). A key answering 403 is marked unhealthy in the
 * cache, shared by every worker, for `coc.key_pool.unhealthy_ttl` or until a health probe succeeds
 * with it.
 */
class CocKeyPool
{
    private const DENIAL_REASONS = ['accessDenied', 'accessDenied.invalidIp'];

    /** @var list<CocApiKey>|null */
    private ?array $keys = null;

    /**
     * @return list<CocApiKey>
     */
    public function keys(): array
    {
        if ($this->keys === null) {
            /** @var list<string> $tokens */
            $tokens = config('coc.tokens');
            $this->keys = array_values(array_map(CocApiKey::fromToken(...), array_unique($tokens)));
        }

        return $this->keys;
    }

    /**
     * The next healthy key with budget left, skipping the ids already tried for this call, and
     * spends one unit of its budget. Null when no healthy key is left.
     *
     * @param  list<string>  $exceptIds
     *
     * @throws CocApiFailure throttled, when healthy keys exist but every one is at its budget
     */
    public function next(array $exceptIds = []): ?CocApiKey
    {
        $healthy = array_values(array_filter(
            $this->keys(),
            fn (CocApiKey $key): bool => ! in_array($key->id, $exceptIds, true) && ! $this->isUnhealthy($key->id),
        ));

        if ($healthy === []) {
            return null;
        }

        $perKey = (int) config('coc.rate.per_key_per_second');
        $usable = array_values(array_filter(
            $healthy,
            fn (CocApiKey $key): bool => ! RateLimiter::tooManyAttempts(CocCacheKeys::rateKey($key->id), $perKey),
        ));

        if ($usable === []) {
            $wait = min(array_map(fn (CocApiKey $key): int => RateLimiter::availableIn(CocCacheKeys::rateKey($key->id)), $healthy));

            throw new CocApiFailure(CocFailureReason::Throttled, max(1, $wait), 'every key is at its budget');
        }

        Cache::add(CocCacheKeys::keyCursor(), 0, (int) config('coc.key_pool.cursor_ttl'));
        $turn = (int) Cache::increment(CocCacheKeys::keyCursor());
        $key = $usable[$turn % count($usable)];
        RateLimiter::hit(CocCacheKeys::rateKey($key->id), 1);

        return $key;
    }

    /**
     * Takes the key out of rotation. Logged once per outage, not once per call: error for a
     * revoked key, critical when the egress IP is not on the key, since that breaks every key at
     * once (specs/09 §7, specs/11 §3).
     */
    public function markUnhealthy(CocApiKey $key, ?string $apiReason): void
    {
        $reason = in_array($apiReason, self::DENIAL_REASONS, true) ? $apiReason : 'accessDenied';
        $wasHealthy = ! $this->isUnhealthy($key->id);

        Cache::put(
            CocCacheKeys::keyUnhealthy($key->id),
            ['reason' => $reason, 'since' => Date::now()->getTimestamp()],
            (int) config('coc.key_pool.unhealthy_ttl'),
        );

        if (! $wasHealthy) {
            return;
        }

        Log::log($reason === 'accessDenied.invalidIp' ? 'critical' : 'error', 'coc.key_unhealthy', ['key' => $key->id, 'reason' => $reason]);

        if ($this->status()->healthy === 0) {
            Log::critical('coc.keys_all_unhealthy', ['keys' => count($this->keys())]);
        }
    }

    public function markHealthy(CocApiKey $key): void
    {
        if ($this->isUnhealthy($key->id)) {
            Cache::forget(CocCacheKeys::keyUnhealthy($key->id));
            Log::info('coc.key_healthy', ['key' => $key->id]);
        }
    }

    public function status(): KeyPoolStatusData
    {
        $keys = array_map(function (CocApiKey $key): CocKeyStatusData {
            $marker = Cache::get(CocCacheKeys::keyUnhealthy($key->id));
            $unhealthy = is_array($marker);

            return new CocKeyStatusData(
                id: $key->id,
                healthy: ! $unhealthy,
                reason: $unhealthy && is_string($marker['reason'] ?? null) ? $marker['reason'] : null,
                unhealthySince: $unhealthy && is_int($marker['since'] ?? null) ? Date::createFromTimestamp($marker['since']) : null,
            );
        }, $this->keys());

        return new KeyPoolStatusData(
            total: count($keys),
            healthy: count(array_filter($keys, fn (CocKeyStatusData $k): bool => $k->healthy)),
            keys: $keys,
        );
    }

    private function isUnhealthy(string $keyId): bool
    {
        return Cache::has(CocCacheKeys::keyUnhealthy($keyId));
    }
}
