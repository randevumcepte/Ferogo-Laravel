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
     * WebRTC sesli görüşme için ICE sunucu listesi.
     * STUN: NAT keşfi (default'ta Google + Cloudflare public STUN, parasız).
     * TURN: P2P kurulamadığında relay — production için ŞART, özellikle
     *       Türk mobil operatörleri simetrik NAT kullanır.
     *
     * Kendi coturn'ünü sunucuya kur (Ubuntu, FreePBX makinesinde):
     *   apt install coturn
     *   /etc/turnserver.conf'a: realm=appnew.randevumcepte.com.tr,
     *     listening-port=3478, tls-listening-port=5349,
     *     user=ferogo:GIZLISIFRE, fingerprint, lt-cred-mech
     *   ufw allow 3478, 5349, 49152:65535/udp
     *
     * .env örnek (FreePBX sunucu IP: 89.252.140.61):
     *   TURN_URLS="turn:89.252.140.61:3478,turn:89.252.140.61:3478?transport=tcp"
     *   TURN_USERNAME=ferogo
     *   TURN_CREDENTIAL=GIZLISIFRE
     */
    'webrtc' => [
        'stun_urls' => array_filter(array_map('trim', explode(',', env('STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302,stun:stun.cloudflare.com:3478')))),
        'turn_urls' => array_filter(array_map('trim', explode(',', env('TURN_URLS', '')))),
        'turn_username'   => env('TURN_USERNAME'),
        'turn_credential' => env('TURN_CREDENTIAL'),
    ],

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
