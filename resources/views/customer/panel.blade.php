<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hesabım · FerXGo</title>
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
<body class="bg-black text-white min-h-screen pb-20 md:pb-0">

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
            <span class="text-2xl font-extrabold tracking-tight">
                <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
            </span>
        </a>
        @php
            $navAvatarUrl = $user->avatar
                ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . ltrim($user->avatar, '/')))
                : null;
        @endphp
        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('reservation.mine') }}"
               class="px-3 py-2 rounded-xl text-xs font-semibold text-zinc-200 hover:text-white border border-white/10 hover:border-white/30 transition">
                Rezervasyonlarım
            </a>
            <form method="POST" action="{{ route('customer.logout') }}" class="inline">
                @csrf
                <button type="submit" class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">Çıkış</button>
            </form>
            <a href="{{ route('customer.profile') }}" title="Profilim"
               class="relative w-10 h-10 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-sm overflow-hidden border-2 border-brand/40 hover:border-brand hover:scale-105 transition shadow-lg shadow-brand/20">
                @if ($navAvatarUrl)
                    <img src="{{ $navAvatarUrl }}" alt="" class="w-full h-full object-cover">
                @else
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                @endif
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8 space-y-6">

    {{-- ===== Profile card ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4 min-w-0">
                @php
                    $headerAvatarUrl = $user->avatar
                        ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . ltrim($user->avatar, '/')))
                        : null;
                @endphp
                <a href="{{ route('customer.profile') }}" class="w-14 h-14 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-xl shrink-0 overflow-hidden hover:opacity-90 transition">
                    @if ($headerAvatarUrl)
                        <img src="{{ $headerAvatarUrl }}" alt="" class="w-full h-full object-cover">
                    @else
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    @endif
                </a>
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

    {{-- ===== Ana çağrı: Araç Çağır (birincil aksiyon) ===== --}}
    <button type="button" id="call-car-btn"
            class="w-full text-left rounded-3xl bg-gradient-to-br from-brand to-brand-600 text-black p-6 flex items-center gap-4 shadow-lg shadow-brand/20 hover:scale-[1.01] active:scale-[0.99] transition">
        <span class="w-14 h-14 rounded-2xl bg-black/10 flex items-center justify-center text-3xl shrink-0">🚗</span>
        <span class="min-w-0 flex-1">
            <span class="block text-2xl font-extrabold leading-tight">Araç Çağır</span>
            <span class="block text-sm font-medium text-black/70 mt-0.5">Nereye gitmek istiyorsun? Favori, havuz ya da kadın sürücü — sen seç.</span>
        </span>
        <span class="text-2xl font-black shrink-0">→</span>
    </button>

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
                $rideStatus === 'driver_arriving'                   => ['Üye sürücü yolda', 'emerald'],
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

            {{-- Canlı takip (driver_arriving / in_progress) --}}
            <div id="live-tracking" class="hidden px-6 py-5 border-b border-white/5 bg-gradient-to-r from-amber-500/15 via-amber-500/5 to-transparent">
                <div class="flex items-center gap-4">
                    <div class="relative shrink-0">
                        <div class="absolute inset-0 rounded-full bg-amber-400/30 animate-ping"></div>
                        <div class="relative w-14 h-14 rounded-full bg-amber-500/20 border-2 border-amber-500/50 flex items-center justify-center text-3xl">
                            🚗
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-amber-300 font-bold mb-0.5" id="tracking-label">Üye Sürücü Yolda</div>
                        <div class="flex items-baseline gap-2">
                            <span id="tracking-eta" class="text-3xl font-extrabold tabular-nums text-white">—</span>
                            <span class="text-sm text-zinc-400">dk kaldı</span>
                        </div>
                        <div class="text-xs text-zinc-400 mt-1">
                            <span id="tracking-distance" class="text-amber-300 font-semibold">—</span> km uzakta
                            <span class="text-zinc-600 mx-1">·</span>
                            son güncelleme <span id="tracking-updated" class="text-zinc-300">—</span>
                        </div>
                        {{-- Distance progress bar --}}
                        <div class="mt-2 h-1.5 rounded-full bg-white/10 overflow-hidden">
                            <div id="tracking-progress" class="h-full bg-gradient-to-r from-amber-500 to-emerald-400 transition-all duration-1000" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
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

                    {{-- İletişim: WhatsApp + Direkt Arama + Uygulama içi arama --}}
                    @php
                        $driverPhoneRaw = $dUser?->phone ?? null;
                        $driverPhoneDigits = $driverPhoneRaw ? preg_replace('/\D+/', '', $driverPhoneRaw) : null;
                        // 0532... → 90532... (WA + tel için baştaki 0'ı 90 yap)
                        if ($driverPhoneDigits && str_starts_with($driverPhoneDigits, '0')) {
                            $driverPhoneIntl = '90' . substr($driverPhoneDigits, 1);
                        } elseif ($driverPhoneDigits && ! str_starts_with($driverPhoneDigits, '90')) {
                            $driverPhoneIntl = '90' . $driverPhoneDigits;
                        } else {
                            $driverPhoneIntl = $driverPhoneDigits;
                        }
                    @endphp
                    @if ($driverPhoneIntl)
                        <div class="flex items-center gap-2 shrink-0">
                            {{-- Uygulama içi sesli arama (WebRTC — CallWidget) --}}
                            <button type="button" id="cust-app-call-btn"
                                    class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-brand hover:bg-brand-600 text-black text-xs font-bold transition shadow-lg shadow-brand/30"
                                    title="Uygulama içi arama">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                                <span class="hidden sm:inline">Ara</span>
                            </button>
                            {{-- Doğrudan telefon (SIM) araması --}}
                            <a href="tel:+{{ $driverPhoneIntl }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-semibold transition"
                               title="Telefondan ara">
                                <svg class="w-4 h-4 text-brand" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                                <span class="hidden sm:inline">SIM</span>
                            </a>
                            {{-- WhatsApp --}}
                            <a href="https://wa.me/{{ $driverPhoneIntl }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold transition shadow-lg shadow-emerald-500/20"
                               title="WhatsApp'tan yaz">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                <span class="hidden sm:inline">WA</span>
                            </a>
                        </div>
                    @endif
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
                            <div class="mb-3" data-photos-wrap>
                                <button type="button" data-photos-toggle
                                        class="w-full inline-flex items-center justify-between gap-2 px-4 py-2.5 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] border border-white/10 hover:border-brand/30 text-xs text-zinc-300 hover:text-white transition">
                                    <span class="inline-flex items-center gap-2">
                                        <span>📸</span>
                                        <span>{{ count($photoUrls) }} araç fotoğrafı</span>
                                    </span>
                                    <span class="text-zinc-500 transition-transform" data-photos-icon>▼</span>
                                </button>
                                <div class="hidden mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2" data-photos-grid>
                                    @foreach ($photoUrls as $url)
                                        <a href="{{ $url }}" target="_blank" class="block rounded-xl overflow-hidden border border-white/10 hover:border-brand/40 transition aspect-[3/2] bg-zinc-900">
                                            <img src="{{ $url }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                        </a>
                                    @endforeach
                                </div>
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

            {{-- Yolculuğu iptal et — yalnızca ride_request (rezervasyon değil) için --}}
            @if ($activeRequest && $activeRequest->public_id)
                <div class="px-6 pb-5 -mt-1">
                    <button type="button" id="active-cancel-btn"
                            data-pid="{{ $activeRequest->public_id }}"
                            class="text-xs font-semibold text-red-300/80 hover:text-red-200 border border-red-500/30 hover:border-red-500/50 bg-red-500/5 hover:bg-red-500/10 rounded-xl px-3 py-2 transition">
                        Yolculuğu iptal et
                    </button>
                </div>
            @endif
        </section>
    @endif

    {{-- Reklam alanı: Yolculuk Takip (Platin · esir dikkat) --}}
    @include('partials.ad-slot', ['placement' => 'ride_tracking'])

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

    {{-- ===== Favori Şoförlerim — "tekrar onu çağır" ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 overflow-hidden" id="favorites-section"
             data-empty="{{ $favoriteDrivers->isEmpty() ? '1' : '0' }}">
        <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
            <div>
                <h2 class="text-sm uppercase tracking-[0.25em] text-zinc-400 font-bold flex items-center gap-2">
                    <span class="text-brand">♥</span> Favori Şoförlerim
                </h2>
                <div class="text-xs text-zinc-500 mt-0.5">Beğendiğin sürücüyü tek dokunuşla tekrar çağır</div>
            </div>
            <span class="text-xs text-zinc-600" id="favorites-count">{{ $favoriteDrivers->count() }} sürücü</span>
        </div>

        {{-- Boş durum --}}
        <div id="favorites-empty" class="p-10 text-center {{ $favoriteDrivers->isEmpty() ? '' : 'hidden' }}">
            <div class="text-4xl mb-3">♡</div>
            <div class="text-sm text-zinc-400 mb-1">Henüz favori şoförün yok.</div>
            <div class="text-xs text-zinc-600">Bir sürücünün profilinde ya da geçmiş yolculukta kalbe dokun, buraya eklensin.</div>
        </div>

        <div id="favorites-grid" class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 {{ $favoriteDrivers->isEmpty() ? 'hidden' : '' }}">
            @foreach ($favoriteDrivers as $fav)
                @php
                    $favUser   = $fav->user;
                    $favVeh    = $fav->currentVehicle;
                    $favVClass = $favVeh?->vehicleClass;
                    $favAvatar = $favUser?->avatar
                        ? (str_starts_with($favUser->avatar, 'http') ? $favUser->avatar : asset('storage/' . ltrim($favUser->avatar, '/')))
                        : null;
                    $favOnline = $fav->availability_status === 'online';
                    $favName   = $favUser?->name ?? 'Sürücü';
                    $favVLabel = $favVeh ? trim(($favVeh->brand ?? '') . ' ' . ($favVeh->model ?? '')) : null;
                @endphp
                <div class="favorite-card relative rounded-2xl border border-white/10 bg-white/[0.02] p-4 flex items-center gap-3"
                     data-driver-id="{{ $fav->id }}">
                    <div class="relative shrink-0">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand to-brand-600 text-black font-extrabold text-lg flex items-center justify-center overflow-hidden border border-brand/30">
                            @if ($favAvatar)
                                <img src="{{ $favAvatar }}" alt="" class="w-full h-full object-cover">
                            @else
                                {{ mb_strtoupper(mb_substr($favName, 0, 1)) }}
                            @endif
                        </div>
                        <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-zinc-950 {{ $favOnline ? 'bg-emerald-400' : 'bg-zinc-600' }}"
                              title="{{ $favOnline ? 'Çevrimiçi' : 'Çevrimdışı' }}"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-white truncate">{{ $favName }}</div>
                        <div class="text-xs text-zinc-500 truncate">
                            <span class="text-brand font-semibold">★ {{ number_format((float) $fav->rating, 2) }}</span>
                            @if ($favVLabel) · {{ $favVLabel }} @endif
                            @if ($favVClass) · {{ $favVClass->name }} @endif
                        </div>
                        <div class="text-[11px] mt-0.5 {{ $favOnline ? 'text-emerald-400' : 'text-zinc-500' }}">
                            {{ $favOnline ? '● Şu an müsait' : '○ Çevrimdışı' }}
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <a href="{{ route('ride.show') }}?prefer_driver={{ $fav->id }}"
                           class="px-3 py-2 rounded-xl bg-brand hover:bg-brand-600 text-black text-xs font-bold transition whitespace-nowrap">
                            Tekrar Çağır
                        </a>
                        <button type="button" class="fav-remove text-[11px] text-zinc-500 hover:text-red-300 transition"
                                data-driver-id="{{ $fav->id }}" title="Favorilerden çıkar">
                            ♥ Çıkar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
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
                            'driver_arriving'  => ['Üye Sürücü Yolda',   'text-brand'],
                            'assigned'         => ['Atanıyor',      'text-zinc-300'],
                            'pending'          => ['Bekliyor',      'text-zinc-400'],
                            'searching'        => ['Üye Sürücü Aranıyor','text-zinc-400'],
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
                        @if ($ride->driver)
                            @php $isFav = in_array($ride->driver->id, $favoriteIds, true); @endphp
                            <button type="button"
                                    class="fav-toggle shrink-0 w-9 h-9 rounded-full border flex items-center justify-center text-base transition
                                           {{ $isFav ? 'bg-brand/15 border-brand/40 text-brand' : 'bg-white/[0.03] border-white/10 text-zinc-500 hover:text-brand hover:border-brand/30' }}"
                                    data-driver-id="{{ $ride->driver->id }}"
                                    data-favorited="{{ $isFav ? '1' : '0' }}"
                                    title="{{ $isFav ? 'Favorilerden çıkar' : 'Favori şoför yap' }}">
                                <span class="fav-icon">{{ $isFav ? '♥' : '♡' }}</span>
                            </button>
                        @endif
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

<script>
(function () {
    'use strict';

    // ===== Araç fotoğrafları toggle =====
    document.querySelectorAll('[data-photos-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const wrap = btn.closest('[data-photos-wrap]');
            if (!wrap) return;
            const grid = wrap.querySelector('[data-photos-grid]');
            const icon = wrap.querySelector('[data-photos-icon]');
            if (!grid) return;
            const willOpen = grid.classList.contains('hidden');
            grid.classList.toggle('hidden');
            if (icon) icon.style.transform = willOpen ? 'rotate(180deg)' : '';
        });
    });

    // ===== Canlı sürücü takibi =====
    const TRACKING_URL = '{{ route('customer.api.tracking') }}';
    const trackingEl  = document.getElementById('live-tracking');
    if (!trackingEl) return;

    const etaEl   = document.getElementById('tracking-eta');
    const distEl  = document.getElementById('tracking-distance');
    const updEl   = document.getElementById('tracking-updated');
    const labelEl = document.getElementById('tracking-label');
    const progressEl = document.getElementById('tracking-progress');

    let initialDistance = null;
    let lastUpdatedAt   = null;
    let serverEta       = null;
    let lastFetchAt     = null;
    let countdownHandle = null;

    function formatRelative(iso) {
        if (!iso) return '—';
        const then = new Date(iso).getTime();
        if (isNaN(then)) return '—';
        const diff = Math.max(0, (Date.now() - then) / 1000);
        if (diff < 5) return 'az önce';
        if (diff < 60) return Math.floor(diff) + ' sn önce';
        if (diff < 3600) return Math.floor(diff / 60) + ' dk önce';
        return Math.floor(diff / 3600) + ' sa önce';
    }

    function renderTick() {
        if (serverEta === null || lastFetchAt === null) return;
        // Saniye-saniye azalan ETA (server 5sn'de bir günceller, biz arada interpolate ederiz)
        const elapsedSec = (Date.now() - lastFetchAt) / 1000;
        const remainingMin = Math.max(0, serverEta - (elapsedSec / 60));
        const display = remainingMin < 1 ? '<1' : Math.ceil(remainingMin);
        if (etaEl) etaEl.textContent = display;
        if (updEl) updEl.textContent = formatRelative(lastUpdatedAt);
    }

    async function pollTracking() {
        try {
            const res = await fetch(TRACKING_URL, { headers: { 'Accept': 'application/json' } });
            if (res.status === 401) return;
            const data = await res.json();
            if (!data.success || !data.tracking) {
                trackingEl.classList.add('hidden');
                return;
            }
            const t = data.tracking;
            const isMoving = ['driver_arriving', 'in_progress', 'assigned'].includes(t.ride_status);
            if (!isMoving) {
                trackingEl.classList.add('hidden');
                return;
            }
            trackingEl.classList.remove('hidden');

            if (labelEl) {
                labelEl.textContent = t.ride_status === 'in_progress'
                    ? 'Hedefe Doğru'
                    : (t.ride_status === 'assigned' ? 'Üye Sürücü Atanıyor' : 'Üye Sürücü Yolda');
            }

            serverEta     = t.eta_minutes;
            lastUpdatedAt = t.last_updated;
            lastFetchAt   = Date.now();

            if (initialDistance === null || initialDistance < t.distance_km) {
                initialDistance = t.distance_km;
            }
            if (distEl)  distEl.textContent = (t.distance_km || 0).toFixed(1);

            // Progress: ne kadar yol kat etti?
            if (progressEl && initialDistance && initialDistance > 0) {
                const covered = Math.max(0, initialDistance - t.distance_km);
                const pct = Math.min(100, Math.max(0, (covered / initialDistance) * 100));
                progressEl.style.width = pct.toFixed(0) + '%';
            }

            renderTick();
        } catch (_) {}
    }

    pollTracking();
    setInterval(pollTracking, 5000);

    // Saniye-saniye smooth ETA azaltma
    if (countdownHandle) clearInterval(countdownHandle);
    countdownHandle = setInterval(renderTick, 1000);
})();
</script>

