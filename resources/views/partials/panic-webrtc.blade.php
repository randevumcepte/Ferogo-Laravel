{{-- Panik WebRTC çekirdeği — kişi (arayan) ile destek çalışanı (cevaplayan) arasında
     sesli görüşme. call-widget'ın kanıtlanmış bağlantı mantığından uyarlandı.
     window.PanicRTC.start({role, pushUrl, pullUrl, csrf, onStatus, onEnd}) ile kullanılır. --}}
<audio id="panic-rtc-audio" autoplay playsinline style="display:none"></audio>
<script>
(function () {
    if (window.PanicRTC) return;

    const ICE_SERVERS = (function () {
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
        return list;
    })();
    const AUDIO = { audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 }, video: false };
    const POLL_MS = 500;

    const audioEl = document.getElementById('panic-rtc-audio');

    let opts = null, pc = null, localStream = null, lastId = 0, pollHandle = null;
    let remoteSet = false, iceQueue = [], offerMade = false, pulling = false, busy = false;

    function status(s) { if (opts && opts.onStatus) try { opts.onStatus(s); } catch (_) {} }

    async function getMic() {
        if (localStream) return localStream;
        if (!window.isSecureContext) throw new Error('HTTPS gerekli');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error('getUserMedia yok');
        localStream = await navigator.mediaDevices.getUserMedia(AUDIO);
        return localStream;
    }

    function buildPc() {
        if (pc) return pc;
        pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
        pc.onicecandidate = (ev) => { if (ev.candidate) push('ice', { candidate: ev.candidate.toJSON() }); };
        pc.ontrack = (ev) => {
            let stream = (ev.streams && ev.streams[0]) ? ev.streams[0] : (audioEl.srcObject instanceof MediaStream ? audioEl.srcObject : new MediaStream());
            if (!(ev.streams && ev.streams[0])) stream.addTrack(ev.track);
            audioEl.srcObject = stream; audioEl.volume = 1.0; audioEl.muted = false;
            audioEl.play().catch(() => {
                const retry = () => { audioEl.play().catch(()=>{}); document.removeEventListener('click', retry); };
                document.addEventListener('click', retry);
            });
        };
        pc.oniceconnectionstatechange = () => {
            const st = pc.iceConnectionState;
            if (st === 'connected' || st === 'completed') status('active');
            if (st === 'failed') { status('failed'); setTimeout(() => hangup(true), 1200); }
        };
        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'connected') status('active');
            if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') { setTimeout(() => hangup(true), 800); }
        };
        return pc;
    }

    function addTracks(peer, stream) {
        const senders = peer.getSenders();
        stream.getTracks().forEach(t => { if (!senders.some(s => s.track && s.track.id === t.id)) peer.addTrack(t, stream); });
    }
    function fixSdp(sdp) { return sdp.endsWith('\r\n') ? sdp : sdp + '\r\n'; }

    async function makeOffer() {
        if (offerMade) return; offerMade = true;
        const stream = await getMic();
        const peer = buildPc();
        addTracks(peer, stream);
        const offer = await peer.createOffer();
        await peer.setLocalDescription(offer);
        push('offer', { sdp: peer.localDescription.sdp, type: peer.localDescription.type });
    }
    async function handleOffer(payload) {
        const peer = buildPc();
        await peer.setRemoteDescription({ type: payload.type, sdp: fixSdp(payload.sdp) });
        remoteSet = true;
        const stream = await getMic();
        addTracks(peer, stream);
        await drainIce();
        const answer = await peer.createAnswer();
        await peer.setLocalDescription(answer);
        push('answer', { sdp: peer.localDescription.sdp, type: peer.localDescription.type });
    }
    async function handleAnswer(payload) {
        if (!pc) return;
        await pc.setRemoteDescription({ type: payload.type, sdp: fixSdp(payload.sdp) });
        remoteSet = true; await drainIce();
    }
    async function handleIce(payload) {
        if (!pc) return;
        if (!remoteSet) { iceQueue.push(payload.candidate); return; }
        try { await pc.addIceCandidate(new RTCIceCandidate(payload.candidate)); } catch (_) {}
    }
    async function drainIce() { while (iceQueue.length) { try { await pc.addIceCandidate(new RTCIceCandidate(iceQueue.shift())); } catch (_) {} } }

    async function push(type, payload) {
        try {
            await fetch(opts.pushUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': opts.csrf || '', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ type, payload }),
            });
        } catch (_) {}
    }
    async function pull() {
        if (pulling) return; pulling = true;
        try {
            const res = await fetch(opts.pullUrl + '?since_id=' + lastId, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            for (const s of (data.signals || [])) {
                lastId = Math.max(lastId, s.id);
                if (s.type === 'offer') await handleOffer(s.payload);
                else if (s.type === 'answer') await handleAnswer(s.payload);
                else if (s.type === 'ice') await handleIce(s.payload);
                else if (s.type === 'bye') hangup(false);
            }
        } catch (_) {} finally { pulling = false; }
    }

    async function start(options) {
        if (busy) return; busy = true;
        opts = options; lastId = 0; remoteSet = false; iceQueue = []; offerMade = false;
        status('connecting');
        try {
            await getMic();
            buildPc();
            if (opts.role === 'caller') await makeOffer();
            if (pollHandle) clearInterval(pollHandle);
            pollHandle = setInterval(pull, POLL_MS);
            pull();
        } catch (e) {
            status('mic-error');
            busy = false;
            throw e;
        }
    }

    function hangup(notify) {
        if (notify && opts) push('bye', { reason: 'hangup' });
        if (pollHandle) { clearInterval(pollHandle); pollHandle = null; }
        if (pc) { try { pc.close(); } catch (_) {} pc = null; }
        if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }
        if (audioEl.srcObject) audioEl.srcObject = null;
        remoteSet = false; iceQueue = []; offerMade = false; pulling = false; busy = false; lastId = 0;
        status('ended');
        if (opts && opts.onEnd) try { opts.onEnd(); } catch (_) {}
    }

    function toggleMute() {
        if (!localStream) return false;
        const t = localStream.getAudioTracks()[0]; if (!t) return false;
        t.enabled = !t.enabled; return !t.enabled; // true = muted
    }

    window.PanicRTC = { start, hangup, toggleMute, isActive: () => busy };
})();
</script>
