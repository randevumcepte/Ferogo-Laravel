<!DOCTYPE html>
<html lang="tr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ferogo · Profesyonel Şoförlü Transfer')</title>
    <meta name="description" content="@yield('description', 'İzmir\'de profesyonel şoförlü transfer. Şeffaf fiyat, lüks araçlar, 7/24 hizmet.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            DEFAULT: '#F0C040',
                            50: '#FEF9E7',
                            100: '#FDF0C1',
                            500: '#F0C040',
                            600: '#D9A621',
                            700: '#B68918',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .gradient-radial {
            background: radial-gradient(ellipse at top, rgba(240,192,64,0.12), transparent 50%),
                        radial-gradient(ellipse at bottom, rgba(240,192,64,0.08), transparent 60%);
        }
    </style>

    @stack('head')
</head>
<body class="bg-black text-white antialiased pb-20 md:pb-0">

    {{-- Navigation — ?embed=1 modunda gizlenir (müşteri paneli içinde iframe için) --}}
    @unless(request()->boolean('embed'))
    <nav class="fixed top-0 inset-x-0 z-50 backdrop-blur-md bg-black/50 border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="text-2xl font-extrabold tracking-tight">
                    <span class="text-white">FERO</span><span class="text-brand">GO</span>
                </span>
            </a>

            {{-- Desktop menü --}}
            <div class="hidden md:flex items-center gap-6 text-sm text-zinc-300">
                <a href="{{ route('home') }}#hizmetler" class="hover:text-white transition">Hizmetler</a>
                <a href="{{ route('ride.show') }}" class="hover:text-white transition {{ request()->routeIs('ride.*') ? 'text-white' : '' }}">Yolculuk Yapın</a>
                <a href="{{ route('driver.apply') }}" class="hover:text-white transition {{ request()->routeIs('driver.*') ? 'text-white' : '' }}">Sürücü Olun</a>
                <a href="tel:+908508401377" class="text-white font-medium">0850 840 13 77</a>
                @auth
                    @if (auth()->user()->type === 'customer')
                        <a href="{{ route('customer.panel') }}" class="px-3 py-1.5 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold text-xs transition">Hesabım</a>
                    @endif
                @else
                    <a href="{{ route('customer.login') }}" class="px-3 py-1.5 rounded-xl border border-white/20 hover:border-brand/40 hover:text-white text-xs font-semibold transition">Giriş Yap</a>
                @endauth
            </div>

            {{-- Mobil hamburger butonu --}}
            <button type="button" id="mobile-menu-toggle"
                class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-white transition"
                aria-label="Menüyü aç" aria-expanded="false" aria-controls="mobile-menu">
                <svg id="mobile-menu-icon-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg id="mobile-menu-icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mobil açılır menü --}}
        <div id="mobile-menu" class="md:hidden hidden border-t border-white/5 bg-black/95 backdrop-blur-md">
            <div class="px-4 py-4 space-y-1">
                <a href="{{ route('home') }}#hizmetler" class="block px-3 py-3 rounded-xl text-zinc-200 hover:bg-white/5 hover:text-white transition">Hizmetler</a>
                <a href="{{ route('ride.show') }}" class="block px-3 py-3 rounded-xl text-zinc-200 hover:bg-white/5 hover:text-white transition {{ request()->routeIs('ride.*') ? 'bg-white/5 text-white' : '' }}">Yolculuk Yapın</a>
                <a href="{{ route('driver.apply') }}" class="block px-3 py-3 rounded-xl text-zinc-200 hover:bg-white/5 hover:text-white transition {{ request()->routeIs('driver.*') ? 'bg-white/5 text-white' : '' }}">Sürücü Olun</a>
                <a href="tel:+908508401377" class="flex items-center gap-2 px-3 py-3 rounded-xl text-white font-medium hover:bg-white/5 transition">
                    <svg class="w-4 h-4 text-brand" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                    0850 840 13 77
                </a>
                <div class="pt-2 mt-2 border-t border-white/5">
                    @auth
                        @if (auth()->user()->type === 'customer')
                            <a href="{{ route('customer.panel') }}" class="block w-full text-center px-4 py-3 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold text-sm transition">Hesabım</a>
                        @endif
                    @else
                        <a href="{{ route('customer.login') }}" class="block w-full text-center px-4 py-3 rounded-xl border border-white/20 hover:border-brand/40 text-white text-sm font-semibold transition">Giriş Yap</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
    @endunless

    @yield('content')

    {{-- Footer — embed modunda gizlenir --}}
    @unless(request()->boolean('embed'))
    <footer class="bg-zinc-950 border-t border-white/5 mt-24">
        <div class="max-w-7xl mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-3 gap-8 text-sm text-zinc-400">
            <div>
                <div class="text-2xl font-extrabold mb-3">
                    <span class="text-white">FERO</span><span class="text-brand">GO</span>
                </div>
                <p class="leading-relaxed">Profesyonel şoförlü transfer platformu. T.C. Ulaştırma ve Altyapı Bakanlığı mevzuatı kapsamında lisanslı.</p>
            </div>
            <div>
                <div class="text-white font-semibold mb-3">Hizmetlerimiz</div>
                <ul class="space-y-2">
                    <li>Havalimanı Transferi</li>
                    <li>Kurumsal Seyahat</li>
                    <li>VIP Transfer</li>
                    <li>Şehir İçi Ulaşım</li>
                </ul>
            </div>
            <div>
                <div class="text-white font-semibold mb-3">İletişim</div>
                <ul class="space-y-2">
                    <li>📞 0850 840 13 77</li>
                    <li>💬 WhatsApp 7/24</li>
                    <li>🌍 İzmir merkez, Türkiye geneli yakında</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/5 py-6 text-center text-xs text-zinc-500">
            &copy; {{ date('Y') }} Ferogo · Tüm hakları saklıdır
        </div>
    </footer>
    @endunless

    {{-- Mobil sticky action bar (Ara / WhatsApp) — embed modunda gizlenir --}}
    @unless(request()->boolean('embed'))
    <div id="mobile-action-bar"
        class="md:hidden fixed bottom-0 inset-x-0 z-40 translate-y-full opacity-0 transition-all duration-300 ease-out pointer-events-none">
        <div class="bg-black/90 backdrop-blur-xl border-t border-white/10 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] px-3">
            <div class="flex gap-2">
                <a href="tel:+908508401377"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-3 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold text-sm transition shadow-lg shadow-brand/30 whitespace-nowrap">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                    <span class="flex flex-col items-start leading-tight">
                        <span class="text-[10px] font-medium opacity-75">Hemen Arayın</span>
                        <span>0850 840 13 77</span>
                    </span>
                </a>
                <a href="https://wa.me/908508401377"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm transition shadow-lg shadow-emerald-500/30 whitespace-nowrap">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    <span class="flex flex-col items-start leading-tight">
                        <span class="text-[10px] font-medium opacity-90">WhatsApp Destek</span>
                        <span>Hemen Yazın</span>
                    </span>
                </a>
            </div>
        </div>
    </div>
    @endunless

    {{-- Cookie Consent Banner — embed modunda gizlenir (parent zaten gösteriyor) --}}
    @unless(request()->boolean('embed'))
    <div id="cookie-consent" class="fixed bottom-0 inset-x-0 z-[100] translate-y-full transition-transform duration-500 ease-out" role="dialog" aria-live="polite" aria-label="Çerez bildirimi">
        <div class="mx-auto max-w-4xl m-3 md:m-6">
            <div class="bg-zinc-900/95 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl shadow-black/50 p-5 md:p-6">
                <div class="flex flex-col md:flex-row md:items-center gap-5">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xl">🍪</span>
                            <h3 class="text-white font-semibold text-base">Çerez Kullanımı</h3>
                        </div>
                        <p class="text-sm text-zinc-400 leading-relaxed">
                            Web sitemizde deneyiminizi iyileştirmek, trafiği analiz etmek ve hizmetlerimizi geliştirmek için çerezler kullanıyoruz. <a href="#" class="text-brand hover:underline">Detaylı bilgi</a>.
                        </p>
                    </div>
                    <div class="flex gap-2 md:flex-shrink-0">
                        <button type="button" id="cookie-reject" class="flex-1 md:flex-none px-5 py-2.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 hover:text-white text-sm font-medium transition">
                            Reddet
                        </button>
                        <button type="button" id="cookie-accept" class="flex-1 md:flex-none px-5 py-2.5 rounded-full bg-brand hover:bg-brand-600 text-black text-sm font-bold transition shadow-lg shadow-brand/20">
                            Kabul Et
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endunless

    <script>
        (function() {
            const STORAGE_KEY = 'cookie-consent';
            const banner = document.getElementById('cookie-consent');
            if (!banner) return;

            if (localStorage.getItem(STORAGE_KEY)) return;

            setTimeout(() => banner.classList.remove('translate-y-full'), 600);

            function hide(decision) {
                localStorage.setItem(STORAGE_KEY, decision);
                localStorage.setItem(STORAGE_KEY + '-at', new Date().toISOString());
                banner.classList.add('translate-y-full');
                setTimeout(() => banner.remove(), 500);
                document.dispatchEvent(new CustomEvent('cookie-consent', { detail: { decision } }));
            }

            document.getElementById('cookie-accept').addEventListener('click', () => hide('accepted'));
            document.getElementById('cookie-reject').addEventListener('click', () => hide('rejected'));
        })();
    </script>

    {{-- Mobil menü toggle --}}
    <script>
        (function() {
            const toggle = document.getElementById('mobile-menu-toggle');
            const menu = document.getElementById('mobile-menu');
            const iconOpen = document.getElementById('mobile-menu-icon-open');
            const iconClose = document.getElementById('mobile-menu-icon-close');
            if (!toggle || !menu) return;

            function setOpen(open) {
                menu.classList.toggle('hidden', !open);
                iconOpen.classList.toggle('hidden', open);
                iconClose.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
            }

            toggle.addEventListener('click', () => {
                setOpen(menu.classList.contains('hidden'));
            });

            // İçindeki linke tıklandığında kapat
            menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setOpen(false)));

            // Dışına tıklayınca kapat
            document.addEventListener('click', (e) => {
                if (menu.classList.contains('hidden')) return;
                if (toggle.contains(e.target) || menu.contains(e.target)) return;
                setOpen(false);
            });

            // ESC ile kapat
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !menu.classList.contains('hidden')) setOpen(false);
            });
        })();
    </script>

    {{-- Mobil sticky action bar: kaydırınca alttan kayar --}}
    <script>
        (function() {
            const bar = document.getElementById('mobile-action-bar');
            if (!bar) return;

            const SHOW_AFTER = 240; // px
            let shown = false;

            function setShown(v) {
                if (v === shown) return;
                shown = v;
                if (v) {
                    bar.classList.remove('translate-y-full', 'opacity-0', 'pointer-events-none');
                } else {
                    bar.classList.add('translate-y-full', 'opacity-0', 'pointer-events-none');
                }
            }

            function onScroll() {
                setShown(window.scrollY > SHOW_AFTER);
            }

            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        })();
    </script>

    @stack('scripts')
</body>
</html>
