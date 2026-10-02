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

    // Active pack under game/{version}/. Null → placeholders only. Pack 1 shipped in P2-05.
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

    // File types a pack may contain, by real signature, and the extension each must carry.
    'mimes' => [
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

    // Largest file a pack may contain. Files are never resized (specs/10 §11.4): a bigger one
    // stays out of the pack until a smaller original is found, and shows the placeholder.
    'max_bytes' => 1024 * 1024,

    // Hosts allowed for URLs the CoC API hands us (clan badges, league icon fallback), https only.
    'remote_hosts' => ['api-assets.clashofclans.com'],

    // Clan badge sizes as named by the API's badgeUrls (specs/09 §8).
    'badge_sizes' => ['small', 'medium', 'large'],

    // Display order of the catalogue, by pack file name without the extension (`{folder}/{slug}`).
    // Read by the progression grids (P2-04); a test keeps pack 1 in step with these lists.
    // Heroes equipment in order
    'heroes_equipments' => [
        'barbarian-king' => [
            'barbarian-puppet',
            'rage-vial',
            'earthquake-boots',
            'vampstache',
            'giant-gauntlet',
            'spiky-ball',
            'snake-bracelet',
            'stick-horse',
        ],
        'archer-queen' => [
            'archer-puppet',
            'invisibility-vial',
            'giant-arrow',
            'healer-puppet',
            'frozen-arrow',
            'magic-mirror',
            'action-figure',
            'monolith-arrow',
        ],
        'minion-prince' => [
            'henchmen-puppet',
            'dark-orb',
            'metal-pants',
            'noble-iron',
            'meteor-staff',
            'dark-crown',
        ],
        'grand-warden' => [
            'eternal-tome',
            'life-gem',
            'rage-gem',
            'healing-tome',
            'heroic-torch',
            'fireball',
            'lavaloon-puppet',
        ],
        'royal-champion' => [
            'seeking-shield',
            'royal-gem',
            'hog-rider-puppet',
            'haste-vial',
            'rocket-spear',
            'electro-boots',
            'frost-flake',
        ],
        'dragon-duke' => [
            'fire-heart',
            'flame-blower',
            'stun-blaster',
            'electro-fangs',
            'rocket-backpack',
            'revenge-deck',
        ],
    ],

    // Heroes in order
    'heroes' => [
        'barbarian-king',
        'archer-queen',
        'minion-prince',
        'grand-warden',
        'royal-champion',
        'dragon-duke',
    ],

    // Units in order
    'units' => [
        'elixir' => [
            'barbarian',
            'archer',
            'giant',
            'goblin',
            'wall-breaker',
            'balloon',
            'wizard',
            'healer',
            'dragon',
            'pekka',
            'baby-dragon',
            'miner',
            'electro-dragon',
            'yeti',
            'dragon-rider',
            'electro-titan',
            'root-rider',
            'thrower',
            'meteor-golem',
        ],
        'dark-elixir' => [
            'minion',
            'hog-rider',
            'valkyrie',
            'golem',
            'witch',
            'lava-hound',
            'bowler',
            'ice-golem',
            'headhunter',
            'apprentice-warden',
            'druid',
            'furnace',
            'ruin-witch',
        ],
    ],

    // Spells in order
    'spells' => [
        'elixir' => [
            'lightning',
            'healing',
            'rage',
            'jump',
            'freeze',
            'clone',
            'invisibility',
            'recall',
            'revive',
            'totem',
        ],
        'dark-elixir' => [
            'poison',
            'earthquake',
            'haste',
            'skeleton',
            'bat',
            'overgrowth',
            'ice-block',
            'angry',
        ],
    ],

    // Pets in order
    'pets' => [
        'lassi',
        'electro-owl',
        'mighty-yak',
        'unicorn',
        'frosty',
        'diggy',
        'poison-lizard',
        'phoenix',
        'spirit-fox',
        'angry-jelly',
        'sneezy',
        'greedy-raven',
    ],

    // Siege machines in order
    'siege-machines' => [
        'wall-wrecker',
        'battle-blimp',
        'stone-slammer',
        'siege-barracks',
        'log-launcher',
        'flame-flinger',
        'battle-drill',
        'troop-launcher',
        'sky-wagon',
    ],

    // Guardians in order
    // Note: excluded from the unit list display due to no API data.
    'guardians' => [
        'longshot',
        'smasher',
        'logger',
    ],

    // Excluded units: these should not be display in the unit list.
    'excluded_units' => [
        'super-barbarian',
        'super-archer',
        'sneaky-goblin',
        'super-wall-breaker',
        'super-giant',
        'rocket-balloon',
        'super-wizard',
        'super-dragon',
        'inferno-dragon',
        'super-minion',
        'super-valkyrie',
        'super-witch',
        'ice-hound',
        'super-bowler',
        'super-miner',
        'super-yeti',
        'super-hog-rider',
        'longshot',
        'smasher',
        'logger',
    ],
];
