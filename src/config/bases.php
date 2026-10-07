<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bases (specs/07 Bases, FR-BASE-*)
    |--------------------------------------------------------------------------
    |
    | Publishing limits and the field rules the composer and the publish service share (P3-01).
    |
    */

    // Town Halls a base may be for (FR-BASE-1); raise `th_max` when the game adds one.
    'th_min' => 2,
    'th_max' => 18,

    'title_max' => 80,
    'description_max' => 2000,

    // Tags per base and the longest tag (FR-BASE-4).
    'tags_max' => 10,
    'tag_max_length' => 24,

    // Media per base (specs/10 §8), checked in the publish transaction.
    'screenshots_max' => 2,
    'videos_max' => 1,

    // The `base-publish` limit (specs/04 §4, FR-BASE-14): bases a user creates in a rolling day and
    // week, deleted ones included, so deleting and republishing still counts (specs/23 §3).
    'publish_per_day' => 5,
    'publish_per_week' => 20,

    // Offered first in the composer; a tag on this list is created as suggested (FR-BASE-4).
    'suggested_tags' => [
        'ring-base', 'island', 'box', 'anti-air', 'anti-ground', 'anti-dragon',
        'anti-root-rider', 'anti-hybrid', 'esports', 'max-th',
    ],

    // The feed (specs/17 §4, §6; specs/21 §3; P3-03). Cards per page and the deepest page an
    // anonymous visitor may load; cached lists live this many seconds.
    'feed' => [
        'per_page' => 24,
        'max_pages' => 100,
        'cache_ttl_trending' => 300,
        'cache_ttl_new' => 60,
        // Highest `min_likes` filter accepted.
        'min_likes_max' => 100_000,
        // A signed-in home feed defaults to the featured account's Town Hall, this many either side.
        'default_th_spread' => 1,
        // Only the first pages are worth caching; deeper ones run live rather than fill the cache.
        'cache_max_page' => 5,
        // The `feed` limiter on `/` and `/bases`: per user signed in, per IP otherwise.
        'requests_per_minute' => 120,
    ],

    // trending = (like*likes + copy*copies + comment*comments + view*views)
    //            / (hours since publish + offset_hours) ^ exponent   (specs/17 §5)
    // Recomputed every 15 minutes for bases published in the last `active_days`, nightly for all.
    // Within one layout hash only the earliest published base keeps its full score; later copies
    // are multiplied by `duplicate_penalty` (specs/23 §3).
    'trending' => [
        'like' => 2.0,
        'copy' => 3.0,
        'comment' => 1.5,
        'view' => 0.1,
        'offset_hours' => 2,
        'exponent' => 1.5,
        'active_days' => 7,
        'duplicate_penalty' => 0.25,
        'batch_size' => 500,
    ],

];
