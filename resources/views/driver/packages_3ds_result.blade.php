<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $success ? 'Ödeme Başarılı' : 'Ödeme Başarısız' }} · Ferogo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: system-ui, sans-serif; }</style>
    <script>
        // 3D bittiğinde sürücüyü paketler sayfasına yönlendir
        setTimeout(() => {
            window.location.href = '{{ route('driver.packages.index') }}';
        }, {{ $success ? 1500 : 3000 }});
    </script>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-zinc-950 border {{ $success ? 'border-emerald-500/30' : 'border-red-500/30' }} rounded-3xl p-6 text-center space-y-4">
        <div class="text-5xl">{{ $success ? '✓' : '✕' }}</div>
        <div class="text-xl font-bold {{ $success ? 'text-emerald-300' : 'text-red-300' }}">
            {{ $success ? 'Ödeme Başarılı' : 'Ödeme Başarısız' }}
        </div>
        <p class="text-sm text-zinc-400">{{ $message }}</p>
        <p class="text-xs text-zinc-600">Paketler sayfasına yönlendiriliyorsun…</p>
    </div>
</body>
</html>
