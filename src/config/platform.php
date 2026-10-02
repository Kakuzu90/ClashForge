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
        // A session ends this long after sign-in even while active (specs/04 §4); idle is session.lifetime.
        'absolute_session_days' => 30,
        'deletion_grace_days' => 30,
        'deletion_batch_size' => 100,
        'unverified_reminder_days' => 3,
        'unverified_warning_days' => 27,
        'unverified_purge_days' => 30,
        'unverified_warning_grace_days' => 3,
        'unverified_batch_size' => 100,
        'unverified_schedule_time' => '04:10',
        'unverified_mail_slow_seconds' => 10,
        // The `known_devices` cookie: how long a browser stays recognised, and how many accounts it
        // remembers (specs/11 "Authentication attacks").
        'known_device_days' => 365,
        'known_devices_max' => 10,
        // Request header the CDN fills with the visitor's country (sessions.country_code, FR-AUTH-7).
        'country_header' => env('COUNTRY_HEADER', 'CF-IPCountry'),
        // Current-password checks (confirm page, password change) per account and IP.
        'password_confirm_per_minute' => 5,
        'password_confirm_per_hour' => 20,
        // Registration (specs/04 §4, specs/11 "Spam and fake accounts"): accepted sign-ups per IP (a
        // typo does not count), every attempt per IP, verification resends per account, and the
        // honeypot form's minimum fill time and maximum age.
        'register_per_hour' => 3,
        'register_attempts_per_hour' => 20,
        'verify_resend_per_hour' => 3,
        'register_min_seconds' => 3,
        'register_max_form_age_minutes' => 120,
        // Verification links stay valid this long (FR-AUTH-3); email-change links too (FR-AUTH-8).
        'verification_link_minutes' => 60,
        // Email change (FR-AUTH-8): accepted requests and resends per account (typos are free),
        // and how many "taken address" notices each inbox gets an hour.
        'email_change_per_hour' => 3,
        'email_change_notice_per_hour' => 1,
        // Change links any one address receives an hour, from every account together.
        'email_change_links_per_address_per_hour' => 3,
        // Username change (FR-PROFILE-7): days between changes, and how long a released name stays
        // held for its old owner (the old profile URL redirects meanwhile).
        'username_change_days' => 30,
        'username_reservation_days' => 90,
        // Names nobody may register (FR-AUTH-1), matched exactly after lowercasing.
        'reserved_usernames' => [
            'about', 'account', 'accounts', 'admin', 'administrator', 'api', 'base', 'bases', 'clan', 'clans',
            'clashcommons', 'clash_commons', 'dashboard', 'dev', 'help', 'login', 'logout', 'mail', 'market',
            'me', 'mod', 'moderator', 'mods', 'notifications', 'null', 'official', 'privacy', 'recruit',
            'register', 'root', 'search', 'security', 'settings', 'staff', 'supercell', 'support', 'system',
            'team', 'terms', 'u', 'undefined', 'www',
        ],
        // Disposable-email domains (FR-AUTH-11): the committed list, and the monthly refresh's copy.
        'disposable_domains_file' => resource_path('blocklists/disposable-email-domains.txt'),
        'disposable_domains_refreshed' => storage_path('app/blocklists/disposable-email-domains.txt'),
        'disposable_domains_url' => 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf',
        // A download with fewer domains than this is treated as broken; the download's timeout.
        'disposable_domains_min' => 1000,
        'disposable_domains_timeout' => 30,
    ],

    // Key for hashing IPs before they are stored (last_login_ip_hash, sessions.ip_hash).
    'ip_hash_salt' => env('IP_HASH_SALT') ?: env('APP_KEY'),

    // platform:prune-operational-tables, daily (specs/20 §2). `coc_api_requests` uses
    // coc.request_log.retention_days; sessions use session.lifetime and auth.absolute_session_days.
    'prune' => [
        'failed_jobs_days' => 30,
    ],

    'health' => [
        // `coc` is never required: a game API outage degrades the platform, it does not take it down (NFR-AVAIL-2).
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

    // Profile field limits (FR-PROFILE-2, specs/07 `profiles`).
    'profile' => [
        'display_name_max' => 50,
        'bio_max' => 500,
        'languages_max' => 3,
        // Cache TTLs in seconds (specs/21 §3): `profile:{username}` and `user:{id}:privacy`.
        'cache_ttl' => 300,
        'privacy_cache_ttl' => 3600,
        // Meta description length for `/u/{username}`, cut from the bio (specs/17 §6).
        'meta_description_max' => 160,
    ],

    // Named limiters outside auth (specs/04 §4).
    'rate_limits' => [
        'global_write_per_minute' => 120,
        // Admin user list and audit log loads per staff member (the deferred rows count too).
        'admin_search_per_minute' => 60,
    ],

    'admin' => [
        // Rows per page in the admin tables, e.g. the audit log (FR-ADMIN-4).
        'per_page' => 50,
        // Latest audit entries about an account on the admin user detail.
        'audit_trail_limit' => 10,
        // Dashboard failed-jobs panel: the specs/20 §6 alert line, and how many job classes it lists.
        'failed_jobs_alert_per_hour' => 20,
        'failed_jobs_top_classes' => 5,
    ],

    'notifications' => [
        'email_per_day' => 10,
        'email_counter_ttl' => 86400,
        'unsubscribe_link_days' => 30,
        'email_slow_seconds' => 10,
        // Rows per page in the notification centre (FR-NOTIF-1).
        'per_page' => 25,
        // Seconds the bell's unread count stays cached, `notif:unread:{id}` (specs/21 §3).
        'unread_cache_ttl' => 60,
        // Retention (specs/16 §7).
        'prune_read_days' => 90,
        'prune_unread_days' => 180,
        'max_per_user' => 500,
    ],

    'security_log' => [
        // auth.permission_denied lines per account (or IP) and route per minute (specs/11 §3).
        'denials_per_minute' => 20,
    ],

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
