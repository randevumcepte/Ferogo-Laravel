<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\AdEvent;
use App\Modules\Marketing\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sponsor aylık performans raporu — yazdırılabilir HTML (tarayıcıdan "PDF olarak kaydet").
 * Yalnızca panel (auth) kullanıcıları erişir.
 */
class AdReportController extends Controller
{
    public function show(Advertisement $advertisement, Request $request)
    {
        // Dönem: ?ay=YYYY-MM verilirse o ay, yoksa son 30 gün
        $ay = $request->query('ay');
        if (is_string($ay) && preg_match('/^\d{4}-\d{2}$/', $ay)) {
            $from = Carbon::createFromFormat('Y-m-d', $ay . '-01')->startOfMonth();
            $to = (clone $from)->endOfMonth();
        } else {
            $to = now();
            $from = (clone $to)->subDays(29)->startOfDay();
        }

        $base = fn () => AdEvent::where('advertisement_id', $advertisement->getKey())
            ->whereBetween('occurred_at', [$from, $to]);

        $imp = (clone $base())->where('type', 'impression')->count();
        $clk = (clone $base())->where('type', 'click')->count();
        $ctr = $imp > 0 ? round($clk / $imp * 100, 2) : 0.0;
        $uniq = (clone $base())->whereNotNull('anon_id')->distinct('anon_id')->count('anon_id');

        // Saat dağılımı (gösterim)
        $hourRows = (clone $base())->where('type', 'impression')
            ->select('hour', DB::raw('count(*) as c'))->groupBy('hour')->pluck('c', 'hour')->toArray();
        $byHour = [];
        for ($h = 0; $h < 24; $h++) {
            $byHour[$h] = (int) ($hourRows[$h] ?? 0);
        }

        // İlçe dağılımı (ilk 8)
        $byDistrict = (clone $base())->whereNotNull('district')
            ->select('district', DB::raw('count(*) as c'))->groupBy('district')
            ->orderByDesc('c')->limit(8)->pluck('c', 'district')->toArray();

        // Cihaz & kitle
        $byDevice = (clone $base())->select('device', DB::raw('count(*) as c'))
            ->groupBy('device')->pluck('c', 'device')->toArray();
        $byAudience = (clone $base())->select('audience', DB::raw('count(*) as c'))
            ->groupBy('audience')->pluck('c', 'audience')->toArray();

        // En yoğun saat & ilçe (özet cümle)
        $peakHour = null;
        $peakHourVal = 0;
        foreach ($byHour as $h => $c) {
            if ($c > $peakHourVal) { $peakHourVal = $c; $peakHour = $h; }
        }
        $peakDistrict = array_key_first($byDistrict) ?: null;

        return view('reports.ad-report', compact(
            'advertisement', 'from', 'to', 'imp', 'clk', 'ctr', 'uniq',
            'byHour', 'byDistrict', 'byDevice', 'byAudience', 'peakHour', 'peakDistrict'
        ));
    }
}
