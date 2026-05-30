{{-- WebRTC sesli görüşme widget'ı.
     Müşteri (ride/show.blade.php) ve sürücü (driver/panel.blade.php) view'larına include edilir.
     getPublicId() global olarak tanımlanmalı (her view'da activeRequestId vs döndürür). --}}

<style>
    @keyframes call-pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.08); opacity: 0.85; } }
    .call-pulse { animation: call-pulse 1.2s ease-in-out infinite; }
    @keyframes call-ring-ripple {
        0%   { transform: scale(0.8); opacity: 0.8; }
        100% { transform: scale(2.2); opacity: 0; }
    }
    .call-ring-ripple { animation: call-ring-ripple 1.5s ease-out infinite; }
</style>

{{-- Floating call widget — sayfanın üstünde overlay --}}
<div id="call-widget" class="hidden fixed inset-0 z-[99999] bg-black/85 backdrop-blur-md flex items-center justify-center p-6" style="z-index: 99999;">
    <div class="bg-zinc-950 border border-white/10 rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl">
        {{-- Avatar + status --}}
        <div class="relative w-28 h-28 mx-auto mb-6">
            <div id="call-ripple-1" class="absolute inset-0 rounded-full bg-brand/30 hidden"></div>
            <div id="call-ripple-2" class="absolute inset-0 rounded-full bg-brand/20 hidden" style="animation-delay: 0.5s"></div>
            <div class="relative w-28 h-28 rounded-full bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-5xl text-black font-black shadow-2xl">
                <span id="call-avatar">📞</span>
            </div>
        </div>

        <div id="call-peer-name" class="text-xl font-bold text-white mb-1">—</div>
        <div id="call-status" class="text-sm text-zinc-400 mb-2">—</div>
        <div id="call-timer" class="text-3xl font-mono font-bold text-brand mb-6 hidden">00:00</div>

        {{-- Buttons --}}
        <div id="call-buttons-incoming" class="hidden grid grid-cols-2 gap-3">
            <button type="button" id="call-btn-reject"
                    class="px-4 py-3 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-bold transition shadow-lg shadow-red-500/30">
                ✕ Reddet
            </button>
            <button type="button" id="call-btn-accept"
                    class="px-4 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold transition shadow-lg shadow-emerald-500/30 call-pulse">
                ✓ Kabul Et
            </button>
        </div>

        <div id="call-buttons-active" class="hidden flex flex-col gap-2">
            <div class="grid grid-cols-2 gap-2">
                <button type="button" id="call-btn-mute"
                        class="px-3 py-2.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.10] border border-white/10 text-white text-xs font-semibold transition">
                    🎙 Mikrofon
                </button>
                <button type="button" id="call-btn-speaker"
                        class="px-3 py-2.5 rounded-xl bg-brand/20 hover:bg-brand/30 border border-brand/40 text-brand text-xs font-semibold transition">
                    🔊 Hoparlör
                </button>
            </div>
            <div id="call-audio-meter" class="hidden text-[10px] text-zinc-500 text-center">karşı taraf: <span id="call-audio-level">—</span></div>
            <button type="button" id="call-btn-hangup"
                    class="px-4 py-3 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-bold transition shadow-lg shadow-red-500/30">
                📞 Kapat
            </button>
        </div>

        <div id="call-buttons-outgoing" class="hidden">
            <button type="button" id="call-btn-cancel"
                    class="w-full px-4 py-3 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-bold transition shadow-lg shadow-red-500/30">
                ✕ İptal
            </button>
        </div>

        <div id="call-error" class="hidden mt-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300 text-left"></div>
    </div>
</div>

