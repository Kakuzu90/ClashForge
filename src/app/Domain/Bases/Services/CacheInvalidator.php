<?php

namespace App\Domain\Bases\Services;

use App\Domain\Bases\Support\FeedCache;

/**
 * What busts the Bases caches (specs/21 §4): one answer for "what drops this key". Feeds sit under
 * one version number (`FeedCache`), so dropping them is a single increment.
 */
class CacheInvalidator
{
    public function baseFeeds(): void
    {
        FeedCache::bump();
    }
}
