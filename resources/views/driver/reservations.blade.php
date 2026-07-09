<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rezervasyonlar · Sürücü Paneli</title>
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

<header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <a href="{{ route('driver.panel') }}" class="flex items-center gap-2 min-w-0">
            <span class="text-2xl font-extrabold tracking-tight">
                <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
            </span>
        </a>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('driver.panel') }}"
               class="px-3 py-2 rounded-xl text-xs font-semibold text-zinc-200 hover:text-white border border-white/10 hover:border-white/30 transition">
                Anlık Panel
            </a>
            <a href="{{ route('driver.packages.index') }}"
               class="px-3 py-2 rounded-xl text-xs font-semibold text-brand hover:text-black hover:bg-brand border border-brand/40 hover:border-brand transition">
                Paketler
            </a>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-6 space-y-5">

    <h1 class="text-2xl font-extrabold tracking-tight">Rezervasyon Pazarı</h1>
    <p class="text-sm text-zinc-400 -mt-3">Planlı (gelecek tarihli) yolculuklar burada listelenir. Beğendiğini sen alırsın — ilk kabul eden kazanır.</p>

    {{-- Sekme başlıkları --}}
    <div class="flex gap-2 border-b border-white/10">
        <button data-tab="market" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-brand text-brand">
            Pazardakiler
        </button>
        <button data-tab="mine" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-zinc-400 hover:text-white">
            Aldıklarım
        </button>
    </div>

    {{-- ===== Pazar ===== --}}
    <section id="tab-market">
        <div id="market-empty" class="hidden rounded-3xl border border-white/10 bg-zinc-950 p-8 text-center text-sm text-zinc-400">
            Şu an sana uygun rezervasyon yok. Yenileri geldikçe burada görünecek.
        </div>
        <div id="market-list" class="space-y-3"></div>
    </section>

    {{-- ===== Aldıklarım ===== --}}
    <section id="tab-mine" class="hidden">
        <div id="mine-empty" class="hidden rounded-3xl border border-white/10 bg-zinc-950 p-8 text-center text-sm text-zinc-400">
            Henüz almış olduğun rezervasyon yok.
        </div>
        <div id="mine-list" class="space-y-3"></div>
    </section>

