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

    <div class="rounded-xl border-2 border-dashed border-white/15 hover:border-brand/50 bg-white/[0.02] hover:bg-brand/[0.03] transition p-4 text-center">
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
                {{-- capture: kamerayı açan input --}}
                <input id="fu-cam-{{ $name }}" type="file" name="{{ $name }}"
                       accept="image/*"
                       @if($capture) capture="{{ $capture }}" @else capture @endif
                       class="hidden fu-input"
                       data-target="{{ $name }}"
                       @if(($required ?? true)) required @endif>
                {{-- galeri: normal file picker --}}
                <input id="fu-gal-{{ $name }}" type="file"
                       accept="image/*"
                       class="hidden fu-input"
                       data-target="{{ $name }}"
                       data-mirror="fu-cam-{{ $name }}">
            @else
                {{-- Belge modu — PDF de kabul, kamera kısıtı yok --}}
                <label for="fu-{{ $name }}"
                       class="cursor-pointer inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 text-white text-xs font-semibold transition">
                    📎 Dosya seç
                </label>
                <input id="fu-{{ $name }}" type="file" name="{{ $name }}"
                       accept="{{ $accept }}"
                       class="hidden fu-input"
                       data-target="{{ $name }}"
                       @if(($required ?? true)) required @endif>
            @endif
        </div>

        <div class="fu-preview-{{ $name }} hidden">
            <img id="fu-img-{{ $name }}" class="mx-auto mb-2 max-h-32 rounded-lg" alt="">
            <div id="fu-name-{{ $name }}" class="text-[10px] text-zinc-400 truncate"></div>
            <div class="text-[10px] text-brand mt-1">✓ Değiştirmek için üstteki butonlara bas</div>
        </div>
    </div>
</div>
