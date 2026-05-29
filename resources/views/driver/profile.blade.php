<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profil · Ferogo Sürücü</title>
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

    {{-- Top bar --}}
    <header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <a href="{{ route('driver.panel') }}" class="flex items-center gap-2 text-sm text-zinc-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Panele dön
            </a>
            <div class="text-sm font-bold text-brand">Profil Yönetimi</div>
            <form method="POST" action="{{ route('driver.logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-xs text-zinc-500 hover:text-red-400 transition">Çıkış</button>
            </form>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-6 space-y-6">

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

        <form method="POST" action="{{ route('driver.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
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
            <section class="bg-zinc-950 border border-white/10 rounded-3xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.25em] text-brand">Adım 2</div>
                        <h2 class="text-lg font-bold">Araç Bilgileri</h2>
                    </div>
                    @if ($vehicle->vehicleClass)
                        <span class="px-2.5 py-1 rounded-md bg-brand/15 text-brand text-[10px] font-bold uppercase tracking-wider">
                            {{ $vehicle->vehicleClass->name }}
                        </span>
                    @endif
                </div>

                <div class="p-6 space-y-5">
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
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-4">
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

                        <div id="new-photos-preview" class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-3"></div>

                        <label for="vehicle-photos-input" class="block w-full px-4 py-4 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] border border-dashed border-white/20 text-center cursor-pointer transition">
                            <div class="text-2xl mb-1">📷</div>
                            <div class="text-sm font-semibold">Fotoğraf Ekle</div>
                            <div class="text-[10px] text-zinc-500 mt-1">Birden fazla seçebilirsin · JPG/PNG · maks 4 MB</div>
                        </label>
                        <input id="vehicle-photos-input" type="file" name="vehicle_photos[]" accept="image/*" multiple class="hidden">
                    </div>
                </div>
            </section>
            @endif

            {{-- Submit --}}
            <div class="sticky bottom-0 bg-black/90 backdrop-blur-md border-t border-white/10 -mx-4 px-4 py-4">
                <button type="submit"
                        class="w-full px-6 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold transition shadow-xl shadow-brand/30">
                    Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </main>

    <script>
    (function() {
        // Avatar canlı önizleme
        const avatarInput   = document.getElementById('avatar-input');
        const avatarPreview = document.getElementById('avatar-preview');
        if (avatarInput) {
            avatarInput.addEventListener('change', () => {
                const file = avatarInput.files[0];
                if (file) avatarPreview.src = URL.createObjectURL(file);
            });
        }

        // Araç fotoğrafları canlı önizleme
        const photosInput   = document.getElementById('vehicle-photos-input');
        const photosPreview = document.getElementById('new-photos-preview');
        if (photosInput && photosPreview) {
            photosInput.addEventListener('change', () => {
                photosPreview.innerHTML = '';
                [...photosInput.files].forEach(file => {
                    const url = URL.createObjectURL(file);
                    photosPreview.insertAdjacentHTML('beforeend', `
                        <div class="relative aspect-square rounded-xl overflow-hidden border border-brand/40 bg-zinc-900">
                            <img src="${url}" alt="" class="w-full h-full object-cover">
                            <span class="absolute top-1 right-1 px-1.5 py-0.5 rounded bg-brand text-black text-[9px] font-bold uppercase tracking-wider">Yeni</span>
                        </div>
                    `);
                });
            });
        }
    })();
    </script>
</body>
</html>
