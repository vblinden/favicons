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

    'max_html_bytes' => (int) env('FAVICONS_MAX_HTML_BYTES', 524288),

    'max_icon_bytes' => (int) env('FAVICONS_MAX_ICON_BYTES', 2097152),

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
    |
    | Responses always revalidate (max-age=0, must-revalidate) so DELETE
    | refresh is visible on the next request. stale_while_revalidate allows
    | soft-serving while a revalidation is in flight.
    |
    */

    'stale_while_revalidate' => (int) env('FAVICONS_STALE_WHILE_REVALIDATE', 86400),

    'variant_cache_seconds' => (int) env('FAVICONS_VARIANT_CACHE_SECONDS', 86400),

    'ttl_seconds' => (int) env('FAVICONS_TTL_SECONDS', 2592000),

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
    | First-fetch Rate Limit
    |--------------------------------------------------------------------------
    |
    | Cold cache misses that trigger an outbound crawl are limited per IP.
    |
    */

    'fetch_max_attempts' => (int) env('FAVICONS_FETCH_MAX_ATTEMPTS', 30),

    'fetch_decay_seconds' => (int) env('FAVICONS_FETCH_DECAY_SECONDS', 60),

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
