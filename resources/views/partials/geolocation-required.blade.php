{{--
    Konum-Zorunlu Modal + JS.
    Platform-aware (iOS Safari / Android Chrome / Desktop için ayrı görsel rehber).

    Kullanım:
      @include('partials.geolocation-required', ['role' => 'passenger' | 'driver'])
      window.GeolocationGate.require({
        onGranted: (coords) => { ... },
        onDenied: () => { ... },
      });

    Kapatılamaz (X yok, ESC yok, dış tıklama yok). İzin verilene kadar açık.
--}}
@php
    $role = $role ?? 'passenger';
    $roleReason = $role === 'driver'
        ? 'Yolcular seni haritada bulabilsin, yakınındaki talepleri alabilesin'
        : 'Yakındaki sürücüleri görüp doğru mesafeyi/süreyi hesaplayabilelim';
@endphp
<div id="geo-required-modal"
     class="hidden fixed inset-0 z-[99998] bg-black/85 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
     role="dialog" aria-modal="true" aria-labelledby="geo-req-title">
    <div class="w-full max-w-md bg-zinc-950 border-2 border-brand rounded-3xl shadow-2xl shadow-brand/30 overflow-hidden my-4">
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

        {{-- ADIM ADIM REHBER — JS platforma göre render eder --}}
        <div id="geo-req-guide" class="p-6 md:p-7 border-b border-white/5">
            <div class="text-[10px] uppercase tracking-[0.25em] text-zinc-500 mb-3">
                <span id="geo-req-guide-title">Nasıl açılır?</span>
            </div>
            <div id="geo-req-guide-body"></div>
        </div>

        {{-- Hata mesajı alanı --}}
        <div id="geo-req-error" class="hidden mx-6 my-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300">
        </div>

        {{-- Aksiyonlar --}}
        <div class="p-6 md:p-7 space-y-2.5">
            <button type="button" id="geo-req-retry-btn"
                    class="w-full inline-flex items-center justify-center gap-2 px-6 py-4 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-base transition shadow-xl shadow-brand/30">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                <span id="geo-req-btn-label">Konumu Aç</span>
            </button>

            {{-- Sayfayı yenile — kullanıcı ayarlardan izni açtıysa --}}
            <button type="button" id="geo-req-reload-btn"
                    class="hidden w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-sm font-semibold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>İzni açtım — Sayfayı Yenile</span>
            </button>

            {{-- Otomatik yeniden dene — arka planda dinler --}}
            <div id="geo-req-autopoll" class="hidden text-center text-[11px] text-emerald-400/80">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    İzin bekleniyor… bulur bulmaz otomatik geçeceğiz
                </span>
            </div>

            {{-- Konumsuz devam et — yalnızca yolcu tarafında, ilk başarısızlıktan sonra görünür.
                 Konum vermeyen/veremeyen kullanıcı en azından haritayı görüp devam edebilsin (sürücü tarafında YOK). --}}
            <button type="button" id="geo-req-skip-btn"
                    class="hidden w-full text-center text-xs text-zinc-500 hover:text-zinc-300 underline underline-offset-4 pt-1">
                Şimdilik konumsuz devam et
            </button>

            <div class="text-[10px] text-zinc-500 text-center pt-1">
                Konum yalnızca yolculuk süresince kullanılır ·
                <a href="{{ route('legal.kvkk') }}" target="_blank" class="text-zinc-400 hover:text-brand underline underline-offset-2">KVKK Aydınlatma</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const modal      = document.getElementById('geo-required-modal');
    const errorBox   = document.getElementById('geo-req-error');
    const retryBtn   = document.getElementById('geo-req-retry-btn');
    const btnLabel   = document.getElementById('geo-req-btn-label');
    const reloadBtn  = document.getElementById('geo-req-reload-btn');
    const skipBtn    = document.getElementById('geo-req-skip-btn');
    const autoPoll   = document.getElementById('geo-req-autopoll');
    const guideTitle = document.getElementById('geo-req-guide-title');
    const guideBody  = document.getElementById('geo-req-guide-body');

    let successCb = null;
    let deniedCb  = null;
    let skipCb    = null;   // "konumsuz devam et" — yalnızca yolcu tarafı geçer
    let permissionPoll = null;
    let geoAttempt = 0;     // GEO_LADDER içindeki deneme indeksi

    // Konum alma merdiveni — izin açıkken bile TIMEOUT olmasın diye kademeli:
    //  0) Yüksek doğruluk, ama son 5 dk'daki GPS fix'i varsa anında dön (maximumAge)
    //     → çoğu cihazda önbellekte fix vardır, saniyesinde açılır, zaman aşımı olmaz.
    //  1) Yüksek doğruluk, daha sabırlı (soğuk GPS / yavaş ağ için 25 sn).
    //  2) SON ÇARE: düşük doğruluk — kaba konum döner (İzmir'e sapabilir) ama kullanıcı
    //     hiç takılmaz; arka plandaki refineUserLocation() GPS gelince pini düzeltir.
    // enableHighAccuracy:false'ı SADECE son basamakta kullanıyoruz (ilk seçenek yapılırsa
    // IP/baz-istasyonuna düşüp Dikili yerine İzmir/Karabağlar gösteriyordu).
    const GEO_LADDER = [
        { enableHighAccuracy: true,  timeout: 15000, maximumAge: 300000 },
        { enableHighAccuracy: true,  timeout: 25000, maximumAge: 600000 },
        { enableHighAccuracy: false, timeout: 12000, maximumAge: 600000 },
    ];

    // ── Platform detect ──────────────────────────────────
    const ua = navigator.userAgent || '';
    const isIOS     = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    const isSafari  = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);
    const isIOSSafari = isIOS && isSafari;
    const isAndroid = /android/i.test(ua);
    const isChrome  = /chrome|crios/i.test(ua) && !/edg|opr|samsung/i.test(ua);
    const isMobile  = isIOS || isAndroid;
    const isMac     = /Macintosh|Mac OS X/i.test(ua) && !isIOS;

    // ── Platform-özel görsel adım rehberi ────────────────
    function initialGuideHtml() {
        if (isIOSSafari) return iosSafariGuide();
        if (isAndroid && isChrome) return androidChromeGuide();
        return desktopGuide();
    }

    function iosSafariGuide() {
        return `
            <ol class="space-y-2.5 text-sm text-zinc-300">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>
                    <span>Alttaki <strong class="text-brand">"Konumu Aç"</strong> butonuna bas.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>
                    <span>Safari'nin çıkardığı popup'ta <strong class="text-brand">"İzin Ver"</strong> seç.</span>
                </li>
            </ol>
        `;
    }

    function iosSafariDeniedGuide() {
        return `
            <div class="rounded-xl bg-white/[0.03] border border-white/10 p-4 mb-3">
                <div class="text-xs text-brand font-bold mb-2 uppercase tracking-wider">📱 iPhone Safari İçin</div>
                <ol class="space-y-3 text-sm text-zinc-300">
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>
                        <div>
                            <div>Ekranın <strong>en üstündeki adres çubuğunun solundaki</strong>
                                <span class="inline-flex items-center justify-center w-8 h-6 rounded-md bg-zinc-800 border border-white/10 text-white text-[11px] font-bold">AA</span>
                                simgesine bas.
                            </div>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>
                        <div>Açılan menüde <strong class="text-brand">"Web Sitesi Ayarları"</strong>na bas.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">3</span>
                        <div><strong>"Konum"</strong> satırında <strong class="text-brand">"İzin Ver"</strong>i seç.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">4</span>
                        <div>Alttaki <strong class="text-emerald-400">"İzni açtım — Sayfayı Yenile"</strong> butonuna bas.</div>
                    </li>
                </ol>
            </div>
            <div class="text-[11px] text-zinc-500 text-center">
                Alternatif: iPhone Ayarlar → Safari → Konum → "Sor" seç, sonra sayfayı yenile.
            </div>
        `;
    }

    function androidChromeGuide() {
        return `
            <ol class="space-y-2.5 text-sm text-zinc-300">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>
                    <span>Alttaki <strong class="text-brand">"Konumu Aç"</strong> butonuna bas.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>
                    <span>Chrome'un çıkardığı popup'ta <strong class="text-brand">"İzin Ver"</strong> seç.</span>
                </li>
            </ol>
        `;
    }

    function androidChromeDeniedGuide() {
        return `
            <div class="rounded-xl bg-white/[0.03] border border-white/10 p-4 mb-3">
                <div class="text-xs text-brand font-bold mb-2 uppercase tracking-wider">📱 Android Chrome İçin</div>
                <ol class="space-y-3 text-sm text-zinc-300">
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>
                        <div>Adres çubuğunun solundaki <strong>🔒 kilit</strong> simgesine bas.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>
                        <div><strong>"İzinler"</strong> ya da <strong>"Site ayarları"</strong>na bas.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">3</span>
                        <div><strong>"Konum"</strong>a bas → <strong class="text-brand">"İzin Ver"</strong>i seç.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">4</span>
                        <div>Alttaki <strong class="text-emerald-400">"İzni açtım — Sayfayı Yenile"</strong> butonuna bas.</div>
                    </li>
                </ol>
            </div>
        `;
    }

    function desktopGuide() {
        return `
            <ol class="space-y-2.5 text-sm text-zinc-300">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>
                    <span>Alttaki <strong class="text-brand">"Konumu Aç"</strong> butonuna bas.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-brand/20 text-brand font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>
                    <span>Tarayıcının üst kısmındaki popup'ta <strong class="text-brand">"İzin Ver"</strong> seç.</span>
                </li>
            </ol>
        `;
    }

    function desktopDeniedGuide() {
        return `
            <div class="rounded-xl bg-white/[0.03] border border-white/10 p-4 mb-3">
                <div class="text-xs text-brand font-bold mb-2 uppercase tracking-wider">💻 Masaüstü İçin</div>
                <ol class="space-y-3 text-sm text-zinc-300">
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>
                        <div>Adres çubuğunun solundaki <strong>🔒 kilit</strong> simgesine tıkla.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>
                        <div><strong>"Site ayarları"</strong>na tıkla.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">3</span>
                        <div><strong>Konum</strong> → <strong class="text-brand">"İzin Ver"</strong>i seç.</div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand text-black font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">4</span>
                        <div>Alttaki <strong class="text-emerald-400">"İzni açtım — Sayfayı Yenile"</strong> butonuna bas.</div>
                    </li>
                </ol>
            </div>
        `;
    }

    function renderGuide(state) {
        // state: 'initial' | 'denied'
        if (state === 'denied') {
            guideTitle.textContent = 'Konum kapalı — nasıl açacaksın?';
            if (isIOSSafari)               guideBody.innerHTML = iosSafariDeniedGuide();
            else if (isAndroid && isChrome) guideBody.innerHTML = androidChromeDeniedGuide();
            else                            guideBody.innerHTML = desktopDeniedGuide();
            reloadBtn.classList.remove('hidden');
            autoPoll.classList.remove('hidden');
            startPermissionPoll(); // Permissions API destekliyorsa arka planda dinle
        } else {
            guideTitle.textContent = 'Nasıl açılır?';
            guideBody.innerHTML = initialGuideHtml();
            reloadBtn.classList.add('hidden');
            autoPoll.classList.add('hidden');
            stopPermissionPoll();
        }
    }

    function show(errorText, deniedState) {
        modal.classList.remove('hidden');
        if (errorText) {
            errorBox.textContent = errorText;
            errorBox.classList.remove('hidden');
        } else {
            errorBox.classList.add('hidden');
        }
        renderGuide(deniedState ? 'denied' : 'initial');
    }

    function hide() {
        modal.classList.add('hidden');
        stopPermissionPoll();
    }

    // Permissions API — kullanıcı ayarlardan izni açınca otomatik yakala
    function startPermissionPoll() {
        if (permissionPoll || !navigator.permissions || !navigator.permissions.query) return;
        // Bazı iOS versiyonlarında `navigator.permissions.query` yok — polling düşer, kullanıcı butona basar
        navigator.permissions.query({ name: 'geolocation' }).then(result => {
            if (result.state === 'granted') {
                // İzin çoktan verilmiş — hemen konum al
                tryGeolocation();
                return;
            }
            result.onchange = () => {
                if (result.state === 'granted') tryGeolocation();
            };
            // Ayrıca 3 sn'de bir tekrar sor (Firefox onchange bazen tetiklenmiyor)
            permissionPoll = setInterval(() => {
                navigator.permissions.query({ name: 'geolocation' }).then(r => {
                    if (r.state === 'granted') { tryGeolocation(); stopPermissionPoll(); }
                }).catch(() => {});
            }, 3000);
        }).catch(() => {});
    }

    function stopPermissionPoll() {
        if (permissionPoll) { clearInterval(permissionPoll); permissionPoll = null; }
    }

    function tryGeolocation() {
        if (! navigator.geolocation) {
            show('Bu tarayıcı konum servisini desteklemiyor. Chrome/Safari/Firefox güncel sürümünü kullan.', true);
            return;
        }

        btnLabel.textContent = geoAttempt === 0 ? 'İzin isteniyor…' : 'GPS konumun aranıyor…';
        retryBtn.disabled = true;

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                geoAttempt = 0;
                btnLabel.textContent = '✓ Alındı';
                retryBtn.disabled = false;
                hide();
                if (typeof successCb === 'function') {
                    successCb({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy });
                }
            },
            (err) => {
                // Zaman aşımı (3) / servis-yok (2 = kCLErrorLocationUnknown) çoğu zaman GEÇİCİ ya da
                // daha sabırlı/kaba bir denemeyle çözülür. Merdivende bir üst basamağa çık.
                // (İzin reddi (1) tekrar denenmez — ayar rehberi gösterilir.)
                if ((err.code === 3 || err.code === 2) && geoAttempt < GEO_LADDER.length - 1) {
                    geoAttempt += 1;
                    const backoff = err.code === 2 ? 1200 : 0; // kCLErrorLocationUnknown → kısa bekle
                    setTimeout(tryGeolocation, backoff);
                    return;
                }
                geoAttempt = 0;
                btnLabel.textContent = 'Tekrar Dene';
                retryBtn.disabled = false;
                let msg = 'Konum alınamadı.';
                let isDenied = false;
                switch (err.code) {
                    case 1: // PERMISSION_DENIED
                        msg = 'Konum izni reddedildi. Aşağıdaki adımları uygulayıp izni aç.';
                        isDenied = true;
                        break;
                    case 2: // POSITION_UNAVAILABLE — macOS'ta genelde Wi-Fi kapalı/Konum Servisleri kapalı
                        msg = isMac
                            ? 'macOS konumu bulamadı (kCLErrorLocationUnknown). Wi-Fi’yi AÇ (Mac konumu Wi-Fi ile bulur) + Sistem Ayarları → Gizlilik ve Güvenlik → Konum Servisleri’ni ve tarayıcına iznini aç, sonra Tekrar Dene.'
                            : 'Konum servisi yanıt vermiyor. Cihazının konum/GPS ayarını aç, sonra tekrar dene.';
                        break;
                    case 3: // TIMEOUT
                        msg = 'Konum alma zaman aşımına uğradı. Açık alanda / Wi-Fi açıkken tekrar dene.';
                        break;
                }
                show(msg, isDenied);
                // İlk başarısızlıktan sonra "konumsuz devam et" seçeneğini göster (yalnızca izin verilmişse)
                if (skipCb) skipBtn.classList.remove('hidden');
                if (typeof deniedCb === 'function') deniedCb(err);
            },
            GEO_LADDER[Math.min(geoAttempt, GEO_LADDER.length - 1)]
        );
    }

    retryBtn.addEventListener('click', () => { geoAttempt = 0; tryGeolocation(); });
    reloadBtn.addEventListener('click', () => {
        // İzin verildikten sonra sayfayı yenile — hem Safari hem Chrome için en kesin yol
        window.location.reload();
    });
    skipBtn.addEventListener('click', () => {
        hide();
        if (typeof skipCb === 'function') skipCb();
    });

    // Public API
    window.GeolocationGate = {
        require: (opts) => {
            successCb = opts?.onGranted || null;
            deniedCb  = opts?.onDenied  || null;
            skipCb    = opts?.onSkip    || null;   // verilirse "konumsuz devam et" seçeneği açılır
            geoAttempt = 0;
            skipBtn.classList.add('hidden');
            if (opts?.skipLabel) skipBtn.textContent = opts.skipLabel;
            renderGuide('initial');
            tryGeolocation();
        },
        show: (msg) => show(msg, false),
        hide,
    };

    // İlk render
    renderGuide('initial');
})();
</script>
