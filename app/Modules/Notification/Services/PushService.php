<?php

namespace App\Modules\Notification\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (FCM HTTP v1) push gönderici.
 *
 * İki mod:
 *  - CANLI: config/services.firebase.enabled=true + geçerli servis hesabı JSON'u varsa,
 *    servis hesabıyla OAuth2 access token alınır (RS256 JWT → token exchange) ve
 *    her token'a FCM v1 messages:send çağrısı yapılır.
 *  - MOCK: credential yoksa/kapalıysa → gönderim loga yazılır, hata FIRLATILMAZ.
 *    Böylece uygulama Firebase kurulmadan da sorunsuz çalışır; JSON eklenince
 *    tek satır .env değişikliğiyle canlıya geçer.
 *
 * Geçersiz (UNREGISTERED/INVALID_ARGUMENT) token'lar toplanır → çağıran taraf temizler.
 */
class PushService
{
    private const TOKEN_URI    = 'https://oauth2.googleapis.com/token';
    private const SCOPE        = 'https://www.googleapis.com/auth/firebase.messaging';
    private const ACCESS_CACHE = 'fcm_access_token';

    /**
     * Bir grup cihaz token'ına aynı bildirimi gönderir.
     *
     * @param  string[]  $tokens
     * @param  array<string,string>  $data  data payload (deep-link için: type, deep_link, public_id...)
     * @return array{sent:int, failed:int, invalid_tokens:string[], mock:bool}
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
    ): array {
        $tokens = array_values(array_unique(array_filter($tokens)));
        $result = ['sent' => 0, 'failed' => 0, 'invalid_tokens' => [], 'mock' => false];

        if (empty($tokens)) {
            return $result;
        }

        // data payload: FCM v1 tüm değerleri STRING ister
        $data = array_map(static fn ($v) => (string) $v, array_filter($data, static fn ($v) => $v !== null && $v !== ''));

        if (! $this->isLive()) {
            $result['mock'] = true;
            $result['sent'] = count($tokens);
            Log::info('[PushService MOCK] push', [
                'tokens' => count($tokens),
                'title'  => $title,
                'body'   => $body,
                'data'   => $data,
            ]);
            return $result;
        }

        $accessToken = $this->accessToken();
        if (! $accessToken) {
            // Token alınamadı → mock gibi davran (akışı bozma)
            $result['mock'] = true;
            $result['failed'] = count($tokens);
            Log::warning('[PushService] access token alınamadı, gönderim atlandı.');
            return $result;
        }

        $projectId = (string) config('services.firebase.project_id');
        $endpoint  = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token'        => $token,
                    'notification' => array_filter([
                        'title' => $title,
                        'body'  => $body,
                        'image' => $imageUrl,
                    ]),
                    'data' => $data,
                    'android' => [
                        'priority'     => 'high',
                        'notification' => ['sound' => 'default'],
                    ],
                    'apns' => [
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ];

            try {
                $resp = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post($endpoint, $message);

                if ($resp->successful()) {
                    $result['sent']++;
                    continue;
                }

                $result['failed']++;
                $status = $resp->json('error.status');
                if (in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                    $result['invalid_tokens'][] = $token;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                Log::warning('[PushService] gönderim hatası', ['err' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /** Firebase canlı gönderime hazır mı? */
    public function isLive(): bool
    {
        return (bool) config('services.firebase.enabled')
            && config('services.firebase.project_id')
            && is_string($this->credentialsPath())
            && is_file($this->credentialsPath());
    }

    private function credentialsPath(): ?string
    {
        $path = config('services.firebase.credentials_path');
        return is_string($path) ? $path : null;
    }

    /**
     * OAuth2 access token (servis hesabı → RS256 JWT → token exchange).
     * ~55 dk cache'lenir.
     */
    private function accessToken(): ?string
    {
        return Cache::remember(self::ACCESS_CACHE, 3300, function (): ?string {
            $creds = $this->credentials();
            if (! $creds) {
                return null;
            }

            $jwt = $this->makeJwt($creds);
            if (! $jwt) {
                return null;
            }

            try {
                $resp = Http::asForm()->timeout(10)->post(self::TOKEN_URI, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);
                if ($resp->successful()) {
                    return (string) $resp->json('access_token') ?: null;
                }
                Log::warning('[PushService] token exchange başarısız', ['body' => $resp->body()]);
            } catch (\Throwable $e) {
                Log::warning('[PushService] token exchange hatası', ['err' => $e->getMessage()]);
            }

            return null;
        });
    }

    /** @return array{client_email:string, private_key:string}|null */
    private function credentials(): ?array
    {
        $path = $this->credentialsPath();
        if (! $path || ! is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }
        return [
            'client_email' => (string) $json['client_email'],
            'private_key'  => (string) $json['private_key'],
        ];
    }

    /** Servis hesabıyla imzalı RS256 JWT üretir. */
    private function makeJwt(array $creds): ?string
    {
        $now = time();
        $header  = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims  = [
            'iss'   => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URI,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $segments = [
            $this->b64(json_encode($header)),
            $this->b64(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256);
        if (! $ok) {
            Log::warning('[PushService] JWT imzalama başarısız (private_key hatalı olabilir).');
            return null;
        }

        $segments[] = $this->b64($signature);
        return implode('.', $segments);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
