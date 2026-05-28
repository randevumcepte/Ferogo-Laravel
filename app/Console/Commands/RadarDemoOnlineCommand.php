<?php

namespace App\Console\Commands;

use App\Modules\Driver\Models\Driver;
use Illuminate\Console\Command;

/**
 * Demo amaçlı: onaylı sürücüleri toplu olarak "online" yapıp konumlarını
 * verilen merkez (lat/lng) etrafına yayar. Hızlı Seç radar testinde
 * "çevrimiçi sürücü yok" mesajı için pratik fix.
 *
 *   php artisan radar:demo-online                    → İzmir merkezi (varsayılan)
 *   php artisan radar:demo-online --lat=38.42 --lng=27.14
 *   php artisan radar:demo-online --offline          → Hepsini offline yap
 */
class RadarDemoOnlineCommand extends Command
{
    protected $signature = 'radar:demo-online
        {--lat=38.4192 : Merkez enlem (varsayılan İzmir Konak)}
        {--lng=27.1287 : Merkez boylam}
        {--radius=0.03 : Dağıtım yarıçapı (derece, ~3 km)}
        {--offline : Hepsini offline yap}';

    protected $description = 'Onaylı sürücüleri demo testleri için online yapıp belirli bir merkez etrafına yayar.';

    public function handle(): int
    {
        if ($this->option('offline')) {
            $count = Driver::where('availability_status', 'online')
                ->update(['availability_status' => 'offline']);
            $this->info("✓ {$count} sürücü offline yapıldı.");
            return self::SUCCESS;
        }

        $lat = (float) $this->option('lat');
        $lng = (float) $this->option('lng');
        $r   = (float) $this->option('radius');

        $drivers = Driver::where('approval_status', 'approved')->get();

        if ($drivers->isEmpty()) {
            $this->warn('Hiç onaylı sürücü yok. Önce sürücü başvurusunu onayla.');
            return self::FAILURE;
        }

        foreach ($drivers as $d) {
            $d->update([
                'availability_status'      => 'online',
                'current_lat'              => $lat + (mt_rand(-1000, 1000) / 1000.0) * $r,
                'current_lng'              => $lng + (mt_rand(-1000, 1000) / 1000.0) * $r,
                'last_location_updated_at' => now(),
            ]);
        }

        $this->info("✓ {$drivers->count()} sürücü online · merkez {$lat},{$lng} · yarıçap {$r}°");
        $this->line('   Radarı test et: /yolculuk-yapin');
        return self::SUCCESS;
    }
}
