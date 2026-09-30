<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Game assets
    |--------------------------------------------------------------------------
    |
    | Clash of Clans assets, used unmodified and only to identify game content (specs/18 §2).
    | Every URL goes through GameAssetResolver, so this file is the whole switchboard.
    |
    */

    // Kill switch: false → our own placeholders everywhere and no game asset is served (18 §2.1 (7)).
    'enabled' => (bool) env('ASSETS_ENABLED', true),

    // Active pack under game/{version}/. Null until the first pack ships (P2-05): placeholders only.
    'pack_version' => env('ASSETS_PACK_VERSION'),

    // The committed manifest the resolver reads at runtime; {version} is replaced (specs/10 §11.2).
    'manifest_path' => resource_path('game-assets/{version}/manifest.json'),

    // Public origin serving the game/ prefix (same cookieless CDN as media, resizing off).
    'cdn_url' => env('ASSETS_CDN_URL', env('MEDIA_CDN_URL')),

    // Disk the pack is published to, and its bucket prefix.
    'disk' => env('ASSETS_DISK', 'media'),
    'prefix' => 'game',

    'cache_control' => 'public, max-age=31536000, immutable',

    // One publish per version at a time; the lock expires on its own if a run dies.
    'publish_lock_seconds' => 1800,

    // File types a pack may contain, by real signature.
    'mimes' => [
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

    // Hosts allowed for URLs the CoC API hands us (clan badges, league icon fallback), https only.
    'remote_hosts' => ['api-assets.clashofclans.com'],

    // Clan badge sizes as named by the API's badgeUrls (specs/09 §8).
    'badge_sizes' => ['small', 'medium', 'large'],

];
