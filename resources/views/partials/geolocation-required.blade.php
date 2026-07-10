{{--
    Konum-Zorunlu Modal + JS.

    Kullanım:
      @include('partials.geolocation-required', ['role' => 'passenger' | 'driver'])
      window.GeolocationGate.require({
        onGranted: (coords) => { ... },
        onDenied: () => { ... },  // opsiyonel — genelde modal zaten göster
      });

    Kapatılamaz (X yok, ESC yok, dış tıklama yok). Yalnızca "Konumu Aç" ya
    da "Tekrar Dene" butonuna basınca navigator.geolocation tekrar denenir.
    Başarılı olursa modal otomatik kapanır.
--}}
@php
    $role = $role ?? 'passenger';
    $roleReason = $role === 'driver'
        ? 'Yolcular seni haritada bulabilsin, yakınındaki talepleri alabilesin'
        : 'Yakındaki sürücüleri görüp doğru mesafeyi/süreyi hesaplayabilelim';
@endphp
<div id="geo-required-modal"
     class="hidden fixed inset-0 z-[99998] bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="geo-req-title">
    <div class="w-full max-w-md bg-zinc-950 border-2 border-brand rounded-3xl shadow-2xl shadow-brand/30 overflow-hidden">
        {{-- Header --}}
        <div class="p-6 md:p-7 border-b border-white/5 text-center">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-brand/15 border-2 border-brand/40 flex items-center justify-center text-4xl animate-pulse">
                📍
            </div>
            <h2 id="geo-req-title" class="text-xl md:text-2xl font-extrabold text-white mb-2">
                Konumunu açman gerekiyor
            </h2>
            <p class="text-sm text-zinc-400 leading-relaxed">
                FerXGo paylaşımlı yolculuk için konum bilgin şart.<br>
                <span class="text-brand font-semibold">{{ $roleReason }}.</span>
            </p>
        </div>

        {{-- Adım adım (nasıl açılır) --}}
        <div class="p-6 md:p-7 border-b border-white/5">
            <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500 mb-3">Nasıl açılır?</div>
            <ol class="text-sm text-zinc-300 space-y-2.5">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>
                    <span>Alttaki <strong class="text-brand">"Konumu Aç"</strong> butonuna bas.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>
                    <span>Tarayıcı sana <strong>izin sorusu</strong> soracak → <strong class="text-brand">"İzin Ver"</strong> seç.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">3</span>
                    <span>Daha önce reddettiysen adres çubuğundaki <strong>🔒 / ⓘ</strong> simgesine tıkla → "Konum → İzin Ver".</span>
                </li>
            </ol>
        </div>

        {{-- Hata mesajı alanı --}}
        <div id="geo-req-error" class="hidden mx-6 my-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300">
        </div>

        {{-- Aksiyonlar --}}
        <div class="p-6 md:p-7 space-y-3">
            <button type="button" id="geo-req-retry-btn"
                    class="w-full inline-flex items-center justify-center gap-2 px-6 py-4 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-base transition shadow-xl shadow-brand/30">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                <span id="geo-req-btn-label">Konumu Aç</span>
            </button>

            <div class="text-[10px] text-zinc-500 text-center">
                Konum yalnızca yolculuk süresince kullanılır ·
                <a href="{{ route('legal.kvkk') }}" target="_blank" class="text-zinc-400 hover:text-brand underline underline-offset-2">KVKK Aydınlatma</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const modal    = document.getElementById('geo-required-modal');
    const errorBox = document.getElementById('geo-req-error');
    const retryBtn = document.getElementById('geo-req-retry-btn');
    const btnLabel = document.getElementById('geo-req-btn-label');

    let successCb = null;
    let deniedCb  = null;

    function show(errorText) {
        modal.classList.remove('hidden');
        // ESC/dış tıklama yok — kapatılamaz
        if (errorText) {
            errorBox.textContent = errorText;
            errorBox.classList.remove('hidden');
        } else {
            errorBox.classList.add('hidden');
        }
    }

    function hide() {
        modal.classList.add('hidden');
    }

    function tryGeolocation() {
        if (! navigator.geolocation) {
            show('Bu tarayıcı konum servisini desteklemiyor. Chrome/Safari/Firefox güncel sürümünü kullan.');
            return;
        }

        btnLabel.textContent = 'İzin isteniyor…';
        retryBtn.disabled = true;

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                btnLabel.textContent = '✓ Alındı';
                retryBtn.disabled = false;
                hide();
                if (typeof successCb === 'function') {
                    successCb({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy });
                }
            },
            (err) => {
                btnLabel.textContent = 'Tekrar Dene';
                retryBtn.disabled = false;
                let msg = 'Konum alınamadı.';
                switch (err.code) {
                    case 1: // PERMISSION_DENIED
                        msg = 'İzin reddedildi. Adres çubuğundaki 🔒 simgesine tıklayıp "Konum → İzin Ver" yap, sonra "Tekrar Dene"ye bas.';
                        break;
                    case 2: // POSITION_UNAVAILABLE
                        msg = 'Konum servisi şu an yanıt vermiyor. Cihazının GPS/konum ayarını aç, tekrar dene.';
                        break;
                    case 3: // TIMEOUT
                        msg = 'Konum alma zaman aşımına uğradı. Tekrar deneyebilirsin.';
                        break;
                }
                show(msg);
                if (typeof deniedCb === 'function') deniedCb(err);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    retryBtn.addEventListener('click', tryGeolocation);

    // Public API
    window.GeolocationGate = {
        require: (opts) => {
            successCb = opts?.onGranted || null;
            deniedCb  = opts?.onDenied  || null;
            tryGeolocation();
        },
        show,  // manuel açma (gerekirse)
        hide,  // manuel kapama (izin gelince otomatik zaten)
    };
})();
</script>
