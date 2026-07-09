<!DOCTYPE html>
<html lang="tr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FerXGo · Paylaşımlı Yolculuk Platformu')</title>
    <meta name="description" content="@yield('description', 'FerXGo, bağımsız üye sürücüleri ve yolcuları buluşturan dijital paylaşımlı yolculuk platformudur. Şeffaf katkı payı, 7/24 platform erişimi.')">

    @include('partials.seo')

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
                    <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
                </span>
            </a>

            {{-- Desktop menü --}}
            <div class="hidden md:flex items-center gap-6 text-sm text-zinc-300">
                <a href="{{ route('home') }}#hizmetler" class="hover:text-white transition">Hizmetler</a>
                <a href="{{ route('ride.show') }}" class="hover:text-white transition {{ request()->routeIs('ride.*') ? 'text-white' : '' }}">Yolculuk Yapın</a>
                <a href="{{ route('driver.apply') }}" class="hover:text-white transition {{ request()->routeIs('driver.*') ? 'text-white' : '' }}">Üye Sürücü Olun</a>
                <a href="tel:+908503403039" class="text-white font-medium">0850 340 3039</a>
                @auth('customer')
                    @if (auth('customer')->user()->type === 'customer')
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
                <a href="{{ route('driver.apply') }}" class="block px-3 py-3 rounded-xl text-zinc-200 hover:bg-white/5 hover:text-white transition {{ request()->routeIs('driver.*') ? 'bg-white/5 text-white' : '' }}">Üye Sürücü Olun</a>
                <a href="tel:+908503403039" class="flex items-center gap-2 px-3 py-3 rounded-xl text-white font-medium hover:bg-white/5 transition">
                    <svg class="w-4 h-4 text-brand" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2z"/></svg>
                    0850 340 3039
                </a>
                <div class="pt-2 mt-2 border-t border-white/5">
                    @auth('customer')
                        @if (auth('customer')->user()->type === 'customer')
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
        <div class="max-w-7xl mx-auto px-6 py-12 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-x-8 gap-y-10 text-sm text-zinc-400">

            {{-- Marka + açıklama + güven + sosyal --}}
            <div class="col-span-2 md:col-span-3 lg:col-span-2">
                <div class="text-2xl font-extrabold mb-3">
                    <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
                </div>
                <p class="leading-relaxed max-w-sm">FerXGo, bağımsız üye sürücüler ile yolcuları dijital ortamda buluşturan bir paylaşımlı yolculuk platformudur. Yolculuk hizmeti üye sürücü ile yolcu arasında gerçekleşir; FerXGo yalnızca aracılık ve eşleştirme hizmeti sunar.</p>

                {{-- Güven rozetleri --}}
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs">
                    <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Doğrulanmış üye sürücüler</span>
                    <span class="inline-flex items-center gap-1.5"><span class="text-brand">🛡</span> KVKK korumalı</span>
                </div>

                {{-- Sosyal --}}
                <div class="mt-5">
                    <div class="text-white font-semibold mb-2 text-xs uppercase tracking-wider">Bizi Takip Et</div>
                    <div class="flex items-center gap-3">
                        <a href="#" aria-label="Instagram"
                           class="w-9 h-9 rounded-lg bg-white/5 border border-white/10 hover:border-brand/40 hover:text-brand flex items-center justify-center transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.6 0 4.9.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.86s0 3.6-.07 4.86c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.9.07s-3.6 0-4.86-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23C2.21 15.6 2.2 15.2 2.2 12s0-3.6.07-4.86c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.4 2.21 8.8 2.2 12 2.2zm0 1.8c-3.14 0-3.51.01-4.75.07-.9.04-1.38.19-1.71.32-.43.17-.74.37-1.06.69-.32.32-.52.63-.69 1.06-.13.33-.28.81-.32 1.71C3.21 8.79 3.2 9.16 3.2 12s.01 3.21.07 4.45c.04.9.19 1.38.32 1.71.17.43.37.74.69 1.06.32.32.63.52 1.06.69.33.13.81.28 1.71.32 1.24.06 1.61.07 4.75.07s3.51-.01 4.75-.07c.9-.04 1.38-.19 1.71-.32.43-.17.74-.37 1.06-.69.32-.32.52-.63.69-1.06.13-.33.28-.81.32-1.71.06-1.24.07-1.61.07-4.45s-.01-3.21-.07-4.45c-.04-.9-.19-1.38-.32-1.71a2.86 2.86 0 0 0-.69-1.06 2.86 2.86 0 0 0-1.06-.69c-.33-.13-.81-.28-1.71-.32C15.51 4.01 15.14 4 12 4zm0 3.06A4.94 4.94 0 1 1 12 16.94 4.94 4.94 0 0 1 12 7.06zm0 8.14A3.2 3.2 0 1 0 12 8.8a3.2 3.2 0 0 0 0 6.4zm6.28-8.34a1.15 1.15 0 1 1-2.3 0 1.15 1.15 0 0 1 2.3 0z"/></svg>
                        </a>
                        <a href="https://wa.me/905412948144" target="_blank" rel="noopener" aria-label="WhatsApp"
                           class="w-9 h-9 rounded-lg bg-white/5 border border-white/10 hover:border-brand/40 hover:text-brand flex items-center justify-center transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.04 2.5A9.5 9.5 0 0 0 3.9 16.86L2.5 21.5l4.76-1.25a9.5 9.5 0 1 0 4.78-17.75zm0 17.4a7.9 7.9 0 0 1-4.02-1.1l-.29-.17-2.82.74.75-2.75-.19-.3a7.9 7.9 0 1 1 6.57 3.58z"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Hızlı Bağlantılar --}}
            <div>
                <div class="text-white font-semibold mb-3 text-xs uppercase tracking-wider">Hızlı Bağlantılar</div>
                <ul class="space-y-2">
                    <li><a href="{{ route('home') }}" class="hover:text-white transition">Anasayfa</a></li>
                    <li><a href="{{ route('home') }}#rezervasyon" class="hover:text-white transition">Rezervasyon Yap</a></li>
                    <li><a href="{{ route('ride.show') }}" class="hover:text-white transition">Yolculuk Yap</a></li>
                    <li><a href="{{ route('driver.apply') }}" class="hover:text-white transition">Sürücü Ol</a></li>
                    <li><a href="{{ route('driver.login') }}" class="hover:text-white transition">Sürücü Girişi</a></li>
                    <li><a href="{{ route('customer.login') }}" class="hover:text-white transition">Müşteri Girişi</a></li>
                </ul>
            </div>

            {{-- Yasal --}}
            <div>
                <div class="text-white font-semibold mb-3 text-xs uppercase tracking-wider">Yasal</div>
                <ul class="space-y-2">
                    <li><a href="{{ route('legal.terms') }}" class="hover:text-white transition">Hizmet Şartları</a></li>
                    <li><a href="{{ route('legal.kvkk') }}" class="hover:text-white transition">KVKK Aydınlatma Metni</a></li>
                    <li><a href="{{ route('legal.distance-sales') }}" class="hover:text-white transition">Mesafeli Satış Sözleşmesi</a></li>
                    <li><a href="{{ route('legal.cookies') }}" class="hover:text-white transition">Çerez Politikası</a></li>
                    <li><a href="{{ route('legal.privacy-security') }}" class="hover:text-white transition">Gizlilik ve Güvenlik</a></li>
                </ul>
            </div>

            {{-- İzmir Rehberi --}}
            <div>
                <div class="text-white font-semibold mb-3 text-xs uppercase tracking-wider">İzmir Rehberi</div>
                <ul class="space-y-2">
                    <li><a href="{{ url('/izmir-havalimani-ulasim') }}" class="hover:text-white transition">Havalimanı Ulaşım</a></li>
                    <li><a href="{{ url('/izmir-uygun-ulasim') }}" class="hover:text-white transition">Uygun Ulaşım</a></li>
                    <li><a href="{{ url('/korsan-taksi-yasal-mi') }}" class="hover:text-white transition">Korsan Taksi Yasal mı?</a></li>
                    <li><a href="{{ route('legal.ride-sharing') }}" class="hover:text-white transition">Paylaşımlı Yolculuk Nedir?</a></li>
                    <li class="pt-2 mt-1 border-t border-white/5"><a href="tel:+908503403039" class="hover:text-white transition">📞 0850 340 3039</a></li>
                </ul>
            </div>

            {{-- Uygulamayı İndir --}}
            <div class="col-span-2 md:col-span-3 lg:col-span-1">
                <div class="text-white font-semibold mb-3 text-xs uppercase tracking-wider">Uygulamayı İndir</div>
                <div class="flex flex-row lg:flex-col gap-3">
                    {{-- App Store (yakında) --}}
                    <div class="relative inline-flex items-center gap-3 px-4 py-2.5 rounded-xl bg-black border border-white/25 shadow-lg shadow-black/40 ring-1 ring-white/5 cursor-default select-none" title="Çok yakında">
                        <svg class="w-7 h-7 text-white shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.377 0-2.332-1.26-3.428-2.8-1.287-1.82-2.323-4.63-2.323-7.28 0-4.28 2.797-6.55 5.552-6.55 1.448 0 2.675.95 3.6.95.865 0 2.222-1.01 3.902-1.01.613 0 2.886.06 4.374 2.19-.13.09-2.383 1.37-2.383 4.19 0 3.26 2.854 4.42 2.955 4.45z"/></svg>
                        <div class="leading-tight">
                            <div class="text-[10px] text-zinc-300">İndir</div>
                            <div class="text-white font-bold text-sm">App Store</div>
                        </div>
                        <span class="absolute -top-2 -right-2 text-[9px] font-bold bg-brand text-black px-1.5 py-0.5 rounded-full shadow">Yakında</span>
                    </div>
                    {{-- Google Play (yakında) --}}
                    <div class="relative inline-flex items-center gap-3 px-4 py-2.5 rounded-xl bg-black border border-white/25 shadow-lg shadow-black/40 ring-1 ring-white/5 cursor-default select-none" title="Çok yakında">
                        <svg class="w-7 h-7 shrink-0" viewBox="0 0 24 24"><path fill="#00D1C1" d="M3.6 2.3c-.3.2-.5.5-.5 1v17.4c0 .5.2.8.5 1l9.3-9.7z"/><path fill="#FFCE00" d="M17.2 12l-3.1-3.2-9.3-6.3c-.2-.1-.4-.2-.6-.2z"/><path fill="#FF4B3E" d="M4.2 22.3c.2 0 .4-.1.6-.2l9.3-6.3-2.9-3z"/><path fill="#00A0FF" d="M20.4 11.1c.6.3 1 .8 1 .9s-.4.6-1 .9l-3.2 1.7-3.1-3.3 3.1-3.3z"/></svg>
                        <div class="leading-tight">
                            <div class="text-[10px] text-zinc-300">İndir</div>
                            <div class="text-white font-bold text-sm">Google Play</div>
                        </div>
                        <span class="absolute -top-2 -right-2 text-[9px] font-bold bg-brand text-black px-1.5 py-0.5 rounded-full shadow">Yakında</span>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-zinc-500">iOS 13+ ve Android 8.0+ desteklenir.</p>
            </div>
        </div>

        <div class="border-t border-white/5 py-6 px-6 text-center text-[11px] text-zinc-500 leading-relaxed">
            &copy; {{ date('Y') }} FerXGo · Tüm hakları saklıdır<br>
            <span class="opacity-75">FerXGo bir dijital eşleştirme platformudur, ticari taşımacılık hizmeti sağlamaz. Yolculuk, bağımsız üye sürücü ile yolcu arasında gerçekleşir. Platform, taraflar arasında kişisel veri korumalı (KVKK 6698) bir aracılık hizmeti sunar.</span>
        </div>
    </footer>
    @endunless

    {{-- Mobil sticky action bar (Ara / WhatsApp) --}}
    @include('partials.mobile-action-bar')

    {{-- ─────────────────────────────────────────────────────────
         YASAL PLATFORM BİLDİRİMİ — oturum başı 1 kez gösterilir
         Click-wrap consent: kullanıcı "Anladım, devam et" tıklarsa
         hizmet şartlarını + paylaşımlı yolculuk modelini kabul eder.
         Legal sayfalarında gösterilmez (kullanıcı zaten metni okuyor).
         ───────────────────────────────────────────────────────── --}}
    @unless(request()->boolean('embed') || request()->routeIs('legal.*'))
    <div id="legal-platform-notice"
         class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/85 backdrop-blur-md px-4 py-6"
         role="dialog" aria-labelledby="legal-notice-title" aria-modal="true">
        <div class="w-full max-w-lg max-h-[92vh] overflow-y-auto rounded-3xl bg-zinc-900 border border-white/10 shadow-2xl">
            <div class="px-6 pt-6 pb-3 flex items-start justify-between gap-4">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-brand/15 border border-brand/30 text-brand text-[11px] uppercase tracking-[0.2em] font-semibold">
                    <span>★</span> Yasal Platform Bildirimi
                </div>
                <button type="button" id="legal-notice-close"
                    class="text-zinc-500 hover:text-white transition w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-lg"
                    aria-label="Kapat">×</button>
            </div>
            <div class="px-6 pb-2">
                <h2 id="legal-notice-title" class="text-2xl font-bold text-white leading-tight">
                    FerXGo hakkında<br>bilmeniz gerekenler
                </h2>
            </div>

            <div class="px-6 py-5 space-y-5 text-sm text-zinc-300">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand/15 text-brand flex items-center justify-center flex-shrink-0">🛡</div>
                    <div>
                        <div class="font-semibold text-white mb-1">Paylaşımlı Yolculuk Platformu</div>
                        <p class="text-zinc-400 leading-relaxed text-[13px]">
                            FerXGo, bağımsız üye sürücüler ile yolcuları dijital ortamda buluşturan bir paylaşımlı yolculuk koordinasyon platformudur.
                            6563 sayılı E-Ticaret Kanunu kapsamında <strong class="text-zinc-200">aracı hizmet sağlayıcı</strong> sıfatıyla faaliyet gösterir;
                            ticari taşımacılık hizmeti sağlamaz.
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand/15 text-brand flex items-center justify-center flex-shrink-0">💳</div>
                    <div>
                        <div class="font-semibold text-white mb-1">Şeffaf Katkı Payı</div>
                        <p class="text-zinc-400 leading-relaxed text-[13px]">
                            Yolculuk katkı payı, üye sürücü ile yolcu arasında belirlenir. Tahmini katkı payı yolculuk öncesi
                            şeffaf biçimde ekranda gösterilir. Yolculuk hizmeti üye sürücü ile yolcu arasında gerçekleşir.
                        </p>
                    </div>
                </div>

                <div class="border-t border-white/5"></div>

                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand/15 text-brand flex items-center justify-center flex-shrink-0">🌍</div>
                    <div>
                        <div class="font-semibold text-white mb-1">Hizmet Bölgesi</div>
                        <p class="text-zinc-400 leading-relaxed text-[13px]">
                            FerXGo şu anda <strong class="text-zinc-200">İzmir</strong>'de aktiftir. Havalimanı yolculuğu, şehir içi ve
                            kurumsal paylaşımlı yolculuk hizmetlerimiz mevcuttur. Hizmet kapsamımız yeni şehirlerle büyümeye devam etmektedir.
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand/15 text-brand flex items-center justify-center flex-shrink-0">🔒</div>
                    <div>
                        <div class="font-semibold text-white mb-1">Kişisel Verilerin Korunması</div>
                        <p class="text-zinc-400 leading-relaxed text-[13px]">
                            Paylaştığınız ad, telefon ve konum bilgileri yalnızca yolculuk organizasyonu amacıyla kullanılır; üçüncü taraflarla paylaşılmaz.
                            Veriler 6698 sayılı <strong class="text-zinc-200">KVKK</strong> kapsamında işlenmekte ve korunmaktadır.
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-6 pb-6 pt-4 border-t border-white/5 space-y-3">
                <button type="button" id="legal-notice-accept"
                    class="w-full px-5 py-3.5 rounded-xl bg-brand hover:bg-brand-600 text-black font-bold transition flex items-center justify-center gap-2">
                    <span>✓</span> Anladım, Devam Et
                </button>
                <p class="text-[11px] text-zinc-500 text-center leading-relaxed px-2">
                    Bu bildirim her oturumda bir kez gösterilir. Platformu kullanmaya devam ederek
                    <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="text-brand hover:underline">Hizmet Şartları</a>'nı,
                    <a href="{{ route('legal.kvkk') }}" target="_blank" rel="noopener" class="text-brand hover:underline">KVKK Aydınlatma Metni</a>'ni ve
                    <a href="{{ route('legal.ride-sharing') }}" target="_blank" rel="noopener" class="text-brand hover:underline">Paylaşımlı Yolculuk modelini</a>
                    kabul etmiş sayılırsınız.
                </p>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const KEY = 'ferogo-legal-notice-acked-v1';
            const CONSENT_URL = @json(route('legal.consent.store'));
            const modal = document.getElementById('legal-platform-notice');
            if (!modal) return;

            function show() {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
            }
            function hide(accepted) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
                if (accepted) {
                    try {
                        sessionStorage.setItem(KEY, JSON.stringify({ ack: true, at: new Date().toISOString() }));
                    } catch (_) {}
                }
            }

            // Cihaz parmak izi (basit canvas-based — daha ileri için fingerprint.js düşünülebilir)
            function deviceFingerprint() {
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    ctx.textBaseline = 'top';
                    ctx.font = '14px Arial';
                    ctx.fillText('ferogo-fp', 2, 2);
                    const data = canvas.toDataURL() + navigator.userAgent + screen.width + 'x' + screen.height;
                    let h = 0;
                    for (let i = 0; i < data.length; i++) {
                        h = ((h << 5) - h) + data.charCodeAt(i);
                        h |= 0;
                    }
                    return 'fp-' + Math.abs(h).toString(16);
                } catch (_) {
                    return null;
                }
            }

            async function logConsent() {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                if (!csrf) return; // CSRF yoksa kayıt zaten reddedilir
                try {
                    await fetch(CONSENT_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            consent_type: 'platform_notice',
                            accepted_via: 'modal',
                            fingerprint: deviceFingerprint(),
                        }),
                        credentials: 'same-origin',
                    });
                } catch (err) {
                    // Sessiz başarısızlık — kullanıcı engellenmesin
                    console.warn('[legal-consent] log failed:', err);
                }
            }

            try {
                if (!sessionStorage.getItem(KEY)) {
                    setTimeout(show, 350);
                }
            } catch (_) {
                setTimeout(show, 350);
            }

            document.getElementById('legal-notice-accept')?.addEventListener('click', () => {
                hide(true);
                logConsent(); // fire-and-forget — server-side audit log
            });
            // Çarpı tuşu: oturumda tekrar gösterme ama KABUL log'u atma (kullanıcı kabul etmedi)
            document.getElementById('legal-notice-close')?.addEventListener('click', () => hide(true));
        })();
    </script>
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

    {{-- Açılır pencere (popup) reklamı — aktif popup varsa gösterilir --}}
    @include('partials.ad-popup')

    {{-- Uygulama indirme QR kartı — sağ alttan yukarı kayar (masaüstü) --}}
    @include('partials.app-download-qr')

    @stack('scripts')
</body>
</html>
