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
<body class="bg-black text-white antialiased">

    {{-- Navigation --}}
    <nav class="fixed top-0 inset-x-0 z-50 backdrop-blur-md bg-black/50 border-b border-white/5">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="text-2xl font-extrabold tracking-tight">
                    <span class="text-white">FERO</span><span class="text-brand">GO</span>
                </span>
            </a>
            <div class="hidden md:flex items-center gap-8 text-sm text-zinc-300">
                <a href="#hizmetler" class="hover:text-white transition">Hizmetler</a>
                <a href="#rezervasyon" class="hover:text-white transition">Rezervasyon</a>
                <a href="#sss" class="hover:text-white transition">SSS</a>
                <a href="tel:+908508401377" class="text-white font-medium">0850 840 13 77</a>
            </div>
        </div>
    </nav>

    @yield('content')

    {{-- Footer --}}
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

    @stack('scripts')
</body>
</html>
