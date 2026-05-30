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

    {{-- ===== Active ride/request ===== --}}
    @if ($activeRequest || $activeRide)
        <section class="rounded-3xl border border-emerald-500/30 bg-emerald-500/[0.05] p-6">
            <div class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.25em] text-emerald-300 font-bold mb-3">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                Aktif Yolculuk
            </div>
            @if ($activeRequest)
                <div class="text-sm text-zinc-300 mb-1">
                    Durum: <span class="font-semibold text-white">
                        @switch($activeRequest->status)
                            @case('pending') Sürücüye iletildi, yanıt bekleniyor @break
                            @case('accepted') Sürücü kabul etti — yola çıktı @break
                            @default {{ $activeRequest->status }}
                        @endswitch
                    </span>
                </div>
                @if ($activeRequest->acceptedDriver?->user)
                    <div class="text-xs text-zinc-400">Sürücü: {{ $activeRequest->acceptedDriver->user->name }}</div>
                @endif
            @elseif ($activeRide)
                <div class="text-sm text-zinc-300">
                    {{ $activeRide->pickup_address }} → {{ $activeRide->dropoff_address }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Durum: {{ $activeRide->status }}</div>
            @endif

            <a href="{{ route('ride.show') }}" class="inline-block mt-4 text-xs text-brand hover:text-brand-600 underline underline-offset-2">
                Detayları gör →
            </a>
        </section>
    @endif

    {{-- ===== Canlı Radar (yolculuk-yapin sayfasının embed versiyonu) ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 overflow-hidden">
        <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm uppercase tracking-[0.25em] text-zinc-400 font-bold">Canlı Radar</div>
                <div class="text-xs text-zinc-500 mt-0.5">Bölgendeki sürücüler · "Seç"e bas, modal açılır</div>
            </div>
            <a href="{{ route('ride.show') }}" target="_blank"
               class="text-xs text-brand hover:text-brand-600 underline underline-offset-2 shrink-0">
                Tam sayfa aç →
            </a>
        </div>

        @php
            $activePid = $activeRequest?->public_id;
        @endphp
        <iframe src="{{ route('ride.show') }}?embed=1{{ $activePid ? '&active_request=' . urlencode($activePid) : '' }}"
                class="w-full block border-0"
                style="height: 900px; background: #0a0a0a;"
                title="Canlı sürücü radarı"
                allow="geolocation"
                referrerpolicy="same-origin"></iframe>
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
