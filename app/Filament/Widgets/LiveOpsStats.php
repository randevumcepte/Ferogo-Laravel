<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Payment\Models\Payment;
use App\Modules\Security\Models\PanicAlert;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Live Ops KPI — 30 saniyede bir yenilenen anlık durum kartları.
 * Amaç: süper admin panele girdiği ilk 3 saniyede platformun nabzını görsün.
 */
class LiveOpsStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        // Online + busy sürücüler
        $onlineDrivers = Driver::whereIn('availability_status', ['online', 'busy'])->count();
        $busyDrivers   = Driver::where('availability_status', 'busy')->count();
        $totalApproved = Driver::where('approval_status', 'approved')->count();

        // Aktif yolculuklar (in_progress veya driver_arriving)
        $activeRides = Ride::whereIn('status', ['driver_arriving', 'in_progress', 'assigned'])->count();

        // Bekleyen ride request'ler (pool aç)
        $pendingRequests = RideRequest::where('status', 'pending')
            ->where('offer_expires_at', '>', now())
            ->count();

        // Bugünkü tamamlanmış yolculuklardan toplam ciro
        $todayRevenue = (float) Ride::where('status', 'completed')
            ->whereDate('completed_at', today())
            ->sum('total_fare');
        $todayRideCount = Ride::where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();

        // Dün ciro (delta için)
        $yesterdayRevenue = (float) Ride::where('status', 'completed')
            ->whereDate('completed_at', today()->subDay())
            ->sum('total_fare');
        $revenueDelta = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100)
            : ($todayRevenue > 0 ? 100 : 0);

        // Bekleyen sürücü başvuruları
        $pendingApps = DriverApplication::where('status', 'pending')->count();

        // Panik alarm — açık olanlar
        $openPanic = class_exists(PanicAlert::class)
            ? PanicAlert::where('status', 'active')->count()
            : 0;

        // Toplam müşteri
        $customers = User::where('type', 'customer')->count();

        return [
            Stat::make('Bugünkü Ciro', '₺' . number_format($todayRevenue, 0, ',', '.'))
                ->description(($revenueDelta >= 0 ? '↑ ' : '↓ ') . abs($revenueDelta) . '% düne göre · ' . $todayRideCount . ' yolculuk')
                ->descriptionIcon($revenueDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueDelta >= 0 ? 'success' : 'danger')
                ->chart($this->hourlyRevenueChart()),

            Stat::make('Aktif Yolculuk', (string) $activeRides)
                ->description($pendingRequests . ' bekleyen talep')
                ->descriptionIcon('heroicon-m-map')
                ->color($activeRides > 0 ? 'primary' : 'gray'),

            Stat::make('Online Sürücü', $onlineDrivers . ' / ' . $totalApproved)
                ->description($busyDrivers . ' yolculukta · ' . ($totalApproved - $onlineDrivers) . ' offline')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($onlineDrivers > 0 ? 'success' : 'warning'),

            Stat::make('Bekleyen Başvuru', (string) $pendingApps)
                ->description('Sürücü başvuruları onay bekliyor')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($pendingApps > 0 ? 'warning' : 'success')
                ->url(url('/admin/driver-applications')),

            Stat::make('Panik Alarm', (string) $openPanic)
                ->description($openPanic > 0 ? 'ACİL — anında müdahale' : 'Aktif alarm yok')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($openPanic > 0 ? 'danger' : 'success'),

            Stat::make('Toplam Müşteri', number_format($customers, 0, ',', '.'))
                ->description('Kayıtlı yolcu hesabı')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }

    /** Son 12 saatlik gelir mini-chart (küçük spark line kart altında). */
    private function hourlyRevenueChart(): array
    {
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $sum = (float) Ride::where('status', 'completed')
                ->whereBetween('completed_at', [$hour->copy()->startOfHour(), $hour->copy()->endOfHour()])
                ->sum('total_fare');
            $data[] = $sum;
        }
        return $data;
    }
}
