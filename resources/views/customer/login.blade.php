<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Müşteri Girişi · Ferogo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { brand: { DEFAULT: '#F0C040', 500: '#F0C040', 600: '#D9A621' } } } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes drift-1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%      { transform: translate(40px, -30px) scale(1.05); }
        }
        @keyframes drift-2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%      { transform: translate(-30px, 25px) scale(1.08); }
        }
        @keyframes drift-3 {
            0%, 100% { transform: translate(0, 0); }
            50%      { transform: translate(20px, 30px); }
        }
        .drift-1 { animation: drift-1 14s ease-in-out infinite; }
        .drift-2 { animation: drift-2 18s ease-in-out infinite; }
        .drift-3 { animation: drift-3 22s ease-in-out infinite; }
        .grid-bg {
            background-image:
                linear-gradient(rgba(240,192,64,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(240,192,64,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50%      { opacity: 1; transform: scale(1.2); }
        }
        .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-black text-white min-h-screen flex flex-col relative overflow-hidden pb-20 md:pb-0">

{{-- Background atmosphere --}}
<div class="absolute inset-0 grid-bg opacity-40 pointer-events-none"></div>
<div class="drift-1 absolute top-[-10%] left-[-10%] w-[40rem] h-[40rem] rounded-full bg-brand/15 blur-[150px] pointer-events-none"></div>
<div class="drift-2 absolute bottom-[-15%] right-[-10%] w-[36rem] h-[36rem] rounded-full bg-brand/10 blur-[140px] pointer-events-none"></div>
<div class="drift-3 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[30rem] h-[30rem] rounded-full bg-emerald-500/[0.04] blur-[120px] pointer-events-none"></div>

{{-- Top-left logo (sticky to corner) --}}
<header class="relative z-10 px-6 py-5">
    <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
        <span class="text-xl font-extrabold tracking-tight">
            <span class="text-white">FERO</span><span class="text-brand">GO</span>
        </span>
    </a>
</header>

<div class="relative z-10 flex-1 flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-md">
        {{-- Big center logo (same as homepage HERO) --}}
        <a href="{{ route('home') }}" class="block text-center mb-8">
            <span class="text-4xl sm:text-5xl font-extrabold tracking-tight">
                <span class="text-white">FERO</span><span class="text-brand glow-text">GO</span>
            </span>
            <div class="mt-2 inline-flex items-center gap-1.5 text-[10px] uppercase tracking-[0.3em] text-zinc-500">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span>
                Premium Şoförlü Transfer
            </div>
        </a>

        <div class="relative bg-zinc-950/80 backdrop-blur-xl border border-white/10 rounded-3xl p-7 shadow-2xl shadow-black/60">
            {{-- Subtle glow on top edge --}}
            <div class="absolute -top-px left-12 right-12 h-px bg-gradient-to-r from-transparent via-brand/40 to-transparent"></div>
            {{-- Step 1: phone --}}
            <div id="step-phone">
                <h1 class="text-2xl font-bold mb-1">Müşteri Girişi</h1>
                <p class="text-sm text-zinc-400 mb-6">Telefonuna SMS ile kod göndereceğiz.</p>

                <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Telefon Numarası</label>
                <input id="phone-input" type="tel" autocomplete="tel" placeholder="0532 000 00 00"
                       class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-4 py-3 text-base text-white placeholder-zinc-600 focus:outline-none transition mb-4">

                <div id="phone-error" class="hidden mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300"></div>

                <button id="send-otp" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 disabled:bg-zinc-700 disabled:text-zinc-500 text-black font-bold transition shadow-xl shadow-brand/30">
                    <span id="send-otp-text">Kod Gönder</span>
                </button>

                <p class="text-[11px] text-zinc-500 mt-4 text-center leading-relaxed">
                    Hesabın yoksa SMS doğrulamasıyla otomatik açılır. Şifre gerekmez.
                </p>
            </div>

            {{-- Step 2: code --}}
            <div id="step-code" class="hidden">
                <button id="back-to-phone" class="text-xs text-zinc-500 hover:text-zinc-300 mb-3 transition">← Numarayı düzelt</button>
                <h1 class="text-2xl font-bold mb-1">Kodu gir</h1>
                <p class="text-sm text-zinc-400 mb-6"><span id="code-phone-label" class="text-zinc-200 font-medium">—</span> numarasına gönderdik.</p>

                <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">6 Haneli Kod</label>
                <input id="code-input" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000"
                       class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-4 py-3 text-center text-2xl tracking-[0.4em] font-mono text-white placeholder-zinc-600 focus:outline-none transition mb-4">

                <div id="code-dev" class="hidden mb-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-200">
                    <span class="font-bold">DEV:</span> Kodun → <span id="code-dev-value" class="font-mono"></span>
                </div>

                <div id="code-error" class="hidden mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300"></div>

                <button id="verify-otp" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 disabled:bg-zinc-700 disabled:text-zinc-500 text-black font-bold transition shadow-xl shadow-brand/30">
                    <span id="verify-otp-text">Giriş Yap</span>
                </button>

                <div class="text-center mt-4">
                    <button id="resend-otp" disabled class="text-xs text-zinc-500 hover:text-brand disabled:hover:text-zinc-500 disabled:cursor-not-allowed underline underline-offset-2 transition">
                        Tekrar gönder (<span id="resend-countdown">60</span>s)
                    </button>
                </div>
            </div>
        </div>

        <p class="text-center text-xs text-zinc-500 mt-6">
            Sürücü müsün? <a href="{{ route('driver.login') }}" class="text-brand hover:text-brand-600 underline underline-offset-2">Sürücü girişi</a>
        </p>
    </div>
</div>

<script>
(function () {
    'use strict';

    const SEND_URL   = '{{ route('phone.send_otp') }}';
    const VERIFY_URL = '{{ route('phone.verify_otp') }}';
    const PANEL_URL  = @json($returnUrl ?: route('customer.panel'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const $ = (id) => document.getElementById(id);
    let resendHandle = null;

    function show(name) {
        $('step-phone').classList.toggle('hidden', name !== 'phone');
        $('step-code').classList.toggle('hidden', name !== 'code');
    }

    $('send-otp').addEventListener('click', async () => {
        const phone = $('phone-input').value.trim();
        $('phone-error').classList.add('hidden');
        if (!/^\+?\d[\d\s]{8,}$/.test(phone)) {
            $('phone-error').textContent = 'Geçerli bir telefon numarası gir.';
            $('phone-error').classList.remove('hidden');
            return;
        }
        $('send-otp').disabled = true;
        $('send-otp-text').textContent = 'Gönderiliyor…';
        try {
            const res = await fetch(SEND_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ phone }),
            });
            const data = await res.json();
            if (!data.ok) {
                $('phone-error').textContent = data.message || 'Kod gönderilemedi.';
                $('phone-error').classList.remove('hidden');
                return;
            }
            $('code-phone-label').textContent = phone;
            if (data.dev_code) {
                $('code-dev').classList.remove('hidden');
                $('code-dev-value').textContent = data.dev_code;
            } else {
                $('code-dev').classList.add('hidden');
            }
            startResendCountdown();
            show('code');
            setTimeout(() => $('code-input').focus(), 80);
        } catch (_) {
            $('phone-error').textContent = 'Bağlantı hatası.';
            $('phone-error').classList.remove('hidden');
        } finally {
            $('send-otp').disabled = false;
            $('send-otp-text').textContent = 'Kod Gönder';
        }
    });

    function startResendCountdown() {
        let s = 60;
        $('resend-otp').disabled = true;
        $('resend-countdown').textContent = s;
        if (resendHandle) clearInterval(resendHandle);
        resendHandle = setInterval(() => {
            s -= 1;
            $('resend-countdown').textContent = s;
            if (s <= 0) {
                clearInterval(resendHandle);
                $('resend-otp').disabled = false;
                $('resend-otp').innerHTML = 'Tekrar gönder';
            }
        }, 1000);
    }

    $('resend-otp').addEventListener('click', () => $('send-otp').click());
    $('back-to-phone').addEventListener('click', () => {
        if (resendHandle) clearInterval(resendHandle);
        show('phone');
    });

    $('verify-otp').addEventListener('click', async () => {
        const code = $('code-input').value.trim();
        const phone = $('phone-input').value.trim();
        $('code-error').classList.add('hidden');
        if (!/^\d{6}$/.test(code)) {
            $('code-error').textContent = '6 haneli kodu eksiksiz gir.';
            $('code-error').classList.remove('hidden');
            return;
        }
        $('verify-otp').disabled = true;
        $('verify-otp-text').textContent = 'Doğrulanıyor…';
        try {
            const res = await fetch(VERIFY_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ phone, code }),
            });
            const data = await res.json();
            if (!data.ok) {
                $('code-error').textContent = data.message || 'Kod hatalı.';
                $('code-error').classList.remove('hidden');
                return;
            }
            window.location.href = PANEL_URL;
        } catch (_) {
            $('code-error').textContent = 'Bağlantı hatası.';
            $('code-error').classList.remove('hidden');
        } finally {
            $('verify-otp').disabled = false;
            $('verify-otp-text').textContent = 'Giriş Yap';
        }
    });
})();
</script>

@include('partials.mobile-action-bar')
</body>
</html>
