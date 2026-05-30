<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hesabım · Ferogo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { brand: { DEFAULT: '#F0C040', 500: '#F0C040', 600: '#D9A621' } } } }
        }
    </script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-black text-white min-h-screen">

@php
    $trustStyles = [
        'guvenilir'  => ['Güvenilir Müşteri', 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'],
        'normal'     => ['Standart',           'bg-zinc-500/15 text-zinc-300 border-zinc-500/30'],
        'riskli'     => ['Riskli',             'bg-amber-500/15 text-amber-300 border-amber-500/30'],
        'cok_riskli' => ['Çok Riskli',         'bg-red-500/15 text-red-300 border-red-500/30'],
    ];
    $label = $trustStyles[$trust->trustLabel()] ?? $trustStyles['normal'];
@endphp

<header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
    <div class="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between gap-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold">F</div>
            <span class="font-bold truncate">Ferogo</span>
        </a>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('ride.show') }}" class="px-3 py-2 rounded-xl text-xs font-semibold bg-brand hover:bg-brand-600 text-black transition">Yolculuk Yap</a>
            <form method="POST" action="{{ route('customer.logout') }}" class="inline">
                @csrf
                <button type="submit" class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">Çıkış</button>
            </form>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8 space-y-6">

    {{-- ===== Profile card ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-xl shrink-0">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold truncate">Merhaba, {{ $user->name }}</h1>
                    <div class="text-sm text-zinc-500 truncate">
                        +90 {{ $user->phone }}
                        @if ($user->phone_verified_at)
                            · <span class="text-emerald-400">Doğrulandı</span>
                        @endif
                    </div>
                </div>
            </div>
            <span class="px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider border {{ $label[1] }}">
                {{ $label[0] }}
            </span>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-3 mt-6">
            <div class="bg-white/[0.03] rounded-2xl p-4 border border-white/5">
                <div class="text-[10px] uppercase tracking-wider text-zinc-500">Güven Skoru</div>
                <div class="text-2xl font-bold mt-1">{{ $trust->trust_score }}<span class="text-xs text-zinc-500">/100</span></div>
            </div>
            <div class="bg-white/[0.03] rounded-2xl p-4 border border-white/5">
                <div class="text-[10px] uppercase tracking-wider text-zinc-500">Tamamlanan</div>
                <div class="text-2xl font-bold mt-1 text-emerald-300">{{ $trust->total_completed }}</div>
            </div>
            <div class="bg-white/[0.03] rounded-2xl p-4 border border-white/5">
                <div class="text-[10px] uppercase tracking-wider text-zinc-500">No-show</div>
                <div class="text-2xl font-bold mt-1 {{ $trust->total_no_shows > 0 ? 'text-red-300' : 'text-zinc-500' }}">
                    {{ $trust->total_no_shows }}
                </div>
            </div>
        </div>

        @if ($trust->isBanned())
            <div class="mt-4 p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-sm text-red-200">
                <div class="font-bold mb-1">Hesabın geçici olarak kısıtlı</div>
                <div class="text-xs">
                    {{ $trust->ban_reason ?? 'Çok sayıda no-show kaydı.' }}
                    @if ($trust->banned_until)
                        Tekrar deneme: <span class="font-semibold">{{ $trust->banned_until->format('d.m.Y H:i') }}</span>
                    @endif
                </div>
            </div>
        @endif
    </section>

    {{-- ===== Aktif Yolculuk — zengin kart ===== --}}
    @if ($activeRequest || $activeRide)
        @php
            $ar       = $activeRequest;
            $ride     = $ar?->ride ?? $activeRide;
            $driver   = $ar?->acceptedDriver;
            $dUser    = $driver?->user;
            $vehicle  = $driver?->currentVehicle;
            $vClass   = $vehicle?->vehicleClass ?? $ride?->vehicleClass;
            $photos   = is_array($vehicle?->photos) ? array_values(array_filter($vehicle->photos)) : [];
            $photoUrls = array_map(function ($p) {
                return str_starts_with($p, 'http') ? $p : asset('storage/' . ltrim($p, '/'));
            }, $photos);

            $expBandLabels = [
                'under_1' => '1 yıldan az',
                '1_to_3'  => '1-3 yıl',
                '3_to_5'  => '3-5 yıl',
                '5_plus'  => '5+ yıl',
            ];
            $expLabel = $driver ? ($expBandLabels[$driver->experience_band] ?? null) : null;

            // Status banner
            $rideStatus = $ride?->status;
            $statusBanner = match (true) {
                $ar && $ar->status === 'pending'                    => ['Sürücüye iletildi, yanıt bekleniyor', 'amber'],
                $rideStatus === 'driver_arriving'                   => ['Şoför yolda', 'emerald'],
                $rideStatus === 'in_progress'                       => ['Yolculukta', 'brand'],
                $rideStatus === 'assigned' || $rideStatus === 'searching' => ['Sürücü atanıyor', 'amber'],
                default                                             => ['Aktif', 'emerald'],
            };
            $bannerColor = $statusBanner[1];
            $bannerCls = [
                'emerald' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                'amber'   => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                'brand'   => 'bg-brand/15 text-brand border-brand/30',
            ][$bannerColor] ?? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
        @endphp

        <section class="rounded-3xl border border-emerald-500/30 bg-gradient-to-br from-emerald-500/[0.08] via-emerald-500/[0.03] to-transparent overflow-hidden">
            {{-- Status banner --}}
            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between gap-3 flex-wrap">
                <div class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.25em] font-bold text-emerald-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Aktif Yolculuk
                </div>
                <span class="px-3 py-1 rounded-full border text-[11px] font-bold uppercase tracking-wider {{ $bannerCls }}">
                    {{ $statusBanner[0] }}
                </span>
            </div>

            @if ($driver)
                {{-- Driver hero --}}
                <div class="px-6 py-5 border-b border-white/5 flex items-center gap-4 flex-wrap">
                    @php
                        $avatarUrl = $dUser?->avatar
                            ? (str_starts_with($dUser->avatar, 'http') ? $dUser->avatar : asset('storage/' . ltrim($dUser->avatar, '/')))
                            : null;
                    @endphp
                    <div class="w-16 h-16 rounded-2xl border-2 border-brand/40 bg-gradient-to-br from-brand to-brand-600 text-black font-extrabold text-2xl flex items-center justify-center shrink-0 overflow-hidden">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ mb_strtoupper(mb_substr($dUser?->name ?? 'S', 0, 1)) }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-xl font-bold text-white truncate">{{ $dUser?->name ?? 'Sürücü' }}</h2>
                            @if ($expLabel)
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-brand/15 text-brand border border-brand/30">
                                    {{ $expLabel }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-sm">
                            <span class="text-brand font-bold">★ {{ number_format((float) $driver->rating, 2) }}</span>
                            <span class="text-zinc-500">·</span>
                            <span class="text-zinc-400">{{ number_format((int) $driver->total_rides, 0, ',', '.') }} yolculuk</span>
                        </div>
                    </div>
                </div>

                {{-- Vehicle --}}
                @if ($vehicle)
                    <div class="px-6 py-5 border-b border-white/5">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500 mb-3">Araç</div>
                        <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                            <div class="min-w-0">
                                <div class="text-base font-bold text-white">
                                    {{ trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: 'Araç' }}
                                </div>
                                <div class="text-xs text-zinc-400 mt-0.5">
                                    {{ collect([$vClass?->name, $vehicle->year_of_manufacture, $vehicle->color])->filter()->join(' · ') ?: '—' }}
                                </div>
                            </div>
                            @if ($vehicle->plate)
                                <div class="px-3 py-1.5 rounded-lg bg-brand/15 border border-brand/30 text-brand font-bold tabular-nums">
                                    {{ $vehicle->plate }}
                                </div>
                            @endif
                        </div>

                        @if (count($photoUrls) > 0)
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                                @foreach ($photoUrls as $url)
                                    <a href="{{ $url }}" target="_blank" class="block rounded-xl overflow-hidden border border-white/10 hover:border-brand/40 transition aspect-[3/2] bg-zinc-900">
                                        <img src="{{ $url }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @php
                            $features = [];
                            if ($vehicle->has_baby_seat)    $features[] = ['👶', 'Bebek koltuğu'];
                            if ($vehicle->has_child_seat)   $features[] = ['🧒', 'Çocuk koltuğu'];
                            if ($vehicle->has_booster_seat) $features[] = ['🪑', 'Yükseltici'];
                            if ($vehicle->pet_friendly)     $features[] = ['🐾', 'Evcil hayvan dostu'];
                        @endphp
                        @if (count($features))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($features as [$icon, $label])
                                    <span class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300">
                                        <span>{{ $icon }}</span><span>{{ $label }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            {{-- Route --}}
            @if ($ride || $ar)
                <div class="px-6 py-5 border-b border-white/5">
                    <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500 mb-3">Güzergah</div>
                    <div class="bg-black/30 rounded-2xl p-4 space-y-3 border border-white/5">
                        <div class="flex items-start gap-3">
                            <div class="flex flex-col items-center pt-1">
                                <div class="w-3 h-3 rounded-full bg-brand"></div>
                                <div class="w-px h-6 bg-white/15 my-1"></div>
                                <div class="w-3 h-3 rounded-sm bg-white"></div>
                            </div>
                            <div class="flex-1 min-w-0 space-y-3">
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Alış</div>
                                    <div class="text-sm text-white">{{ $ride?->pickup_address ?? $ar?->pickup_address ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Bırakış</div>
                                    <div class="text-sm text-white">{{ $ride?->dropoff_address ?? $ar?->dropoff_address ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Stats --}}
            <div class="px-6 py-5 grid grid-cols-3 gap-3">
                <div class="bg-white/[0.03] rounded-2xl p-4 border border-white/5">
                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Mesafe</div>
                    <div class="text-lg font-bold text-white mt-1">
                        {{ number_format((float) ($ride?->estimated_distance_km ?? $ar?->distance_km ?? 0), 1) }} km
                    </div>
                </div>
                <div class="bg-white/[0.03] rounded-2xl p-4 border border-white/5">
                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Süre</div>
                    <div class="text-lg font-bold text-white mt-1">
                        {{ (int) ($ride?->estimated_duration_minutes ?? $ar?->duration_minutes ?? 0) }} dk
                    </div>
                </div>
                <div class="bg-white/[0.03] rounded-2xl p-4 border border-white/5">
                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Ücret</div>
                    <div class="text-lg font-bold text-brand mt-1">
                        ₺{{ number_format((float) ($ride?->total_fare ?? $ar?->estimated_fare ?? 0), 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Canlı Radar (yolculuk-yapin sayfasının embed versiyonu) ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 overflow-hidden">
        <div class="px-5 py-4 border-b border-white/5">
            <div class="text-sm uppercase tracking-[0.25em] text-zinc-400 font-bold">Canlı Radar</div>
            <div class="text-xs text-zinc-500 mt-0.5">Bölgendeki sürücüler · "Seç"e bas, modal açılır</div>
        </div>

        @php
            $activePid = $activeRequest?->public_id;
        @endphp
        <iframe id="radar-iframe"
                src="{{ route('ride.show') }}?embed=1{{ $activePid ? '&active_request=' . urlencode($activePid) : '' }}"
                class="w-full block border-0"
                style="height: 900px; background: #0a0a0a; overflow: hidden;"
                scrolling="no"
                title="Canlı sürücü radarı"
                allow="geolocation"
                referrerpolicy="same-origin"></iframe>
        <script>
            // iframe içindeki sayfa postMessage ile yüksekliğini bildirir → scroll çıkmaz
            (function () {
                const ifr = document.getElementById('radar-iframe');
                if (!ifr) return;
                window.addEventListener('message', (e) => {
                    if (!e.data || e.data.type !== 'ferogo:iframe-height') return;
                    const h = parseInt(e.data.height, 10);
                    if (h > 200 && h < 5000) ifr.style.height = h + 'px';
                });
            })();
        </script>
    </section>

    {{-- ===== Recent rides ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 overflow-hidden">
        <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
            <h2 class="text-sm uppercase tracking-[0.25em] text-zinc-400 font-bold">Son Yolculuklar</h2>
            <span class="text-xs text-zinc-600">{{ $recentRides->count() }} kayıt</span>
        </div>
        @if ($recentRides->isEmpty())
            <div class="p-10 text-center">
                <div class="text-4xl mb-3">🚗</div>
                <div class="text-sm text-zinc-400 mb-1">Henüz yolculuk geçmişin yok.</div>
                <div class="text-xs text-zinc-600">İlk yolculuğunu yapmak için "Yolculuk Yap"a bas.</div>
            </div>
        @else
            <ul class="divide-y divide-white/5">
                @foreach ($recentRides as $ride)
                    @php
                        $statusStyles = [
                            'completed'        => ['Tamamlandı',   'text-emerald-300'],
                            'cancelled'        => ['İptal',         'text-zinc-500'],
                            'no_show'          => ['No-Show',       'text-red-300'],
                            'in_progress'      => ['Yolculukta',    'text-brand'],
                            'driver_arriving'  => ['Şoför Yolda',   'text-brand'],
                            'assigned'         => ['Atanıyor',      'text-zinc-300'],
                            'pending'          => ['Bekliyor',      'text-zinc-400'],
                            'searching'        => ['Şoför Aranıyor','text-zinc-400'],
                        ];
                        $sLabel = $statusStyles[$ride->status] ?? [$ride->status, 'text-zinc-400'];
                    @endphp
                    <li class="px-5 py-4 flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="text-sm text-white truncate">{{ $ride->pickup_address }} → {{ $ride->dropoff_address }}</div>
                            <div class="text-xs text-zinc-500 mt-0.5">
                                {{ $ride->created_at->format('d.m.Y H:i') }}
                                @if ($ride->driver?->user)
                                    · {{ $ride->driver->user->name }}
                                @endif
                                @if ($ride->vehicleClass)
                                    · {{ $ride->vehicleClass->name }}
                                @endif
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm font-bold text-brand">₺{{ number_format((float) $ride->total_fare, 0, ',', '.') }}</div>
                            <div class="text-[11px] font-semibold {{ $sLabel[1] }}">{{ $sLabel[0] }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ===== Trust info ===== --}}
    <section class="rounded-3xl border border-white/5 bg-gradient-to-br from-brand/10 to-transparent p-6">
        <div class="text-xs uppercase tracking-[0.25em] text-brand mb-3">Güven Skoru Nasıl İşler?</div>
        <ul class="text-sm text-zinc-300 space-y-2">
            <li class="flex items-start gap-2"><span class="text-emerald-400">+</span> Her tamamlanan yolculuk: <span class="text-emerald-300 font-semibold">+10 puan</span></li>
            <li class="flex items-start gap-2"><span class="text-red-400">−</span> "Müşteri gelmedi" raporu: <span class="text-red-300 font-semibold">−30 puan</span></li>
            <li class="flex items-start gap-2"><span class="text-amber-400">−</span> Sürücü yola çıktıktan sonra iptal: <span class="text-amber-300 font-semibold">−10 puan</span></li>
            <li class="flex items-start gap-2"><span class="text-zinc-500">·</span> Skor 50 ile başlar. 25 altında riskli kullanıcı olarak işaretlenirsin.</li>
        </ul>
    </section>

</main>

</body>
</html>
