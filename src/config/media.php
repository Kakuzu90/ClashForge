<?php

$mb = 1024 * 1024;

// Width-bound variants shared by the gallery-style collections (specs/10 §5).
$galleryVariants = [
    'full' => ['width' => 1600],
    'card' => ['width' => 800],
    'thumb' => ['width' => 320],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The disk lives in config/filesystems.php; nothing else names the storage provider
    | (specs/10 §2.1). `cdn_url` is the public origin for the public/ prefix; `presign_host`
    | is the endpoint browsers can reach when it differs from the one the app uses.
    |
    */

    'disk' => env('MEDIA_DISK', 'media'),
    'cdn_url' => env('MEDIA_CDN_URL'),
    'presign_host' => env('MEDIA_PRESIGN_HOST'),

    // Presigned PUT lifetime in seconds (specs/10 §3).
    'intent_ttl' => 300,

    // After processing, the quarantine key is deleted again this many seconds after its PUT URL
    // expires, on this queue, so a late re-upload cannot linger.
    'quarantine_recheck_margin' => 60,
    'cleanup_queue' => 'low',

    // Private signed GET lifetime in seconds; cached for `signed_url_cache_margin` less (specs/10 §7).
    'signed_url_ttl' => 300,
    'signed_url_cache_margin' => 30,

    // Unattached rows expire after this many hours and are swept (specs/10 §3, §9).
    'pending_expiry_hours' => 24,

    // Cache-Control per visibility (specs/10 §7).
    'public_cache_control' => 'public, max-age=31536000, immutable',
    'private_cache_control' => 'private, no-store',

    // Longest filename accepted from the browser, and the length kept for display (specs/10 §4).
    'filename' => [
        'input_max_length' => 1000,
        'max_length' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing
    |--------------------------------------------------------------------------
    */

    'processing' => [
        // The media worker consumes this queue on the `media` queue connection (specs/20 §1).
        'queue' => 'media',
        'timeout' => 900,
        // Failures (exceptions or timeouts) before giving up, within the retry window. A full
        // temp volume releases the job instead and does not count (specs/10 §10).
        'max_exceptions' => 2,
        'retry_window_minutes' => 60,
        'backoff' => [60, 300],
        // Per-job temp directories live here; each is removed when its job ends or fails.
        'temp_dir' => storage_path('app/media-tmp'),
        // Pre-flight: release the job when free space would drop below this (specs/10 §10).
        'min_free_bytes' => 512 * $mb,
        'release_delay' => 60,
    ],

    'image' => [
        // Real MIME (from magic bytes) → stored extension (specs/10 §4).
        'mimes' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        // How the allowed types are named in messages to uploaders.
        'types_label' => 'JPEG, PNG or WebP',
        'max_width' => 6000,
        'max_height' => 6000,
        'min_width' => 200,
        'min_height' => 200,
        'webp_quality' => 82,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    |
    | `accepts_uploads` is false for collections whose processor has not shipped yet:
    | base_video (P3-02). Square variants are centre-cropped.
    |
    */

    'collections' => [
        'avatar' => [
            'kind' => 'image',
            'visibility' => 'public',
            'max_bytes' => 2 * $mb,
            'accepts_uploads' => true,
            'variants' => [
                'full' => ['width' => 512, 'square' => true],
                'card' => ['width' => 128, 'square' => true],
                'thumb' => ['width' => 48, 'square' => true],
            ],
        ],
        'account_image' => [
            'kind' => 'image',
            'visibility' => 'public',
            'max_bytes' => 5 * $mb,
            'accepts_uploads' => true,
            'variants' => $galleryVariants,
        ],
        'base_screenshot' => [
            'kind' => 'image',
            'visibility' => 'public',
            'max_bytes' => 5 * $mb,
            'accepts_uploads' => true,
            'variants' => $galleryVariants,
        ],
        'portfolio' => [
            'kind' => 'image',
            'visibility' => 'public',
            'max_bytes' => 5 * $mb,
            'accepts_uploads' => true,
            'variants' => [
                'card' => ['width' => 800],
                'thumb' => ['width' => 320],
            ],
        ],
        'base_video' => [
            'kind' => 'video',
            'visibility' => 'public',
            'max_bytes' => 100 * $mb,
            'accepts_uploads' => false,
            'variants' => [],
        ],
        'evidence' => [
            'kind' => 'image',
            'visibility' => 'private',
            'max_bytes' => 5 * $mb,
            'accepts_uploads' => true,
            // Dispute evidence (P2-16): staff read it full size, its uploader sees the thumbnail.
            'variants' => [
                'full' => ['width' => 1600],
                'thumb' => ['width' => 320],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    |
    | The cleanup jobs of specs/10 §9. Quarantined media is never touched by any of them.
    |
    */

    'lifecycle' => [
        // Soft-deleted media keeps its objects this long before media:purge-deleted removes them.
        'purge_after_days' => 7,
        // media:retry-failed re-runs `processing_error` failures younger than the window, until
        // this many processing attempts in total have been made.
        'retry_max_attempts' => 3,
        'retry_window_hours' => 24,
        // Media ids per DeleteMediaObjectsJob, and per claim or retry query. Sized so a batch
        // (about four keys a row) deletes well inside the job's 75 s timeout.
        'batch_size' => 50,
        // A `deleting` row this old lost its deletion job; the sweeper queues it again.
        'stale_deleting_minutes' => 60,
        // A key with no row is deleted only when seen again at least this long after first sight.
        'reconcile_confirm_after_hours' => 24,
    ],

    'rate_limits' => [
        'intents_per_hour' => 30,
    ],

];
