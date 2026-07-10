{{-- Yeniden kullanılabilir ACİL YARDIM (panic) butonu + sistem-içi modal + WebRTC.
     Parametreler:
       $role    : 'customer' | 'driver'  (varsayılan customer)
       $always  : true → buton hep görünür; false → yalnız aktif yolculukta (varsayılan false)
     Aktif yolculuk id'si JS'ten okunur: window.callWidgetGetPublicId() (yoksa null). --}}
@php($panicRole = $role ?? 'customer')
@php($panicAlways = $always ?? false)

<button type="button" id="panic-emergency-btn"
        class="hidden fixed bottom-24 right-4 z-[100] w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 text-white shadow-2xl shadow-red-500/50 border-2 border-white/20 flex items-center justify-center text-2xl font-bold animate-pulse"
        aria-label="Acil yardım">🚨</button>

@include('partials.panic-webrtc')

<div id="panic-em-modal" class="fixed inset-0 z-[110] items-center justify-center p-4"
     style="display:none; background:rgba(70,0,0,.75); backdrop-filter:blur(2px);">
    <div class="w-full max-w-sm rounded-2xl bg-white text-gray-900 shadow-2xl overflow-hidden">
        <div id="panic-em-head" class="px-5 py-4 text-center text-white" style="background:#dc2626;">
            <div class="text-3xl leading-none mb-1">🚨</div>
            <h3 id="panic-em-title" class="text-lg font-extrabold">ACİL YARDIM</h3>
        </div>
        <div class="px-5 py-4 text-center">
            <p id="panic-em-body" class="text-sm text-gray-700 leading-relaxed"></p>
            <div id="panic-em-actions" class="mt-5 flex flex-col gap-2"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const btn = document.getElementById('panic-emergency-btn');
    const modal = document.getElementById('panic-em-modal');
    if (!btn || !modal) return;

    const ROLE   = @json($panicRole);
    const ALWAYS = @json($panicAlways);
    const PANIC_URL = '{{ url('/api/panic') }}';
    const CALL_CENTER = '+908503403039';
    const QKEY = 'ferxgo_panic_queue';

    const head    = document.getElementById('panic-em-head');
    const titleEl = document.getElementById('panic-em-title');
    const bodyEl  = document.getElementById('panic-em-body');
    const actions = document.getElementById('panic-em-actions');

    function getRideId() {
        try { return (window.callWidgetGetPublicId ? window.callWidgetGetPublicId() : null) || null; }
        catch (_) { return null; }
    }
    // Görünürlük: hep göster ya da yalnız aktif yolculukta
    function refreshVisibility() {
        const show = ALWAYS || !!getRideId();
        btn.classList.toggle('hidden', !show);
    }
    refreshVisibility();
    setInterval(refreshVisibility, 1500);

    const openModal  = () => { modal.style.display = 'flex'; };
    const closeModal = () => {
        if (window.PanicRTC && window.PanicRTC.isActive()) window.PanicRTC.hangup(true);
        modal.style.display = 'none'; actions.innerHTML = '';
    };

    function btnEl(label, cls, onClick) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'w-full rounded-xl py-3 font-bold text-sm ' + cls;
        b.textContent = label;
        b.addEventListener('click', onClick);
        return b;
    }
    function linkEl(label, href, cls) {
        const a = document.createElement('a');
        a.href = href;
        a.className = 'w-full rounded-xl py-3 font-bold text-sm text-center block ' + cls;
        a.textContent = label;
        return a;
    }

    const qGet = () => { try { return JSON.parse(localStorage.getItem(QKEY) || '[]'); } catch (_) { return []; } };
    const qSet = (q) => { try { localStorage.setItem(QKEY, JSON.stringify(q)); } catch (_) {} };
    function qPush(p) { const q = qGet(); q.push(p); qSet(q); }

    async function postPanic(payload) {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch(PANIC_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!res.ok) throw new Error('http ' + res.status);
        return await res.json();
    }
    async function flushQueue() {
        const q = qGet(); if (!q.length) return;
        const remaining = [];
        for (const p of q) { try { await postPanic(p); } catch (_) { remaining.push(p); } }
        qSet(remaining);
    }
    window.addEventListener('online', flushQueue);
    setInterval(flushQueue, 20000);
    flushQueue();

    function showConfirm() {
        head.style.background = '#dc2626';
        titleEl.textContent = 'ACİL YARDIM';
        bodyEl.textContent = 'Çağrı merkezi sizinle HEMEN iletişime geçecek. Acil bir durumdaysanız devam edin.';
        actions.innerHTML = '';
        actions.appendChild(btnEl('🚨 EVET, ACİL YARDIM İSTİYORUM', 'bg-red-600 hover:bg-red-700 text-white', sendPanic));
        actions.appendChild(btnEl('Vazgeç', 'bg-gray-100 hover:bg-gray-200 text-gray-700', closeModal));
        openModal();
    }

    function showOffline(payload) {
        head.style.background = '#b45309';
        titleEl.textContent = '⚠️ İnternet Yok';
        bodyEl.textContent = 'Alarmın cihazına kaydedildi ve bağlantı gelir gelmez otomatik gönderilecek. İnternet olmadan da hemen ulaşmak için:';
        actions.innerHTML = '';
        var smsBody = 'ACIL YARDIM! FERXGO ' + (ROLE === 'driver' ? 'surucu' : 'yolcu') + '. Konum: '
            + (payload.lat ? payload.lat + ',' + payload.lng : 'bilinmiyor') + '. Lutfen hemen arayin.';
        actions.appendChild(linkEl('📞 Çağrı Merkezini Ara', 'tel:' + CALL_CENTER, 'bg-green-600 hover:bg-green-700 text-white'));
        actions.appendChild(linkEl('✉️ SMS ile Bildir', 'sms:' + CALL_CENTER + '?body=' + encodeURIComponent(smsBody), 'bg-blue-600 hover:bg-blue-700 text-white'));
        actions.appendChild(btnEl('Kapat', 'bg-gray-100 hover:bg-gray-200 text-gray-700', closeModal));
    }

    // Alarm iletildikten sonra destek çalışanını WebRTC ile "arar" (kişi = arayan)
    function startSupportCall(publicId) {
        if (!window.PanicRTC || !publicId) return;
        var muteBtn = btnEl('🔇 Mikrofonu Kapat', 'bg-gray-100 hover:bg-gray-200 text-gray-700', function () {
            var m = window.PanicRTC.toggleMute();
            muteBtn.textContent = m ? '🎙️ Mikrofonu Aç' : '🔇 Mikrofonu Kapat';
        });
        var endBtn = btnEl('📴 Görüşmeyi Bitir', 'bg-red-600 hover:bg-red-700 text-white', function () {
            window.PanicRTC.hangup(true); closeModal();
        });
        window.PanicRTC.start({
            role: 'caller',
            pushUrl: PANIC_URL + '/' + publicId + '/signal',
            pullUrl: PANIC_URL + '/' + publicId + '/signals',
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            onStatus: function (s) {
                if (s === 'connecting') bodyEl.textContent = 'Destek ekibi aranıyor, lütfen hattı açık tutun…';
                else if (s === 'active') bodyEl.textContent = '🟢 Destek ekibiyle görüşüyorsunuz. Sakin olun, buradayız.';
                else if (s === 'mic-error') bodyEl.textContent = 'Mikrofon açılamadı. Aşağıdaki butonla arayın.';
                else if (s === 'failed') bodyEl.textContent = 'Sesli bağlantı kurulamadı. Aşağıdan arayın.';
            },
        }).catch(function () {});
        actions.innerHTML = '';
        actions.appendChild(muteBtn);
        actions.appendChild(endBtn);
        actions.appendChild(linkEl('📞 Çağrı Merkezini Ara', 'tel:' + CALL_CENTER, 'bg-green-600 hover:bg-green-700 text-white'));
    }

    function showResult(ok, message, call, publicId) {
        head.style.background = ok ? '#16a34a' : '#dc2626';
        titleEl.textContent = ok ? '✓ Alarm İletildi' : 'Bağlantı Sorunu';
        bodyEl.textContent = ok
            ? (message || 'Çağrı merkezi alarmınızı aldı. Destek ekibi bağlanıyor…')
            : (message || 'İstek gönderilemedi. Lütfen doğrudan arayın.');
        actions.innerHTML = '';
        if (ok && publicId && window.PanicRTC) { startSupportCall(publicId); return; }
        const phone = call || CALL_CENTER;
        actions.appendChild(linkEl('📞 Çağrı Merkezini Ara', 'tel:' + phone, 'bg-green-600 hover:bg-green-700 text-white'));
        actions.appendChild(btnEl('Kapat', 'bg-gray-100 hover:bg-gray-200 text-gray-700', closeModal));
    }

    async function sendPanic() {
        actions.innerHTML = '';
        bodyEl.textContent = 'Alarm gönderiliyor…';
        let lat = null, lng = null, acc = null;
        if (navigator.geolocation) {
            try {
                const pos = await new Promise((res, rej) => navigator.geolocation.getCurrentPosition(res, rej, { timeout: 4000 }));
                lat = pos.coords.latitude; lng = pos.coords.longitude; acc = pos.coords.accuracy;
            } catch (_) {}
        }
        const payload = {
            triggered_by_type: ROLE,
            ride_request_public_id: getRideId() || '',
            lat, lng, location_accuracy_m: acc,
        };
        try {
            const data = await postPanic(payload);
            showResult(!!data.success, data.message, data.call, data.alert_id);
        } catch (err) {
            qPush(payload);
            showOffline(payload);
        }
    }

    btn.addEventListener('click', showConfirm);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
})();
</script>
