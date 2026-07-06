<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Driver\Models\Driver;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Test / demo verilerini temizler: tüm sürücü kayıtlarını, ilişkili yolculuk
 * geçmişlerini, ödeme/no-show/tazminat kayıtlarını, sürücü araçlarını ve
 * sürücü kullanıcılarını (type=driver) siler. Gerçek üretim öncesi "temiz sayfa"
 * için kullanılır.
 *
 * FK stratejisi:
 *   - restrict FK'lı tablolar (driver_payouts, no_show_reports,
 *     driver_compensations) elle temizlenir
 *   - nullOnDelete FK'lar otomatik null olur (rides, ride_requests,
 *     security_incidents, verification_photos)
 *   - cascadeOnDelete FK'lar otomatik silinir (driver_change_requests,
 *     customer_favorite_drivers, driver_packages)
 *
 *   php artisan drivers:reset --force
 *   php artisan drivers:reset --keep-users     (araç + sürücü sil, user kalır)
 *   php artisan drivers:reset --keep-rides     (ride/ride_requests dokunma)
 */
class DriversResetCommand extends Command
{
    protected $signature = 'drivers:reset
        {--force : Doğrulama sormadan çalıştır}
        {--keep-users : Sürücü user kayıtları korunur (type=driver silinmez)}
        {--keep-rides : Ride ve RideRequest kayıtlarına dokunma}
        {--keep-applications : Eski başvurulara (driver_applications) dokunma}';

    protected $description = 'Tüm sürücüleri (fake + demo dahil) ve ilişkili kayıtları siler. Temiz sayfa için.';

    public function handle(): int
    {
        $driverCount  = Driver::count();
        $userCount    = User::where('type', 'driver')->count();
        $vehicleIds   = Driver::whereNotNull('current_vehicle_id')->pluck('current_vehicle_id')->unique();
        $vehicleCount = $vehicleIds->count();

        if ($driverCount === 0 && $userCount === 0) {
            $this->info('  Hiç sürücü kaydı yok. Temiz.');
            return self::SUCCESS;
        }

        $this->warn('  ⚠ Silinecek kayıtlar:');
        $this->line("     - Driver: {$driverCount}");
        $this->line("     - User (type=driver): {$userCount}");
        $this->line("     - Vehicle (sürücüye bağlı): {$vehicleCount}");
        $this->line('     - Bağlı: driver_payouts, no_show_reports, driver_compensations,');
        $this->line('              driver_change_requests, customer_favorite_drivers, driver_packages');

        if (! $this->option('force') && ! $this->confirm('  Bu işlem GERİ ALINAMAZ. Devam edilsin mi?')) {
            $this->info('  İptal edildi.');
            return self::SUCCESS;
        }

        $driverIds = Driver::pluck('id')->all();
        $userIds   = User::where('type', 'driver')->pluck('id')->all();

        DB::transaction(function () use ($driverIds, $userIds, $vehicleIds) {

            // 1) restrict FK'lı tablolar — Driver silinmeden önce temizlenmeli
            if (\Schema::hasTable('driver_payouts')) {
                $n = DB::table('driver_payouts')->whereIn('driver_id', $driverIds)->delete();
                $this->line("     · driver_payouts: {$n} silindi");
            }
            if (\Schema::hasTable('no_show_reports')) {
                $n = DB::table('no_show_reports')->whereIn('driver_id', $driverIds)->delete();
                $this->line("     · no_show_reports: {$n} silindi");
            }
            if (\Schema::hasTable('driver_compensations')) {
                $n = DB::table('driver_compensations')->whereIn('driver_id', $driverIds)->delete();
                $this->line("     · driver_compensations: {$n} silindi");
            }

            // 2) İsteğe bağlı: ride ve ride_request kayıtları
            if (! $this->option('keep-rides')) {
                if (\Schema::hasTable('rides')) {
                    $n = DB::table('rides')->whereIn('driver_id', $driverIds)->delete();
                    $this->line("     · rides: {$n} silindi");
                }
                if (\Schema::hasTable('ride_requests')) {
                    $n = DB::table('ride_requests')
                        ->whereIn('offered_driver_id', $driverIds)
                        ->orWhereIn('accepted_driver_id', $driverIds)
                        ->delete();
                    $this->line("     · ride_requests: {$n} silindi");
                }
            }

            // 3) Driver'ları sil — cascadeOnDelete tetiklenir
            //    (driver_change_requests, customer_favorite_drivers, driver_packages)
            $n = Driver::whereIn('id', $driverIds)->forceDelete();
            $this->line("     · drivers: {$n} silindi");

            // 4) Sürücü araçları
            if ($vehicleIds->isNotEmpty()) {
                $vehicles = Vehicle::whereIn('id', $vehicleIds)->get();
                foreach ($vehicles as $v) {
                    // Foto dosyalarını da temizle
                    if (is_array($v->photos)) {
                        foreach ($v->photos as $p) {
                            if ($p && ! str_starts_with($p, 'http')) {
                                Storage::disk('public')->delete($p);
                            }
                        }
                    }
                    $v->forceDelete();
                }
                $this->line("     · vehicles: {$vehicles->count()} silindi");
            }

            // 5) Sürücü user kayıtları
            if (! $this->option('keep-users')) {
                $users = User::whereIn('id', $userIds)->get();
                foreach ($users as $u) {
                    if ($u->avatar && ! str_starts_with($u->avatar, 'http')) {
                        Storage::disk('public')->delete($u->avatar);
                    }
                }
                $n = User::whereIn('id', $userIds)->forceDelete();
                $this->line("     · users (type=driver): {$n} silindi");
            }

            // 6) Eski başvurular (temiz sayfa için)
            if (! $this->option('keep-applications') && \Schema::hasTable('driver_applications')) {
                $n = DB::table('driver_applications')->delete();
                $this->line("     · driver_applications: {$n} silindi");
            }
        });

        $this->info('  ✓ Sürücü tarafı sıfırlandı. Yeni sürücü şu adımlarla eklenebilir:');
        $this->line('     1) /surucu-olun sayfasından başvuru formunu doldur');
        $this->line('     2) /admin → Operasyon → Sürücü Başvuruları');
        $this->line('     3) Başvurudaki "Onayla ve Hesap Aç" → e-posta + şifre ata');
        $this->line('     4) Bildirimden gelen e-posta/şifreyle /surucu-giris → giriş');
        $this->line('     5) /surucu-paneli/profil → araç bilgileri + belgeleri yükle');

        return self::SUCCESS;
    }
}
