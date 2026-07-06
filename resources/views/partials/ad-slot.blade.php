{{--
    Reklam alanı (ad slot) — sunumdaki "REKLAM ALANLARI" slaytının canlı karşılığı.

    Kullanım:
        @include('partials.ad-slot', ['placement' => 'home_banner'])
        @include('partials.ad-slot', ['placement' => 'radar_map', 'class' => 'mb-8'])

    Süper adminde (Pazarlama → Reklam Alanları) o slot için aktif reklam varsa
    reklam gösterilir; yoksa altın kesikli "REKLAM ALANINIZ" boş alanı görünür.

    Görsel önerilen ölçü: 1200×628 px (yatay 1.91:1), JPG/PNG.
--}}
@php
    $placement = $placement ?? 'home_banner';
    $slotClass = $class ?? '';
    try {
        $ad = \App\Modules\Marketing\Models\Advertisement::activeFor($placement);
    } catch (\Throwable $e) {
        $ad = null; // tablo henüz migrate edilmemişse sayfa kırılmasın
    }
    $slotLabel = \App\Modules\Marketing\Models\Advertisement::PLACEMENTS[$placement] ?? 'Reklam';
    $slotSeg = \App\Modules\Marketing\Models\Advertisement::PLACEMENT_SEGMENTS[$placement] ?? '';
    if ($ad) { $ad->recordImpression(); }
@endphp

@if ($ad)
    <a href="{{ $ad->link_url ? route('ad.click', $ad) : '#' }}"
       @if ($ad->link_url) target="_blank" rel="noopener sponsored" @endif
       class="ad-slot group relative block overflow-hidden rounded-3xl border border-brand/50 bg-gradient-to-br from-brand/[0.18] via-brand/[0.06] to-transparent ring-1 ring-brand/20 shadow-[0_12px_45px_-12px_rgba(240,192,64,0.45)] hover:border-brand/70 hover:ring-brand/40 hover:-translate-y-0.5 transition duration-300 {{ $slotClass }}">

        {{-- Sponsorlu şeridi --}}
        <span class="absolute top-3 right-3 z-10 inline-flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-widest text-black bg-brand px-2.5 py-1 rounded-full shadow-lg shadow-brand/30">
            ★ Sponsorlu
        </span>

        <div class="flex flex-col sm:flex-row items-stretch">
            {{-- Görsel alanı --}}
            <div class="sm:w-56 lg:w-64 shrink-0 relative overflow-hidden">
                @if ($ad->image_url)
                    <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}"
                         class="w-full h-44 sm:h-full object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent to-black/10 sm:to-transparent pointer-events-none"></div>
                @else
                    <div class="w-full h-44 sm:h-full min-h-[9rem] bg-gradient-to-br from-brand/35 to-brand-700/20 flex items-center justify-center text-6xl font-black text-brand/80">★</div>
                @endif
            </div>

            {{-- İçerik --}}
            <div class="flex-1 p-5 sm:p-7 flex flex-col justify-center gap-2">
                @if ($ad->sponsor_name)
                    <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-brand">{{ $ad->sponsor_name }}</div>
                @endif
                <div class="text-lg sm:text-2xl font-extrabold text-white leading-tight">{{ $ad->title }}</div>
                @if ($ad->description)
                    <div class="text-sm text-zinc-300/90 line-clamp-2 max-w-xl">{{ $ad->description }}</div>
                @endif
                @if ($ad->cta_text)
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-brand text-black text-sm font-extrabold group-hover:bg-brand-600 transition shadow-lg shadow-brand/30">
                            {{ $ad->cta_text }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </a>
@else
    {{-- Boş reklam alanı — süper adminden doldurulabilir --}}
    <div class="ad-slot ad-slot--empty relative rounded-3xl border-2 border-dashed border-brand/60 bg-gradient-to-br from-brand/[0.12] to-transparent p-8 text-center shadow-[0_12px_45px_-12px_rgba(240,192,64,0.3)] {{ $slotClass }}">
        <div class="text-[11px] uppercase tracking-[0.3em] text-brand/80 mb-2">★ Reklam Alanı ★</div>
        <div class="text-brand font-extrabold text-lg sm:text-2xl">REKLAM ALANINIZ</div>
        <div class="text-sm text-zinc-300 mt-1.5">{{ $slotLabel }} · {{ $slotSeg }}</div>
        <div class="text-[11px] text-zinc-500 mt-2">Süper admin → Pazarlama → Reklam Alanları’ndan yönetilir</div>
    </div>
@endif
