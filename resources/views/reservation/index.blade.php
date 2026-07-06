@extends('layouts.public')

@section('title', 'Ferxgo · İzmir Paylaşımlı Yolculuk Platformu')
@section('description', 'İzmir\'de bağımsız üye sürücüler ile yolcuları buluşturan paylaşımlı yolculuk platformu. Şeffaf katkı payı, 7/24 platform erişimi.')

@push('head')
<style>
    @keyframes float-orb {
        0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.5; }
        50% { transform: translate(20px, -30px) scale(1.1); opacity: 0.7; }
    }
    @keyframes float-orb-2 {
        0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.4; }
        50% { transform: translate(-30px, 20px) scale(1.15); opacity: 0.6; }
    }
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    .orb-1 { animation: float-orb 8s ease-in-out infinite; }
    .orb-2 { animation: float-orb-2 10s ease-in-out infinite; }
    .hero-grid {
        background-image:
            linear-gradient(rgba(240,192,64,0.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(240,192,64,0.06) 1px, transparent 1px);
        background-size: 60px 60px;
        mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
        -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
    }
    .hero-bg-base {
        background:
            radial-gradient(ellipse 80% 60% at 50% 0%, rgba(240,192,64,0.18) 0%, transparent 60%),
            radial-gradient(ellipse 60% 80% at 80% 100%, rgba(240,192,64,0.10) 0%, transparent 55%),
            linear-gradient(180deg, #0a0a0a 0%, #000 100%);
    }
    .hero-bg-image {
        background-image:
            linear-gradient(180deg, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.75) 60%, rgba(0,0,0,1) 100%),
            url('{{ asset('images/hero-bg.jpg') }}');
        background-size: cover;
        background-position: center;
    }
    .text-shimmer {
        background: linear-gradient(90deg, #F0C040 0%, #FDF0C1 50%, #F0C040 100%);
        background-size: 200% auto;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: shimmer 4s linear infinite;
    }
    .extras-accordion > summary { list-style: none; }
    .extras-accordion > summary::-webkit-details-marker { display: none; }

    /* iOS Safari datetime-local & number input normalize: diğer text input'larla aynı görünüm */
    input[type="datetime-local"],
    input[type="number"],
    input[type="text"],
    input[type="tel"] {
        -webkit-appearance: none;
        appearance: none;
        box-sizing: border-box;
        font-family: inherit;
        font-size: 16px; /* iOS zoom-on-focus engeli */
        line-height: 1.5;
        min-height: 48px;
    }
    input[type="datetime-local"] {
        display: block;
        width: 100%;
        text-align: left;
    }
    /* iOS datetime-local içindeki placeholder/değer alanını sıfırla */
    input[type="datetime-local"]::-webkit-date-and-time-value {
        text-align: left;
        min-height: 1.5em;
    }
    /* number input spinner kaldır (kompakt görünüm) */
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type="number"] { -moz-appearance: textfield; }
</style>
@endpush

@section('content')
<div class="gradient-radial pt-24">

    {{-- Hero --}}
    <section class="relative overflow-hidden -mt-24 pt-24">
        {{-- Background base (CSS-only premium look) + optional image layer --}}
        <div class="absolute inset-0 hero-bg-base"></div>
        <div class="absolute inset-0 hero-bg-image opacity-0 transition-opacity duration-700" id="hero-bg-img"></div>

        {{-- Animated glow orbs --}}
        <div class="orb-1 absolute top-10 left-1/4 w-[28rem] h-[28rem] rounded-full bg-brand/25 blur-3xl pointer-events-none"></div>
        <div class="orb-2 absolute bottom-0 right-1/4 w-[24rem] h-[24rem] rounded-full bg-brand/15 blur-3xl pointer-events-none"></div>

        {{-- Subtle grid --}}
        <div class="absolute inset-0 hero-grid pointer-events-none"></div>

        {{-- Top vignette for nav blend --}}
        <div class="absolute top-0 inset-x-0 h-32 bg-gradient-to-b from-black to-transparent pointer-events-none"></div>

        {{-- Content --}}
        <div class="relative px-6 pt-6 pb-24 sm:pt-10 sm:pb-28 md:pt-16 md:pb-36">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand/10 border border-brand/25 text-brand text-xs font-semibold uppercase tracking-wider mb-6 backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></span>
                    İzmir · 7/24 Paylaşımlı Yolculuk
                </div>
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold leading-tight mb-6 tracking-tight drop-shadow-2xl">
                    <span class="text-shimmer">Paylaşımlı</span> yolculuğun<br>en kolay yolu
                </h1>
                <p class="text-lg md:text-xl text-zinc-200 max-w-2xl mx-auto leading-relaxed mb-10 drop-shadow-lg">
                    Bağımsız üye sürücüler, bakımlı araçlar, şeffaf katkı payı.<br>
                    Havalimanı, iş toplantısı, şehir içi — kapı önünde eşleştirme.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 items-center justify-center">
                    <a href="#rezervasyon" class="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-brand hover:bg-brand-600 text-black font-bold text-lg transition shadow-lg shadow-brand/30 hover:shadow-brand/50 hover:scale-105 duration-200">
                        Hemen Rezervasyon Yap
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                    <a href="{{ route('ride.show') }}" class="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-white/5 hover:bg-white/10 border border-white/15 text-white font-semibold text-base transition backdrop-blur-sm">
                        <svg class="w-5 h-5 text-brand" fill="currentColor" viewBox="0 0 24 24"><path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11m-14 0h14m-14 0a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h1v1a1 1 0 0 0 2 0v-1h8v1a1 1 0 0 0 2 0v-1h1a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1M7 14h.01M17 14h.01"/></svg>
                        Yolculuk Yap
                    </a>
                </div>

                {{-- Live stat counters --}}
                <div id="hero-stats" class="mt-10 sm:mt-14 flex flex-wrap items-stretch justify-center gap-3 sm:gap-5">
                    <div class="group flex flex-col items-center justify-center w-[150px] sm:w-[200px] rounded-2xl bg-gradient-to-b from-white/[0.07] to-white/[0.02] border border-white/10 hover:border-brand/40 px-5 py-5 sm:px-8 sm:py-7 backdrop-blur-sm transition-colors duration-300 shadow-lg shadow-black/20">
                        <div class="flex items-baseline whitespace-nowrap text-4xl sm:text-5xl font-extrabold text-brand tracking-tight tabular-nums leading-none drop-shadow-[0_2px_10px_rgba(212,175,55,0.25)]">
                            <span class="js-counter" data-target="23534">0</span><span class="text-brand/70 ml-0.5">+</span>
                        </div>
                        <div class="mt-2.5 text-[11px] sm:text-xs text-zinc-400 uppercase tracking-[0.2em] font-medium">Yolcu</div>
                    </div>
                    <div class="group flex flex-col items-center justify-center w-[150px] sm:w-[200px] rounded-2xl bg-gradient-to-b from-white/[0.07] to-white/[0.02] border border-white/10 hover:border-brand/40 px-5 py-5 sm:px-8 sm:py-7 backdrop-blur-sm transition-colors duration-300 shadow-lg shadow-black/20">
                        <div class="flex items-baseline whitespace-nowrap text-4xl sm:text-5xl font-extrabold text-brand tracking-tight tabular-nums leading-none drop-shadow-[0_2px_10px_rgba(212,175,55,0.25)]">
                            <span class="js-counter" data-target="2376">0</span><span class="text-brand/70 ml-0.5">+</span>
                        </div>
                        <div class="mt-2.5 text-[11px] sm:text-xs text-zinc-400 uppercase tracking-[0.2em] font-medium">Araç</div>
                    </div>
                </div>

                {{-- Trust strip --}}
                <div class="mt-8 sm:mt-12 flex flex-wrap items-center justify-center gap-x-6 sm:gap-x-8 gap-y-2 sm:gap-y-3 text-[11px] sm:text-xs text-zinc-400 uppercase tracking-wider">
                    <div class="flex items-center gap-2"><span class="text-brand">★</span> Doğrulanmış Üye Sürücüler</div>
                    <div class="flex items-center gap-2"><span class="text-brand">✓</span> Şeffaf Katkı Payı</div>
                    <div class="flex items-center gap-2"><span class="text-brand">⏱</span> Uçuş Takibi</div>
                    <div class="flex items-center gap-2"><span class="text-brand">🛡</span> KVKK Korumalı</div>
                </div>
            </div>
        </div>

        {{-- City skyline silhouette --}}
        <svg class="absolute bottom-0 left-0 w-full h-24 md:h-32 text-black pointer-events-none" preserveAspectRatio="none" viewBox="0 0 1440 120" fill="currentColor">
            <path d="M0,120 L0,80 L40,80 L40,60 L80,60 L80,75 L120,75 L120,40 L160,40 L160,55 L200,55 L200,70 L240,70 L240,30 L280,30 L280,50 L320,50 L320,65 L360,65 L360,45 L400,45 L400,75 L440,75 L440,55 L480,55 L480,25 L520,25 L520,50 L560,50 L560,70 L600,70 L600,40 L640,40 L640,60 L680,60 L680,80 L720,80 L720,35 L760,35 L760,55 L800,55 L800,75 L840,75 L840,45 L880,45 L880,65 L920,65 L920,30 L960,30 L960,55 L1000,55 L1000,75 L1040,75 L1040,40 L1080,40 L1080,60 L1120,60 L1120,80 L1160,80 L1160,50 L1200,50 L1200,70 L1240,70 L1240,35 L1280,35 L1280,60 L1320,60 L1320,75 L1360,75 L1360,45 L1400,45 L1400,65 L1440,65 L1440,120 Z"/>
        </svg>
    </section>

    {{-- Reservation Form --}}
    <section id="rezervasyon" class="px-4 sm:px-6 pt-12 pb-16 -mt-8 relative z-10">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-8 sm:mb-10">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3">Rezervasyon Oluştur</h2>
                <p class="text-sm sm:text-base text-zinc-400">Adresleri yazın, fiyat ekranda anında görünsün.</p>
            </div>

            @if($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-200">
                    <div class="font-semibold mb-2">Lütfen aşağıdaki hataları düzeltin:</div>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="reservation-form" method="POST" action="{{ route('reservation.store') }}" class="space-y-4 sm:space-y-6">
                @csrf

                {{-- Step 1: Şehir + Araç --}}
                <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-4 sm:p-6 space-y-5">
                    <div class="flex items-center gap-2 text-brand font-semibold">
                        <span class="w-6 h-6 rounded-full bg-brand text-black flex items-center justify-center text-xs font-bold">1</span>
                        Şehir & Araç Sınıfı
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-2">Şehir</label>
                        <select id="city-select" name="city_id" required class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}"
                                    data-lat="{{ $city->center_lat }}"
                                    data-lng="{{ $city->center_lng }}"
                                    {{ old('city_id', $cities->first()->id) == $city->id ? 'selected' : '' }}>
                                    {{ $city->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-3">Araç Sınıfı</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            @foreach($vehicleClasses as $class)
                                <label class="cursor-pointer">
                                    <input type="radio" name="vehicle_class_id" value="{{ $class->id }}"
                                        class="peer sr-only vehicle-class-radio"
                                        {{ old('vehicle_class_id', $vehicleClasses->first()->id) == $class->id ? 'checked' : '' }}
                                        required>
                                    <div class="border-2 border-white/10 rounded-xl p-4 peer-checked:border-brand peer-checked:bg-brand/5 transition">
                                        <div class="font-bold text-lg mb-1">{{ $class->name }}</div>
                                        <div class="text-xs text-zinc-400 mb-2">👤 {{ $class->max_passengers }} kişi · 🧳 {{ $class->max_luggage }} bagaj</div>
                                        <div class="text-sm text-brand font-semibold">₺{{ number_format($class->base_fare, 0, ',', '.') }} açılış</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Step 2: Rota --}}
                <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-4 sm:p-6 space-y-5">
                    <div class="flex items-center gap-2 text-brand font-semibold">
                        <span class="w-6 h-6 rounded-full bg-brand text-black flex items-center justify-center text-xs font-bold">2</span>
                        Rota
                        <span id="distance-display" class="ml-auto text-xs text-zinc-400 font-normal hidden">
                            <span id="distance-text"></span> · <span id="duration-text"></span>
                        </span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-2">📍 Alış Adresi</label>
                        <div class="relative">
                            <input type="text" id="pickup-address" name="pickup_address" value="{{ old('pickup_address') }}" required
                                placeholder="Adres aramaya başlayın..."
                                autocomplete="off"
                                class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 pr-12 text-white focus:border-brand focus:outline-none">
                            <button type="button" id="use-my-location" title="Konumumu kullan"
                                class="absolute inset-y-0 right-0 flex items-center justify-center w-11 text-zinc-400 hover:text-brand transition-colors focus:outline-none focus:text-brand"
                                aria-label="Konumumu kullan">
                                <svg id="use-my-location-icon" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M2 12h2m16 0h2M12 7a5 5 0 100 10 5 5 0 000-10z"/>
                                    <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
                                </svg>
                                <svg id="use-my-location-spinner" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 hidden animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                                </svg>
                            </button>
                        </div>
                        <input type="hidden" id="pickup-lat" name="pickup_lat" value="{{ old('pickup_lat') }}">
                        <input type="hidden" id="pickup-lng" name="pickup_lng" value="{{ old('pickup_lng') }}">
                        <p id="use-my-location-error" class="hidden mt-1 text-xs text-red-400"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-2">🎯 Bırakış Adresi</label>
                        <input type="text" id="dropoff-address" name="dropoff_address" value="{{ old('dropoff_address') }}" required
                            placeholder="Adres aramaya başlayın..."
                            autocomplete="off"
                            class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                        <input type="hidden" id="dropoff-lat" name="dropoff_lat" value="{{ old('dropoff_lat') }}">
                        <input type="hidden" id="dropoff-lng" name="dropoff_lng" value="{{ old('dropoff_lng') }}">
                    </div>

                    {{-- Distance/duration hidden inputs (Maps doldurur) --}}
                    <input type="hidden" id="distance-km" name="distance_km" value="{{ old('distance_km') }}">
                    <input type="hidden" id="duration-minutes" name="duration_minutes" value="{{ old('duration_minutes') }}">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-zinc-300 mb-2">Tarih & Saat</label>
                            <input type="datetime-local" id="scheduled-at" name="scheduled_at" value="{{ old('scheduled_at', now()->addHours(2)->format('Y-m-d\TH:i')) }}" required
                                min="{{ now()->addMinutes(30)->format('Y-m-d\TH:i') }}"
                                class="w-full bg-zinc-800 border border-white/10 rounded-xl px-3 sm:px-4 py-3 text-white text-sm sm:text-base focus:border-brand focus:outline-none">
                        </div>
                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-zinc-300 mb-2">Yolcu Sayısı</label>
                            <input type="number" name="passenger_count" value="{{ old('passenger_count', 1) }}" min="1" max="8" required
                                class="w-full bg-zinc-800 border border-white/10 rounded-xl px-3 sm:px-4 py-3 text-white text-sm sm:text-base focus:border-brand focus:outline-none">
                        </div>
                    </div>
                </div>

                {{-- Step 3: Ekstralar --}}
                @if($extras->isNotEmpty())
                <details class="extras-accordion group bg-zinc-900/50 border border-white/5 rounded-2xl">
                    <summary class="flex items-center justify-between gap-2 p-4 sm:p-6 cursor-pointer list-none select-none">
                        <div class="flex items-center gap-2 text-brand font-semibold">
                            <span class="w-6 h-6 rounded-full bg-brand text-black flex items-center justify-center text-xs font-bold">3</span>
                            Ekstralar <span class="text-xs text-zinc-400 font-normal">(opsiyonel)</span>
                            <span id="extras-count-badge" class="hidden ml-1 text-xs px-2 py-0.5 rounded-full bg-brand/20 text-brand font-medium">0 seçili</span>
                        </div>
                        <svg class="w-5 h-5 text-zinc-400 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>

                    <div class="px-4 sm:px-6 pb-4 sm:pb-6 space-y-2">
                        @foreach($extras as $i => $extra)
                            <label class="flex items-center justify-between gap-2 sm:gap-3 p-3 bg-zinc-800/50 rounded-xl cursor-pointer hover:bg-zinc-800 transition">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" class="extra-toggle w-4 h-4 rounded border-white/20 bg-zinc-700"
                                        data-extra-id="{{ $extra->id }}"
                                        data-extra-price="{{ $extra->price }}"
                                        data-extra-per-unit="{{ $extra->per_unit ? '1' : '0' }}">
                                    <div>
                                        <div class="font-medium text-sm">{{ $extra->name }}</div>
                                        @if($extra->description)
                                            <div class="text-xs text-zinc-400">{{ $extra->description }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold {{ $extra->price > 0 ? 'text-brand' : 'text-zinc-400' }}">
                                        @if($extra->price > 0)
                                            +₺{{ number_format($extra->price, 0, ',', '.') }}
                                        @else
                                            Ücretsiz
                                        @endif
                                    </span>
                                    <input type="hidden" name="extras[{{ $i }}][extra_id]" value="{{ $extra->id }}" disabled
                                        data-extra-input-for="{{ $extra->id }}">
                                    <input type="number" name="extras[{{ $i }}][quantity]" value="1" min="1" max="{{ $extra->max_quantity }}"
                                        class="extra-quantity w-16 bg-zinc-700 border border-white/10 rounded px-2 py-1 text-white text-sm hidden"
                                        data-extra-input-for="{{ $extra->id }}"
                                        disabled>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </details>
                @endif

                {{-- Step 4: Müşteri Bilgileri --}}
                <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-4 sm:p-6 space-y-5">
                    <div class="flex items-center gap-2 text-brand font-semibold">
                        <span class="w-6 h-6 rounded-full bg-brand text-black flex items-center justify-center text-xs font-bold">4</span>
                        İletişim Bilgileri
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-2">Ad Soyad</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                            class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-2">Telefon</label>
                            <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" required
                                placeholder="0532 XXX XX XX"
                                class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-2">T.C. Kimlik No <span class="text-zinc-500 text-xs">(opsiyonel)</span></label>
                            <input type="text" name="customer_tc_no" value="{{ old('customer_tc_no') }}" maxlength="11" pattern="[0-9]{11}"
                                placeholder="11 haneli"
                                class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                        </div>
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer p-3 bg-zinc-800/30 rounded-xl">
                        <input type="checkbox" name="kvkk_consent" value="1" required class="mt-1 w-4 h-4 rounded border-white/20 bg-zinc-700">
                        <span class="text-xs text-zinc-400 leading-relaxed">
                            <strong class="text-zinc-300">KVKK Onayı:</strong>
                            Paylaştığım kişisel verilerimin 6698 sayılı KVKK kapsamında işlenmesini ve
                            5682 sayılı kanun gereği T.C. Kimlik bilgilerimin Emniyet Genel Müdürlüğü sistemlerine bildirilmesini kabul ediyorum.
                        </span>
                    </label>
                </div>

                {{-- Fare Preview --}}
                <div id="fare-preview" class="bg-gradient-to-br from-brand/10 to-brand/5 border-2 border-brand/30 rounded-2xl p-4 sm:p-6 hidden">
                    <div class="flex items-center justify-between mb-4">
                        <div id="fare-tier-badge" class="hidden px-3 py-1 rounded-full bg-brand text-black text-xs font-bold uppercase tracking-wider"></div>
                        <div id="fare-loading" class="hidden text-xs text-zinc-400">Hesaplanıyor...</div>
                    </div>

                    {{-- Üç özet kart: Mesafe / Süre / Tahmini Ücret --}}
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-4">
                        <div class="bg-zinc-900/60 border border-white/5 rounded-xl p-2 sm:p-3 min-w-0">
                            <div class="flex items-center gap-1 sm:gap-1.5 text-[10px] sm:text-xs text-zinc-400 uppercase tracking-wider mb-1">
                                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                <span class="truncate">Mesafe</span>
                            </div>
                            <div id="card-distance" class="text-sm sm:text-lg md:text-xl font-bold text-white truncate">—</div>
                        </div>
                        <div class="bg-zinc-900/60 border border-white/5 rounded-xl p-2 sm:p-3 min-w-0">
                            <div class="flex items-center gap-1 sm:gap-1.5 text-[10px] sm:text-xs text-zinc-400 uppercase tracking-wider mb-1">
                                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="truncate">Süre</span>
                            </div>
                            <div id="card-duration" class="text-sm sm:text-lg md:text-xl font-bold text-white truncate">—</div>
                        </div>
                        <div class="bg-brand/10 border-2 border-brand/40 rounded-xl p-2 sm:p-3 min-w-0">
                            <div class="flex items-center gap-1 sm:gap-1.5 text-[10px] sm:text-xs text-brand uppercase tracking-wider mb-1">
                                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="truncate">Katkı Payı</span>
                            </div>
                            <div id="card-fare" class="text-sm sm:text-lg md:text-xl font-bold text-brand truncate">—</div>
                        </div>
                    </div>

                    {{-- Detaylı kırılım (açılabilir) --}}
                    <details class="group">
                        <summary class="cursor-pointer list-none text-xs text-zinc-400 hover:text-zinc-200 flex items-center gap-1 select-none">
                            <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            Fiyat detayı
                        </summary>
                        <div id="fare-breakdown" class="space-y-1 text-sm mt-3 pt-3 border-t border-white/5"></div>
                    </details>
                </div>

                <button type="submit" class="w-full py-4 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold text-lg transition shadow-lg shadow-brand/20">
                    Rezervasyonu Oluştur →
                </button>

                <p class="text-center text-xs text-zinc-500">
                    Rezervasyon sonrası tahmini katkı payı SMS ile bildirilir. Nihai tutar yolculuk sonunda netleşir.
                </p>
            </form>
        </div>
    </section>

    {{-- Services --}}
    <section id="hizmetler" class="px-6 py-16">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-10">
                <h2 class="text-2xl md:text-3xl font-bold mb-2">Her Yolculuk için Eşleştirme</h2>
                <p class="text-zinc-400 text-sm">Havalimanından şehir içine, kurumsaldan premium yolculuğa — bağımsız üye sürücülerle.</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach([
                    ['✈️', 'Havalimanı Yolculuğu', 'Uçuş takibi dahil zamanında eşleştirme'],
                    ['💼', 'Kurumsal Yolculuk', 'İş toplantıları için önceden rezervasyon'],
                    ['⭐', 'Premium Yolculuk', 'Konforlu araçlarla özel anlarınız için'],
                    ['🏙️', 'Şehir İçi Yolculuk', 'Anlık paylaşımlı yolculuk eşleştirme'],
                ] as $service)
                    <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-6 hover:border-brand/30 transition">
                        <div class="text-3xl mb-3">{{ $service[0] }}</div>
                        <div class="text-white font-semibold mb-2">{{ $service[1] }}</div>
                        <div class="text-sm text-zinc-400 leading-relaxed">{{ $service[2] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</div>

@push('scripts')
{{-- Hero animated counters (0 → hedef, artarak) --}}
<script>
(function () {
    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        var suffix = el.getAttribute('data-suffix') || '';
        var duration = 1800;
        var start = null;
        function step(ts) {
            if (start === null) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            // easeOutExpo — hızlı başlayıp yavaşlayarak biter
            var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            var value = Math.floor(eased * target);
            el.textContent = value.toLocaleString('tr-TR') + (progress === 1 ? suffix : '');
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function run() {
        var counters = document.querySelectorAll('.js-counter');
        if (!counters.length) return;
        if (!('IntersectionObserver' in window)) {
            counters.forEach(animateCounter);
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        counters.forEach(function (c) { io.observe(c); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
</script>

{{-- Google Maps Places Library --}}
<script>
    window.initMap = function() {
        if (typeof google === 'undefined' || !google.maps) {
            console.warn('Google Maps yüklenemedi');
            return;
        }

        // İzmir bias (default)
        const izmirBounds = new google.maps.LatLngBounds(
            { lat: 38.30, lng: 26.80 },
            { lat: 38.55, lng: 27.40 }
        );

        const autocompleteOpts = {
            componentRestrictions: { country: 'tr' },
            fields: ['formatted_address', 'geometry', 'place_id', 'name', 'types'],
            bounds: izmirBounds,
            strictBounds: false,
        };

        /**
         * Place'i kullanıcı dostu metne çevir:
         *  - Tesis/POI (havalimanı, otel, mağaza): "İsim, Adres"
         *  - Sokak/cadde: sadece formatted_address (zaten ismi içeriyor)
         */
        const formatPlace = (place) => {
            const name = (place.name || '').trim();
            const addr = (place.formatted_address || '').trim();

            if (!name) return addr;
            if (!addr) return name;

            // Adres zaten isimle başlıyorsa tekrar koyma
            if (addr.toLowerCase().startsWith(name.toLowerCase())) {
                return addr;
            }

            // POI/tesis tipleri için name + addr birleştir
            const poiTypes = ['establishment', 'point_of_interest', 'airport', 'lodging',
                              'restaurant', 'shopping_mall', 'tourist_attraction', 'university',
                              'hospital', 'transit_station', 'train_station', 'bus_station'];
            const isPoi = (place.types || []).some(t => poiTypes.includes(t));

            return isPoi ? `${name}, ${addr}` : addr;
        };

        // Pickup autocomplete
        const pickupInput = document.getElementById('pickup-address');
        const pickupAuto = new google.maps.places.Autocomplete(pickupInput, autocompleteOpts);
        pickupAuto.addListener('place_changed', () => {
            const place = pickupAuto.getPlace();
            if (place.geometry && place.geometry.location) {
                document.getElementById('pickup-lat').value = place.geometry.location.lat();
                document.getElementById('pickup-lng').value = place.geometry.location.lng();
                pickupInput.value = formatPlace(place) || pickupInput.value;
                FeroGoForm.calculateDistance();
            }
        });

        // Dropoff autocomplete
        const dropoffInput = document.getElementById('dropoff-address');
        const dropoffAuto = new google.maps.places.Autocomplete(dropoffInput, autocompleteOpts);
        dropoffAuto.addListener('place_changed', () => {
            const place = dropoffAuto.getPlace();
            if (place.geometry && place.geometry.location) {
                document.getElementById('dropoff-lat').value = place.geometry.location.lat();
                document.getElementById('dropoff-lng').value = place.geometry.location.lng();
                dropoffInput.value = formatPlace(place) || dropoffInput.value;
                FeroGoForm.calculateDistance();
            }
        });

        // Enter tuşunda form submit'i engelle (autocomplete önerisi seçimi tetiklensin)
        [pickupInput, dropoffInput].forEach(el => {
            el.addEventListener('keydown', e => {
                if (e.key === 'Enter') e.preventDefault();
            });
        });

        // "Konumumu kullan" butonu: tarayıcı geolocation + reverse geocoding
        const locBtn = document.getElementById('use-my-location');
        const locIcon = document.getElementById('use-my-location-icon');
        const locSpinner = document.getElementById('use-my-location-spinner');
        const locError = document.getElementById('use-my-location-error');
        const geocoder = new google.maps.Geocoder();

        const setLocLoading = (loading) => {
            locBtn.disabled = loading;
            locIcon.classList.toggle('hidden', loading);
            locSpinner.classList.toggle('hidden', !loading);
        };
        const showLocError = (msg) => {
            locError.textContent = msg;
            locError.classList.remove('hidden');
        };
        const clearLocError = () => {
            locError.textContent = '';
            locError.classList.add('hidden');
        };

        locBtn.addEventListener('click', () => {
            clearLocError();
            if (!navigator.geolocation) {
                showLocError('Tarayıcınız konum servisini desteklemiyor.');
                return;
            }
            setLocLoading(true);

            // OpenStreetMap Nominatim ile reverse geocoding (anahtar gerektirmez)
            const nominatimReverse = async (lat, lng) => {
                const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=tr&zoom=18&addressdetails=1`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Nominatim HTTP ' + res.status);
                const data = await res.json();
                return data && data.display_name ? data.display_name : null;
            };

            const finalize = (lat, lng, address) => {
                pickupInput.value = address || `Konumum (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                document.getElementById('pickup-lat').value = lat;
                document.getElementById('pickup-lng').value = lng;
                setLocLoading(false);
                FeroGoForm.calculateDistance();
            };

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;

                    geocoder.geocode({ location: { lat, lng } }, async (results, status) => {
                        if (status === 'OK' && results && results[0]) {
                            finalize(lat, lng, results[0].formatted_address);
                            return;
                        }
                        // Google başarısız (büyük ihtimalle Geocoding API kapalı) → Nominatim'e düş
                        console.warn('Google geocoder status:', status, '— Nominatim deneniyor');
                        try {
                            const addr = await nominatimReverse(lat, lng);
                            finalize(lat, lng, addr);
                        } catch (e) {
                            console.warn('Nominatim hatası:', e);
                            finalize(lat, lng, null);
                            showLocError('Adres çevrilemedi, koordinatlarla devam ediliyor.');
                        }
                    });
                },
                (err) => {
                    setLocLoading(false);
                    if (err.code === err.PERMISSION_DENIED) {
                        showLocError('Konum izni reddedildi. Tarayıcı ayarlarından izin verin.');
                    } else if (err.code === err.POSITION_UNAVAILABLE) {
                        showLocError('Konum bilgisi alınamadı.');
                    } else if (err.code === err.TIMEOUT) {
                        showLocError('Konum isteği zaman aşımına uğradı.');
                    } else {
                        showLocError('Konum alınırken bir hata oluştu.');
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    };
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&libraries=places&callback=initMap&language=tr&region=TR"></script>

{{-- Form interaction & live fare --}}
<script>
const FeroGoForm = (function() {
    let distanceService = null;
    let fareDebounceTimer = null;

    function getDistanceService() {
        if (!distanceService && typeof google !== 'undefined' && google.maps) {
            distanceService = new google.maps.DistanceMatrixService();
        }
        return distanceService;
    }

    function calculateDistance() {
        const pLat = parseFloat(document.getElementById('pickup-lat').value);
        const pLng = parseFloat(document.getElementById('pickup-lng').value);
        const dLat = parseFloat(document.getElementById('dropoff-lat').value);
        const dLng = parseFloat(document.getElementById('dropoff-lng').value);

        if (!pLat || !pLng || !dLat || !dLng) return;

        const service = getDistanceService();
        if (!service) return;

        showFareLoading(true);

        service.getDistanceMatrix({
            origins: [{ lat: pLat, lng: pLng }],
            destinations: [{ lat: dLat, lng: dLng }],
            travelMode: google.maps.TravelMode.DRIVING,
            unitSystem: google.maps.UnitSystem.METRIC,
        }, (response, status) => {
            if (status === 'OK' && response.rows[0].elements[0].status === 'OK') {
                const el = response.rows[0].elements[0];
                const km = +(el.distance.value / 1000).toFixed(2);
                const min = Math.round(el.duration.value / 60);

                document.getElementById('distance-km').value = km;
                document.getElementById('duration-minutes').value = min;

                document.getElementById('distance-text').textContent = km.toFixed(1) + ' km';
                document.getElementById('duration-text').textContent = min + ' dk';
                document.getElementById('distance-display').classList.remove('hidden');

                updateFarePreview();
            } else {
                console.warn('Distance Matrix hata:', status);
                showFareLoading(false);
            }
        });
    }

    function updateFarePreview() {
        clearTimeout(fareDebounceTimer);
        fareDebounceTimer = setTimeout(_updateFarePreview, 300);
    }

    function _updateFarePreview() {
        const km = document.getElementById('distance-km').value;
        const min = document.getElementById('duration-minutes').value;
        if (!km || !min) return;

        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name=csrf-token]').content);
        formData.append('city_id', document.getElementById('city-select').value);
        const checkedClass = document.querySelector('.vehicle-class-radio:checked');
        if (!checkedClass) return;
        formData.append('vehicle_class_id', checkedClass.value);
        formData.append('distance_km', km);
        formData.append('duration_minutes', min);
        formData.append('scheduled_at', document.getElementById('scheduled-at').value);

        // Müşteri telefonu (varsa) — sadık müşteri indirimli indi-bindi için
        const phoneInput = document.querySelector('input[name="customer_phone"]');
        if (phoneInput && phoneInput.value.trim().length >= 10) {
            formData.append('customer_phone', phoneInput.value.trim());
        }

        // Aktif ekstralar
        let extraIdx = 0;
        document.querySelectorAll('.extra-toggle:checked').forEach(cb => {
            const id = cb.dataset.extraId;
            const qty = document.querySelector(`.extra-quantity[data-extra-input-for="${id}"]`)?.value || 1;
            formData.append(`extras[${extraIdx}][extra_id]`, id);
            formData.append(`extras[${extraIdx}][quantity]`, qty);
            extraIdx++;
        });

        showFareLoading(true);

        fetch('{{ route('reservation.calculate-fare') }}', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(data => {
            showFareLoading(false);
            if (data.success && data.fare) {
                renderFare(data.fare);
            }
        })
        .catch(err => {
            console.warn('Fiyat hesaplanamadı:', err);
            showFareLoading(false);
        });
    }

    function renderFare(fare) {
        const fmt = (n) => '₺' + Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const fmtInt = (n) => '₺' + Math.round(Number(n)).toLocaleString('tr-TR');

        // Üst kartlar: Mesafe / Süre / Tahmini Ücret
        const km = parseFloat(document.getElementById('distance-km').value) || 0;
        const min = parseInt(document.getElementById('duration-minutes').value) || 0;
        document.getElementById('card-distance').textContent = km.toFixed(1) + ' km';
        document.getElementById('card-duration').textContent = min + ' dk';
        document.getElementById('card-fare').textContent = fmtInt(fare.total_fare);

        // Araç sınıfı rozet
        const tierBadge = document.getElementById('fare-tier-badge');
        const selectedRadio = document.querySelector('.vehicle-class-radio:checked');
        if (selectedRadio) {
            const tierName = selectedRadio.closest('label').querySelector('.font-bold').textContent.trim();
            tierBadge.textContent = tierName;
            tierBadge.classList.remove('hidden');
        }

        let html = `
            <div class="flex justify-between text-zinc-300"><span>Açılış</span><span>${fmt(fare.base_fare)}</span></div>
        `;
        if (parseFloat(fare.boarding_fee) > 0) {
            const tierLabels = { trusted: 'sadık müşteri', standard: 'müşteri', new: 'yeni müşteri', suspicious: 'riskli' };
            const tierColors = { trusted: 'text-emerald-400', standard: 'text-zinc-300', new: 'text-zinc-400', suspicious: 'text-rose-400' };
            const label = tierLabels[fare.customer_trust_tier] || 'standart';
            const color = tierColors[fare.customer_trust_tier] || 'text-zinc-300';
            html += `<div class="flex justify-between ${color}"><span>İndi-bindi <span class="text-xs opacity-70">(${label})</span></span><span>${fmt(fare.boarding_fee)}</span></div>`;
        }
        html += `<div class="flex justify-between text-zinc-300"><span>Mesafe</span><span>${fmt(fare.distance_fare)}</span></div>`;
        if (parseFloat(fare.time_fare) > 0) {
            html += `<div class="flex justify-between text-zinc-300"><span>Süre</span><span>${fmt(fare.time_fare)}</span></div>`;
        }
        if (parseFloat(fare.extras_total) > 0) {
            html += `<div class="flex justify-between text-zinc-300"><span>Ekstralar</span><span>${fmt(fare.extras_total)}</span></div>`;
        }
        if (parseFloat(fare.multiplier) > 1) {
            html += `<div class="flex justify-between text-yellow-400 text-xs"><span>Gece zammı (×${fare.multiplier})</span><span>uygulandı</span></div>`;
        }
        html += `
            <div class="flex justify-between text-xl font-bold text-white pt-3 mt-2 border-t border-white/10">
                <span>Tahmini Toplam</span>
                <span class="text-brand">${fmt(fare.total_fare)}</span>
            </div>
        `;
        document.getElementById('fare-breakdown').innerHTML = html;
        document.getElementById('fare-preview').classList.remove('hidden');
    }

    function showFareLoading(show) {
        document.getElementById('fare-loading').classList.toggle('hidden', !show);
    }

    function updateExtrasBadge() {
        const badge = document.getElementById('extras-count-badge');
        if (!badge) return;
        const count = document.querySelectorAll('.extra-toggle:checked').length;
        if (count > 0) {
            badge.textContent = count + ' seçili';
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function initExtras() {
        document.querySelectorAll('.extra-toggle').forEach(cb => {
            cb.addEventListener('change', (e) => {
                e.stopPropagation();
                const id = cb.dataset.extraId;
                const inputs = document.querySelectorAll(`[data-extra-input-for="${id}"]`);
                inputs.forEach(input => {
                    input.disabled = !cb.checked;
                    if (input.classList.contains('extra-quantity')) {
                        input.classList.toggle('hidden', !cb.checked);
                    }
                });
                updateExtrasBadge();
                updateFarePreview();
            });
        });

        document.querySelectorAll('.extra-quantity').forEach(qInput => {
            qInput.addEventListener('change', updateFarePreview);
            qInput.addEventListener('click', (e) => e.stopPropagation());
        });
    }

    function initFormChangeListeners() {
        document.getElementById('city-select')?.addEventListener('change', updateFarePreview);
        document.querySelectorAll('.vehicle-class-radio').forEach(r => {
            r.addEventListener('change', updateFarePreview);
        });
        document.getElementById('scheduled-at')?.addEventListener('change', updateFarePreview);
        // Telefon değişince katmana göre indi-bindi yeniden hesaplanır
        document.querySelector('input[name="customer_phone"]')?.addEventListener('input', updateFarePreview);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initExtras();
        initFormChangeListeners();
        initHeroBackground();
    });

    function initHeroBackground() {
        const layer = document.getElementById('hero-bg-img');
        if (!layer) return;
        const url = '{{ asset('images/hero-bg.jpg') }}';
        const probe = new Image();
        probe.onload = () => layer.classList.replace('opacity-0', 'opacity-100');
        probe.onerror = () => layer.remove();
        probe.src = url;
    }

    return { calculateDistance, updateFarePreview };
})();
</script>
@endpush
@endsection
