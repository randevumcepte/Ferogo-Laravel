<?php

namespace App\Console\Commands;

use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use Illuminate\Console\Command;

/**
 * Son N ride request'in durumunu + ilgili sürücülerin müsaitlik bilgisini özetler.
 * "Talep sürücüye düşmüyor" türü sorunlarda tek bir bakışta teşhis.
 *
 * Kullanım:
 *   php artisan rr:inspect           // son 5
 *   php artisan rr:inspect --count=15
 */
class RideRequestInspectCommand extends Command
{
    protected $signature = 'rr:inspect {--count=5}';
    protected $description = 'Son ride request\'leri ve hedef sürücülerin online durumunu özetle';

    public function handle(): int
    {
        $count = (int) $this->option('count');

        // Sürücü özeti
        $onlineDrivers = Driver::where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->count();
        $busyDrivers = Driver::where('approval_status', 'approved')
            ->where('availability_status', 'busy')
            ->count();
        $offlineDrivers = Driver::where('approval_status', 'approved')
            ->where('availability_status', 'offline')
            ->count();

        $this->info("Onaylı sürücüler — online: {$onlineDrivers} · busy: {$busyDrivers} · offline: {$offlineDrivers}");
        $this->line('');

        $requests = RideRequest::query()
            ->with(['offeredDriver.user', 'acceptedDriver.user', 'ride'])
            ->latest('id')
            ->limit($count)
            ->get();

        if ($requests->isEmpty()) {
            $this->warn('Hiç ride_request kaydı yok.');
            return self::SUCCESS;
        }

        $rows = $requests->map(function (RideRequest $r) {
            $offered = $r->offeredDriver;
            $accepted = $r->acceptedDriver;

            $expiresInfo = '—';
            if ($r->offer_expires_at) {
                $expiresInfo = $r->offer_expires_at->isPast()
                    ? '⌛ EXPIRED ' . $r->offer_expires_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) . ' önce'
                    : '⏱ ' . max(0, (int) round(now()->diffInSeconds($r->offer_expires_at, false))) . 's kaldı';
            }

            $offeredInfo = $offered
                ? '#' . $offered->id . ' ' . ($offered->user->name ?? '?') . ' [' . $offered->availability_status . ']'
                : '—';

            // Ride status — driver panelinin "Aktif yolculuk" göstermesi/göstermemesinin sebebi.
            $rideInfo = '—';
            if ($r->ride) {
                $rideInfo = '#' . $r->ride->id . ' [' . $r->ride->status . ']';
            }

            return [
                'id'              => $r->id,
                'public_id'       => substr($r->public_id, 0, 8) . '…',
                'created'         => $r->created_at->format('H:i:s'),
                'status'          => $r->status,
                'phone'           => $r->customer_phone,
                'offered_driver'  => $offeredInfo,
                'candidate_idx'   => ($r->current_candidate_index ?? 0) . '/' . count($r->candidate_driver_ids ?? []),
                'rejections'      => $r->rejection_count,
                'expires'         => $expiresInfo,
                'ride'            => $rideInfo,
            ];
        })->toArray();

        $this->table(
            ['ID', 'Public', 'Created', 'Status', 'Phone', 'Offered Driver', 'Aday#', 'Red', 'Expires', 'Ride [status]'],
            $rows
        );

        $this->line('');
        $this->comment('Driver paneli "Aktif Yolculuk" yerine "Yeni talep bekleniyor" gösterir EĞER:');
        $this->line('  · request status != accepted   VEYA');
        $this->line('  · Ride status IN [completed, cancelled, no_show]');
        $this->line('');
        $this->comment('Hızlı teşhis:');
        $this->line('  · pending + offered online → sürücüde offer GÖRÜNMELİ. Görünmüyorsa polling/JS sorunu');
        $this->line('  · accepted + Ride [completed] → eski test, surucu zaten teslim etmis');
        $this->line('  · accepted + Ride [driver_arriving|in_progress] → AKTIF olmali, surucu ekranda gormeli');

        return self::SUCCESS;
    }
}
