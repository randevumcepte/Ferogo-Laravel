# Sürücü Onboarding (Hesap-Önce / Martı Modeli) — API Sözleşmesi

Bu belge, web + mobil (Flutter) tarafından tüketilen sürücü doğrulama/onboarding
akışını tanımlar. Backend tek doğruluk kaynağıdır: tamamlanma durumu ve
"incelemeye hazır mı" mantığı `DriverOnboardingService` içinde hesaplanır.

## Akış

```
Ön kayıt → hesap "beklemede" açılır + otomatik giriş
   → "Doğrulama Durumu" ekranı (kilitli onboarding; tam panel DEĞİL)
   → adım adım: kişisel · araç bilgisi · araç fotoğrafları (6 açı) · ehliyet
     · selfie · SRC · adli sicil · psikoteknik · ruhsat · sigorta · muayene
   → her adım ANINDA kaydedilir (kısmi yükleme serbest)
   → TÜM belgeler tamamlanınca "İncelemeye Gönder" (submitted_at set)
   → admin inceler (Sürücüler → İncele & Onayla): sınıf onayı + belge onayı
   → approved → tam panel açılır (online olma, yolculuk alma)
   → rejected → gerekçeli mesaj, panel kapalı
```

## Durum modeli

- `approval_status`: `pending | approved | rejected | suspended` (enum değişmedi)
- `submitted_at` (Driver): null = onboarding devam ediyor / eksik; dolu = inceleme bekliyor
- Türetilmiş `status` (serviste): `incomplete | pending_review | approved | rejected | suspended`
- Herhangi bir belge/araç değişince `submitted_at` sıfırlanır → yeniden gönderilmeli.

## Web endpoint'leri (session, guard: driver)

| Metot | Yol | Açıklama |
|------|-----|----------|
| GET  | `/surucu-paneli/dogrulama` | Doğrulama Durumu ekranı (HTML) |
| GET  | `/surucu-paneli/api/onboarding/status` | Tamamlanma durumu (JSON) |
| GET  | `/surucu-paneli/api/onboarding/vehicle-models?make_id=` | Markaya bağlı modeller |
| POST | `/surucu-paneli/api/onboarding/vehicle` | Araç bilgisi kaydet |
| POST | `/surucu-paneli/api/onboarding/photo` | Tek açı araç fotoğrafı |
| POST | `/surucu-paneli/api/onboarding/document` | Belge yükle |
| POST | `/surucu-paneli/api/onboarding/submit` | İncelemeye gönder |

## İstek/yanıt şekilleri

### GET onboarding/status
```json
{
  "ok": true,
  "onboarding": {
    "status": "incomplete",
    "is_submitted": false,
    "is_ready_for_review": false,
    "percent": 45,
    "completed": 5,
    "total": 11,
    "missing": ["Selfie Doğrulama", "SRC Belgesi", "..."],
    "steps": [
      { "key": "personal", "label": "Kişisel Bilgiler", "group": "kimlik", "complete": true },
      { "key": "vehicle_info", "label": "Araç Bilgileri", "group": "arac", "complete": true },
      { "key": "vehicle_photos", "label": "Araç Fotoğrafları", "group": "arac", "complete": false }
      // ...
    ]
  }
}
```

### POST onboarding/vehicle
Body (multipart veya form): `vehicle_type, vehicle_make_id, vehicle_model_id, year, color, plate, vehicle_class_id`
- `vehicle_class_id` sürücünün ÖNERİSİDİR; admin incelemede onaylar/düzeltir.
- Yanıt: `{ ok, onboarding }` (güncel durum) veya `{ ok:false, message }` (422).

### POST onboarding/photo
Body (multipart): `angle` (`left|front|right|back|interior_front|interior_back`), `photo` (image ≤ 8MB)
- Yanıt: `{ ok, angle, url, onboarding }`

### POST onboarding/document
Body (multipart): `type` (`license|selfie|src|criminal_record|psychotechnic|registration|insurance|inspection`), `file` (pdf/jpg/png/webp ≤ 10MB), opsiyonel `expires` (date)
- `registration` (ruhsat) araca yazılır; önce araç bilgisi kaydedilmiş olmalı.
- Yanıt: `{ ok, url, onboarding }`

### POST onboarding/submit
- Eksikse: `422 { ok:false, code:"incomplete", message, missing:[...], onboarding }`
- Tamsa: `{ ok:true, message, onboarding }` (submitted_at set edilir)

## Araç kataloğu (seçmeli marka/model)

- `vehicle_makes` / `vehicle_models` tabloları (VehicleCatalogSeeder ile doldurulur).
- Marka listesi onboarding sayfasına server-side gömülür; model listesi
  `vehicle-models?make_id=` ile bağımlı çekilir.
- Katalog admin panelinden genişletilebilir.

## Mobil (Flutter) — KALAN backend işi

Yukarıdaki endpoint'ler **web session (guard: driver)** içindir. Mobil uygulama
token (Sanctum) ile kimliklenir. Mobil entegrasyonu için yapılacak (ayrı görev):

1. `routes/api.php` içindeki `auth:sanctum` sürücü grubuna aynı işlevlerin
   API karşılıklarını ekle (aynı `DriverOnboardingService` kullanılarak). Sürücü,
   `DriverController::currentDriver($request)` desenindeki gibi token'dan çözülür.
2. Yanıt şekilleri yukarıdakiyle birebir aynı tutulmalı (tek istemci mantığı).
3. Flutter tarafında "Doğrulama Durumu" ekranı bu sözleşmeye göre uygulanır
   (ekran görüntülerindeki adım listesi = `steps[]`).

> Not: Servis ve veri modeli platformdan bağımsız olduğundan, mobil için yalnızca
> ince bir Sanctum controller katmanı eklemek yeterlidir; iş mantığı tekrar yazılmaz.
