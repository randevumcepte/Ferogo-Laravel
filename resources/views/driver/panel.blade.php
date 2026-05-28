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
<body class="bg-black text-white min-h-screen">

    {{-- ===== Top bar ===== --}}
    <header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-bold text-sm shrink-0">
                    {{ mb_strtoupper(mb_substr($driver->user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate">{{ $driver->user->name }}</div>
                    <div class="text-[11px] text-zinc-500 truncate">
                        ★ {{ number_format((float) $driver->rating, 2) }} · {{ $driver->total_rides }} yolculuk
                        @if ($driver->currentVehicle)
                            · {{ $driver->currentVehicle->plate }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button id="availability-toggle"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition border"
                        data-status="{{ $driver->availability_status }}">
                    <span id="availability-dot" class="w-2 h-2 rounded-full"></span>
                    <span id="availability-label">—</span>
                </button>
                <form method="POST" action="{{ route('driver.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">Çıkış</button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-5">

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
                        <button id="active-complete"
                                class="px-3 py-1.5 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-xs font-semibold text-emerald-300 transition">
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
                        <div class="flex items-center justify-between">
                            <div class="text-base font-semibold" id="active-customer">—</div>
                            <a id="active-phone" href="#" class="text-xs text-brand hover:text-brand-600 underline underline-offset-2">—</a>
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

        // === SOUND (Web Audio beep) ===
        let audioCtx = null;
        function beep(freq = 880, ms = 220) {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.value = 0.0001;
                osc.connect(gain).connect(audioCtx.destination);
                osc.start();
                gain.gain.exponentialRampToValueAtTime(0.15, audioCtx.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + ms / 1000);
                osc.stop(audioCtx.currentTime + ms / 1000 + 0.05);
            } catch (_) {}
        }
        // İlk kullanıcı etkileşiminde context'i aç (mobil zorunluluk)
        document.addEventListener('click', () => {
            if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (_) {} }
        }, { once: true });

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
                if (data.ok) renderAvailability(data.status);
            } catch (_) {}
        });

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

            // Yeni offer geldiyse — ses + countdown reset
            if (o.public_id !== lastOfferId) {
                lastOfferId = o.public_id;
                beep(880, 200); setTimeout(() => beep(1100, 220), 240);

                $('offer-accept').onclick = async () => {
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

        function renderActive(a, messages) {
            // Yolculuk değiştiyse chat ve sayaçları sıfırla
            if (currentActiveId !== a.public_id) {
                currentActiveId = a.public_id;
                lastMessageId = 0;
                $('chat-list').innerHTML = '';
            }
            activePickupCoords = { lat: a.pickup_lat, lng: a.pickup_lng };

            $('active-customer').textContent = a.customer_name;
            $('active-phone').textContent = a.customer_phone;
            $('active-phone').href = 'tel:' + a.customer_phone.replace(/\s+/g, '');
            $('active-pickup').textContent = a.pickup_address;
            $('active-dropoff').textContent = a.dropoff_address;

            renderTrustBadge(a);
            renderNoShowState(a);

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

                if (data.offer) {
                    renderOffer(data.offer);
                } else if (data.active) {
                    if (countdownHandle) { clearInterval(countdownHandle); countdownHandle = null; }
                    lastOfferId = null;
                    renderActive(data.active, data.messages || []);
                } else {
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
    })();
    </script>
</body>
</html>
