<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk used to persist resolved favicon masters.
    |
    */

    'disk' => env('FAVICONS_DISK', 'favicons'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */

    'user_agent' => env(
        'FAVICONS_USER_AGENT',
        'FaviconsBot/1.0 (+https://favicons.test; favicon fetcher)',
    ),

    'timeout' => (int) env('FAVICONS_TIMEOUT', 5),

    'connect_timeout' => (int) env('FAVICONS_CONNECT_TIMEOUT', 3),

    'max_redirects' => (int) env('FAVICONS_MAX_REDIRECTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Image Sizes
    |--------------------------------------------------------------------------
    */

    'default_size' => (int) env('FAVICONS_DEFAULT_SIZE', 32),

    'min_size' => 16,

    'max_size' => 512,

    'fallback_size' => 64,

    /*
    |--------------------------------------------------------------------------
    | Star Avatars Fallback
    |--------------------------------------------------------------------------
    |
    | When a site has no usable favicon, fetch a deterministic PNG from
    | Star Avatars before generating a local letter tile.
    |
    */

    'staravatars' => [
        'enabled' => (bool) env('FAVICONS_STARAVATARS_ENABLED', true),
        'base_url' => env('FAVICONS_STARAVATARS_BASE_URL', 'https://staravatars.com'),
        'size' => (int) env('FAVICONS_STARAVATARS_SIZE', 64),
        'shape' => env('FAVICONS_STARAVATARS_SHAPE', 'rounded'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Caching
    |--------------------------------------------------------------------------
    */

    'cache_max_age' => (int) env('FAVICONS_CACHE_MAX_AGE', 604800),

    'stale_while_revalidate' => (int) env('FAVICONS_STALE_WHILE_REVALIDATE', 86400),

    /*
    |--------------------------------------------------------------------------
    | Refresh Rate Limit
    |--------------------------------------------------------------------------
    |
    | Clients may force-refresh a domain this many times per rolling window
    | (per IP + domain).
    |
    */

    'refresh_max_attempts' => (int) env('FAVICONS_REFRESH_MAX_ATTEMPTS', 5),

    'refresh_decay_seconds' => (int) env('FAVICONS_REFRESH_DECAY_SECONDS', 604800),

    /*
    |--------------------------------------------------------------------------
    | Fetch Lock
    |--------------------------------------------------------------------------
    */

    'fetch_lock_seconds' => (int) env('FAVICONS_FETCH_LOCK_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Leaderboard
    |--------------------------------------------------------------------------
    */

    'leaderboard_limit' => (int) env('FAVICONS_LEADERBOARD_LIMIT', 50),

];
