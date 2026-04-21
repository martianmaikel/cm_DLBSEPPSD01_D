<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Social Media Posting
    |--------------------------------------------------------------------------
    |
    | Automated posting of events and daily briefings to social media channels
    | (Threads, Facebook Pages, Telegram). Each channel is configured in the
    | social_channels table with its own credentials and preferences.
    |
    */

    'enabled' => env('SOCIAL_POSTING_ENABLED', false),

    // Event relevance gate: only events meeting BOTH criteria get posted
    'relevance' => [
        'min_weighted_severity' => (float) env('SOCIAL_MIN_WEIGHTED_SEVERITY', 4.5),
        'min_factor_severity' => (int) env('SOCIAL_MIN_FACTOR_SEVERITY', 7),
        'allowed_statuses' => ['corroborated', 'confirmed'],
    ],

    // Events with occurred_at within this window get a "BREAKING:" prefix
    'breaking' => [
        'window_minutes' => (int) env('SOCIAL_BREAKING_WINDOW_MINUTES', 60),
    ],

    'rate_limits' => [
        'default_daily_limit' => 50,
        'publish_interval_seconds' => 5, // min gap between posts to same platform
    ],

    // Meta (Threads + Facebook) shared app credentials
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'token_refresh_days_before_expiry' => 14,
    ],

    'telegram' => [
        'parse_mode' => 'HTML',
    ],

    'bluesky' => [
        'service' => env('BLUESKY_SERVICE', 'https://bsky.social'),
    ],

    'x' => [
        'api_url' => 'https://api.twitter.com/2/tweets',
    ],

    // URLs used in post content
    'urls' => [
        'briefing' => env('APP_URL', 'https://clashmonitor.com') . '/briefing',
        'newsletter' => env('APP_URL', 'https://clashmonitor.com') . '/newsletter',
        'event' => env('APP_URL', 'https://clashmonitor.com') . '/event',
    ],

];
