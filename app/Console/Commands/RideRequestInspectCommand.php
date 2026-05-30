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
            ->with(['offeredDriver.user', 'acceptedDriver.user'])
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
                'ride_id'         => $r->ride_id ?: '—',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Public', 'Created', 'Status', 'Phone', 'Offered Driver', 'Aday#', 'Red', 'Expires', 'Ride'],
            $rows
        );

        $this->line('');
        $this->comment('Yorumlama:');
        $this->line('  · status=pending + offered driver "online" + expires not past → sürücüde görünmeli');
        $this->line('  · status=pending + offered driver "offline/busy" → sürücü teklifi göremez (gerekirse 60s sonra fallback\'e geçer)');
        $this->line('  · status=exhausted → tüm adaylar reddetti / timeout');
        $this->line('  · status=accepted + ride_id dolu → başarılı');

        return self::SUCCESS;
    }
}
