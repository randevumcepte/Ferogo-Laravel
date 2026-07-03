<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Güvenli Ödeme · Ferxgo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { brand: { DEFAULT: '#F0C040', 500: '#F0C040', 600: '#D9A621' } } } }
        }
    </script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-black text-white min-h-screen flex flex-col">

    <header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10 px-4 py-3">
        <div class="max-w-3xl mx-auto flex items-center justify-between gap-3">
            <div class="text-sm font-bold">
                <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
                <span class="ml-2 text-xs text-zinc-500">Güvenli Ödeme (PayTR)</span>
            </div>
            <a href="{{ route('driver.packages.index') }}" class="text-xs text-zinc-400 hover:text-white">İptal et</a>
        </div>
    </header>

    <main class="flex-1 max-w-3xl w-full mx-auto px-4 py-5">
        <div class="rounded-2xl border border-brand/30 bg-brand/[0.06] p-4 mb-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-[10px] uppercase tracking-[0.25em] text-brand font-bold">Paket</div>
                    <div class="text-lg font-extrabold mt-0.5">{{ $package->label() }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] uppercase tracking-wider text-zinc-500">Tutar</div>
                    <div class="text-xl font-black tabular-nums">{{ number_format($package->price, 2, ',', '.') }} ₺</div>
                </div>
            </div>
        </div>

        {{-- PayTR iframe — kart, saklı kart, 3D Secure ve Masterpass burada otomatik --}}
        <div class="rounded-2xl overflow-hidden bg-white" style="min-height: 600px;">
            <iframe src="{{ $iframeUrl }}"
                    id="paytriframe"
                    frameborder="0"
                    style="width: 100%; height: 600px; border: 0;"></iframe>
        </div>

        <p class="text-[11px] text-zinc-600 mt-3 leading-relaxed text-center">
            Kart bilgilerin Ferxgo'ya iletilmez; ödeme PayTR ve bankan arasında gerçekleşir.
            Ödeme onaylandığında <span class="text-brand">birkaç saniye içinde</span> paketin aktive olur.
        </p>
    </main>

    {{-- PayTR iframe resizer — iframe içerik yüksekliğine göre kendini ayarlar --}}
    <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
    <script>
        // Sürücü iframe içinde ödemeyi tamamladıktan sonra success/fail URL'lerine yönlendirilir.
        // Polling: paket aktive olduysa otomatik paketler sayfasına dön (UX).
        const STATUS_URL = '{{ route('driver.packages.status', ['package' => $package->id]) }}';
        let pollHandle = setInterval(async () => {
            try {
                const r = await fetch(STATUS_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) return;
                const d = await r.json();
                if (d.status === 'active') {
                    clearInterval(pollHandle);
                    window.location.href = '{{ route('driver.packages.index') }}';
                } else if (d.status === 'failed') {
                    clearInterval(pollHandle);
                    window.location.href = '{{ route('driver.packages.failure', ['package' => $package->id]) }}';
                }
            } catch (_) {}
        }, 3000);

        // PayTR iframe resizer init (yüksekliği otomatik ayarlar)
        try {
            if (window.iFrameResize) {
                iFrameResize({}, '#paytriframe');
            }
        } catch (_) {}
    </script>
</body>
</html>
