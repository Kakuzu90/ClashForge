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

];
