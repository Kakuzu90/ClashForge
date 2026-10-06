<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\ClanData;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\CocTag;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Data\TokenVerificationResult;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;
use SensitiveParameter;

/**
 * The outermost layer (specs/09 §1, §5): a cache hit costs no rate budget. Stores the raw payload
 * and when it was fetched, as plain arrays (specs/21 §1: reconstructible, driver-agnostic), and
 * maps it again on read.
 *
 * Interactive calls read the short-lived entry and the negative 404 cache; background calls and
 * `fresh` calls skip both reads, since they exist to get new data. Every success also refreshes
 * the 24 h `…:last` entry, which an interactive call gets, marked stale, when the API is
 * unavailable; background and `fresh` calls get the failure.
 * verifyToken passes straight through: an ownership check is never answered from a cache.
 */
final class CachedCocApiClient implements CocApiClient
{
    public function __construct(
        private readonly CocApiClient $inner,
        private readonly ResponseMapper $mapper,
        private readonly CocRequestLog $log,
    ) {}

    public function player(PlayerTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false, ?int $timeout = null): PlayerData
    {
        $ttl = $priority === CocPriority::Background ? 'coc.cache.player_sync_ttl' : 'coc.cache.player_ttl';

        /** @var PlayerData */
        return $this->fetch(
            kind: 'player',
            endpoint: 'players',
            tag: $tag,
            useCache: $priority === CocPriority::Interactive && ! $fresh,
            ttl: (int) config($ttl),
            key: CocCacheKeys::player($tag),
            lastKey: CocCacheKeys::playerLast($tag),
            call: fn (): PlayerData => $this->inner->player($tag, $priority, $fresh, $timeout),
            map: fn (Payload $p, CarbonImmutable $at, bool $stale): PlayerData => $this->mapper->player($p, $at, $stale),
        );
    }

    public function clan(ClanTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false): ClanData
    {
        /** @var ClanData */
        return $this->fetch(
            kind: 'clan',
            endpoint: 'clans',
            tag: $tag,
            useCache: $priority === CocPriority::Interactive && ! $fresh,
            ttl: (int) config('coc.cache.clan_ttl'),
            key: CocCacheKeys::clan($tag),
            lastKey: CocCacheKeys::clanLast($tag),
            call: fn (): ClanData => $this->inner->clan($tag, $priority, $fresh),
            map: fn (Payload $p, CarbonImmutable $at, bool $stale): ClanData => $this->mapper->clan($p, $at, $stale),
        );
    }

    public function verifyToken(PlayerTag $tag, #[SensitiveParameter] string $token): TokenVerificationResult
    {
        return $this->inner->verifyToken($tag, $token);
    }

    /**
     * @param  'player'|'clan'  $kind
     * @param  Closure(): (PlayerData|ClanData)  $call
     * @param  Closure(Payload, CarbonImmutable, bool): (PlayerData|ClanData)  $map
     */
    private function fetch(string $kind, string $endpoint, CocTag $tag, bool $useCache, int $ttl, string $key, string $lastKey, Closure $call, Closure $map): PlayerData|ClanData
    {
        if ($useCache) {
            if (Cache::has(CocCacheKeys::notFound($kind, $tag))) {
                $this->log->record($endpoint, $tag, 404, 0, cached: true);

                throw new TagNotFound($tag->value);
            }

            if (($hit = $this->entry($key, $endpoint, $map, stale: false)) !== null) {
                $this->log->record($endpoint, $tag, 200, 0, cached: true);

                return $hit;
            }
        }

        try {
            $data = $call();
        } catch (TagNotFound $e) {
            Cache::put(CocCacheKeys::notFound($kind, $tag), true, (int) config('coc.cache.negative_ttl'));

            throw $e;
        } catch (CocApiFailure $e) {
            // Only an ordinary view falls back to the last good answer. A sync job must see the
            // failure to back off, and a manual refresh must say the API is unavailable
            // (specs/09 §6, §7).
            if (! $useCache) {
                throw $e;
            }

            return $this->entry($lastKey, $endpoint, $map, stale: true) ?? throw $e;
        }

        $entry = ['payload' => $data->rawPayload, 'fetched_at' => ($data->fetchedAt ?? CarbonImmutable::now())->getTimestamp()];
        Cache::put($key, $entry, $ttl);
        Cache::put($lastKey, $entry, (int) config('coc.cache.stale_ttl'));
        Cache::forget(CocCacheKeys::notFound($kind, $tag));

        return $data;
    }

    /**
     * A cached entry mapped back to its DTO; null when absent or no longer mappable (a mapper
     * change since it was written), so it is fetched again instead of failing.
     *
     * @param  Closure(Payload, CarbonImmutable, bool): (PlayerData|ClanData)  $map
     */
    private function entry(string $key, string $endpoint, Closure $map, bool $stale): PlayerData|ClanData|null
    {
        $entry = Cache::get($key);

        if (! is_array($entry) || ! is_array($entry['payload'] ?? null) || ! is_int($entry['fetched_at'] ?? null)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = $entry['payload'];

            return $map(new Payload($payload, $endpoint), CarbonImmutable::createFromTimestamp($entry['fetched_at']), $stale);
        } catch (CocApiFailure) {
            Cache::forget($key);

            return null;
        }
    }
}
