@extends('layouts.public')

@section('title', 'Ferogo · İzmir Premium Şoförlü Transfer')

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
        <div class="relative px-6 py-24 md:py-36">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand/10 border border-brand/25 text-brand text-xs font-semibold uppercase tracking-wider mb-6 backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></span>
                    İzmir · 7/24 Aktif Hizmet
                </div>
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold leading-tight mb-6 tracking-tight drop-shadow-2xl">
                    Şehirde <span class="text-shimmer">premium</span> ulaşımın<br>en kolay yolu
                </h1>
                <p class="text-lg md:text-xl text-zinc-200 max-w-2xl mx-auto leading-relaxed mb-10 drop-shadow-lg">
                    Profesyonel şoförler, bakımlı lüks araçlar, şeffaf fiyat.<br>
                    Havalimanı, iş toplantısı, şehir içi — her an yanınızda.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 items-center justify-center">
                    <a href="#rezervasyon" class="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-brand hover:bg-brand-600 text-black font-bold text-lg transition shadow-lg shadow-brand/30 hover:shadow-brand/50 hover:scale-105 duration-200">
                        Hemen Rezervasyon Yap
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                    <a href="tel:+908508401377" class="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-white/5 hover:bg-white/10 border border-white/15 text-white font-semibold text-base transition backdrop-blur-sm">
                        <svg class="w-5 h-5 text-brand" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                        0850 840 13 77
                    </a>
                </div>

                {{-- Trust strip --}}
                <div class="mt-12 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-xs text-zinc-400 uppercase tracking-wider">
                    <div class="flex items-center gap-2"><span class="text-brand">★</span> Lisanslı Şoförler</div>
                    <div class="flex items-center gap-2"><span class="text-brand">✓</span> Şeffaf Fiyat</div>
                    <div class="flex items-center gap-2"><span class="text-brand">⏱</span> Uçuş Takibi</div>
                    <div class="flex items-center gap-2"><span class="text-brand">♛</span> Lüks Filo</div>
                </div>
            </div>
        </div>

        {{-- City skyline silhouette --}}
        <svg class="absolute bottom-0 left-0 w-full h-24 md:h-32 text-black pointer-events-none" preserveAspectRatio="none" viewBox="0 0 1440 120" fill="currentColor">
            <path d="M0,120 L0,80 L40,80 L40,60 L80,60 L80,75 L120,75 L120,40 L160,40 L160,55 L200,55 L200,70 L240,70 L240,30 L280,30 L280,50 L320,50 L320,65 L360,65 L360,45 L400,45 L400,75 L440,75 L440,55 L480,55 L480,25 L520,25 L520,50 L560,50 L560,70 L600,70 L600,40 L640,40 L640,60 L680,60 L680,80 L720,80 L720,35 L760,35 L760,55 L800,55 L800,75 L840,75 L840,45 L880,45 L880,65 L920,65 L920,30 L960,30 L960,55 L1000,55 L1000,75 L1040,75 L1040,40 L1080,40 L1080,60 L1120,60 L1120,80 L1160,80 L1160,50 L1200,50 L1200,70 L1240,70 L1240,35 L1280,35 L1280,60 L1320,60 L1320,75 L1360,75 L1360,45 L1400,45 L1400,65 L1440,65 L1440,120 Z"/>
        </svg>
    </section>

    {{-- Services --}}
    <section id="hizmetler" class="px-6 py-16">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach([
                    ['✈️', 'Havalimanı Transferi', 'Uçuş takibi dahil zamanında transfer'],
                    ['💼', 'Kurumsal Seyahat', 'İş toplantıları için önceden rezervasyon'],
                    ['⭐', 'VIP Transfer', 'Lüks araç filosu, özel anlarınız için'],
                    ['🏙️', 'Şehir İçi Ulaşım', 'Anlık şoförlü araç hizmeti'],
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

    {{-- Reservation Form --}}
    <section id="rezervasyon" class="px-6 py-16">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-10">
                <h2 class="text-3xl md:text-4xl font-bold mb-3">Rezervasyon Oluştur</h2>
                <p class="text-zinc-400">Adresleri yazın, fiyat ekranda anında görünsün.</p>
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

            <form id="reservation-form" method="POST" action="{{ route('reservation.store') }}" class="space-y-6">
                @csrf

                {{-- Step 1: Şehir + Araç --}}
                <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-6 space-y-5">
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
                <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-6 space-y-5">
                    <div class="flex items-center gap-2 text-brand font-semibold">
                        <span class="w-6 h-6 rounded-full bg-brand text-black flex items-center justify-center text-xs font-bold">2</span>
                        Rota
                        <span id="distance-display" class="ml-auto text-xs text-zinc-400 font-normal hidden">
                            <span id="distance-text"></span> · <span id="duration-text"></span>
                        </span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-2">📍 Alış Adresi</label>
                        <input type="text" id="pickup-address" name="pickup_address" value="{{ old('pickup_address') }}" required
                            placeholder="Adres aramaya başlayın..."
                            autocomplete="off"
                            class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                        <input type="hidden" id="pickup-lat" name="pickup_lat" value="{{ old('pickup_lat') }}">
                        <input type="hidden" id="pickup-lng" name="pickup_lng" value="{{ old('pickup_lng') }}">
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

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-2">Tarih & Saat</label>
                            <input type="datetime-local" id="scheduled-at" name="scheduled_at" value="{{ old('scheduled_at', now()->addHours(2)->format('Y-m-d\TH:i')) }}" required
                                min="{{ now()->addMinutes(30)->format('Y-m-d\TH:i') }}"
                                class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-2">Yolcu Sayısı</label>
                            <input type="number" name="passenger_count" value="{{ old('passenger_count', 1) }}" min="1" max="8" required
                                class="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-brand focus:outline-none">
                        </div>
                    </div>
                </div>

                {{-- Step 3: Ekstralar --}}
                @if($extras->isNotEmpty())
                <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-6 space-y-5">
                    <div class="flex items-center gap-2 text-brand font-semibold">
                        <span class="w-6 h-6 rounded-full bg-brand text-black flex items-center justify-center text-xs font-bold">3</span>
                        Ekstralar <span class="text-xs text-zinc-400 font-normal">(opsiyonel)</span>
                    </div>

                    <div class="space-y-2">
                        @foreach($extras as $i => $extra)
                            <label class="flex items-center justify-between gap-3 p-3 bg-zinc-800/50 rounded-xl cursor-pointer hover:bg-zinc-800 transition">
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
                </div>
                @endif

                {{-- Step 4: Müşteri Bilgileri --}}
                <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-6 space-y-5">
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
                <div id="fare-preview" class="bg-gradient-to-br from-brand/10 to-brand/5 border-2 border-brand/30 rounded-2xl p-6 hidden">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-brand font-semibold">💰 Tahmini Ücret</div>
                        <div id="fare-loading" class="hidden text-xs text-zinc-400">Hesaplanıyor...</div>
                    </div>
                    <div id="fare-breakdown" class="space-y-1 text-sm"></div>
                </div>

                <button type="submit" class="w-full py-4 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold text-lg transition shadow-lg shadow-brand/20">
                    Rezervasyonu Oluştur →
                </button>

                <p class="text-center text-xs text-zinc-500">
                    Rezervasyon sonrası tahmini ücret SMS ile gönderilir. Nihai ücret yolculuk sonunda netleşir.
                </p>
            </form>
        </div>
    </section>

</div>

@push('scripts')
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
        let html = `
            <div class="flex justify-between text-zinc-300"><span>Açılış</span><span>${fmt(fare.base_fare)}</span></div>
            <div class="flex justify-between text-zinc-300"><span>Mesafe</span><span>${fmt(fare.distance_fare)}</span></div>
        `;
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

    function initExtras() {
        document.querySelectorAll('.extra-toggle').forEach(cb => {
            cb.addEventListener('change', () => {
                const id = cb.dataset.extraId;
                const inputs = document.querySelectorAll(`[data-extra-input-for="${id}"]`);
                inputs.forEach(input => {
                    input.disabled = !cb.checked;
                    if (input.classList.contains('extra-quantity')) {
                        input.classList.toggle('hidden', !cb.checked);
                    }
                });
                updateFarePreview();
            });
        });

        document.querySelectorAll('.extra-quantity').forEach(qInput => {
            qInput.addEventListener('change', updateFarePreview);
        });
    }

    function initFormChangeListeners() {
        document.getElementById('city-select')?.addEventListener('change', updateFarePreview);
        document.querySelectorAll('.vehicle-class-radio').forEach(r => {
            r.addEventListener('change', updateFarePreview);
        });
        document.getElementById('scheduled-at')?.addEventListener('change', updateFarePreview);
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
