<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SEO defaults
    |--------------------------------------------------------------------------
    |
    | Used by the root view when a controller passes no PageMeta (specs/06 §2, specs/17 §6).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Health
    |--------------------------------------------------------------------------
    |
    | GET /health for uptime monitoring (specs/03 NFR-OBS-4). A required check that is down turns
    | the response into a 503; anything else down or degraded reports "degraded" with a 200.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    |
    | Named limiters from specs/04 §4, keyed on ip + email. The HIBP range lookup behind
    | Password::uncompromised() fails open after `hibp_timeout` seconds.
    |
    */

    'auth' => [
        'login_per_minute' => 5,
        'login_per_hour' => 20,
        'password_reset_per_hour' => 3,
        // Per-IP ceilings, so rotating the email cannot turn one client into unlimited hash work.
        'login_per_ip_per_minute' => 30,
        'password_reset_per_ip_per_hour' => 20,
        'min_password_length' => 10,
        'hibp_timeout' => 2,
    ],

    // Key for hashing IPs before they are stored (last_login_ip_hash, later session ip_hash).
    'ip_hash_salt' => env('IP_HASH_SALT') ?: env('APP_KEY'),

    'health' => [
        'required' => ['database', 'queue', 'storage', 'scheduler'],
        // Oldest pending job per queue before the queue reads as degraded, in seconds (specs/20 §6).
        'queue_max_wait' => ['high' => 300, 'default' => 1800],
        // The scheduler writes a heartbeat every minute; older than this it is down (specs/20 §6).
        'heartbeat_max_age' => 300,
        // platform:check-health runs every 5 min; a missed run lets the result lapse to "unknown".
        'external_ttl' => 900,
        // Kept far above heartbeat_max_age: an expired heartbeat reads as unknown, not down.
        'heartbeat_ttl' => 604800,
        // The storage probe is a HEAD on this key (media disk); it need not exist.
        'storage_probe_key' => 'health/.probe',
        // Own store, so a database outage cannot break the limiter or cost a write per ping.
        'rate_limit_store' => env('HEALTH_RATE_LIMIT_STORE', 'file'),
        'rate_limit_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request logging
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy IPs/CIDRs (the CDN edge's published ranges in staging and production),
    | so request()->ip(), rate limits and logs see the client, not the edge. Empty trusts none.
    |
    */

    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

    'logging' => [
        'request_id_header' => 'X-Request-Id',
        // Routes (by name) left out of the per-request summary line.
        'skip_routes' => ['health'],
    ],

    'seo' => [
        // Same line as the Home page: honest about what exists today (antislop R-38).
        'default_description' => 'Base layouts, verified player cards and clan recruitment for Clash of Clans players. The first features are on the way.',
        'default_og_image' => null,
    ],

];
