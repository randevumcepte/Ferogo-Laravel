{{--
    Admin panel — Acil yardım (panic) sesli + görsel alarm dinleyicisi.
    Her admin sayfasına render hook ile enjekte edilir (AdminPanelProvider BODY_END).
    /admin/panic-poll'u periyodik yoklar; yeni açık alarm gelince tam ekran kırmızı
    banner açar ve alarm sesi çalar (operatör "Sessize Al" / "Kapat" deyene dek).
--}}
<div id="ferxgo-panic-root"
     data-poll-url="{{ url('/admin/panic-poll') }}"
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
    #ferxgo-panic-overlay .b-map { background: #2563eb; color: #fff; }
    #ferxgo-panic-overlay .b-open { background: #b91c1c; color: #fff; }
    #ferxgo-panic-overlay .b-mute { background: #f3f4f6; color: #111; }
    #ferxgo-panic-overlay .b-dismiss { background: #e5e7eb; color: #444; }
    #ferxgo-panic-overlay .count { margin-top: 12px; font-size: 13px; color: #666; }
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

        var nameLine = alert.name ? ('<div class="meta">Ad: <b>' + escapeHtml(alert.name) + '</b></div>') : '';
        var phoneLine = alert.phone
            ? ('<div class="meta">Telefon: <a href="tel:' + escapeHtml(alert.phone) + '">' + escapeHtml(alert.phone) + '</a></div>')
            : '<div class="meta">Telefon: —</div>';
        var mapBtn = alert.map_url
            ? '<a class="btn b-map" href="' + escapeAttr(alert.map_url) + '" target="_blank" rel="noopener">📍 Haritada Aç</a>'
            : '';
        var countLine = total > 1 ? ('<div class="count">+ ' + (total - 1) + ' açık alarm daha var</div>') : '';

        overlay.innerHTML =
            '<div class="box" role="alertdialog" aria-label="Acil yardım alarmı">' +
                '<h1>🚨 ACİL YARDIM ALARMI</h1>' +
                '<div class="sub">Bir kullanıcı panik butonuna bastı — HEMEN müdahale edin.</div>' +
                '<div class="who">' + escapeHtml(alert.who || '') + (alert.ago ? ' · ' + escapeHtml(alert.ago) : '') + '</div>' +
                nameLine + phoneLine +
                '<div class="btns">' +
                    mapBtn +
                    '<a class="btn b-open" href="' + escapeAttr(alert.url) + '">Panelde Aç</a>' +
                    '<button type="button" class="b-mute">🔇 Sessize Al</button>' +
                    '<button type="button" class="b-dismiss">Kapat</button>' +
                '</div>' +
                countLine +
            '</div>';

        document.body.appendChild(overlay);

        overlay.querySelector('.b-mute').addEventListener('click', function () {
            muted = true; stopSiren(); this.textContent = '🔇 Susturuldu';
        });
        overlay.querySelector('.b-dismiss').addEventListener('click', function () {
            dismissed[alert.id] = true;
            removeOverlay();
            stopSiren();
        });
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
