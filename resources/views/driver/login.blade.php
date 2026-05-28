<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sürücü Girişi · Ferogo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { brand: { DEFAULT: '#F0C040', 600: '#D9A621' } } } }
        }
    </script>
    <style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-2xl font-extrabold tracking-tight">
                <span class="text-brand">●</span> Ferogo
            </a>
            <div class="mt-2 text-xs uppercase tracking-[0.3em] text-zinc-500">Sürücü Paneli</div>
        </div>

        <div class="bg-zinc-950 border border-white/10 rounded-3xl p-8 shadow-2xl">
            <h1 class="text-2xl font-bold mb-1">Hoş geldin</h1>
            <p class="text-sm text-zinc-400 mb-6">E-posta ve şifrenle giriş yap.</p>

            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('driver.login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">E-posta</label>
                    <input type="email" name="email" required autofocus value="{{ old('email') }}"
                           class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-3 text-sm text-white placeholder-zinc-600 focus:outline-none transition"
                           placeholder="surucu@ferogo.com.tr">
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-2">Şifre</label>
                    <input type="password" name="password" required
                           class="w-full bg-white/[0.03] border border-white/10 focus:border-brand/40 rounded-xl px-3 py-3 text-sm text-white placeholder-zinc-600 focus:outline-none transition"
                           placeholder="••••••••">
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold transition shadow-xl shadow-brand/30">
                    Giriş Yap
                </button>
            </form>

            <div class="mt-6 text-center text-xs text-zinc-500">
                Sürücü değil misin?
                <a href="{{ route('driver.apply') }}" class="text-brand hover:text-brand-600 underline underline-offset-2">Başvuru yap</a>
            </div>
        </div>

        <div class="mt-6 text-center text-[11px] text-zinc-600">
            © {{ date('Y') }} Ferogo · Sürücü Paneli
        </div>
    </div>
</body>
</html>
