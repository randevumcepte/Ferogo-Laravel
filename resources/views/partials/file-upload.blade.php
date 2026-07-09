{{--
    Basit dosya upload widget'ı. Sürücü başvuru formunda tekrar tekrar
    kullanılır. Preview + boyut kontrol JS ile.

    Kullanım:
      @include('partials.file-upload', ['name' => 'selfie', 'label' => 'Selfie', 'hint' => 'Yüzün net'])
--}}
<div class="file-upload-widget">
    <label class="block text-xs font-medium text-zinc-400 mb-2">{{ $label }}</label>
    <label for="fu-{{ $name }}"
           class="block cursor-pointer rounded-xl border-2 border-dashed border-white/15 hover:border-brand/50 bg-white/[0.02] hover:bg-brand/[0.03] transition p-4 text-center">
        <div class="fu-empty-{{ $name }}">
            <div class="text-3xl mb-1">📷</div>
            <div class="text-xs text-zinc-300 font-semibold">Dosya seç / fotoğraf çek</div>
            <div class="text-[10px] text-zinc-500 mt-1">{{ $hint ?? 'JPG / PNG / PDF' }}</div>
        </div>
        <div class="fu-preview-{{ $name }} hidden">
            <img id="fu-img-{{ $name }}" class="mx-auto mb-2 max-h-32 rounded-lg" alt="">
            <div id="fu-name-{{ $name }}" class="text-[10px] text-zinc-400 truncate"></div>
            <div class="text-[10px] text-brand mt-1">✓ Değiştirmek için tekrar tıkla</div>
        </div>
    </label>
    <input id="fu-{{ $name }}" type="file" name="{{ $name }}"
           accept="{{ $accept ?? 'image/*,application/pdf' }}"
           class="hidden fu-input"
           data-target="{{ $name }}"
           @if(($required ?? true)) required @endif>
</div>
