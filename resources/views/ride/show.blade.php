@extends('layouts.public')

@section('title', 'Yolculuk Yap · Ferogo · Paylaşımlı Yolculuk')
@section('description', 'Şehir içi, havalimanı veya uzun mesafe — bağımsız üye sürücüler, konforlu araçlar, şeffaf katkı payı. 60 saniyede paylaşımlı yolculuk eşleştirmesi.')

@php
    /** @var \App\Models\User|null $authedCustomer */
    $authedCustomer = auth()->user();
    $authedCustomer = ($authedCustomer && $authedCustomer->type === 'customer') ? $authedCustomer : null;

    $authedTrust = null;
    if ($authedCustomer) {
        $authedTrust = \App\Modules\Booking\Models\CustomerTrust::where('phone', $authedCustomer->phone)->first();
    }

    // Embed modu: müşteri paneline iframe ile gömüldüğünde global nav + ekstra section'lar gizlenir, sadece radar + modal kalır.
    $embed = request()->boolean('embed');
@endphp

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    .ride-mesh {
        background:
            radial-gradient(circle at 20% 15%, rgba(240,192,64,0.20) 0%, transparent 38%),
            radial-gradient(circle at 80% 25%, rgba(240,192,64,0.12) 0%, transparent 42%),
            radial-gradient(circle at 50% 95%, rgba(240,192,64,0.10) 0%, transparent 45%),
            #0a0a0a;
    }
    .ride-noise {
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='240' height='240' viewBox='0 0 240 240'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/><feColorMatrix values='0 0 0 0 0.94  0 0 0 0 0.75  0 0 0 0 0.25  0 0 0 0.045 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
    }
    @keyframes drift-1 {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(28px, -22px) rotate(2deg); }
    }
    @keyframes drift-2 {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(-24px, 26px) rotate(-3deg); }
    }
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.92); }
    }
    @keyframes route-dash {
        to { stroke-dashoffset: -32; }
    }
    .drift-1 { animation: drift-1 12s ease-in-out infinite; }
    .drift-2 { animation: drift-2 14s ease-in-out infinite; }
    .pulse-dot { animation: pulse-dot 1.8s ease-in-out infinite; }
    .route-anim { stroke-dasharray: 6 8; animation: route-dash 1.4s linear infinite; }
    .display-font {
        font-weight: 900;
        letter-spacing: -0.04em;
        line-height: 0.92;
    }
    .glow-text {
        text-shadow: 0 0 60px rgba(240,192,64,0.45);
    }
    .glass-card {
        background: linear-gradient(135deg, rgba(240,192,64,0.10) 0%, rgba(255,255,255,0.02) 100%);
        backdrop-filter: blur(20px);
    }
    .bento-card {
        background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.01) 100%);
        backdrop-filter: blur(12px);
        transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1), border-color 0.3s;
    }
    .bento-card:hover {
        transform: translateY(-4px);
        border-color: rgba(240,192,64,0.35);
    }
    .step-line {
        background: linear-gradient(90deg, transparent 0%, rgba(240,192,64,0.5) 20%, rgba(240,192,64,0.5) 80%, transparent 100%);
    }
    .faq-item[open] summary svg { transform: rotate(180deg); }
    .faq-item > summary { list-style: none; }
    .faq-item > summary::-webkit-details-marker { display: none; }
    .marquee {
        mask-image: linear-gradient(90deg, transparent, black 10%, black 90%, transparent);
        -webkit-mask-image: linear-gradient(90deg, transparent, black 10%, black 90%, transparent);
    }
    @keyframes scroll-x {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    .scroll-x { animation: scroll-x 32s linear infinite; }

    /* ====== RADAR / LIVE MAP ====== */
    #ferogo-radar-map {
        background: #0a0a0a;
    }
    /* Leaflet dark filter — neutralize attribution glow */
    .leaflet-container { background: #0a0a0a; outline: none; }
    .leaflet-control-attribution {
        background: rgba(0,0,0,0.5) !important;
        color: #71717a !important;
        font-size: 10px !important;
        backdrop-filter: blur(8px);
    }
    .leaflet-control-attribution a { color: #a1a1aa !important; }
    .leaflet-control-zoom { display: none !important; }
    .leaflet-bar { border: none !important; }

    /* Pulse rings emanating from user pin */
    @keyframes radar-pulse {
        0%   { transform: scale(0.4); opacity: 0.85; }
        100% { transform: scale(2.4); opacity: 0;    }
    }
    .radar-ring {
        position: absolute;
        inset: 0;
        border-radius: 9999px;
        border: 2px solid rgba(240,192,64,0.55);
        animation: radar-pulse 2.6s cubic-bezier(0.2, 0.8, 0.2, 1) infinite;
    }
    .radar-ring.delay-1 { animation-delay: 0.9s; }
    .radar-ring.delay-2 { animation-delay: 1.8s; }

    /* User pin */
    .radar-user-pin {
        position: relative;
        width: 18px;
        height: 18px;
        border-radius: 9999px;
        background: #F0C040;
        box-shadow: 0 0 0 4px rgba(240,192,64,0.25), 0 0 24px rgba(240,192,64,0.7);
    }
    .radar-user-pin::after {
        content: '';
        position: absolute; inset: 5px;
        border-radius: 9999px;
        background: #0a0a0a;
    }

    /* Driver car marker — gold rounded square with car silhouette */
    .driver-marker {
        width: 30px; height: 30px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #F0C040 0%, #D9A621 100%);
        border-radius: 10px;
        box-shadow: 0 4px 16px rgba(240,192,64,0.45), 0 0 0 2px rgba(10,10,10,0.95);
        color: #0a0a0a;
        transition: transform 0.6s cubic-bezier(0.4,0,0.2,1);
    }
    .driver-marker svg { width: 18px; height: 18px; }
    .driver-marker.busy {
        background: linear-gradient(135deg, #52525b 0%, #27272a 100%);
        color: #a1a1aa;
        box-shadow: 0 4px 12px rgba(0,0,0,0.6), 0 0 0 2px rgba(10,10,10,0.95);
    }
    .driver-marker.premium {
        background: linear-gradient(135deg, #ffffff 0%, #d4d4d8 100%);
        color: #0a0a0a;
    }

    /* Driver list rail */
    .driver-rail-card {
        background: rgba(20,20,20,0.85);
        backdrop-filter: blur(20px);
        transition: background 0.2s, border-color 0.2s;
    }
    .driver-rail-card:hover {
        background: rgba(30,30,30,0.95);
        border-color: rgba(240,192,64,0.35);
    }

    /* HUD chip pulse */
    @keyframes hud-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.45); }
        50%      { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
    }
    .hud-live-dot { animation: hud-pulse 2s ease-out infinite; }

    /* Sweep line — extra radar flavor */
    @keyframes radar-sweep {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }
    .radar-sweep {
        position: absolute;
        width: 220px; height: 220px;
        left: 50%; top: 50%;
        margin-left: -110px; margin-top: -110px;
        background: conic-gradient(from 0deg, rgba(240,192,64,0) 0deg, rgba(240,192,64,0.18) 35deg, rgba(240,192,64,0) 70deg);
        border-radius: 9999px;
        animation: radar-sweep 6s linear infinite;
        pointer-events: none;
        mix-blend-mode: screen;
    }
</style>
@endpush

@section('content')
<div class="ride-mesh {{ $embed ? 'pt-2' : 'pt-24' }} relative overflow-hidden">

    {{-- Noise overlay --}}
    <div class="absolute inset-0 ride-noise opacity-[0.35] pointer-events-none mix-blend-overlay"></div>

    {{-- Floating background shapes --}}
    <div class="drift-1 absolute top-32 -left-32 w-[28rem] h-[28rem] rounded-full bg-brand/10 blur-[120px] pointer-events-none"></div>
    <div class="drift-2 absolute top-[40rem] -right-32 w-[32rem] h-[32rem] rounded-full bg-brand/15 blur-[140px] pointer-events-none"></div>

    {{-- ============ LIVE RADAR (en üstte — birincil deneyim) ============ --}}
    <section id="canli-radar" class="relative px-6 pt-12 md:pt-20 pb-20 md:pb-28">
        <div class="max-w-7xl mx-auto">

            @if ($authedCustomer && ! $embed)
                @php
                    $trustScore = $authedTrust?->trust_score ?? 50;
                    $trustLabel = $authedTrust?->trustLabel() ?? 'normal';
                    $tStyles = [
                        'guvenilir'  => ['Güvenilir Yolcu', 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'],
                        'normal'     => ['Standart',           'bg-zinc-500/15 text-zinc-300 border-zinc-500/30'],
                        'riskli'     => ['Riskli',             'bg-amber-500/15 text-amber-300 border-amber-500/30'],
                        'cok_riskli' => ['Çok Riskli',         'bg-red-500/15 text-red-300 border-red-500/30'],
                    ];
                    $tStyle = $tStyles[$trustLabel] ?? $tStyles['normal'];
                @endphp

                <div class="mb-6 flex items-center justify-between gap-4 p-4 rounded-2xl bg-zinc-950 border border-white/10">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold shrink-0">
                            {{ mb_strtoupper(mb_substr($authedCustomer->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <div class="text-sm font-semibold text-white truncate">Merhaba, {{ $authedCustomer->name }}</div>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border {{ $tStyle[1] }}">
                                    {{ $tStyle[0] }}
                                </span>
                            </div>
                            <div class="text-[11px] text-zinc-500 truncate">
                                Güven skoru <span class="text-brand font-semibold">{{ $trustScore }}/100</span>
                                @if ($authedTrust)
                                    · {{ $authedTrust->total_completed }} tamamlanmış yolculuk
                                @endif
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('customer.panel') }}"
                       class="shrink-0 px-3 py-2 rounded-xl text-xs font-semibold bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 hover:text-white transition">
                        Hesabım →
                    </a>
                </div>
            @endif


            {{-- Section heading --}}
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-8">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-brand mb-4">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                        Canlı Radar
                    </div>
                    <h2 class="display-font text-4xl md:text-5xl text-white mb-4">
                        Bölgendeki üye sürücüler<br>
                        <span class="text-zinc-500">şu an</span> hareket halinde.
                    </h2>
                    <p class="text-zinc-400 leading-relaxed">
                        Konumunu paylaş, çevrendeki Ferogo araçlarını gerçek zamanlı izle. Bu önizleme salt okunur — çağırmak için rezervasyon formuna geç.
                    </p>
                </div>
                <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-zinc-300 backdrop-blur-sm shrink-0">
                    <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Salt okunur · Önizleme
                </div>
            </div>

            {{-- Map + Rail grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

                {{-- Map card --}}
                <div class="lg:col-span-8 relative rounded-3xl border border-white/10 overflow-hidden bg-black/40 shadow-2xl shadow-black/40">
                    <div id="ferogo-radar-map" class="relative w-full h-[520px] md:h-[600px]">
                        {{-- Loading state --}}
                        <div id="radar-loading" class="absolute inset-0 z-[401] flex flex-col items-center justify-center bg-black/80 backdrop-blur-sm">
                            <div class="relative w-16 h-16 mb-5">
                                <div class="radar-ring"></div>
                                <div class="radar-ring delay-1"></div>
                                <div class="radar-ring delay-2"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="radar-user-pin"></div>
                                </div>
                            </div>
                            <div id="radar-loading-text" class="text-sm text-zinc-300 text-center max-w-xs">
                                Konumun hazırlanıyor…<br>
                                <span class="text-xs text-zinc-500">Tarayıcı izin isterse "İzin ver"e dokun.</span>
                            </div>
                            <button id="radar-fallback-btn" class="mt-5 hidden text-xs text-brand hover:text-brand-600 underline underline-offset-4">
                                İzin vermeden İzmir merkezinden devam et
                            </button>
                        </div>
                    </div>

                    {{-- Floating HUD chips --}}
                    <div class="absolute top-4 left-4 z-[400] flex flex-col gap-2 pointer-events-none">
                        <div class="inline-flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-full bg-black/70 border border-white/10 backdrop-blur-md text-xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 hud-live-dot"></span>
                            <span class="text-white font-semibold">CANLI</span>
                            <span class="text-zinc-500">·</span>
                            <span class="text-zinc-400" id="radar-update-time">şimdi</span>
                        </div>
                        <div class="inline-flex items-center gap-3 px-3 py-2 rounded-xl bg-black/70 border border-white/10 backdrop-blur-md text-xs">
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-zinc-500">Müsait</div>
                                <div class="text-base font-bold text-brand" id="radar-available-count">—</div>
                            </div>
                            <div class="w-px h-7 bg-white/10"></div>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-zinc-500">En yakın</div>
                                <div class="text-base font-bold text-white" id="radar-nearest-eta">—</div>
                            </div>
                        </div>
                    </div>

                    {{-- Bottom legend --}}
                    <div class="absolute bottom-4 left-4 z-[400] pointer-events-none">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/70 border border-white/10 backdrop-blur-md text-xs text-zinc-300 pointer-events-auto">
                            <span class="text-brand">●</span> Senin konumun
                            <span class="text-zinc-600 mx-1">|</span>
                            <span class="inline-block w-2.5 h-2.5 rounded bg-brand"></span> Müsait üye sürücü
                            <span class="text-zinc-600 mx-1">|</span>
                            <span class="inline-block w-2.5 h-2.5 rounded bg-zinc-600"></span> Yolculukta
                        </div>
                    </div>
                </div>

                {{-- Driver rail --}}
                <div class="lg:col-span-4 flex flex-col gap-3">
                    <div class="flex items-center justify-between px-1">
                        <div class="text-xs uppercase tracking-[0.25em] text-zinc-500">Yakındaki Üye Sürücüler</div>
                        <div class="text-[10px] text-zinc-600" id="radar-rail-meta">— bulundu</div>
                    </div>
                    <div id="radar-driver-rail" class="space-y-2.5">
                        {{-- Skeletons --}}
                        @for($i = 0; $i < 4; $i++)
                            <div class="driver-rail-card border border-white/5 rounded-2xl p-4 animate-pulse">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-white/5"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="h-3 bg-white/5 rounded w-2/3"></div>
                                        <div class="h-2 bg-white/5 rounded w-1/2"></div>
                                    </div>
                                    <div class="h-3 bg-white/5 rounded w-12"></div>
                                </div>
                            </div>
                        @endfor
                    </div>

                    <div class="mt-2 p-4 rounded-2xl bg-gradient-to-br from-brand/15 to-transparent border border-brand/20">
                        <div class="text-xs text-brand uppercase tracking-wider mb-1">Hatırlatma</div>
                        <p class="text-xs text-zinc-300 leading-relaxed">
                            Liste 3 saniyede bir güncellenir. Sürücüler yolculuk aldıkça <span class="text-zinc-500">gri</span>, müsait olduklarında <span class="text-brand">altın</span> görünür.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @unless($embed)
    {{-- ============ HERO ============ --}}
    <section class="relative px-6 pt-4 pb-20">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

            {{-- Left: copy --}}
            <div class="lg:col-span-7">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-medium text-zinc-300 mb-8 backdrop-blur-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                    Şu an <span class="text-white font-semibold">42 üye sürücü</span> İzmir'de yolda
                </div>

                <h1 class="display-font text-5xl sm:text-6xl md:text-7xl lg:text-8xl text-white mb-8">
                    Adresini<br>
                    yaz,<br>
                    <span class="relative inline-block">
                        <span class="text-brand glow-text">yola çık</span><span class="text-brand">.</span>
                    </span>
                </h1>

                <p class="text-lg md:text-xl text-zinc-300 leading-relaxed mb-10 max-w-xl">
                    Şehir içi, havalimanı, iş toplantısı veya uzun mesafe — bağımsız üye sürücüler ve konforlu araçlar dakikalar içinde kapında. Şeffaf katkı payı, pazarlık yok.
                </p>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('home') }}#rezervasyon" class="group inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-base transition-all shadow-2xl shadow-brand/30 hover:shadow-brand/50 hover:scale-[1.02]">
                        Rezervasyon Yap
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                    <a href="tel:+908508401377" class="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-medium text-base transition backdrop-blur-sm">
                        <svg class="w-5 h-5 text-brand" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                        0850 840 13 77
                    </a>
                </div>

                {{-- Inline trust --}}
                <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-xs text-zinc-400 uppercase tracking-wider">
                    <div class="flex items-center gap-2"><span class="text-brand">★</span> 4.9/5 memnuniyet</div>
                    <div class="flex items-center gap-2"><span class="text-brand">✓</span> Şeffaf katkı payı</div>
                    <div class="flex items-center gap-2"><span class="text-brand">⏱</span> Uçuş takibi</div>
                    <div class="flex items-center gap-2"><span class="text-brand">♛</span> Lüks filo</div>
                </div>
            </div>

            {{-- Right: live trip card --}}
            <div class="lg:col-span-5 relative">
                <div class="relative">
                    <div class="absolute -inset-6 bg-brand/20 blur-3xl rounded-full"></div>

                    <div class="relative glass-card border border-white/10 rounded-3xl p-7 md:p-8 shadow-2xl shadow-black/40">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <div class="text-xs uppercase tracking-widest text-zinc-400 mb-1">Canlı yolculuk</div>
                                <div class="text-sm text-zinc-500">Üye sürücü yolda</div>
                            </div>
                            <div class="px-2 py-1 rounded-md bg-emerald-500/15 text-emerald-300 text-xs font-bold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span>
                                3 dk uzakta
                            </div>
                        </div>

                        {{-- Route preview --}}
                        <div class="bg-black/40 rounded-2xl p-5 mb-5 border border-white/5">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="flex flex-col items-center pt-1">
                                    <div class="w-3 h-3 rounded-full bg-brand"></div>
                                    <svg class="my-1" width="2" height="28" viewBox="0 0 2 28">
                                        <line class="route-anim" x1="1" y1="0" x2="1" y2="28" stroke="rgba(240,192,64,0.6)" stroke-width="2"/>
                                    </svg>
                                    <div class="w-3 h-3 rounded-sm bg-white"></div>
                                </div>
                                <div class="flex-1 space-y-3">
                                    <div>
                                        <div class="text-xs text-zinc-500 uppercase tracking-wide">Alış</div>
                                        <div class="text-sm text-white font-medium">Alsancak, Cumhuriyet Bulvarı</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 uppercase tracking-wide">Bırakış</div>
                                        <div class="text-sm text-white font-medium">İzmir Adnan Menderes Havalimanı</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Driver row --}}
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-zinc-700 to-zinc-900 border-2 border-brand/40 flex items-center justify-center text-sm font-bold text-zinc-300">MK</div>
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-white">Mehmet K.</div>
                                <div class="text-xs text-zinc-500">Mercedes Vito · 35 AB 1234</div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 text-brand text-sm font-bold">★ 4.95</div>
                                <div class="text-xs text-zinc-500">1240 yolculuk</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-5 border-t border-white/5">
                            <div>
                                <div class="text-xs text-zinc-500 mb-1">Tahmini süre</div>
                                <div class="text-xl font-bold text-white">28 dk</div>
                            </div>
                            <div>
                                <div class="text-xs text-zinc-500 mb-1">Katkı payı</div>
                                <div class="text-xl font-bold text-brand">₺640</div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating mini badge --}}
                    <div class="absolute -bottom-4 -left-4 bg-black border border-white/10 rounded-2xl px-4 py-3 shadow-2xl">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-full bg-brand/20 flex items-center justify-center text-brand">🛡</div>
                            <div>
                                <div class="text-xs text-zinc-500">Yolculuk</div>
                                <div class="text-base font-bold text-white">Sigortalı</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endunless

    {{-- ============ QUICK SELECT MODAL ============ (her zaman görünür — embed dahil) --}}
    <div id="quick-modal" class="fixed inset-0 z-[1000] hidden items-center justify-center px-4 py-6">
        <div id="quick-modal-backdrop" class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-2xl bg-zinc-950 border border-white/10 rounded-3xl shadow-2xl shadow-black/60 overflow-hidden max-h-[92vh] overflow-y-auto">
            {{-- Header --}}
            <div class="relative px-6 pt-6 pb-5 bg-gradient-to-br from-brand/15 via-brand/5 to-transparent border-b border-white/5">
                <button type="button" id="quick-modal-close" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 flex items-center justify-center text-zinc-400 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div class="text-xs uppercase tracking-[0.25em] text-brand mb-2">Hızlı Seç</div>
                <div class="flex items-center gap-3">
                    <div class="relative w-12 h-12 rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-900 border border-brand/40 flex items-center justify-center text-xl" id="qm-driver-icon">🚗</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="text-lg font-bold text-white truncate" id="qm-driver-name">—</div>
                            <span id="qm-driver-badge"></span>
                            <span class="text-xs text-brand shrink-0" id="qm-driver-rating">★ —</span>
                        </div>
                        <div class="text-xs text-zinc-500 truncate" id="qm-driver-meta">—</div>
                        <div class="text-[10px] text-zinc-600 mt-0.5 flex items-center gap-1">
                            <span>🔒</span>
                            <span>Plaka ve fotoğraflar eşleştirme sonrası açılacaktır</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Driver profile step: Seç'e bastığında önce burası açılır, kullanıcı tanıtım bilgisine bakar --}}
            {{-- Auth required step: login değilse "Seç" tıklayınca buraya düşer --}}
            <div id="quick-modal-auth-required" class="hidden px-6 py-10 text-center">
                <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-brand/15 border border-brand/30 flex items-center justify-center text-4xl">🔒</div>
                <h3 class="text-xl font-bold text-white mb-2">Yolculuk için önce hesabını aç</h3>
                <p class="text-sm text-zinc-400 leading-relaxed mb-6">
                    Güvenliğin ve sürücülerin korunması için her çağrı doğrulanmış bir hesaptan yapılmalı.
                    <br><span class="text-zinc-500">Hesabın yoksa SMS doğrulamasıyla 30 saniyede açılır.</span>
                </p>
                <a id="qm-auth-login-btn" href="{{ route('customer.login') }}?return=/yolculuk-yapin"
                   class="block w-full px-5 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold transition shadow-xl shadow-brand/30 mb-3">
                    Giriş Yap / Kayıt Ol
                </a>
                <button type="button" id="qm-auth-cancel" class="text-xs text-zinc-500 hover:text-zinc-300 underline underline-offset-2">
                    Vazgeç
                </button>
            </div>

            {{-- Driver profile step: avatar, bio, credentials, vehicle photos --}}
            <div id="quick-modal-driver-profile" class="hidden">
                {{-- Loader --}}
                <div id="qm-dp-loading" class="px-6 py-16 text-center">
                    <div class="inline-block w-10 h-10 border-2 border-brand/30 border-t-brand rounded-full animate-spin"></div>
                    <div class="text-xs text-zinc-500 mt-4">Sürücü profili yükleniyor…</div>
                </div>

                {{-- Content (populated by JS) --}}
                <div id="qm-dp-content" class="hidden">
                    {{-- Hero: avatar + name + rating --}}
                    <div class="px-6 py-6 border-b border-white/5 bg-gradient-to-br from-brand/10 via-brand/5 to-transparent">
                        <div class="flex items-center gap-4">
                            <div id="qm-dp-avatar"
                                 class="w-16 h-16 rounded-2xl bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-2xl shrink-0 border-2 border-brand/40 shadow-xl shadow-brand/20 bg-cover bg-center">
                                <span id="qm-dp-avatar-fallback">F</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 id="qm-dp-name" class="text-xl font-bold text-white truncate">—</h3>
                                    <span id="qm-dp-exp-badge" class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-brand/15 text-brand border border-brand/30"></span>
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-xs text-zinc-400">
                                    <span class="text-brand font-bold"><span id="qm-dp-rating">—</span></span>
                                    <span>·</span>
                                    <span id="qm-dp-trips">— yolculuk</span>
                                    <span class="hidden sm:inline">·</span>
                                    <span class="hidden sm:inline">Üye: <span id="qm-dp-member-since">—</span></span>
                                </div>
                            </div>
                        </div>
                        <p id="qm-dp-bio" class="text-sm text-zinc-300 mt-4 leading-relaxed">—</p>
                    </div>

                    {{-- Credentials --}}
                    <div class="px-6 py-5 border-b border-white/5">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500 mb-3">Belgeler ve Onaylar</div>
                        <div id="qm-dp-credentials" class="grid grid-cols-2 sm:grid-cols-3 gap-2"></div>
                    </div>

                    {{-- Vehicle photos (grid + click-to-lightbox) --}}
                    <div id="qm-dp-photos-wrap" class="hidden px-6 py-5 border-b border-white/5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500">Araç Fotoğrafları</div>
                            <div class="text-[10px] text-zinc-500">
                                <span id="qm-dp-photos-count">—</span> fotoğraf · tıklayarak büyüt
                            </div>
                        </div>
                        <div id="qm-dp-photos" class="grid grid-cols-2 sm:grid-cols-3 gap-2"></div>
                    </div>

                    {{-- Vehicle info --}}
                    <div id="qm-dp-vehicle-wrap" class="hidden px-6 py-5 border-b border-white/5">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500 mb-3">Araç</div>
                        <div class="bg-black/30 border border-white/5 rounded-2xl p-4 space-y-3">
                            <div class="min-w-0">
                                <div id="qm-dp-vehicle-name" class="text-base font-bold text-white">—</div>
                                <div id="qm-dp-vehicle-meta" class="text-xs text-zinc-400">—</div>
                            </div>
                            <div id="qm-dp-vehicle-features" class="flex flex-wrap gap-1.5"></div>
                            <div class="flex flex-wrap gap-3 pt-2 border-t border-white/5 text-[11px]">
                                <span id="qm-dp-vehicle-insurance" class="inline-flex items-center gap-1 text-zinc-400"></span>
                                <span id="qm-dp-vehicle-inspection" class="inline-flex items-center gap-1 text-zinc-400"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="px-6 py-5 flex items-center gap-3">
                        <button type="button" id="qm-dp-back"
                                class="px-4 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 hover:text-white text-sm font-medium transition">
                            ← Geri
                        </button>
                        <button type="button" id="qm-dp-call"
                                class="flex-1 px-5 py-3 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold transition shadow-xl shadow-brand/30">
                            Bu Sürücüyü Çağır →
                        </button>
                    </div>
                </div>

                {{-- Error --}}
                <div id="qm-dp-error" class="hidden px-6 py-10 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/15 border border-red-500/30 flex items-center justify-center text-3xl">⚠</div>
                    <h3 class="text-base font-bold text-white mb-1">Profil yüklenemedi</h3>
                    <p id="qm-dp-error-msg" class="text-sm text-zinc-400 mb-5">—</p>
                    <button type="button" id="qm-dp-error-close"
                            class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 text-sm font-medium transition">
                        Kapat
                    </button>
                </div>
            </div>

            {{-- Form --}}
            <form id="quick-modal-form" class="px-6 py-6 space-y-5">
                @csrf

                {{-- Pickup --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Alış noktası</label>
                    <div class="flex items-start gap-2.5 p-3 rounded-xl bg-white/[0.03] border border-white/10">
                        <div class="w-2.5 h-2.5 rounded-full bg-brand mt-1.5 shrink-0 pulse-dot"></div>
                        <div class="flex-1 min-w-0">
                            <input type="text" id="qm-pickup-address" name="pickup_address" class="w-full bg-transparent text-sm text-white placeholder-zinc-600 focus:outline-none" placeholder="Konumun hazırlanıyor…" required>
                            <div class="text-[10px] text-zinc-600 mt-0.5" id="qm-pickup-coords">—</div>
                        </div>
                    </div>
                </div>

                {{-- Dropoff --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Nereye?</label>
                    <div class="flex items-start gap-2.5 p-3 rounded-xl bg-white/[0.03] border border-white/10 focus-within:border-brand/40 transition">
                        <div class="w-2.5 h-2.5 rounded-sm bg-white mt-1.5 shrink-0"></div>
                        <input type="text" id="qm-dropoff-address" name="dropoff_address" class="w-full bg-transparent text-sm text-white placeholder-zinc-600 focus:outline-none" placeholder="Adres, semt veya tesis adı" required>
                    </div>
                    <div id="qm-dropoff-suggestions" class="hidden mt-1.5 rounded-xl bg-zinc-900 border border-white/10 overflow-hidden divide-y divide-white/5 max-h-48 overflow-y-auto"></div>
                </div>

                {{-- Fare preview --}}
                <div id="qm-fare-card" class="grid grid-cols-3 gap-2 p-3 rounded-xl bg-black/40 border border-white/5">
                    <div>
                        <div class="text-[9px] uppercase tracking-wider text-zinc-500">Mesafe</div>
                        <div class="text-sm font-bold text-white" id="qm-fare-distance">—</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase tracking-wider text-zinc-500">Süre</div>
                        <div class="text-sm font-bold text-white" id="qm-fare-duration">—</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase tracking-wider text-zinc-500">Tahmini</div>
                        <div class="text-sm font-bold text-brand" id="qm-fare-total">—</div>
                    </div>
                </div>

                {{-- Customer — login değilse görünür, login ise hesap rozetiyle değiştirilir --}}
                @if ($authedCustomer)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-sm shrink-0">
                            {{ mb_strtoupper(mb_substr($authedCustomer->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-white truncate">{{ $authedCustomer->name }}</div>
                            <div class="text-[11px] text-zinc-400 truncate">+90 {{ $authedCustomer->phone }} · doğrulanmış</div>
                        </div>
                        <a href="{{ route('customer.panel') }}" class="text-[11px] text-brand hover:text-brand-600 underline underline-offset-2 shrink-0">Hesabım</a>
                    </div>
                    <input type="hidden" name="customer_name"  value="{{ $authedCustomer->name }}">
                    <input type="hidden" name="customer_phone" value="{{ $authedCustomer->phone }}">
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Ad Soyad</label>
                            <input type="text" name="customer_name" class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white placeholder-zinc-600 focus:outline-none transition" placeholder="Adın" required>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Telefon</label>
                            <input type="tel" name="customer_phone" class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white placeholder-zinc-600 focus:outline-none transition" placeholder="0532 000 00 00" required>
                        </div>
                    </div>
                @endif

                {{-- KVKK --}}
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="kvkk_consent" class="mt-0.5 w-4 h-4 rounded bg-white/5 border-white/20 text-brand focus:ring-brand/40 focus:ring-offset-0" required>
                    <span class="text-[11px] text-zinc-400 leading-relaxed">
                        <a href="#" class="text-zinc-300 hover:text-brand underline underline-offset-2">KVKK aydınlatma metnini</a> okudum, kişisel verilerimin işlenmesine onay veriyorum.
                    </span>
                </label>

                {{-- Error --}}
                <div id="qm-error" class="hidden p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300"></div>

                {{-- Submit --}}
                <button type="submit" id="qm-submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 disabled:bg-zinc-700 disabled:text-zinc-500 text-black font-bold transition shadow-xl shadow-brand/30">
                    <span id="qm-submit-text">Talebi Gönder</span>
                    <svg id="qm-submit-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                </button>
            </form>

            {{-- OTP step: telefon doğrulama (yeni telefon ya da süresi geçmiş token) --}}
            <div id="quick-modal-otp" class="hidden px-6 py-6 space-y-5">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-brand/15 border border-brand/30 flex items-center justify-center text-3xl">📱</div>
                    <h3 class="text-xl font-bold text-white mb-1">Telefonunu doğrula</h3>
                    <p class="text-sm text-zinc-400">
                        <span id="qm-otp-phone-label" class="text-zinc-200 font-medium">—</span> numarasına<br>
                        6 haneli kod gönderdik.
                    </p>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Doğrulama kodu</label>
                    <input id="qm-otp-code" type="text" inputmode="numeric" maxlength="6" pattern="\d{6}"
                           class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-4 py-3 text-center text-2xl tracking-[0.4em] font-mono text-white placeholder-zinc-600 focus:outline-none transition"
                           placeholder="000000" autocomplete="one-time-code">
                </div>

                <div id="qm-otp-dev" class="hidden p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-200">
                    <span class="font-bold">DEV mode:</span> Kodun → <span id="qm-otp-dev-code" class="font-mono"></span>
                </div>

                <div id="qm-otp-error" class="hidden p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300"></div>

                <button type="button" id="qm-otp-verify" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 disabled:bg-zinc-700 disabled:text-zinc-500 text-black font-bold transition shadow-xl shadow-brand/30">
                    <span id="qm-otp-verify-text">Doğrula ve Devam Et</span>
                    <svg id="qm-otp-verify-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                </button>

                <div class="flex items-center justify-between text-xs">
                    <button type="button" id="qm-otp-resend" disabled
                            class="text-zinc-500 hover:text-brand disabled:hover:text-zinc-500 disabled:cursor-not-allowed underline underline-offset-2 transition">
                        Tekrar gönder (<span id="qm-otp-countdown">60</span>s)
                    </button>
                    <button type="button" id="qm-otp-back" class="text-zinc-500 hover:text-zinc-300 transition">← Numarayı düzelt</button>
                </div>
            </div>

            {{-- Waiting state: sürücüye teklif gönderildi, cevap bekleniyor --}}
            <div id="quick-modal-waiting" class="hidden px-6 py-8 text-center">
                <div class="relative w-20 h-20 mx-auto mb-5">
                    <div class="absolute inset-0 rounded-full border-2 border-brand/30 pulse-dot"></div>
                    <div class="absolute inset-2 rounded-full border-2 border-brand/50"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-3xl">📡</div>
                </div>
                <h3 class="text-xl font-bold text-white mb-1">
                    <span id="qm-waiting-driver">—</span> çağrılıyor
                </h3>
                <p class="text-sm text-zinc-400 mb-6">Üye sürücü cevap veriyor… <span id="qm-waiting-countdown" class="text-brand font-bold tabular-nums">60</span> sn</p>

                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10 text-left mb-5">
                    <div class="text-[10px] uppercase tracking-wider text-zinc-500 mb-1">Aday sırası</div>
                    <div class="text-xs text-zinc-300" id="qm-waiting-progress">1 / —</div>
                </div>

                <button type="button" id="qm-waiting-cancel"
                        class="w-full px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 hover:text-white font-medium text-sm transition">
                    Vazgeç
                </button>
            </div>

            {{-- RECONFIRM stage: havuzdan ilk kabul eden başka sürücü, müşteri onayı bekliyor (Faz 3-4) --}}
            <div id="quick-modal-reconfirm" class="hidden px-6 py-8 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-[11px] uppercase tracking-[0.2em] font-bold mb-5">
                    <span>🔄</span> Yeni Sürücü Bulundu
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Sizin için başka bir araç bulduk</h3>
                <p class="text-sm text-zinc-400 mb-6">
                    Seçtiğiniz üye sürücü cevap vermedi. Yakındaki başka bir üye sürücü talebinizi aldı.
                    Bu sürücüyle devam etmek ister misiniz?
                </p>

                <div class="bg-zinc-900/60 border border-brand/30 rounded-2xl p-4 mb-6 text-left">
                    <div class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-900 border border-brand/40 flex items-center justify-center text-2xl shrink-0" id="qm-reconfirm-icon">🚗</div>
                        <div class="flex-1 min-w-0">
                            <div class="text-base font-bold text-white truncate" id="qm-reconfirm-name">—</div>
                            <div class="text-[11px] text-zinc-400 truncate" id="qm-reconfirm-vehicle">—</div>
                            <div class="flex items-center gap-2 mt-1 text-xs">
                                <span class="text-brand font-bold" id="qm-reconfirm-rating">★ —</span>
                                <span class="text-zinc-600">·</span>
                                <span class="text-zinc-400" id="qm-reconfirm-trips">— yolculuk</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-white/5 text-[10px] text-zinc-500 leading-relaxed flex items-start gap-1.5">
                        <span>🔒</span>
                        <span>Plaka ve araç fotoğrafları onayınızdan sonra açılacaktır.</span>
                    </div>
                </div>

                <div class="text-[11px] text-zinc-500 mb-4">
                    Karar süresi: <span id="qm-reconfirm-countdown" class="text-amber-300 font-bold tabular-nums">60</span> sn
                </div>

                <div class="flex gap-3">
                    <button type="button" id="qm-reconfirm-decline"
                            class="flex-1 px-5 py-3 rounded-2xl bg-white/5 hover:bg-red-500/15 border border-white/10 hover:border-red-500/40 text-zinc-300 hover:text-red-300 font-medium text-sm transition">
                        Reddet
                    </button>
                    <button type="button" id="qm-reconfirm-accept"
                            class="flex-1 px-5 py-3 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-sm transition shadow-lg shadow-brand/30">
                        ✓ Onayla
                    </button>
                </div>
            </div>

            {{-- Faz 6 — Görsel doğrulama stage'i (yolculuk başladıktan sonra) --}}
            <div id="quick-modal-visual-verify" class="hidden">
                <div class="px-6 pt-6 pb-4 bg-gradient-to-br from-brand/15 via-brand/5 to-transparent border-b border-white/5">
                    <div class="inline-flex items-center gap-2 text-[10px] uppercase tracking-[0.25em] text-brand font-bold mb-2">
                        <span>🛡</span> Güvenlik Doğrulaması
                    </div>
                    <h2 class="text-xl font-bold text-white mb-1">Bindiğiniz araç bu mu?</h2>
                    <p class="text-xs text-zinc-400">Aşağıdaki sürücü ve araç bilgilerini araçtakiyle karşılaştırın.</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="bg-zinc-900/60 border border-brand/30 rounded-2xl p-4">
                        <div class="flex items-center gap-3">
                            <img id="vv-driver-photo" src="" alt="" class="w-16 h-16 rounded-xl object-cover bg-zinc-800 border border-white/10">
                            <div class="flex-1 min-w-0">
                                <div class="text-base font-bold text-white truncate" id="vv-driver-name">—</div>
                                <div class="text-[11px] text-zinc-400 truncate" id="vv-driver-vehicle">—</div>
                                <div class="mt-1 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-brand/15 border border-brand/30">
                                    <span class="text-[11px] font-mono font-bold text-brand tracking-wider" id="vv-driver-plate">— —</span>
                                </div>
                            </div>
                        </div>
                        <div id="vv-vehicle-photos" class="mt-3 grid grid-cols-3 gap-2"></div>
                    </div>
                </div>
                <div class="px-6 pb-6 pt-2 space-y-3 border-t border-white/5">
                    <button type="button" id="vv-confirm-yes"
                            class="w-full px-5 py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold transition flex items-center justify-center gap-2">
                        <span>✓</span> EVET — Sürücü ve araç doğru
                    </button>
                    <button type="button" id="vv-confirm-no"
                            class="w-full px-5 py-3 rounded-xl bg-red-500/15 hover:bg-red-500/25 border border-red-500/40 text-red-300 font-bold text-sm transition">
                        ⚠ HAYIR — Eşleşmiyor, ÇAĞRI MERKEZİNİ ÇAĞIR
                    </button>
                    <p class="text-[11px] text-zinc-500 text-center leading-relaxed">
                        HAYIR derseniz çağrı merkezi sürücüye anında ulaşır, kimliği fotoğraflı olarak doğrular.
                        Güvenliğiniz için araçtan inmenizi öneririz.
                    </p>
                </div>
            </div>

            {{-- Accepted state: sürücü kabul etti, aktif yolculuk + chat --}}
            <div id="quick-modal-accepted" class="hidden">
                <div class="px-6 pt-6 pb-4 bg-gradient-to-br from-emerald-500/15 via-emerald-500/5 to-transparent border-b border-white/5">
                    <div class="inline-flex items-center gap-2 text-[10px] uppercase tracking-[0.25em] text-emerald-300 font-bold mb-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span>
                        Üye Sürücü Yolda
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-900 border border-brand/40 flex items-center justify-center text-xl">🚗</div>
                        <div class="flex-1 min-w-0">
                            <div class="text-base font-bold text-white truncate" id="qm-accepted-name">—</div>
                            <div class="text-[11px] text-zinc-400 truncate" id="qm-accepted-vehicle">—</div>
                        </div>
                        <button type="button" id="qm-call-btn"
                                class="shrink-0 w-11 h-11 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center transition shadow-lg shadow-emerald-500/30"
                                title="Sürücüyü ara">
                            📞
                        </button>
                        <div class="text-right shrink-0">
                            <div class="text-xs text-brand font-bold" id="qm-accepted-rating">★ —</div>
                            <div class="text-[10px] text-zinc-500" id="qm-accepted-trips">—</div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-b border-white/5">
                    <div class="bg-black/30 rounded-2xl p-3 space-y-2 text-xs">
                        <div class="flex items-start gap-2.5">
                            <div class="w-2 h-2 rounded-full bg-brand mt-1.5"></div>
                            <div class="flex-1 text-zinc-300 truncate" id="qm-accepted-pickup">—</div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <div class="w-2 h-2 rounded-sm bg-white mt-1.5"></div>
                            <div class="flex-1 text-zinc-300 truncate" id="qm-accepted-dropoff">—</div>
                        </div>
                    </div>
                </div>

                {{-- Chat --}}
                <div class="px-6 pt-4 pb-2">
                    <div class="text-[10px] uppercase tracking-wider text-zinc-500 mb-2">Sürücüyle mesajlaş</div>
                    <div id="qm-chat-list" class="h-48 overflow-y-auto p-2 rounded-xl bg-black/30 border border-white/5 space-y-2 text-xs"></div>
                </div>
                <form id="qm-chat-form" class="flex items-center gap-2 px-6 py-3 border-t border-white/5">
                    <input id="qm-chat-input" type="text" maxlength="1000" autocomplete="off" required
                           class="flex-1 bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2 text-sm text-white placeholder-zinc-600 focus:outline-none"
                           placeholder="Üye sürücüye mesaj…">
                    <button type="submit" class="px-3 py-2 rounded-xl bg-brand hover:bg-brand-600 text-black text-xs font-bold transition">Gönder</button>
                </form>

                {{-- 60sn müşteri onay butonu — sürücüye "müşterim gerçek, geliyorum" sinyali --}}
                <div id="qm-confirm-bar" class="hidden px-6 py-3 border-t border-white/5">
                    <button type="button" id="qm-confirm-btn"
                            class="w-full px-4 py-2.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 text-xs font-semibold transition">
                        Sürücüyü gördüm, geliyorum
                    </button>
                </div>

                <div class="px-6 py-3 border-t border-white/5 text-center">
                    <button type="button" id="quick-modal-done"
                            class="text-xs text-zinc-500 hover:text-zinc-300 underline underline-offset-2">
                        Pencereyi kapat
                    </button>
                </div>
            </div>

            {{-- Terminal state: kimse almadı / iptal --}}
            <div id="quick-modal-terminal" class="hidden px-6 py-10 text-center">
                <div class="w-20 h-20 mx-auto mb-5 rounded-full bg-white/5 flex items-center justify-center text-3xl">😕</div>
                <h3 class="text-xl font-bold text-white mb-2" id="qm-terminal-title">—</h3>
                <p class="text-sm text-zinc-400 leading-relaxed mb-6" id="qm-terminal-msg">—</p>
                <div class="flex gap-2">
                    <button type="button" id="qm-terminal-close" class="flex-1 px-4 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-zinc-300 font-medium transition">
                        Kapat
                    </button>
                    <a href="{{ route('home') }}#rezervasyon" class="flex-1 px-4 py-3 rounded-2xl bg-brand hover:bg-brand-600 text-black text-sm font-bold transition text-center">
                        Rezervasyon Yap
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PHOTO LIGHTBOX (araç fotoğraf full-screen viewer) ============ --}}
    <div id="qm-lightbox" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/95 backdrop-blur-sm">
        <button type="button" id="qm-lightbox-close"
                class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center text-white text-xl z-10 transition">
            ✕
        </button>
        <button type="button" id="qm-lightbox-prev"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center text-white text-xl z-10 transition">
            ‹
        </button>
        <button type="button" id="qm-lightbox-next"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center text-white text-xl z-10 transition">
            ›
        </button>
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 px-3 py-1.5 rounded-full bg-white/10 border border-white/20 text-xs text-white tabular-nums z-10">
            <span id="qm-lightbox-index">1</span> / <span id="qm-lightbox-total">1</span>
        </div>
        <img id="qm-lightbox-img" src="" alt="Araç fotoğrafı"
             class="max-w-[92vw] max-h-[92vh] object-contain rounded-2xl shadow-2xl shadow-black/80">
    </div>

    @unless($embed)
    {{-- ============ MARQUEE STRIP ============ --}}
    <section class="relative py-6 border-y border-white/5 bg-black/40 backdrop-blur-sm marquee overflow-hidden">
        <div class="flex scroll-x whitespace-nowrap text-sm uppercase tracking-[0.3em] text-zinc-600">
            @for($i = 0; $i < 2; $i++)
                <div class="flex items-center gap-12 px-6">
                    <span>Havalimanı Yolculuğu</span><span class="text-brand">·</span>
                    <span>Şehir İçi</span><span class="text-brand">·</span>
                    <span>Kurumsal</span><span class="text-brand">·</span>
                    <span>VIP</span><span class="text-brand">·</span>
                    <span>Uzun Mesafe</span><span class="text-brand">·</span>
                    <span>Düğün & Etkinlik</span><span class="text-brand">·</span>
                    <span>Saatlik Kiralama</span><span class="text-brand">·</span>
                </div>
            @endfor
        </div>
    </section>

    {{-- ============ HOW IT WORKS ============ --}}
    <section id="nasil-calisir" class="relative px-6 py-20 md:py-28">
        <div class="max-w-6xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Süreç</div>
                <h2 class="display-font text-4xl md:text-6xl text-white mb-5">Üç adım, 60 saniye.</h2>
                <p class="text-lg text-zinc-400">Telefonunu doğrula, hesabın hazır. Web'den rezervasyon, kapına üye sürücü.</p>
            </div>

            <div class="relative">
                <div class="absolute top-8 left-12 right-12 h-px step-line hidden md:block"></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-4 relative">
                    @foreach([
                        ['01', 'Adresini yaz', 'Alış ve bırakış adresini gir, araç sınıfını seç. Tahmini katkı payı anında ekranda.', '📍'],
                        ['02', 'Telefonunu doğrula', 'SMS ile gelen kodu gir. Hesabın 30 saniyede hazır, ödeme yolculuk sonunda.', '✓'],
                        ['03', 'Yola çık', 'Üye sürücü ve plaka SMS ile gelir. Kapına gelir, paylaşımlı yolculuk başlar.', '🛣'],
                    ] as $step)
                        <div class="relative text-center">
                            <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-full bg-black border-2 border-brand text-2xl mb-5 mx-auto">
                                {{ $step[3] }}
                            </div>
                            <div class="text-xs font-mono text-brand mb-2">{{ $step[0] }}</div>
                            <h3 class="text-xl font-bold text-white mb-2">{{ $step[1] }}</h3>
                            <p class="text-sm text-zinc-400 leading-relaxed max-w-[260px] mx-auto">{{ $step[2] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ VEHICLE CLASSES ============ --}}
    <section class="relative px-6 py-20">
        <div class="max-w-7xl mx-auto">
            <div class="max-w-3xl mb-14">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Filo</div>
                <h2 class="display-font text-4xl md:text-6xl text-white mb-6">
                    Her ihtiyaca<br>
                    <span class="text-zinc-500">doğru</span> araç.
                </h2>
                <p class="text-lg text-zinc-400 leading-relaxed">
                    Tek başına şehir içi mi, ailenle havalimanı yolculuğu mu, ekiple iş seyahati mi — her senaryoya bir sınıf.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                @foreach([
                    ['Comfort', 'Şehir içi günlük', ['4 kişi', '2 bagaj', 'Sedan'], 'Skoda Superb · VW Passat seviyesi', '💼'],
                    ['Business', 'İş ve havalimanı', ['4 kişi', '3 bagaj', 'Lüks sedan'], 'Mercedes E-Class · BMW 5 seviyesi', '👔'],
                    ['VIP / Aile', 'Grup ve premium yolculuk', ['6-7 kişi', '6 bagaj', 'Minivan'], 'Mercedes Vito · VW Caravelle seviyesi', '♛'],
                ] as $vc)
                    <div class="bento-card rounded-3xl p-7 border border-white/5 flex flex-col">
                        <div class="text-4xl mb-5">{{ $vc[4] }}</div>
                        <div class="text-xs uppercase tracking-[0.2em] text-brand mb-2">{{ $vc[1] }}</div>
                        <h3 class="display-font text-3xl text-white mb-4">{{ $vc[0] }}</h3>
                        <div class="flex flex-wrap gap-2 mb-5">
                            @foreach($vc[2] as $tag)
                                <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300 text-xs font-medium">{{ $tag }}</span>
                            @endforeach
                        </div>
                        <p class="text-sm text-zinc-400 mb-6 flex-1">{{ $vc[3] }}</p>
                        <a href="{{ route('home') }}#rezervasyon" class="inline-flex items-center gap-2 text-brand font-semibold text-sm hover:gap-3 transition-all">
                            Katkı payı hesapla
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ WHY FEROGO BENTO ============ --}}
    <section class="relative px-6 py-20">
        <div class="max-w-7xl mx-auto">
            <div class="max-w-3xl mb-16">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Neden Ferogo</div>
                <h2 class="display-font text-4xl md:text-6xl text-white mb-6">
                    Ferogo deneyimi:<br>
                    <span class="text-zinc-500">net,</span> güvenli, <span class="text-zinc-500">profesyonel.</span>
                </h2>
                <p class="text-lg text-zinc-400 leading-relaxed">
                    Pazarlık, "uzun yoldan" şüphesi yok. Adresini yazdığın an tahmini katkı payını görüyorsun — yolculuk bittiğinde üye sürücüye ödüyorsun.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">

                {{-- Big card --}}
                <div class="bento-card md:col-span-2 md:row-span-2 rounded-3xl p-8 md:p-10 border border-white/5 relative overflow-hidden">
                    <div class="absolute top-8 right-8 text-7xl opacity-10">💎</div>
                    <div class="relative">
                        <div class="text-xs uppercase tracking-[0.2em] text-brand mb-4">01 · Şeffaf katkı payı</div>
                        <h3 class="display-font text-3xl md:text-5xl text-white mb-4">Katkı payı baştan belli, yolda değişmez</h3>
                        <p class="text-zinc-400 leading-relaxed mb-6 max-w-md">
                            Adresleri yazdığın an net katkı payını görürsün. Trafik, ara durak veya saat geç oldu diye sürpriz katkı çıkmaz. Yazılan tutar, ödenen tutardır.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 rounded-full bg-brand/10 border border-brand/25 text-brand text-xs font-semibold">Sabit katkı</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300 text-xs font-semibold">Pazarlık yok</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300 text-xs font-semibold">Yolda değişmez</span>
                        </div>
                    </div>
                </div>

                {{-- Medium card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">⏱</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">02 · Uçuş takibi</div>
                    <h3 class="text-xl font-bold text-white mb-2">Uçağın geç kalsa bile</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Havalimanı yolculuklarında uçuş takibi yapılır. Üye sürücü, sen indiğinde kapıda olur.</p>
                </div>

                {{-- Medium card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">💳</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">03 · Önden ödeme yok</div>
                    <h3 class="text-xl font-bold text-white mb-2">Yolculuk sonunda öde</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Kart bilgisi istemeyiz. Katkı payı yolculuk bitince üye sürücüye nakit, kart veya banka transferi ile ödenir.</p>
                </div>

                {{-- Wide card --}}
                <div class="bento-card md:col-span-2 rounded-3xl p-7 border border-white/5">
                    <div class="flex items-start gap-5">
                        <div class="w-14 h-14 rounded-2xl bg-brand/15 flex items-center justify-center text-2xl shrink-0">🛡</div>
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-brand mb-2">04 · Güvenlik</div>
                            <h3 class="text-xl font-bold text-white mb-2">Doğrulanmış üye sürücü, güvenli yolculuk</h3>
                            <p class="text-sm text-zinc-400 leading-relaxed">Her üye sürücü kimlik doğrulamasından, sabıka kaydı kontrolünden ve yolcu memnuniyeti değerlendirmesinden geçer. Yolculuk koltuk sigortalıdır.</p>
                        </div>
                    </div>
                </div>

                {{-- Small card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">📱</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">05 · Tek tıkla kayıt</div>
                    <h3 class="text-xl font-bold text-white mb-2">Uygulama yok, web'den hızlı kayıt</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">SMS ile doğrula, hesabın hazır. Yolculuk geçmişini panelinden takip et — uygulama indirmek zorunda değilsin.</p>
                </div>

                {{-- Wide card --}}
                <div class="bento-card md:col-span-2 rounded-3xl p-7 border border-white/5 relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 text-9xl opacity-5">📞</div>
                    <div class="relative">
                        <div class="text-xs uppercase tracking-[0.2em] text-brand mb-2">06 · 7/24 destek</div>
                        <h3 class="text-xl font-bold text-white mb-2">Gerçek insan, her saatte</h3>
                        <p class="text-sm text-zinc-400 leading-relaxed max-w-md">Bot değil. Yolculuk sırasında, öncesinde veya sonrasında soru, sorun, değişiklik — anında insan ile konuş.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ USE CASES ============ --}}
    <section class="relative px-6 py-20">
        <div class="max-w-6xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Senaryolar</div>
                <h2 class="display-font text-4xl md:text-5xl text-white mb-5">Hangi yolculuk?</h2>
                <p class="text-lg text-zinc-400">En çok tercih edilen senaryolar — sen de tek tıkla başlat.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach([
                    ['✈️', 'Havalimanı', 'Uçuş takipli kapı önü yolculuk'],
                    ['💼', 'İş Toplantısı', 'Zamanında, temsil eden araç'],
                    ['🎉', 'Düğün & Gece', 'Özel etkinlik, VIP karşılama'],
                    ['🏙', 'Şehir İçi', 'Hızlı, güvenli, paylaşımlı yolculuk hattı'],
                ] as $uc)
                    <a href="{{ route('home') }}#rezervasyon" class="bento-card rounded-2xl p-6 border border-white/5 block group">
                        <div class="text-3xl mb-3">{{ $uc[0] }}</div>
                        <h3 class="text-lg font-bold text-white mb-1 group-hover:text-brand transition">{{ $uc[1] }}</h3>
                        <p class="text-sm text-zinc-400 leading-relaxed">{{ $uc[2] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ TESTIMONIALS ============ --}}
    <section class="relative px-6 py-20">
        <div class="max-w-6xl mx-auto">
            <div class="max-w-2xl mb-14">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Yolcular ne diyor</div>
                <h2 class="display-font text-4xl md:text-5xl text-white">Bir kez deneyen<br>tekrar arıyor.</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                @foreach([
                    ['Ferda Y.', 'İzmir', 'Sabah 6\'da havalimanına gittim, üye sürücü 5\'te kapıdaydı. Araç temiz, su, şarj — her şey hazır. Çok rahat bir yolculuk oldu.'],
                    ['Selim T.', 'Alsancak', 'Kurumsal misafirlerimizi karşıladık. Katkı payı baştan belliydi, makbuz düzgün geldi. Muhasebe ile sorun çıkmadı.'],
                    ['Aylin K.', 'Karşıyaka', 'Düğün için ayarlamıştım. Üye sürücü bey çok kibardı, fotoğraf çekme molasında bile bekledi. Çiçek çelenkli karşılama harikaydı.'],
                ] as $t)
                    <div class="bento-card rounded-3xl p-7 border border-white/5">
                        <div class="flex items-center gap-1 text-brand text-sm mb-4">★★★★★</div>
                        <p class="text-zinc-300 leading-relaxed mb-5 text-sm">"{{ $t[2] }}"</p>
                        <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-zinc-700 to-zinc-900 border border-white/10 flex items-center justify-center text-xs font-bold text-zinc-400">{{ mb_substr($t[0], 0, 1) }}{{ explode(' ', $t[0])[1][0] ?? '' }}</div>
                            <div>
                                <div class="text-sm font-semibold text-white">{{ $t[0] }}</div>
                                <div class="text-xs text-zinc-500">{{ $t[1] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ FAQ ============ --}}
    <section class="relative px-6 py-20">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-14">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Sıkça Sorulan</div>
                <h2 class="display-font text-4xl md:text-5xl text-white">Net cevaplar.</h2>
            </div>

            <div class="space-y-3">
                @foreach([
                    ['Önceden ödeme alıyor musunuz?', 'Hayır. Hiçbir aşamada kart bilgisi istemiyoruz. Katkı payı yolculuk bittiğinde üye sürücüye nakit, kart veya banka transferi ile ödenir.'],
                    ['Katkı payı yolda değişir mi?', 'Hayır. Trafik, alternatif rota, kısa ara duraklar dahil — yazılan tutar ödenen tutardır. Yolcunun talebiyle yeni durak eklenirse bunu önceden netleştiririz.'],
                    ['Üye sürücü geç kalırsa?', 'Rezervasyon saatinden 5 dk geç kalan üye sürücü için indirim uygulanır, 15 dk geç kalan rezervasyon için ücretsiz iptal/yenileme hakkın vardır.'],
                    ['Uçağım geç kalırsa ne olur?', 'Havalimanı yolculuklarında uçuş takibi otomatik yapılır. Geç inseniz bile üye sürücü kapıda olur, ek katkı çıkmaz.'],
                    ['Bagaj sınırı var mı?', 'Her araç sınıfının taşıyabileceği maksimum bagaj kapasitesi belirtilir. Fazla bagaj için araç sınıfını VIP/Aile olarak seçmen yeterli.'],
                    ['Çocuk koltuğu, evcil hayvan?', 'Bebek/çocuk koltuğu ve evcil hayvan opsiyonu rezervasyonda ekstra olarak işaretlenebilir — üye sürücü hazır gelir.'],
                    ['İptal politikası nedir?', 'Yolculuk saatinden 1 saat öncesine kadar ücretsiz iptal. Daha sonra %50, yolculuk saatinde ise katkı payının tamamı alınır.'],
                ] as $faq)
                    <details class="faq-item bg-white/[0.02] border border-white/5 rounded-2xl hover:border-brand/30 transition group">
                        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer">
                            <span class="text-white font-medium">{{ $faq[0] }}</span>
                            <svg class="w-5 h-5 text-zinc-400 shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="px-5 pb-5 text-sm text-zinc-400 leading-relaxed">
                            {{ $faq[1] }}
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ FINAL CTA STRIP ============ --}}
    <section class="relative px-6 py-16">
        <div class="max-w-5xl mx-auto">
            <div class="relative rounded-3xl bg-gradient-to-br from-brand/20 via-brand/5 to-transparent border border-brand/20 p-8 md:p-12 overflow-hidden">
                <div class="absolute -right-12 -top-12 w-64 h-64 bg-brand/20 blur-3xl rounded-full"></div>
                <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div>
                        <h3 class="display-font text-3xl md:text-4xl text-white mb-2">Hazırsan yola çıkalım.</h3>
                        <p class="text-zinc-300">60 saniyede rezervasyon, dakikalar içinde kapında.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('home') }}#rezervasyon" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-brand hover:bg-brand-600 text-black font-bold transition shadow-lg shadow-brand/30">
                            Rezervasyon Yap
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </a>
                        <a href="https://wa.me/908508401377" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition">
                            💬 WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endunless

</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    'use strict';

    const DEFAULT_CENTER = [38.4192, 27.1287]; // İzmir Konak
    const DRIVER_COUNT = 9;
    const TICK_MS = 1800;
    const PASSENGER_NAMES = ['Mehmet K.', 'Burak A.', 'Tolga Ş.', 'Emre D.', 'Serkan O.', 'Hakan Y.', 'Cem B.', 'Murat İ.', 'Onur G.', 'Kerem Ç.'];
    const VEHICLE_CLASSES = [
        { label: 'Easy',     slug: 'easy',     type: 'available', icon: '🚗' },
        { label: 'Platinum', slug: 'platinum', type: 'premium',   icon: '👔' },
        { label: 'VIP',      slug: 'vip',      type: 'premium',   icon: '♛'  },
        { label: 'Easy',     slug: 'easy',     type: 'available', icon: '🚗' },
    ];
    const PLATES = ['35 AB 1234', '35 KZ 4471', '35 EM 8820', '35 BC 5532', '35 TR 9908', '35 FG 3217'];

    const mapEl = document.getElementById('ferogo-radar-map');
    const loadingEl = document.getElementById('radar-loading');
    const loadingText = document.getElementById('radar-loading-text');
    const fallbackBtn = document.getElementById('radar-fallback-btn');
    const railEl = document.getElementById('radar-driver-rail');
    const railMetaEl = document.getElementById('radar-rail-meta');
    const availableCountEl = document.getElementById('radar-available-count');
    const nearestEtaEl = document.getElementById('radar-nearest-eta');
    const updateTimeEl = document.getElementById('radar-update-time');

    if (!mapEl || typeof L === 'undefined') return;

    let map = null;
    let userMarker = null;
    let drivers = [];
    let tickHandle = null;
    let userCenterGlobal = null;
    let userAddressGlobal = null;

    // === Gerçek sürücüler (DB'den, periyodik) — modal flow için
    const NEARBY_URL = '{{ route('ride_requests.nearby') }}';
    const VCLASS_ICONS = { easy: '🚗', platinum: '👔', vip: '♛' };
    let realDrivers = [];
    let realDriversHandle = null;

    let realDriversTotalOnline = 0;
    async function fetchRealDrivers(center) {
        try {
            const res = await fetch(`${NEARBY_URL}?lat=${center[0]}&lng=${center[1]}&limit=3`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) {
                console.warn('[radar] nearby HTTP', res.status, await res.text().catch(() => ''));
                return;
            }
            const data = await res.json();
            const next = Array.isArray(data.drivers) ? data.drivers : [];
            realDriversTotalOnline = Number(data.total_online) || 0;
            console.debug('[radar] nearby:', { total_online: realDriversTotalOnline, returned: next.length, drivers: next });
            const changed = next.length !== realDrivers.length
                || next.some((d, i) => !realDrivers[i] || realDrivers[i].id !== d.id);
            realDrivers = next;
            if (changed && userCenterGlobal) renderRail(userCenterGlobal);
        } catch (err) {
            console.error('[radar] nearby fetch failed:', err);
        }
    }

    /** Mock kart tıklandığında gerçek sürücüyü seç (varsa). */
    function pickRealDriverFor(mock) {
        if (!realDrivers.length) return null;
        // 1) İsim tam eşleşmesi — mock "Mehmet K." → real "Mehmet K." (en doğal eşleme)
        const byName = realDrivers.find(r => r.name === mock.name);
        if (byName) return byName;
        // 2) Aynı vehicle class
        const byClass = realDrivers.find(r => r.vehicle_class_slug === mock.vSlug);
        if (byClass) return byClass;
        // 3) Fallback: ilk müsait
        return realDrivers[0];
    }

    function userPinHtml() {
        return `
            <div class="relative w-[60px] h-[60px] flex items-center justify-center">
                <div class="radar-ring"></div>
                <div class="radar-ring delay-1"></div>
                <div class="radar-ring delay-2"></div>
                <div class="radar-sweep"></div>
                <div class="radar-user-pin"></div>
            </div>`;
    }

    function carSvg() {
        // Material Design "directions_car" — temiz, evrensel okunabilir yan görünüm
        return `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.04 3H5.81l1.04-3zM5 17v-5h14v5H5zm2-2.5c0 .83-.67 1.5-1.5 1.5S4 15.33 4 14.5 4.67 13 5.5 13s1.5.67 1.5 1.5zm13 0c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5z"/></svg>`;
    }

    function driverIcon(state) {
        const cls = state === 'busy' ? 'busy' : (state === 'premium' ? 'premium' : '');
        return L.divIcon({
            html: `<div class="driver-marker ${cls}">${carSvg()}</div>`,
            className: 'driver-marker-wrapper',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        });
    }

    function userIcon() {
        return L.divIcon({
            html: userPinHtml(),
            className: 'user-marker-wrapper',
            iconSize: [60, 60],
            iconAnchor: [30, 30],
        });
    }

    function rand(min, max) { return Math.random() * (max - min) + min; }
    function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

    function makeDriver(center, idx) {
        // Spread within ~2.5km
        const lat = center[0] + rand(-0.022, 0.022);
        const lng = center[1] + rand(-0.028, 0.028);
        const vc = VEHICLE_CLASSES[idx % VEHICLE_CLASSES.length];
        const busy = Math.random() < 0.25;
        return {
            id: idx,
            name: PASSENGER_NAMES[idx % PASSENGER_NAMES.length],
            plate: PLATES[idx % PLATES.length],
            vclass: vc.label,
            vSlug: vc.slug,
            vIcon: vc.icon,
            state: busy ? 'busy' : vc.type,
            rating: (4.6 + Math.random() * 0.39).toFixed(2),
            trips: Math.floor(rand(180, 2400)),
            lat, lng,
            heading: rand(0, 360),
            speed: rand(0.00008, 0.00022), // ~0.5-1.5 km in degrees per tick
            marker: null,
        };
    }

    function distanceKm(a, b) {
        const R = 6371;
        const dLat = (b[0] - a[0]) * Math.PI / 180;
        const dLng = (b[1] - a[1]) * Math.PI / 180;
        const x = Math.sin(dLat/2)**2 + Math.cos(a[0]*Math.PI/180) * Math.cos(b[0]*Math.PI/180) * Math.sin(dLng/2)**2;
        return 2 * R * Math.asin(Math.sqrt(x));
    }

    // Sınıf badge'i — renk kodlu, net okunur etiket
    function classBadge(slug, label) {
        const styles = {
            easy:     'bg-zinc-700/60 border border-zinc-500/30 text-zinc-200',
            platinum: 'bg-gradient-to-br from-zinc-200 to-zinc-400 text-zinc-900 shadow-sm shadow-white/10',
            vip:      'bg-gradient-to-br from-brand to-brand-600 text-black shadow-sm shadow-brand/40',
        };
        const cls = styles[slug] || styles.easy;
        return `<span class="${cls} px-1.5 py-0.5 rounded-md text-[9px] font-extrabold tracking-[0.1em] uppercase shrink-0">${label}</span>`;
    }

    function moveDriver(d, userCenter) {
        // Random walk with slight bias toward staying within range of user
        const dist = distanceKm(userCenter, [d.lat, d.lng]);
        if (dist > 3.5) {
            // Steer back toward user
            const dx = userCenter[1] - d.lng;
            const dy = userCenter[0] - d.lat;
            d.heading = Math.atan2(dy, dx) * 180 / Math.PI;
        } else {
            d.heading += rand(-25, 25);
        }
        const rad = d.heading * Math.PI / 180;
        d.lat += Math.sin(rad) * d.speed;
        d.lng += Math.cos(rad) * d.speed;

        // Occasional state flip
        if (Math.random() < 0.04) {
            d.state = d.state === 'busy' ? (Math.random() < 0.3 ? 'premium' : 'available') : (Math.random() < 0.5 ? 'busy' : d.state);
        }
    }

    function renderRail(userCenter) {
        // Real drivers (API) — her zaman Müsait + Seç
        const realCards = realDrivers.map(r => ({
            id: 'real-' + r.id,
            name: r.name,
            plate: r.plate || '—',
            vIcon: VCLASS_ICONS[r.vehicle_class_slug] || '🚗',
            vSlug: r.vehicle_class_slug,
            vclass: r.vehicle_class || 'Easy',
            rating: Number(r.rating || 0).toFixed(2),
            km: r.distance_km,
            isBusy: false,
            isReal: true,
            photoUrl: r.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(r.name || 'Sürücü')}&background=F0C040&color=000&size=128&bold=true`,
            raw: r,
        }));

        // Mock drivers — animasyon devam etsin, görsel doluluk için
        const sortedMock = [...drivers]
            .map(d => ({ d, km: distanceKm(userCenter, [d.lat, d.lng]) }))
            .sort((a, b) => a.km - b.km)
            .map(({ d, km }) => ({
                id: 'mock-' + d.id,
                name: d.name,
                plate: d.plate,
                vIcon: d.vIcon,
                vSlug: d.vSlug,
                vclass: d.vclass,
                rating: d.rating,
                km,
                isBusy: d.state === 'busy',
                isReal: false,
                photoUrl: `https://ui-avatars.com/api/?name=${encodeURIComponent(d.name || 'Sürücü')}&background=27272a&color=fff&size=128&bold=true`,
                raw: d,
            }));

        // Real driver'lar üstte (max 3), mock'larla 5'e tamamla. İsim çakışmalarını ele
        const cards = [...realCards];
        const usedNames = new Set(realCards.map(c => c.name.toLowerCase()));
        for (const m of sortedMock) {
            if (cards.length >= 5) break;
            const key = m.name.toLowerCase();
            if (usedNames.has(key)) continue;
            cards.push(m);
            usedNames.add(key);
        }

        railEl.innerHTML = cards.map(d => {
            const mins = Math.max(1, Math.round(d.km * 2.4 + 0.8));
            const isBusy = d.isBusy;
            const dotColor = isBusy ? 'bg-zinc-500' : 'bg-brand';
            const statusText = isBusy ? 'Yolculukta' : 'Müsait';
            const statusClass = isBusy ? 'text-zinc-400' : 'text-brand';
            const badge = classBadge(d.vSlug, d.vclass);
            const liveDot = d.isReal
                ? `<span class="ml-1 text-[8px] text-emerald-400 font-bold tracking-wider">● CANLI</span>`
                : '';
            const selectBtn = isBusy
                ? `<div class="text-[10px] text-zinc-600 uppercase tracking-wider px-2.5 py-1.5 rounded-lg border border-white/5">Dolu</div>`
                : `<button type="button" class="quick-select-btn group/btn inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-brand hover:bg-brand-600 text-black text-[11px] font-bold uppercase tracking-wider transition shadow-md shadow-brand/30" data-card-id="${d.id}">
                        Seç
                        <svg class="w-3 h-3 transition-transform group-hover/btn:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </button>`;
            return `
                <div class="driver-rail-card border ${d.isReal ? 'border-brand/30' : 'border-white/5'} rounded-2xl p-3.5 flex items-center gap-3">
                    <div class="relative w-11 h-11 shrink-0">
                        <img src="${d.photoUrl}" alt="" class="w-11 h-11 rounded-xl object-cover border border-white/10 bg-zinc-900" loading="lazy" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(d.name)}&background=27272a&color=fff&size=128&bold=true';">
                        <span class="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-md bg-zinc-900 border border-white/10 flex items-center justify-center text-[10px]">${d.vIcon}</span>
                        <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full ${dotColor} border-2 border-black"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <div class="text-sm font-semibold text-white truncate max-w-[140px]">${d.name}</div>
                            ${badge}
                            <span class="text-[10px] text-brand shrink-0">★ ${d.rating}</span>
                            ${liveDot}
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[11px] font-bold text-white">${d.km.toFixed(1)} km</span>
                            <span class="text-zinc-700">·</span>
                            <span class="text-[10px] ${statusClass} uppercase tracking-wider">${statusText} · ${mins} dk</span>
                        </div>
                    </div>
                    <div class="shrink-0">${selectBtn}</div>
                </div>`;
        }).join('');

        // Bind: real → openQuickModal(real); mock → openQuickModal(mock) (modal kendi remap eder)
        railEl.querySelectorAll('.quick-select-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const cardId = btn.dataset.cardId;
                const card = cards.find(c => c.id === cardId);
                if (card) openQuickModal(card.raw);
            });
        });

        const availableCount = drivers.filter(d => d.state !== 'busy').length;
        const nearestAvailable = sortedMock.find(m => !m.isBusy);
        availableCountEl.textContent = availableCount;
        if (nearestAvailable) {
            const mins = Math.max(1, Math.round(nearestAvailable.km * 2.4 + 0.8));
            nearestEtaEl.textContent = `${mins} dk`;
        } else {
            nearestEtaEl.textContent = '—';
        }
        railMetaEl.textContent = `${drivers.length} araç`;
        updateTimeEl.textContent = 'şimdi';
    }

    function tick(userCenter) {
        drivers.forEach(d => {
            moveDriver(d, userCenter);
            if (d.marker) {
                d.marker.setLatLng([d.lat, d.lng]);
                d.marker.setIcon(driverIcon(d.state));
            }
        });
        renderRail(userCenter);
    }

    async function reverseGeocode(lat, lng) {
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=tr&zoom=18`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return null;
            const data = await res.json();
            return data.display_name || null;
        } catch (_) {
            return null;
        }
    }

    // === Adres arama — sunucu proxy + iptal + in-memory cache ===
    const PLACES_URL = '{{ route('reservation.search-places') }}';
    const placesCache = new Map(); // q -> results[] (session-içi)
    let placesAbort = null;

    async function searchPlaces(query) {
        const q = query.trim().toLowerCase();
        if (q.length < 2) return [];

        // Cache hit — anında dön
        if (placesCache.has(q)) return placesCache.get(q);

        // Eski request'i iptal et — kullanıcı yazmaya devam ettiyse cevabı çöpe
        if (placesAbort) placesAbort.abort();
        placesAbort = new AbortController();

        try {
            const res = await fetch(`${PLACES_URL}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' },
                signal: placesAbort.signal,
            });
            if (!res.ok) return [];
            const data = await res.json();
            const results = Array.isArray(data.results) ? data.results : [];

            // Cache (LRU benzeri — 50 girdiyle sınırla)
            if (placesCache.size >= 50) placesCache.delete(placesCache.keys().next().value);
            placesCache.set(q, results);

            return results;
        } catch (err) {
            if (err.name === 'AbortError') return [];
            console.warn('[Ferogo] searchPlaces failed', err);
            return [];
        }
    }

    function startSimulation(center) {
        loadingEl.style.display = 'none';
        userCenterGlobal = center;

        // Async reverse geocode for pickup address
        reverseGeocode(center[0], center[1]).then(addr => {
            userAddressGlobal = addr;
        });

        map = L.map('ferogo-radar-map', {
            zoomControl: false,
            attributionControl: true,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            dragging: true,
            tap: true,
        }).setView(center, 14);

        // Dark map tiles — CartoDB Dark Matter
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(map);
        // Labels on top — subtle
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19,
            opacity: 0.7,
        }).addTo(map);

        userMarker = L.marker(center, { icon: userIcon(), interactive: false, zIndexOffset: 1000 }).addTo(map);

        drivers = Array.from({ length: DRIVER_COUNT }, (_, i) => makeDriver(center, i));
        drivers.forEach(d => {
            d.marker = L.marker([d.lat, d.lng], { icon: driverIcon(d.state), interactive: false }).addTo(map);
        });

        renderRail(center);
        tickHandle = setInterval(() => tick(center), TICK_MS);

        // Gerçek sürücüleri arka planda çek — modal seçimi için
        fetchRealDrivers(center);
        if (realDriversHandle) clearInterval(realDriversHandle);
        realDriversHandle = setInterval(() => fetchRealDrivers(center), 8000);

        // Pause when off-screen
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting && !tickHandle) {
                    tickHandle = setInterval(() => tick(center), TICK_MS);
                } else if (!e.isIntersecting && tickHandle) {
                    clearInterval(tickHandle);
                    tickHandle = null;
                }
            });
        }, { threshold: 0.1 });
        io.observe(mapEl);
    }

    function fallbackToIzmir() {
        startSimulation(DEFAULT_CENTER);
    }

    // ===== QUICK SELECT MODAL =====
    const RIDE_REQ_URL    = '{{ route('ride_requests.store') }}';
    const RIDE_REQ_SHOW   = (id) => '{{ url('/api/ride-requests') }}/' + encodeURIComponent(id);
    const OTP_SEND_URL    = '{{ route('phone.send_otp') }}';
    const OTP_VERIFY_URL  = '{{ route('phone.verify_otp') }}';
    const LOGIN_URL       = '{{ route('customer.login') }}?return=/yolculuk-yapin';
    const POLL_STATUS_MS  = 2000;
    const POLL_CHAT_MS    = 2500;

    // Sunucudan gelen login durumu — auth-required gate + OTP atlama için.
    @php
        $ferogoAuthPayload = $authedCustomer ? [
            'id'          => $authedCustomer->id,
            'name'        => $authedCustomer->name,
            'phone'       => $authedCustomer->phone,
            'trust_label' => $authedTrust?->trustLabel() ?? 'normal',
            'trust_score' => (int) ($authedTrust?->trust_score ?? 50),
        ] : null;
    @endphp
    const FEROGO_AUTH = {!! json_encode($ferogoAuthPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

    // ===== CİHAZ FİNGERPRİNT (rate limit + sabotaj koruması) =====
    // Hafif: ekran + saat dilimi + dil + UA + canvas — sabit-ish 64 char hash
    const deviceFingerprint = (() => {
        try {
            const stored = localStorage.getItem('fero_fp');
            if (stored && stored.length === 64) return stored;
        } catch (_) {}
        const parts = [
            navigator.userAgent || '',
            navigator.language || '',
            new Date().getTimezoneOffset(),
            screen.width + 'x' + screen.height + 'x' + (screen.colorDepth || 24),
            navigator.hardwareConcurrency || 0,
            navigator.platform || '',
            Math.random().toString(36).slice(2), // tekil cihaz tanımlayıcı
        ].join('|');
        // Basit hash → hex (64 char için iki tur SHA-256 lazım ama subtle async)
        // Senkron tutmak için djb2 + suffix uzatma
        let h = 5381;
        for (let i = 0; i < parts.length; i++) h = ((h << 5) + h + parts.charCodeAt(i)) >>> 0;
        const fp = (h.toString(16) + parts.length.toString(16) + Date.now().toString(16) + Math.random().toString(16).slice(2)).slice(0, 64).padEnd(64, '0');
        try { localStorage.setItem('fero_fp', fp); } catch (_) {}
        return fp;
    })();

    // ===== Doğrulama token saklama (telefon başına 24 saat) =====
    function normalizePhoneJs(p) {
        let d = String(p || '').replace(/\D+/g, '');
        if (d.startsWith('90') && d.length === 12) d = d.slice(2);
        if (d.startsWith('0') && d.length === 11) d = d.slice(1);
        return d;
    }
    function storedTokenFor(phone) {
        try {
            const key = 'fero_otp_token:' + normalizePhoneJs(phone);
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const obj = JSON.parse(raw);
            if (!obj || !obj.token || !obj.expires_at) return null;
            if (Date.now() > obj.expires_at) { localStorage.removeItem(key); return null; }
            return obj.token;
        } catch (_) { return null; }
    }
    function storeToken(phone, token) {
        try {
            const key = 'fero_otp_token:' + normalizePhoneJs(phone);
            // Token TTL backend'de 24 saat; biz 23 saat tutalım (clock drift için)
            localStorage.setItem(key, JSON.stringify({
                token,
                expires_at: Date.now() + 23 * 3600 * 1000,
            }));
        } catch (_) {}
    }

    const modalEl = document.getElementById('quick-modal');
    const modalAuthRequired = document.getElementById('quick-modal-auth-required');
    const modalDriverProfile = document.getElementById('quick-modal-driver-profile');
    const modalForm = document.getElementById('quick-modal-form');
    const modalOtp = document.getElementById('quick-modal-otp');
    const modalWaiting = document.getElementById('quick-modal-waiting');
    const modalReconfirm = document.getElementById('quick-modal-reconfirm');
    const modalVisualVerify = document.getElementById('quick-modal-visual-verify');
    const modalAccepted = document.getElementById('quick-modal-accepted');
    const modalTerminal = document.getElementById('quick-modal-terminal');
    const qmOtpCode = document.getElementById('qm-otp-code');
    const qmOtpError = document.getElementById('qm-otp-error');
    const qmOtpVerify = document.getElementById('qm-otp-verify');
    const qmOtpVerifyText = document.getElementById('qm-otp-verify-text');
    const qmOtpVerifySpinner = document.getElementById('qm-otp-verify-spinner');
    const qmOtpResend = document.getElementById('qm-otp-resend');
    const qmOtpCountdown = document.getElementById('qm-otp-countdown');
    const qmOtpBack = document.getElementById('qm-otp-back');
    const qmOtpPhoneLabel = document.getElementById('qm-otp-phone-label');
    const qmOtpDev = document.getElementById('qm-otp-dev');
    const qmOtpDevCode = document.getElementById('qm-otp-dev-code');
    const qmConfirmBar = document.getElementById('qm-confirm-bar');
    const qmConfirmBtn = document.getElementById('qm-confirm-btn');
    const qmPickupInput = document.getElementById('qm-pickup-address');
    const qmPickupCoords = document.getElementById('qm-pickup-coords');
    const qmDropoffInput = document.getElementById('qm-dropoff-address');
    const qmDropoffSuggestions = document.getElementById('qm-dropoff-suggestions');
    const qmFareDistance = document.getElementById('qm-fare-distance');
    const qmFareDuration = document.getElementById('qm-fare-duration');
    const qmFareTotal = document.getElementById('qm-fare-total');
    const qmError = document.getElementById('qm-error');
    const qmSubmit = document.getElementById('qm-submit');
    const qmSubmitText = document.getElementById('qm-submit-text');
    const qmSubmitSpinner = document.getElementById('qm-submit-spinner');

    let selectedDriver = null;       // ekrandaki driver objesi (mock ya da real)
    let selectedRealDriver = null;   // backend'e gönderilecek real driver
    let selectedDropoff = null;      // { lat, lng, display_name }
    let dropoffSearchTimer = null;

    // Ride request state (waiting/accepted)
    let activeRequestId = null;
    let statusPollHandle = null;
    let chatPollHandle = null;
    let waitingCountdownHandle = null;
    let chatLastMessageId = 0;

    function showStage(name) {
        modalAuthRequired.classList.toggle('hidden', name !== 'auth-required');
        modalDriverProfile.classList.toggle('hidden', name !== 'driver-profile');
        modalForm.classList.toggle('hidden', name !== 'form');
        modalOtp.classList.toggle('hidden', name !== 'otp');
        modalWaiting.classList.toggle('hidden', name !== 'waiting');
        modalReconfirm.classList.toggle('hidden', name !== 'reconfirm');
        modalVisualVerify.classList.toggle('hidden', name !== 'visual-verify');
        modalAccepted.classList.toggle('hidden', name !== 'accepted');
        modalTerminal.classList.toggle('hidden', name !== 'terminal');
        // Driver-profile stage'inde header'ı gizle — kendi hero'su var
        const modalHeader = document.querySelector('#quick-modal .relative.px-6.pt-6.pb-5');
        if (modalHeader) modalHeader.classList.toggle('hidden', name === 'driver-profile');
    }

    // Auth-required stage: "Vazgeç" / "Giriş Yap" butonları
    document.getElementById('qm-auth-cancel').addEventListener('click', () => closeQuickModal());

    // Driver profile stage: "Geri" → kapat, "Bu Sürücüyü Çağır" → form'a geç
    document.getElementById('qm-dp-back').addEventListener('click', () => closeQuickModal());
    document.getElementById('qm-dp-call').addEventListener('click', () => {
        showStage('form');
        setTimeout(() => qmDropoffInput.focus(), 100);
    });
    document.getElementById('qm-dp-error-close').addEventListener('click', () => closeQuickModal());

    // ===== PHOTO LIGHTBOX =====
    let currentPhotos = [];
    let currentLightboxIdx = 0;
    const lightboxEl     = document.getElementById('qm-lightbox');
    const lightboxImg    = document.getElementById('qm-lightbox-img');
    const lightboxIndex  = document.getElementById('qm-lightbox-index');
    const lightboxTotal  = document.getElementById('qm-lightbox-total');

    function openLightbox(idx) {
        if (!currentPhotos.length) return;
        currentLightboxIdx = ((idx % currentPhotos.length) + currentPhotos.length) % currentPhotos.length;
        lightboxImg.src = currentPhotos[currentLightboxIdx];
        lightboxIndex.textContent = currentLightboxIdx + 1;
        lightboxTotal.textContent = currentPhotos.length;
        lightboxEl.classList.remove('hidden');
        lightboxEl.classList.add('flex');
    }
    function closeLightbox() {
        lightboxEl.classList.add('hidden');
        lightboxEl.classList.remove('flex');
    }
    document.getElementById('qm-lightbox-close').addEventListener('click', closeLightbox);
    document.getElementById('qm-lightbox-prev').addEventListener('click', () => openLightbox(currentLightboxIdx - 1));
    document.getElementById('qm-lightbox-next').addEventListener('click', () => openLightbox(currentLightboxIdx + 1));
    lightboxEl.addEventListener('click', (e) => {
        if (e.target === lightboxEl) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
        if (lightboxEl.classList.contains('hidden')) return;
        if (e.key === 'Escape')     closeLightbox();
        if (e.key === 'ArrowLeft')  openLightbox(currentLightboxIdx - 1);
        if (e.key === 'ArrowRight') openLightbox(currentLightboxIdx + 1);
    });

    // API'den zengin profil çek + render
    async function fetchAndRenderDriverProfile(driverId) {
        const loading = document.getElementById('qm-dp-loading');
        const content = document.getElementById('qm-dp-content');
        const error   = document.getElementById('qm-dp-error');
        loading.classList.remove('hidden');
        content.classList.add('hidden');
        error.classList.add('hidden');

        try {
            const res = await fetch(`/api/drivers/${driverId}/profile`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Profil yüklenemedi.');
            renderDriverProfile(data.driver);
            loading.classList.add('hidden');
            content.classList.remove('hidden');
        } catch (e) {
            loading.classList.add('hidden');
            document.getElementById('qm-dp-error-msg').textContent = e.message || 'Beklenmedik hata.';
            error.classList.remove('hidden');
        }
    }

    function renderDriverProfile(d) {
        const avatarEl = document.getElementById('qm-dp-avatar');
        const avatarFb = document.getElementById('qm-dp-avatar-fallback');
        if (d.avatar) {
            avatarEl.style.backgroundImage = `url('${d.avatar}')`;
            avatarFb.textContent = '';
        } else {
            avatarEl.style.backgroundImage = '';
            avatarFb.textContent = (d.name || 'F').trim().charAt(0).toUpperCase();
        }

        document.getElementById('qm-dp-name').textContent = d.short_name || d.name;
        const expLabel = d.experience?.label && d.experience.label !== '—' ? d.experience.label : null;
        const expBadge = document.getElementById('qm-dp-exp-badge');
        if (expLabel) { expBadge.textContent = expLabel; expBadge.classList.remove('hidden'); }
        else { expBadge.classList.add('hidden'); }

        document.getElementById('qm-dp-rating').textContent = `★ ${Number(d.rating || 0).toFixed(2)}`;
        document.getElementById('qm-dp-trips').textContent = `${(d.total_rides || 0).toLocaleString('tr-TR')} yolculuk`;
        document.getElementById('qm-dp-member-since').textContent = d.member_since || '—';
        document.getElementById('qm-dp-bio').textContent = d.bio || '—';

        // Credentials
        const credsEl = document.getElementById('qm-dp-credentials');
        credsEl.innerHTML = (d.credentials || []).map(c => {
            const ring = c.valid
                ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                : 'bg-zinc-500/10 border-white/10 text-zinc-500';
            const check = c.valid
                ? '<span class="text-emerald-400">✓</span>'
                : '<span class="text-zinc-600">·</span>';
            return `
                <div class="p-3 rounded-xl border ${ring}">
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold">
                        <span>${c.icon || ''}</span>
                        <span class="truncate">${escapeChat(c.label)}</span>
                        ${check}
                    </div>
                    ${c.detail ? `<div class="text-[10px] text-zinc-400 mt-0.5 truncate">${escapeChat(c.detail)}</div>` : ''}
                </div>
            `;
        }).join('');

        // Vehicle
        const vehicleWrap = document.getElementById('qm-dp-vehicle-wrap');
        const photosWrap  = document.getElementById('qm-dp-photos-wrap');
        if (d.vehicle) {
            vehicleWrap.classList.remove('hidden');
            const vname = [d.vehicle.brand, d.vehicle.model].filter(Boolean).join(' ') || (d.vehicle.class_name || 'Araç');
            const vmeta = [d.vehicle.class_name, d.vehicle.year, d.vehicle.color].filter(Boolean).join(' · ');
            document.getElementById('qm-dp-vehicle-name').textContent = vname;
            document.getElementById('qm-dp-vehicle-meta').textContent = vmeta || '—';

            const feats = d.vehicle.features || [];
            document.getElementById('qm-dp-vehicle-features').innerHTML = feats.map(f =>
                `<span class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300">
                    <span>${f.icon}</span><span>${escapeChat(f.label)}</span>
                </span>`
            ).join('');

            const insEl = document.getElementById('qm-dp-vehicle-insurance');
            const inspEl = document.getElementById('qm-dp-vehicle-inspection');
            insEl.innerHTML  = d.vehicle.insurance_valid  ? '<span class="text-emerald-400">●</span> Sigorta geçerli' : '<span class="text-amber-400">●</span> Sigorta süresi geçmiş';
            inspEl.innerHTML = d.vehicle.inspection_valid ? '<span class="text-emerald-400">●</span> Muayene geçerli' : '<span class="text-amber-400">●</span> Muayene süresi geçmiş';

            // Photos grid + lightbox
            // GEÇİCİ: araç fotoğrafları kullanıcı tarafında gizli — geri açmak için
            // SHOW_VEHICLE_PHOTOS_IN_PROFILE = true yap.
            const SHOW_VEHICLE_PHOTOS_IN_PROFILE = false;
            const photos = (d.vehicle.photos || []).filter(Boolean);
            currentPhotos = photos;
            if (SHOW_VEHICLE_PHOTOS_IN_PROFILE && photos.length > 0) {
                photosWrap.classList.remove('hidden');
                document.getElementById('qm-dp-photos-count').textContent = photos.length;
                document.getElementById('qm-dp-photos').innerHTML = photos.map((url, idx) =>
                    `<button type="button" data-photo-idx="${idx}"
                             class="qm-photo-thumb relative group block rounded-xl overflow-hidden border border-white/10 hover:border-brand/60 transition aspect-[3/2] bg-zinc-900 cursor-zoom-in">
                        <img src="${url}" alt="" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center">
                            <span class="opacity-0 group-hover:opacity-100 text-white text-xl transition">🔍</span>
                        </div>
                    </button>`
                ).join('');
                // Thumbnail click -> lightbox
                document.querySelectorAll('.qm-photo-thumb').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const idx = parseInt(btn.dataset.photoIdx, 10);
                        openLightbox(idx);
                    });
                });
            } else {
                photosWrap.classList.add('hidden');
            }
        } else {
            vehicleWrap.classList.add('hidden');
            photosWrap.classList.add('hidden');
        }
    }

    function openQuickModal(driver) {
        // Anonim ziyaretçiler için: önce kayıt/giriş zorunlu (sabotaj koruması).
        if (!FEROGO_AUTH) {
            resetActiveRequest();
            // Header'da seçilen sürücü görünmesin → minimum şey göster
            document.getElementById('qm-driver-icon').textContent = '🔒';
            document.getElementById('qm-driver-name').textContent = 'Önce giriş yap';
            document.getElementById('qm-driver-rating').textContent = '';
            document.getElementById('qm-driver-badge').innerHTML = '';
            document.getElementById('qm-driver-meta').textContent = '';
            showStage('auth-required');
            modalEl.classList.remove('hidden');
            modalEl.classList.add('flex');
            document.body.style.overflow = 'hidden';
            return;
        }

        // Eğer gerçek sürücü varsa onu seç, yoksa mock'la devam (submit'te uyarı çıkar)
        const real = driver.vehicle_class_slug ? driver : pickRealDriverFor(driver);
        selectedRealDriver = real || null;
        selectedDriver = driver;
        selectedDropoff = null;
        resetActiveRequest();

        // Header — varsa real driver bilgileriyle göster (PRIVACY: plaka eşleştirme sonrası açılır)
        const display = real || driver;
        const slug = display.vehicle_class_slug || display.vSlug || 'easy';
        const cls  = display.vehicle_class || display.vclass || 'Easy';
        // PRIVACY: plaka müşteri eşleştirme öncesi GİZLİ
        // Araç markası + modeli gösterilir, plaka "Eşleştirme sonrası" ibaresiyle değiştirilir
        const vehicleSummary = (display.vehicle_label || display.vehicle_class || 'Üye Sürücü');
        const rating = Number(display.rating || 0).toFixed(2);
        document.getElementById('qm-driver-icon').textContent = VCLASS_ICONS[slug] || '🚗';
        document.getElementById('qm-driver-name').textContent = display.name;
        document.getElementById('qm-driver-rating').textContent = `★ ${rating}`;
        document.getElementById('qm-driver-badge').innerHTML = classBadge(slug, cls);
        document.getElementById('qm-driver-meta').textContent = vehicleSummary;

        // Reset form
        modalForm.reset();
        // ÖNCE detaylı sürücü/araç profili — API'den zengin veri (bio + sertifikalar + fotoğraflar)
        showStage('driver-profile');
        if (real && real.id) {
            fetchAndRenderDriverProfile(real.id);
        } else {
            // Mock sürücü — API çağrısı yok, doğrudan kapat (gerçek sürücüsüz işlem yok)
            document.getElementById('qm-dp-loading').classList.add('hidden');
            document.getElementById('qm-dp-error-msg').textContent = 'Şu an çevrimiçi gerçek sürücü yok. Lütfen sayfayı yenile.';
            document.getElementById('qm-dp-error').classList.remove('hidden');
        }
        qmError.classList.add('hidden');
        qmDropoffSuggestions.classList.add('hidden');
        qmFareDistance.textContent = '—';
        qmFareDuration.textContent = '—';
        qmFareTotal.textContent = '—';

        // Pickup
        if (userAddressGlobal) {
            qmPickupInput.value = userAddressGlobal;
        } else {
            qmPickupInput.value = '';
            qmPickupInput.placeholder = 'Konum adresini gir';
        }
        if (userCenterGlobal) {
            qmPickupCoords.textContent = `${userCenterGlobal[0].toFixed(5)}, ${userCenterGlobal[1].toFixed(5)}`;
        }

        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
        document.body.style.overflow = 'hidden';
        setTimeout(() => qmDropoffInput.focus(), 100);
    }

    function resetActiveRequest() {
        activeRequestId = null;
        chatLastMessageId = 0;
        if (statusPollHandle) { clearInterval(statusPollHandle); statusPollHandle = null; }
        if (chatPollHandle) { clearInterval(chatPollHandle); chatPollHandle = null; }
        if (waitingCountdownHandle) { clearInterval(waitingCountdownHandle); waitingCountdownHandle = null; }
        const chat = document.getElementById('qm-chat-list');
        if (chat) chat.innerHTML = '';
    }

    function closeQuickModal() {
        // Aktif waiting ise → cancel gönder (sürücü boşuna beklemesin)
        const wasWaiting = !modalWaiting.classList.contains('hidden');
        const idForCancel = activeRequestId;

        modalEl.classList.add('hidden');
        modalEl.classList.remove('flex');
        document.body.style.overflow = '';

        if (wasWaiting && idForCancel) {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            try {
                // keepalive: pencere kapansa bile request bitsin
                fetch(`{{ url('/api/ride-requests') }}/${encodeURIComponent(idForCancel)}/cancel`, {
                    method: 'POST',
                    keepalive: true,
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                }).catch(() => {});
            } catch (_) {}
        }
        resetActiveRequest();
    }

    document.getElementById('quick-modal-close').addEventListener('click', closeQuickModal);
    document.getElementById('quick-modal-backdrop').addEventListener('click', closeQuickModal);
    document.getElementById('quick-modal-done').addEventListener('click', closeQuickModal);
    document.getElementById('qm-terminal-close').addEventListener('click', closeQuickModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modalEl.classList.contains('hidden')) closeQuickModal();
    });

    // Dropoff autocomplete — 180 ms debounce + iptal + stale-yanıt koruması
    qmDropoffInput.addEventListener('input', () => {
        const q = qmDropoffInput.value.trim();
        selectedDropoff = null;
        clearTimeout(dropoffSearchTimer);
        if (q.length < 2) {
            qmDropoffSuggestions.classList.add('hidden');
            updateFarePreview();
            return;
        }
        dropoffSearchTimer = setTimeout(async () => {
            const currentQ = q; // snapshot
            const results = await searchPlaces(currentQ);

            // Kullanıcı bu sırada yazmaya devam ettiyse — bu cevabı atla
            if (qmDropoffInput.value.trim() !== currentQ) return;

            if (!results.length) {
                qmDropoffSuggestions.classList.add('hidden');
                return;
            }
            qmDropoffSuggestions.innerHTML = results.map((r, i) => `
                <button type="button" data-idx="${i}" class="qm-suggestion w-full text-left px-3 py-2.5 hover:bg-white/5 transition">
                    <div class="text-xs text-white truncate">${r.display_name.split(',').slice(0, 2).join(',')}</div>
                    <div class="text-[10px] text-zinc-500 truncate">${r.display_name.split(',').slice(2).join(',').trim()}</div>
                </button>`).join('');
            qmDropoffSuggestions.classList.remove('hidden');
            qmDropoffSuggestions.querySelectorAll('.qm-suggestion').forEach(btn => {
                btn.addEventListener('click', () => {
                    const r = results[parseInt(btn.dataset.idx, 10)];
                    selectedDropoff = { lat: parseFloat(r.lat), lng: parseFloat(r.lon), display_name: r.display_name };
                    qmDropoffInput.value = r.display_name.split(',').slice(0, 2).join(',');
                    qmDropoffSuggestions.classList.add('hidden');
                    updateFarePreview();
                });
            });
        }, 180);
    });

    function updateFarePreview() {
        if (!selectedDriver || !userCenterGlobal || !selectedDropoff) {
            qmFareDistance.textContent = '—';
            qmFareDuration.textContent = '—';
            qmFareTotal.textContent = '—';
            return;
        }
        // Düz mesafe + %20 yol uzunluğu payı
        const straight = distanceKm(userCenterGlobal, [selectedDropoff.lat, selectedDropoff.lng]);
        const km = straight * 1.2;
        const mins = Math.max(5, Math.round(km * 2.2 + 3));

        // Hızlı yerel tahmin (backend'e gitmeden)
        const rates = {
            easy:     { base: 50,  perKm: 22, min: 150 },
            platinum: { base: 100, perKm: 35, min: 250 },
            vip:      { base: 200, perKm: 55, min: 500 },
        };
        const r = rates[selectedDriver.vSlug] || rates.easy;
        const calc = Math.max(r.min, r.base + km * r.perKm);

        qmFareDistance.textContent = `${km.toFixed(1)} km`;
        qmFareDuration.textContent = `${mins} dk`;
        qmFareTotal.textContent = `₺${Math.round(calc)}`;
    }

    // Pending submit payload — OTP doğrulama sonrası gönderilmek üzere saklanır.
    let pendingPayload = null;
    let otpResendHandle = null;

    function buildPayloadFromForm() {
        const fd = new FormData(modalForm);
        const straight = distanceKm(userCenterGlobal, [selectedDropoff.lat, selectedDropoff.lng]);
        const km = straight * 1.2;
        const mins = Math.max(5, Math.round(km * 2.2 + 3));
        const fallbacks = realDrivers
            .filter(d => d.id !== selectedRealDriver.id)
            .map(d => d.id);
        const rates = {
            easy:     { base: 50,  perKm: 22, min: 150 },
            platinum: { base: 100, perKm: 35, min: 250 },
            vip:      { base: 200, perKm: 55, min: 500 },
        };
        const r = rates[selectedRealDriver.vehicle_class_slug] || rates.easy;
        const estFare = Math.max(r.min, r.base + km * r.perKm);

        return {
            vehicle_class_slug:    selectedRealDriver.vehicle_class_slug,
            pickup_address:        qmPickupInput.value.trim() || `${userCenterGlobal[0].toFixed(5)}, ${userCenterGlobal[1].toFixed(5)}`,
            pickup_lat:            userCenterGlobal[0],
            pickup_lng:            userCenterGlobal[1],
            dropoff_address:       selectedDropoff.display_name,
            dropoff_lat:           selectedDropoff.lat,
            dropoff_lng:           selectedDropoff.lng,
            customer_name:         fd.get('customer_name'),
            customer_phone:        fd.get('customer_phone'),
            distance_km:           parseFloat(km.toFixed(2)),
            duration_minutes:      mins,
            estimated_fare:        Math.round(estFare),
            preferred_driver_id:   selectedRealDriver.id,
            fallback_driver_ids:   fallbacks,
            fingerprint:           deviceFingerprint,
            kvkk_consent:          fd.get('kvkk_consent') ? 1 : 0,
        };
    }

    // ===== SUBMIT — OTP koruma katmanlı =====
    modalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        qmError.classList.add('hidden');

        if (!userCenterGlobal) {
            qmError.textContent = 'Konum bilgisi eksik. Konum izni ver veya sayfayı yenile.';
            qmError.classList.remove('hidden'); return;
        }
        if (!selectedDropoff) {
            qmError.textContent = 'Lütfen önerilerden bir bırakış noktası seç.';
            qmError.classList.remove('hidden'); return;
        }
        if (!selectedRealDriver) {
            // Diagnostic: çevrimiçi var ama liste yüklemediyse → fetch'i tekrar zorla
            if (realDriversTotalOnline > 0) {
                qmError.textContent = `Sürücü listesi yüklenmedi (${realDriversTotalOnline} çevrimiçi var). Sayfayı yenile (Cmd+Shift+R) ve tekrar dene.`;
            } else {
                qmError.textContent = 'Şu an hiç çevrimiçi sürücü yok. Birkaç dakika sonra dene ya da rezervasyon formundan ilerle.';
            }
            // Son şans: bir kez daha fetch
            if (userCenterGlobal) fetchRealDrivers(userCenterGlobal);
            qmError.classList.remove('hidden'); return;
        }

        pendingPayload = buildPayloadFromForm();

        // Login müşteri: session zaten doğrulanmış, OTP yok → direkt gönder.
        if (FEROGO_AUTH) {
            await submitRideRequest(null);
            return;
        }

        // Anonim akış: cache'de token varsa kullan, yoksa OTP iste
        const cached = storedTokenFor(pendingPayload.customer_phone);
        if (cached) {
            await submitRideRequest(cached);
            return;
        }
        await requestOtp(pendingPayload.customer_phone);
    });

    // Rate-limit hatasi: qmError'da canli geri sayim + submit butonu disable
    let qmRateLimitHandle = null;
    function showQmRateLimit(baseMsg, retryAfter) {
        if (qmRateLimitHandle) clearInterval(qmRateLimitHandle);
        let remaining = Math.max(1, parseInt(retryAfter, 10) || 60);
        const cleanMsg = baseMsg.replace(/\s*\d+\s*dakika.*$/i, '').replace(/\s*[Bb]ekle.*$/, '').trim();
        function render() {
            qmError.innerHTML = `${cleanMsg} <span class="text-red-200 font-bold tabular-nums ml-1">${remaining}</span> sn`;
            qmError.classList.remove('hidden');
            qmSubmit.disabled = true;
            qmSubmitText.textContent = `Bekle ${remaining}s`;
        }
        render();
        qmRateLimitHandle = setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(qmRateLimitHandle);
                qmRateLimitHandle = null;
                qmError.classList.add('hidden');
                qmSubmit.disabled = false;
                qmSubmitText.textContent = 'Talebi Gönder';
                qmSubmitSpinner.classList.add('hidden');
                return;
            }
            render();
        }, 1000);
    }

    async function requestOtp(phone) {
        qmError.classList.add('hidden');
        qmSubmit.disabled = true;
        qmSubmitText.textContent = 'Kod gönderiliyor…';
        qmSubmitSpinner.classList.remove('hidden');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(OTP_SEND_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ phone, fingerprint: deviceFingerprint }),
            });
            const data = await res.json();
            if (!data.ok) {
                if (data.retry_after) {
                    showQmRateLimit(data.message || 'Çok sık kod isteği.', data.retry_after);
                } else {
                    qmError.textContent = data.message || 'Kod gönderilemedi.';
                    qmError.classList.remove('hidden');
                }
                return;
            }


            qmOtpPhoneLabel.textContent = phone;
            qmOtpCode.value = '';
            qmOtpError.classList.add('hidden');
            if (data.dev_code) {
                qmOtpDev.classList.remove('hidden');
                qmOtpDevCode.textContent = data.dev_code;
            } else {
                qmOtpDev.classList.add('hidden');
            }
            startOtpCountdown();
            showStage('otp');
            setTimeout(() => qmOtpCode.focus(), 80);
        } catch (err) {
            qmError.textContent = 'Bağlantı hatası. Tekrar dene.';
            qmError.classList.remove('hidden');
        } finally {
            qmSubmit.disabled = false;
            qmSubmitText.textContent = 'Talebi Gönder';
            qmSubmitSpinner.classList.add('hidden');
        }
    }

    function startOtpCountdown() {
        let s = 60;
        qmOtpResend.disabled = true;
        qmOtpCountdown.textContent = s;
        if (otpResendHandle) clearInterval(otpResendHandle);
        otpResendHandle = setInterval(() => {
            s -= 1;
            qmOtpCountdown.textContent = s;
            if (s <= 0) {
                clearInterval(otpResendHandle);
                qmOtpResend.disabled = false;
                qmOtpResend.innerHTML = 'Tekrar gönder';
            }
        }, 1000);
    }

    qmOtpResend.addEventListener('click', () => {
        if (!pendingPayload) return;
        requestOtp(pendingPayload.customer_phone);
    });
    qmOtpBack.addEventListener('click', () => {
        if (otpResendHandle) clearInterval(otpResendHandle);
        showStage('form');
    });

    qmOtpVerify.addEventListener('click', async () => {
        const code = (qmOtpCode.value || '').trim();
        qmOtpError.classList.add('hidden');
        if (!/^\d{6}$/.test(code)) {
            qmOtpError.textContent = '6 haneli kodu eksiksiz gir.';
            qmOtpError.classList.remove('hidden'); return;
        }
        if (!pendingPayload) {
            qmOtpError.textContent = 'Oturum süresi doldu. Baştan başla.';
            qmOtpError.classList.remove('hidden'); return;
        }

        qmOtpVerify.disabled = true;
        qmOtpVerifyText.textContent = 'Doğrulanıyor…';
        qmOtpVerifySpinner.classList.remove('hidden');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(OTP_VERIFY_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    phone: pendingPayload.customer_phone,
                    name:  pendingPayload.customer_name,
                    code,
                    fingerprint: deviceFingerprint,
                }),
            });
            const data = await res.json();
            if (!data.ok || !data.token) {
                qmOtpError.textContent = data.message || 'Kod hatalı.';
                qmOtpError.classList.remove('hidden');
                return;
            }
            storeToken(pendingPayload.customer_phone, data.token);
            await submitRideRequest(data.token);
        } catch (err) {
            qmOtpError.textContent = 'Bağlantı hatası. Tekrar dene.';
            qmOtpError.classList.remove('hidden');
        } finally {
            qmOtpVerify.disabled = false;
            qmOtpVerifyText.textContent = 'Doğrula ve Devam Et';
            qmOtpVerifySpinner.classList.add('hidden');
        }
    });

    async function submitRideRequest(verificationToken) {
        if (!pendingPayload) return;
        const payload = Object.assign({}, pendingPayload);
        if (verificationToken) payload.verification_token = verificationToken;
        // Login yoksa name/phone form input'larından gelir; loginse hidden input'lardan.

        qmOtpVerify.disabled = true;
        qmOtpVerifyText.textContent = 'Talep gönderiliyor…';
        qmOtpVerifySpinner.classList.remove('hidden');
        qmSubmit.disabled = true;
        qmSubmitText.textContent = 'Gönderiliyor…';
        qmSubmitSpinner.classList.remove('hidden');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(RIDE_REQ_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            let data;
            try { data = await res.json(); }
            catch (_) { throw new Error('Sunucu beklenmedik bir cevap döndü.'); }

            if (!res.ok || !data.success) {
                // Console'a tam yanıtı log'la — kullanıcı görmese de teşhis için
                console.warn('[RR-STORE] failed', { status: res.status, body: data });

                // Anonim akış: token geçersizse OTP iste (session düşmüşse görmüyoruz, baştan başla)
                if (!FEROGO_AUTH && data.phone_reverify_required) {
                    try { localStorage.removeItem('fero_otp_token:' + normalizePhoneJs(payload.customer_phone)); } catch (_) {}
                    await requestOtp(payload.customer_phone);
                    return;
                }

                // Hata mesajını TÜM detaylarıyla form'da göster — auto-redirect YOK
                let msgs = [];
                if (data.errors && typeof data.errors === 'object') {
                    Object.entries(data.errors).forEach(([field, arr]) => {
                        const list = Array.isArray(arr) ? arr : [arr];
                        msgs.push(`${field}: ${list.join(', ')}`);
                    });
                }
                if (data.message && !msgs.length) msgs.push(data.message);
                if (!msgs.length) msgs.push(`Sunucu hatası (${res.status}). Tekrar dene.`);

                const target = modalOtp.classList.contains('hidden') ? qmError : qmOtpError;
                target.innerHTML = msgs.map(m => `<div>${m.replace(/</g, '&lt;')}</div>`).join('');
                target.classList.remove('hidden');
                return;
            }

            activeRequestId = data.public_id;
            applyStatus(data.status);
            showStage('waiting');
            startStatusPolling();
        } catch (err) {
            const target = modalOtp.classList.contains('hidden') ? qmError : qmOtpError;
            target.textContent = err.message;
            target.classList.remove('hidden');
        } finally {
            qmOtpVerify.disabled = false;
            qmOtpVerifyText.textContent = 'Doğrula ve Devam Et';
            qmOtpVerifySpinner.classList.add('hidden');
            qmSubmit.disabled = false;
            qmSubmitText.textContent = 'Talebi Gönder';
            qmSubmitSpinner.classList.add('hidden');
        }
    }

    // ===== WAITING — vazgeç butonu =====
    document.getElementById('qm-waiting-cancel').addEventListener('click', async () => {
        if (!activeRequestId) { closeQuickModal(); return; }
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            await fetch(`{{ url('/api/ride-requests') }}/${encodeURIComponent(activeRequestId)}/cancel`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
        } catch (_) {}
        closeQuickModal();
    });

    // ===== STATUS POLLING =====
    function startStatusPolling() {
        if (statusPollHandle) clearInterval(statusPollHandle);
        statusPollHandle = setInterval(pollStatusOnce, POLL_STATUS_MS);
        pollStatusOnce();
    }
    async function pollStatusOnce() {
        if (!activeRequestId) return;
        try {
            const res = await fetch(RIDE_REQ_SHOW(activeRequestId), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (data.success && data.status) applyStatus(data.status);
        } catch (_) {}
    }
    function applyStatus(s) {
        // Faz 6 — Yolculuk başladıysa (started_at) ve henüz görsel doğrulama yapılmadıysa
        // ride/show modal'ı zaten kapalıysa müşteri paneline yönlenmiş olabilir,
        // ama hızlı seç modal'ı hala açıksa görsel doğrulama modal'ı tetiklenir.
        if (s.started_at && !s.visual_verified_at && !s.visual_verify_failed_at) {
            openVisualVerifyModal(s);
            return;
        }
        if (s.status === 'pool_expanded') {
            // Havuza yayıldı — kullanıcıya "sürücü aranıyor (yakındakilere talep gitti)" göster
            const total = (s.pool_candidate_driver_ids?.length || 0);
            document.getElementById('qm-waiting-driver').textContent =
                total > 0 ? `${total} yakın sürücü` : 'Yakındaki sürücüler';
            document.getElementById('qm-waiting-progress').textContent = 'Havuz · paralel sorgu';

            if (waitingCountdownHandle) clearInterval(waitingCountdownHandle);
            let remaining = Math.max(0, s.seconds_remaining || 0);
            document.getElementById('qm-waiting-countdown').textContent = remaining;
            waitingCountdownHandle = setInterval(() => {
                remaining = Math.max(0, remaining - 1);
                document.getElementById('qm-waiting-countdown').textContent = remaining;
                if (remaining <= 0) clearInterval(waitingCountdownHandle);
            }, 1000);

            showStage('waiting');
            return;
        }
        if (s.status === 'awaiting_customer_reconfirm') {
            // Havuzdan biri kabul etti, müşteri onayı bekleniyor (Faz 3-4)
            const drv = s.accepted_driver || {};
            document.getElementById('qm-reconfirm-icon').textContent =
                ({ easy:'🚗', platinum:'👔', vip:'♛' }[drv.vehicle_class_slug] || '🚗');
            document.getElementById('qm-reconfirm-name').textContent = drv.name || 'Üye Sürücü';
            document.getElementById('qm-reconfirm-vehicle').textContent =
                [drv.vehicle_class, drv.vehicle_label].filter(Boolean).join(' · ') || drv.vehicle_class || '—';
            document.getElementById('qm-reconfirm-rating').textContent = `★ ${Number(drv.rating || 0).toFixed(2)}`;
            document.getElementById('qm-reconfirm-trips').textContent = `${drv.trips || 0} yolculuk`;

            // Countdown
            if (waitingCountdownHandle) clearInterval(waitingCountdownHandle);
            let remaining = Math.max(0, s.seconds_remaining || 60);
            document.getElementById('qm-reconfirm-countdown').textContent = remaining;
            waitingCountdownHandle = setInterval(() => {
                remaining = Math.max(0, remaining - 1);
                document.getElementById('qm-reconfirm-countdown').textContent = remaining;
                if (remaining <= 0) clearInterval(waitingCountdownHandle);
            }, 1000);

            showStage('reconfirm');
            return;
        }
        if (s.status === 'pending') {
            // Waiting render
            const drv = s.offered_driver || {};
            document.getElementById('qm-waiting-driver').textContent = drv.name || 'Sürücü';
            document.getElementById('qm-waiting-progress').textContent =
                `${(s.current_index || 0) + 1} / ${s.total_candidates || 1}`;

            // Countdown
            if (waitingCountdownHandle) clearInterval(waitingCountdownHandle);
            let remaining = Math.max(0, s.seconds_remaining || 0);
            document.getElementById('qm-waiting-countdown').textContent = remaining;
            waitingCountdownHandle = setInterval(() => {
                remaining = Math.max(0, remaining - 1);
                document.getElementById('qm-waiting-countdown').textContent = remaining;
                if (remaining <= 0) clearInterval(waitingCountdownHandle);
            }, 1000);

            showStage('waiting');
        } else if (s.status === 'accepted') {
            if (waitingCountdownHandle) { clearInterval(waitingCountdownHandle); waitingCountdownHandle = null; }
            renderAccepted(s);
            // Sürücü vardı + müşteri onayı henüz yok → onay barını göster (60sn bot kontrolü)
            if (s.arrived_at && !s.customer_confirmed_at) {
                qmConfirmBar.classList.remove('hidden');
            } else {
                qmConfirmBar.classList.add('hidden');
            }
            startChatPolling();
            showStage('accepted');
        } else if (s.status === 'exhausted') {
            stopAllPolling();
            document.getElementById('qm-terminal-title').textContent = 'Şu an müsait sürücü yok';
            document.getElementById('qm-terminal-msg').textContent =
                'Aday sürücülerin tümü cevap vermedi. Birkaç dakika sonra tekrar dene veya rezervasyon formuna geç.';
            showStage('terminal');
        } else if (s.status === 'no_show') {
            stopAllPolling();
            document.getElementById('qm-terminal-title').textContent = 'Yolculuk iptal edildi';
            document.getElementById('qm-terminal-msg').textContent =
                'Sürücü varış noktasına geldi ama seni bulamadı. Tekrar çağrı yapmadan önce profilini gözden geçir.';
            showStage('terminal');
        } else if (s.status === 'cancelled') {
            stopAllPolling();
            closeQuickModal();
        }
    }

    // ===== FAZ 6 — Görsel doğrulama modal'ı (yolculuk başladıktan sonra) =====
    let visualVerifyShownFor = null;
    function openVisualVerifyModal(s) {
        if (visualVerifyShownFor === activeRequestId) return; // tek sefer
        visualVerifyShownFor = activeRequestId;

        const drv = s.accepted_driver || {};
        document.getElementById('vv-driver-photo').src =
            drv.photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(drv.name || 'Sürücü')}&background=F0C040&color=000&size=128&bold=true`;
        document.getElementById('vv-driver-name').textContent = drv.name || 'Üye Sürücü';
        document.getElementById('vv-driver-vehicle').textContent =
            [drv.vehicle_class, drv.vehicle_label].filter(Boolean).join(' · ') || '—';
        document.getElementById('vv-driver-plate').textContent = drv.plate || '— — —';

        const photosWrap = document.getElementById('vv-vehicle-photos');
        photosWrap.innerHTML = '';
        const photos = drv.vehicle_photos || (drv.vehicle_photo_url ? [drv.vehicle_photo_url] : []);
        photos.slice(0, 3).forEach(url => {
            const img = document.createElement('img');
            img.src = url;
            img.alt = 'Araç';
            img.className = 'w-full h-20 object-cover rounded-lg bg-zinc-800 border border-white/10';
            photosWrap.appendChild(img);
        });

        showStage('visual-verify');
    }
    async function sendVisualVerify(match) {
        if (!activeRequestId) return;
        const yesBtn = document.getElementById('vv-confirm-yes');
        const noBtn  = document.getElementById('vv-confirm-no');
        [yesBtn, noBtn].forEach(b => b.disabled = true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(`{{ url('/api/ride-requests') }}/${encodeURIComponent(activeRequestId)}/visual-verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ match }),
            });
            const data = await res.json();
            if (match) {
                alert('✓ Doğrulandı. İyi yolculuklar!');
                showStage('accepted');
            } else {
                alert('⚠ Çağrı merkezi sürücüyle iletişime geçti. Güvenliğiniz için araçtan inebilirsiniz.');
                showStage('accepted');
            }
        } catch (err) {
            alert('Bağlantı hatası, lütfen tekrar dene.');
        } finally {
            [yesBtn, noBtn].forEach(b => b.disabled = false);
        }
    }
    document.getElementById('vv-confirm-yes')?.addEventListener('click', () => sendVisualVerify(true));
    document.getElementById('vv-confirm-no')?.addEventListener('click', () => {
        if (confirm('⚠ Sürücü/araç eşleşmiyor diyorsunuz. Çağrı merkezi sürücüyü arayacak. Devam ediyorum.')) {
            sendVisualVerify(false);
        }
    });

    // ===== RECONFIRM (Faz 4) — müşteri havuz fallback sürücüsünü onaylar/reddeder =====
    async function sendReconfirm(accept) {
        if (!activeRequestId) return;
        const buttons = document.querySelectorAll('#qm-reconfirm-accept, #qm-reconfirm-decline');
        buttons.forEach(b => b.disabled = true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(`{{ url('/api/ride-requests') }}/${encodeURIComponent(activeRequestId)}/reconfirm`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ accept }),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                console.warn('[reconfirm] failed', data);
            }
            // Polling otomatik bir sonraki durumu yakalayacak
            pollStatusOnce();
        } catch (err) {
            console.error('[reconfirm] error', err);
        } finally {
            buttons.forEach(b => b.disabled = false);
        }
    }
    document.getElementById('qm-reconfirm-accept')?.addEventListener('click', () => sendReconfirm(true));
    document.getElementById('qm-reconfirm-decline')?.addEventListener('click', () => sendReconfirm(false));

    // ===== CONFIRM — müşteri "sürücüyü gördüm" basar (bot/sabotaj koruması) =====
    qmConfirmBtn.addEventListener('click', async () => {
        if (!activeRequestId) return;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            await fetch(`{{ url('/api/ride-requests') }}/${encodeURIComponent(activeRequestId)}/confirm`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            qmConfirmBar.classList.add('hidden');
        } catch (_) {}
    });
    function stopAllPolling() {
        if (statusPollHandle) { clearInterval(statusPollHandle); statusPollHandle = null; }
        if (chatPollHandle)   { clearInterval(chatPollHandle); chatPollHandle = null; }
        if (waitingCountdownHandle) { clearInterval(waitingCountdownHandle); waitingCountdownHandle = null; }
    }

    // ===== ACCEPTED — chat + render =====
    function renderAccepted(s) {
        const drv = s.accepted_driver || {};
        document.getElementById('qm-accepted-name').textContent = drv.name || 'Sürücü';
        document.getElementById('qm-accepted-vehicle').textContent =
            [drv.vehicle_class, drv.vehicle_label, drv.plate].filter(Boolean).join(' · ') || '—';
        document.getElementById('qm-accepted-rating').textContent = `★ ${Number(drv.rating || 0).toFixed(2)}`;
        document.getElementById('qm-accepted-trips').textContent = `${drv.trips || 0} yolculuk`;
        document.getElementById('qm-accepted-pickup').textContent = qmPickupInput.value.trim();
        document.getElementById('qm-accepted-dropoff').textContent = qmDropoffInput.value.trim();
    }

    function startChatPolling() {
        if (chatPollHandle) clearInterval(chatPollHandle);
        chatPollHandle = setInterval(pollChatOnce, POLL_CHAT_MS);
        pollChatOnce();
    }
    async function pollChatOnce() {
        if (!activeRequestId) return;
        try {
            const res = await fetch(
                `{{ url('/api/ride-requests') }}/${encodeURIComponent(activeRequestId)}/messages?since_id=${chatLastMessageId}`,
                { headers: { 'Accept': 'application/json' } }
            );
            if (!res.ok) return;
            const data = await res.json();
            (data.messages || []).forEach(appendChatMessage);
        } catch (_) {}
    }
    function appendChatMessage(m) {
        if (m.id <= chatLastMessageId) return;
        chatLastMessageId = m.id;
        const chat = document.getElementById('qm-chat-list');
        const bubble = document.createElement('div');
        const align = m.sender === 'customer' ? 'justify-end' : (m.sender === 'system' ? 'justify-center' : 'justify-start');
        const bg = m.sender === 'customer' ? 'bg-brand text-black' : (m.sender === 'system' ? 'bg-white/5 text-zinc-400 italic text-[11px]' : 'bg-white/10');
        bubble.className = `flex ${align}`;
        bubble.innerHTML = `<div class="max-w-[80%] rounded-2xl px-3 py-2 ${bg}">${escapeChat(m.body)}</div>`;
        chat.appendChild(bubble);
        chat.scrollTop = chat.scrollHeight;
    }
    function escapeChat(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }
    document.getElementById('qm-chat-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!activeRequestId) return;
        const input = document.getElementById('qm-chat-input');
        const body = input.value.trim();
        if (!body) return;
        input.value = '';
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(`{{ url('/api/ride-requests') }}/${encodeURIComponent(activeRequestId)}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ body }),
            });
            const data = await res.json();
            if (data.success) appendChatMessage(data.message);
        } catch (_) { alert('Mesaj gönderilemedi.'); }
    });

    function init() {
        if (!('geolocation' in navigator)) {
            fallbackToIzmir();
            return;
        }
        // Show fallback button after 4s in case user ignores prompt
        const fallbackTimer = setTimeout(() => {
            if (fallbackBtn) fallbackBtn.classList.remove('hidden');
            if (loadingText) loadingText.innerHTML = 'Konum izni bekleniyor…<br><span class="text-xs text-zinc-500">Tarayıcının üst kısmındaki istemi onayla.</span>';
        }, 4000);

        if (fallbackBtn) {
            fallbackBtn.addEventListener('click', () => {
                clearTimeout(fallbackTimer);
                fallbackToIzmir();
            });
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                clearTimeout(fallbackTimer);
                startSimulation([pos.coords.latitude, pos.coords.longitude]);
            },
            () => {
                clearTimeout(fallbackTimer);
                fallbackToIzmir();
            },
            { timeout: 8000, maximumAge: 60000, enableHighAccuracy: false }
        );
    }

    // Lazy init — wait until user scrolls near
    const initObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            init();
            initObserver.disconnect();
        }
    }, { rootMargin: '200px' });
    initObserver.observe(mapEl);

    // ===== Sesli görüşme widget'ı için global hook'lar =====
    // Embed modunda (müşteri panelinden iframe) URL'den active_request param'i gelir
    // → activeRequestId'yi bootstrap et ki call widget pollState publicId'yi bulsun.
    (() => {
        try {
            const u = new URL(window.location.href);
            const ar = u.searchParams.get('active_request');
            if (ar && !activeRequestId) {
                activeRequestId = ar;
                console.log('[radar] activeRequestId bootstrap from URL:', ar);
                if (typeof startStatusPolling === 'function') startStatusPolling();
            }

            // Embed: iframe yüksekliği parent'a bildir → parent iframe scroll çıkmasın
            if (u.searchParams.get('embed') === '1' && window.parent !== window) {
                const sendHeight = () => {
                    try {
                        const h = Math.max(
                            document.documentElement.scrollHeight || 0,
                            document.body?.scrollHeight || 0,
                        );
                        if (h > 0) window.parent.postMessage({ type: 'ferogo:iframe-height', height: h }, '*');
                    } catch (_) {}
                };
                // İlk yükleme + DOM değişikliklerinde + periyodik
                window.addEventListener('load', sendHeight);
                document.addEventListener('DOMContentLoaded', sendHeight);
                setTimeout(sendHeight, 200);
                setTimeout(sendHeight, 800);
                setTimeout(sendHeight, 2000);
                if (window.ResizeObserver) {
                    new ResizeObserver(sendHeight).observe(document.body);
                } else {
                    setInterval(sendHeight, 2000);
                }
            }
        } catch (_) {}
    })();

    window.callWidgetGetPublicId = () => activeRequestId;
    window.callWidgetGetPeerName = () => {
        const el = document.getElementById('qm-accepted-name');
        return el ? (el.textContent || 'Sürücü') : 'Sürücü';
    };
    const callBtn = document.getElementById('qm-call-btn');
    if (callBtn) {
        callBtn.addEventListener('click', () => {
            if (window.CallWidget) window.CallWidget.start();
        });
    }
})();
</script>

@include('partials.call-widget')
@endpush
