<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'ai' => [
        // Which AIProviderInterface implementation to bind (see
        // AppServiceProvider::register()). Only 'mock' exists until a real
        // provider is chosen (PRD "Open Decisions").
        'provider' => env('AI_PROVIDER', 'mock'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        // Value Telegram must send back in the X-Telegram-Bot-Api-Secret-Token
        // header (set via setWebhook's secret_token param). See SECURITY.md
        // "Authentication/Authorization" and TELEGRAM_DESIGN.md "Webhook".
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),
    ],

];
