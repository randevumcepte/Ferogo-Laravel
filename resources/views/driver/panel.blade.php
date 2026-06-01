<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sürücü Paneli · Ferogo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { brand: { DEFAULT: '#F0C040', 500: '#F0C040', 600: '#D9A621' } } } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes pulse-ring {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50%      { opacity: 1;   transform: scale(1.05); }
        }
        .pulse-ring { animation: pulse-ring 1.4s ease-in-out infinite; }

        @keyframes flash-bg {
            0%, 100% { background-color: rgba(240,192,64,0.10); }
            50%      { background-color: rgba(240,192,64,0.25); }
        }
        .flash-bg { animation: flash-bg 1s ease-in-out infinite; }
    </style>
</head>
<body class="bg-black text-white min-h-screen pb-20 md:pb-0">

    {{-- ===== Top bar ===== --}}
    <header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 min-w-0">
                <span class="text-2xl font-extrabold tracking-tight">
                    <span class="text-white">FERO</span><span class="text-brand">GO</span>
                </span>
            </a>

            @php
                $navAvatarUrl = $driver->user->avatar
                    ? (str_starts_with($driver->user->avatar, 'http') ? $driver->user->avatar : asset('storage/' . ltrim($driver->user->avatar, '/')))
                    : null;
            @endphp

            <div class="flex items-center gap-2 shrink-0">
                <button id="availability-toggle"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition border"
                        data-status="{{ $driver->availability_status }}">
                    <span id="availability-dot" class="w-2 h-2 rounded-full"></span>
                    <span id="availability-label">—</span>
                </button>
                <a href="{{ route('driver.packages.index') }}"
                   class="px-3 py-2 rounded-xl text-xs font-semibold text-brand hover:text-black hover:bg-brand border border-brand/40 hover:border-brand transition">
                    Paketler
                </a>
                <form method="POST" action="{{ route('driver.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">Çıkış</button>
                </form>
                <a href="{{ route('driver.profile') }}" title="Profilim"
                   class="relative w-10 h-10 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-sm overflow-hidden border-2 border-brand/40 hover:border-brand hover:scale-105 transition shadow-lg shadow-brand/20">
                    @if ($navAvatarUrl)
                        <img src="{{ $navAvatarUrl }}" alt="" class="w-full h-full object-cover">
                    @else
                        {{ mb_strtoupper(mb_substr($driver->user->name, 0, 1)) }}
                    @endif
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-5">

        {{-- ===== Paket uyarısı (JS açıp kapatır) ===== --}}
        <section id="package-banner" class="hidden">
            <a href="{{ route('driver.packages.index') }}"
               class="block rounded-3xl border-2 border-red-500/40 bg-red-500/[0.08] p-4 hover:bg-red-500/[0.12] transition">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center shrink-0">⚠</div>
                        <div class="min-w-0">
                            <div class="font-bold text-red-200" id="package-banner-title">Paket gerekli</div>
                            <div class="text-xs text-red-300/80 truncate" id="package-banner-subtitle">Online olmak için paket satın al.</div>
                        </div>
                    </div>
                    <div class="text-xs text-red-200 font-semibold shrink-0">Paket Al →</div>
                </div>
            </a>
        </section>

        {{-- ===== Driver kimlik özeti ===== --}}
        <section class="rounded-3xl border border-white/10 bg-zinc-950 p-5 flex items-center gap-4">
            <a href="{{ route('driver.profile') }}" class="w-14 h-14 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-xl shrink-0 overflow-hidden hover:opacity-90 transition border-2 border-brand/40">
                @if ($navAvatarUrl)
                    <img src="{{ $navAvatarUrl }}" alt="" class="w-full h-full object-cover">
                @else
                    {{ mb_strtoupper(mb_substr($driver->user->name, 0, 1)) }}
                @endif
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold truncate">{{ $driver->user->name }}</h1>
                <div class="text-xs text-zinc-500 truncate">
                    ★ {{ number_format((float) $driver->rating, 2) }} · {{ $driver->total_rides }} yolculuk
                    @if ($driver->currentVehicle)
                        · {{ $driver->currentVehicle->plate }}
                    @endif
                </div>
            </div>
        </section>

        {{-- ===== Offer card (pending teklif) ===== --}}
        <section id="offer-card" class="hidden">
            <div class="relative rounded-3xl border-2 border-brand bg-brand/10 flash-bg overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.25em] text-brand font-bold">
                            <span class="w-2 h-2 rounded-full bg-brand pulse-ring"></span>
                            Yeni Talep
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-extrabold tabular-nums" id="offer-countdown">60</div>
                            <div class="text-[10px] uppercase tracking-wider text-zinc-400">saniye</div>
                        </div>
                    </div>

                    <div class="space-y-3 mb-5">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-zinc-500">Müşteri</div>
                            <div class="text-base font-semibold" id="offer-customer">—</div>
                        </div>
                        <div class="bg-black/30 rounded-2xl p-4 space-y-3 border border-white/5">
                            <div class="flex items-start gap-3">
                                <div class="w-3 h-3 rounded-full bg-brand mt-1.5 shrink-0"></div>
                                <div class="min-w-0">
                                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Alış</div>
                                    <div class="text-sm" id="offer-pickup">—</div>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-3 h-3 rounded-sm bg-white mt-1.5 shrink-0"></div>
                                <div class="min-w-0">
                                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Bırakış</div>
                                    <div class="text-sm" id="offer-dropoff">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5">
                                <div class="text-[9px] uppercase tracking-wider text-zinc-500">Mesafe</div>
                                <div class="text-base font-bold" id="offer-distance">—</div>
                            </div>
                            <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5">
                                <div class="text-[9px] uppercase tracking-wider text-zinc-500">Süre</div>
                                <div class="text-base font-bold" id="offer-duration">—</div>
                            </div>
                            <div class="bg-white/[0.03] rounded-xl p-3 border border-white/5">
                                <div class="text-[9px] uppercase tracking-wider text-zinc-500">Net</div>
                                <div class="text-base font-bold text-brand" id="offer-fare">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button id="offer-reject"
                                class="flex-1 px-4 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm font-semibold text-zinc-300 transition">
                            Reddet
                        </button>
                        <button id="offer-accept"
                                class="flex-1 px-4 py-3 rounded-2xl bg-brand hover:bg-brand-600 text-black text-sm font-extrabold transition shadow-lg shadow-brand/30">
                            Kabul Et
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== Active ride card ===== --}}
        <section id="active-card" class="hidden">
            <div class="rounded-3xl border border-emerald-500/30 bg-emerald-500/[0.05] overflow-hidden">
                <div class="p-5 border-b border-white/5 flex items-center justify-between gap-3 flex-wrap">
                    <div class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.25em] text-emerald-400 font-bold">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-ring"></span>
                        Aktif Yolculuk
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button id="active-arrived"
                                class="px-3 py-1.5 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-xs font-semibold text-amber-300 transition">
                            Lokasyona vardım
                        </button>
                        <button id="active-no-show" disabled
                                class="px-3 py-1.5 rounded-lg bg-red-500/15 hover:bg-red-500/25 border border-red-500/30 text-xs font-semibold text-red-300 transition disabled:bg-white/[0.03] disabled:text-zinc-500 disabled:border-white/10 disabled:cursor-not-allowed">
                            <span id="active-no-show-label">Müşteri gelmedi</span>
                        </button>
                        {{-- Faz 5: tuzak soru — "Müşteri araca bindi mi?" yalnızca vardı + henüz sorulmamışsa --}}
                        <button id="active-boarding-question" class="hidden px-3 py-1.5 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-xs font-semibold text-amber-300 transition">
                            Müşteri araca bindi mi?
                        </button>
                        {{-- Faz 5: YOLCULUĞU BAŞLAT — boarding_confirmed_at sonrası, started_at yokken --}}
                        <button id="active-start-ride" class="hidden px-4 py-2 rounded-xl bg-brand hover:bg-brand-600 text-black text-sm font-bold uppercase tracking-wide transition shadow-lg shadow-brand/30">
                            ▶ Yolculuğu Başlat
                        </button>
                        <button id="active-complete" class="hidden px-3 py-1.5 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-xs font-semibold text-emerald-300 transition">
                            Tamamlandı
                        </button>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-[10px] uppercase tracking-wider text-zinc-500">Müşteri</div>
                            <span id="active-trust-badge" class="hidden text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-base font-semibold flex-1 min-w-0 truncate" id="active-customer">—</div>
                            <button type="button" id="active-call-btn"
                                    class="shrink-0 w-9 h-9 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center transition shadow-md shadow-emerald-500/30"
                                    title="Müşteriyi ara">
                                📞
                            </button>
                        </div>
                        <div id="active-customer-meta" class="text-[11px] text-zinc-500 mt-0.5"></div>
                    </div>

                    <div class="bg-black/30 rounded-2xl p-4 space-y-3 border border-white/5">
                        <div class="flex items-start gap-3">
                            <div class="w-3 h-3 rounded-full bg-brand mt-1.5"></div>
                            <div class="min-w-0">
                                <div class="text-[10px] uppercase tracking-wider text-zinc-500">Alış</div>
                                <div class="text-sm" id="active-pickup">—</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-3 h-3 rounded-sm bg-white mt-1.5"></div>
                            <div class="min-w-0">
                                <div class="text-[10px] uppercase tracking-wider text-zinc-500">Bırakış</div>
                                <div class="text-sm" id="active-dropoff">—</div>
                            </div>
                        </div>
                    </div>

                    {{-- Chat --}}
                    <div class="bg-black/30 rounded-2xl border border-white/5 overflow-hidden">
                        <div class="px-4 py-2.5 border-b border-white/5 text-[10px] uppercase tracking-wider text-zinc-500">Mesajlaşma</div>
                        <div id="chat-list" class="h-56 overflow-y-auto p-3 space-y-2 text-sm"></div>
                        <form id="chat-form" class="flex items-center gap-2 p-2 border-t border-white/5">
                            <input id="chat-input" type="text" maxlength="1000" autocomplete="off" required
                                   class="flex-1 bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-2 text-sm text-white placeholder-zinc-600 focus:outline-none"
                                   placeholder="Müşteriye mesaj…">
                            <button type="submit" class="px-3 py-2 rounded-xl bg-brand hover:bg-brand-600 text-black text-xs font-bold transition">Gönder</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== Idle (no offer, no active) ===== --}}
        <section id="idle-card">
            <div class="rounded-3xl border border-white/10 bg-zinc-950 p-10 text-center">
                <div class="relative w-20 h-20 mx-auto mb-5">
                    <div class="absolute inset-0 rounded-full border-2 border-brand/30 pulse-ring"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <svg class="w-9 h-9 text-brand" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.85 7h10.29l1.04 3H5.81l1.04-3zM5 17v-5h14v5H5zm2-2.5c0 .83-.67 1.5-1.5 1.5S4 15.33 4 14.5 4.67 13 5.5 13s1.5.67 1.5 1.5zm13 0c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5z"/></svg>
                    </div>
                </div>
                <h2 class="text-xl font-bold mb-1" id="idle-title">Yeni talep bekleniyor…</h2>
                <p class="text-sm text-zinc-500" id="idle-subtitle">Sayfayı açık tut. Talep gelince ses çalar ve burada belirir.</p>
            </div>
        </section>
    </main>

    <script>
    (function() {
        'use strict';

        const STATE_URL = '{{ route('driver.api.state') }}';
        const AVAIL_URL = '{{ route('driver.api.availability') }}';
        const ACCEPT_URL = (id) => '{{ url('/surucu-paneli/api/offers') }}/' + encodeURIComponent(id) + '/accept';
        const REJECT_URL = (id) => '{{ url('/surucu-paneli/api/offers') }}/' + encodeURIComponent(id) + '/reject';
        const MSG_URL     = '{{ route('driver.api.message') }}';
        const DONE_URL    = '{{ route('driver.api.complete') }}';
        const ARRIVED_URL = '{{ route('driver.api.arrived') }}';
        const NOSHOW_URL  = '{{ route('driver.api.no_show') }}';
        const BOARDING_QUESTION_URL = '{{ route('driver.api.boarding_question') }}';
        const BOARDING_CONFIRM_URL  = '{{ route('driver.api.boarding_confirm') }}';
        const START_RIDE_URL        = '{{ route('driver.api.start_ride') }}';
        const POLL_MS     = 2500;

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const $ = (id) => document.getElementById(id);

        const offerCard   = $('offer-card');
        const activeCard  = $('active-card');
        const idleCard    = $('idle-card');
        const availBtn    = $('availability-toggle');
        const availDot    = $('availability-dot');
        const availLabel  = $('availability-label');

        let lastOfferId   = null;   // hangi public_id şu an gösteriliyor
        let lastMessageId = 0;
        let countdownHandle = null;

        // === SOUND (Web Audio beep — daha yüksek, canlı ton) ===
        let audioCtx = null;
        function beep(freq = 880, ms = 220, vol = 0.6) {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                // square wave klasik telefon zili karakteri verir
                osc.type = 'square';
                osc.frequency.value = freq;
                gain.gain.value = 0.0001;
                osc.connect(gain).connect(audioCtx.destination);
                osc.start();
                gain.gain.exponentialRampToValueAtTime(vol, audioCtx.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + ms / 1000);
                osc.stop(audioCtx.currentTime + ms / 1000 + 0.05);
            } catch (_) {}
        }
        document.addEventListener('click', () => {
            if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (_) {} }
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
        }, { once: true });

        // Offer geldiğinde — yüksek sesli 3 tonlu klasik telefon zili döngülü
        let offerBeepInterval = null;
        function startOfferBeep() {
            if (offerBeepInterval) return;
            const ring = () => {
                beep(1318, 150, 0.7);                              // E6 — yüksek dikkat çekici
                setTimeout(() => beep(987, 150, 0.7), 180);        // B5
                setTimeout(() => beep(1318, 200, 0.7), 360);       // E6 tekrar
            };
            ring();
            offerBeepInterval = setInterval(ring, 1200);
            if (navigator.vibrate) navigator.vibrate([400, 200, 400, 200, 400]);
        }
        function stopOfferBeep() {
            if (offerBeepInterval) { clearInterval(offerBeepInterval); offerBeepInterval = null; }
            if (navigator.vibrate) navigator.vibrate(0);
        }

        // === AVAILABILITY TOGGLE ===
        function renderAvailability(status) {
            availBtn.dataset.status = status;
            if (status === 'busy') {
                availDot.className = 'w-2 h-2 rounded-full bg-amber-400';
                availLabel.textContent = 'Yolculukta';
                availBtn.className = availBtn.className.replace(/border-\S+/g, '') + ' border-amber-500/40 bg-amber-500/10 text-amber-300';
                availBtn.disabled = true;
            } else if (status === 'online') {
                availDot.className = 'w-2 h-2 rounded-full bg-emerald-400 pulse-ring';
                availLabel.textContent = 'Müsait';
                availBtn.className = availBtn.className.replace(/border-\S+/g, '') + ' border-emerald-500/40 bg-emerald-500/10 text-emerald-300';
                availBtn.disabled = false;
            } else {
                availDot.className = 'w-2 h-2 rounded-full bg-zinc-500';
                availLabel.textContent = 'Çevrimdışı';
                availBtn.className = availBtn.className.replace(/border-\S+/g, '') + ' border-white/10 bg-white/[0.03] text-zinc-400';
                availBtn.disabled = false;
            }
        }

        availBtn.addEventListener('click', async () => {
            if (availBtn.disabled) return;
            const next = availBtn.dataset.status === 'online' ? 'offline' : 'online';
            try {
                const res = await fetch(AVAIL_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ status: next }),
                });
                const data = await res.json();
                if (data.ok) {
                    renderAvailability(data.status);
                } else if (data.code === 'package_required') {
                    // Paket yok → Paketler sayfasına yönlendir
                    if (confirm(data.message + '\n\nPaketler sayfasına gidilsin mi?')) {
                        location.href = data.redirect || '{{ route('driver.packages.index') }}';
                    }
                }
            } catch (_) {}
        });

        // === PAKET BANNER ===
        const pkgBanner   = $('package-banner');
        const pkgTitle    = $('package-banner-title');
        const pkgSubtitle = $('package-banner-subtitle');

        function renderPackageBanner(pkg) {
            if (!pkg || !pkg.active) {
                pkgBanner.classList.remove('hidden');
                pkgTitle.textContent = 'Paket gerekli';
                pkgSubtitle.textContent = 'Online olmak ve iş alabilmek için paket satın al.';
                return;
            }

            const mins = pkg.remaining_minutes || 0;
            // 1 saatten az kalmışsa uyarı göster
            if (mins > 0 && mins < 60) {
                pkgBanner.classList.remove('hidden');
                pkgTitle.textContent = 'Paketin bitiyor';
                pkgSubtitle.textContent = `Yaklaşık ${mins} dakika kaldı. Yeni paket al, kesintisiz devam et.`;
            } else {
                pkgBanner.classList.add('hidden');
            }
        }

        // === RENDER ===
        function showSection(name) {
            offerCard.classList.toggle('hidden', name !== 'offer');
            activeCard.classList.toggle('hidden', name !== 'active');
            idleCard.classList.toggle('hidden', name !== 'idle');
        }

        function renderOffer(o) {
            $('offer-customer').textContent = o.customer_name;
            $('offer-pickup').textContent = o.pickup_address;
            $('offer-dropoff').textContent = o.dropoff_address;
            $('offer-distance').textContent = `${o.distance_km.toFixed(1)} km`;
            $('offer-duration').textContent = `${o.duration_minutes} dk`;
            $('offer-fare').textContent = o.estimated_fare ? `₺${Math.round(o.estimated_fare)}` : '—';

            // Yeni offer geldiyse — sürekli bip + countdown reset
            if (o.public_id !== lastOfferId) {
                lastOfferId = o.public_id;
                startOfferBeep();

                $('offer-accept').onclick = async () => {
                    stopOfferBeep();
                    $('offer-accept').disabled = true;
                    try {
                        const res = await fetch(ACCEPT_URL(o.public_id), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        if (!data.ok) alert(data.message || 'Kabul edilemedi.');
                    } catch (_) { alert('Bağlantı hatası.'); }
                    finally { $('offer-accept').disabled = false; pollNow(); }
                };
                $('offer-reject').onclick = async () => {
                    stopOfferBeep();
                    $('offer-reject').disabled = true;
                    try {
                        await fetch(REJECT_URL(o.public_id), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                    } catch (_) {}
                    finally { $('offer-reject').disabled = false; pollNow(); }
                };
            }

            // Countdown
            if (countdownHandle) clearInterval(countdownHandle);
            let remaining = o.seconds_remaining;
            $('offer-countdown').textContent = remaining;
            countdownHandle = setInterval(() => {
                remaining = Math.max(0, remaining - 1);
                $('offer-countdown').textContent = remaining;
                if (remaining <= 0) { clearInterval(countdownHandle); pollNow(); }
            }, 1000);

            showSection('offer');
        }

        let currentActiveId = null;       // şu an aktif olan request public_id
        let activePickupCoords = null;    // { lat, lng } — no-show GPS doğrulaması için
        let noShowTickHandle = null;

        function renderTrustBadge(a) {
            const badge = $('active-trust-badge');
            const meta  = $('active-customer-meta');
            const label = a.customer_trust_label;
            const styles = {
                guvenilir:  { txt: 'Güvenilir',  cls: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' },
                normal:     { txt: 'Normal',     cls: 'bg-zinc-500/15 text-zinc-300 border border-zinc-500/30' },
                riskli:     { txt: 'Riskli',     cls: 'bg-amber-500/15 text-amber-300 border border-amber-500/30' },
                cok_riskli: { txt: 'Çok Riskli', cls: 'bg-red-500/15 text-red-300 border border-red-500/30' },
            };
            const s = styles[label] || styles.normal;
            badge.textContent = a.customer_is_new ? 'Yeni Müşteri' : s.txt;
            badge.className = 'text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ' + s.cls;
            badge.classList.remove('hidden');

            const parts = [];
            if (a.customer_completed_rides > 0) parts.push(a.customer_completed_rides + ' tamamlanmış yolculuk');
            if (a.customer_no_shows > 0)        parts.push(a.customer_no_shows + ' geçmiş no-show');
            meta.textContent = parts.join(' · ');
        }

        function renderNoShowState(a) {
            const arrivedBtn = $('active-arrived');
            const noShowBtn  = $('active-no-show');
            const label      = $('active-no-show-label');

            if (!a.arrived_at) {
                arrivedBtn.disabled = false;
                arrivedBtn.classList.remove('opacity-50');
                arrivedBtn.textContent = 'Lokasyona vardım';
                noShowBtn.disabled = true;
                label.textContent = 'Müşteri gelmedi';
                if (noShowTickHandle) { clearInterval(noShowTickHandle); noShowTickHandle = null; }
                return;
            }

            // Vardı — buton işareti
            arrivedBtn.disabled = true;
            arrivedBtn.classList.add('opacity-50');
            arrivedBtn.textContent = '✓ Varış kaydedildi';

            if (a.no_show_button_ready) {
                noShowBtn.disabled = false;
                label.textContent = 'Müşteri gelmedi';
                if (noShowTickHandle) { clearInterval(noShowTickHandle); noShowTickHandle = null; }
            } else {
                noShowBtn.disabled = true;
                let remaining = Math.max(0, a.no_show_countdown_sec || 0);
                label.textContent = 'Müşteri gelmedi (' + remaining + ' sn)';
                if (noShowTickHandle) clearInterval(noShowTickHandle);
                noShowTickHandle = setInterval(() => {
                    remaining = Math.max(0, remaining - 1);
                    label.textContent = remaining > 0
                        ? 'Müşteri gelmedi (' + remaining + ' sn)'
                        : 'Müşteri gelmedi';
                    if (remaining <= 0) {
                        noShowBtn.disabled = false;
                        clearInterval(noShowTickHandle);
                        noShowTickHandle = null;
                    }
                }, 1000);
            }
        }

        /**
         * Faz 5 — Tuzak soru + Yolculuğu Başlat akışı UI render.
         *
         * Durumlar (state machine):
         *   1. arrived_at YOK → tüm butonlar gizli
         *   2. arrived_at VAR, boarding_question_at YOK → "Müşteri araca bindi mi?" butonu görünür
         *   3. boarding_question_at VAR, boarding_confirmed_at YOK → tuzak modal otomatik açılır
         *   4. boarding_confirmed_at VAR, started_at YOK → "▶ YOLCULUĞU BAŞLAT" sarı butonu görünür
         *   5. started_at VAR → "Tamamlandı" butonu görünür
         */
        function renderBoardingStartState(a) {
            const boardingBtn  = $('active-boarding-question');
            const startBtn     = $('active-start-ride');
            const completeBtn  = $('active-complete');

            const arrived         = !!a.arrived_at;
            const questionOpened  = !!a.boarding_question_at;
            const boardingDone    = !!a.boarding_confirmed_at;
            const started         = !!a.started_at;

            // 1. Henüz vardı değil
            if (!arrived) {
                boardingBtn.classList.add('hidden');
                startBtn.classList.add('hidden');
                completeBtn.classList.add('hidden');
                return;
            }

            // 5. Yolculuk başladı → sadece "Tamamlandı"
            if (started) {
                boardingBtn.classList.add('hidden');
                startBtn.classList.add('hidden');
                completeBtn.classList.remove('hidden');
                return;
            }

            // 4. Boarding onaylı, başlatma bekliyor → "▶ YOLCULUĞU BAŞLAT"
            if (boardingDone) {
                boardingBtn.classList.add('hidden');
                startBtn.classList.remove('hidden');
                completeBtn.classList.add('hidden');
                return;
            }

            // 3. Tuzak soru açıldı ama cevap yok → modal otomatik aç
            if (questionOpened) {
                boardingBtn.classList.add('hidden');
                startBtn.classList.add('hidden');
                completeBtn.classList.add('hidden');
                if (!boardingModalShownFor || boardingModalShownFor !== a.public_id) {
                    boardingModalShownFor = a.public_id;
                    openBoardingTrapModal();
                }
                return;
            }

            // 2. Vardı + tuzak henüz açılmadı → "Müşteri araca bindi mi?" butonu
            boardingBtn.classList.remove('hidden');
            startBtn.classList.add('hidden');
            completeBtn.classList.add('hidden');
        }

        let boardingModalShownFor = null;

        function openBoardingTrapModal() {
            const modal = $('boarding-trap-modal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeBoardingTrapModal() {
            const modal = $('boarding-trap-modal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function renderActive(a, messages) {
            // Yolculuk değiştiyse chat ve sayaçları sıfırla
            if (currentActiveId !== a.public_id) {
                currentActiveId = a.public_id;
                lastMessageId = 0;
                $('chat-list').innerHTML = '';
            }
            activePickupCoords = { lat: a.pickup_lat, lng: a.pickup_lng };

            $('active-customer').textContent = a.customer_name;
            $('active-pickup').textContent = a.pickup_address;
            $('active-dropoff').textContent = a.dropoff_address;

            renderTrustBadge(a);
            renderNoShowState(a);
            renderBoardingStartState(a);

            // Mesaj append
            const chat = $('chat-list');
            (messages || []).forEach(m => {
                lastMessageId = Math.max(lastMessageId, m.id);
                const bubble = document.createElement('div');
                const align = m.sender === 'driver' ? 'justify-end' : (m.sender === 'system' ? 'justify-center' : 'justify-start');
                const bg = m.sender === 'driver' ? 'bg-brand text-black' : (m.sender === 'system' ? 'bg-white/5 text-zinc-400 text-xs' : 'bg-white/10');
                bubble.className = `flex ${align}`;
                bubble.innerHTML = `<div class="max-w-[80%] rounded-2xl px-3 py-2 ${bg}">${escapeHtml(m.body)}</div>`;
                chat.appendChild(bubble);
            });
            chat.scrollTop = chat.scrollHeight;

            showSection('active');
        }

        // === ARRIVED button ===
        $('active-arrived').addEventListener('click', async () => {
            $('active-arrived').disabled = true;
            try {
                const res = await fetch(ARRIVED_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!data.ok) alert(data.message || 'Varış işaretlenemedi.');
            } catch (_) { alert('Bağlantı hatası.'); }
            finally { pollNow(); }
        });

        // === NO-SHOW button ===
        $('active-no-show').addEventListener('click', async () => {
            if ($('active-no-show').disabled) return;
            if (!confirm('Müşterinin gelmediğini onaylıyor musun? Bu kayıt müşterinin hesabına işlenir ve sana tazminat ödenir.')) return;

            $('active-no-show').disabled = true;

            // GPS koordinatlarını al — yakınlık doğrulaması için kritik
            const sendNoShow = async (lat, lng) => {
                try {
                    const body = { lat, lng };
                    const res = await fetch(NOSHOW_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify(body),
                    });
                    const data = await res.json();
                    if (!data.ok) {
                        alert(data.message || 'No-show kaydedilemedi.');
                        $('active-no-show').disabled = false;
                        return;
                    }
                    alert(data.message || 'Olay kayda alındı. Tazminat hesabına işlendi.');
                    lastMessageId = 0;
                    $('chat-list').innerHTML = '';
                    pollNow();
                } catch (_) {
                    alert('Bağlantı hatası.');
                    $('active-no-show').disabled = false;
                }
            };

            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => sendNoShow(pos.coords.latitude, pos.coords.longitude),
                    () => sendNoShow(null, null),
                    { timeout: 6000, maximumAge: 30000, enableHighAccuracy: true }
                );
            } else {
                await sendNoShow(null, null);
            }
        });

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        // Chat form
        $('chat-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = $('chat-input');
            const body = input.value.trim();
            if (!body) return;
            input.value = '';
            try {
                const res = await fetch(MSG_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ body }),
                });
                const data = await res.json();
                if (data.ok) {
                    // Optimistic append
                    lastMessageId = Math.max(lastMessageId, data.message.id);
                    const chat = $('chat-list');
                    const bubble = document.createElement('div');
                    bubble.className = 'flex justify-end';
                    bubble.innerHTML = `<div class="max-w-[80%] rounded-2xl px-3 py-2 bg-brand text-black">${escapeHtml(data.message.body)}</div>`;
                    chat.appendChild(bubble);
                    chat.scrollTop = chat.scrollHeight;
                }
            } catch (_) { alert('Mesaj gönderilemedi.'); }
        });

        // ===== Faz 5 — Tuzak soru & Ride start =====

        // "Müşteri araca bindi mi?" butonu → tuzak soruyu açar (sadece sorgu zamanını işaretler)
        $('active-boarding-question').addEventListener('click', async () => {
            try {
                const res = await fetch(BOARDING_QUESTION_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (res.ok) pollNow();
            } catch (_) {}
        });

        // Tuzak modal "EVET" — boarding_confirmed_at set edilir, "Yolculuğu Başlat" butonu görünür
        document.addEventListener('click', async (e) => {
            const target = e.target.closest('#boarding-trap-yes');
            if (!target) return;
            target.disabled = true;
            try {
                const res = await fetch(BOARDING_CONFIRM_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (res.ok) {
                    closeBoardingTrapModal();
                    pollNow();
                }
            } catch (_) {} finally { target.disabled = false; }
        });
        // Tuzak modal "HAYIR" — sadece modal'ı kapat (sürücü tekrar deneyebilir)
        document.addEventListener('click', (e) => {
            if (e.target.closest('#boarding-trap-no')) {
                closeBoardingTrapModal();
                boardingModalShownFor = null;
            }
        });

        // YOLCULUĞU BAŞLAT — sarı buton
        $('active-start-ride').addEventListener('click', async () => {
            if (!confirm('Müşteri araçta ve yola çıkmaya hazır mı? Yolculuğu başlatıyorum.')) return;
            $('active-start-ride').disabled = true;
            try {
                const res = await fetch(START_RIDE_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (res.ok) pollNow();
                else alert('Başlatılamadı, sayfayı yenile.');
            } catch (_) {} finally { $('active-start-ride').disabled = false; }
        });

        // Complete
        $('active-complete').addEventListener('click', async () => {
            if (!confirm('Yolculuğu tamamlandı olarak işaretle?')) return;
            try {
                const res = await fetch(DONE_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.ok) { lastMessageId = 0; $('chat-list').innerHTML = ''; pollNow(); }
            } catch (_) {}
        });

        // === POLL ===
        async function pollOnce() {
            try {
                const res = await fetch(`${STATE_URL}?since_id=${lastMessageId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.status === 401) { location.href = '{{ route('driver.login') }}'; return; }
                const data = await res.json();

                if (data.driver) renderAvailability(data.driver.availability_status);
                if (data.package) renderPackageBanner(data.package);

                // Faz 6: aktif güvenlik olayı (security_incident) varsa → zorla foto modal'ı aç
                if (data.security_incident && data.security_incident.public_id && !data.security_incident.all_uploaded) {
                    if (typeof window.openSecurityPhotoModal === 'function') {
                        window.openSecurityPhotoModal(data.security_incident);
                    }
                }

                if (data.offer) {
                    renderOffer(data.offer);
                } else if (data.active) {
                    stopOfferBeep();
                    if (countdownHandle) { clearInterval(countdownHandle); countdownHandle = null; }
                    lastOfferId = null;
                    renderActive(data.active, data.messages || []);
                } else {
                    stopOfferBeep();
                    if (countdownHandle) { clearInterval(countdownHandle); countdownHandle = null; }
                    lastOfferId = null;
                    showSection('idle');
                }
            } catch (err) {
                console.warn('[Ferogo driver] poll failed', err);
            }
        }

        let pollHandle = null;
        function pollNow() { pollOnce(); }
        function startPolling() {
            pollOnce();
            if (pollHandle) clearInterval(pollHandle);
            pollHandle = setInterval(pollOnce, POLL_MS);
        }

        // Sekme arka planda → daha seyrek poll
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (pollHandle) { clearInterval(pollHandle); pollHandle = setInterval(pollOnce, 8000); }
            } else {
                startPolling();
            }
        });

        renderAvailability('{{ $driver->availability_status }}');
        startPolling();

        // ===== Sesli görüşme widget'ı için global hook'lar =====
        window.callWidgetGetPublicId = () => currentActiveId;
        window.callWidgetGetPeerName = () => {
            const el = document.getElementById('active-customer');
            return el ? (el.textContent || 'Müşteri') : 'Müşteri';
        };

        document.getElementById('active-call-btn').addEventListener('click', () => {
            if (window.CallWidget) window.CallWidget.start();
        });
    })();
    </script>

    @include('partials.call-widget')
    @include('partials.mobile-action-bar')

    {{-- Faz 6: ZORUNLU KİMLİK DOĞRULAMA modal'ı — security incident açıldığında otomatik açılır.
         Müşteri "sürücü/araç eşleşmiyor" dedi → çağrı merkezi alarmı + sürücüden 3 foto:
         (1) selfie (ön kamera + gece beyaz ekran flash)
         (2) araç dış görünümü (arka kamera)
         (3) plaka net görünüm (arka kamera)
         Tüm 3 foto yüklenene kadar bu modal kapanmaz. --}}
    <div id="security-photo-modal"
         class="fixed inset-0 z-[150] hidden items-center justify-center bg-red-950/95 backdrop-blur-md px-4 py-6"
         role="dialog" aria-modal="true">
        <div class="w-full max-w-md max-h-[95vh] overflow-y-auto rounded-3xl bg-zinc-900 border-2 border-red-500/60 shadow-2xl shadow-red-500/30">
            <div class="px-6 pt-6 pb-3 bg-red-500/15 border-b border-red-500/30">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-500/30 border border-red-500/60 text-red-200 text-[11px] uppercase tracking-[0.2em] font-bold mb-3 animate-pulse">
                    🚨 ACİL — GÜVENLİK DOĞRULAMASI
                </div>
                <h2 class="text-2xl font-bold text-white leading-tight">
                    Kimliğinizi doğrulamanız gerekiyor
                </h2>
                <p class="text-sm text-zinc-200 mt-2 leading-relaxed">
                    Yolcunuz "sürücü/araç eşleşmiyor" bildiriminde bulundu. Çağrı merkezi sizinle
                    iletişime geçti. Lütfen aşağıdaki <strong>3 fotoğrafı</strong> hemen çekin.
                </p>
            </div>

            <div class="px-6 py-4 space-y-3" id="security-photo-steps">
                {{-- Selfie --}}
                <div class="photo-step border border-white/10 rounded-2xl p-4" data-photo-type="selfie">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full bg-red-500/30 border border-red-500/60 text-red-200 text-xs font-bold flex items-center justify-center photo-step-num">1</span>
                            <span class="text-sm font-semibold text-white">Selfie (Yüzünüz)</span>
                        </div>
                        <span class="photo-step-status text-xs text-zinc-500">⏳ Bekleniyor</span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mb-3">Yüzünüz net görünmeli. Karanlıksa ekran otomatik beyaz olur.</p>
                    <button type="button" class="photo-capture-btn w-full px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-black font-bold text-sm transition">
                        📷 Selfie Çek
                    </button>
                </div>

                {{-- Vehicle --}}
                <div class="photo-step border border-white/10 rounded-2xl p-4" data-photo-type="vehicle">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full bg-red-500/30 border border-red-500/60 text-red-200 text-xs font-bold flex items-center justify-center photo-step-num">2</span>
                            <span class="text-sm font-semibold text-white">Aracın Dış Fotoğrafı</span>
                        </div>
                        <span class="photo-step-status text-xs text-zinc-500">⏳ Bekleniyor</span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mb-3">Araç tamamen kareye girmeli — renk + model görünür şekilde.</p>
                    <button type="button" class="photo-capture-btn w-full px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-black font-bold text-sm transition">
                        📷 Aracı Fotoğrafla
                    </button>
                </div>

                {{-- Plate --}}
                <div class="photo-step border border-white/10 rounded-2xl p-4" data-photo-type="plate">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full bg-red-500/30 border border-red-500/60 text-red-200 text-xs font-bold flex items-center justify-center photo-step-num">3</span>
                            <span class="text-sm font-semibold text-white">Plaka (Net)</span>
                        </div>
                        <span class="photo-step-status text-xs text-zinc-500">⏳ Bekleniyor</span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mb-3">Plaka rakam ve harfleri net okunabilir olmalı.</p>
                    <button type="button" class="photo-capture-btn w-full px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-black font-bold text-sm transition">
                        📷 Plakayı Fotoğrafla
                    </button>
                </div>
            </div>

            <div class="px-6 pb-6 pt-3 border-t border-white/5">
                <div id="security-photo-progress" class="text-center text-sm text-zinc-300 mb-3">
                    <span id="sp-uploaded">0</span> / 3 yüklendi
                </div>
                <p class="text-[11px] text-zinc-500 text-center leading-relaxed">
                    Tüm fotoğraflar yüklenene kadar bu ekran kapanmaz. Yardım için
                    <a href="tel:+908508401377" class="text-brand hover:underline">0850 840 13 77</a>
                </p>
            </div>
        </div>
    </div>

    {{-- Gizli kamera input'ları — capture attribute mobil kameraları açar --}}
    <input type="file" id="sp-capture-selfie"  accept="image/*" capture="user"        class="hidden">
    <input type="file" id="sp-capture-vehicle" accept="image/*" capture="environment" class="hidden">
    <input type="file" id="sp-capture-plate"   accept="image/*" capture="environment" class="hidden">

    {{-- Gece flash overlay'i (selfie tetiklendiğinde 1.5sn beyaz ekran) --}}
    <div id="sp-flash-overlay" class="fixed inset-0 z-[160] hidden bg-white"></div>

    <script>
    (function () {
        const modal = document.getElementById('security-photo-modal');
        if (!modal) return;
        const inputs = {
            selfie:  document.getElementById('sp-capture-selfie'),
            vehicle: document.getElementById('sp-capture-vehicle'),
            plate:   document.getElementById('sp-capture-plate'),
        };
        const flash = document.getElementById('sp-flash-overlay');
        let currentIncidentPublicId = null;
        const uploaded = new Set();
        let modalShownForIncident = null;

        // Cron-state poller incident bildirdiğinde modal'ı aç
        window.openSecurityPhotoModal = function (incident) {
            if (!incident || !incident.public_id) return;
            if (modalShownForIncident === incident.public_id && incident.all_uploaded) return;
            currentIncidentPublicId = incident.public_id;
            modalShownForIncident = incident.public_id;

            // Mevcut uploaded'ları sync et
            uploaded.clear();
            (incident.photos_uploaded || []).forEach(t => uploaded.add(t));
            redrawProgress();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

            // Eğer hepsi yüklendi (operator inceliyor) → modal'ı sadece bilgilendirme amaçlı tut
            if (incident.all_uploaded) {
                // İsteğe bağlı: bilgi mesajı göster, kapat
            }
        };

        function redrawProgress() {
            document.getElementById('sp-uploaded').textContent = uploaded.size;
            document.querySelectorAll('.photo-step').forEach(step => {
                const type = step.dataset.photoType;
                const status = step.querySelector('.photo-step-status');
                const btn = step.querySelector('.photo-capture-btn');
                if (uploaded.has(type)) {
                    status.textContent = '✓ Yüklendi';
                    status.className = 'photo-step-status text-xs text-emerald-300 font-bold';
                    btn.classList.add('opacity-50', 'pointer-events-none');
                    btn.textContent = '✓ Tamamlandı';
                } else {
                    status.textContent = '⏳ Bekleniyor';
                    status.className = 'photo-step-status text-xs text-zinc-500';
                }
            });
            // Tüm 3'ü yüklendiyse modal'ı kapatabiliriz (operator inceleyecek)
            if (uploaded.size >= 3) {
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = '';
                    alert('✓ 3 fotoğraf yüklendi. Çağrı merkezi inceliyor.');
                }, 400);
            }
        }

        async function captureFlow(type) {
            // Gece selfie ise önce beyaz ekran flash
            if (type === 'selfie') {
                flash.classList.remove('hidden');
                await new Promise(r => setTimeout(r, 1200));
                flash.classList.add('hidden');
            }
            inputs[type].click();
        }

        async function uploadPhoto(type, file) {
            if (!currentIncidentPublicId) return;
            const fd = new FormData();
            fd.append('photo', file);
            fd.append('type', type);
            fd.append('flash_used', type === 'selfie' ? '1' : '0');
            fd.append('front_camera', type === 'selfie' ? '1' : '0');
            if (navigator.geolocation) {
                try {
                    const pos = await new Promise((res, rej) => {
                        navigator.geolocation.getCurrentPosition(res, rej, { timeout: 3000 });
                    });
                    fd.append('captured_lat', pos.coords.latitude);
                    fd.append('captured_lng', pos.coords.longitude);
                } catch (_) {}
            }
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`{{ url('/api/security-incidents') }}/${encodeURIComponent(currentIncidentPublicId)}/photo`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (data.success) {
                    uploaded.add(type);
                    redrawProgress();
                } else {
                    alert('Foto yüklenemedi: ' + (data.message || 'Bilinmeyen hata'));
                }
            } catch (err) {
                alert('Bağlantı hatası. Tekrar deneyin.');
            }
        }

        // Button click → kamera aç
        document.querySelectorAll('.photo-capture-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const step = btn.closest('.photo-step');
                captureFlow(step.dataset.photoType);
            });
        });

        // File seçildi → upload
        Object.entries(inputs).forEach(([type, input]) => {
            input.addEventListener('change', (e) => {
                const file = e.target.files?.[0];
                if (file) uploadPhoto(type, file);
                e.target.value = ''; // sıfırla, aynı dosya tekrar seçilebilsin
            });
        });
    })();
    </script>

    {{-- Faz 5: Tuzak soru modal'ı — "Müşteri araca bindi mi?" --}}
    <div id="boarding-trap-modal"
         class="fixed inset-0 z-[120] hidden items-center justify-center bg-black/85 backdrop-blur-md px-4 py-6"
         role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-3xl bg-zinc-900 border border-amber-500/40 shadow-2xl shadow-amber-500/20">
            <div class="px-6 pt-6 pb-3">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/15 border border-amber-500/40 text-amber-300 text-[11px] uppercase tracking-[0.2em] font-bold mb-3">
                    <span class="text-base">🚪</span> Onay Bekleniyor
                </div>
                <h2 class="text-2xl font-bold text-white leading-tight">
                    Müşteri araca bindi mi?
                </h2>
                <p class="text-sm text-zinc-400 mt-2 leading-relaxed">
                    Müşterinin gerçekten araçta olduğundan emin ol. Onayını alır almaz
                    <strong class="text-amber-300">"Yolculuğu Başlat"</strong> butonu görünür.
                </p>
            </div>

            <div class="px-6 py-4 bg-black/30 border-y border-white/5 text-[12px] text-zinc-500 leading-relaxed">
                <strong class="text-zinc-300">⚠ Uyarı:</strong> Bu butona basmak yolculuğu BAŞLATMAZ.
                Yalnızca müşterinin araçta olduğunu kayıt altına alır. Yolculuk fiilen başlatıldığında
                müşteriye görsel doğrulama ekranı gönderilir.
            </div>

            <div class="px-6 py-5 flex flex-col gap-3">
                <button type="button" id="boarding-trap-yes"
                        class="w-full px-5 py-3.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-black font-bold transition flex items-center justify-center gap-2">
                    <span>✓</span> EVET, müşteri araçta
                </button>
                <button type="button" id="boarding-trap-no"
                        class="w-full px-5 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 hover:text-white text-sm font-medium transition">
                    Henüz binmedi (kapat)
                </button>
            </div>
        </div>
    </div>
</body>
</html>
