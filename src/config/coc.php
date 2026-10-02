<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clash of Clans API
    |--------------------------------------------------------------------------
    |
    | Everything goes through the CocApiClient contract (specs/09 §1). `fake` serves the recorded
    | fixtures and is the default outside production; production must run `http`, and resolving
    | the fake there throws. `http` is wrapped as Cached(Throttled(Http)) (specs/09 §1). The sync
    | and key_rotation groups of specs/09 §11 arrive with P2-09 and P2-08.
    |
    */

    'driver' => env('COC_API_DRIVER', 'fake'),

    'base_url' => env('COC_API_BASE_URL', 'https://api.clashofclans.com/v1'),

    // One key per egress IP plus spares, comma-separated (specs/09 §3). Never logged: a key is
    // identified by the first characters of its sha256 only.
    'tokens' => array_values(array_filter(array_map('trim', explode(',', (string) env('COC_API_TOKENS', ''))))),

    // Seconds (specs/09 §7).
    'timeouts' => [
        'connect' => 5,
        'total' => 10,
    ],

    'key_pool' => [
        // A key answering 403 stays out of rotation this long at most; a successful
        // coc:check-health probe (every 5 min) brings it back sooner.
        'unhealthy_ttl' => 3600,
        // The round-robin cursor; losing it only restarts the rotation.
        'cursor_ttl' => 86400,
        // Hex characters of the token's sha256 used as the key id in logs and status.
        'id_length' => 8,
    ],

    'log' => [
        // A malformed 200 is logged with its body (specs/23 §5), cut to this many bytes.
        'malformed_body_bytes' => 2048,
    ],

    // Self-imposed budget, well below any observed ceiling (specs/09 §4). Background calls may use
    // only (1 - interactive_share) of the global budget, so the rest stays for people waiting.
    'rate' => [
        'global_per_second' => 10,
        'global_per_minute' => 500,
        'per_key_per_second' => 5,
        'interactive_share' => 0.3,
    ],

    // specs/09 §7. Opens after `consecutive_failures` in a row, or when more than `error_rate` of
    // at least `min_samples` calls in the last `window` seconds failed (counted in buckets of
    // `bucket_seconds`). While open, one probe call is let through every `probe_interval` seconds.
    'circuit' => [
        'consecutive_failures' => 10,
        'error_rate' => 0.5,
        'window' => 120,
        'min_samples' => 20,
        'bucket_seconds' => 10,
        'probe_interval' => 60,
        // Ceiling on a maintenance Retry-After; past it the probe decides.
        'max_open_seconds' => 3600,
    ],

    // Seconds (specs/09 §5, specs/21 §3).
    'cache' => [
        'player_ttl' => 300,
        // A background fetch writes the same key for longer, so a view right after a sync is free.
        'player_sync_ttl' => 1800,
        'clan_ttl' => 900,
        'negative_ttl' => 600,
        // The last good payload, served marked stale while the API is unavailable.
        'stale_ttl' => 86400,
    ],

    'request_log' => [
        // `coc_api_requests` rows older than this are pruned (specs/07).
        'retention_days' => 7,
    ],

    // Attaching and verifying accounts (specs/04 §4, specs/09 §9, specs/23 §2). Attach counts each
    // distinct tag once an hour, so looking a tag up and then attaching it is one attempt.
    'accounts' => [
        'attach_per_hour' => 5,
        'verify_per_hour' => 5,
        // Attached accounts at which a user is flagged for review (badge farming).
        'anomaly_accounts' => 20,
        // A flagged user is flagged again at most this often while above the threshold.
        'anomaly_reflag_hours' => 24,
    ],

    'fake' => [
        // Recorded responses, one file per tag (specs/19 §6).
        'fixtures_path' => base_path('tests/Fixtures/coc'),
        // The in-game token the fake accepts for every fixture player (local development only).
        'valid_token' => env('COC_FAKE_VALID_TOKEN', 'local-dev-token'),
    ],

];
