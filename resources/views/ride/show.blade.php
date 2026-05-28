@extends('layouts.public')

@section('title', 'Yolculuk Yapın · Ferogo · Premium Şoförlü Transfer')
@section('description', 'Şehir içi, havalimanı veya uzun mesafe — profesyonel şoför, lüks araç, şeffaf fiyat. 60 saniyede rezervasyon yap, kapına gelsin.')

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
<div class="ride-mesh pt-24 relative overflow-hidden">

    {{-- Noise overlay --}}
    <div class="absolute inset-0 ride-noise opacity-[0.35] pointer-events-none mix-blend-overlay"></div>

    {{-- Floating background shapes --}}
    <div class="drift-1 absolute top-32 -left-32 w-[28rem] h-[28rem] rounded-full bg-brand/10 blur-[120px] pointer-events-none"></div>
    <div class="drift-2 absolute top-[40rem] -right-32 w-[32rem] h-[32rem] rounded-full bg-brand/15 blur-[140px] pointer-events-none"></div>

    {{-- ============ HERO ============ --}}
    <section class="relative px-6 pt-12 md:pt-20 pb-20">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

            {{-- Left: copy --}}
            <div class="lg:col-span-7">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-medium text-zinc-300 mb-8 backdrop-blur-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                    Şu an <span class="text-white font-semibold">42 şoför</span> İzmir'de hizmette
                </div>

                <h1 class="display-font text-5xl sm:text-6xl md:text-7xl lg:text-8xl text-white mb-8">
                    Adresini<br>
                    yaz,<br>
                    <span class="relative inline-block">
                        <span class="text-brand glow-text">yola çık</span><span class="text-brand">.</span>
                    </span>
                </h1>

                <p class="text-lg md:text-xl text-zinc-300 leading-relaxed mb-10 max-w-xl">
                    Şehir içi, havalimanı, iş toplantısı veya uzun mesafe — profesyonel şoför ve premium araç dakikalar içinde kapında. Şeffaf fiyat, pazarlık yok.
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
                    <div class="flex items-center gap-2"><span class="text-brand">✓</span> Şeffaf fiyat</div>
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
                                <div class="text-sm text-zinc-500">Şoför yolda</div>
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
                                <div class="text-xs text-zinc-500 mb-1">Net ücret</div>
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

    {{-- ============ LIVE RADAR ============ --}}
    <section id="canli-radar" class="relative px-6 pt-4 pb-20 md:pb-28">
        <div class="max-w-7xl mx-auto">

            {{-- Section heading --}}
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-8">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-brand mb-4">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                        Canlı Radar
                    </div>
                    <h2 class="display-font text-4xl md:text-5xl text-white mb-4">
                        Bölgendeki şoförler<br>
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

                    {{-- Bottom CTA --}}
                    <div class="absolute bottom-4 left-4 right-4 z-[400] flex items-center justify-between gap-3 pointer-events-none">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/70 border border-white/10 backdrop-blur-md text-xs text-zinc-300 pointer-events-auto">
                            <span class="text-brand">●</span> Senin konumun
                            <span class="text-zinc-600 mx-1">|</span>
                            <span class="inline-block w-2.5 h-2.5 rounded bg-brand"></span> Müsait şoför
                            <span class="text-zinc-600 mx-1">|</span>
                            <span class="inline-block w-2.5 h-2.5 rounded bg-zinc-600"></span> Yolculukta
                        </div>
                        <a href="{{ route('home') }}#rezervasyon" class="pointer-events-auto inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold text-sm transition shadow-lg shadow-brand/30">
                            Birini çağır
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </a>
                    </div>
                </div>

                {{-- Driver rail --}}
                <div class="lg:col-span-4 flex flex-col gap-3">
                    <div class="flex items-center justify-between px-1">
                        <div class="text-xs uppercase tracking-[0.25em] text-zinc-500">Yakındaki Şoförler</div>
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

    {{-- ============ QUICK SELECT MODAL ============ --}}
    <div id="quick-modal" class="fixed inset-0 z-[1000] hidden items-center justify-center px-4 py-6">
        <div id="quick-modal-backdrop" class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-md bg-zinc-950 border border-white/10 rounded-3xl shadow-2xl shadow-black/60 overflow-hidden max-h-[92vh] overflow-y-auto">
            {{-- Header --}}
            <div class="relative px-6 pt-6 pb-5 bg-gradient-to-br from-brand/15 via-brand/5 to-transparent border-b border-white/5">
                <button type="button" id="quick-modal-close" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 flex items-center justify-center text-zinc-400 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div class="text-xs uppercase tracking-[0.25em] text-brand mb-2">Hızlı Seç</div>
                <div class="flex items-center gap-3">
                    <div class="relative w-12 h-12 rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-900 border border-brand/40 flex items-center justify-center text-xl" id="qm-driver-icon">🚗</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="text-lg font-bold text-white truncate" id="qm-driver-name">—</div>
                            <span class="text-xs text-brand shrink-0" id="qm-driver-rating">★ —</span>
                        </div>
                        <div class="text-xs text-zinc-500 truncate" id="qm-driver-meta">—</div>
                    </div>
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

                {{-- Customer --}}
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

            {{-- Success state --}}
            <div id="quick-modal-success" class="hidden px-6 py-10 text-center">
                <div class="relative w-20 h-20 mx-auto mb-5">
                    <div class="absolute inset-0 rounded-full bg-emerald-500/15 hud-live-dot"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Talebin iletildi!</h3>
                <p class="text-sm text-zinc-400 leading-relaxed mb-6" id="qm-success-msg">—</p>
                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 text-left mb-6 space-y-2">
                    <div class="flex justify-between text-xs">
                        <span class="text-zinc-500">Onaylanan ücret</span>
                        <span class="text-brand font-bold" id="qm-success-fare">—</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-zinc-500">Sürücü</span>
                        <span class="text-white font-medium" id="qm-success-driver">—</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-zinc-500">Rezervasyon No</span>
                        <span class="text-white font-mono text-[11px]" id="qm-success-id">—</span>
                    </div>
                </div>
                <p class="text-[11px] text-zinc-500 mb-5">
                    Sürücüye bildirim gönderildi. Birkaç dakika içinde seni telefondan arayacaktır.
                </p>
                <button type="button" id="quick-modal-done" class="w-full px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-medium text-sm transition">
                    Tamam
                </button>
            </div>
        </div>
    </div>

    {{-- ============ MARQUEE STRIP ============ --}}
    <section class="relative py-6 border-y border-white/5 bg-black/40 backdrop-blur-sm marquee overflow-hidden">
        <div class="flex scroll-x whitespace-nowrap text-sm uppercase tracking-[0.3em] text-zinc-600">
            @for($i = 0; $i < 2; $i++)
                <div class="flex items-center gap-12 px-6">
                    <span>Havalimanı Transfer</span><span class="text-brand">·</span>
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
                <p class="text-lg text-zinc-400">Uygulamasız, üyeliksiz. Web'den rezervasyon, kapına şoför.</p>
            </div>

            <div class="relative">
                <div class="absolute top-8 left-12 right-12 h-px step-line hidden md:block"></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-4 relative">
                    @foreach([
                        ['01', 'Adresini yaz', 'Alış ve bırakış adresini gir, araç sınıfını seç. Fiyat anında ekranda.', '📍'],
                        ['02', 'Onayla', 'Telefon ve isim — kart bilgisi istemeyiz, ödeme yolculuk sonunda.', '✓'],
                        ['03', 'Yola çık', 'Şoför ve plaka SMS ile gelir. Kapına gelir, premium yolculuk başlar.', '🛣'],
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
                    Tek başına şehir içi mi, ailenle havalimanı transferi mi, ekiple iş seyahati mi — her senaryoya bir sınıf.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                @foreach([
                    ['Comfort', 'Şehir içi günlük', ['4 kişi', '2 bagaj', 'Sedan'], 'Skoda Superb · VW Passat seviyesi', '💼'],
                    ['Business', 'İş ve havalimanı', ['4 kişi', '3 bagaj', 'Lüks sedan'], 'Mercedes E-Class · BMW 5 seviyesi', '👔'],
                    ['VIP / Aile', 'Grup ve VIP transfer', ['6-7 kişi', '6 bagaj', 'Minivan'], 'Mercedes Vito · VW Caravelle seviyesi', '♛'],
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
                            Fiyat hesapla
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
                    Taksiden farkı:<br>
                    <span class="text-zinc-500">net,</span> güvenli, <span class="text-zinc-500">profesyonel.</span>
                </h2>
                <p class="text-lg text-zinc-400 leading-relaxed">
                    Pazarlık, taksimetre kuşkusu, "uzun yoldan" şüphesi yok. Adresini yazdığın an fiyatı görüyorsun — yolculuk bittiğinde ödüyorsun.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">

                {{-- Big card --}}
                <div class="bento-card md:col-span-2 md:row-span-2 rounded-3xl p-8 md:p-10 border border-white/5 relative overflow-hidden">
                    <div class="absolute top-8 right-8 text-7xl opacity-10">💎</div>
                    <div class="relative">
                        <div class="text-xs uppercase tracking-[0.2em] text-brand mb-4">01 · Şeffaf fiyat</div>
                        <h3 class="display-font text-3xl md:text-5xl text-white mb-4">Fiyat baştan belli, yolda değişmez</h3>
                        <p class="text-zinc-400 leading-relaxed mb-6 max-w-md">
                            Adresleri yazdığın an net ücreti görürsün. Trafik, ara durak veya saat geç oldu diye sürpriz ücret çıkmaz. Yazılan fiyat, ödenen fiyattır.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 rounded-full bg-brand/10 border border-brand/25 text-brand text-xs font-semibold">Sabit fiyat</span>
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
                    <p class="text-sm text-zinc-400 leading-relaxed">Havalimanı transferlerinde uçuş takibi yapılır. Şoför, sen indiğinde kapıda olur.</p>
                </div>

                {{-- Medium card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">💳</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">03 · Önden ödeme yok</div>
                    <h3 class="text-xl font-bold text-white mb-2">Yolculuk sonunda öde</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Kart bilgisi istemeyiz. Yolculuk bitince nakit, kart veya transferle ödersin.</p>
                </div>

                {{-- Wide card --}}
                <div class="bento-card md:col-span-2 rounded-3xl p-7 border border-white/5">
                    <div class="flex items-start gap-5">
                        <div class="w-14 h-14 rounded-2xl bg-brand/15 flex items-center justify-center text-2xl shrink-0">🛡</div>
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-brand mb-2">04 · Güvenlik</div>
                            <h3 class="text-xl font-bold text-white mb-2">Lisanslı şoför, sigortalı yolculuk</h3>
                            <p class="text-sm text-zinc-400 leading-relaxed">Her şoför kimlik doğrulamasından, sabıka kaydı kontrolünden ve yolcu memnuniyeti değerlendirmesinden geçer. Yolculuk koltuk sigortalıdır.</p>
                        </div>
                    </div>
                </div>

                {{-- Small card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">📱</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">05 · Üyelik yok</div>
                    <h3 class="text-xl font-bold text-white mb-2">İndirme yok, üyelik yok</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Web'den rezervasyon, SMS ile takip. Bir uygulama daha indirmek zorunda değilsin.</p>
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
                    ['✈️', 'Havalimanı', 'Uçuş takipli kapı önü transfer'],
                    ['💼', 'İş Toplantısı', 'Zamanında, temsil eden araç'],
                    ['🎉', 'Düğün & Gece', 'Özel etkinlik, VIP karşılama'],
                    ['🏙', 'Şehir İçi', 'Hızlı, güvenli, premium hat'],
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
                    ['Ferda Y.', 'İzmir', 'Sabah 6\'da havalimanına gittim, şoför 5\'te kapıdaydı. Araç temiz, su, şarj — her şey hazır. Bir daha taksi düşünmem.'],
                    ['Selim T.', 'Alsancak', 'Kurumsal misafirlerimizi karşıladık. Fiyat baştan belliydi, makbuz düzgün geldi. Muhasebe ile sorun çıkmadı.'],
                    ['Aylin K.', 'Karşıyaka', 'Düğün için tutmuştum. Şoför bey çok kibardı, fotoğraf çekme molasında bile bekledi. Çiçek çelenkli karşılama harikaydı.'],
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
                    ['Önceden ücret alıyor musunuz?', 'Hayır. Hiçbir aşamada kart bilgisi istemiyoruz. Ücret yolculuk bittiğinde nakit, kart veya banka transferi ile ödenir.'],
                    ['Fiyat yolda değişir mi?', 'Hayır. Trafik, alternatif rota, kısa ara duraklar dahil — yazılan fiyat ödenen fiyattır. Yolcunun talebiyle yeni durak eklenirse bunu önceden netleştiririz.'],
                    ['Şoför geç kalırsa?', 'Rezervasyon saatinden 5 dk geç kalan şoför için indirim uygulanır, 15 dk geç kalan rezervasyon için ücretsiz iptal/yenileme hakkın vardır.'],
                    ['Uçağım geç kalırsa ne olur?', 'Havalimanı transferlerinde uçuş takibi otomatik yapılır. Geç inseniz bile şoför kapıda olur, ek ücret çıkmaz.'],
                    ['Bagaj sınırı var mı?', 'Her araç sınıfının taşıyabileceği maksimum bagaj kapasitesi belirtilir. Fazla bagaj için araç sınıfını VIP/Aile olarak seçmen yeterli.'],
                    ['Çocuk koltuğu, evcil hayvan?', 'Bebek/çocuk koltuğu ve evcil hayvan opsiyonu rezervasyonda ekstra olarak işaretlenebilir — şoför hazır gelir.'],
                    ['İptal politikası nedir?', 'Yolculuk saatinden 1 saat öncesine kadar ücretsiz iptal. Daha sonra %50, yolculuk saatinde ise tam ücret tahsil edilir.'],
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
        const sorted = [...drivers]
            .map(d => ({ d, km: distanceKm(userCenter, [d.lat, d.lng]) }))
            .sort((a, b) => a.km - b.km);

        const top = sorted.slice(0, 5);
        railEl.innerHTML = top.map(({ d, km }) => {
            const mins = Math.max(1, Math.round(km * 2.4 + 0.8));
            const isBusy = d.state === 'busy';
            const dotColor = isBusy ? 'bg-zinc-500' : 'bg-brand';
            const statusText = isBusy ? 'Yolculukta' : 'Müsait';
            const statusClass = isBusy ? 'text-zinc-400' : 'text-brand';
            const selectBtn = isBusy
                ? `<div class="text-[10px] text-zinc-600 uppercase tracking-wider px-2.5 py-1.5 rounded-lg border border-white/5">Dolu</div>`
                : `<button type="button" class="quick-select-btn group/btn inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-brand hover:bg-brand-600 text-black text-[11px] font-bold uppercase tracking-wider transition shadow-md shadow-brand/30" data-driver-id="${d.id}">
                        Seç
                        <svg class="w-3 h-3 transition-transform group-hover/btn:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </button>`;
            return `
                <div class="driver-rail-card border border-white/5 rounded-2xl p-3.5 flex items-center gap-3" data-driver-id="${d.id}">
                    <div class="relative w-11 h-11 rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-900 border border-white/10 flex items-center justify-center text-lg shrink-0">
                        ${d.vIcon}
                        <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full ${dotColor} border-2 border-black"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="text-sm font-semibold text-white truncate">${d.name}</div>
                            <span class="text-[10px] text-brand shrink-0">★ ${d.rating}</span>
                        </div>
                        <div class="text-[11px] text-zinc-500 truncate">${d.vclass} · ${d.plate}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[11px] font-bold text-white">${km.toFixed(1)} km</span>
                            <span class="text-zinc-700">·</span>
                            <span class="text-[10px] ${statusClass} uppercase tracking-wider">${statusText} · ${mins} dk</span>
                        </div>
                    </div>
                    <div class="shrink-0">${selectBtn}</div>
                </div>`;
        }).join('');

        // Bind select buttons
        railEl.querySelectorAll('.quick-select-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.driverId, 10);
                const driver = drivers.find(x => x.id === id);
                if (driver) openQuickModal(driver);
            });
        });

        const availableCount = drivers.filter(d => d.state !== 'busy').length;
        const nearestAvailable = sorted.find(({ d }) => d.state !== 'busy');
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
    const modalEl = document.getElementById('quick-modal');
    const modalForm = document.getElementById('quick-modal-form');
    const modalSuccess = document.getElementById('quick-modal-success');
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

    let selectedDriver = null;
    let selectedDropoff = null; // { lat, lng, display_name }
    let dropoffSearchTimer = null;

    function openQuickModal(driver) {
        selectedDriver = driver;
        selectedDropoff = null;

        // Driver header
        document.getElementById('qm-driver-icon').textContent = driver.vIcon;
        document.getElementById('qm-driver-name').textContent = driver.name;
        document.getElementById('qm-driver-rating').textContent = `★ ${driver.rating}`;
        document.getElementById('qm-driver-meta').textContent = `${driver.vclass} · ${driver.plate}`;

        // Reset form
        modalForm.reset();
        modalForm.classList.remove('hidden');
        modalSuccess.classList.add('hidden');
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

        // Open
        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
        document.body.style.overflow = 'hidden';
        setTimeout(() => qmDropoffInput.focus(), 100);
    }

    function closeQuickModal() {
        modalEl.classList.add('hidden');
        modalEl.classList.remove('flex');
        document.body.style.overflow = '';
    }

    document.getElementById('quick-modal-close').addEventListener('click', closeQuickModal);
    document.getElementById('quick-modal-backdrop').addEventListener('click', closeQuickModal);
    document.getElementById('quick-modal-done').addEventListener('click', closeQuickModal);
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

    // Submit
    modalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        qmError.classList.add('hidden');

        if (!selectedDriver || !userCenterGlobal) {
            qmError.textContent = 'Konum veya sürücü bilgisi eksik.';
            qmError.classList.remove('hidden');
            return;
        }
        if (!selectedDropoff) {
            // Sadece text bırakış da kabul, ama mesafe hesaplayamayız → uyar
            qmError.textContent = 'Lütfen önerilerden bir bırakış noktası seç.';
            qmError.classList.remove('hidden');
            return;
        }

        const fd = new FormData(modalForm);
        const straight = distanceKm(userCenterGlobal, [selectedDropoff.lat, selectedDropoff.lng]);
        const km = straight * 1.2;
        const mins = Math.max(5, Math.round(km * 2.2 + 3));

        const payload = {
            vehicle_class_slug: selectedDriver.vSlug,
            pickup_address: qmPickupInput.value.trim() || `${userCenterGlobal[0].toFixed(5)}, ${userCenterGlobal[1].toFixed(5)}`,
            pickup_lat: userCenterGlobal[0],
            pickup_lng: userCenterGlobal[1],
            dropoff_address: selectedDropoff.display_name,
            dropoff_lat: selectedDropoff.lat,
            dropoff_lng: selectedDropoff.lng,
            customer_name: fd.get('customer_name'),
            customer_phone: fd.get('customer_phone'),
            preferred_driver_name: selectedDriver.name,
            preferred_driver_plate: selectedDriver.plate,
            distance_km: parseFloat(km.toFixed(2)),
            duration_minutes: mins,
            kvkk_consent: fd.get('kvkk_consent') ? 1 : 0,
        };

        qmSubmit.disabled = true;
        qmSubmitText.textContent = 'Gönderiliyor…';
        qmSubmitSpinner.classList.remove('hidden');

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch('{{ route('reservation.quick-request') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                const firstErr = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Talep gönderilemedi.');
                throw new Error(firstErr);
            }

            // Success
            modalForm.classList.add('hidden');
            modalSuccess.classList.remove('hidden');
            document.getElementById('qm-success-msg').textContent = data.message;
            document.getElementById('qm-success-fare').textContent = `₺${Math.round(data.total_fare)}`;
            document.getElementById('qm-success-driver').textContent = data.driver_name;
            document.getElementById('qm-success-id').textContent = data.public_id.slice(-12).toUpperCase();
        } catch (err) {
            qmError.textContent = err.message;
            qmError.classList.remove('hidden');
        } finally {
            qmSubmit.disabled = false;
            qmSubmitText.textContent = 'Talebi Gönder';
            qmSubmitSpinner.classList.add('hidden');
        }
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
})();
</script>
@endpush
