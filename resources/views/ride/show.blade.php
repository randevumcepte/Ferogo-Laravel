@extends('layouts.public')

@section('title', 'Yolculuk Yapın · Ferogo · Premium Şoförlü Transfer')
@section('description', 'Şehir içi, havalimanı veya uzun mesafe — profesyonel şoför, lüks araç, şeffaf fiyat. 60 saniyede rezervasyon yap, kapına gelsin.')

@push('head')
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
                    ['Ferda Y.', 'İzmir', 'Sabah 6'da havalimanına gittim, şoför 5'te kapıdaydı. Araç temiz, su, şarj — her şey hazır. Bir daha taksi düşünmem.'],
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
