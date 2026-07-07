{{--
    Açılır pencere (popup) reklamı — site geneli.
    'popup' slotunda aktif reklam varsa sayfa açılınca ortada bir kez gösterilir.
    Kapatılınca o oturumda tekrar çıkmaz. Aktif popup yoksa hiçbir şey render edilmez.
--}}
@php
    try {
        $popupAd = \App\Modules\Marketing\Models\Advertisement::activeFor('popup');
    } catch (\Throwable $e) {
        $popupAd = null;
    }
    if ($popupAd) { $popupAd->recordImpression(); }
@endphp

@if ($popupAd)
    <div id="ad-popup" data-ad-id="{{ $popupAd->id }}"
         class="fixed inset-0 z-[9999] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Reklam">
        {{-- Arka plan --}}
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" data-ad-popup-close></div>

        {{-- Kart --}}
        <div class="relative w-full max-w-md bg-zinc-950 border-2 border-brand/50 rounded-3xl overflow-hidden shadow-2xl shadow-brand/20 ad-popup-card">
            {{-- Kapat --}}
            <button type="button" data-ad-popup-close aria-label="Kapat"
                    class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-black/60 hover:bg-black/80 border border-white/15 text-white flex items-center justify-center transition text-lg leading-none">✕</button>

            {{-- Görsel --}}
            @if ($popupAd->image_src)
                <img src="{{ $popupAd->image_src }}" alt="{{ $popupAd->title }}" class="w-full h-52 object-cover">
            @else
                <div class="w-full h-40 bg-gradient-to-br from-brand/35 to-brand-700/20 flex items-center justify-center text-6xl font-black text-brand/80">★</div>
            @endif

            {{-- İçerik --}}
            <div class="p-6 text-center">
                <span class="inline-block text-[10px] font-extrabold uppercase tracking-widest text-black bg-brand px-2.5 py-1 rounded-full mb-3">★ Sponsorlu</span>
                @if ($popupAd->sponsor_name)
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-brand mb-1">{{ $popupAd->sponsor_name }}</div>
                @endif
                <div class="text-xl font-extrabold text-white leading-tight">{{ $popupAd->title }}</div>
                @if ($popupAd->description)
                    <p class="text-sm text-zinc-300/90 mt-2">{{ $popupAd->description }}</p>
                @endif

                <a href="{{ $popupAd->link_url ? route('ad.click', $popupAd) : '#' }}"
                   @if ($popupAd->link_url) target="_blank" rel="noopener sponsored" @endif
                   class="mt-5 w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-extrabold transition shadow-lg shadow-brand/30">
                    {{ $popupAd->cta_text ?: 'Fiyat Al' }}
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </a>
            </div>
        </div>
    </div>

    <style>
        @keyframes adPopIn { from { opacity: 0; transform: translateY(14px) scale(.96); } to { opacity: 1; transform: none; } }
        #ad-popup.flex .ad-popup-card { animation: adPopIn .3s ease-out; }
    </style>

    <script>
        (function () {
            var el = document.getElementById('ad-popup');
            if (!el) return;
            var key = 'ad_popup_dismissed_' + el.dataset.adId;
            try { if (sessionStorage.getItem(key)) return; } catch (e) {}

            function show() { el.classList.remove('hidden'); el.classList.add('flex'); }
            function hide() {
                el.classList.add('hidden');
                el.classList.remove('flex');
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
            }

            setTimeout(show, 1200); // sayfa oturduktan ~1.2 sn sonra çıksın
            el.querySelectorAll('[data-ad-popup-close]').forEach(function (b) {
                b.addEventListener('click', hide);
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
        })();
    </script>
@endif
