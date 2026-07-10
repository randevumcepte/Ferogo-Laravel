{{--
    Dosya upload widget'ı — sürücü başvuru formunda tekrarlı kullanılır.

    Parametreler:
      $name    → input adı
      $label   → başlık
      $hint    → alt açıklama
      $mode    → 'photo' (kamera + galeri, PDF kabul etmez) | 'document' (dosya + PDF)
      $capture → 'user' (ön kamera) | 'environment' (arka kamera) | null

    Selfie      → mode=photo, capture=user
    Kimlik/Ehliyet/Araç foto → mode=photo, capture=environment
    Belgeler (ruhsat, sigorta vs.) → mode=document (PDF de olur)
--}}
@php
    $mode    = $mode    ?? 'document';
    $capture = $capture ?? null;
    $accept  = $mode === 'photo' ? 'image/*' : 'image/*,application/pdf';
@endphp
<div class="file-upload-widget">
    <label class="block text-xs font-medium text-zinc-400 mb-2">{{ $label }}</label>

    <div class="relative rounded-xl border-2 border-dashed border-white/15 hover:border-brand/50 bg-white/[0.02] hover:bg-brand/[0.03] transition p-4 text-center">

        {{-- GIZLI INPUT'LAR (empty/preview'de paylaşılan) --}}
        @if($mode === 'photo')
            <input id="fu-cam-{{ $name }}" type="file" name="{{ $name }}"
                   accept="image/*"
                   @if($capture) capture="{{ $capture }}" @else capture @endif
                   class="hidden fu-input"
                   data-target="{{ $name }}"
                   @if(($required ?? true)) data-required="true" @endif>
            <input id="fu-gal-{{ $name }}" type="file"
                   accept="image/*"
                   class="hidden fu-input"
                   data-target="{{ $name }}"
                   data-mirror="fu-cam-{{ $name }}">
        @else
            <input id="fu-{{ $name }}" type="file" name="{{ $name }}"
                   accept="{{ $accept }}"
                   class="hidden fu-input"
                   data-target="{{ $name }}"
                   @if(($required ?? true)) data-required="true" @endif>
        @endif

        {{-- ÇARPI (X) — sadece preview'da görünür → dosyayı kaldırır ve empty state'e döner --}}
        <button type="button"
                class="fu-clear-btn absolute top-2 right-2 hidden w-8 h-8 rounded-full bg-red-500/90 hover:bg-red-500 text-white text-lg font-bold flex items-center justify-center shadow-lg shadow-red-500/40 transition z-10"
                data-clear-target="{{ $name }}"
                title="Dosyayı kaldır">
            ✕
        </button>

        {{-- EMPTY STATE (henüz dosya seçilmedi) --}}
        <div class="fu-empty-{{ $name }}">
            <div class="text-3xl mb-1">📷</div>
            <div class="text-xs text-zinc-300 font-semibold mb-1">
                @if($mode === 'photo') Fotoğraf çek / seç
                @else Dosya seç
                @endif
            </div>
            <div class="text-[10px] text-zinc-500 mb-3">{{ $hint ?? ($mode === 'photo' ? 'JPG / PNG' : 'JPG / PNG / PDF') }}</div>

            @if($mode === 'photo')
                <div class="grid grid-cols-2 gap-2">
                    <label for="fu-cam-{{ $name }}"
                           class="cursor-pointer inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-brand hover:bg-brand-600 text-black text-xs font-bold transition">
                        📷 Kamera
                    </label>
                    <label for="fu-gal-{{ $name }}"
                           class="cursor-pointer inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-white text-xs font-semibold transition">
                        🖼 Galeri
                    </label>
                </div>
            @else
                <label for="fu-{{ $name }}"
                       class="cursor-pointer inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-white text-xs font-semibold transition">
                    📎 Dosya seç
                </label>
            @endif
        </div>

        {{-- PREVIEW STATE (dosya seçildi) — sadece görsel + isim + ✓ Yüklendi. Değiştirmek için üstteki × butonuna basılır (kaldır), sonra tekrar yükle. --}}
        <div class="fu-preview-{{ $name }} hidden">
            <img id="fu-img-{{ $name }}" class="mx-auto mb-2 max-h-32 rounded-lg" alt="">
            <div id="fu-name-{{ $name }}" class="text-[10px] text-zinc-400 truncate mb-1"></div>
            <div class="text-[10px] text-emerald-400">✓ Yüklendi</div>
        </div>
    </div>
</div>
