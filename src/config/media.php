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
        'unsupported_message' => 'This file type is not supported. Use a JPEG, PNG or WebP image.',
        'max_width' => 6000,
        'max_height' => 6000,
        'min_width' => 200,
        'min_height' => 200,
        'webp_quality' => 82,
    ],

    // Replay videos (specs/10 §4, §6): probed before any transcode, rewritten as one progressive
    // h264/aac mp4 plus a WebP poster.
    'video' => [
        // Declared type and extension at intent time.
        'mimes' => [
            'video/mp4' => 'mp4',
        ],
        'extensions' => ['mp4'],
        'types_label' => 'MP4',
        'unsupported_message' => 'This file type is not supported. Use an MP4 video.',
        // Real types (from magic bytes) the worker accepts: the ISO media family that one demuxer
        // reads. Anything else behind an .mp4 name is quarantined.
        'real_mimes' => [
            'video/mp4' => 'mp4',
            'video/x-m4v' => 'm4v',
            'video/quicktime' => 'mov',
        ],
        'video_codecs' => ['h264', 'hevc'],
        'audio_codecs' => ['aac', 'mp3'],
        // The only decoders ffmpeg may open on an upload (ffmpeg names mp3 `mp3float`), and on our
        // own output for the poster. Probes read headers only and decode nothing.
        'decoders' => ['h264', 'hevc', 'aac', 'mp3', 'mp3float'],
        'output_decoders' => ['h264', 'aac'],
        // Frames per second: input above this is refused; output is capped at `output_max_fps`.
        'max_frame_rate' => 120,
        'output_max_fps' => 60,
        'max_duration' => 60,
        // Input bound, either orientation, checked before transcoding.
        'max_long_side' => 3840,
        'max_short_side' => 2160,
        // Output: short side at most this, scaled down only.
        'output_short_side' => 720,
        'preset' => 'veryfast',
        'crf' => 26,
        // Second pass when the first output is over `max_output_bytes`; still over fails.
        'crf_retry' => 30,
        'max_output_bytes' => 40 * $mb,
        'audio_bitrate' => '96k',
        'audio_channels_max' => 2,
        // Poster frame position as a share of the duration.
        'poster_at' => 0.1,
        'poster_quality' => 82,
        // Wall-clock limit per process. The worst run (two probes of the input and output, two
        // transcodes, a poster) stays inside the job's `processing.timeout`, so the job is never
        // killed with ffmpeg still running; `ffmpeg_timelimit` (CPU seconds) bounds a child that
        // outlives its worker anyway.
        'probe_timeout' => 30,
        'transcode_timeout' => 360,
        'poster_timeout' => 60,
        'ffmpeg_timelimit' => 300,
        'nice' => 10,
        'ffmpeg' => 'ffmpeg',
        'ffprobe' => 'ffprobe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    |
    | `accepts_uploads` is false for collections whose processor has not shipped yet. Square
    | variants are centre-cropped. Video renditions are fixed (`video_720p` and `poster`) and
    | configured under `video`.
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
            'accepts_uploads' => true,
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
        // Replay video intents per user per 24 h window (from the first in it), on top of the
        // hourly limit (specs/24 R6).
        'video_intents_per_day' => 10,
    ],

    // System Health's media processing row (specs/20 §6): the p95 of the latest run's duration
    // for rows processed in the window, against the alert.
    'health' => [
        'processing_window_hours' => 24,
        'processing_p95_alert_seconds' => 180,
    ],

];
