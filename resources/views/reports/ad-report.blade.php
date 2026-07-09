@php
    $devLabels = ['mobile' => 'Mobil', 'desktop' => 'Masaüstü', 'app' => 'Uygulama'];
    $audLabels = ['customer' => 'Yolcu', 'driver' => 'Sürücü', 'guest' => 'Misafir'];
    $maxHour = max(1, max($byHour));
    $maxDist = max(1, count($byDistrict) ? max($byDistrict) : 1);
    $totalDev = max(1, array_sum($byDevice));
    $hourText = $peakHour !== null ? str_pad((string) $peakHour, 2, '0', STR_PAD_LEFT) . ':00' : '—';
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>FerXGo Reklam Raporu — {{ $advertisement->title }}</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Segoe UI",Arial,sans-serif}
    body{background:#eee;color:#1a1a1a}
    .sheet{width:210mm;min-height:297mm;margin:10px auto;background:#fff;padding:16mm 15mm;box-shadow:0 4px 24px rgba(0,0,0,.15)}
    .top{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #0a0a0a;padding-bottom:5mm;margin-bottom:6mm}
    .logo{font-family:"Arial Black",Arial,sans-serif;font-weight:900;font-size:26pt;color:#0a0a0a}
    .logo .g{color:#E0A82E}
    .top .r{text-align:right;font-size:10pt;color:#555}
    .top .r b{color:#0a0a0a;font-size:12pt}
    h1{font-size:16pt;margin-bottom:1mm}
    .sub{color:#666;font-size:10.5pt;margin-bottom:6mm}
    .cards{display:flex;gap:4mm;margin-bottom:7mm}
    .card{flex:1;border:1px solid #e3e3e3;border-radius:10px;padding:5mm;text-align:center;background:#fafafa}
    .card b{display:block;font-size:22pt;color:#0a0a0a;line-height:1}
    .card.gold b{color:#C8901A}
    .card span{display:block;font-size:8.5pt;color:#777;text-transform:uppercase;letter-spacing:1px;margin-top:2mm}
    h2{font-size:11pt;text-transform:uppercase;letter-spacing:1.5px;color:#C8901A;margin:6mm 0 3mm;border-bottom:1px solid #eee;padding-bottom:2mm}
    .bars{display:flex;align-items:flex-end;gap:2px;height:34mm}
    .bar{flex:1;background:linear-gradient(180deg,#F0C040,#D9A621);border-radius:2px 2px 0 0;min-height:1px}
    .hrlabels{display:flex;gap:2px;margin-top:1mm}
    .hrlabels span{flex:1;text-align:center;font-size:5.5pt;color:#999}
    .drow{display:flex;align-items:center;gap:3mm;margin-bottom:2mm}
    .drow .n{width:32mm;font-size:9.5pt;font-weight:600}
    .drow .t{flex:1;background:#f0f0f0;border-radius:4px;height:6mm;overflow:hidden}
    .drow .t i{display:block;height:100%;background:linear-gradient(90deg,#F0C040,#D9A621)}
    .drow .v{width:14mm;text-align:right;font-size:9.5pt;font-weight:700;color:#C8901A}
    .two{display:flex;gap:8mm}
    .two>div{flex:1}
    .pill{display:inline-block;font-size:9pt;background:#faf3df;color:#8a6410;border:1px solid #efd9a0;border-radius:20px;padding:1mm 3mm;margin:0 2mm 2mm 0}
    .insight{background:#fbf7ea;border-left:4px solid #E0A82E;padding:4mm 5mm;border-radius:0 8px 8px 0;font-size:10.5pt;margin:5mm 0}
    .foot{margin-top:8mm;border-top:1px solid #eee;padding-top:3mm;font-size:8.5pt;color:#999;text-align:center}
    .noprint{position:fixed;top:12px;right:12px}
    .btn{background:#0a0a0a;color:#F0C040;border:none;padding:10px 18px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer}
    @media print{ body{background:#fff} .sheet{box-shadow:none;margin:0} .noprint{display:none} @page{size:A4;margin:0} }
</style>
</head>
<body>
<div class="noprint"><button class="btn" onclick="window.print()">🖨️ PDF olarak kaydet</button></div>
<div class="sheet">
    <div class="top">
        <div class="logo">Fer<span class="g">X</span>Go</div>
        <div class="r"><b>Reklam Performans Raporu</b><br>{{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}</div>
    </div>

    <h1>{{ $advertisement->title }}</h1>
    <div class="sub">
        {{ $advertisement->sponsor_name ? $advertisement->sponsor_name . ' · ' : '' }}
        Alan: {{ \App\Modules\Marketing\Models\Advertisement::PLACEMENTS[$advertisement->placement] ?? $advertisement->placement }}
    </div>

    <div class="cards">
        <div class="card"><b>{{ number_format($imp, 0, ',', '.') }}</b><span>Gösterim</span></div>
        <div class="card gold"><b>{{ number_format($clk, 0, ',', '.') }}</b><span>Tıklama</span></div>
        <div class="card"><b>%{{ $ctr }}</b><span>CTR</span></div>
        <div class="card"><b>{{ number_format($uniq, 0, ',', '.') }}</b><span>Tekil Kişi</span></div>
    </div>

    @if ($imp > 0)
    <div class="insight">
        📊 Reklamınız bu dönemde <b>{{ number_format($imp, 0, ',', '.') }}</b> kez görüldü, <b>{{ number_format($clk, 0, ',', '.') }}</b> tıklama aldı (CTR %{{ $ctr }}).
        En yoğun saat <b>{{ $hourText }}</b>@if($peakDistrict), en çok etkileşim <b>{{ $peakDistrict }}</b> ilçesinden@endif.
    </div>
    @endif

    <h2>Saate Göre Görülme (0–23)</h2>
    <div class="bars">
        @foreach ($byHour as $h => $c)
            <div class="bar" style="height:{{ max(1, round($c / $maxHour * 100)) }}%" title="{{ $h }}:00 · {{ $c }}"></div>
        @endforeach
    </div>
    <div class="hrlabels">
        @foreach ($byHour as $h => $c)<span>{{ $h % 3 === 0 ? $h : '' }}</span>@endforeach
    </div>

    <div class="two">
        <div>
            <h2>İlçeye Göre Etkileşim</h2>
            @forelse ($byDistrict as $name => $c)
                <div class="drow"><div class="n">{{ $name }}</div><div class="t"><i style="width:{{ round($c / $maxDist * 100) }}%"></i></div><div class="v">{{ $c }}</div></div>
            @empty
                <div class="sub">Bu dönemde konum verisi oluşmadı.</div>
            @endforelse
        </div>
        <div>
            <h2>Cihaz & Kitle</h2>
            <div style="margin-bottom:4mm">
                @foreach ($byDevice as $d => $c)
                    <span class="pill">{{ $devLabels[$d] ?? ($d ?: 'Bilinmiyor') }}: {{ round($c / $totalDev * 100) }}%</span>
                @endforeach
            </div>
            @foreach ($byAudience as $a => $c)
                <span class="pill">{{ $audLabels[$a] ?? $a }}: {{ number_format($c, 0, ',', '.') }}</span>
            @endforeach
        </div>
    </div>

    <div class="foot">
        FerXGo · İzmir Paylaşımlı Yolculuk Platformu · ferxgo.com.tr<br>
        Rakamlar teknik ölçüme dayanır. Konum verisi yalnızca kullanıcı izni olan etkileşimlerde toplanır (KVKK uyumlu, ham IP saklanmaz).
    </div>
</div>
</body>
</html>
