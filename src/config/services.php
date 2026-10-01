<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Cloudflare Turnstile on registration and the reset-link form (FR-AUTH-11, specs/11). Local
    // and test runs default to Cloudflare's published always-pass test keys; anywhere else the keys
    // must come from the environment, and a missing secret fails closed.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY', in_array(env('APP_ENV'), ['local', 'testing'], true) ? '1x00000000000000000000AA' : null),
        'secret' => env('TURNSTILE_SECRET_KEY', in_array(env('APP_ENV'), ['local', 'testing'], true) ? '1x0000000000000000000000000000000AA' : null),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout' => 3,
        // Cloudflare's tokens are at most 2,048 characters.
        'max_token_length' => 2048,
    ],

];
