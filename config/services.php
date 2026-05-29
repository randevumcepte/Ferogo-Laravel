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

    'google_maps_key' => env('GOOGLE_MAPS_API_KEY'),

    /*
     * Voice Telekom SMS — OTP / bilgilendirme.
     * smsvt.voicetelekom.com:9587 (HTTP) ya da :9588 (HTTPS)
     * Auth: HTTP Basic (username:password)
     */
    'voicetelekom' => [
        'host'       => env('VOICETELEKOM_HOST', 'smsvt.voicetelekom.com'),
        'port'       => env('VOICETELEKOM_PORT', 9587),
        'username'   => env('VOICETELEKOM_USERNAME'),
        'password'   => env('VOICETELEKOM_PASSWORD'),
        'sender'     => env('VOICETELEKOM_SENDER', 'FEROGO'),
        'enabled'    => env('VOICETELEKOM_ENABLED', false),
        // dakika — sms/create min 60. Eski isim VOICETELEKOM_OTP_VALIDITY de geri uyumlu çalışır.
        'validity'   => (int) env('VOICETELEKOM_VALIDITY', env('VOICETELEKOM_OTP_VALIDITY', 60)),
        'commercial' => (bool) env('VOICETELEKOM_COMMERCIAL', false),
    ],

];
