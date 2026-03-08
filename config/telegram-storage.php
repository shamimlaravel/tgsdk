<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Channels
    |--------------------------------------------------------------------------
    |
    | List of Telegram channel IDs or usernames used for file storage.
    | Files will be distributed across these channels based on the
    | configured rotation strategy.
    |
    */

    'channels' => explode(',', env('TELEGRAM_STORAGE_CHANNELS', '')),

    /*
    |--------------------------------------------------------------------------
    | Channel Rotation Strategy
    |--------------------------------------------------------------------------
    |
    | Determines how files are distributed across configured channels.
    | Supported: "round-robin", "least-used", "capacity-aware"
    |
    */

    'rotation_strategy' => env('TELEGRAM_STORAGE_ROTATION', 'round-robin'),

    /*
    |--------------------------------------------------------------------------
    | Chunking Configuration
    |--------------------------------------------------------------------------
    |
    | Files exceeding the chunk_threshold will be split into chunks of
    | chunk_size bytes each. Auto-adjusts for premium accounts.
    |
    */

    'chunk_threshold' => (int) env('TELEGRAM_STORAGE_CHUNK_THRESHOLD', 1_950_000_000), // ~1.95 GB

    'chunk_size' => (int) env('TELEGRAM_STORAGE_CHUNK_SIZE', 1_950_000_000), // ~1.95 GB

    'chunk_compression' => (bool) env('TELEGRAM_STORAGE_CHUNK_COMPRESSION', false),

    'chunk_encryption' => (bool) env('TELEGRAM_STORAGE_CHUNK_ENCRYPTION', false),

    'chunk_encryption_key' => env('TELEGRAM_STORAGE_ENCRYPTION_KEY'),

    'chunk_verify' => (bool) env('TELEGRAM_STORAGE_CHUNK_VERIFY', true),

    'upload_stall_timeout' => (int) env('TELEGRAM_STORAGE_STALL_TIMEOUT', 30), // minutes

    /*
    |--------------------------------------------------------------------------
    | Temp Storage
    |--------------------------------------------------------------------------
    |
    | Temporary directory used for staging files before upload and chunk
    | splitting. The Python worker must have access to this path.
    |
    */

    'temp_path' => env('TELEGRAM_STORAGE_TEMP_PATH', storage_path('app/telegram-tmp')),

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis connection and queue key used to communicate with the Python
    | upload worker.
    |
    */

    'redis' => [
        'connection' => env('TELEGRAM_STORAGE_REDIS_CONNECTION', 'default'),
        'queue_key' => env('TELEGRAM_STORAGE_REDIS_QUEUE', 'telegram_upload_queue'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Callback
    |--------------------------------------------------------------------------
    |
    | URL and shared HMAC secret the Python worker uses to report upload
    | results back to the Laravel application.
    |
    */

    'worker_callback_url' => env('TELEGRAM_STORAGE_CALLBACK_URL', '/telegram-storage/callback'),

    'worker_callback_secret' => env('TELEGRAM_STORAGE_CALLBACK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Download / Streaming
    |--------------------------------------------------------------------------
    |
    | Configuration for the streaming proxy endpoint that serves files
    | stored in Telegram back to users.
    |
    */

    'download' => [
        'route_prefix' => env('TELEGRAM_STORAGE_ROUTE_PREFIX', 'tg-stream'),
        'middleware' => ['web'],
        'signed_urls' => (bool) env('TELEGRAM_STORAGE_SIGNED_URLS', false),
        'url_ttl' => (int) env('TELEGRAM_STORAGE_URL_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    |
    | Optional CDN layer placed in front of the streaming proxy.
    | When enabled, generated URLs will use the CDN base URL.
    |
    */

    'cdn' => [
        'enabled' => (bool) env('TELEGRAM_STORAGE_CDN_ENABLED', false),
        'base_url' => env('TELEGRAM_STORAGE_CDN_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pyrogram / Telegram API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and settings for the Python Pyrogram worker that handles
    | the actual file upload to Telegram via MTProto.
    |
    */

    'pyrogram' => [
        'api_id' => (int) env('TELEGRAM_API_ID', 0),
        'api_hash' => env('TELEGRAM_API_HASH', ''),
        'session_name' => env('TELEGRAM_SESSION_NAME', 'telegram_storage'),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'concurrency' => (int) env('TELEGRAM_UPLOAD_CONCURRENCY', 3),

        /*
        |----------------------------------------------------------------------
        | Multi-Account Session Pooling
        |----------------------------------------------------------------------
        |
        | Configure multiple Pyrogram sessions for parallel uploads and
        | rate limit isolation. Each entry should contain:
        | api_id, api_hash, session_name, bot_token (optional), is_premium (bool)
        |
        */

        'sessions' => [],

        'session_strategy' => env('TELEGRAM_SESSION_STRATEGY', 'round-robin'), // round-robin, least-busy
    ],

];