<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    async function toggleFavorite(driverId) {
        const res = await fetch(`/musteri-paneli/favori/${driverId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        return res.json().catch(() => ({ ok: false, message: 'Bağlantı hatası.' }));
    }

    // Tüm yüzeylerdeki (geçmiş yolculuk satırı) kalpleri senkronize et.
    function syncHearts(driverId, favorited) {
        document.querySelectorAll(`.fav-toggle[data-driver-id="${driverId}"]`).forEach(btn => {
            btn.dataset.favorited = favorited ? '1' : '0';
            btn.title = favorited ? 'Favorilerden çıkar' : 'Favori şoför yap';
            const icon = btn.querySelector('.fav-icon');
            if (icon) icon.textContent = favorited ? '♥' : '♡';
            btn.classList.toggle('bg-brand/15', favorited);
            btn.classList.toggle('border-brand/40', favorited);
            btn.classList.toggle('text-brand', favorited);
            btn.classList.toggle('bg-white/[0.03]', !favorited);
            btn.classList.toggle('border-white/10', !favorited);
            btn.classList.toggle('text-zinc-500', !favorited);
        });
    }

    function removeFavoriteCard(driverId) {
        const card = document.querySelector(`.favorite-card[data-driver-id="${driverId}"]`);
        if (card) card.remove();
        const grid = document.getElementById('favorites-grid');
        const empty = document.getElementById('favorites-empty');
        const countEl = document.getElementById('favorites-count');
        const remaining = grid ? grid.querySelectorAll('.favorite-card').length : 0;
        if (countEl) countEl.textContent = remaining + ' sürücü';
        if (remaining === 0) {
            grid?.classList.add('hidden');
            empty?.classList.remove('hidden');
        }
    }

    document.addEventListener('click', async (e) => {
        // Geçmiş yolculuk satırındaki kalp → ekle/çıkar
        const toggle = e.target.closest('.fav-toggle');
        if (toggle) {
            e.preventDefault();
            if (toggle.dataset.busy === '1') return;
            toggle.dataset.busy = '1';
            const id = toggle.dataset.driverId;
            const data = await toggleFavorite(id);
            toggle.dataset.busy = '';
            if (!data.ok) { alert(data.message || 'İşlem başarısız.'); return; }
            syncHearts(id, data.favorited);
            if (data.favorited) location.reload(); // yeni kart listede görünsün
            return;
        }

        // Favori kartındaki "Çıkar"
        const remove = e.target.closest('.fav-remove');
        if (remove) {
            e.preventDefault();
            if (remove.dataset.busy === '1') return;
            remove.dataset.busy = '1';
            const id = remove.dataset.driverId;
            const data = await toggleFavorite(id);
            remove.dataset.busy = '';
            if (!data.ok) { alert(data.message || 'İşlem başarısız.'); return; }
            syncHearts(id, false);
            removeFavoriteCard(id);
        }
    });
})();
</script>

{{-- Aktif yolculukta sürücü ile sesli görüşme (WebRTC) --}}
@if (isset($activeRequest) && $activeRequest)
<script>
(function () {
    // CallWidget'ın kullanacağı global hook'lar
    window.callWidgetGetPublicId = () => @json($activeRequest->public_id);
    window.callWidgetGetPeerName = () => @json($dUser?->name ?? 'Sürücü');
    // 'Ara' butonuna tıklanınca widget açılsın
    const appCallBtn = document.getElementById('cust-app-call-btn');
    if (appCallBtn) {
        appCallBtn.addEventListener('click', () => {
            if (window.CallWidget) window.CallWidget.start();
        });
    }
})();
</script>
@include('partials.call-widget')
@endif

@include('partials.mobile-action-bar')

{{-- ACİL YARDIM (panic) butonu — aktif yolculuk varken (talep kabul/atama sürecinde de) --}}
@if ($activeRequest || $activeRide)
<button type="button" id="customer-panic-btn"
        class="fixed bottom-24 right-4 z-[100] w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 text-white shadow-2xl shadow-red-500/50 border-2 border-white/20 flex items-center justify-center text-2xl font-bold animate-pulse"
        aria-label="Acil yardım">
    🚨
</button>

@include('partials.panic-webrtc')

{{-- Sistem-içi acil yardım modal'ı (tarayıcı alert/confirm yerine) --}}
<div id="cust-panic-modal" class="fixed inset-0 z-[110] items-center justify-center p-4"
     style="display:none; background:rgba(70,0,0,.75); backdrop-filter:blur(2px);">
    <div class="w-full max-w-sm rounded-2xl bg-white text-gray-900 shadow-2xl overflow-hidden">
        <div id="cust-panic-head" class="px-5 py-4 text-center text-white" style="background:#dc2626;">
            <div class="text-3xl leading-none mb-1">🚨</div>
            <h3 id="cust-panic-title" class="text-lg font-extrabold">ACİL YARDIM</h3>
        </div>
        <div class="px-5 py-4 text-center">
            <p id="cust-panic-body" class="text-sm text-gray-700 leading-relaxed"></p>
            <div id="cust-panic-actions" class="mt-5 flex flex-col gap-2"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const btn = document.getElementById('customer-panic-btn');
    const modal = document.getElementById('cust-panic-modal');
    if (!btn || !modal) return;

    const head    = document.getElementById('cust-panic-head');
    const titleEl = document.getElementById('cust-panic-title');
    const bodyEl  = document.getElementById('cust-panic-body');
    const actions = document.getElementById('cust-panic-actions');

    const openModal  = () => { modal.style.display = 'flex'; };
    const closeModal = () => {
        if (window.PanicRTC && window.PanicRTC.isActive()) window.PanicRTC.hangup(true);
        modal.style.display = 'none'; actions.innerHTML = '';
    };

    // Alarm iletildikten sonra destek çalışanını WebRTC ile "arar" (kişi = arayan)
    function startSupportCall(publicId) {
        if (!window.PanicRTC || !publicId) return;
        var muteBtn = btnEl('🔇 Mikrofonu Kapat', 'bg-gray-100 hover:bg-gray-200 text-gray-700', function () {
            var m = window.PanicRTC.toggleMute();
            muteBtn.textContent = m ? '🎙️ Mikrofonu Aç' : '🔇 Mikrofonu Kapat';
        });
        var endBtn = btnEl('📴 Görüşmeyi Bitir', 'bg-red-600 hover:bg-red-700 text-white', function () {
            window.PanicRTC.hangup(true); closeModal();
        });
        window.PanicRTC.start({
            role: 'caller',
            pushUrl: '{{ url('/api/panic') }}/' + publicId + '/signal',
            pullUrl: '{{ url('/api/panic') }}/' + publicId + '/signals',
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            onStatus: function (s) {
                if (s === 'connecting') bodyEl.textContent = 'Destek ekibi aranıyor, lütfen hattı açık tutun…';
                else if (s === 'active') bodyEl.textContent = '🟢 Destek ekibiyle görüşüyorsunuz. Sakin olun, buradayız.';
                else if (s === 'mic-error') bodyEl.textContent = 'Mikrofon açılamadı. Aşağıdaki butonla arayın.';
                else if (s === 'failed') bodyEl.textContent = 'Sesli bağlantı kurulamadı. Aşağıdan arayın.';
            },
        }).catch(function () {});
        actions.innerHTML = '';
        actions.appendChild(muteBtn);
        actions.appendChild(endBtn);
        actions.appendChild(linkEl('📞 Çağrı Merkezini Ara', 'tel:' + CALL_CENTER, 'bg-green-600 hover:bg-green-700 text-white'));
    }

    function btnEl(label, cls, onClick) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'w-full rounded-xl py-3 font-bold text-sm ' + cls;
        b.textContent = label;
        b.addEventListener('click', onClick);
        return b;
    }
    function linkEl(label, href, cls) {
        const a = document.createElement('a');
        a.href = href;
        a.className = 'w-full rounded-xl py-3 font-bold text-sm text-center block ' + cls;
        a.textContent = label;
        return a;
    }

    function showConfirm() {
        head.style.background = '#dc2626';
        titleEl.textContent = 'ACİL YARDIM';
        bodyEl.textContent = 'Çağrı merkezi sizinle HEMEN iletişime geçecek. Acil bir durumdaysanız devam edin.';
        actions.innerHTML = '';
        actions.appendChild(btnEl('🚨 EVET, ACİL YARDIM İSTİYORUM', 'bg-red-600 hover:bg-red-700 text-white', sendPanic));
        actions.appendChild(btnEl('Vazgeç', 'bg-gray-100 hover:bg-gray-200 text-gray-700', closeModal));
        openModal();
    }

    function showResult(ok, message, call, publicId) {
        head.style.background = ok ? '#16a34a' : '#dc2626';
        titleEl.textContent = ok ? '✓ Alarm İletildi' : 'Bağlantı Sorunu';
        bodyEl.textContent = ok
            ? (message || 'Çağrı merkezi alarmınızı aldı. Destek ekibi bağlanıyor…')
            : (message || 'İstek gönderilemedi. Lütfen doğrudan arayın.');
        actions.innerHTML = '';
        if (ok && publicId && window.PanicRTC) {
            startSupportCall(publicId);
            return;
        }
        const phone = call || '+908503403039';
        actions.appendChild(linkEl('📞 Çağrı Merkezini Ara', 'tel:' + phone, 'bg-green-600 hover:bg-green-700 text-white'));
        actions.appendChild(btnEl('Kapat', 'bg-gray-100 hover:bg-gray-200 text-gray-700', closeModal));
    }

    // ── İnternetsiz dayanıklılık: alarm cihazda kuyruğa alınır, bağlantı gelince otomatik gönderilir ──
    const PANIC_URL = '{{ url('/api/panic') }}';
    const CALL_CENTER = '+908503403039';
    const QKEY = 'ferxgo_panic_queue';

    const qGet = () => { try { return JSON.parse(localStorage.getItem(QKEY) || '[]'); } catch (_) { return []; } };
    const qSet = (q) => { try { localStorage.setItem(QKEY, JSON.stringify(q)); } catch (_) {} };
    function qPush(payload) { const q = qGet(); q.push(payload); qSet(q); }

    async function postPanic(payload) {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch(PANIC_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!res.ok) throw new Error('http ' + res.status);
        return await res.json();
    }

    async function flushQueue() {
        const q = qGet();
        if (!q.length) return;
        const remaining = [];
        for (const p of q) {
            try { await postPanic(p); } catch (_) { remaining.push(p); }
        }
        qSet(remaining);
    }
    window.addEventListener('online', flushQueue);
    setInterval(flushQueue, 20000);
    flushQueue();

    function showOffline(payload) {
        head.style.background = '#b45309';
        titleEl.textContent = '⚠️ İnternet Yok';
        bodyEl.textContent = 'Alarmın cihazına kaydedildi ve bağlantı gelir gelmez otomatik gönderilecek. İnternet olmadan da hemen ulaşmak için:';
        actions.innerHTML = '';
        var smsBody = 'ACIL YARDIM! FERXGO yolcu. Konum: '
            + (payload.lat ? payload.lat + ',' + payload.lng : 'bilinmiyor') + '. Lutfen hemen arayin.';
        actions.appendChild(linkEl('📞 Çağrı Merkezini Ara', 'tel:' + CALL_CENTER, 'bg-green-600 hover:bg-green-700 text-white'));
        actions.appendChild(linkEl('✉️ SMS ile Bildir', 'sms:' + CALL_CENTER + '?body=' + encodeURIComponent(smsBody), 'bg-blue-600 hover:bg-blue-700 text-white'));
        actions.appendChild(btnEl('Kapat', 'bg-gray-100 hover:bg-gray-200 text-gray-700', closeModal));
    }

    async function sendPanic() {
        actions.innerHTML = '';
        bodyEl.textContent = 'Alarm gönderiliyor…';
        let lat = null, lng = null, acc = null;
        if (navigator.geolocation) {
            try {
                const pos = await new Promise((res, rej) => navigator.geolocation.getCurrentPosition(res, rej, { timeout: 4000 }));
                lat = pos.coords.latitude; lng = pos.coords.longitude; acc = pos.coords.accuracy;
            } catch (_) {}
        }
        const payload = {
            triggered_by_type: 'customer',
            ride_request_public_id: '{{ $activeRequest?->public_id ?? '' }}',
            lat, lng, location_accuracy_m: acc,
        };
        try {
            const data = await postPanic(payload);
            showResult(!!data.success, data.message, data.call, data.alert_id);
        } catch (err) {
            qPush(payload);
            showOffline(payload);
        }
    }

    btn.addEventListener('click', showConfirm);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
})();
</script>
@endif

{{-- ===== Araç Çağır CTA + Aktif yolculuğu iptal ===== --}}
<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // "Araç Çağır" → radar iframe içindeki Hızlı Seç modalını aç.
    const callBtn = document.getElementById('call-car-btn');
    if (callBtn) {
        callBtn.addEventListener('click', () => {
            const ifr = document.getElementById('radar-iframe');
            if (ifr && ifr.contentWindow) {
                ifr.contentWindow.postMessage({ type: 'ferogo:open-booking' }, '*');
                ifr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    // "Yolculuğu iptal et" → aktif ride_request'i iptal et, sonra sayfayı yenile.
    const cancelBtn = document.getElementById('active-cancel-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', async () => {
            const pid = cancelBtn.dataset.pid;
            if (!pid) return;
            if (!confirm('Yolculuğu iptal etmek istediğine emin misin?')) return;
            cancelBtn.disabled = true;
            cancelBtn.textContent = 'İptal ediliyor…';
            try {
                const res = await fetch(`{{ url('/api/ride-requests') }}/${encodeURIComponent(pid)}/cancel`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (res.ok) {
                    location.reload();
                    return;
                }
            } catch (_) {}
            // Hata: butonu geri aç
            cancelBtn.disabled = false;
            cancelBtn.textContent = 'Yolculuğu iptal et';
            alert('İptal edilemedi. Lütfen tekrar dene.');
        });
    }
})();
</script>

</body>
</html>
