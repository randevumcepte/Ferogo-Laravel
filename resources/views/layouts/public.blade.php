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

    {{-- Mobil sticky action bar (Ara / WhatsApp) --}}
    @include('partials.mobile-action-bar')

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

    @stack('scripts')
</body>
</html>
