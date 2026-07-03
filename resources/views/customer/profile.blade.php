<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profilim · Ferxgo</title>
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
        .glow { box-shadow: 0 0 60px -10px rgba(240,192,64,0.4); }
    </style>
</head>
<body class="bg-black text-white min-h-screen pb-20 md:pb-0">

@php
    $avatarUrl = $user->avatar
        ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . ltrim($user->avatar, '/')))
        : null;
    $tStyles = [
        'guvenilir'  => ['Güvenilir', 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'],
        'normal'     => ['Standart',   'bg-zinc-500/15 text-zinc-300 border-zinc-500/30'],
        'riskli'     => ['Riskli',     'bg-amber-500/15 text-amber-300 border-amber-500/30'],
        'cok_riskli' => ['Çok Riskli', 'bg-red-500/15 text-red-300 border-red-500/30'],
    ];
    $tStyle = $tStyles[$trust->trustLabel()] ?? $tStyles['normal'];
@endphp

<header class="sticky top-0 z-30 bg-black/85 backdrop-blur-md border-b border-white/10">
    <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between gap-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2 min-w-0">
            <span class="text-2xl font-extrabold tracking-tight">
                <span class="text-white">Fer</span><span class="text-brand italic">X</span><span class="text-white">Go</span>
            </span>
        </a>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('customer.panel') }}" class="px-3 py-2 rounded-xl text-xs font-semibold text-zinc-400 hover:text-white hover:bg-white/5 transition">← Panel</a>
            <form method="POST" action="{{ route('customer.logout') }}" class="inline">
                @csrf
                <button type="submit" class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition">Çıkış</button>
            </form>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-8 space-y-6">

    @if (session('success'))
        <div class="rounded-2xl bg-emerald-500/10 border border-emerald-500/30 px-5 py-3 text-sm text-emerald-300">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- ===== Hero ===== --}}
    <section class="relative rounded-3xl border border-white/10 bg-gradient-to-br from-brand/15 via-brand/5 to-transparent overflow-hidden">
        <div class="absolute -top-20 -right-20 w-64 h-64 rounded-full bg-brand/20 blur-3xl"></div>
        <div class="relative px-6 sm:px-8 py-8 flex flex-col sm:flex-row items-center sm:items-end gap-6">
            {{-- Avatar with upload --}}
            <form action="{{ route('customer.profile.update') }}" method="POST" enctype="multipart/form-data" id="avatar-form" class="shrink-0 group relative">
                @csrf
                <input type="hidden" name="name" value="{{ $user->name }}">
                <input type="file" name="avatar" id="avatar-input" accept="image/*" class="hidden" onchange="document.getElementById('avatar-form').submit();">
                <label for="avatar-input" class="cursor-pointer block">
                    <div class="w-32 h-32 rounded-3xl border-4 border-brand glow flex items-center justify-center text-black font-extrabold text-5xl bg-gradient-to-br from-brand to-brand-600 overflow-hidden">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                        @endif
                    </div>
                    <div class="absolute inset-0 rounded-3xl bg-black/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                        <span class="text-white text-xs font-semibold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Değiştir
                        </span>
                    </div>
                </label>
            </form>

            <div class="flex-1 min-w-0 text-center sm:text-left">
                <div class="text-[10px] uppercase tracking-[0.3em] text-brand mb-1">Profilim</div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-white truncate">{{ $user->name }}</h1>
                <div class="text-sm text-zinc-400 mt-1 flex items-center justify-center sm:justify-start gap-2 flex-wrap">
                    <span>+90 {{ $user->phone }}</span>
                    @if ($user->phone_verified_at)
                        <span class="inline-flex items-center gap-1 text-emerald-400 text-xs">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Doğrulandı
                        </span>
                    @endif
                </div>
                <div class="mt-3 inline-flex items-center gap-2 flex-wrap">
                    <span class="px-3 py-1 rounded-full border text-[11px] font-bold uppercase tracking-wider {{ $tStyle[1] }}">
                        {{ $tStyle[0] }} Müşteri
                    </span>
                    <span class="text-xs text-zinc-400">Üyelik: {{ $memberSince?->translatedFormat('d M Y') ?? '—' }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Stats ===== --}}
    <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-white/10 bg-zinc-950 p-5">
            <div class="text-[10px] uppercase tracking-wider text-zinc-500">Güven Skoru</div>
            <div class="text-2xl font-extrabold mt-1">{{ $trust->trust_score }}<span class="text-xs text-zinc-500">/100</span></div>
        </div>
        <div class="rounded-2xl border border-white/10 bg-zinc-950 p-5">
            <div class="text-[10px] uppercase tracking-wider text-zinc-500">Toplam Yolculuk</div>
            <div class="text-2xl font-extrabold mt-1">{{ $totalRides }}</div>
        </div>
        <div class="rounded-2xl border border-white/10 bg-zinc-950 p-5">
            <div class="text-[10px] uppercase tracking-wider text-zinc-500">Tamamlanan</div>
            <div class="text-2xl font-extrabold mt-1 text-emerald-300">{{ $completedRides }}</div>
        </div>
        <div class="rounded-2xl border border-white/10 bg-zinc-950 p-5">
            <div class="text-[10px] uppercase tracking-wider text-zinc-500">Üyelik</div>
            <div class="text-2xl font-extrabold mt-1">{{ $memberDays }}<span class="text-xs text-zinc-500"> gün</span></div>
        </div>
    </section>

    {{-- ===== Edit form ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 p-6 sm:p-8">
        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.25em] text-zinc-400 font-bold mb-5">
            <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Bilgileri Düzenle
        </div>

        <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Ad Soyad</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="120" required
                       class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-4 py-3 text-base text-white placeholder-zinc-600 focus:outline-none transition">
                @error('name')<div class="text-xs text-red-300 mt-1">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Telefon</label>
                <div class="w-full bg-white/[0.02] border border-white/5 rounded-xl px-4 py-3 text-base text-zinc-400 flex items-center justify-between">
                    <span>+90 {{ $user->phone }}</span>
                    <span class="text-[10px] text-zinc-500 uppercase tracking-wider">Değiştirilemez</span>
                </div>
                <div class="text-[11px] text-zinc-500 mt-1.5">Telefonunu değiştirmek için destek ile iletişime geç.</div>
            </div>

            @if ($avatarUrl)
                <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-zinc-400 hover:text-white transition">
                    <input type="checkbox" name="remove_avatar" value="1" class="w-4 h-4 rounded bg-white/5 border-white/20 text-brand focus:ring-brand/40">
                    Profil resmini kaldır
                </label>
            @endif

            <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold text-sm transition shadow-xl shadow-brand/30">
                Kaydet
            </button>
        </form>
    </section>

    {{-- ===== KVKK / Veri yönetimi ===== --}}
    <section class="rounded-3xl border border-white/10 bg-zinc-950 overflow-hidden">
        <div class="px-6 sm:px-8 py-5 border-b border-white/5">
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.25em] text-zinc-400 font-bold mb-1">
                <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                KVKK · Veri Yönetimi
            </div>
            <p class="text-xs text-zinc-500">Türk KVKK mevzuatı kapsamında verilerini indirebilir veya hesabını silebilirsin.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-white/5">
            {{-- Download data --}}
            <div class="p-6 sm:p-8">
                <div class="text-3xl mb-3">📥</div>
                <h3 class="text-base font-bold text-white mb-1">Verilerimi İndir</h3>
                <p class="text-xs text-zinc-400 leading-relaxed mb-4">
                    Profilin, yolculuk geçmişin ve güven skorun JSON formatında tek dosyada.
                </p>
                <a href="{{ route('customer.profile.data') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm font-semibold text-zinc-300 hover:text-white transition">
                    İndir (JSON)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </a>
            </div>

            {{-- Delete account --}}
            <div class="p-6 sm:p-8 bg-red-500/[0.03]">
                <div class="text-3xl mb-3">🗑</div>
                <h3 class="text-base font-bold text-white mb-1">Hesabımı Sil</h3>
                <p class="text-xs text-zinc-400 leading-relaxed mb-4">
                    Kişisel verilerin temizlenir. Yolculuk geçmişi yasal zorunluluk gereği anonim olarak saklanır.
                </p>
                <button type="button" id="delete-account-btn"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/15 hover:bg-red-500/25 border border-red-500/30 text-sm font-semibold text-red-300 transition">
                    Hesabı Sil
                </button>
            </div>
        </div>
    </section>

