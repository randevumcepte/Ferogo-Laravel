<?php

use Laravel\Sanctum\Sanctum;

return [

    // Mobil için SPA stateful domain'i kullanmıyoruz; web tarafı zaten session-cookie
    // kullanıyor, ayrı bir SPA yok. Bu yüzden 'stateful' liste boş bırakılabilir,
    // ama Sanctum'un kendi guard akışı bozulmasın diye standart default'u koruyoruz.
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    // Token TTL — 30 gün. Mobil token rotation interceptor'da yapılır.
    // null = süresiz; biz süre veriyoruz, eski cihaz unutulan token'lar otomatik düşsün.
    'expiration' => 60 * 24 * 30,

    // Tokeni hash'le sakla (database'de plain değil, prefix hariç hash) — Sanctum 4 default
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
