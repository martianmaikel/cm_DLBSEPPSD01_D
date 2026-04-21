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
        'options' => [
            'ConfigurationSetName' => env('SES_CONFIGURATION_SET'),
        ],
    ],

    'newsletter' => [
        'unsubscribe_mailbox' => env('NEWSLETTER_UNSUBSCRIBE_MAILBOX', 'unsubscribe@clashmonitor.com'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'briefing@clashmonitor.com'),
        'from_name' => env('MAIL_FROM_NAME', 'ClashMonitor'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rsshub' => [
        'base_url' => env('RSSHUB_BASE_URL', 'http://localhost:1200'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

    'acled' => [
        'email' => env('ACLED_EMAIL'),
        'password' => env('ACLED_PASSWORD'),
    ],

];
