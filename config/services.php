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

    /*
     * Sürücü ekosistemi ayarları.
     *
     *   enforce_packages=false yapmak → sürücü aktif paket olmadan da
     *   müsait olabilir, radar'a düşer, iş kabul eder. Test/QA için.
     *   Prod'da HER ZAMAN true olmalı — .env'de DRIVER_ENFORCE_PACKAGES=true.
     */
    'driver' => [
        'enforce_packages' => env('DRIVER_ENFORCE_PACKAGES', true),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    /*
     * Firebase Cloud Messaging — HTTP v1 API ile mobil push.
     * credentials: service account JSON'ın yolu (storage/app altı, git'e girmez).
     * enabled=false → PushService no-op çalışır (dev/QA; token kaydedilir ama gönderilmez).
     * Kurulum: Firebase Console → ferxgo → Project settings → Service accounts
     *          → Generate new private key → dosyayı FCM_CREDENTIALS_PATH'e koy.
     */
    'fcm' => [
        'enabled'     => (bool) env('FCM_HTTP_V1_ENABLED', false),
        'project_id'  => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS_PATH', 'storage/app/firebase-service-account.json'),
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
     * Yandex Maps — adres/işletme arama (Geosuggest) + koordinat (Geocoder).
     * İKİ AYRI ürün, İKİ AYRI anahtar (Yandex Developer Dashboard'da ayrı oluşturulur):
     *   - suggest_key  → "Geosuggest API"  (yaz→öner; koordinat DÖNMEZ, uri döner)
     *   - geocoder_key → "Geocoder (HTTP)" (seçilen uri/metin → lat/lon)
     * Kart zorunlu değil; Basic ücretsiz kota ile başlar. Anahtar boşsa GeoService
     * otomatik Photon'a (OSM) düşer — uygulama her hâlükârda çalışır.
     *
     * ll/spn: öneri bias (İzmir merkez). ll = "boylam,enlem" (lon,lat!).
     */
    'yandex' => [
        'suggest_key'  => env('YANDEX_SUGGEST_KEY'),
        'geocoder_key' => env('YANDEX_GEOCODER_KEY'),
        'lang'         => env('YANDEX_LANG', 'tr_TR'),
        'll'           => env('YANDEX_LL', '27.1428,38.4237'), // İzmir merkez (lon,lat)
        'spn'          => env('YANDEX_SPN', '0.6,0.5'),         // kapsam kutusu
    ],

    /*
     * SEO — site geneli meta / Schema.org / Search Console.
     * site_url: kanonik ve sitemap URL'lerinin tabanı (APP_URL fallback).
     * verification: Google Search Console "HTML etiketi" doğrulama kodu.
     */
    'seo' => [
        'site_url'     => rtrim(env('APP_URL', 'https://ferxgo.com'), '/'),
        'phone'        => env('SEO_PHONE', '+908503403039'),
        'phone_label'  => env('SEO_PHONE_LABEL', '0850 340 3039'),
        'verification' => env('GOOGLE_SITE_VERIFICATION', 'torPFLRu13QsVkyBnt30IQX9xLU8joPWYldCp1pp4mI'),
    ],

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

    /*
     * PayTR iFrame API — sürücü paket ödemeleri (Martı TAG benzeri abonelik).
     * iframe içinde: kart girişi, 3D Secure, saklı kart ve Masterpass otomatik aktif.
     * enabled=false → MockGateway devreye girer (dev için, fake checkout).
     *
     * test_mode=true iken PayTR test kartları ile çalışır, gerçek tahsilat yok.
     * Canlıya geçerken FerXGo'ya özel mağaza credential'ları + test_mode=false.
     */
    /*
     * Acil yardım (panic) alarmı — operatör bilgilendirme.
     * Sürücü/yolcu ACİL YARDIM'a bastığında nöbetçi operatör(ler)e anında SMS gider.
     *
     * .env:
     *   PANIC_SMS_ENABLED=true                            (varsayılan: KAPALI — istenirse açılır)
     *   PANIC_OPERATOR_PHONES="05xxxxxxxxx,05yyyyyyyyy"   (virgülle ayır, cep numarası — sabit hat SMS almaz!)
     */
    'panic' => [
        'sms_enabled'     => (bool) env('PANIC_SMS_ENABLED', false),
        'operator_phones' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PANIC_OPERATOR_PHONES', ''))
        ))),

        /*
         * Click-to-call — operatör panelden tek tıkla arasın (FreePBX/Asterisk AMI).
         * Kapalıyken buton tel: fallback'e düşer. Detay: ClickToCallService.
         */
        'click_to_call' => [
            'enabled' => (bool) env('PANIC_CLICK_TO_CALL_ENABLED', false),
            'ami' => [
                'host'             => env('AMI_HOST'),
                'port'             => (int) env('AMI_PORT', 5038),
                'username'         => env('AMI_USERNAME'),
                'secret'           => env('AMI_SECRET'),
                'operator_channel' => env('PANIC_OPERATOR_CHANNEL'),
                'context'          => env('PANIC_CALL_CONTEXT', 'from-internal'),
                'caller_id'        => env('PANIC_CALL_CALLERID', 'FERXGO ACIL DURUM <5555>'),
                'outbound_prefix'  => env('PANIC_OUTBOUND_PREFIX', ''),
            ],
        ],
    ],

    'paytr' => [
        'enabled'         => (bool) env('PAYTR_ENABLED', false),
        'merchant_id'     => env('PAYTR_MERCHANT_ID'),
        'merchant_key'    => env('PAYTR_MERCHANT_KEY'),
        'merchant_salt'   => env('PAYTR_MERCHANT_SALT'),
        'test_mode'       => (bool) env('PAYTR_TEST_MODE', true),
        'timeout_limit'   => (int) env('PAYTR_TIMEOUT_LIMIT', 30),  // dakika
        'max_installment' => (int) env('PAYTR_MAX_INSTALLMENT', 1), // 1 = tek çekim
    ],

    /*
     * Firebase Cloud Messaging (FCM HTTP v1) — mobil push bildirimleri.
     *
     * Kurulum:
     *   1. Firebase Console → proje → Ayarlar → Servis Hesapları → "Yeni özel anahtar üret"
     *   2. İnen JSON'u sunucuya koy: storage/app/firebase/service-account.json
     *   3. .env:
     *        FCM_ENABLED=true
     *        FCM_PROJECT_ID=ferxgo-xxxx           (JSON içindeki project_id)
     *        FCM_CREDENTIALS_PATH=/tam/yol/service-account.json   (opsiyonel; varsayılan storage yolu)
     *
     * enabled=false veya JSON yoksa → PushService MOCK moduna düşer: gönderimi
     * loga yazar, hata fırlatmaz. Böylece uygulama credential olmadan da çalışır.
     */
    'firebase' => [
        'enabled'          => (bool) env('FCM_ENABLED', false),
        'project_id'       => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH', storage_path('app/firebase/service-account.json')),
    ],

];
