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

    'max_html_bytes' => (int) env('FAVICONS_MAX_HTML_BYTES', 2097152),

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
    | Cached image responses may be stored by browsers and CDNs for max_age
    | seconds. stale_while_revalidate allows soft-serving while a revalidation
    | is in flight. Force-refresh remains available via DELETE /r/{domain}.
    |
    */

    'http_max_age' => (int) env('FAVICONS_HTTP_MAX_AGE', 86400),

    'stale_while_revalidate' => (int) env('FAVICONS_STALE_WHILE_REVALIDATE', 86400),

    /*
    | When true and the queue is not sync, cold cache misses soft-serve a
    | letter tile immediately and resolve the real icon in a background job.
    */

    'async_cold_miss' => (bool) env('FAVICONS_ASYNC_COLD_MISS', true),

    /*
    | How many top-scoring icon candidates to download concurrently.
    */

    'parallel_candidates' => (int) env('FAVICONS_PARALLEL_CANDIDATES', 4),

    /*
    | Maximum domains accepted by the batch endpoint.
    */

    'batch_max_domains' => (int) env('FAVICONS_BATCH_MAX_DOMAINS', 50),

    /*
    | Bump this when served image bytes change without fetched_at changing
    | (for example an ICO decoder fix) so browsers do not keep a 304 of the
    | previous PNG.
    */

    'image_revision' => (int) env('FAVICONS_IMAGE_REVISION', 4),

    /*
    | Target edge length when rasterizing SVG masters with Imagick.
    */

    'svg_raster_size' => (int) env('FAVICONS_SVG_RASTER_SIZE', 128),

    'variant_cache_seconds' => (int) env('FAVICONS_VARIANT_CACHE_SECONDS', 86400),

    'ttl_seconds' => (int) env('FAVICONS_TTL_SECONDS', 2592000),

    /*
    | Fallback masters (Star Avatars / letter tiles) are retried sooner so a
    | temporary fetch failure does not stick for the full cache TTL.
    */

    'fallback_ttl_seconds' => (int) env('FAVICONS_FALLBACK_TTL_SECONDS', 86400),

    /*
    | Letter tiles are generated locally when Star Avatars is unreachable.
    | Retry sooner so a transient outage does not stick for a full day.
    */

    'letter_fallback_ttl_seconds' => (int) env('FAVICONS_LETTER_FALLBACK_TTL_SECONDS', 300),

    /*
    | SHA-1 digests of icon bodies we refuse to serve (raw or PNG-normalized).
    | The Laravel skeleton favicon.ico is common on apps that only ship an SVG
    | logo; keeping it makes unrelated sites look like Laravel.
    */

    'rejected_content_sha1' => [
        '4de5db574e6c589ef763ce57c4061f2269648ca4',
        '4ef41ff73f045d319c84db457971e316d2c5360e',
    ],

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
    | How long a request waits to acquire the per-domain fetch lock before
    | giving up with a 429. Kept short so cold-miss stampedes fail fast
    | instead of piling up PHP workers behind a single slow crawl.
    */

    'fetch_lock_wait_seconds' => (int) env('FAVICONS_FETCH_LOCK_WAIT_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Leaderboard
    |--------------------------------------------------------------------------
    */

    'leaderboard_limit' => (int) env('FAVICONS_LEADERBOARD_LIMIT', 50),

    'leaderboard_cache_seconds' => (int) env('FAVICONS_LEADERBOARD_CACHE_SECONDS', 60),

    /*
    | How many top leaderboard domains to warm on the scheduled command.
    */

    'warm_limit' => (int) env('FAVICONS_WARM_LIMIT', 50),

];
