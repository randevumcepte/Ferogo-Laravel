<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API response'larına standart güvenlik header'ları ekler.
 *
 * Amaç:
 *  - MITM saldırılarını zorlaştır (HSTS)
 *  - JSON cevabın HTML olarak yorumlanmasını engelle (X-Content-Type-Options)
 *  - Tarayıcı geçmişinden cookie sızıntısını azalt (Referrer-Policy)
 *  - Eski iframe-clickjacking vektörlerini kapat
 *  - Mobil API olduğu için CSP burada zayıf — esas CSP web tarafında kalır.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // 1 yıl HSTS — sadece HTTPS üzerinden anlamlı, http'de tarayıcı zaten yok sayar.
        // includeSubDomains kasıtlı: tüm alt domain'ler de TLS şart.
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // JSON yanıtı sniff edilip text/html olarak çalıştırılmasın
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Eski tarayıcılarda iframe'e gömülüp clickjack edilmesin
        $response->headers->set('X-Frame-Options', 'DENY');

        // Referrer sızıntısı: API base URL bile dışarı sızmasın
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // Cross-origin policy: API başka origin'den fetch edilebilsin ama isolated kalsın
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        // Cache: API yanıtları kaza ile public CDN'de cache'lenmesin
        if (! $response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'no-store, max-age=0');
        }

        // Server header'ı kaldır — sürüm bilgisi recon için kullanılır
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
