{{-- ───────────────────────────────────────────────────────────
     Uygulama indirme QR kartı — sağ alt köşeden yukarı kayar.
     Sadece masaüstünde (lg+) gösterilir (QR'ı telefonla taramak için).
     "Bugünlük Gizle" → localStorage'da bugünün tarihini tutar.
     QR ŞU AN SAHTE (dekoratif SVG). Uygulama yayınlanınca gerçek
     store QR görseliyle değiştirilecek.
     embed modunda gizli.
─────────────────────────────────────────────────────────── --}}
@unless(request()->boolean('embed'))
<div id="app-qr-card"
     class="hidden lg:block fixed bottom-5 right-5 z-[60] w-[340px] translate-y-8 opacity-0 pointer-events-none
            transition-all duration-500 ease-out">
    <div class="relative rounded-2xl bg-zinc-900/95 border border-white/10 shadow-2xl shadow-black/50 backdrop-blur-md p-5">

        {{-- Kapat --}}
        <button type="button" id="app-qr-close" aria-label="Kapat"
                class="absolute top-3 right-3 w-7 h-7 rounded-full flex items-center justify-center text-zinc-400 hover:text-white hover:bg-white/10 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <div class="text-lg font-bold text-white pr-6">Uygulamayı İndirin</div>
        <p class="mt-1 text-xs text-zinc-400 leading-relaxed">FerXGo'yu Android veya iOS'ta hemen kullanın.</p>

        {{-- QR (beyaz zemin, kontrast için) --}}
        <div class="mt-4 flex items-center gap-4">
            <div class="shrink-0 rounded-xl bg-white p-2.5 shadow-lg">
                @php
                    // Deterministik sahte QR: 25x25 modül, 3 köşe finder deseni + sözde-rastgele veri.
                    $n = 25; $cell = 6; $size = $n * $cell;
                    $isFinder = function ($x, $y) use ($n) {
                        foreach ([[0,0],[$n-7,0],[0,$n-7]] as [$ox,$oy]) {
                            $dx = $x-$ox; $dy = $y-$oy;
                            if ($dx>=0 && $dx<=6 && $dy>=0 && $dy<=6) {
                                $ring   = ($dx==0||$dx==6||$dy==0||$dy==6);
                                $center = ($dx>=2&&$dx<=4&&$dy>=2&&$dy<=4);
                                return $ring || $center ? 1 : -1; // 1=dolu, -1=finder içi boş
                            }
                        }
                        return 0; // finder değil
                    };
                @endphp
                <svg width="118" height="118" viewBox="0 0 {{ $size }} {{ $size }}" shape-rendering="crispEdges" role="img" aria-label="Uygulama indirme QR kodu (örnek)">
                    <rect width="{{ $size }}" height="{{ $size }}" fill="#ffffff"/>
                    @for($y=0; $y<$n; $y++)
                        @for($x=0; $x<$n; $x++)
                            @php
                                $f = $isFinder($x,$y);
                                if ($f === 1) { $fill = true; }
                                elseif ($f === -1) { $fill = false; }
                                else { $fill = (($x*3 + $y*7 + (($x*$y)%11) + $x) % 5) < 2; }
                            @endphp
                            @if($fill)
                                <rect x="{{ $x*$cell }}" y="{{ $y*$cell }}" width="{{ $cell }}" height="{{ $cell }}" fill="#0a0a0a"/>
                            @endif
                        @endfor
                    @endfor
                </svg>
            </div>
            <div class="text-xs text-zinc-400 leading-relaxed">
                <div class="text-white font-semibold mb-1">📷 Telefon kameranla tara</div>
                Kamerayı QR'a tut, uygulama sayfası açılsın.
            </div>
        </div>

        {{-- Store etiketleri (yayınlanınca linklenecek) --}}
        <div class="mt-4 flex items-center justify-center gap-2 text-xs text-zinc-500">
            <span class="inline-flex items-center gap-1"> App Store</span>
            <span class="text-zinc-700">·</span>
            <span class="inline-flex items-center gap-1">▶ Google Play</span>
            <span class="ml-1 text-[9px] font-bold bg-brand text-black px-1.5 py-0.5 rounded-full">Yakında</span>
        </div>

        {{-- Bugünlük gizle --}}
        <button type="button" id="app-qr-hide-today"
                class="mt-4 w-full py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-zinc-300 hover:text-white transition">
            Bugünlük Gizle
        </button>
    </div>
</div>

<script>
(function () {
    var card = document.getElementById('app-qr-card');
    if (!card) return;

    var KEY = 'ferxgo-appqr-hidden-until'; // localStorage: bu tarihe kadar gösterme (YYYY-MM-DD)
    var SESSION_KEY = 'ferxgo-appqr-closed'; // sessionStorage: bu oturumda kapatıldı

    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + (d.getMonth()+1) + '-' + d.getDate();
    }
    function shouldShow() {
        try {
            if (sessionStorage.getItem(SESSION_KEY)) return false;
            if (localStorage.getItem(KEY) === todayStr()) return false;
        } catch (e) {}
        return true;
    }
    function show() {
        card.classList.remove('translate-y-8', 'opacity-0', 'pointer-events-none');
    }
    function hide() {
        card.classList.add('translate-y-8', 'opacity-0', 'pointer-events-none');
    }

    document.getElementById('app-qr-close').addEventListener('click', function () {
        hide();
        try { sessionStorage.setItem(SESSION_KEY, '1'); } catch (e) {}
    });
    document.getElementById('app-qr-hide-today').addEventListener('click', function () {
        hide();
        try { localStorage.setItem(KEY, todayStr()); } catch (e) {}
    });

    if (shouldShow()) {
        // Sayfa oturduktan ~2.5 sn sonra yukarı kaydır
        setTimeout(show, 2500);
    }
})();
</script>
@endunless