</main>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const fmt = {
        money: (n) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(n || 0),
        km: (n) => (n != null ? (Math.round(n * 10) / 10).toString().replace('.', ',') + ' km' : '—'),
        date: (iso) => {
            if (!iso) return '—';
            try {
                const d = new Date(iso);
                return d.toLocaleString('tr-TR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            } catch (e) { return iso; }
        },
        timeUntil: (iso) => {
            if (!iso) return '';
            const ms = new Date(iso).getTime() - Date.now();
            if (ms <= 0) return 'şimdi';
            const h = Math.floor(ms / 3_600_000);
            const m = Math.floor((ms % 3_600_000) / 60_000);
            if (h >= 24) return Math.floor(h / 24) + ' gün ' + (h % 24) + ' saat';
            if (h > 0) return h + ' saat ' + m + ' dk';
            return m + ' dk';
        },
        statusLabel: (s) => ({
            'reservation_accepted': 'Beklemede',
            'reservation_reconfirm_requested': 'Teyit gerekli',
            'reservation_confirmed': 'Onaylı',
            'reservation_imminent': 'Yaklaşıyor',
        }[s] || s),
        statusClass: (s) => ({
            'reservation_accepted': 'bg-blue-500/15 text-blue-300 border-blue-500/30',
            'reservation_reconfirm_requested': 'bg-amber-500/15 text-amber-300 border-amber-500/30',
            'reservation_confirmed': 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
            'reservation_imminent': 'bg-brand/15 text-brand border-brand/40',
        }[s] || 'bg-white/5 text-zinc-300 border-white/10'),
    };

    async function api(url, init = {}) {
        const opts = {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(init.headers || {}) },
            ...init,
        };
        if (opts.method && opts.method !== 'GET') {
            opts.headers['X-CSRF-TOKEN'] = csrf;
            opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
        }
        const r = await fetch(url, opts);
        let data = null;
        try { data = await r.json(); } catch (e) {}
        if (!r.ok || (data && data.ok === false)) {
            throw new Error((data && data.message) || ('HTTP ' + r.status));
        }
        return data;
    }

    // Karşılama (uçak/tren/otogar) rozeti
    function transportChip(r) {
        if (!r.transport_type) return '';
        const parts = [r.transport_icon || '', r.transport_label || ''];
        if (r.transport_code) parts.push('· ' + escapeHtml(r.transport_code));
        if (r.transport_scheduled_at) parts.push('· varış ' + fmt.date(r.transport_scheduled_at));
        return `<div class="mb-2 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-brand/10 border border-brand/30 text-[11px] font-semibold text-brand">${parts.join(' ')}</div>`;
    }

    // Yolcunun canlı durumu (sadece "Aldıklarım")
    function paxChip(r) {
        if (!r.pax_status) return '';
        const map = {
            on_way:  ['🚶 Yolcu yola çıktı', 'bg-blue-500/15 text-blue-300 border-blue-500/30'],
            arrived: ['✅ Yolcu geldi, bekliyor', 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'],
            delayed: ['⏳ Yolcu gecikecek', 'bg-amber-500/15 text-amber-300 border-amber-500/30'],
        };
        const [label, cls] = map[r.pax_status] || [r.pax_status_label || r.pax_status, 'bg-white/5 text-zinc-300 border-white/10'];
        const when = r.pax_status_at ? ' · ' + fmt.date(r.pax_status_at) : '';
        return `<div class="mb-2 inline-flex items-center px-2.5 py-1 rounded-lg border text-[11px] font-bold ${cls}">${label}${when}</div>`;
    }

    // Ücretsiz bekleme bilgisi
    function freeWaitLine(r) {
        if (!r.free_wait_until) return '';
        return `<div class="text-[11px] text-zinc-500 mb-2">Ücretsiz bekleme: <span class="text-zinc-300">${r.free_wait_minutes} dk</span> (≈ ${fmt.date(r.free_wait_until)}'e kadar)</div>`;
    }

    function marketCard(r) {
        return `
        <div class="rounded-2xl border border-white/10 bg-zinc-950 p-4 hover:border-brand/40 transition">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div class="text-xs text-zinc-500 uppercase tracking-wider">${fmt.date(r.scheduled_at)} · <span class="text-zinc-300">${fmt.timeUntil(r.scheduled_at)} sonra</span></div>
                <div class="text-lg font-extrabold text-brand">${fmt.money(r.total_fare)}</div>
            </div>
            ${transportChip(r)}
            <div class="space-y-2 mb-3">
                <div class="flex items-start gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-brand mt-1.5 shrink-0"></div>
                    <div class="text-sm text-white">${escapeHtml(r.pickup_address)}</div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-2.5 h-2.5 rounded-sm bg-white mt-1.5 shrink-0"></div>
                    <div class="text-sm text-zinc-300">${escapeHtml(r.dropoff_address)}</div>
                </div>
            </div>
            <div class="flex items-center justify-between gap-2 text-xs text-zinc-500">
                <div>${fmt.km(r.distance_km)} · ${r.duration_minutes ?? 0} dk · ${r.passenger_count} yolcu</div>
                <button data-accept="${r.public_id}" class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-600 text-black text-xs font-extrabold transition">Kabul Et</button>
            </div>
        </div>`;
    }

    function mineCard(r) {
        const needsReconfirm = r.status === 'reservation_reconfirm_requested';
        return `
        <div class="rounded-2xl border ${needsReconfirm ? 'border-amber-500/40' : 'border-white/10'} bg-zinc-950 p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider ${fmt.statusClass(r.status)}">${fmt.statusLabel(r.status)}</span>
                <div class="text-lg font-extrabold text-brand">${fmt.money(r.total_fare)}</div>
            </div>
            <div class="text-xs text-zinc-500 mb-2">${fmt.date(r.scheduled_at)} · <span class="text-zinc-300">${fmt.timeUntil(r.scheduled_at)} sonra</span></div>
            ${transportChip(r)}
            ${paxChip(r)}
            ${freeWaitLine(r)}
            <div class="text-xs text-zinc-400 mb-3">Müşteri: <span class="text-white font-semibold">${escapeHtml(r.customer_name || '—')}</span></div>
            <div class="space-y-2 mb-3">
                <div class="flex items-start gap-2"><div class="w-2.5 h-2.5 rounded-full bg-brand mt-1.5 shrink-0"></div><div class="text-sm text-white">${escapeHtml(r.pickup_address)}</div></div>
                <div class="flex items-start gap-2"><div class="w-2.5 h-2.5 rounded-sm bg-white mt-1.5 shrink-0"></div><div class="text-sm text-zinc-300">${escapeHtml(r.dropoff_address)}</div></div>
            </div>
            <div class="flex items-center justify-between gap-2">
                <button data-cancel="${r.public_id}" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-red-500/15 border border-white/10 hover:border-red-500/30 text-xs text-zinc-300 hover:text-red-300 transition">Vazgeç</button>
                ${needsReconfirm
                    ? `<button data-confirm="${r.public_id}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-black text-xs font-extrabold transition">✅ Teyit Ver</button>`
                    : (r.status === 'reservation_imminent'
                        ? `<span class="text-xs text-brand font-semibold">📞 Arama açık</span>`
                        : `<span class="text-xs text-zinc-500">${r.driver_reconfirmed_at ? 'Teyit verildi' : 'Bekleniyor'}</span>`)
                }
            </div>
        </div>`;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function loadMarket() {
        const list = document.getElementById('market-list');
        const empty = document.getElementById('market-empty');
        try {
            const data = await api('/surucu-paneli/api/reservations/market');
            const items = data.reservations || [];
            list.innerHTML = items.map(marketCard).join('');
            empty.classList.toggle('hidden', items.length > 0);
            if (items.length === 0 && data.message) {
                empty.textContent = data.message;
            }
        } catch (e) {
            list.innerHTML = `<div class="text-sm text-red-400">Yüklenemedi: ${escapeHtml(e.message)}</div>`;
        }
    }

    async function loadMine() {
        const list = document.getElementById('mine-list');
        const empty = document.getElementById('mine-empty');
        try {
            const data = await api('/surucu-paneli/api/reservations/mine');
            const items = data.reservations || [];
            list.innerHTML = items.map(mineCard).join('');
            empty.classList.toggle('hidden', items.length > 0);
        } catch (e) {
            list.innerHTML = `<div class="text-sm text-red-400">Yüklenemedi: ${escapeHtml(e.message)}</div>`;
        }
    }

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach((b) => {
                const active = b.dataset.tab === tab;
                b.classList.toggle('border-brand', active);
                b.classList.toggle('text-brand', active);
                b.classList.toggle('border-transparent', !active);
                b.classList.toggle('text-zinc-400', !active);
            });
            document.getElementById('tab-market').classList.toggle('hidden', tab !== 'market');
            document.getElementById('tab-mine').classList.toggle('hidden', tab !== 'mine');
            if (tab === 'market') loadMarket(); else loadMine();
        });
    });

    // Delegated handlers
    document.addEventListener('click', async (e) => {
        const acceptBtn = e.target.closest('[data-accept]');
        const confirmBtn = e.target.closest('[data-confirm]');
        const cancelBtn = e.target.closest('[data-cancel]');

        if (acceptBtn) {
            const pid = acceptBtn.dataset.accept;
            acceptBtn.disabled = true; acceptBtn.textContent = '…';
            try {
                await api(`/surucu-paneli/api/reservations/${pid}/accept`, { method: 'POST' });
                await loadMarket();
                await loadMine();
            } catch (err) {
                alert(err.message);
                acceptBtn.disabled = false; acceptBtn.textContent = 'Kabul Et';
            }
        } else if (confirmBtn) {
            const pid = confirmBtn.dataset.confirm;
            confirmBtn.disabled = true; confirmBtn.textContent = '…';
            try {
                await api(`/surucu-paneli/api/reservations/${pid}/confirm`, { method: 'POST' });
                await loadMine();
            } catch (err) {
                alert(err.message);
                confirmBtn.disabled = false; confirmBtn.textContent = '✅ Teyit Ver';
            }
        } else if (cancelBtn) {
            if (!confirm('Bu rezervasyondan vazgeçmek istediğine emin misin? Puanın düşebilir.')) return;
            const pid = cancelBtn.dataset.cancel;
            cancelBtn.disabled = true;
            try {
                await api(`/surucu-paneli/api/reservations/${pid}/cancel`, {
                    method: 'POST', body: JSON.stringify({ reason: 'driver_cancelled' }),
                });
                await loadMine();
            } catch (err) {
                alert(err.message);
                cancelBtn.disabled = false;
            }
        }
    });

    // İlk yükleme + 30 sn'de bir refresh
    loadMarket();
    setInterval(() => {
        const marketVisible = !document.getElementById('tab-market').classList.contains('hidden');
        if (marketVisible) loadMarket(); else loadMine();
    }, 30_000);
})();
</script>
</body>
</html>
