{{--
    Reklam alanı (ad slot) — sunumdaki "REKLAM ALANLARI" slaytının canlı karşılığı.

    Kullanım:
        @include('partials.ad-slot', ['placement' => 'home_banner'])
        @include('partials.ad-slot', ['placement' => 'radar_map', 'class' => 'mb-8'])

    Süper adminde (Pazarlama → Reklam Alanları) o slot için aktif reklam varsa
    reklam gösterilir; yoksa altın kesikli "REKLAM ALANINIZ" boş alanı görünür.
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
       class="ad-slot group relative block overflow-hidden rounded-2xl border border-brand/40 bg-gradient-to-br from-brand/[0.12] to-brand/[0.02] shadow-lg shadow-black/20 hover:border-brand/70 transition {{ $slotClass }}">
        <span class="absolute top-2 right-2 z-10 text-[9px] uppercase tracking-widest text-brand/80 bg-black/50 px-1.5 py-0.5 rounded">Reklam</span>
        <div class="flex items-center gap-4 p-4 sm:p-5">
            @if ($ad->image_url)
                <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}"
                     class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl object-cover shrink-0" loading="lazy">
            @else
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl bg-brand/20 text-brand flex items-center justify-center text-2xl font-black shrink-0">★</div>
            @endif
            <div class="min-w-0 flex-1">
                @if ($ad->sponsor_name)
                    <div class="text-[10px] uppercase tracking-wider text-brand/80 mb-0.5 truncate">{{ $ad->sponsor_name }}</div>
                @endif
                <div class="text-sm sm:text-base font-bold text-white truncate">{{ $ad->title }}</div>
                @if ($ad->description)
                    <div class="text-xs text-zinc-400 mt-0.5 line-clamp-2">{{ $ad->description }}</div>
                @endif
            </div>
            @if ($ad->cta_text)
                <span class="shrink-0 px-4 py-2 rounded-full bg-brand text-black text-xs font-bold group-hover:bg-brand-600 transition whitespace-nowrap">{{ $ad->cta_text }}</span>
            @endif
        </div>
    </a>
@else
    {{-- Boş reklam alanı — süper adminden doldurulabilir --}}
    <div class="ad-slot ad-slot--empty relative rounded-2xl border-2 border-dashed border-brand/50 bg-gradient-to-br from-brand/[0.10] to-brand/[0.02] p-5 text-center {{ $slotClass }}">
        <div class="text-[10px] uppercase tracking-[0.25em] text-brand/70 mb-1">Reklam Alanı</div>
        <div class="text-brand font-extrabold text-sm sm:text-base">REKLAM ALANINIZ · {{ $slotLabel }}</div>
        <div class="text-[11px] text-zinc-400 mt-1">{{ $slotSeg }} · süper adminden yönetilir</div>
    </div>
@endif
