<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Paketler · FerXGo Sürücü</title>
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
<body class="bg-black text-white min-h-screen pb-20">

    {{-- ===== Top bar ===== --}}
    <header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <a href="{{ route('driver.panel') }}" class="flex items-center gap-2 min-w-0">
                <span class="text-2xl font-extrabold tracking-tight">
                    <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
                </span>
            </a>
            <a href="{{ route('driver.panel') }}" class="text-xs text-zinc-400 hover:text-white">← Panele dön</a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-6">

        {{-- ===== Flash mesajlar ===== --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/[0.08] p-4 text-sm text-emerald-200">
                ✓ {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-red-500/30 bg-red-500/[0.08] p-4 text-sm text-red-200">
                ✕ {{ session('error') }}
            </div>
        @endif

        {{-- ===== Aktif paket durumu ===== --}}
        @if ($activePackage)
            @php
                $remainingSeconds = max(0, $activePackage->expires_at->getTimestamp() - now()->getTimestamp());
                $remainingMinsTotal = (int) floor($remainingSeconds / 60);
                $remainingHoursTotal = (int) floor($remainingMinsTotal / 60);
                $remainingLabel = $remainingHoursTotal >= 24
                    ? floor($remainingHoursTotal / 24) . ' gün ' . ($remainingHoursTotal % 24) . ' saat'
                    : ($remainingHoursTotal >= 1 ? $remainingHoursTotal . ' saat ' . ($remainingMinsTotal % 60) . ' dk' : $remainingMinsTotal . ' dakika');
            @endphp
            <section class="rounded-3xl border-2 border-brand bg-brand/10 p-5">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.25em] text-brand font-bold mb-1">Aktif Paket</div>
                        <div class="text-2xl font-extrabold">{{ $activePackage->label() }}</div>
                        <div class="text-xs text-zinc-400 mt-1">
                            Başlangıç: {{ $activePackage->starts_at?->format('d.m.Y H:i') ?? '—' }}
                            · Bitiş: <span class="text-brand font-semibold">{{ $activePackage->expires_at?->format('d.m.Y H:i') }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider text-zinc-500">Kalan</div>
                        <div class="text-xl font-bold text-brand">{{ $remainingLabel }}</div>
                    </div>
                </div>
            </section>
        @elseif (! config('services.driver.enforce_packages', true))
            {{-- Test / QA modu: paket zorunluluğu geçici olarak kapalı --}}
            <section class="rounded-3xl border border-emerald-500/30 bg-emerald-500/[0.06] p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">🧪</div>
                    <div>
                        <div class="font-bold text-emerald-200">Test modu aktif</div>
                        <div class="text-xs text-emerald-300/80 mt-0.5">Şu an paket zorunluluğu geçici olarak kapalı. Paket olmadan da müsait olabilir, iş kabul edebilirsin.</div>
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-3xl border border-red-500/30 bg-red-500/[0.06] p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center shrink-0">⚠</div>
                    <div>
                        <div class="font-bold text-red-200">Aktif paketin yok</div>
                        <div class="text-xs text-red-300/80 mt-0.5">Paket almadan radar'a düşmezsin, iş atanmaz. Aşağıdan paket seç.</div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ===== Paket kataloğu ===== --}}
        <section>
            <h2 class="text-xs uppercase tracking-[0.25em] text-zinc-500 font-bold mb-3">Paket Seç</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($catalog as $pkg)
                    <form method="POST" action="{{ route('driver.packages.purchase') }}" class="contents">
                        @csrf
                        <input type="hidden" name="type" value="{{ $pkg['key'] }}">
                        <button type="submit"
                                class="text-left rounded-3xl border-2 transition p-5 flex flex-col gap-3 hover:scale-[1.02] active:scale-[0.99]
                                       {{ ($pkg['badge'] ?? null) === 'POPÜLER' ? 'border-brand bg-brand/10' : 'border-white/10 bg-zinc-950 hover:border-white/30' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="text-xl font-extrabold">{{ $pkg['label'] }}</div>
                                    <div class="text-[11px] text-zinc-500 mt-0.5">{{ $pkg['subtitle'] }}</div>
                                </div>
                                @if (! empty($pkg['badge']))
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-1 rounded-full
                                                 {{ $pkg['badge'] === 'POPÜLER' ? 'bg-brand text-black' : 'bg-emerald-500/20 text-emerald-300' }}">
                                        {{ $pkg['badge'] }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-3xl font-black tabular-nums">{{ number_format($pkg['price'], 0, ',', '.') }}</span>
                                <span class="text-xs text-zinc-500 font-bold">₺</span>
                            </div>
                            <div class="text-[11px] text-zinc-500">
                                {{ $pkg['duration_hours'] >= 24 ? floor($pkg['duration_hours'] / 24) . ' gün' : $pkg['duration_hours'] . ' saat' }}
                                · saat başı ~{{ number_format($pkg['price'] / $pkg['duration_hours'], 0, ',', '.') }} ₺
                            </div>
                            <div class="mt-1 inline-flex items-center gap-1.5 text-xs font-bold {{ ($pkg['badge'] ?? null) === 'POPÜLER' ? 'text-black' : 'text-brand' }}">
                                Satın Al →
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>
            <p class="text-[11px] text-zinc-600 mt-3 leading-relaxed">
                Komisyon yok — paket aldığın sürece yaptığın her işin <span class="text-brand font-semibold">%100'ü senin</span>.
                Ödeme PayTR güvenli iframe'inde alınır: kart, saklı kart ve Masterpass otomatik desteklenir.
                Mevcut paketin bitmeden yeni paket alırsan süre üst üste eklenir.
            </p>
        </section>

        {{-- ===== Geçmiş ===== --}}
        @if ($history->isNotEmpty())
            <section>
                <h2 class="text-xs uppercase tracking-[0.25em] text-zinc-500 font-bold mb-3">Paket Geçmişi</h2>
                <div class="rounded-2xl border border-white/10 bg-zinc-950 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-white/[0.03] text-[10px] uppercase tracking-wider text-zinc-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Paket</th>
                                <th class="px-3 py-2 text-right">Ücret</th>
                                <th class="px-3 py-2 text-left">Kart</th>
                                <th class="px-3 py-2 text-left">Bitiş</th>
                                <th class="px-3 py-2 text-left">Durum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($history as $p)
                                <tr>
                                    <td class="px-3 py-2.5 font-semibold">{{ $p->label() }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($p->price, 0, ',', '.') }} ₺</td>
                                    <td class="px-3 py-2.5 text-zinc-400 text-[11px]">
                                        @if ($p->card_last_four)
                                            •••• {{ $p->card_last_four }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-zinc-400">{{ $p->expires_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        @php
                                            $colors = [
                                                'active'  => 'bg-emerald-500/20 text-emerald-300',
                                                'expired' => 'bg-zinc-500/20 text-zinc-400',
                                                'pending' => 'bg-amber-500/20 text-amber-300',
                                                'failed'  => 'bg-red-500/20 text-red-300',
                                                'refunded'=> 'bg-blue-500/20 text-blue-300',
                                            ];
                                            $labels = [
                                                'active'   => 'Aktif',
                                                'expired'  => 'Süresi Doldu',
                                                'pending'  => 'Beklemede',
                                                'failed'   => 'Başarısız',
                                                'refunded' => 'İade',
                                            ];
                                        @endphp
                                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full {{ $colors[$p->status] ?? 'bg-zinc-500/20 text-zinc-400' }}">
                                            {{ $labels[$p->status] ?? $p->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

    </main>
</body>
</html>
