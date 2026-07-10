<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Doğrulama · FerXGo Sürücü</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { brand: { DEFAULT: '#F0C040', 500: '#F0C040', 600: '#D9A621' } } } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        /* Dark tema select fix — açılan option'lar okunmuyordu */
        select { color-scheme: dark; color: #fff; background-color: #0a0a0a; }
        select option, select optgroup { background-color: #0f0f0f !important; color: #fff !important; padding: 8px 12px; }
        select option:checked, select option:hover { background-color: #F0C040 !important; color: #000 !important; }
        select option[disabled] { color: #666 !important; }
        select:disabled { opacity: 0.5; cursor: not-allowed; }
        select option[value=""] { color: #71717a; }
    </style>
</head>
<body class="bg-black text-white min-h-screen pb-24">

    @php
        $ob = $onboarding;
        $angles = [
            'left' => 'Sol yan', 'front' => 'Ön', 'right' => 'Sağ yan',
            'back' => 'Arka', 'interior_front' => 'İç ön', 'interior_back' => 'İç arka',
        ];
        $savedAngles = ($vehicle && is_array($vehicle->photo_angles)) ? $vehicle->photo_angles : [];
        $docs = [
            ['type' => 'license',         'label' => 'Ehliyet',      'file' => $driver->license_file_path],
            ['type' => 'selfie',          'label' => 'Selfie',       'file' => $driver->selfie_file_path],
            ['type' => 'src',             'label' => 'SRC Belgesi',  'file' => $driver->src_file_path],
            ['type' => 'criminal_record', 'label' => 'Adli Sicil',   'file' => $driver->criminal_record_file_path],
            ['type' => 'psychotechnic',   'label' => 'Psikoteknik',  'file' => $driver->psychotechnic_file_path],
            ['type' => 'registration',    'label' => 'Ruhsat',       'file' => $vehicle?->registration_file_path],
            ['type' => 'insurance',       'label' => 'Sigorta',      'file' => $driver->insurance_file_path],
            ['type' => 'inspection',      'label' => 'Muayene',      'file' => $driver->inspection_file_path],
        ];
        // step key → complete map (JS ile güncellenecek)
        $stepDone = collect($ob['steps'])->mapWithKeys(fn ($s) => [$s['key'] => $s['complete']]);
    @endphp

    {{-- Top bar --}}
    <header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
            <span class="text-2xl font-extrabold tracking-tight">
                <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
            </span>
            <form method="POST" action="{{ route('driver.logout') }}">
                @csrf
                <button type="submit" class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">Çıkış</button>
            </form>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-6 space-y-5">

        <div>
            <div class="text-[10px] uppercase tracking-[0.3em] text-brand mb-1">Doğrulama Durumu</div>
            <h1 class="text-2xl font-extrabold">Merhaba {{ $driver->user->name }} 👋</h1>
            <p class="text-sm text-zinc-400 mt-1">Ön kaydın tamamlandı. Başvurunu tamamlamak için istenen bilgi ve belgeleri yükle.</p>
        </div>

        {{-- İlerleme --}}
        <div class="bg-zinc-950 border border-white/10 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-zinc-400">Tamamlanma</span>
                <span class="text-xs font-bold text-brand"><span id="ob-completed">{{ $ob['completed'] }}</span>/<span id="ob-total">{{ $ob['total'] }}</span> · <span id="ob-percent">{{ $ob['percent'] }}</span>%</span>
            </div>
            <div class="w-full h-2 rounded-full bg-white/10 overflow-hidden">
                <div id="ob-bar" class="h-full bg-brand transition-all" style="width: {{ $ob['percent'] }}%"></div>
            </div>
        </div>

        {{-- Durum bandı --}}
        <div id="ob-status-banner"></div>

        {{-- 1) Kişisel --}}
        <details class="ob-step bg-zinc-950 border border-white/10 rounded-2xl overflow-hidden" data-step-key="personal">
            <summary class="px-5 py-4 flex items-center justify-between cursor-pointer">
                <span class="flex items-center gap-3"><span class="step-dot"></span><span class="font-semibold">Kişisel Bilgiler</span></span>
                <span class="text-zinc-500 text-sm">Ön kayıttan alındı</span>
            </summary>
            <div class="px-5 pb-5 text-sm text-zinc-400 space-y-1 border-t border-white/5 pt-4">
                <div><span class="text-zinc-500">Ad Soyad:</span> {{ $driver->user->name }}</div>
                <div><span class="text-zinc-500">Telefon:</span> {{ $driver->user->phone }}</div>
                <div><span class="text-zinc-500">E-posta:</span> {{ $driver->user->email }}</div>
                <p class="text-xs text-zinc-600 pt-2">Değişiklik için destek ile iletişime geç.</p>
            </div>
        </details>

        {{-- 2) Araç Bilgileri --}}
        <details class="ob-step bg-zinc-950 border border-white/10 rounded-2xl overflow-hidden" data-step-key="vehicle_info" {{ $stepDone['vehicle_info'] ? '' : 'open' }}>
            <summary class="px-5 py-4 flex items-center justify-between cursor-pointer">
                <span class="flex items-center gap-3"><span class="step-dot"></span><span class="font-semibold">Araç Bilgileri</span></span>
                <span class="text-zinc-600 text-xs">Marka/model seçmeli</span>
            </summary>
            <form id="vehicle-form" class="px-5 pb-5 border-t border-white/5 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Araç Tipi</label>
                    <select name="vehicle_type" required class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-brand/40">
                        @foreach (['Otomobil','Station Wagon','SUV','Minivan (MPV)','Panelvan / Ticari','Minibüs'] as $t)
                            <option value="{{ $t }}" {{ optional($vehicle)->vehicle_type === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Marka</label>
                    <select name="vehicle_make_id" id="make-select" required class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-brand/40">
                        <option value="">Seçiniz</option>
                        @foreach ($makes as $m)
                            <option value="{{ $m->id }}" {{ optional($vehicle)->vehicle_make_id == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Model</label>
                    <select name="vehicle_model_id" id="model-select" required data-selected="{{ optional($vehicle)->vehicle_model_id }}" class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-brand/40">
                        <option value="">Önce marka seç</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Yıl</label>
                    <select name="year" required class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-brand/40">
                        <option value="">Seçiniz</option>
                        @for ($y = (int) date('Y') + 1; $y >= 1990; $y--)
                            <option value="{{ $y }}" {{ optional($vehicle)->year_of_manufacture == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Renk</label>
                    <input type="text" name="color" maxlength="30" required value="{{ optional($vehicle)->color }}" class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-brand/40">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Plaka</label>
                    <input type="text" name="plate" maxlength="20" required value="{{ optional($vehicle)->plate }}" placeholder="35 ABC 123" class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white uppercase focus:outline-none focus:border-brand/40">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Araç Sınıfı (önerin — admin onaylar)</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($vehicleClasses as $vc)
                            <label class="cursor-pointer">
                                <input type="radio" name="vehicle_class_id" value="{{ $vc->id }}" class="peer sr-only" {{ optional($vehicle)->vehicle_class_id == $vc->id ? 'checked' : '' }} required>
                                <div class="text-center py-2.5 rounded-xl border border-white/10 text-xs font-bold text-zinc-300 peer-checked:border-brand peer-checked:bg-brand/10 peer-checked:text-brand transition">{{ $vc->name }}</div>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="w-full py-3 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold text-sm transition">Araç Bilgilerini Kaydet</button>
                </div>
            </form>
        </details>

        {{-- 3) Araç Fotoğrafları --}}
        <details class="ob-step bg-zinc-950 border border-white/10 rounded-2xl overflow-hidden" data-step-key="vehicle_photos">
            <summary class="px-5 py-4 flex items-center justify-between cursor-pointer">
                <span class="flex items-center gap-3"><span class="step-dot"></span><span class="font-semibold">Araç Fotoğrafları</span></span>
                <span class="text-zinc-600 text-xs">6 açı</span>
            </summary>
            <div class="px-5 pb-5 border-t border-white/5 pt-4 space-y-3">
                @if (! $vehicle)
                    <p class="text-xs text-amber-300/80">Önce araç bilgilerini kaydet, sonra fotoğrafları yükleyebilirsin.</p>
                @endif
                @foreach ($angles as $key => $label)
                    <div class="flex items-center justify-between gap-3 photo-row" data-angle="{{ $key }}">
                        <span class="text-sm text-zinc-300">{{ $label }} fotoğrafı</span>
                        <div class="flex items-center gap-2">
                            <img class="photo-thumb w-12 h-12 rounded-lg object-cover border border-white/10 {{ empty($savedAngles[$key]) ? 'hidden' : '' }}" src="{{ empty($savedAngles[$key]) ? '' : asset('storage/' . $savedAngles[$key]) }}" alt="">
                            <label class="px-3 py-2 rounded-lg bg-white/[0.06] hover:bg-white/[0.10] border border-white/10 text-xs font-semibold cursor-pointer transition">
                                📷 {{ empty($savedAngles[$key]) ? 'Yükle' : 'Değiştir' }}
                                <input type="file" accept="image/*" class="hidden photo-input" data-angle="{{ $key }}" {{ $vehicle ? '' : 'disabled' }}>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </details>

        {{-- 4) Belgeler --}}
        <details class="bg-zinc-950 border border-white/10 rounded-2xl overflow-hidden" open>
            <summary class="px-5 py-4 flex items-center justify-between cursor-pointer">
                <span class="font-semibold">Belgeler</span>
                <span class="text-zinc-600 text-xs">Ehliyet · Selfie · SRC · Adli Sicil · Psikoteknik · Ruhsat · Sigorta · Muayene</span>
            </summary>
            <div class="px-5 pb-5 border-t border-white/5 pt-4 space-y-3">
                @foreach ($docs as $d)
                    <div class="flex items-center justify-between gap-3 doc-row" data-doc="{{ $d['type'] }}">
                        <span class="flex items-center gap-2 text-sm text-zinc-300">
                            <span class="doc-check text-xs {{ $d['file'] ? 'text-emerald-400' : 'text-zinc-600' }}">{{ $d['file'] ? '✓' : '○' }}</span>
                            {{ $d['label'] }}
                        </span>
                        <label class="px-3 py-2 rounded-lg bg-white/[0.06] hover:bg-white/[0.10] border border-white/10 text-xs font-semibold cursor-pointer transition">
                            📎 <span class="doc-btn-label">{{ $d['file'] ? 'Değiştir' : 'Yükle' }}</span>
                            <input type="file" accept=".pdf,image/*" class="hidden doc-input" data-doc="{{ $d['type'] }}">
                        </label>
                    </div>
                @endforeach
                <p class="text-[11px] text-zinc-600 pt-1">Ruhsat yüklemek için önce araç bilgilerini kaydetmen gerekir.</p>
            </div>
        </details>

        {{-- İncelemeye gönder --}}
        <button id="submit-btn" class="w-full py-4 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-base transition shadow-xl shadow-brand/30">
            İncelemeye Gönder
        </button>
        <p class="text-center text-xs text-zinc-500">İnceleme, tüm belgeler tamamlandığında başlar.</p>
    </main>

    {{-- Toast --}}
    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden px-4 py-3 rounded-xl text-sm font-semibold shadow-lg"></div>

    {{-- Popup --}}
    <div id="popup" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4">
        <div class="bg-zinc-900 border border-white/10 rounded-3xl max-w-sm w-full p-6 text-center">
            <div id="popup-icon" class="text-4xl mb-3"></div>
            <h3 id="popup-title" class="text-lg font-bold mb-2"></h3>
            <p id="popup-body" class="text-sm text-zinc-400 mb-5"></p>
            <button id="popup-close" class="w-full py-3 rounded-xl bg-white/10 hover:bg-white/15 font-semibold text-sm transition">Tamam</button>
        </div>
    </div>

    <script>
    (function () {
        'use strict';
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const URLS = {
            status:   '{{ route('driver.onboarding.status') }}',
            models:   '{{ route('driver.onboarding.models') }}',
            vehicle:  '{{ route('driver.onboarding.vehicle') }}',
            photo:    '{{ route('driver.onboarding.photo') }}',
            document: '{{ route('driver.onboarding.document') }}',
            submit:   '{{ route('driver.onboarding.submit') }}',
            panel:    '{{ route('driver.panel') }}',
        };
        const $ = (id) => document.getElementById(id);

        function toast(msg, ok = true) {
            const t = $('toast');
            t.textContent = msg;
            t.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-xl text-sm font-semibold shadow-lg ' +
                (ok ? 'bg-emerald-500 text-black' : 'bg-red-500 text-white');
            t.classList.remove('hidden');
            setTimeout(() => t.classList.add('hidden'), 3000);
        }
        function popup(icon, title, body) {
            $('popup-icon').textContent = icon;
            $('popup-title').textContent = title;
            $('popup-body').textContent = body;
            const p = $('popup');
            p.classList.remove('hidden'); p.classList.add('flex');
        }
        $('popup-close').addEventListener('click', () => {
            const p = $('popup'); p.classList.add('hidden'); p.classList.remove('flex');
        });

        // Durum → UI güncelle
        function renderStatus(ob) {
            if (!ob) return;
            $('ob-completed').textContent = ob.completed;
            $('ob-total').textContent = ob.total;
            $('ob-percent').textContent = ob.percent;
            $('ob-bar').style.width = ob.percent + '%';

            ob.steps.forEach(s => {
                document.querySelectorAll(`.ob-step[data-step-key="${s.key}"] .step-dot`).forEach(dot => {
                    dot.className = 'step-dot inline-block w-4 h-4 rounded-full text-[10px] leading-4 text-center ' +
                        (s.complete ? 'bg-emerald-500 text-black' : 'border border-zinc-600 text-transparent');
                    dot.textContent = s.complete ? '✓' : '';
                });
            });

            const banner = $('ob-status-banner');
            if (ob.status === 'approved') {
                banner.innerHTML = '<div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-200">Başvurun onaylandı! <a href="' + URLS.panel + '" class="underline font-bold">Panele git</a></div>';
            } else if (ob.status === 'rejected') {
                banner.innerHTML = '<div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/30 text-sm text-red-200 font-semibold">Başvurun reddedildi. Destek ile iletişime geç.</div>';
            } else if (ob.status === 'pending_review') {
                banner.innerHTML = '<div class="p-4 rounded-2xl bg-blue-500/10 border border-blue-500/30 text-sm text-blue-200"><span class="font-bold">İnceleniyor 🕒</span> — Belgelerin eksiksiz alındı, inceleme ekibimiz başvurunu değerlendiriyor.</div>';
            } else {
                const miss = (ob.missing || []).join(', ');
                banner.innerHTML = '<div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-sm text-amber-200"><span class="font-bold">Eksik evrak var.</span> İnceleme, tüm belgeler yüklendiğinde başlar.' +
                    (miss ? '<div class="text-xs text-amber-300/70 mt-1">Eksik: ' + miss + '</div>' : '') + '</div>';
            }
        }
        renderStatus(@json($ob));

        async function postForm(url, formData) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: formData,
            });
            return { ok: res.ok, data: await res.json().catch(() => ({})) };
        }

        // ── Marka → Model bağımlı dropdown ──
        const makeSel = $('make-select'), modelSel = $('model-select');
        async function loadModels(makeId, preselect) {
            modelSel.innerHTML = '<option value="">Yükleniyor…</option>';
            if (!makeId) { modelSel.innerHTML = '<option value="">Önce marka seç</option>'; return; }
            const res = await fetch(URLS.models + '?make_id=' + encodeURIComponent(makeId), { headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(() => ({ models: [] }));
            modelSel.innerHTML = '<option value="">Seçiniz</option>' +
                (data.models || []).map(m => `<option value="${m.id}" ${String(m.id) === String(preselect) ? 'selected' : ''}>${m.name}</option>`).join('');
        }
        if (makeSel) {
            makeSel.addEventListener('change', () => loadModels(makeSel.value, null));
            if (makeSel.value) loadModels(makeSel.value, modelSel.dataset.selected);
        }

        // ── Araç bilgisi kaydet ──
        const vform = $('vehicle-form');
        if (vform) {
            vform.addEventListener('submit', async (e) => {
                e.preventDefault();
                const { ok, data } = await postForm(URLS.vehicle, new FormData(vform));
                if (ok && data.ok) {
                    toast('Araç bilgileri kaydedildi ✓');
                    renderStatus(data.onboarding);
                    // fotoğraf inputlarını aç
                    document.querySelectorAll('.photo-input').forEach(i => i.disabled = false);
                } else {
                    toast(data.message || 'Kaydedilemedi.', false);
                }
            });
        }

        // ── Araç fotoğrafı yükle ──
        document.querySelectorAll('.photo-input').forEach(inp => {
            inp.addEventListener('change', async () => {
                const file = inp.files[0]; if (!file) return;
                const fd = new FormData(); fd.append('angle', inp.dataset.angle); fd.append('photo', file);
                const { ok, data } = await postForm(URLS.photo, fd);
                if (ok && data.ok) {
                    const row = inp.closest('.photo-row');
                    const thumb = row.querySelector('.photo-thumb');
                    thumb.src = data.url; thumb.classList.remove('hidden');
                    toast('Fotoğraf yüklendi ✓');
                    renderStatus(data.onboarding);
                } else {
                    toast(data.message || 'Yüklenemedi.', false);
                }
                inp.value = '';
            });
        });

        // ── Belge yükle ──
        document.querySelectorAll('.doc-input').forEach(inp => {
            inp.addEventListener('change', async () => {
                const file = inp.files[0]; if (!file) return;
                const fd = new FormData(); fd.append('type', inp.dataset.doc); fd.append('file', file);
                const { ok, data } = await postForm(URLS.document, fd);
                if (ok && data.ok) {
                    const row = inp.closest('.doc-row');
                    row.querySelector('.doc-check').textContent = '✓';
                    row.querySelector('.doc-check').className = 'doc-check text-xs text-emerald-400';
                    row.querySelector('.doc-btn-label').textContent = 'Değiştir';
                    toast('Belge yüklendi ✓');
                    renderStatus(data.onboarding);
                } else {
                    toast(data.message || 'Yüklenemedi.', false);
                }
                inp.value = '';
            });
        });

        // ── İncelemeye gönder ──
        $('submit-btn').addEventListener('click', async () => {
            const res = await fetch(URLS.submit, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const data = await res.json().catch(() => ({}));
            renderStatus(data.onboarding);
            if (res.ok && data.ok) {
                popup('✅', 'Başvurun alındı', data.message || 'İnceleme ekibimiz başvurunu değerlendirmeye başladı.');
            } else if (data.code === 'incomplete') {
                popup('📋', 'Eksik evrak var', (data.message || 'Eksik belgelerin var.') + (data.missing && data.missing.length ? '\n\nEksik: ' + data.missing.join(', ') : ''));
            } else {
                toast(data.message || 'Bir hata oluştu.', false);
            }
        });
    })();
    </script>
</body>
</html>
