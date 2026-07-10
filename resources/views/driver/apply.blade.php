@extends('layouts.public')

@section('title', 'Üye Sürücü Olun · FerXGo · Aynı Yolun Yolcusuyla Buluş')
@section('description', 'FerXGo paylaşımlı yolculuk platformuna bağımsız üye sürücü olarak katıl. Aynı güzergahtaki yolcularla buluş, yol masraflarını paylaş. Platform komisyon almaz; sabit üyelik ile erişirsin. FerXGo ticari taşımacılık değil, dijital eşleştirme hizmetidir.')

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
    /* Select açılınca dropdown option'ları koyu tema — beyaz zeminli okunmaz option'lar için kritik fix */
    select.form-input {
        color: #fff;
        background-color: #0a0a0a;
        color-scheme: dark;
    }
    select.form-input option,
    select.form-input optgroup {
        background-color: #0f0f0f;
        color: #fff;
        padding: 8px 12px;
    }
    select.form-input option:checked,
    select.form-input option:hover {
        background-color: #F0C040;
        color: #000;
    }
    select.form-input:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    /* Firefox için ek stil */
    select.form-input option[disabled] {
        color: #666;
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

            <form method="POST" action="{{ route('driver.apply.store') }}" enctype="multipart/form-data"
                  class="bg-zinc-950/60 backdrop-blur-xl border border-white/10 rounded-3xl p-6 md:p-10 space-y-10">
                @csrf

                @if(isset($categories) && $categories->count() > 0)
                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">1. Sürücü Kategorisi</div>
                    <p class="text-xs text-zinc-500 mb-4">Hangi tür araçla paylaşımlı yolculuk yapacaksın?</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach($categories as $cat)
                            <label class="cursor-pointer">
                                <input type="radio" name="driver_category_id" value="{{ $cat->id }}" required
                                       {{ old('driver_category_id') == $cat->id ? 'checked' : '' }}
                                       data-slug="{{ $cat->slug }}" class="sr-only peer category-radio">
                                <div class="p-5 rounded-2xl border-2 border-white/10 bg-white/[0.02] peer-checked:border-brand peer-checked:bg-brand/10 transition text-center">
                                    <div class="text-4xl mb-2">{{ $cat->emoji }}</div>
                                    <div class="text-base font-bold text-white">{{ $cat->name }}</div>
                                    <div class="text-[11px] text-zinc-500 mt-1">
                                        Ehliyet: <span class="text-zinc-300 font-semibold">{{ $cat->required_license_class }}</span>
                                        @if($cat->requires_src)  · SRC şart @endif
                                        @if($cat->requires_helmet) · Kask şart @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </section>
                @endif

                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">2. Kişisel Bilgiler</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Ad Soyad</label>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" required maxlength="120" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="Mehmet Yılmaz">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-zinc-400 mb-2">T.C. Kimlik No <span class="text-zinc-600">(11 hane · yasal zorunlu)</span></label>
                            <input type="text" name="tc_no" value="{{ old('tc_no') }}" required pattern="[0-9]{11}" maxlength="11" inputmode="numeric" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600 tabular-nums" placeholder="12345678901">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Telefon</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="32" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="0532 000 00 00">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Doğum yılı</label>
                            <input type="number" name="birth_year" value="{{ old('birth_year') }}" required min="1940" max="{{ date('Y') - 18 }}" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="1990">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">E-posta <span class="text-zinc-600">(giriş için)</span></label>
                            <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="mehmet@ornek.com">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Şehir</label>
                            <select name="city_id" required class="form-input w-full rounded-xl px-4 py-3 text-white">
                                <option value="">Seçiniz</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Şifre <span class="text-zinc-600">(min 6 karakter)</span></label>
                            <input type="password" name="password" required minlength="6" maxlength="100" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="••••••••">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Şifre (tekrar)</label>
                            <input type="password" name="password_confirmation" required minlength="6" maxlength="100" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600" placeholder="••••••••">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Cinsiyet</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="gender" value="male" required class="sr-only peer" {{ old('gender') === 'male' ? 'checked' : '' }}>
                                    <div class="flex items-center justify-center gap-2 p-4 rounded-xl bg-white/[0.02] border border-white/10 peer-checked:border-brand peer-checked:bg-brand/10 text-zinc-300 transition">
                                        <span class="text-lg">👨</span><span class="text-sm font-medium">Erkek</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="gender" value="female" required class="sr-only peer" {{ old('gender') === 'female' ? 'checked' : '' }}>
                                    <div class="flex items-center justify-center gap-2 p-4 rounded-xl bg-white/[0.02] border border-white/10 peer-checked:border-brand peer-checked:bg-brand/10 text-zinc-300 transition">
                                        <span class="text-lg">👩</span><span class="text-sm font-medium">Kadın</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Ehliyet sınıfı</label>
                            <select name="license_class" required class="form-input w-full rounded-xl px-4 py-3 text-white">
                                @foreach(['B' => 'B', 'A2' => 'A2', 'A' => 'A', 'D1' => 'D1', 'D' => 'D', 'E' => 'E'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('license_class', 'B') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Deneyim</label>
                            <select name="experience_band" required class="form-input w-full rounded-xl px-4 py-3 text-white">
                                @foreach(['under_1' => '1 yıldan az', '1_to_3' => '1-3 yıl', '3_to_5' => '3-5 yıl', '5_plus' => '5 yıl ve üzeri'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('experience_band', '1_to_3') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">3. Kimlik & Selfie</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        @include('partials.file-upload', ['name' => 'selfie',   'label' => 'Selfie',        'hint' => 'Yüzün net görünsün', 'mode' => 'photo', 'capture' => 'user'])
                        @include('partials.file-upload', ['name' => 'id_front', 'label' => 'Kimlik — Ön',   'hint' => 'T.C. kimlik ön yüz', 'mode' => 'photo', 'capture' => 'environment'])
                        @include('partials.file-upload', ['name' => 'id_back',  'label' => 'Kimlik — Arka', 'hint' => 'T.C. kimlik arka yüz', 'mode' => 'photo', 'capture' => 'environment'])
                    </div>
                </section>

                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">4. Ehliyet</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @include('partials.file-upload', ['name' => 'license_front', 'label' => 'Ehliyet — Ön',  'hint' => 'Fotoğraflı yüz', 'mode' => 'photo', 'capture' => 'environment'])
                        @include('partials.file-upload', ['name' => 'license_back',  'label' => 'Ehliyet — Arka','hint' => 'Sınıflar/geçerlilik', 'mode' => 'photo', 'capture' => 'environment'])
                    </div>
                </section>

                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">5. Araç Bilgileri</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Marka</label>
                            <select id="vehicle-make" name="vehicle_make_id" required class="form-input w-full rounded-xl px-4 py-3 text-white">
                                <option value="">Önce kategori seç</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Model</label>
                            <select id="vehicle-model" name="vehicle_model_id" required class="form-input w-full rounded-xl px-4 py-3 text-white" disabled>
                                <option value="">Önce marka seç</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Yıl</label>
                            <select name="vehicle_year" required class="form-input w-full rounded-xl px-4 py-3 text-white">
                                <option value="">Seçiniz</option>
                                @for($y = date('Y') + 1; $y >= 2000; $y--)
                                    <option value="{{ $y }}" {{ old('vehicle_year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Renk</label>
                            <select name="vehicle_color" required class="form-input w-full rounded-xl px-4 py-3 text-white">
                                <option value="">Seçiniz</option>
                                @foreach(['Beyaz','Siyah','Gri','Gümüş','Kırmızı','Mavi','Yeşil','Sarı','Turuncu','Kahverengi','Bej','Diğer'] as $renk)
                                    <option value="{{ $renk }}" {{ old('vehicle_color') === $renk ? 'selected' : '' }}>{{ $renk }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Yolcu Kapasitesi <span class="text-zinc-600">(sürücü hariç)</span></label>
                            <select name="vehicle_capacity" required class="form-input w-full rounded-xl px-4 py-3 text-white">
                                <option value="">Seçiniz</option>
                                @foreach([1 => '1 yolcu (motor)', 2 => '2 yolcu', 3 => '3 yolcu', 4 => '4 yolcu (standart otomobil)', 5 => '5 yolcu', 6 => '6 yolcu (SUV/Kombi)', 7 => '7 yolcu (Vito/Minibüs)', 8 => '8 yolcu', 9 => '9-11 yolcu (Sprinter/Transporter)', 12 => '12-16 yolcu (Minibüs)'] as $cap => $label)
                                    <option value="{{ $cap }}" {{ old('vehicle_capacity') == $cap ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-zinc-400 mb-2">Plaka</label>
                            <input type="text" name="vehicle_plate" value="{{ old('vehicle_plate') }}" required maxlength="15" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600 uppercase" placeholder="35 ABC 1234">
                        </div>
                    </div>
                </section>

                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">6. Araç Fotoğrafları <span class="text-zinc-600 normal-case tracking-normal">— 6 açı zorunlu</span></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach($vehiclePhotoSlots as $slot => $label)
                            @include('partials.file-upload', ['name' => 'vehicle_photo_' . $slot, 'label' => $label, 'hint' => 'Aracın net görünsün', 'mode' => 'photo', 'capture' => 'environment'])
                        @endforeach
                    </div>
                </section>

                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">7. Belgeler <span class="text-zinc-600 normal-case tracking-normal">— PDF veya fotoğraf</span></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @include('partials.file-upload', ['name' => 'registration_file',    'label' => 'Araç Ruhsatı',      'hint' => 'PDF veya fotoğraf'])
                        @include('partials.file-upload', ['name' => 'insurance_file',       'label' => 'Trafik Sigortası',  'hint' => 'Geçerlilik tarihi görünsün'])
                        @include('partials.file-upload', ['name' => 'inspection_file',      'label' => 'Fenni Muayene',     'hint' => 'TÜVTÜRK belgesi'])
                        @include('partials.file-upload', ['name' => 'criminal_record_file', 'label' => 'Adli Sicil Kaydı',  'hint' => 'e-Devletten al'])
                    </div>
                </section>

                <section id="taxi-docs" class="hidden">
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-5 pb-3 border-b border-brand/20">🚕 Sarı Taksi — Ek Belgeler</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @include('partials.file-upload', ['name' => 'src_file',         'label' => 'SRC-2 Sertifikası',        'hint' => 'Zorunlu'])
                        @include('partials.file-upload', ['name' => 'taksi_plaka_file', 'label' => 'Ticari Taksi Plaka İzin',  'hint' => 'T plaka belgesi'])
                        @include('partials.file-upload', ['name' => 'taksimetre_file',  'label' => 'Taksimetre Kalibrasyon',   'hint' => 'Geçerli kalibrasyon'])
                        @include('partials.file-upload', ['name' => 'oda_kaydi_file',   'label' => 'Taksiciler Odası Kaydı',   'hint' => 'İzmir Esnaf Odası'])
                    </div>
                </section>

                <section id="motor-docs" class="hidden">
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-5 pb-3 border-b border-brand/20">🏍 Motosiklet — Ek Belge</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @include('partials.file-upload', ['name' => 'helmet_file', 'label' => 'Kask Fotoğrafı', 'hint' => 'Kullandığın kaskı çek', 'mode' => 'photo', 'capture' => 'environment'])
                    </div>
                </section>

                <section>
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 mb-5 pb-3 border-b border-white/5">Eklemek istediğin</div>
                    <textarea name="notes" rows="3" maxlength="1000" class="form-input w-full rounded-xl px-4 py-3 text-white placeholder-zinc-600 resize-none" placeholder="Bize iletmek istediğin bir şey">{{ old('notes') }}</textarea>
                </section>

                <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 md:p-5 flex items-start gap-3">
                    <div class="text-xl shrink-0">📋</div>
                    <div class="text-xs md:text-sm text-zinc-300 leading-relaxed">
                        <div class="font-semibold text-amber-200 mb-1.5">Paylaşımlı Yolculuk Modeli</div>
                        FerXGo <strong>ticari taşımacılık hizmeti sunmaz</strong>. Aynı güzergâhı paylaşan yolcu ve üye sürücü, yolculuğun <strong>yakıt, aşınma ve yol masraflarını</strong> aralarında paylaşırlar. Yolcunun ödediği tutar, ticari bir hizmet bedeli değil; ortak yolculuk giderlerine katkı payıdır. FerXGo yalnızca aracılık ve eşleştirme hizmeti sunar.
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="kvkk" value="1" required class="mt-1 w-5 h-5 rounded border-white/20 bg-white/5 text-brand focus:ring-brand focus:ring-offset-0">
                        <span class="text-sm text-zinc-400 leading-relaxed">
                            Kişisel verilerimin işlenmesini kabul ediyorum.
                            <a href="{{ route('legal.kvkk') }}" target="_blank" class="text-brand hover:underline">KVKK Aydınlatma Metni</a>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="terms" value="1" required class="mt-1 w-5 h-5 rounded border-white/20 bg-white/5 text-brand focus:ring-brand focus:ring-offset-0">
                        <span class="text-sm text-zinc-400 leading-relaxed">
                            <a href="{{ route('legal.terms') }}" target="_blank" class="text-brand hover:underline">Hizmet Şartları</a> ve
                            <a href="{{ route('legal.ride-sharing') }}" target="_blank" class="text-brand hover:underline">Paylaşımlı Yolculuk modelini</a>
                            okudum, kabul ediyorum.
                        </span>
                    </label>
                </div>

                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-8 py-5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-lg transition-all shadow-2xl shadow-brand/30 hover:shadow-brand/50">
                    Başvurumu Gönder
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </button>

                <p class="text-xs text-zinc-500 text-center">
                    Sorun var mı? → <a href="tel:+908503403039" class="text-brand hover:underline font-semibold">0850 340 3039</a>
                </p>
            </form>

            {{-- Reklam alanı: Sürücü Olun — Alt (formun altı, ayrı yönetilir) --}}
            @include('partials.ad-slot', ['placement' => 'driver_apply_bottom', 'class' => 'mt-8'])

            <div class="mt-6 text-center">
                <a href="#neden-ferxgo" class="inline-flex items-center gap-2 text-xs text-zinc-500 hover:text-brand transition">
                    Model nasıl işliyor, öğren
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
                    İzmir'de <span class="text-white font-semibold">üye sürücü kaydı</span> açık
                </div>

                <h1 class="display-font text-5xl sm:text-6xl md:text-7xl lg:text-8xl text-white mb-8">
                    Aynı yol,<br>
                    paylaşılan<br>
                    <span class="relative inline-block">
                        <span class="text-brand glow-text">masraf</span><span class="text-brand">.</span>
                    </span>
                </h1>

                <p class="text-lg md:text-xl text-zinc-300 leading-relaxed mb-10 max-w-xl">
                    İzmir'in büyüyen paylaşımlı yolculuk platformuna <strong>bağımsız üye sürücü</strong> olarak katıl. Aynı güzergahtaki yolcularla buluş, yol masraflarını paylaş. Esnek saatler, sabit üyelik, komisyonsuz katkı payı — kendi yolculuğunun sahibi sen ol.
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

            {{-- Right: cost-sharing model card --}}
            <div class="lg:col-span-5 relative">
                <div class="relative">
                    {{-- Decorative orb behind card --}}
                    <div class="absolute -inset-6 bg-brand/20 blur-3xl rounded-full"></div>

                    {{-- Model card --}}
                    <div class="relative stat-card border border-white/10 rounded-3xl p-7 md:p-9 shadow-2xl shadow-black/40">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <div class="text-xs uppercase tracking-widest text-zinc-400 mb-2">Masraf paylaşımı</div>
                                <div class="text-sm text-zinc-500">Katkı payının sana kalan kısmı</div>
                            </div>
                            <div class="px-2 py-1 rounded-md bg-brand/15 text-brand text-xs font-bold">Komisyonsuz</div>
                        </div>

                        <div class="display-font text-7xl md:text-8xl text-white mb-1 tabular-nums">
                            %100
                        </div>
                        <div class="text-sm text-zinc-500 mb-6">Yolcunun katkı payını platform kesmez</div>

                        <div class="space-y-3 mb-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-400">Platform komisyonu</span>
                                <span class="font-bold text-brand">₺0</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-400">Katkı payını kim öder</span>
                                <span class="font-semibold text-white">Doğrudan yolcu</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-400">Platforma erişim</span>
                                <span class="font-semibold text-white">Sabit üyelik</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-5 mt-4 border-t border-white/5">
                            <div>
                                <div class="text-xs text-zinc-500 mb-1">Çalışma şekli</div>
                                <div class="text-lg font-bold text-brand">Bağımsız</div>
                            </div>
                            <div>
                                <div class="text-xs text-zinc-500 mb-1">Saatler</div>
                                <div class="text-lg font-bold text-white">Sana bağlı</div>
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
                    ['200+', 'Aktif üye sürücü'],
                    ['₺0', 'Platform komisyonu'],
                    ['%100', 'Katkı payı sana'],
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
                    <span class="text-zinc-500">paylaşım,</span> esneklik, <span class="text-zinc-500">saygı.</span>
                </h2>
                <p class="text-lg text-zinc-400 leading-relaxed">
                    Üye sürücü olmak bir mecburiyet değil, bir tercih olmalı. Biz koşulları öyle kuruyoruz ki sen sadece yola ve yol arkadaşına odaklan.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">

                {{-- Big card --}}
                <div class="bento-card md:col-span-2 md:row-span-2 rounded-3xl p-8 md:p-10 border border-white/5 relative overflow-hidden">
                    <div class="absolute top-8 right-8 text-7xl opacity-10">🤝</div>
                    <div class="relative">
                        <div class="text-xs uppercase tracking-[0.2em] text-brand mb-4">01 · Katkı payı</div>
                        <h3 class="display-font text-3xl md:text-5xl text-white mb-4">Komisyon yok, katkı payı senin</h3>
                        <p class="text-zinc-400 leading-relaxed mb-6 max-w-md">
                            Yolcunun ödediği yol katkı payının <strong>tamamı sana</strong> kalır — para doğrudan yolcudan sana geçer, FerXGo aradan komisyon almaz. Platforma yalnızca sabit, dönemsel bir üyelik bedeliyle erişirsin.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 rounded-full bg-brand/10 border border-brand/25 text-brand text-xs font-semibold">%100 katkı payı</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300 text-xs font-semibold">Komisyon ₺0</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-zinc-300 text-xs font-semibold">Sabit üyelik</span>
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
                    <div class="text-xs uppercase tracking-[0.2em] text-brand mb-3">03 · Doğrudan katkı</div>
                    <h3 class="text-xl font-bold text-white mb-2">Para aradan geçmez</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">Yol katkı payı her yolculukta doğrudan yolcudan sana ulaşır. Platform tahsilat yapmaz, kesinti almaz.</p>
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
                        ['02', 'Belge yükle', 'Sürücü belgesi, kimlik, araç ruhsatı. Hepsi panelden.', '📋'],
                        ['03', 'Aracını tanıt', 'Kendi konforlu aracını sisteme ekle, fotoğraf yükle.', '🚗'],
                        ['04', 'Yola çık', 'Onayını al, çevrimiçi ol, ilk yol arkadaşını kabul et.', '🛣'],
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
                        'Geçerli B sınıfı sürücü belgesi',
                        'Araç ruhsatı ve zorunlu trafik sigortası güncel',
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
    {{-- ============ SSS (Soru & Cevap) ============ --}}
    <section id="sss" class="relative px-6 py-20 md:py-28">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-12">
                <div class="text-xs uppercase tracking-[0.3em] text-brand mb-4">Soru & Cevap</div>
                <h2 class="display-font text-4xl md:text-6xl text-white mb-5">Aklına takılanlar</h2>
                <p class="text-lg text-zinc-400">Model, katkı payı ve sorumluluklar hakkında en net haliyle.</p>
            </div>

            <div class="space-y-3">
                @foreach([
                    [
                        'FerXGo ile taksi ya da ticari taşımacılık mı yapıyorum?',
                        'Hayır. FerXGo bir dijital eşleştirme platformudur; taşımacılık şirketi değildir. Aynı güzergaha giden yolcularla buluşur, yolun masraflarını paylaşırsınız. Kâr amaçlı, ticari yolcu taşımacılığı yapmazsınız — bağımsız bir bireysiniz.',
                    ],
                    [
                        'Yolcudan aldığım para nedir? Ücret mi, kazanç mı?',
                        'Aldığınız tutar bir “ücret” ya da “gelir” değil, yol katkı payıdır: yakıt, amortisman gibi yol masraflarının yolcuyla paylaşılmasıdır. Katkı payı mesafeye göre belirlenir ve tamamı size kalır; FerXGo bu paydan komisyon almaz.',
                    ],
                    [
                        'FerXGo bana maaş veya ödeme yapıyor mu?',
                        'Hayır. FerXGo işvereniniz değildir ve size hiçbir ödeme yapmaz. Katkı payı doğrudan yolcu ile aranızda kalır; para platformdan geçmez. Aranızda bir iş sözleşmesi ya da bordro ilişkisi yoktur.',
                    ],
                    [
                        'Platforma nasıl erişiyorum? Komisyon var mı?',
                        'Platforma sabit, dönemsel bir üyelik bedeliyle erişirsiniz. Yolculuk başına komisyon veya kesinti alınmaz. Güncel üyelik koşulları başvurunuz onaylandıktan sonra sizinle paylaşılır.',
                    ],
                    [
                        'Ticari belge (SRC vb.) gerekli mi?',
                        'Model bireysel masraf paylaşımına dayanır; geçerli B sınıfı sürücü belgesi, güncel araç ruhsatı ve zorunlu trafik sigortası esas alınır. Yasal yükümlülükler mevzuata göre zamanla değişebilir — yürürlükteki kurallara uymak sürücünün sorumluluğundadır, FerXGo bu konuda bilgilendirme sağlar.',
                    ],
                    [
                        'Yasal ve mali sorumluluk kimde?',
                        'Bağımsız üye sürücü olarak kendi yasal ve mali yükümlülüklerinizden siz sorumlusunuz. FerXGo taraflar arasında yalnızca aracılık ve eşleştirme hizmeti sunar; yolculuğun tarafı değildir.',
                    ],
                    [
                        'Çalışma saatlerim belli mi? Vardiya var mı?',
                        'Hayır. Vardiya, hedef veya zorunlu mesai yoktur. İstediğiniz zaman çevrimiçi olur, uygun bir yol arkadaşını kabul eder, dilediğinizde kapatırsınız. Tamamen size bağlıdır.',
                    ],
                    [
                        'Güvenlik nasıl sağlanıyor?',
                        'Yolcular doğrulanır, yolculuklar kayıt altında tutulur ve 7/24 destek ekibi bulunur. Sürücü ile yolcu karşılıklı puanlanır; bu da güveni ve saygıyı korur.',
                    ],
                ] as $qa)
                    <details class="group rounded-2xl bg-white/[0.03] border border-white/8 hover:border-brand/25 transition overflow-hidden">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none p-5">
                            <span class="text-white font-semibold text-base md:text-lg">{{ $qa[0] }}</span>
                            <span class="shrink-0 w-7 h-7 rounded-full bg-brand/15 text-brand flex items-center justify-center transition-transform group-open:rotate-45">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </span>
                        </summary>
                        <div class="px-5 pb-5 -mt-1 text-sm md:text-base text-zinc-400 leading-relaxed">
                            {{ $qa[1] }}
                        </div>
                    </details>
                @endforeach
            </div>

            {{-- Model / hukuki netlik şeridi --}}
            <div class="mt-10 p-5 rounded-2xl bg-brand/[0.06] border border-brand/20 flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-brand/15 text-brand flex items-center justify-center shrink-0 mt-0.5">ℹ</div>
                <p class="text-xs md:text-sm text-zinc-400 leading-relaxed">
                    <strong class="text-zinc-200">Özet:</strong> FerXGo, bağımsız üye sürücüler ile yolcuları buluşturan bir dijital eşleştirme (aracılık) platformudur. Ticari taşımacılık hizmeti sunmaz, sürücülere ödeme yapmaz ve onları istihdam etmez. Yolculuk, aynı güzergahtaki yolcu ile sürücü arasında masraf paylaşımı esasıyla gerçekleşir. Sürücünün yasal ve mali yükümlülükleri kendisine aittir.
                </p>
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
                        <a href="https://wa.me/905412948144"
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
(function() {
    // ============================================================
    // 1) Kategoriye göre marka dropdown'ını doldur (AJAX)
    //    + Sarı Taksi / Motosiklet özel belge bölümlerini göster
    // ============================================================
    const makeSel   = document.getElementById('vehicle-make');
    const modelSel  = document.getElementById('vehicle-model');
    const taxiDocs  = document.getElementById('taxi-docs');
    const motorDocs = document.getElementById('motor-docs');

    async function loadMakes(categorySlug) {
        if (!makeSel) return;
        makeSel.innerHTML = '<option value="">Yükleniyor…</option>';
        modelSel.innerHTML = '<option value="">Önce marka seç</option>';
        modelSel.disabled = true;

        // Kategori-özel belge bölümleri
        if (taxiDocs)  taxiDocs.classList.toggle('hidden', categorySlug !== 'sari_taksi');
        if (motorDocs) motorDocs.classList.toggle('hidden', categorySlug !== 'motosiklet');

        try {
            const res  = await fetch(`{{ route('driver.catalog.makes') }}?category=${encodeURIComponent(categorySlug)}`);
            const data = await res.json();
            const makes = data.makes || [];
            makeSel.innerHTML = '<option value="">Seçiniz</option>' +
                makes.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
        } catch (e) {
            makeSel.innerHTML = '<option value="">Yüklenemedi, yenile</option>';
        }
    }

    async function loadModels(makeId, categorySlug) {
        if (!modelSel) return;
        modelSel.innerHTML = '<option value="">Yükleniyor…</option>';
        modelSel.disabled = true;

        try {
            const res  = await fetch(`{{ route('driver.catalog.models') }}?make=${encodeURIComponent(makeId)}&category=${encodeURIComponent(categorySlug)}`);
            const data = await res.json();
            const models = data.models || [];
            if (models.length === 0) {
                modelSel.innerHTML = '<option value="">Model bulunamadı</option>';
            } else {
                modelSel.innerHTML = '<option value="">Seçiniz</option>' +
                    models.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
                modelSel.disabled = false;
            }
        } catch (e) {
            modelSel.innerHTML = '<option value="">Yüklenemedi, yenile</option>';
        }
    }

    document.querySelectorAll('.category-radio').forEach(radio => {
        radio.addEventListener('change', () => {
            loadMakes(radio.dataset.slug);
        });
    });

    if (makeSel) {
        makeSel.addEventListener('change', () => {
            const checked = document.querySelector('.category-radio:checked');
            const catSlug = checked?.dataset?.slug;
            if (makeSel.value && catSlug) loadModels(makeSel.value, catSlug);
        });
    }

    // Sayfa açılırken bir kategori önceden seçiliyse (form hata sonrası) çalıştır
    const preChecked = document.querySelector('.category-radio:checked');
    if (preChecked) preChecked.dispatchEvent(new Event('change'));

    // ============================================================
    // 2) Dosya upload — Galeri seçilirse dosyayı ana input'a mirror et
    //    (form submit'te tek input adı gitsin)
    // ============================================================
    document.querySelectorAll('.fu-input').forEach(input => {
        input.addEventListener('change', () => {
            const name = input.dataset.target;
            let file = input.files?.[0];
            if (!file) return;

            // Galeri input'u seçildiyse dosyayı ana (kamera) input'a kopyala
            const mirrorId = input.dataset.mirror;
            if (mirrorId) {
                const target = document.getElementById(mirrorId);
                if (target) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    target.files = dt.files;
                }
            }

            const empty  = document.querySelector('.fu-empty-' + name);
            const prev   = document.querySelector('.fu-preview-' + name);
            const img    = document.getElementById('fu-img-' + name);
            const nameEl = document.getElementById('fu-name-' + name);
            if (!empty || !prev || !img || !nameEl) return;

            if (file.type.startsWith('image/')) {
                img.src = URL.createObjectURL(file);
                img.classList.remove('hidden');
            } else {
                img.classList.add('hidden');
            }
            nameEl.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
            empty.classList.add('hidden');
            prev.classList.remove('hidden');
        });
    });
})();
</script>
@endpush
