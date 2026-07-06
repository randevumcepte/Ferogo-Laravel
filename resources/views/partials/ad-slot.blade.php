{{--
    Reklam alanı (ad slot) — sunumdaki "REKLAM ALANLARI" slaytının canlı karşılığı.

    Kullanım:
        @include('partials.ad-slot', ['placement' => 'home_banner'])
        @include('partials.ad-slot', ['placement' => 'radar_map', 'class' => 'mb-8'])
        @include('partials.ad-slot', ['placement' => 'radar_sidebar', 'compact' => true])   (dar kolon)

    compact=true → dikey dizilim (görsel üstte), dar sidebar kolonları için.
    Görsel varsa gösterilir; yoksa "Görsel Alanı" yer tutucusu. Önerilen görsel: 1200×628 px.
--}}
@php
    $placement = $placement ?? 'home_banner';
    $slotClass = $class ?? '';
    $compact = $compact ?? false;
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
       class="ad-slot group relative block overflow-hidden rounded-3xl border-2 border-brand/50 bg-gradient-to-br from-brand/[0.16] via-brand/[0.05] to-transparent shadow-[0_14px_50px_-14px_rgba(240,192,64,0.5)] hover:border-brand/75 hover:-translate-y-0.5 transition duration-300 {{ $slotClass }}">

        {{-- Sponsorlu şeridi --}}
        <span class="absolute top-3 right-3 z-10 inline-flex items-center gap-1 text-[9px] sm:text-[10px] font-extrabold uppercase tracking-widest text-black bg-brand px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full shadow-lg shadow-brand/30">
            ★ Sponsorlu
        </span>

        <div class="flex items-stretch {{ $compact ? 'flex-col' : 'flex-col sm:flex-row min-h-[10.5rem]' }}">
            {{-- Görsel / görsel alanı --}}
            <div class="shrink-0 relative {{ $compact ? 'w-full' : 'sm:w-64 lg:w-72' }}">
                @if ($ad->image_url)
                    <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}"
                         class="w-full object-cover group-hover:scale-105 transition duration-500 {{ $compact ? 'h-32' : 'h-48 sm:h-full' }}" loading="lazy">
                @else
                    {{-- Görsel henüz yok: reklamverenin görselinin geleceği alan --}}
                    <div class="border-2 border-dashed border-brand/40 bg-brand/[0.06] flex flex-col items-center justify-center text-center gap-1.5 text-brand/75 rounded-2xl {{ $compact ? 'h-28 m-3' : 'h-40 sm:h-auto sm:absolute sm:inset-3 m-4 sm:m-0' }}">
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"></path></svg>
                        <div class="text-[11px] font-bold uppercase tracking-wider">Görsel Alanı</div>
                        <div class="text-[10px] text-brand/50">1200 × 628 px</div>
                    </div>
                @endif
            </div>

            {{-- İçerik --}}
            <div class="flex-1 flex flex-col justify-center gap-2 {{ $compact ? 'p-4' : 'p-5 sm:p-7' }}">
                @if ($ad->sponsor_name)
                    <div class="font-bold uppercase tracking-[0.22em] text-brand {{ $compact ? 'text-[10px]' : 'text-xs' }}">{{ $ad->sponsor_name }}</div>
                @endif
                <div class="font-extrabold text-white leading-tight {{ $compact ? 'text-base' : 'text-xl sm:text-2xl' }}">{{ $ad->title }}</div>
                @if ($ad->description)
                    <div class="text-zinc-300/90 line-clamp-2 max-w-xl {{ $compact ? 'text-xs' : 'text-sm' }}">{{ $ad->description }}</div>
                @endif
                @if ($ad->cta_text)
                    <div class="{{ $compact ? 'mt-2' : 'mt-3' }}">
                        <span class="inline-flex items-center gap-2 rounded-full bg-brand text-black font-extrabold group-hover:bg-brand-600 transition shadow-lg shadow-brand/30 {{ $compact ? 'px-4 py-2 text-xs' : 'px-6 py-3 text-sm' }}">
                            {{ $ad->cta_text }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </a>
@else
    {{-- Aktif reklam yok — süper adminden doldurulabilir boş alan --}}
    <div class="ad-slot ad-slot--empty relative rounded-3xl border-2 border-dashed border-brand/60 bg-gradient-to-br from-brand/[0.12] to-transparent text-center shadow-[0_12px_45px_-12px_rgba(240,192,64,0.3)] {{ $compact ? 'p-5' : 'p-8' }} {{ $slotClass }}">
        <div class="text-[11px] uppercase tracking-[0.3em] text-brand/80 mb-2">★ Reklam Alanı ★</div>
        <div class="text-brand font-extrabold {{ $compact ? 'text-base' : 'text-lg sm:text-2xl' }}">REKLAM ALANINIZ</div>
        <div class="text-sm text-zinc-300 mt-1.5">{{ $slotLabel }}</div>
        <div class="text-[11px] text-zinc-500 mt-2">{{ $slotSeg }}</div>
    </div>
@endif
