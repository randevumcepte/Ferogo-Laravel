<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Ödeme · FerXGo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: system-ui, sans-serif; }</style>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-zinc-950 border border-amber-500/30 rounded-3xl p-6 space-y-5">
        <div class="flex items-center gap-2 text-amber-400 text-xs font-bold uppercase tracking-[0.25em]">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
            Test Modu (Mock Provider)
        </div>

        <div>
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Paket</div>
            <div class="text-2xl font-extrabold">{{ $package->label() }}</div>
        </div>

        <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-4 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-zinc-500">Tutar</span>
                <span class="font-bold tabular-nums">{{ number_format($package->price, 2, ',', '.') }} ₺</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-zinc-500">Süre</span>
                <span>{{ $package->duration_hours >= 24 ? floor($package->duration_hours / 24) . ' gün' : $package->duration_hours . ' saat' }}</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-zinc-500">Merchant OID</span>
                <span class="text-zinc-400 font-mono">{{ $merchantOid }}</span>
            </div>
        </div>

        <p class="text-xs text-zinc-500 leading-relaxed">
            PayTR entegrasyonu pasif (PAYTR_ENABLED=false). Gerçek tahsilat yapılmıyor.
            "Ödemeyi Onayla" butonu, üretimde PayTR bildirim'inden gelen success ile aynı sonucu üretir
            — paket aktive olur, sürücü radara düşer.
        </p>

        @php
            $mockSalt = config('services.paytr.merchant_salt') ?: 'MOCK_SALT';
            $mockKey  = config('services.paytr.merchant_key') ?: 'MOCK_KEY';
            $status = 'success';
            $totalAmount = (int) round((float) $package->price * 100);
            $mockHash = base64_encode(hash_hmac('sha256', $merchantOid . $mockSalt . $status . $totalAmount, $mockKey, true));
        @endphp

        <form method="POST" action="{{ $callback }}" class="space-y-2">
            @csrf
            <input type="hidden" name="merchant_oid" value="{{ $merchantOid }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="total_amount" value="{{ $totalAmount }}">
            <input type="hidden" name="hash" value="{{ $mockHash }}">
            <input type="hidden" name="payment_type" value="card">
            <input type="hidden" name="masked_pan" value="406985******1234">
            <button type="submit"
                    class="w-full px-4 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-black font-extrabold text-sm transition">
                ✓ Ödemeyi Onayla (Test)
            </button>
        </form>
        <a href="{{ route('driver.packages.index') }}" class="block text-center text-xs text-zinc-500 hover:text-white transition">İptal</a>
    </div>
</body>
</html>
