<?php

namespace App\Modules\Mobile\Services;

use App\Modules\Mobile\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging — HTTP v1 API ile mobil push gönderimi.
 *
 * Bağımlılıksız: OAuth2 erişim token'ı service account JSON'dan openssl ile
 * imzalanan bir JWT üzerinden alınır (google/auth ya da kreait paketi gerekmez).
 * Erişim token'ı ~1 saat geçerli; 55 dk cache'lenir.
 *
 * Kullanım:
 *   app(PushService::class)->sendToUser($userId, 'Başlık', 'Metin', ['type' => 'new_offer', 'ride_request_id' => 42]);
 *
 * Notlar:
 *  - services.fcm.enabled=false iken hiçbir şey göndermez (dev/QA güvenli no-op).
 *  - Geçersiz/expire token (UNREGISTERED / 404) otomatik null'lanır — bir dahaki
 *    push'ta o ölü kayda uğraşılmaz. Cihaz yeniden login olunca token tazelenir.
 *  - data payload'daki tüm değerler string'e çevrilir (FCM v1 zorunluluğu).
 */
class PushService
{
    private const OAUTH_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE           = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CACHE_KEY       = 'fcm:access_token';

    /**
     * Bir kullanıcının tüm cihazlarına push gönderir.
     *
     * @param  array<string,mixed>  $data  deep-link için (type, ride_request_id, vb.)
     * @return array{sent:int,failed:int,pruned:int}
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        return $this->sendToUsers([$userId], $title, $body, $data);
    }

    /**
     * Birden çok kullanıcının cihazlarına push gönderir.
     *
     * @param  array<int>  $userIds
     * @param  array<string,mixed>  $data
     * @return array{sent:int,failed:int,pruned:int}
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'pruned' => 0];

        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) {
            return $summary;
        }

        if (! config('services.fcm.enabled')) {
            // Dev/QA: push kapalı — sessizce geç (polling zaten çalışıyor).
            return $summary;
        }

        $rows = DeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('fcm_token')
            ->get(['id', 'fcm_token']);

        if ($rows->isEmpty()) {
            return $summary;
        }

        try {
            $accessToken = $this->accessToken();
        } catch (Throwable $e) {
            Log::error('FCM: erişim token alınamadı', ['err' => $e->getMessage()]);
            $summary['failed'] = $rows->count();
            return $summary;
        }

        $projectId = (string) config('services.fcm.project_id');
        $endpoint  = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // data payload: FCM v1 tüm değerlerin string olmasını ister.
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        foreach ($rows as $row) {
            $payload = [
                'message' => [
                    'token'        => $row->fcm_token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => $stringData,
                    'android'      => [
                        'priority'     => 'high',
                        'notification' => ['sound' => 'default', 'channel_id' => 'ferxgo_default'],
                    ],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['sound' => 'default', 'content-available' => 1]],
                    ],
                ],
            ];

            try {
                $resp = Http::withToken($accessToken)
                    ->acceptJson()
                    ->timeout(10)
                    ->post($endpoint, $payload);

                if ($resp->successful()) {
                    $summary['sent']++;
                    continue;
                }

                // Ölü token → kaydı temizle ki bir daha denenmesin.
                if ($this->isDeadToken($resp->status(), (string) $resp->body())) {
                    DeviceToken::whereKey($row->id)->update(['fcm_token' => null]);
                    $summary['pruned']++;
                } else {
                    $summary['failed']++;
                    Log::warning('FCM: gönderim başarısız', [
                        'device_token_id' => $row->id,
                        'status'          => $resp->status(),
                        'body'            => $resp->body(),
                    ]);
                }
            } catch (Throwable $e) {
                $summary['failed']++;
                Log::warning('FCM: gönderim exception', ['device_token_id' => $row->id, 'err' => $e->getMessage()]);
            }
        }

        return $summary;
    }

    /**
     * Geçersiz/kayıtsız token mı? (bir daha gönderilmemeli)
     * FCM v1: 404 NOT_FOUND / UNREGISTERED ya da 400 INVALID_ARGUMENT (bozuk token).
     */
    private function isDeadToken(int $status, string $body): bool
    {
        if ($status === 404) {
            return true;
        }
        if ($status === 400 && str_contains($body, 'INVALID_ARGUMENT') && str_contains($body, 'token')) {
            return true;
        }
        return str_contains($body, 'UNREGISTERED');
    }

    /**
     * OAuth2 erişim token'ı (55 dk cache). Service account JSON'dan RS256 imzalı
     * JWT üretir, oauth2.googleapis.com'dan bearer access_token alır.
     */
    private function accessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, 3300, function (): string {
            $creds = $this->credentials();

            $now = time();
            $jwt = $this->signJwt([
                'iss'   => $creds['client_email'],
                'sub'   => $creds['client_email'],
                'aud'   => self::OAUTH_TOKEN_URI,
                'scope' => self::SCOPE,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], $creds['private_key']);

            $resp = Http::asForm()->timeout(10)->post(self::OAUTH_TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if (! $resp->successful() || ! $resp->json('access_token')) {
                throw new \RuntimeException('OAuth2 token reddedildi: ' . $resp->status() . ' ' . $resp->body());
            }

            return (string) $resp->json('access_token');
        });
    }

    /**
     * Service account JSON'ı okur ve doğrular.
     *
     * @return array{client_email:string,private_key:string}
     */
    private function credentials(): array
    {
        $path = (string) config('services.fcm.credentials');
        // Göreli yol → proje köküne göre çöz.
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            throw new \RuntimeException("FCM service account bulunamadı: {$path}");
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new \RuntimeException('FCM service account JSON geçersiz (client_email/private_key yok).');
        }

        return [
            'client_email' => (string) $json['client_email'],
            'private_key'  => (string) $json['private_key'],
        ];
    }

    /**
     * RS256 imzalı JWT üretir (openssl ile — ek paket gerektirmez).
     *
     * @param  array<string,mixed>  $claims
     */
    private function signJwt(array $claims, string $privateKey): string
    {
        $header  = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64Url(json_encode($claims));
        $signingInput = $header . '.' . $payload;

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('JWT imzalanamadı (private_key hatalı olabilir).');
        }

        return $signingInput . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
