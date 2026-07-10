{{--
    Admin panel — Acil yardım (panic) sesli + görsel alarm dinleyicisi.
    Her admin sayfasına render hook ile enjekte edilir (AdminPanelProvider BODY_END).
    /admin/panic-poll'u periyodik yoklar; yeni açık alarm gelince tam ekran kırmızı
    banner açar ve alarm sesi çalar (operatör "Sessize Al" / "Kapat" deyene dek).
--}}
@include('partials.panic-webrtc')

<div id="ferxgo-panic-root"
     data-poll-url="{{ url('/admin/panic-poll') }}"
     data-call-url="{{ url('/admin/panic-call') }}"
     data-signal-base="{{ url('/admin/panic-call') }}"
     style="display:none"></div>

<style>
    #ferxgo-panic-overlay {
        position: fixed; inset: 0; z-index: 2147483647;
        background: rgba(120,0,0,.92);
        display: flex; align-items: center; justify-content: center;
        animation: ferxgoPanicFlash 1s steps(2, start) infinite;
        font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    @keyframes ferxgoPanicFlash {
        0% { background: rgba(150,0,0,.94); }
        100% { background: rgba(60,0,0,.94); }
    }
    #ferxgo-panic-overlay .box {
        background: #fff; color: #111; max-width: 560px; width: 92%;
        border-radius: 18px; padding: 28px; text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,.6);
    }
    #ferxgo-panic-overlay h1 { font-size: 30px; font-weight: 900; color: #b91c1c; margin: 0 0 4px; }
    #ferxgo-panic-overlay .sub { font-size: 15px; color: #444; margin-bottom: 16px; }
    #ferxgo-panic-overlay .who { font-size: 20px; font-weight: 800; margin: 8px 0; }
    #ferxgo-panic-overlay .meta { font-size: 15px; color: #333; margin: 4px 0; }
    #ferxgo-panic-overlay .meta a { color: #b91c1c; font-weight: 700; }
    #ferxgo-panic-overlay .btns { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; justify-content: center; }
    #ferxgo-panic-overlay button, #ferxgo-panic-overlay a.btn {
        border: 0; border-radius: 10px; padding: 12px 18px; font-size: 15px; font-weight: 700;
        cursor: pointer; text-decoration: none; display: inline-block;
    }
    #ferxgo-panic-overlay .b-call { background: #16a34a; color: #fff; font-size: 17px; padding: 14px 26px; }
    #ferxgo-panic-overlay .b-call:disabled { opacity: .7; cursor: default; }
    #ferxgo-panic-overlay .b-share { background: #7c3aed; color: #fff; }
    #ferxgo-panic-overlay .b-map { background: #2563eb; color: #fff; }
    #ferxgo-panic-overlay .b-open { background: #b91c1c; color: #fff; }
    #ferxgo-panic-overlay .b-mute { background: #f3f4f6; color: #111; }
    #ferxgo-panic-overlay .b-dismiss { background: #e5e7eb; color: #444; }
    #ferxgo-panic-overlay .count { margin-top: 12px; font-size: 13px; color: #666; }
    #ferxgo-panic-overlay .who-card {
        background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
        padding: 14px; margin: 14px 0 4px;
    }
    #ferxgo-panic-overlay .who-card .lbl { font-size: 12px; color: #991b1b; text-transform: uppercase; letter-spacing: .5px; }

    /* Küçültülmüş sürüklenebilir çağrı kutusu */
    #ferxgo-panic-mini {
        position: fixed; top: 16px; right: 16px; z-index: 2147483647;
        width: 270px; background: #fff; color: #111; border-radius: 14px;
        box-shadow: 0 12px 40px rgba(0,0,0,.45); border: 2px solid #dc2626;
        font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        overflow: hidden; user-select: none; touch-action: none;
    }
    #ferxgo-panic-mini .pm-head {
        background: #dc2626; color: #fff; padding: 9px 12px; font-weight: 800;
        font-size: 13px; cursor: move; display: flex; align-items: center; justify-content: space-between; gap: 8px;
    }
    #ferxgo-panic-mini .pm-body { padding: 11px 12px; }
    #ferxgo-panic-mini .pm-name { font-weight: 700; font-size: 14px; }
    #ferxgo-panic-mini .pm-status { font-size: 13px; color: #16a34a; margin: 4px 0 10px; font-weight: 700; }
    #ferxgo-panic-mini .pm-actions { display: flex; gap: 8px; }
    #ferxgo-panic-mini .pm-actions button, #ferxgo-panic-mini .pm-actions a {
        flex: 1; border: 0; border-radius: 8px; padding: 9px; font-size: 12px; font-weight: 800; cursor: pointer;
        text-align: center; text-decoration: none; display: inline-block;
    }
    #ferxgo-panic-mini .pm-open { background: #2563eb; color: #fff; }
    #ferxgo-panic-mini .pm-close { background: #dc2626; color: #fff; }