{{-- Audio element — uzak ses stream'i burada çalar --}}
<audio id="call-remote-audio" autoplay playsinline></audio>

<script>
(function() {
    'use strict';

    // ───── Config ────────────────────────────────────────────
    // ICE sunucuları config/services.php'den (.env üzerinden) gelir.
    // TURN tanımlı değilse simetrik NAT arkasında (Türk mobil operatörleri)
    // P2P kurulamaz → ses gelmez. Production için TURN ŞART.
    const STUN_SERVERS = (function () {
        const stunUrls = @json(config('services.webrtc.stun_urls', []));
        const turnUrls = @json(config('services.webrtc.turn_urls', []));
        const turnUser = @json(config('services.webrtc.turn_username'));
        const turnCred = @json(config('services.webrtc.turn_credential'));
        const list = [];
        for (const u of stunUrls) if (u) list.push({ urls: u });
        if (turnUrls && turnUrls.length && turnUser && turnCred) {
            for (const u of turnUrls) if (u) list.push({ urls: u, username: turnUser, credential: turnCred });
        }
        if (!list.length) list.push({ urls: 'stun:stun.l.google.com:19302' });
        console.log('[call] ICE servers:', list.length, list.some(s => /^turns?:/.test(s.urls)) ? '(TURN var)' : '(SADECE STUN — NAT arkasında ses gelmeyebilir)');
        return list;
    })();
    const AUDIO_CONSTRAINTS = {
        audio: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl:  true,
            sampleRate: 48000,
            channelCount: 1,
        },
        video: false,
    };
    const SIGNAL_POLL_MS = 500;
    const STATE_POLL_MS_IDLE   = 3000;
    const STATE_POLL_MS_ACTIVE = 1500;

    // ───── Elements ──────────────────────────────────────────
    const $ = (id) => document.getElementById(id);
    const widget   = $('call-widget');
    const ripple1  = $('call-ripple-1');
    const ripple2  = $('call-ripple-2');
    const audioEl  = $('call-remote-audio');
    const statusEl = $('call-status');
    const timerEl  = $('call-timer');
    const peerEl   = $('call-peer-name');
    const errorEl  = $('call-error');
    const btnAccept = $('call-btn-accept');
    const btnReject = $('call-btn-reject');
    const btnHangup = $('call-btn-hangup');
    const btnCancel = $('call-btn-cancel');
    const btnMute    = $('call-btn-mute');
    const btnSpeaker = $('call-btn-speaker');
    const audioMeter = $('call-audio-meter');
    const audioLevel = $('call-audio-level');
    const grpIncoming = $('call-buttons-incoming');
    const grpActive   = $('call-buttons-active');
    const grpOutgoing = $('call-buttons-outgoing');

    // Audio element sesi maksimuma sabitle
    audioEl.volume = 1.0;

    // ───── State ─────────────────────────────────────────────
    let currentCallId   = null;
    let currentStatus   = 'idle';
    let myRole          = null;
    let isInitiator     = false;
    let pc              = null;
    let localStream     = null;
    let lastSignalId    = 0;
    let statePollHandle = null;
    let signalPollHandle = null;
    let timerHandle     = null;
    let timerSeconds    = 0;
    let muted           = false;
    let speakerOn       = true;
    let pendingIceQueue = [];
    let remoteDescSet   = false;
    let statsHandle     = null;
    let lastBytesReceived = 0;
    let iceWatchdog     = null;

    // ───── URL helpers ───────────────────────────────────────
    function getPid() {
        if (typeof window.callWidgetGetPublicId === 'function') return window.callWidgetGetPublicId();
        return null;
    }
    function getPeerName() {
        if (typeof window.callWidgetGetPeerName === 'function') return window.callWidgetGetPeerName();
        return 'Görüşme';
    }
    function csrfToken() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function callUrl(action) {
        const pid = getPid();
        return `/api/ride-requests/${encodeURIComponent(pid)}/call/${action}`;
    }

    // ───── Ringtone (Web Audio API ile sentezlenmiş — dosya yüklemeden) ─────
    let ringAudioCtx = null;
    let ringOsc = null;
    let ringGain = null;
    let ringInterval = null;
    let ringVibInterval = null;  // ringInterval bir sayıdır, üzerine property atılamaz
    function startRingtone() {
        stopRingtone();
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            ringAudioCtx = new AC();
            const playBeep = () => {
                if (!ringAudioCtx) return;
                ringOsc = ringAudioCtx.createOscillator();
                ringGain = ringAudioCtx.createGain();
                ringOsc.type = 'sine';
                ringOsc.frequency.value = 440;
                ringGain.gain.setValueAtTime(0, ringAudioCtx.currentTime);
                ringGain.gain.linearRampToValueAtTime(0.18, ringAudioCtx.currentTime + 0.05);
                ringGain.gain.linearRampToValueAtTime(0, ringAudioCtx.currentTime + 0.9);
                ringOsc.connect(ringGain).connect(ringAudioCtx.destination);
                ringOsc.start();
                ringOsc.stop(ringAudioCtx.currentTime + 1.0);
            };
            playBeep();
            ringInterval = setInterval(playBeep, 2000);
            // Mobilde titreşim — try/catch ile sar, kullanıcı gesture yoksa vibrate fail edebilir
            if (navigator.vibrate) {
                try {
                    navigator.vibrate([400, 200, 400, 200, 400]);
                    ringVibInterval = setInterval(() => {
                        try { navigator.vibrate([400, 200, 400]); } catch (_) {}
                    }, 2000);
                } catch (_) { /* vibrate gesture yok, sessizce yut */ }
            }
        } catch (e) {
            console.warn('[call] ringtone failed', e);
        }
    }
    function stopRingtone() {
        if (ringVibInterval) { clearInterval(ringVibInterval); ringVibInterval = null; }
        if (ringInterval) { clearInterval(ringInterval); ringInterval = null; }
        if (ringOsc) { try { ringOsc.stop(); } catch(e){} ringOsc = null; }
        if (ringAudioCtx) { try { ringAudioCtx.close(); } catch(e){} ringAudioCtx = null; }
        if (navigator.vibrate) { try { navigator.vibrate(0); } catch (_) {} }
    }

    // ───── UI rendering ──────────────────────────────────────
    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }
    function clearError() {
        errorEl.classList.add('hidden');
    }
    function show(status) {
        currentStatus = status;
        if (status === 'idle' || status === 'ended') {
            widget.classList.add('hidden');
            stopTimer();
            return;
        }
        widget.classList.remove('hidden');
        peerEl.textContent = getPeerName();
        clearError();
        const isConnected = (status === 'active');
        ripple1.classList.toggle('hidden', isConnected);
        ripple2.classList.toggle('hidden', isConnected);
        ripple1.classList.toggle('call-ring-ripple', !isConnected);
        ripple2.classList.toggle('call-ring-ripple', !isConnected);
        timerEl.classList.toggle('hidden', !isConnected);
        grpIncoming.classList.toggle('hidden', status !== 'incoming');
        grpActive.classList.toggle('hidden', status !== 'active' && status !== 'connecting');
        grpOutgoing.classList.toggle('hidden', status !== 'outgoing');
        if (status === 'outgoing')        statusEl.textContent = 'Arıyor…';
        else if (status === 'incoming')   statusEl.textContent = 'Gelen çağrı';
        else if (status === 'connecting') statusEl.textContent = 'Bağlanıyor…';
        else if (status === 'active')     statusEl.textContent = 'Bağlandı';
    }
    function startTimer() {
        timerSeconds = 0;
        timerEl.textContent = '00:00';
        timerHandle = setInterval(() => {
            timerSeconds++;
            const m = String(Math.floor(timerSeconds / 60)).padStart(2, '0');
            const s = String(timerSeconds % 60).padStart(2, '0');
            timerEl.textContent = `${m}:${s}`;
        }, 1000);
    }
    function stopTimer() {
        if (timerHandle) { clearInterval(timerHandle); timerHandle = null; }
    }

    // ───── WebRTC ────────────────────────────────────────────
    async function getMic() {
        if (localStream) return localStream;
        // HTTPS değilse tarayıcı mikrofona kesin izin vermez — net hata göster
        if (!window.isSecureContext) {
            showError('Sesli görüşme için sayfa HTTPS olmalı. Adres çubuğunda "https://" ile başlayan adresle aç.');
            throw new Error('insecure context');
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Bu tarayıcı sesli görüşmeyi desteklemiyor. Chrome veya Safari kullan.');
            throw new Error('no getUserMedia');
        }
        try {
            localStream = await navigator.mediaDevices.getUserMedia(AUDIO_CONSTRAINTS);
        } catch (err) {
            // İzin reddi vs cihaz yok ayrımı
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                showError('Mikrofon izni reddedildi. Tarayıcı adres çubuğunun yanındaki 🔒 simgesine basıp "Mikrofon → İzin Ver" seç, sonra sayfayı yenile.');
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                showError('Mikrofon bulunamadı. Cihazda mikrofon olduğundan ve başka bir uygulamanın kullanmadığından emin ol.');
            } else {
                showError('Mikrofon açılamadı: ' + (err.message || err.name));
            }
            console.warn('[call] getUserMedia failed', err);
            throw err;
        }
        return localStream;
    }
    function buildPc() {
        if (pc) return pc;
        pc = new RTCPeerConnection({ iceServers: STUN_SERVERS });
        console.log('[call] pc created');
        pc.onicecandidate = (ev) => {
            if (ev.candidate) {
                pushSignal('ice', { candidate: ev.candidate.toJSON() });
            }
        };
        // Sağlam ontrack: hem streams[0] hem ev.track fallback
        pc.ontrack = (ev) => {
            console.log('[call] ontrack', { kind: ev.track.kind, streams: ev.streams.length, trackEnabled: ev.track.enabled, trackMuted: ev.track.muted });
            let stream;
            if (ev.streams && ev.streams[0]) {
                stream = ev.streams[0];
            } else {
                stream = audioEl.srcObject;
                if (!stream || !(stream instanceof MediaStream)) {
                    stream = new MediaStream();
                }
                stream.addTrack(ev.track);
            }
            audioEl.srcObject = stream;
            audioEl.volume = 1.0;
            audioEl.muted = false;
            // Autoplay reddedilirse butona tıklatma — user gesture'ı zaten var ama Safari için emniyet
            const tryPlay = () => audioEl.play().then(
                () => console.log('[call] audio playing, volume=', audioEl.volume, 'muted=', audioEl.muted),
                (err) => {
                    console.warn('[call] audio play failed, retrying after gesture', err);
                    showError('Ses çalmak için ekrana dokun.');
                    const retry = () => {
                        audioEl.play().then(
                            () => { clearError(); console.log('[call] audio playing after gesture'); },
                            (e) => console.warn('[call] still failed', e)
                        );
                        document.removeEventListener('click', retry);
                        document.removeEventListener('touchstart', retry);
                    };
                    document.addEventListener('click', retry);
                    document.addEventListener('touchstart', retry);
                }
            );
            tryPlay();
            // Track muted değişimi
            ev.track.onunmute = () => console.log('[call] remote track unmuted');
            ev.track.onmute   = () => console.log('[call] remote track muted');
            ev.track.onended  = () => console.log('[call] remote track ended');
            // Stats izleme — gerçekten paket geliyor mu?
            startStatsMonitor();
        };
        pc.oniceconnectionstatechange = () => {
            console.log('[call] ICE state:', pc.iceConnectionState);
            if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                clearIceWatchdog();
                if (currentStatus !== 'active') { show('active'); startTimer(); }
            }
            if (pc.iceConnectionState === 'checking') {
                // 20sn içinde connected olmazsa fail say
                armIceWatchdog(20000);
            }
            if (pc.iceConnectionState === 'failed') {
                clearIceWatchdog();
                showError('Bağlantı kurulamadı (NAT/firewall). TURN relay reddedildi.');
                setTimeout(() => endCall(true), 1500);
            }
        };
        pc.onconnectionstatechange = () => {
            console.log('[call] PC state:', pc.connectionState);
            if (pc.connectionState === 'connected') {
                if (currentStatus !== 'active') { show('active'); startTimer(); }
            }
            if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
                if (currentStatus === 'active') {
                    showError('Bağlantı koptu.');
                    setTimeout(() => endCall(true), 800);
                }
            }
        };
        return pc;
    }
    // Karşı taraftan ses paketi gelip gelmediğini izle — diagnostic
    function startStatsMonitor() {
        if (statsHandle) clearInterval(statsHandle);
        lastBytesReceived = 0;
        audioMeter.classList.remove('hidden');
        statsHandle = setInterval(async () => {
            if (!pc) return;
            try {
                const stats = await pc.getStats();
                let inboundAudio = null;
                stats.forEach(r => {
                    if (r.type === 'inbound-rtp' && r.kind === 'audio') inboundAudio = r;
                });
                if (inboundAudio) {
                    const bytes = inboundAudio.bytesReceived || 0;
                    const delta = bytes - lastBytesReceived;
                    lastBytesReceived = bytes;
                    const kbps = Math.round((delta * 8) / 1024);
                    audioLevel.textContent = kbps > 0 ? `${kbps} kbps ✓` : 'paket yok ✗';
                    audioLevel.style.color = kbps > 0 ? '#34d399' : '#f87171';
                    if (kbps === 0 && currentStatus === 'active') {
                        console.warn('[call] stats: no audio packets received');
                    }
                } else {
                    audioLevel.textContent = 'inbound yok';
                }
            } catch (e) {
                console.warn('[call] stats failed', e);
            }
        }, 1500);
    }
    function stopStatsMonitor() {
        if (statsHandle) { clearInterval(statsHandle); statsHandle = null; }
        audioMeter.classList.add('hidden');
        audioLevel.textContent = '—';
    }
    function armIceWatchdog(ms) {
        if (iceWatchdog) return;
        iceWatchdog = setTimeout(() => {
            if (!pc) return;
            const st = pc.iceConnectionState;
            if (st !== 'connected' && st !== 'completed') {
                console.warn('[call] ICE watchdog tripped, state=', st);
                showError('Bağlantı zaman aşımı. Karşı tarafın ağında engel var, TURN gerekiyor.');
                setTimeout(() => endCall(true), 1500);
            }
        }, ms);
    }
    function clearIceWatchdog() {
        if (iceWatchdog) { clearTimeout(iceWatchdog); iceWatchdog = null; }
    }

    function addLocalTracks(peer, stream) {
        // Idempotent: aynı track ikinci kez eklenmesin
        const senders = peer.getSenders();
        stream.getTracks().forEach(t => {
            if (!senders.some(s => s.track && s.track.id === t.id)) {
                peer.addTrack(t, stream);
            }
        });
    }
    // SHA-256 hash — SDP'nin transit sırasında değişip değişmediğini teyit için
    async function _sdpHash(s) {
        try {
            const buf = new TextEncoder().encode(s);
            const h = await crypto.subtle.digest('SHA-256', buf);
            return Array.from(new Uint8Array(h)).slice(0, 6).map(b => b.toString(16).padStart(2,'0')).join('');
        } catch (_) { return '-'; }
    }

    async function makeOffer() {
        const stream = await getMic();
        const peer = buildPc();
        addLocalTracks(peer, stream);
        const offer = await peer.createOffer();
        await peer.setLocalDescription(offer);
        const sdp = peer.localDescription.sdp;
        console.log('[call] offer sent · len:', sdp.length, '· hash:', await _sdpHash(sdp), '· ua:', navigator.userAgent.match(/Chrome|Safari|Firefox|Edge/g)?.join(','));
        pushSignal('offer', { sdp, type: peer.localDescription.type });
    }
    async function handleRemoteOffer(payload) {
        console.log('[call] remote offer received · len:', payload.sdp.length, '· hash:', await _sdpHash(payload.sdp), '· ua:', navigator.userAgent.match(/Chrome|Safari|Firefox|Edge/g)?.join(','));
        const peer = buildPc();
        // KRİTİK SIRA: önce setRemoteDescription (transceiver'lar kurulur), sonra addTrack
        await peer.setRemoteDescription({ type: payload.type, sdp: payload.sdp });
        remoteDescSet = true;
        const stream = await getMic();
        addLocalTracks(peer, stream);
        await drainIceQueue();
        const answer = await peer.createAnswer();
        await peer.setLocalDescription(answer);
        const sdp = peer.localDescription.sdp;
        console.log('[call] answer sent · len:', sdp.length, '· hash:', await _sdpHash(sdp));
        pushSignal('answer', { sdp, type: peer.localDescription.type });
    }
    async function handleRemoteAnswer(payload) {
        if (!pc) return;
        console.log('[call] remote answer received · len:', payload.sdp.length, '· hash:', await _sdpHash(payload.sdp));
        await pc.setRemoteDescription({ type: payload.type, sdp: payload.sdp });
        remoteDescSet = true;
        await drainIceQueue();
    }
    async function handleRemoteIce(payload) {
        if (!pc) return;
        if (!remoteDescSet) {
            pendingIceQueue.push(payload.candidate);
            return;
        }
        try {
            await pc.addIceCandidate(new RTCIceCandidate(payload.candidate));
        } catch (e) {
            console.warn('[call] addIceCandidate failed', e);
        }
    }
    async function drainIceQueue() {
        while (pendingIceQueue.length) {
            const c = pendingIceQueue.shift();
            try { await pc.addIceCandidate(new RTCIceCandidate(c)); } catch (e) {}
        }
    }

    // ───── Signaling ─────────────────────────────────────────
    async function pushSignal(type, payload) {
        try {
            await fetch(callUrl('signal'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: JSON.stringify({ type, payload }),
            });
        } catch (e) {
            console.warn('[call] pushSignal failed', type, e);
        }
    }
    async function pullSignals() {
        if (!currentCallId) return;
        try {
            const res = await fetch(`${callUrl('signals')}?since_id=${lastSignalId}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            for (const s of (data.signals || [])) {
                lastSignalId = Math.max(lastSignalId, s.id);
                if (s.type === 'offer')       await handleRemoteOffer(s.payload);
                else if (s.type === 'answer') await handleRemoteAnswer(s.payload);
                else if (s.type === 'ice')    await handleRemoteIce(s.payload);
                else if (s.type === 'bye')    endCall(false);
            }
        } catch (e) {
            console.warn('[call] pullSignals failed', e);
        }
    }

    // ───── Polling ───────────────────────────────────────────
    function startSignalPolling() {
        if (signalPollHandle) return;
        signalPollHandle = setInterval(pullSignals, SIGNAL_POLL_MS);
    }
    function stopSignalPolling() {
        if (signalPollHandle) { clearInterval(signalPollHandle); signalPollHandle = null; }
    }
    async function pollState() {
        const pid = getPid();
        if (!pid) return;
        try {
            const res = await fetch(callUrl('state'), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            myRole = data.role;
            const call = data.call;
            if (!call) return;

            // Yeni gelen call
            if (currentStatus === 'idle' && call.status === 'ringing' && call.initiator !== myRole) {
                currentCallId = call.id;
                isInitiator = false;
                lastSignalId = 0;
                show('incoming');
                startRingtone();
                startSignalPolling();
                return;
            }
            // Outgoing → karşı taraf kabul etti
            // ÖNEMLİ: timer'ı burada başlatma! Sadece "connecting" göster.
            // Gerçek timer ICE connected olunca (oniceconnectionstatechange) başlar.
            if (currentStatus === 'outgoing' && call.status === 'accepted' && currentCallId === call.id) {
                stopRingtone();
                show('connecting');
                // initiator → offer yap
                if (isInitiator) await makeOffer();
                return;
            }
            // Karşı taraf kapattıysa
            if ((currentStatus === 'incoming' || currentStatus === 'outgoing' || currentStatus === 'active')
                && (call.status === 'ended' || call.status === 'rejected' || call.status === 'missed')
                && currentCallId === call.id) {
                endCall(false);
            }
        } catch (e) {
            // sessizce yut
        }
    }
    function startStatePolling() {
        if (statePollHandle) clearInterval(statePollHandle);
        const ms = (currentStatus === 'active' || currentStatus === 'incoming' || currentStatus === 'outgoing')
            ? STATE_POLL_MS_ACTIVE : STATE_POLL_MS_IDLE;
        statePollHandle = setInterval(() => {
            // status değiştiyse interval'ı tekrar ayarla
            const desired = (currentStatus === 'active' || currentStatus === 'incoming' || currentStatus === 'outgoing')
                ? STATE_POLL_MS_ACTIVE : STATE_POLL_MS_IDLE;
            if (desired !== ms) startStatePolling();
            else pollState();
        }, ms);
        pollState();
    }

    // ───── Actions ───────────────────────────────────────────
    async function startCall() {
        const pid = getPid();
        console.log('[call] startCall pid=', pid);
        if (!pid) { showError('Aktif yolculuk yok.'); return; }
        clearError();
        try {
            // Önce mikrofon iste — kabul edilmezse hiç başlatma
            await getMic();
            const res = await fetch(callUrl('start'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: JSON.stringify({}),
            });
            const data = await res.json();
            if (!data.success) { showError(data.message || 'Çağrı başlatılamadı.'); return; }
            currentCallId = data.call.id;
            myRole = data.role;
            isInitiator = (data.call.initiator === myRole);
            lastSignalId = 0;
            show('outgoing');
            startSignalPolling();
        } catch (e) {
            // mic izin reddi vs zaten showError yapmış
        }
    }
    async function acceptCall() {
        stopRingtone();
        // Hemen "Bağlanıyor..." ekranına geç — kullanıcı feedback'i için
        show('connecting');
        try {
            // Önce mikrofon iste (Safari iOS izni burada sorar)
            await getMic();
            buildPc();
            const res = await fetch(callUrl('accept'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!data.success) { showError(data.message || 'Kabul edilemedi.'); show('idle'); return; }
            // Offer karşı taraftan gelecek (handleRemoteOffer)
        } catch (e) {
            showError('Bağlantı hatası.');
            show('idle');
        }
    }
    async function endCall(notifyServer) {
        stopRingtone();
        stopStatsMonitor();
        clearIceWatchdog();
        if (notifyServer) {
            try {
                await fetch(callUrl('end'), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                });
            } catch (e) {}
        }
        stopSignalPolling();
        stopTimer();
        remoteDescSet = false;
        pendingIceQueue = [];
        if (pc) { try { pc.close(); } catch (e) {} pc = null; }
        if (localStream) {
            localStream.getTracks().forEach(t => t.stop());
            localStream = null;
        }
        if (audioEl.srcObject) audioEl.srcObject = null;
        currentCallId = null;
        isInitiator = false;
        lastSignalId = 0;
        muted = false;
        btnMute.textContent = '🎙 Mikrofon Açık';
        show('idle');
    }

    // ───── Wire up ───────────────────────────────────────────
    btnAccept.addEventListener('click', acceptCall);
    btnReject.addEventListener('click', () => endCall(true));
    btnHangup.addEventListener('click', () => endCall(true));
    btnCancel.addEventListener('click', () => endCall(true));
    btnMute.addEventListener('click', () => {
        if (!localStream) return;
        muted = !muted;
        localStream.getAudioTracks().forEach(t => t.enabled = !muted);
        btnMute.textContent = muted ? '🔇 Kapalı' : '🎙 Mikrofon';
    });
    btnSpeaker.addEventListener('click', async () => {
        speakerOn = !speakerOn;
        audioEl.volume = speakerOn ? 1.0 : 0.4;
        btnSpeaker.textContent = speakerOn ? '🔊 Hoparlör' : '🔈 Sessiz';
        btnSpeaker.classList.toggle('bg-brand/20', speakerOn);
        btnSpeaker.classList.toggle('text-brand', speakerOn);
        btnSpeaker.classList.toggle('bg-white/[0.06]', !speakerOn);
        btnSpeaker.classList.toggle('text-white', !speakerOn);
        // setSinkId destekleyen tarayıcılarda çıkış cihazını zorla (Chrome desktop)
        if (audioEl.setSinkId) {
            try { await audioEl.setSinkId('default'); } catch (e) {}
        }
        // Tekrar play denemesi
        audioEl.play().catch(()=>{});
    });

    // Public API
    window.CallWidget = {
        start: startCall,
        end:   () => endCall(true),
        isActive: () => currentStatus !== 'idle' && currentStatus !== 'ended',
    };

    // Kick off state polling
    startStatePolling();
})();
</script>
