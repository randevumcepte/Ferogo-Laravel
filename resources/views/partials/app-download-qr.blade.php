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
     class="hidden lg:block fixed bottom-5 right-5 z-[60] w-[360px] translate-y-10 opacity-0 pointer-events-none
            transition-all duration-500 ease-out">

    {{-- Altın parıltı halesi --}}
    <div class="absolute -inset-3 bg-brand/20 blur-2xl rounded-[2rem] pointer-events-none"></div>

    {{-- Gradient kenarlık --}}
    <div class="relative rounded-3xl p-[1.5px] bg-gradient-to-br from-brand/70 via-white/10 to-brand/40 shadow-2xl shadow-black/60">
        <div class="relative rounded-3xl bg-gradient-to-b from-zinc-900 to-black overflow-hidden">

            {{-- Üst dekoratif ışık --}}
            <div class="absolute -top-16 -right-10 w-40 h-40 bg-brand/25 blur-3xl rounded-full pointer-events-none"></div>

            {{-- Kapat --}}
            <button type="button" id="app-qr-close" aria-label="Kapat"
                    class="absolute top-3 right-3 z-10 w-7 h-7 rounded-full flex items-center justify-center text-zinc-400 hover:text-white hover:bg-white/10 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="relative p-5">
                {{-- Marka + puan --}}
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand to-brand-600 flex items-center justify-center text-black font-extrabold text-lg shadow-lg shadow-brand/30">
                        <span>F</span>
                    </div>
                    <div class="leading-tight">
                        <div class="text-sm font-extrabold">
                            <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
                        </div>
                        <div class="flex items-center gap-1 text-[10px] text-zinc-400">
                            <span class="text-brand tracking-tighter">★★★★★</span> 4.9
                        </div>
                    </div>
                </div>

                <div class="text-lg font-bold text-white pr-6 leading-tight">Uygulamayı cebine al 📲</div>
                <p class="mt-1 text-xs text-zinc-400 leading-relaxed">Rezervasyon, canlı takip ve anlık teklifler telefonunda.</p>

                {{-- QR + özellikler --}}
                <div class="mt-4 flex items-center gap-4">
                    {{-- QR + tarayıcı köşe çentikleri --}}
                    <div class="relative shrink-0">
                        <div class="rounded-xl bg-white p-2.5 shadow-lg">
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
                            <svg width="112" height="112" viewBox="0 0 {{ $size }} {{ $size }}" shape-rendering="crispEdges" role="img" aria-label="Uygulama indirme QR kodu (örnek)">
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
                        {{-- Marka köşe çentikleri (tarayıcı estetiği) --}}
                        <span class="absolute -top-1 -left-1 w-4 h-4 border-t-2 border-l-2 border-brand rounded-tl-md"></span>
                        <span class="absolute -top-1 -right-1 w-4 h-4 border-t-2 border-r-2 border-brand rounded-tr-md"></span>
                        <span class="absolute -bottom-1 -left-1 w-4 h-4 border-b-2 border-l-2 border-brand rounded-bl-md"></span>
                        <span class="absolute -bottom-1 -right-1 w-4 h-4 border-b-2 border-r-2 border-brand rounded-br-md"></span>
                    </div>

                    <div class="flex-1">
                        <div class="text-xs text-white font-semibold flex items-center gap-1.5 mb-2">
                            <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                            Kamerayla tara
                        </div>
                        <ul class="space-y-1.5 text-[11px] text-zinc-400">
                            <li class="flex items-center gap-1.5"><span class="text-brand">✓</span> Canlı konum takibi</li>
                            <li class="flex items-center gap-1.5"><span class="text-brand">✓</span> Hızlı sürücü onayı</li>
                            <li class="flex items-center gap-1.5"><span class="text-brand">✓</span> Anlık fiyat teklifi</li>
                        </ul>
                    </div>
                </div>

                {{-- Store rozetleri (belirgin) --}}
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="relative inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-black border border-white/20 shadow-md cursor-default select-none" title="Çok yakında">
                        <svg class="w-5 h-5 text-white shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.377 0-2.332-1.26-3.428-2.8-1.287-1.82-2.323-4.63-2.323-7.28 0-4.28 2.797-6.55 5.552-6.55 1.448 0 2.675.95 3.6.95.865 0 2.222-1.01 3.902-1.01.613 0 2.886.06 4.374 2.19-.13.09-2.383 1.37-2.383 4.19 0 3.26 2.854 4.42 2.955 4.45z"/></svg>
                        <span class="text-white font-semibold text-xs">App Store</span>
                    </div>
                    <div class="relative inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-black border border-white/20 shadow-md cursor-default select-none" title="Çok yakında">
                        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24"><path fill="#00D1C1" d="M3.6 2.3c-.3.2-.5.5-.5 1v17.4c0 .5.2.8.5 1l9.3-9.7z"/><path fill="#FFCE00" d="M17.2 12l-3.1-3.2-9.3-6.3c-.2-.1-.4-.2-.6-.2z"/><path fill="#FF4B3E" d="M4.2 22.3c.2 0 .4-.1.6-.2l9.3-6.3-2.9-3z"/><path fill="#00A0FF" d="M20.4 11.1c.6.3 1 .8 1 .9s-.4.6-1 .9l-3.2 1.7-3.1-3.3 3.1-3.3z"/></svg>
                        <span class="text-white font-semibold text-xs">Google Play</span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-center">
                    <span class="text-[10px] font-bold bg-brand/15 text-brand border border-brand/30 px-2 py-0.5 rounded-full">🚀 Çok yakında yayında</span>
                </div>

                {{-- Bugünlük gizle --}}
                <button type="button" id="app-qr-hide-today"
                        class="mt-3 w-full py-2 rounded-lg text-xs text-zinc-500 hover:text-zinc-300 transition">
                    Bugünlük gizle
                </button>
            </div>
        </div>
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
        card.classList.remove('translate-y-10', 'opacity-0', 'pointer-events-none');
    }
    function hide() {
        card.classList.add('translate-y-10', 'opacity-0', 'pointer-events-none');
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
