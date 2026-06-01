@extends('layouts.public')

@section('title', 'Rezervasyonlarım · Ferogo')

@section('content')
<div class="gradient-radial pt-24 pb-16 min-h-screen">
    <section class="px-6 py-8">
        <div class="max-w-3xl mx-auto">

            <h1 class="text-3xl md:text-4xl font-bold mb-2">Rezervasyonlarım</h1>
            <p class="text-zinc-400 mb-8">Geçmiş ve yaklaşan tüm rezervasyonların.</p>

            @if ($rides->isEmpty())
                <div class="rounded-2xl border border-white/10 bg-zinc-950 p-10 text-center">
                    <div class="text-zinc-400 mb-4">Henüz rezervasyonun yok.</div>
                    <a href="{{ route('home') }}" class="inline-block px-6 py-3 rounded-xl bg-brand hover:bg-brand-600 text-black text-sm font-bold transition">
                        Rezervasyon Oluştur
                    </a>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($rides as $ride)
                        @php
                            $statusLabel = match($ride->status) {
                                'reservation_pending_pool'        => 'Sürücü Aranıyor',
                                'reservation_accepted'            => 'Sürücü Atandı',
                                'reservation_reconfirm_requested' => 'Sürücüden Teyit Bekleniyor',
                                'reservation_confirmed'           => 'Onaylı',
                                'reservation_imminent'            => 'Yaklaşıyor',
                                'reservation_unmatched'           => 'Eşleşmedi',
                                'assigned'                        => 'Sürücü Yolda',
                                'driver_arriving'                 => 'Sürücü Yolda',
                                'in_progress'                     => 'Yolculukta',
                                'completed'                       => 'Tamamlandı',
                                'cancelled'                       => 'İptal Edildi',
                                default                           => $ride->status,
                            };
                            $statusClass = match($ride->status) {
                                'reservation_pending_pool'        => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                                'reservation_accepted'            => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                                'reservation_reconfirm_requested' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                                'reservation_confirmed'           => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                'reservation_imminent'            => 'bg-brand/15 text-brand border-brand/40',
                                'reservation_unmatched'           => 'bg-red-500/15 text-red-300 border-red-500/30',
                                'completed'                       => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                                'cancelled'                       => 'bg-zinc-700/30 text-zinc-400 border-white/10',
                                default                           => 'bg-white/5 text-zinc-300 border-white/10',
                            };
                            $canCancel = in_array($ride->status, [
                                'reservation_pending_pool', 'reservation_accepted',
                                'reservation_reconfirm_requested', 'reservation_confirmed',
                                'reservation_imminent',
                            ], true);
                        @endphp

                        <div class="rounded-2xl border border-white/10 bg-zinc-950 p-5">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                                <div class="text-lg font-extrabold text-brand">
                                    {{ number_format((float) $ride->total_fare, 0, ',', '.') }} ₺
                                </div>
                            </div>

                            <div class="text-xs text-zinc-500 mb-3">
                                {{ $ride->scheduled_at?->translatedFormat('d M Y H:i') ?? '—' }}
                                · {{ $ride->vehicleClass->name ?? '—' }}
                            </div>

                            <div class="space-y-2 mb-3">
                                <div class="flex items-start gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full bg-brand mt-1.5 shrink-0"></div>
                                    <div class="text-sm text-white">{{ $ride->pickup_address }}</div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <div class="w-2.5 h-2.5 rounded-sm bg-white mt-1.5 shrink-0"></div>
                                    <div class="text-sm text-zinc-300">{{ $ride->dropoff_address }}</div>
                                </div>
                            </div>

                            @if ($ride->driver && in_array($ride->status, ['reservation_accepted','reservation_reconfirm_requested','reservation_confirmed','reservation_imminent','assigned','driver_arriving','in_progress','completed'], true))
                                <div class="rounded-xl bg-black/40 border border-white/5 p-3 mb-3 text-sm">
                                    <div class="text-[10px] uppercase tracking-wider text-zinc-500 mb-1">Sürücün</div>
                                    <div class="text-white font-semibold">{{ $ride->driver->user->name }}</div>
                                    <div class="text-xs text-zinc-400">
                                        ★ {{ number_format((float) $ride->driver->rating, 2) }}
                                        @if ($ride->vehicleClass) · {{ $ride->vehicleClass->name }} @endif
                                        @if ($ride->callUnlocked()) · <span class="text-brand font-bold">📞 Arama açık</span> @endif
                                    </div>
                                </div>
                            @endif

                            @if ($canCancel)
                                <div class="flex justify-end">
                                    <button type="button"
                                            data-cancel-ride="{{ $ride->public_id }}"
                                            class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-red-500/15 border border-white/10 hover:border-red-500/30 text-xs text-zinc-300 hover:text-red-300 transition">
                                        İptal Et
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </section>
</div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-cancel-ride]');
        if (!btn) return;
        if (!confirm('Rezervasyonu iptal etmek istediğine emin misin?')) return;
        const pid = btn.dataset.cancelRide;
        btn.disabled = true;
        try {
            const r = await fetch(`/api/reservations/${pid}/cancel`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ reason: 'customer_request' }),
            });
            const data = await r.json();
            if (!r.ok || data.ok === false) throw new Error(data.message || ('HTTP ' + r.status));
            location.reload();
        } catch (err) {
            alert(err.message);
            btn.disabled = false;
        }
    });
})();
</script>
@endsection
