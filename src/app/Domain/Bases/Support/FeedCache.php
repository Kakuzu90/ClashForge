<?php

namespace App\Domain\Bases\Support;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Enums\FeedSort;
use Illuminate\Support\Facades\Cache;

/**
 * Feed cache keys (specs/21 §3) under one version number (§4 mechanism 3): a publish or a trending
 * run bumps it, which orphans every cached page at once; old keys expire on their TTL.
 */
final class FeedCache
{
    private const VERSION_KEY = 'feed:version';

    // Long enough to outlive every page TTL; losing it only restarts the numbering.
    private const VERSION_TTL = 30 * 86_400;

    public static function key(FeedFiltersData $filters, int $page, ?string $position): string
    {
        $at = $position === null ? "p{$page}" : "p{$page}:".substr(sha1($position), 0, 12);

        return 'feed:v'.self::version().':'.$filters->signature().':'.$at;
    }

    public static function ttl(FeedFiltersData $filters): int
    {
        return (int) config($filters->sort === FeedSort::Newest ? 'bases.feed.cache_ttl_new' : 'bases.feed.cache_ttl_trending');
    }

    public static function bump(): void
    {
        Cache::add(self::VERSION_KEY, 1, self::VERSION_TTL);
        Cache::increment(self::VERSION_KEY);
    }

    private static function version(): int
    {
        return (int) Cache::remember(self::VERSION_KEY, self::VERSION_TTL, fn (): int => 1);
    }
}