</main>

{{-- Delete confirm modal --}}
<div id="delete-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/80 backdrop-blur-sm px-4">
    <div class="bg-zinc-950 border border-red-500/30 rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl shadow-red-500/20">
        <div class="text-4xl mb-3">⚠</div>
        <h2 class="text-xl font-bold mb-2">Hesabı silmek istediğinden emin misin?</h2>
        <p class="text-sm text-zinc-400 mb-4 leading-relaxed">
            Profil bilgilerin (ad, telefon, e-posta, avatar) silinir.<br>
            Yolculuk geçmişin yasal/mali kayıt zorunluluğu gereği <strong class="text-zinc-300">anonim</strong> olarak saklanır.
        </p>
        <form method="POST" action="{{ route('customer.profile.delete') }}">
            @csrf
            <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Onaylamak için <span class="text-red-400 font-bold">SİL</span> yaz</label>
            <input type="text" name="confirm" required autocomplete="off"
                   class="w-full bg-white/[0.03] border border-red-500/30 focus:border-red-500/60 rounded-xl px-4 py-3 text-base text-white placeholder-zinc-600 focus:outline-none transition mb-4"
                   placeholder="SİL">
            @error('confirm')<div class="text-xs text-red-300 mb-3">{{ $message }}</div>@enderror
            <div class="flex gap-2">
                <button type="button" id="delete-cancel"
                        class="flex-1 px-4 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm font-semibold text-zinc-300 transition">
                    Vazgeç
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-bold transition">
                    Hesabı Sil
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('delete-modal');
        const open  = document.getElementById('delete-account-btn');
        const cancel = document.getElementById('delete-cancel');
        if (!modal || !open) return;
        open.addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
        cancel.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        });
    })();
</script>

@include('partials.mobile-action-bar')
</body>
</html>
