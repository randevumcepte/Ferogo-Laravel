# Ferogo Mobile API — v1

Bu doküman Flutter mobil uygulamasının kullanacağı backend API'sini özetler. Web tarafı (session-cookie) değişmeden çalışmaya devam eder; mobile ayrı namespace altında Sanctum bearer token ile.

## Kurulum (sunucu/dev)

```bash
composer install
php artisan migrate          # personal_access_tokens + device_tokens
php artisan config:clear
php artisan route:clear
```

`.env` içine en az şunlar eklenmeli:

```
SANCTUM_STATEFUL_DOMAINS=
FCM_HTTP_V1_ENABLED=false
FCM_PROJECT_ID=
```

> Firebase config dosyası (`firebase-service-account.json`) sunucuya yüklendiğinde `FCM_HTTP_V1_ENABLED=true` ve push servisi devreye alınacak (Faz 2).

## Genel sözleşme

- **Base URL**: `https://ferogo.com.tr/api/v1`
- **Auth**: `Authorization: Bearer <token>` (login response'larından gelir)
- **Cihaz binding**: Her authed istekte `X-Device-Id: <device_uuid>` zorunlu. Token, ilk login'de bu device_id ile bağlanır; başka device_id ile gelirse token iptal edilir ve `401 token_revoked` döner.
- **Response zarfı**: `{ "ok": true|false, ... }`. Hata: `{ "ok": false, "message": "...", "code"?: "..." }`.
- **Tarih formatı**: ISO 8601 (örn. `2026-05-31T12:34:56+03:00`).
- **Para birimi**: TL, integer kuruş yok — `total_fare: 245.50` gibi.
- **Tüm endpoint'ler HTTPS zorunlu** (ForceHttps middleware).

## Auth

### Müşteri OTP akışı

```http
POST /auth/customer/send-otp
{ "phone": "+90 555 123 45 67", "device_id": "dev_xxxxx" }
→ 200 { "ok": true, "message": "Kod gönderildi.", "dev_code": "123456"  // sadece debug=true iken }
```

```http
POST /auth/customer/verify-otp
{ "phone": "...", "code": "123456", "device_id": "dev_xxxxx",
  "name": "Ali Veli", "platform": "android", "app_version": "1.0.0",
  "os_version": "14", "device_model": "Pixel 8", "locale": "tr-TR" }
→ 200 {
  "ok": true,
  "token": "1|aBcDeF...",        // Bearer token (saklamak için secure_storage)
  "expires_at": "2026-06-30T...",
  "user": { "id": 1, "name": "Ali Veli", "phone": "...", "type": "customer" }
}
```

### Sürücü login

```http
POST /auth/driver/login
{ "email": "...", "password": "...", "device_id": "dev_xxxxx", "platform": "android", ... }
→ 200 {
  "ok": true, "token": "...", "expires_at": "...",
  "user": {...},
  "driver_id": 7, "availability_status": "offline"
}
```

Hata kodları: `account_inactive`, `driver_not_approved`.

### Diğer auth endpoint'leri

| Method | Path | Açıklama |
|---|---|---|
| `GET`  | `/auth/me`      | Bearer + X-Device-Id ile profil döner |
| `POST` | `/auth/logout`  | Bu cihazın token'ını iptal eder |

## Cihaz (FCM + diğer cihazlardan çıkış)

| Method | Path | Body | Açıklama |
|---|---|---|---|
| `POST` | `/devices/push-token` | `{ fcm_token }` | Login'den sonra FCM token'ı kaydet |
| `GET`  | `/devices`            | — | Kullanıcının aktif cihazlarını listele |
| `DELETE` | `/devices/{id}`     | — | Belirli bir cihazın token'ını iptal et |

## Müşteri akışı

| Method | Path | Açıklama |
|---|---|---|
| `GET`  | `/customer/bootstrap` | vehicle_classes vb. referans data |
| `GET`  | `/customer/places/search?q=` | Nominatim proxy, İzmir viewbox + 60dk cache |
| `POST` | `/customer/fare/calculate` | Canlı fiyat hesabı |
| `GET`  | `/customer/drivers/nearby?lat=&lng=&limit=` | Yakındaki onaylı+online sürücüler |
| `GET`  | `/customer/drivers/{id}/profile` | Sürücü detay (sertifikalar + araç) |
| `POST` | `/customer/ride-requests` | Yeni talep yarat |
| `GET`  | `/customer/ride-requests/{publicId}` | Durum polling (her 2 sn) |
| `POST` | `/customer/ride-requests/{publicId}/cancel` | İptal |
| `POST` | `/customer/ride-requests/{publicId}/confirm` | "Sürücüyü gördüm" onayı |
| `GET`  | `/customer/ride-requests/{publicId}/messages?since_id=` | Chat polling |
| `POST` | `/customer/ride-requests/{publicId}/messages` | Mesaj gönder |
| `GET`  | `/customer/history?limit=10` | Geçmiş yolculuklar |

### Talep yaratma örneği

```http
POST /customer/ride-requests
{
  "vehicle_class_slug": "easy",
  "pickup_address": "Alsancak, İzmir",
  "pickup_lat": 38.4377, "pickup_lng": 27.1428,
  "dropoff_address": "Adnan Menderes Havalimanı",
  "dropoff_lat": 38.2924, "dropoff_lng": 27.1567,
  "distance_km": 18.2, "duration_minutes": 26,
  "estimated_fare": 380.0,
  "preferred_driver_id": 12,
  "fallback_driver_ids": [7, 19],
  "kvkk_consent": true
}
→ 200 { "ok": true, "public_id": "rr_xxxxx", "status": { ... } }
```

Hata: `429` (rate limit), `422` (sürücü müsait değil/KVKK eksik).

## Sürücü akışı

| Method | Path | Açıklama |
|---|---|---|
| `GET`  | `/driver/state?since_id=` | Tek endpoint: driver + offer + active + messages |
| `POST` | `/driver/availability` | `{ status, lat?, lng? }` |
| `POST` | `/driver/location` | `{ lat, lng }` — 5 sn throttle |
| `POST` | `/driver/offers/{publicId}/accept` | Teklifi kabul et |
| `POST` | `/driver/offers/{publicId}/reject` | Teklifi reddet |
| `POST` | `/driver/active/arrived` | "Vardım" |
| `POST` | `/driver/active/no-show` | `{ lat?, lng?, note? }` |
| `POST` | `/driver/active/complete` | "Tamamlandı" |
| `POST` | `/driver/active/message` | Mesaj gönder |

## Güvenlik özeti

| Katman | Nasıl |
|---|---|
| TLS zorunlu | ForceHttps middleware web+api'de aktif; HSTS header (1 yıl) |
| Token sınırlı süre | Sanctum 30 gün TTL, login response'unda `expires_at` döner |
| Cihaz binding | `X-Device-Id` ↔ token eşleşmesi her istekte doğrulanır; uyumsuzsa token derhal silinir |
| Token ability scope | `customer:*` veya `driver:*` — başka rolün endpoint'i çağrılırsa 403 |
| Role guard | User.type kolonu ile eşleşmiyorsa 403 `role_mismatch` |
| Account status | `User.status != 'active'` ise 403 `account_inactive` |
| OTP brute force | Telefon başı 5 yanlış / dk + IP başı 10 send / saat + 1/dk gönderim |
| Driver login brute force | Email başı 5 / dk + IP başı 30 / saat |
| Talep flood | Telefon başı 2 / 10dk + IP başı 5 / 10dk + cihaz başı 8 / saat |
| Mesaj flood | Kullanıcı başı 10 / dk |
| Konum flood | Sürücü başı 1 / 5sn |
| Trust ban | CustomerTrustService — no-show > eşik ise ban |
| Response header'ları | X-Content-Type-Options, X-Frame-Options, Referrer-Policy, CORP, Cache-Control: no-store, Server header silinir |
| Varlık sızdırma | Login hatasında "e-posta veya şifre hatalı" tek mesaj; başka kullanıcının ride_request'ine erişim 404 |

## Push notification (FCM)

Backend Faz 2'de eklenecek tetikleyiciler:

- Müşteriye: sürücü kabul etti / sürücü vardı / yeni mesaj
- Sürücüye: yeni teklif / talep iptal edildi / yeni mesaj

Backend'in payload formatı:

```json
{
  "notification": { "title": "...", "body": "..." },
  "data": {
    "type": "ride_offer" | "ride_accepted" | "ride_arrived" | "message",
    "public_id": "rr_xxx"
  }
}
```

Flutter tarafı `data.type` ile go_router'a yönlendirir. Notification-only mesaj gönderilmez (deeplink karışmasın).

## Karşı tarafı tutarsız bırakmamak

- Talep yaratıldığında polling 2 sn'de bir `show` endpoint'ine düşer; aynı zamanda FCM gelirse uygulama state'i invalidate edilir.
- Sürücü teklifini accept ederken aynı request başka sürücüye gidiyor olabilir → 409 dönerse "kaçırdın" göster.
- Token süresi dolarsa `/auth/me` 401 döner → uygulama login'e yönlendirir (refresh token tasarımı yok, yeniden OTP/şifre girer).
