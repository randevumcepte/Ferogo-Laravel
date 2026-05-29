<?php

namespace App\Console\Commands;

use App\Modules\Booking\Models\PhoneVerification;
use App\Modules\Booking\Services\CustomerTrustService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * SMS provider devreye alınana kadar test/dev kolaylığı:
 *   php artisan otp:last 0532XXXXXXX
 * Telefonun en güncel OTP kodunu cache + DB'den çekip yazdırır.
 */
class OtpLastCommand extends Command
{
    protected $signature = 'otp:last {phone : Telefon (boşluklu/prefixli OK)}';
    protected $description = 'Bir telefonun son gönderilen OTP kodunu (dev/test) göster';

    public function handle(CustomerTrustService $trust): int
    {
        $phone = (string) $this->argument('phone');
        $normalized = $trust->normalizePhone($phone);

        $this->info("Telefon: {$phone}");
        $this->info("Normalize: {$normalized}");

        // 1) Cache (10 dk geçerli, en hızlı)
        $cached = Cache::get('last_otp_dev:' . $normalized);
        if ($cached) {
            $this->line('');
            $this->info("CACHE (10 dk): <fg=yellow;options=bold>{$cached}</>");
        }

        // 2) Son PhoneVerification kaydı (DB) — kod hash'li, sadece meta
        $latest = PhoneVerification::where('phone', $normalized)
            ->latest('id')
            ->first();

        if (! $latest) {
            $this->warn('Bu telefon için kayıt yok. SMS adımına geldin mi?');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Son PhoneVerification kaydı:');
        $this->table(['Alan', 'Değer'], [
            ['ID',              $latest->id],
            ['Oluşturuldu',     $latest->created_at?->format('d.m.Y H:i:s')],
            ['Süresi doluyor',  $latest->expires_at?->format('d.m.Y H:i:s')],
            ['Süresi doldu mu', $latest->isExpired() ? 'EVET' : 'hayır'],
            ['Doğrulandı mı',   $latest->verified_at ? 'EVET (' . $latest->verified_at->format('H:i:s') . ')' : 'hayır'],
            ['Deneme sayısı',   $latest->attempts],
            ['IP',              $latest->ip ?? '—'],
        ]);

        if (! $cached) {
            $this->line('');
            $this->warn('Cache boş — kod 10 dk önce gönderildiyse cache uçmuş olabilir.');
            $this->warn('Telefondan yeniden "Kod gönder" bas ya da log dosyasını kontrol et:');
            $this->line('  <fg=cyan>tail -200 storage/logs/laravel.log | grep "\\[OTP\\]"</>');
        }

        return self::SUCCESS;
    }
}
