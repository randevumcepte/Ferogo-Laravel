<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profil · FerXGo Sürücü</title>
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

    {{-- Top bar --}}
    <header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
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

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('driver.panel') }}"
                   class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">
                    ← Panele dön
                </a>
                <form method="POST" action="{{ route('driver.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">Çıkış</button>
                </form>
                <div class="relative w-10 h-10 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-sm overflow-hidden border-2 border-brand/40 shadow-lg shadow-brand/20">
                    @if ($navAvatarUrl)
                        <img src="{{ $navAvatarUrl }}" alt="" class="w-full h-full object-cover">
                    @else
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    @endif
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-6">

        @if (session('success'))
            <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-300 flex items-center gap-2">
                ✓ {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-sm text-red-300">
                <ul class="space-y-1 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="profile-form" method="POST" action="{{ route('driver.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- ===== Profil Bilgileri ===== --}}
            <section class="bg-zinc-950 border border-white/10 rounded-3xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.25em] text-brand">Adım 1</div>
                        <h2 class="text-lg font-bold">Kişisel Bilgiler</h2>
                    </div>
                </div>

                <div class="p-6 space-y-5">
                    {{-- Avatar --}}
                    <div class="flex items-center gap-4">
                        @php
                            $avatarUrl = $user->avatar
                                ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . $user->avatar))
                                : 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=F0C040&color=000&size=200&bold=true';
                        @endphp
                        <img id="avatar-preview" src="{{ $avatarUrl }}" alt=""
                             class="w-24 h-24 rounded-2xl border-2 border-brand/40 object-cover bg-zinc-900 shrink-0" />
                        <div class="flex-1">
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Profil Fotoğrafı</label>
                            <label for="avatar-input" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.10] border border-white/10 text-sm font-semibold cursor-pointer transition">
                                📷 Fotoğraf Seç
                            </label>
                            <input id="avatar-input" type="file" name="avatar" accept="image/*" class="hidden">
                            <div class="text-[10px] text-zinc-500 mt-1.5">JPG/PNG, maks 4 MB</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Ad Soyad</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120"
                                   class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Telefon</label>
                            <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" required maxlength="20"
                                   class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition">
                        </div>
                    </div>
                </div>
            </section>

            {{-- ===== Araç Bilgileri ===== --}}
            @if ($vehicle)
            @if (isset($pendingVehicleRequest) && $pendingVehicleRequest)
                <div class="p-4 rounded-2xl bg-yellow-500/10 border border-yellow-500/30 text-sm text-yellow-200 flex items-start gap-3">
                    <span class="text-2xl">🕒</span>
                    <div>
                        <div class="font-bold">Araç değişikliği onay bekliyor</div>
                        <div class="text-xs text-yellow-300/80 mt-1">
                            {{ $pendingVehicleRequest->created_at->diffForHumans() }} talep edildi. Süper admin onayladığında değişiklikler müşterilere yansır.
                            Bu sırada eski araç bilgilerin canlıda görünmeye devam eder.
                        </div>
                    </div>
                </div>
            @endif
            <section class="bg-zinc-950 border border-white/10 rounded-3xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.25em] text-brand">Adım 2</div>
                        <h2 class="text-lg font-bold">Araç Bilgileri</h2>
                        <p class="text-xs text-zinc-500 mt-1">Değişiklikler admin onayından sonra yayına girer.</p>
                    </div>
                    @if ($vehicle->vehicleClass)
                        <span class="px-2.5 py-1 rounded-md bg-brand/15 text-brand text-[10px] font-bold uppercase tracking-wider">
                            {{ $vehicle->vehicleClass->name }}
                        </span>
                    @endif
                </div>

                <div class="p-6 space-y-5">
                    {{-- Araç sınıfı seçimi: Easy / Platinum / VIP --}}
                    @if (isset($vehicleClasses) && $vehicleClasses->count() > 0)
                    @php
                        $classMeta = [
                            'easy' => [
                                'accent'   => 'from-zinc-700 to-zinc-900 text-white',
                                'border'   => 'border-white/20',
                                'icon'     => '🚗',
                                'tagline'  => 'Ekonomik standart',
                                'detail'   => 'Standart sedan veya MPV (Passat, Vito, Talisman vb.). Şehir içi ve havalimanı yolculukları için ekonomik seçenek. Klimalı, temiz, konforlu.',
                                'examples' => 'Volkswagen Passat · Mercedes Vito · Renault Talisman · Skoda Superb',
                            ],
                            'platinum' => [
                                'accent'   => 'from-zinc-200 to-zinc-400 text-zinc-900',
                                'border'   => 'border-zinc-300/40',
                                'icon'     => '👔',
                                'tagline'  => 'Lüks sedan · iş seyahati',
                                'detail'   => 'Üst segment iş sınıfı sedan. İş yolculukları, VIP misafir karşılama, kurumsal yolculuklar için ideal. Üst konfor + sessiz iç mekan.',
                                'examples' => 'Mercedes E-Class · Audi A6 · BMW 5 Series',
                            ],
                            'vip' => [
                                'accent'   => 'from-brand to-brand-600 text-black',
                                'border'   => 'border-brand/60',
                                'icon'     => '👑',
                                'tagline'  => 'Premium · protokol',
                                'detail'   => 'En üst sınıf lüks sedan/limuzin. Protokol görevleri, özel etkinlikler, üst düzey misafir ağırlama. Şık giyimli üye sürücü, üst sınıf içecek servisi.',
                                'examples' => 'Mercedes S-Class · BMW 7 Series · Audi A8 · Maybach',
                            ],
                        ];
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500">Araç Sınıfı</label>
                            <span class="text-[10px] text-zinc-500">Müşteriler bu sınıfa göre seçim yapar</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-{{ min($vehicleClasses->count(), 3) }} gap-3">
                            @foreach ($vehicleClasses as $vc)
                                @php
                                    $selected = (int) old('vehicle_class_id', $vehicle->vehicle_class_id) === (int) $vc->id;
                                    $meta = $classMeta[$vc->slug] ?? $classMeta['easy'];
                                @endphp
                                <div class="vehicle-class-option" data-slug="{{ $vc->slug }}">
                                    <input type="radio"
                                           id="vc-{{ $vc->id }}"
                                           name="vehicle_class_id"
                                           value="{{ $vc->id }}"
                                           {{ $selected ? 'checked' : '' }}
                                           class="peer sr-only">
                                    <label for="vc-{{ $vc->id }}"
                                           class="block cursor-pointer rounded-2xl border-2 {{ $meta['border'] }} bg-gradient-to-br {{ $meta['accent'] }} p-4 opacity-50 hover:opacity-80 transition peer-checked:opacity-100 peer-checked:ring-4 peer-checked:ring-brand/60 peer-checked:scale-[1.02]">
                                        <div class="flex items-start justify-between gap-2 mb-2">
                                            <div class="text-2xl">{{ $meta['icon'] }}</div>
                                            <button type="button"
                                                    class="vc-info-btn w-6 h-6 rounded-full bg-black/20 hover:bg-black/40 text-current flex items-center justify-center text-xs font-bold transition shrink-0"
                                                    data-slug="{{ $vc->slug }}"
                                                    aria-label="Bilgi"
                                                    title="Detay">
                                                i
                                            </button>
                                        </div>
                                        <div class="text-base font-extrabold tracking-tight">{{ $vc->name }}</div>
                                        <div class="text-[11px] opacity-80 mt-0.5">{{ $meta['tagline'] }}</div>
                                    </label>
                                    <div class="vc-info-panel hidden mt-2 p-3 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-zinc-300 leading-relaxed" data-slug="{{ $vc->slug }}">
                                        <div class="mb-2">{{ $meta['detail'] }}</div>
                                        <div class="text-[10px] text-zinc-500 uppercase tracking-wider mb-1">Örnek modeller</div>
                                        <div class="text-[11px] text-zinc-400">{{ $meta['examples'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Marka</label>
                            <input type="text" name="vehicle_brand" value="{{ old('vehicle_brand', $vehicle->brand) }}" maxlength="60"
                                   class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Model</label>
                            <input type="text" name="vehicle_model" value="{{ old('vehicle_model', $vehicle->model) }}" maxlength="60"
                                   class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Yıl</label>
                            <input type="number" name="vehicle_year" value="{{ old('vehicle_year', $vehicle->year_of_manufacture) }}" min="1990" max="2030"
                                   class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Renk</label>
                            <input type="text" name="vehicle_color" value="{{ old('vehicle_color', $vehicle->color) }}" maxlength="30"
                                   class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Plaka</label>
                            <input type="text" name="vehicle_plate" value="{{ old('vehicle_plate', $vehicle->plate) }}" maxlength="15"
                                   class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none transition uppercase">
                        </div>
                    </div>

                    {{-- Mevcut araç fotoğrafları --}}
                    @php $existingPhotos = is_array($vehicle->photos) ? $vehicle->photos : []; @endphp
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-[10px] uppercase tracking-[0.2em] text-zinc-500">Araç Fotoğrafları</label>
                            <span class="text-[10px] text-zinc-500">{{ count($existingPhotos) }} / 20</span>
                        </div>

                        @if (count($existingPhotos) > 0)
                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3 mb-4">
                                @foreach ($existingPhotos as $photoPath)
                                    @php
                                        $photoUrl = str_starts_with($photoPath, 'http') ? $photoPath : asset('storage/' . $photoPath);
                                    @endphp
                                    <div class="relative group aspect-square rounded-xl overflow-hidden border border-white/10 bg-zinc-900">
                                        <img src="{{ $photoUrl }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                        <label class="absolute inset-0 bg-red-500/0 hover:bg-red-500/70 transition flex items-center justify-center cursor-pointer">
                                            <input type="checkbox" name="remove_photos[]" value="{{ $photoPath }}" class="peer sr-only">
                                            <span class="opacity-0 group-hover:opacity-100 peer-checked:opacity-100 text-white text-xs font-bold transition">✕ Sil</span>
                                            <span class="hidden peer-checked:flex absolute inset-0 bg-red-500/80 items-center justify-center text-white text-xs font-bold">Silinecek</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div id="new-photos-preview" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3 mb-3"></div>
                        <div id="upload-status" class="hidden mb-3 text-xs text-zinc-400"></div>

                        <label for="vehicle-photos-input" class="block w-full px-4 py-4 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] border border-dashed border-white/20 text-center cursor-pointer transition">
                            <div class="text-2xl mb-1">📷</div>
                            <div class="text-sm font-semibold">Fotoğraf Ekle</div>
                            <div class="text-[10px] text-zinc-500 mt-1">Birden fazla seçebilirsin · JPG/PNG · otomatik sıkıştırılır</div>
                        </label>
                        <input id="vehicle-photos-input" type="file" accept="image/*" multiple class="hidden">
                        {{-- AJAX upload sonrası path'leri buraya yazılır, form ile birlikte gider --}}
                        <div id="new-photo-paths-container"></div>
                    </div>
                </div>
            </section>
            @endif

            {{-- ===== Belgeler ===== --}}
            @php
                $documents = [
                    ['type' => 'license',         'label' => 'Ehliyet',         'icon' => '🪪', 'has_expiry' => true,  'expires_at' => $driver->license_expires_at,    'expires_label' => 'Geçerlilik bitişi', 'file_path' => $driver->license_file_path,         'approved_at' => $driver->license_approved_at],
                    ['type' => 'src',             'label' => 'SRC Sertifikası', 'icon' => '📜', 'has_expiry' => true,  'expires_at' => $driver->src_expires_at,        'expires_label' => 'Geçerlilik bitişi', 'file_path' => $driver->src_file_path,             'approved_at' => $driver->src_approved_at],
                    ['type' => 'psychotechnic',   'label' => 'Psikoteknik',     'icon' => '🧠', 'has_expiry' => true,  'expires_at' => $driver->psychotechnic_test_at, 'expires_label' => 'Test tarihi',        'file_path' => $driver->psychotechnic_file_path,    'approved_at' => $driver->psychotechnic_approved_at],
                    ['type' => 'criminal_record', 'label' => 'Adli Sicil',      'icon' => '🛡', 'has_expiry' => true,  'expires_at' => $driver->criminal_record_at,    'expires_label' => 'Belge tarihi',       'file_path' => $driver->criminal_record_file_path,  'approved_at' => $driver->criminal_record_approved_at],
                    ['type' => 'insurance',       'label' => 'Sigorta',         'icon' => '🧾', 'has_expiry' => true,  'expires_at' => $driver->insurance_expires_at,  'expires_label' => 'Bitiş tarihi',       'file_path' => $driver->insurance_file_path,        'approved_at' => $driver->insurance_approved_at],
                    ['type' => 'inspection',      'label' => 'Muayene',         'icon' => '🔧', 'has_expiry' => true,  'expires_at' => $driver->inspection_expires_at, 'expires_label' => 'Bitiş tarihi',       'file_path' => $driver->inspection_file_path,       'approved_at' => $driver->inspection_approved_at],
                ];
            @endphp

            <section class="bg-zinc-950 border border-white/10 rounded-3xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/10">
                    <div class="text-[10px] uppercase tracking-[0.25em] text-brand">Adım 3</div>
                    <h2 class="text-lg font-bold">Resmi Belgeler</h2>
                    <p class="text-xs text-zinc-500 mt-1">PDF veya fotoğraf yükle. Her belge ayrı kaydedilir — sayfa altındaki kaydet butonuna basmana gerek yok.</p>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($documents as $doc)
                        @php
                            $hasFile     = ! empty($doc['file_path']);
                            $isExpired   = $doc['expires_at'] && $doc['expires_at']->isPast();
                            $isApproved  = $hasFile && ! empty($doc['approved_at']);
                            $isPending   = $hasFile && empty($doc['approved_at']);
                            $fileUrl     = $hasFile ? (str_starts_with($doc['file_path'], 'http') ? $doc['file_path'] : asset('storage/' . $doc['file_path'])) : null;
                            $borderClass = $isExpired      ? 'border-red-500/30 bg-red-500/5'
                                         : ($isApproved   ? 'border-emerald-500/30 bg-emerald-500/5'
                                         : ($isPending    ? 'border-yellow-500/30 bg-yellow-500/5'
                                                          : 'border-white/10 bg-white/[0.02]'));
                        @endphp
                        <div class="document-card rounded-2xl border {{ $borderClass }} p-4"
                             data-doc-type="{{ $doc['type'] }}">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="text-2xl">{{ $doc['icon'] }}</div>
                                    <div>
                                        <div class="text-sm font-bold text-white">{{ $doc['label'] }}</div>
                                        <div class="text-[10px] uppercase tracking-wider mt-0.5
                                            @if($isExpired) text-red-400
                                            @elseif($isApproved) text-emerald-400
                                            @elseif($isPending) text-yellow-400
                                            @else text-zinc-500
                                            @endif">
                                            @if($isExpired) ⚠ Süresi dolmuş
                                            @elseif($isApproved) ✓ Onaylı
                                            @elseif($isPending) 🕒 Onay bekliyor
                                            @else Yüklenmemiş
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if($hasFile)
                                    <a href="{{ $fileUrl }}" target="_blank" class="text-[10px] text-brand hover:underline">Aç ↗</a>
                                @endif
                            </div>

                            @if($doc['has_expiry'])
                                <div class="mb-3">
                                    <label class="block text-[9px] uppercase tracking-wider text-zinc-500 mb-1">{{ $doc['expires_label'] }}</label>
                                    <input type="date" name="doc_expires_{{ $doc['type'] }}"
                                           value="{{ $doc['expires_at']?->format('Y-m-d') }}"
                                           class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none">
                                </div>
                            @endif

                            <div class="flex items-center gap-2">
                                <label class="flex-1 px-3 py-2 rounded-lg bg-white/[0.06] hover:bg-white/[0.10] border border-white/10 text-center text-xs font-semibold cursor-pointer transition">
                                    📎 <span>{{ $hasFile ? 'Değiştir' : 'Yükle' }}</span>
                                    <input type="file" accept=".pdf,image/*" class="hidden doc-file-input" data-doc-type="{{ $doc['type'] }}">
                                </label>
                                @if($hasFile)
                                    <button type="button" class="px-3 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-300 text-xs font-semibold transition doc-delete-btn"
                                            data-doc-type="{{ $doc['type'] }}">
                                        ✕
                                    </button>
                                @endif
                            </div>

                            <div class="doc-status hidden mt-2 text-[10px]"></div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Submit --}}
            <div class="bg-zinc-950 border border-white/10 rounded-3xl p-5">
                <button type="submit"
                        class="w-full px-6 py-4 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-base transition shadow-xl shadow-brand/30">
                    💾 Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </main>

    <script>
    (function() {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const UPLOAD_URL    = '{{ route('driver.api.vehicle_photo') }}';
        const DOC_UPLOAD_URL = '{{ route('driver.api.document.upload') }}';
        const DOC_DELETE_URL = '{{ route('driver.api.document.delete') }}';

        // ===== Araç sınıfı (i) bilgi paneli toggle =====
        document.querySelectorAll('.vc-info-btn').forEach(btn => {
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                const slug = btn.dataset.slug;
                const panel = document.querySelector(`.vc-info-panel[data-slug="${slug}"]`);
                if (panel) panel.classList.toggle('hidden');
            });
        });

        // ===== Avatar canlı önizleme =====
        const avatarInput   = document.getElementById('avatar-input');
        const avatarPreview = document.getElementById('avatar-preview');
        if (avatarInput) {
            avatarInput.addEventListener('change', () => {
                const file = avatarInput.files[0];
                if (file) avatarPreview.src = URL.createObjectURL(file);
            });
        }

        // ===== Araç fotoğraf upload (resize + ayrı ayrı AJAX) =====
        const photosInput     = document.getElementById('vehicle-photos-input');
        const photosPreview   = document.getElementById('new-photos-preview');
        const pathsContainer  = document.getElementById('new-photo-paths-container');
        const statusEl        = document.getElementById('upload-status');

        // Canvas ile resize — uzun kenar 1600px, JPEG quality 0.85 → ~300-700KB
        async function resizeImage(file) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const MAX = 1600;
                    let { width, height } = img;
                    if (width > MAX || height > MAX) {
                        if (width > height) { height = Math.round(height * MAX / width); width = MAX; }
                        else                { width  = Math.round(width  * MAX / height); height = MAX; }
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width; canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    canvas.toBlob(blob => {
                        if (!blob) return reject(new Error('canvas toBlob failed'));
                        resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }));
                    }, 'image/jpeg', 0.85);
                };
                img.onerror = () => reject(new Error('image load failed'));
                img.src = URL.createObjectURL(file);
            });
        }

        async function uploadOne(file, previewEl) {
            const fd = new FormData();
            fd.append('photo', file);
            const res = await fetch(UPLOAD_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            if (!res.ok) {
                const text = await res.text().catch(() => '');
                throw new Error('Upload failed: ' + res.status + ' ' + text.slice(0, 200));
            }
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Yükleme başarısız.');
            // Hidden input ekle ki form submit'inde gönderilsin
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'new_photo_paths[]';
            input.value = data.path;
            input.dataset.previewId = previewEl.id;
            pathsContainer.appendChild(input);
            // Önizleme rozetini ✓ yap
            previewEl.querySelector('.upload-badge').innerHTML = '✓';
            previewEl.querySelector('.upload-badge').classList.remove('bg-zinc-700', 'text-zinc-300');
            previewEl.querySelector('.upload-badge').classList.add('bg-emerald-500', 'text-white');
        }

        if (photosInput && photosPreview) {
            photosInput.addEventListener('change', async () => {
                const files = [...photosInput.files];
                if (files.length === 0) return;
                statusEl.classList.remove('hidden');
                statusEl.textContent = `${files.length} fotoğraf yükleniyor…`;
                let done = 0, failed = 0;
                for (const file of files) {
                    // Preview ekle (yükleniyor durumunda)
                    const previewId = 'preview-' + Date.now() + '-' + Math.random().toString(36).slice(2,7);
                    const url = URL.createObjectURL(file);
                    photosPreview.insertAdjacentHTML('beforeend', `
                        <div id="${previewId}" class="relative aspect-square rounded-xl overflow-hidden border border-brand/40 bg-zinc-900">
                            <img src="${url}" alt="" class="w-full h-full object-cover">
                            <span class="upload-badge absolute top-1 right-1 w-5 h-5 rounded-full bg-zinc-700 text-zinc-300 flex items-center justify-center text-[10px] font-bold">⟳</span>
                        </div>
                    `);
                    const previewEl = document.getElementById(previewId);
                    try {
                        const resized = await resizeImage(file);
                        await uploadOne(resized, previewEl);
                        done++;
                    } catch (err) {
                        console.error('upload failed', file.name, err);
                        failed++;
                        previewEl.querySelector('.upload-badge').innerHTML = '✕';
                        previewEl.querySelector('.upload-badge').classList.remove('bg-zinc-700', 'text-zinc-300');
                        previewEl.querySelector('.upload-badge').classList.add('bg-red-500', 'text-white');
                        previewEl.title = err.message;
                    }
                    statusEl.textContent = `${done + failed} / ${files.length} işlendi${failed > 0 ? ` (${failed} başarısız)` : ''}`;
                }
                statusEl.textContent = failed === 0
                    ? `✓ ${done} fotoğraf yüklendi. "Değişiklikleri Kaydet" basmayı unutma.`
                    : `${done} yüklendi, ${failed} başarısız. Tekrar dene.`;
                statusEl.classList.toggle('text-emerald-400', failed === 0 && done > 0);
                statusEl.classList.toggle('text-red-400', failed > 0);
                photosInput.value = ''; // sonraki seçim için reset
            });
        }

        // ===== Belge yükleme (AJAX, anında kaydedilir) =====
        document.querySelectorAll('.doc-file-input').forEach(input => {
            input.addEventListener('change', async () => {
                const file = input.files[0];
                if (!file) return;
                const type = input.dataset.docType;
                const card = input.closest('.document-card');
                const statusEl = card.querySelector('.doc-status');
                const expiresInput = card.querySelector(`input[name="doc_expires_${type}"]`);

                statusEl.textContent = 'Yükleniyor…';
                statusEl.classList.remove('hidden', 'text-emerald-400', 'text-red-400');
                statusEl.classList.add('text-zinc-400');

                const fd = new FormData();
                fd.append('type', type);
                fd.append('file', file);
                if (expiresInput && expiresInput.value) fd.append('expires', expiresInput.value);

                try {
                    const res = await fetch(DOC_UPLOAD_URL, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: fd,
                    });
                    if (!res.ok) {
                        const txt = await res.text().catch(() => '');
                        throw new Error('HTTP ' + res.status + ' ' + txt.slice(0, 200));
                    }
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Yükleme başarısız.');

                    statusEl.textContent = '✓ Yüklendi. Sayfa yenileniyor…';
                    statusEl.classList.remove('text-zinc-400');
                    statusEl.classList.add('text-emerald-400');
                    setTimeout(() => location.reload(), 600);
                } catch (err) {
                    console.error('document upload failed', type, err);
                    statusEl.textContent = '✕ ' + (err.message || 'Hata');
                    statusEl.classList.remove('text-zinc-400');
                    statusEl.classList.add('text-red-400');
                }
                input.value = '';
            });
        });

        // ===== Belge silme =====
        document.querySelectorAll('.doc-delete-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Bu belgeyi silmek istediğine emin misin?')) return;
                const type = btn.dataset.docType;
                try {
                    const fd = new FormData();
                    fd.append('type', type);
                    const res = await fetch(DOC_DELETE_URL, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: fd,
                    });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Silinemedi.');
                    location.reload();
                } catch (err) {
                    alert('Hata: ' + (err.message || 'Bilinmeyen'));
                }
            });
        });
    })();
    </script>

    @include('partials.mobile-action-bar')
</body>
</html>
