<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} · FerXGo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: system-ui, sans-serif; }</style>
    <script>
        setTimeout(() => {
            window.location.href = '{{ route('driver.packages.index') }}';
        }, {{ ($redirectIn ?? 3) * 1000 }});
    </script>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-zinc-950 border {{ $success ? 'border-emerald-500/30' : 'border-red-500/30' }} rounded-3xl p-6 text-center space-y-4">
        <div class="text-5xl">{{ $success ? '✓' : '✕' }}</div>
        <div class="text-xl font-bold {{ $success ? 'text-emerald-300' : 'text-red-300' }}">
            {{ $title }}
        </div>
        <p class="text-sm text-zinc-400">{{ $message }}</p>
        <p class="text-xs text-zinc-600">Paketler sayfasına yönlendiriliyorsun…</p>
    </div>
</body>
</html>
