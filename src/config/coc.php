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

    // The admin dashboard's API panel (FR-ADMIN-5): hours of `coc_api_requests` it sums.
    'health' => [
        'window_hours' => 24,
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

    // Ownership disputes (specs/13 §5 guardrails).
    'disputes' => [
        // Disputes a user may have running at once.
        'max_open_per_user' => 2,
        // Denied disputes after which a user may not open another for `bar_days`.
        'bar_after_denials' => 2,
        'bar_days' => 90,
        // Days the holder has to answer before the dispute goes to the admins anyway.
        'holder_response_days' => 7,
        // Days a dispute waits on the claimant before it is withdrawn.
        'claimant_inactive_days' => 30,
        // Days before a claimant may dispute the same tag again after withdrawing.
        'reopen_cooldown_days' => 30,
        // Evidence images per submission (specs/10: 3 per item).
        'evidence_max' => 3,
        // Longest statement or note, in characters (specs/07: reason ≤ 1000).
        'text_max' => 1000,
    ],

    // Background sync of verified accounts (specs/09 §6, specs/20 §2–3, §6). Seconds unless named.
    'sync' => [
        // How often each tier is synced; a frozen resource retries this often until it stops.
        'tiers' => [
            'hot' => (int) env('COC_SYNC_HOT', 7200),
            'warm' => (int) env('COC_SYNC_WARM', 43200),
            'cold' => (int) env('COC_SYNC_COLD', 259200),
            'frozen' => (int) env('COC_SYNC_FROZEN', 604800),
        ],
        // Owner activity windows: active within `hot_active_days` is hot, within `warm_active_days` warm.
        'hot_active_days' => 7,
        'warm_active_days' => 30,
        // Most jobs one `coc:sync-accounts` run dispatches; the background budget may allow fewer.
        'batch_size' => (int) env('COC_SYNC_BATCH', 100),
        'queue' => 'sync',
        // A queued account is not due again for this long, so a backlog never queues it twice. Above
        // the job's three tries with their backoff (60 + 300 + 900 s).
        'claim_seconds' => 1800,
        // First retry after a failed sync, doubled per failure and capped at the tier's interval.
        'backoff_base' => 300,
        // 404s in a row before an account shows as stale and its owner hears about it.
        'not_found_stale' => 3,
        // Failures in a row before a resource freezes, and failed weekly retries before it stops.
        'frozen_after' => 5,
        'frozen_max_attempts' => 4,
        // The sync success rate on the admin pages, and the line under which it is flagged.
        'success_window_minutes' => 30,
        'success_alert' => 0.9,
    ],

    // The account page (P2-04). Data older than `stale_hours` shows as stale; stat deltas compare
    // with the newest snapshot at least `delta_days` old.
    'display' => [
        'stale_hours' => 168,
        'delta_days' => 7,
    ],

    'fake' => [
        // Recorded responses, one file per tag (specs/19 §6).
        'fixtures_path' => base_path('tests/Fixtures/coc'),
        // The in-game token the fake accepts for every fixture player (local development only).
        'valid_token' => env('COC_FAKE_VALID_TOKEN', 'local-dev-token'),
    ],

];
