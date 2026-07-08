@extends('layouts.public')

@section('title', 'Üye Sürücü Olun · FerXGo · Kendi Yolculuğunun Sahibi Ol')
@section('description', 'FerXGo paylaşımlı yolculuk platformuna üye sürücü olarak katıl. Esnek saatler, katkı payının tamamı senin, üyelik tabanlı şeffaf model. İzmir genelinde üye sürücü kayıtları açık.')

@push('head')
<style>
    .ferogo-mesh {
        background:
            radial-gradient(circle at 15% 20%, rgba(240,192,64,0.22) 0%, transparent 35%),
            radial-gradient(circle at 85% 10%, rgba(240,192,64,0.10) 0%, transparent 40%),
            radial-gradient(circle at 50% 90%, rgba(240,192,64,0.14) 0%, transparent 45%),
            #0a0a0a;
    }
    .ferogo-noise {
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='240' height='240' viewBox='0 0 240 240'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/><feColorMatrix values='0 0 0 0 0.94  0 0 0 0 0.75  0 0 0 0 0.25  0 0 0 0.045 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
    }
    @keyframes ticker {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
    }
    @keyframes drift-1 {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(30px, -20px) rotate(2deg); }
    }
    @keyframes drift-2 {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(-25px, 25px) rotate(-3deg); }
    }
    .drift-1 { animation: drift-1 12s ease-in-out infinite; }
    .drift-2 { animation: drift-2 14s ease-in-out infinite; }
    .ticker { animation: ticker 2.5s ease-in-out infinite; }
    .display-font {
        font-weight: 900;
        letter-spacing: -0.04em;
        line-height: 0.92;
    }
    .glow-text {
        text-shadow: 0 0 60px rgba(240,192,64,0.45);
    }
    .stat-card {
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
    .form-input {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        transition: all 0.2s;
    }
    .form-input:focus {
        outline: none;
        border-color: #F0C040;
        background: rgba(240,192,64,0.04);
        box-shadow: 0 0 0 4px rgba(240,192,64,0.08);
    }
    .check-pill input:checked + div {
        background: rgba(240,192,64,0.12);
        border-color: #F0C040;
        color: #FDF0C1;
    }
    .marquee {
        mask-image: linear-gradient(90deg, transparent, black 10%, black 90%, transparent);
        -webkit-mask-image: linear-gradient(90deg, transparent, black 10%, black 90%, transparent);
    }
    @keyframes scroll-x {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    .scroll-x { animation: scroll-x 35s linear infinite; }
</style>
@endpush

@section('content')
<div class="ferogo-mesh pt-24 relative overflow-hidden">

    {{-- Noise overlay --}}
    <div class="absolute inset-0 ferogo-noise opacity-[0.35] pointer-events-none mix-blend-overlay"></div>

    {{-- Floating background shapes --}}
    <div class="drift-1 absolute top-32 -left-32 w-[28rem] h-[28rem] rounded-full bg-brand/10 blur-[120px] pointer-events-none"></div>
    <div class="drift-2 absolute top-[40rem] -right-32 w-[32rem] h-[32rem] rounded-full bg-brand/15 blur-[140px] pointer-events-none"></div>

    {{-- ============ APPLICATION FORM (en üstte) ============ --}}
    <section id="basvuru" class="relative px-6 pt-8 md:pt-12 pb-16">
        <div class="max-w-3xl mx-auto">

            <div class="text-center mb-8 md:mb-10">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-3">Başvuru</div>
                <h1 class="display-font text-4xl md:text-6xl text-white mb-4">2 dakika. Aynı gün cevap.</h1>
                <p class="text-base md:text-lg text-zinc-400 max-w-xl mx-auto">
                    Aşağıdaki formu doldur, ekip içinden bir kişi 24 saat içinde seninle iletişime geçsin.
                </p>
            </div>

            {{-- Reklam alanı: Sürücü başvuru üstü --}}
            @include('partials.ad-slot', ['placement' => 'driver_apply', 'class' => 'mb-8'])

            @if(session('application_success'))
                <div class="mb-8 p-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">✓</div>
                    <div>
                        <div class="text-emerald-200 font-semibold mb-1">Başvurun alındı.</div>
                        <div class="text-sm text-emerald-100/80">24 saat içinde telefonla seni arayacağız. Hazırda dur.</div>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-8 p-5 rounded-2xl bg-red-500/10 border border-red-500/30">
                    <div class="text-red-200 font-semibold mb-2">Lütfen şunları düzelt:</div>
                    <ul class="list-disc list-inside text-sm text-red-100/80 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('driver.apply.store') }}" class="bg-zinc-950/60 backdrop-blur-xl border border-white/10 rounded-3xl p-6 md:p-10 space-y-8">
                @csrf

                {{-- Section: kişisel --}}
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">Kişisel</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Ad Soyad</label>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" required maxlength="120" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="Mehmet Yılmaz">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Telefon</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="32" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="0532 000 00 00">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">E-posta <span class="text-zinc-600">(opsiyonel)</span></label>
                            <input type="email" name="email" value="{{ old('email') }}" maxlength="255" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="mehmet@ornek.com">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Şehir</label>
                            <select name="city_id" class="form-input w-full rounded-xl px-4 py-3 text-white">
                                <option value="">Seçiniz</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Doğum yılı</label>
                            <input type="number" name="birth_year" value="{{ old('birth_year') }}" min="1940" max="{{ date('Y') - 18 }}" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="1990">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Cinsiyet</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="check-pill cursor-pointer">
                                    <input type="radio" name="gender" value="male" class="sr-only peer" {{ old('gender') === 'male' ? 'checked' : '' }} required>
                                    <div class="flex items-center justify-center gap-2 p-4 rounded-xl bg-white/[0.02] border border-white/10 text-zinc-300 transition">
                                        <span class="text-lg">👨</span>
                                        <span class="text-sm font-medium">Erkek</span>
                                    </div>
                                </label>
                                <label class="check-pill cursor-pointer">
                                    <input type="radio" name="gender" value="female" class="sr-only peer" {{ old('gender') === 'female' ? 'checked' : '' }} required>
                                    <div class="flex items-center justify-center gap-2 p-4 rounded-xl bg-white/[0.02] border border-white/10 text-zinc-300 transition">
                                        <span class="text-lg">👩</span>
                                        <span class="text-sm font-medium">Kadın</span>
                                    </div>
                                </label>
                            </div>
                            <p class="text-[11px] text-zinc-500 mt-2">Kadın sürücülerimize "sadece kadın yolcu" opsiyonu sunulur.</p>
                        </div>
                    </div>
                </div>

                {{-- Section: profesyonel --}}
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">Sürüş profili</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Ehliyet sınıfı</label>
                            <select name="license_class" class="form-input w-full rounded-xl px-4 py-3 text-white">
                                @foreach(['B' => 'B', 'D1' => 'D1', 'D' => 'D', 'E' => 'E'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('license_class', 'B') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Deneyim</label>
                            <select name="experience_band" class="form-input w-full rounded-xl px-4 py-3 text-white">
                                @foreach(['under_1' => '1 yıldan az', '1_to_3' => '1-3 yıl', '3_to_5' => '3-5 yıl', '5_plus' => '5 yıl ve üzeri'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('experience_band', '1_to_3') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="check-pill cursor-pointer block max-w-sm">
                            <input type="checkbox" name="has_src" value="1" class="sr-only peer" {{ old('has_src') ? 'checked' : '' }}>
                            <div class="flex items-center gap-3 p-4 rounded-xl bg-white/[0.02] border border-white/10 text-zinc-300 transition">
                                <div class="w-5 h-5 rounded-md border-2 border-current flex items-center justify-center text-xs">✓</div>
                                <span class="text-sm font-medium">SRC-2 belgem var</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-zinc-400 mb-2">Aracın <span class="text-zinc-600">(marka, model, yıl)</span></label>
                        <input type="text" name="vehicle_info" value="{{ old('vehicle_info') }}" required maxlength="255" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="Mercedes Vito 2021">
                        <p class="text-xs text-zinc-500 mt-2">Bakımlı, sigara içilmeyen, son 7 yıl içinde üretilmiş araç.</p>
                    </div>
                </div>

                {{-- Section: not --}}
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">Eklemek istediğin</div>
                    <textarea name="notes" rows="4" maxlength="1000" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600 resize-none" placeholder="Bizimle paylaşmak istediğin bir şey varsa...">{{ old('notes') }}</textarea>
                </div>

                {{-- Vergi Sorumluluğu Bilgilendirme --}}
                <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 md:p-5">
                    <div class="flex items-start gap-3">
                        <div class="text-xl shrink-0">📋</div>
                        <div class="text-xs md:text-sm text-zinc-300 leading-relaxed">
                            <div class="font-semibold text-amber-200 mb-1.5">Vergi Sorumluluğu Bilgilendirmesi</div>
                            <p>
                                FerXGo bir <strong>paylaşımlı yolculuk platformudur</strong>; ticari taşımacılık yapmaz, üye sürücülerin işvereni değildir.
                                Gelir İdaresi Başkanlığı'nın 7 Ağustos 2024 tarihli kararı uyarınca paylaşımlı yolculuk faaliyetinden elde edilen kazanç
                                <strong>üye sürücünün ticari kazancıdır</strong> ve vergi yükümlülüğü tamamen üye sürücüye aittir.
                                FerXGo, başvurunuz kabul edildiğinde anlaşmalı mali müşavirlik desteğiyle basit usul vergi kaydı kurulumuna yardımcı olabilir.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Onaylar --}}
                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="kvkk" value="1" required class="mt-1 w-5 h-5 rounded border-white/20 bg-white/5 text-brand focus:ring-brand focus:ring-offset-0">
                        <span class="text-sm text-zinc-400 leading-relaxed">
                            Kişisel verilerimin başvuru değerlendirme amacıyla işlenmesini ve benimle iletişim kurulmasını kabul ediyorum.
                            <a href="{{ route('legal.kvkk') }}" target="_blank" class="text-brand hover:underline">KVKK Aydınlatma Metni</a>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="terms" value="1" required class="mt-1 w-5 h-5 rounded border-white/20 bg-white/5 text-brand focus:ring-brand focus:ring-offset-0">
                        <span class="text-sm text-zinc-400 leading-relaxed">
                            <a href="{{ route('legal.terms') }}" target="_blank" class="text-brand hover:underline">Hizmet Şartları</a> ve
                            <a href="{{ route('legal.ride-sharing') }}" target="_blank" class="text-brand hover:underline">Paylaşımlı Yolculuk modelini</a>
                            okudum, anladım. FerXGo'nun aracı hizmet sağlayıcı olduğunu, vergi sorumluluğunun bana ait olduğunu kabul ediyorum.
                        </span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="group w-full inline-flex items-center justify-center gap-2 px-8 py-5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-lg transition-all shadow-2xl shadow-brand/30 hover:shadow-brand/50">
                    Başvurumu Gönder
                    <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </button>

                <p class="text-xs text-zinc-500 text-center">
                    Telefonla görüşmek istersen → <a href="tel:+908503403039" class="text-brand hover:underline font-semibold">0850 340 3039</a>
                </p>
            </form>

            {{-- Reklam alanı: Sürücü Olun — Alt (formun altı, ayrı yönetilir) --}}
            @include('partials.ad-slot', ['placement' => 'driver_apply_bottom', 'class' => 'mt-8'])

            <div class="mt-6 text-center">
                <a href="#neden-ferxgo" class="inline-flex items-center gap-2 text-xs text-zinc-500 hover:text-brand transition">
                    Ne kazanacağını öğren
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- ============ HERO ============ --}}
    <section id="neden-ferxgo" class="relative px-6 pt-12 md:pt-20 pb-24" style="scroll-margin-top: 80px;">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

            {{-- Left: copy --}}
            <div class="lg:col-span-7">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-medium text-zinc-300 mb-8 backdrop-blur-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Şu an İzmir'de <span class="text-white font-semibold">37 sürücü</span> alımı yapılıyor
                </div>

                <h1 class="display-font text-5xl sm:text-6xl md:text-7xl lg:text-8xl text-white mb-8">
                    Direksiyona<br>
                    geç,<br>
                    <span class="relative inline-block">
                        <span class="text-brand glow-text">kazan</span><span class="text-brand">.</span>
                    </span>
                </h1>

                <p class="text-lg md:text-xl text-zinc-300 leading-relaxed mb-10 max-w-xl">
                    İzmir'in en hızlı büyüyen paylaşımlı yolculuk platformuna üye sürücü olarak katıl. Esnek saatler, şeffaf katkı payı, üyelik tabanlı model — kendi yolculuğunun sahibi sen ol.
                </p>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="#basvuru" class="group inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-base transition-all shadow-2xl shadow-brand/30 hover:shadow-brand/50 hover:scale-[1.02]">
                        Hemen Başvur
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                    <a href="#nasil-olur" class="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-medium text-base transition backdrop-blur-sm">
                        Nasıl olur?
                    </a>
                </div>

                {{-- Inline mini-trust --}}
                <div class="mt-10 flex items-center gap-4">
                    <div class="flex -space-x-2">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-zinc-700 to-zinc-900 border-2 border-black flex items-center justify-center text-xs font-bold text-zinc-400">MK</div>
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-zinc-700 to-zinc-900 border-2 border-black flex items-center justify-center text-xs font-bold text-zinc-400">SY</div>
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-zinc-700 to-zinc-900 border-2 border-black flex items-center justify-center text-xs font-bold text-zinc-400">AT</div>
                        <div class="w-9 h-9 rounded-full bg-brand border-2 border-black flex items-center justify-center text-xs font-extrabold text-black">+200</div>
                    </div>
                    <div class="text-sm text-zinc-400">
                        <span class="text-white font-semibold">200+ sürücü</span> bu hafta yola çıktı
                    </div>
                </div>
            </div>

            {{-- Right: floating earnings card --}}
            <div class="lg:col-span-5 relative">
                <div class="relative">
                    {{-- Decorative orb behind card --}}
                    <div class="absolute -inset-6 bg-brand/20 blur-3xl rounded-full"></div>

                    {{-- Main earnings card --}}
                    <div class="relative stat-card border border-white/10 rounded-3xl p-7 md:p-9 shadow-2xl shadow-black/40">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <div class="text-xs uppercase tracking-widest text-zinc-400 mb-2">Bu hafta</div>
                                <div class="text-sm text-zinc-500">Ortalama sürücü kazancı</div>
                            </div>
                            <div class="px-2 py-1 rounded-md bg-emerald-500/15 text-emerald-300 text-xs font-bold ticker">↑ %18</div>
                        </div>

                        <div class="display-font text-6xl md:text-7xl text-white mb-1 tabular-nums">
                            ₺<span id="counter-earnings">7.420</span>
                        </div>
                        <div class="text-sm text-zinc-500 mb-6">42 saat aktif çalışma · İzmir</div>

                        {{-- Mini chart bars --}}
                        <div class="flex items-end justify-between gap-1.5 h-20 mb-6">
                            @foreach([42, 58, 35, 71, 49, 88, 95] as $i => $h)
                                <div class="flex-1 rounded-t-md {{ $i === 6 ? 'bg-brand' : 'bg-zinc-700' }}" style="height: {{ $h }}%"></div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-5 border-t border-white/5">
                            <div>
                                <div class="text-xs text-zinc-500 mb-1">Sürücü payı</div>
                                <div class="text-2xl font-bold text-brand">%100</div>
                            </div>
                            <div>
                                <div class="text-xs text-zinc-500 mb-1">Ödeme</div>
                                <div class="text-2xl font-bold text-white">Haftalık</div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating mini badge --}}
                    <div class="absolute -bottom-4 -left-4 bg-black border border-white/10 rounded-2xl px-4 py-3 shadow-2xl">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-full bg-brand/20 flex items-center justify-center text-brand">★</div>
                            <div>
                                <div class="text-xs text-zinc-500">Memnuniyet</div>
                                <div class="text-base font-bold text-white">4.9/5</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ MARQUEE BRAND STRIP ============ --}}
    <section class="relative py-6 border-y border-white/5 bg-black/40 backdrop-blur-sm marquee overflow-hidden">
        <div class="flex scroll-x whitespace-nowrap text-sm uppercase tracking-[0.3em] text-zinc-600">
            @for($i = 0; $i < 2; $i++)
                <div class="flex items-center gap-12 px-6">
                    <span>Mercedes Vito</span><span class="text-brand">·</span>
                    <span>BMW 5 Series</span><span class="text-brand">·</span>
                    <span>Audi A6</span><span class="text-brand">·</span>
                    <span>Mercedes E-Class</span><span class="text-brand">·</span>
                    <span>VW Caravelle</span><span class="text-brand">·</span>
                    <span>Mercedes S-Class</span><span class="text-brand">·</span>
                    <span>Range Rover</span><span class="text-brand">·</span>
                </div>
            @endfor
        </div>
    </section>

    {{-- ============ STATS ============ --}}
    <section class="relative px-6 py-20 md:py-28">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-white/5 rounded-3xl overflow-hidden border border-white/5">
                @foreach([
                    ['200+', 'Aktif sürücü'],
                    ['₺28K', 'Aylık ortalama kazanç'],
                    ['%100', 'Sürücü payı'],
                    ['4.9', 'Sürücü memnuniyeti'],
                ] as $stat)
                    <div class="bg-black p-8 md:p-10 group hover:bg-zinc-950 transition">
                        <div class="display-font text-5xl md:text-6xl text-white mb-3 group-hover:text-brand transition">{{ $stat[0] }}</div>
                        <div class="text-sm text-zinc-500 uppercase tracking-wider">{{ $stat[1] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ BENTO BENEFITS ============ --}}
    <section class="relative px-6 py-20">
        <div class="max-w-7xl mx-auto">
            <div class="max-w-3xl mb-16">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Neden FerXGo</div>
                <h2 class="display-font text-4xl md:text-6xl text-white mb-6">
                    Üç şey net:<br>
                    <span class="text-zinc-500">kazanç,</span> esneklik, <span class="text-zinc-500">saygı.</span>
                </h2>
                <p class="text-lg text-zinc-400 leading-relaxed">
                    Sürücü olmak bir mecburiyet değil, bir tercih olmalı. Biz koşulları öyle kuruyoruz ki sen sadece direksiyona ve yolcuya odaklan.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">

                {{-- Big card --}}
                <div class="bento-card md:col-span-2 md:row-span-2 rounded-3xl p-8 md:p-10 border border-white/5 relative overflow-hidden">
                    <div class="absolute top-8 right-8 text-7xl opacity-10">💰</div>
                    <div class="relative">
                        <div class="text-xs uppercase tracking-[0.2em] text-brand mb-4">01 · Kazanç</div>
                        <h3 class="display-font text-3xl md:text-5xl text-white mb-4">Sektörün en yüksek sürücü payı</h3>
                        <p class="text-zinc-400 leading-relaxed mb-6 max-w-md">
                            Yolcunun ödediği katkı payının <strong>tamamı senin</strong>. FerXGo komisyon almaz; sabit dönemsel üyelik bedeli ile platforma erişirsin. Bahşişler tamamen sana aittir.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 rounded-full bg-brand/10 border border-brand/25 text-brand text-xs font-semibold">%100 pay</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300 text-xs font-semibold">Tip senin</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300 text-xs font-semibold">Şeffaf bordro</span>
                        </div>
                    </div>
                </div>

                {{-- Medium card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">⏱</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">02 · Esneklik</div>
                    <h3 class="text-xl font-bold text-white mb-2">İstediğin saatte çevrimiçi ol</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Vardiya yok, hedef yok. Online'a geç, yolculuğu al, kapat. Sen yönet.</p>
                </div>

                {{-- Medium card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">⚡</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">03 · Hızlı ödeme</div>
                    <h3 class="text-xl font-bold text-white mb-2">Her Cuma hesabında</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Haftalık otomatik ödeme. Acil ihtiyaçta anlık çekim de mümkün.</p>
                </div>

                {{-- Wide card --}}
                <div class="bento-card md:col-span-2 rounded-3xl p-7 border border-white/5">
                    <div class="flex items-start gap-5">
                        <div class="w-14 h-14 rounded-2xl bg-brand/15 flex items-center justify-center text-2xl shrink-0">🛡</div>
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-brand mb-2">04 · Güvence</div>
                            <h3 class="text-xl font-bold text-white mb-2">Sigorta, hukuki destek, 7/24 operasyon</h3>
                            <p class="text-sm text-zinc-400 leading-relaxed">Yolcu uyuşmazlığına, teknik soruna karşı arkanda deneyimli destek ekibi var. Yalnız değilsin.</p>
                        </div>
                    </div>
                </div>

                {{-- Small card --}}
                <div class="bento-card rounded-3xl p-7 border border-white/5">
                    <div class="text-3xl mb-4">👔</div>
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">05 · Yolcu</div>
                    <h3 class="text-xl font-bold text-white mb-2">Doğrulanmış yolcular</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Kurumsal, havalimanı, VIP. Pazarlık yok, katkı payı net, yolcu kibar.</p>
                </div>

                {{-- Wide card --}}
                <div class="bento-card md:col-span-2 rounded-3xl p-7 border border-white/5 relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 text-9xl opacity-5">🚀</div>
                    <div class="relative">
                        <div class="text-xs uppercase tracking-[0.2em] text-brand mb-2">06 · Onboarding</div>
                        <h3 class="text-xl font-bold text-white mb-2">48 saatte yolda ol</h3>
                        <p class="text-sm text-zinc-400 leading-relaxed max-w-md">Başvurudan ilk yolculuğa ortalama 2 gün. Belge yükleme, kısa eğitim, plaka eşleme — hepsi tek panelden.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HOW IT WORKS ============ --}}
    <section id="nasil-olur" class="relative px-6 py-20 md:py-28">
        <div class="max-w-6xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Süreç</div>
                <h2 class="display-font text-4xl md:text-6xl text-white mb-5">Dört adım, iki gün.</h2>
                <p class="text-lg text-zinc-400">Basit, hızlı, şeffaf — bürokrasi yok, takip senin elinde.</p>
            </div>

            {{-- Horizontal step flow --}}
            <div class="relative">
                <div class="absolute top-8 left-12 right-12 h-px step-line hidden md:block"></div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 md:gap-4 relative">
                    @foreach([
                        ['01', 'Başvur', 'Aşağıdaki formu doldur — 2 dakika sürer.', '📝'],
                        ['02', 'Belge yükle', 'Ehliyet, SRC, ruhsat. Hepsi panelden.', '📋'],
                        ['03', 'Aracını tanıt', 'Kendi konforlu aracını sisteme ekle, fotoğraf yükle.', '🚗'],
                        ['04', 'Yola çık', 'Onayını al, çevrimiçi ol, ilk yolculuğu kabul et.', '🛣'],
                    ] as $step)
                        <div class="relative text-center">
                            <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-full bg-black border-2 border-brand text-2xl mb-5 mx-auto">
                                {{ $step[3] }}
                            </div>
                            <div class="text-xs font-mono text-brand mb-2">{{ $step[0] }}</div>
                            <h3 class="text-xl font-bold text-white mb-2">{{ $step[1] }}</h3>
                            <p class="text-sm text-zinc-400 leading-relaxed max-w-[200px] mx-auto">{{ $step[2] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ REQUIREMENTS ============ --}}
    <section class="relative px-6 py-20">
        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
                <div>
                    <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Gereksinimler</div>
                    <h2 class="display-font text-4xl md:text-5xl text-white mb-5">Sende olmalı.</h2>
                    <p class="text-zinc-400 leading-relaxed">
                        Paylaşımlı yolculuk, doğrulanmış üye sürücüyle başlar. Aşağıdaki maddeler senin için zaten varsa, başvurun anında işleme alınır.
                    </p>
                </div>

                <ul class="space-y-3">
                    @foreach([
                        'Adına / kullanımında bakımlı bir araç (son 7 yıl)',
                        'En az 22 yaşında olmak',
                        'B sınıfı ehliyet (büyük araç için D / D1)',
                        'Geçerli SRC-2 belgesi (yoksa biz yönlendiririz)',
                        'Sabıka kaydı temiz olmak',
                        'En az 2 yıl sürüş deneyimi',
                        'Sigara içilmeyen, bakımlı araç',
                        'Akıllı telefon (Android 9+ / iOS 14+)',
                    ] as $req)
                        <li class="flex items-start gap-3 p-4 rounded-2xl bg-white/[0.02] border border-white/5 hover:border-brand/30 transition">
                            <div class="w-6 h-6 rounded-full bg-brand/15 flex items-center justify-center text-brand text-sm shrink-0 mt-0.5">✓</div>
                            <span class="text-zinc-200">{{ $req }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
    {{-- ============ FINAL CTA STRIP ============ --}}
    <section class="relative px-4 sm:px-6 py-12 sm:py-16">
        <div class="max-w-5xl mx-auto">
            <div class="relative rounded-2xl sm:rounded-3xl bg-gradient-to-br from-brand/20 via-brand/5 to-transparent border border-brand/20 p-6 sm:p-8 md:p-12 overflow-hidden">
                <div class="absolute -right-12 -top-12 w-64 h-64 bg-brand/20 blur-3xl rounded-full pointer-events-none"></div>
                <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div>
                        <h3 class="display-font text-2xl sm:text-3xl md:text-4xl text-white mb-1.5 sm:mb-2">Sorun mu var?</h3>
                        <p class="text-sm sm:text-base text-zinc-300">WhatsApp veya telefonla 7/24 erişebilirsin.</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3 w-full md:w-auto md:shrink-0">
                        <a href="https://wa.me/908503403039"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold transition shadow-lg shadow-emerald-500/20 whitespace-nowrap">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            WhatsApp
                        </a>
                        <a href="tel:+908503403039"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-white hover:bg-zinc-100 text-zinc-900 text-sm font-semibold transition shadow-lg shadow-white/10 whitespace-nowrap">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                            0850 340 3039
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
    // Simple counter animation for hero earnings
    (function() {
        const el = document.getElementById('counter-earnings');
        if (!el) return;
        const target = 7420;
        const duration = 1800;
        const start = performance.now();
        function step(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const value = Math.floor(target * eased);
            el.textContent = value.toLocaleString('tr-TR');
            if (t < 1) requestAnimationFrame(step);
        }
        // start when in viewport
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    requestAnimationFrame(step);
                    io.disconnect();
                }
            });
        }, { threshold: 0.3 });
        io.observe(el);
    })();
</script>
@endpush
