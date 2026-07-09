<?php

namespace Database\Seeders;

use App\Modules\Legal\Models\LegalTextVersion;
use Illuminate\Database\Seeder;

/**
 * 6 hukuki metnin v1.0 sürümünü seed eder.
 *
 * Content olarak ilgili blade view dosyasının ham içeriği saklanır
 * (kullanıcıya gösterilen metnin kanonik kaynağı). SHA-256 bu içerik
 * üzerinden hesaplanır.
 *
 * Metin değiştiğinde:
 *   1) İlgili blade view'ı güncelle
 *   2) Bu seeder'ı yeni versiyon string'iyle güncelle ve yeniden çalıştır
 *   3) (Veya `php artisan legal:publish-version` komutuyla otomatize et — TODO)
 *
 * Eski versiyon SİLİNMEZ; sadece superseded_at set edilir.
 */
class LegalTextVersionSeeder extends Seeder
{
    public function run(): void
    {
        $version = 'v1.1-2026-07-09';

        $items = [
            [
                'key'         => 'platform_notice',
                'title'       => 'Yasal Platform Bildirimi (Modal)',
                'view'        => null, // modal layouts/public.blade.php içinde inline
                'content'     => $this->platformNoticeContent(),
            ],
            [
                'key'   => 'terms',
                'title' => 'Hizmet Şartları',
                'view'  => 'resources/views/legal/terms.blade.php',
            ],
            [
                'key'   => 'kvkk',
                'title' => 'KVKK Aydınlatma Metni',
                'view'  => 'resources/views/legal/kvkk.blade.php',
            ],
            [
                'key'   => 'distance_sales',
                'title' => 'Mesafeli Satış Sözleşmesi',
                'view'  => 'resources/views/legal/distance-sales.blade.php',
            ],
            [
                'key'   => 'cookies',
                'title' => 'Çerez Politikası',
                'view'  => 'resources/views/legal/cookies.blade.php',
            ],
            [
                'key'   => 'ride_sharing',
                'title' => 'Paylaşımlı Yolculuk Modeli',
                'view'  => 'resources/views/legal/ride-sharing.blade.php',
            ],
            [
                'key'   => 'driver_registration',
                'title' => 'Sürücü Kayıt Onayı (sorumluluk beyanı)',
                'view'  => null,
                'content' => $this->driverRegistrationContent(),
            ],
            [
                'key'   => 'reservation_kvkk',
                'title' => 'Yolcu Rezervasyon KVKK Onayı',
                'view'  => null,
                'content' => $this->reservationKvkkContent(),
            ],
        ];

        foreach ($items as $item) {
            $content = $item['content'] ?? $this->readView($item['view']);
            if ($content === null) {
                $this->command->warn("Atlandı: {$item['key']} (view okunamadı: {$item['view']})");
                continue;
            }

            $sha256 = LegalTextVersion::hashContent($content);

            // Aynı key+version varsa güncelle, yoksa yarat
            LegalTextVersion::updateOrCreate(
                ['key' => $item['key'], 'version' => $version],
                [
                    'content'      => $content,
                    'sha256'       => $sha256,
                    'title'        => $item['title'],
                    'published_at' => now(),
                    'superseded_at' => null,
                    'change_notes' => 'İlk yayın (FerXGo lansman v1.0).',
                ]
            );

            $this->command->info("✓ {$item['key']} {$version} (sha: " . substr($sha256, 0, 12) . "…)");
        }
    }

    protected function readView(?string $relativePath): ?string
    {
        if (! $relativePath) return null;
        $full = base_path($relativePath);
        return is_readable($full) ? file_get_contents($full) : null;
    }

    /**
     * Modal'da gösterilen metin (HTML değil, sadece görünür textler).
     * Eğer modal değişirse buradaki metin de güncellenmeli.
     */
    protected function platformNoticeContent(): string
    {
        return <<<'TXT'
YASAL PLATFORM BİLDİRİMİ — FerXGo hakkında bilmeniz gerekenler

Paylaşımlı Yolculuk Platformu
FerXGo, bağımsız üye sürücüler ile yolcuları dijital ortamda buluşturan bir paylaşımlı yolculuk koordinasyon platformudur.
6563 sayılı E-Ticaret Kanunu kapsamında aracı hizmet sağlayıcı sıfatıyla faaliyet gösterir; ticari taşımacılık hizmeti sağlamaz.

Şeffaf Katkı Payı
Yolculuk katkı payı, üye sürücü ile yolcu arasında belirlenir. Tahmini katkı payı yolculuk öncesi şeffaf biçimde ekranda gösterilir.
Yolculuk hizmeti üye sürücü ile yolcu arasında gerçekleşir.

Hizmet Bölgesi
FerXGo şu anda İzmir'de aktiftir. Havalimanı yolculuğu, şehir içi ve kurumsal paylaşımlı yolculuk hizmetlerimiz mevcuttur.
Hizmet kapsamımız yeni şehirlerle büyümeye devam etmektedir.

Kişisel Verilerin Korunması
Paylaştığınız ad, telefon ve konum bilgileri yalnızca yolculuk organizasyonu amacıyla kullanılır; üçüncü taraflarla paylaşılmaz.
Veriler 6698 sayılı KVKK kapsamında işlenmekte ve korunmaktadır.

Bu bildirim her oturumda bir kez gösterilir. Platformu kullanmaya devam ederek Hizmet Şartları'nı,
KVKK Aydınlatma Metni'ni ve Paylaşımlı Yolculuk modelini kabul etmiş sayılırsınız.
TXT;
    }

    protected function driverRegistrationContent(): string
    {
        return <<<'TXT'
SÜRÜCÜ KAYIT ONAY METNİ (Sorumluluk Beyanı)

FerXGo bir paylaşımlı yolculuk eşleştirme platformudur; ticari taşımacılık yapmaz, yolculuğun tarafı değildir ve üye sürücülerin işvereni değildir.

Yolcunun ödediği katkı payı, yolculuğun değişken giderlerine (yakıt, amortisman) katkı niteliğindedir ve doğrudan üye sürücüye aittir.
FerXGo bu ödemenin tarafı değildir ve komisyon almaz.

Paylaşımlı yolculuğa kendi aracınızla ve kendi takdirinizle katılırsınız; bu faaliyetten doğabilecek her türlü yasal ve mali yükümlülük tamamen size aittir.

Aşağıdaki bilgileri okuduğumu ve onaylıyorum:
- FerXGo'nun aracı hizmet sağlayıcı olduğunu,
- Yasal ve mali sorumluluğun bana ait olduğunu,
- Hizmet Şartları ve Paylaşımlı Yolculuk modelini kabul ettiğimi,
- KVKK kapsamında verilerimin işlenmesine onay verdiğimi.
TXT;
    }

    protected function reservationKvkkContent(): string
    {
        return <<<'TXT'
YOLCU REZERVASYON KVKK ONAYI

Rezervasyon yaparak:
- KVKK aydınlatma metnini okuduğumu,
- Kişisel verilerimin (ad, telefon, konum) yolculuk organizasyonu amacıyla işlenmesini kabul ettiğimi,
- Verilerimin yalnızca eşleştirilen üye sürücüyle paylaşılacağını anladığımı,
- Hizmet Şartları ve Mesafeli Satış Sözleşmesi'ni kabul ettiğimi beyan ederim.

Veriler 6698 sayılı KVKK kapsamında işlenir ve korunur. Ayrıntılı bilgi için KVKK Aydınlatma Metni'ne bakınız.
TXT;
    }
}
