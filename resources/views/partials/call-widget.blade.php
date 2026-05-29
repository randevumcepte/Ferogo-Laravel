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
<div id="call-widget" class="hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-6">
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
            <button type="button" id="call-btn-mute"
                    class="px-4 py-2.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.10] border border-white/10 text-white text-sm font-semibold transition">
                🎙 Mikrofon Açık
            </button>
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
    const STUN_SERVERS = [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
    ];
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
    const btnMute   = $('call-btn-mute');
    const grpIncoming = $('call-buttons-incoming');
    const grpActive   = $('call-buttons-active');
    const grpOutgoing = $('call-buttons-outgoing');

    // ───── State ─────────────────────────────────────────────
    let currentCallId   = null;
    let currentStatus   = 'idle';        // idle | outgoing | incoming | active | ended
    let myRole          = null;          // 'customer' | 'driver'
    let isInitiator     = false;
    let pc              = null;          // RTCPeerConnection
    let localStream     = null;
    let lastSignalId    = 0;
    let statePollHandle = null;
    let signalPollHandle = null;
    let timerHandle     = null;
    let timerSeconds    = 0;
    let muted           = false;
    let pendingIceQueue = [];            // ICE candidates buffered until remote desc set
    let remoteDescSet   = false;

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
        ripple1.classList.toggle('hidden', status === 'active');
        ripple2.classList.toggle('hidden', status === 'active');
        ripple1.classList.toggle('call-ring-ripple', status !== 'active');
        ripple2.classList.toggle('call-ring-ripple', status !== 'active');
        timerEl.classList.toggle('hidden', status !== 'active');
        grpIncoming.classList.toggle('hidden', status !== 'incoming');
        grpActive.classList.toggle('hidden', status !== 'active');
        grpOutgoing.classList.toggle('hidden', status !== 'outgoing');
        if (status === 'outgoing')      statusEl.textContent = 'Arıyor…';
        else if (status === 'incoming') statusEl.textContent = 'Gelen çağrı';
        else if (status === 'active')   statusEl.textContent = 'Bağlandı';
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
            console.log('[call] ontrack', { kind: ev.track.kind, streams: ev.streams.length });
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
            // Autoplay reddedilirse butona tıklatma — user gesture'ı zaten var ama Safari için emniyet
            const tryPlay = () => audioEl.play().then(
                () => console.log('[call] audio playing'),
                (err) => {
                    console.warn('[call] audio play failed, retrying after gesture', err);
                    // Kullanıcı widget'ta herhangi bir butona basınca tekrar dene
                    const retry = () => { audioEl.play().catch(()=>{}); document.removeEventListener('click', retry); };
                    document.addEventListener('click', retry);
                }
            );
            tryPlay();
        };
        pc.oniceconnectionstatechange = () => {
            console.log('[call] ICE state:', pc.iceConnectionState);
            if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                if (currentStatus !== 'active') { show('active'); startTimer(); }
            }
            if (pc.iceConnectionState === 'failed') {
                showError('Bağlantı kurulamadı (NAT/firewall). TURN sunucusu gerekebilir.');
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
    async function makeOffer() {
        const stream = await getMic();
        const peer = buildPc();
        stream.getTracks().forEach(t => peer.addTrack(t, stream));
        const offer = await peer.createOffer({ offerToReceiveAudio: true });
        // Opus 48kHz mono — sabit kalite (kullanılabilirlik için bitrate'i sınırlama)
        await peer.setLocalDescription(offer);
        pushSignal('offer', { sdp: peer.localDescription.sdp, type: peer.localDescription.type });
    }
    async function handleRemoteOffer(payload) {
        const stream = await getMic();
        const peer = buildPc();
        stream.getTracks().forEach(t => peer.addTrack(t, stream));
        await peer.setRemoteDescription({ type: payload.type, sdp: payload.sdp });
        remoteDescSet = true;
        await drainIceQueue();
        const answer = await peer.createAnswer();
        await peer.setLocalDescription(answer);
        pushSignal('answer', { sdp: peer.localDescription.sdp, type: peer.localDescription.type });
    }
    async function handleRemoteAnswer(payload) {
        if (!pc) return;
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
                startSignalPolling();
                return;
            }
            // Outgoing → karşı taraf kabul etti
            if (currentStatus === 'outgoing' && call.status === 'accepted' && currentCallId === call.id) {
                // initiator → offer yap
                if (isInitiator) await makeOffer();
                show('active');
                if (!timerHandle) startTimer();
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
        try {
            const res = await fetch(callUrl('accept'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!data.success) { showError(data.message || 'Kabul edilemedi.'); return; }
            // mic iste, hazır ol — offer karşı taraftan gelecek
            await getMic();
            buildPc();
            // localStream'i şimdi ekle (handleRemoteOffer'da yine eklenecek ama eklenmiş olabilir)
        } catch (e) {
            showError('Bağlantı hatası.');
        }
    }
    async function endCall(notifyServer) {
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
        btnMute.textContent = muted ? '🔇 Mikrofon Kapalı' : '🎙 Mikrofon Açık';
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