</style>

<script>
(function () {
    var root = document.getElementById('ferxgo-panic-root');
    if (!root || window.__ferxgoPanicInit) return;
    window.__ferxgoPanicInit = true;

    var POLL_URL = root.getAttribute('data-poll-url');
    var POLL_MS  = 8000;
    var dismissed = {};          // bu oturumda kapatılan alarm id'leri
    var audioCtx = null, beepTimer = null, muted = false;
    var overlay = null, current = null;
    var miniEl = null, minimized = false, activeAlert = null, callPhone = null;

    // ---- Alarm sesi (WebAudio — dosya gerektirmez) ----
    function beepOnce() {
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            var o = audioCtx.createOscillator(), g = audioCtx.createGain();
            o.type = 'square'; o.frequency.value = 880;
            g.gain.value = 0.0001;
            o.connect(g); g.connect(audioCtx.destination);
            var t = audioCtx.currentTime;
            g.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
            o.start(t); o.stop(t + 0.5);
        } catch (e) {}
    }
    function startSiren() {
        if (muted || beepTimer) return;
        beepOnce();
        beepTimer = setInterval(beepOnce, 1200);
    }
    function stopSiren() {
        if (beepTimer) { clearInterval(beepTimer); beepTimer = null; }
    }

    function buildOverlay(alert, total) {
        removeOverlay();
        current = alert;
        overlay = document.createElement('div');
        overlay.id = 'ferxgo-panic-overlay';

        var phoneTxt = alert.phone ? escapeHtml(alert.phone) : '—';
        var nameTxt  = alert.name ? escapeHtml(alert.name) : '(isim kayıtlı değil)';

        var hasLoc = !!(alert.lat && alert.lng);
        var locTxt = hasLoc ? (Number(alert.lat).toFixed(5) + ', ' + Number(alert.lng).toFixed(5)) : 'Konum yok';

        // Kimden geldiği — belirgin bilgi kartı
        var whoCard =
            '<div class="who-card">' +
                '<div class="lbl">Alarmı gönderen</div>' +
                '<div class="who">' + escapeHtml(alert.who || 'Kullanıcı') + '</div>' +
                '<div class="meta">Ad: <b>' + nameTxt + '</b></div>' +
                '<div class="meta">Telefon: <b>' + phoneTxt + '</b></div>' +
                '<div class="meta">📍 Konum: <b>' + escapeHtml(locTxt) + '</b></div>' +
                (alert.ago ? '<div class="meta">' + escapeHtml(alert.ago) + '</div>' : '') +
            '</div>';

        var callBtn = '<button type="button" class="b-call" data-phone="' + escapeAttr(alert.phone || '') + '">📞 Çağrıyı Aç (Konuş)</button>';
        var mapBtn = alert.map_url
            ? '<a class="btn b-map" href="' + escapeAttr(alert.map_url) + '" target="_blank" rel="noopener">📍 Haritada Aç</a>'
            : '';
        var shareBtn = hasLoc
            ? '<button type="button" class="b-share">📤 Konumu Paylaş</button>'
            : '';
        var countLine = total > 1 ? ('<div class="count">+ ' + (total - 1) + ' açık alarm daha var</div>') : '';

        overlay.innerHTML =
            '<div class="box" role="alertdialog" aria-label="Acil yardım alarmı">' +
                '<h1>🚨 ACİL YARDIM ALARMI</h1>' +
                '<div class="sub">Bir kullanıcı panik butonuna bastı — HEMEN müdahale edin.</div>' +
                whoCard +
                '<div class="btns">' +
                    callBtn +
                    shareBtn +
                    mapBtn +
                    '<a class="btn b-open" href="' + escapeAttr(alert.url) + '" target="_blank" rel="noopener">Paneli Aç</a>' +
                    '<button type="button" class="b-mute">🔇 Sessize Al</button>' +
                    '<button type="button" class="b-dismiss">Kapat</button>' +
                '</div>' +
                countLine +
            '</div>';

        document.body.appendChild(overlay);

        // Konumu paylaş — Web Share API (mobil/masaüstü), yoksa panoya kopyala
        var shareEl = overlay.querySelector('.b-share');
        if (shareEl) {
            shareEl.addEventListener('click', function () {
                var txt = 'FERXGO ACİL DURUM — ' + (alert.who || '') + ' konumu: ' + locTxt;
                var url = alert.map_url || ('https://www.google.com/maps?q=' + alert.lat + ',' + alert.lng);
                if (navigator.share) {
                    navigator.share({ title: 'FERXGO Acil Durum Konumu', text: txt, url: url }).catch(function () {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(txt + ' ' + url).then(function () {
                        shareEl.textContent = '✓ Kopyalandı';
                        setTimeout(function () { shareEl.textContent = '📤 Konumu Paylaş'; }, 2000);
                    }).catch(function () {});
                }
            });
        }

        // Çağrıyı Aç — WebRTC ile kişiyle tarayıcı üzerinden sesli konuş (operatör = cevaplayan)
        var callEl = overlay.querySelector('.b-call');
        if (callEl) {
            callEl.addEventListener('click', function () {
                answerCall(alert.id, callEl.getAttribute('data-phone'));
            });
        }

        // Paneli Aç — yeni sekmede detay panelini açar + çağrı kutusunu küçültür (sürüklenebilir)
        var openEl = overlay.querySelector('.b-open');
        if (openEl) {
            openEl.addEventListener('click', function () {
                collapseToMini(alert);   // yeni sekme zaten anchor target=_blank ile açılır
            });
        }

        overlay.querySelector('.b-mute').addEventListener('click', function () {
            muted = true; stopSiren(); this.textContent = '🔇 Susturuldu';
        });
        overlay.querySelector('.b-dismiss').addEventListener('click', function () {
            endEverything(alert.id);
        });
    }

    // Çağrı durumunu görünen UI'a (tam overlay ya da mini kutu) yaz
    function callStatusText(s) {
        return s === 'connecting' ? '📞 Bağlanıyor…'
             : s === 'active'     ? '🟢 Görüşülüyor'
             : s === 'failed'     ? '📞 Bağlantı kurulamadı'
             : s === 'ended'      ? 'Görüşme bitti'
             : s === 'mic-error'  ? 'Mikrofon açılamadı'
             : s;
    }
    function setCallStatus(s) {
        var txt = callStatusText(s);
        if (overlay) {
            var b = overlay.querySelector('.b-call');
            if (b) { b.textContent = (s === 'active' ? '🟢 Görüşülüyor' : txt); b.disabled = (s === 'active' || s === 'connecting'); }
        }
        if (miniEl) {
            var st = miniEl.querySelector('.pm-status');
            if (st) st.textContent = txt;
        }
    }

    // Operatör WebRTC ile kişinin çağrısını cevaplar (tarayıcıda konuşur — santral gerekmez)
    function answerCall(alertId, phone) {
        if (!window.PanicRTC) { if (phone) window.location.href = 'tel:' + phone; return; }
        callPhone = phone;
        muted = true; stopSiren(); // alarm sesini sustur, konuşmaya geç
        var base = root.getAttribute('data-signal-base');
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        setCallStatus('connecting');
        window.PanicRTC.start({
            role: 'operator',
            pushUrl: base + '/' + alertId + '/signal',
            pullUrl: base + '/' + alertId + '/signals',
            csrf: csrf,
            onStatus: function (s) {
                setCallStatus(s);
                if (s === 'mic-error' && phone) window.location.href = 'tel:' + phone;
            },
        }).catch(function () {
            setCallStatus('failed');
            if (phone) window.location.href = 'tel:' + phone;
        });
    }

    // Tam overlay'i kapat ama çağrıyı SÜRDÜR; sağ üstte sürüklenebilir mini kutu göster
    function collapseToMini(alert) {
        minimized = true;
        activeAlert = alert;
        removeOverlay();          // çağrıyı bitirmeden sadece görseli kaldır
        stopSiren();
        buildMini(alert);
    }

    function buildMini(alert) {
        removeMini();
        miniEl = document.createElement('div');
        miniEl.id = 'ferxgo-panic-mini';
        var who = (alert.who || 'Kullanıcı') + (alert.name ? ' · ' + alert.name : '');
        miniEl.innerHTML =
            '<div class="pm-head"><span>🚨 Acil Çağrı</span><span>⠿ sürükle</span></div>' +
            '<div class="pm-body">' +
                '<div class="pm-name">' + escapeHtml(who) + '</div>' +
                '<div class="pm-status">' + (window.PanicRTC && window.PanicRTC.isActive() ? '🟢 Görüşülüyor' : 'Alarm açık') + '</div>' +
                '<div class="pm-actions">' +
                    '<a class="pm-open" href="' + escapeAttr(alert.url) + '" target="_blank" rel="noopener">Paneli Aç</a>' +
                    '<button type="button" class="pm-close">Kapat</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(miniEl);

        miniEl.querySelector('.pm-close').addEventListener('click', function () {
            endEverything(alert.id);
        });
        makeDraggable(miniEl, miniEl.querySelector('.pm-head'));
    }

    function removeMini() {
        if (miniEl && miniEl.parentNode) miniEl.parentNode.removeChild(miniEl);
        miniEl = null;
    }

    // Çağrıyı bitir + tüm görselleri kaldır + bu oturumda tekrar açılmasın
    function endEverything(alertId) {
        if (window.PanicRTC && window.PanicRTC.isActive()) window.PanicRTC.hangup(true);
        if (alertId != null) dismissed[alertId] = true;
        removeOverlay();
        removeMini();
        minimized = false;
        activeAlert = null;
        stopSiren();
    }

    // Mini kutuyu mouse/touch ile sürükle
    function makeDraggable(el, handle) {
        var dragging = false, sx = 0, sy = 0, ox = 0, oy = 0;
        handle.addEventListener('pointerdown', function (e) {
            dragging = true;
            var r = el.getBoundingClientRect();
            ox = r.left; oy = r.top; sx = e.clientX; sy = e.clientY;
            el.style.left = ox + 'px'; el.style.top = oy + 'px';
            el.style.right = 'auto';
            try { handle.setPointerCapture(e.pointerId); } catch (_) {}
        });
        handle.addEventListener('pointermove', function (e) {
            if (!dragging) return;
            var nx = ox + (e.clientX - sx), ny = oy + (e.clientY - sy);
            nx = Math.max(0, Math.min(nx, window.innerWidth - el.offsetWidth));
            ny = Math.max(0, Math.min(ny, window.innerHeight - el.offsetHeight));
            el.style.left = nx + 'px'; el.style.top = ny + 'px';
        });
        var stop = function () { dragging = false; };
        handle.addEventListener('pointerup', stop);
        handle.addEventListener('pointercancel', stop);
    }

    function removeOverlay() {
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        overlay = null; current = null;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }
    function escapeAttr(s) { return escapeHtml(s); }

    function poll() {
        fetch(POLL_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.alerts) return;
                var live = data.alerts.filter(function (a) { return !dismissed[a.id]; });
                if (live.length === 0) {
                    removeOverlay(); stopSiren(); muted = false;
                    return;
                }
                // En yeni açık alarmı göster
                var top = live[0];
                if (!current || current.id !== top.id) {
                    muted = false;
                    buildOverlay(top, live.length);
                }
                startSiren();
            })
            .catch(function () {});
    }

    // İlk kullanıcı etkileşiminde AudioContext'i uyandır (tarayıcı autoplay politikası)
    document.addEventListener('click', function once() {
        try { if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
        document.removeEventListener('click', once);
    }, { once: true });

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
