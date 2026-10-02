<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clash of Clans API
    |--------------------------------------------------------------------------
    |
    | Everything goes through the CocApiClient contract (specs/09 §1). `fake` serves the recorded
    | fixtures and is the default outside production; production must run `http`, and resolving
    | the fake there throws. The cache, rate, circuit, sync and key_rotation groups of specs/09 §11
    | arrive with the tasks that read them (P2-07, P2-08, P2-09).
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

    'fake' => [
        // Recorded responses, one file per tag (specs/19 §6).
        'fixtures_path' => base_path('tests/Fixtures/coc'),
        // The in-game token the fake accepts for every fixture player (local development only).
        'valid_token' => env('COC_FAKE_VALID_TOKEN', 'local-dev-token'),
    ],

];
