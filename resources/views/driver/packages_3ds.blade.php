<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Doğrulama · Ferogo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, sans-serif; }
        /* iyzico 3D HTML kendi formunu auto-submit eder; loading overlay
           gerçek 3D ekranı yüklenene kadar görünür kalır. */
    </style>
</head>
<body class="bg-black text-white min-h-screen flex flex-col">

    <header class="bg-zinc-950 border-b border-white/10 px-4 py-3 flex items-center justify-between">
        <div class="text-sm font-bold">
            <span class="text-white">FERO</span><span class="text-brand" style="color:#F0C040">GO</span>
            <span class="ml-2 text-xs text-zinc-500">3D Güvenli Ödeme</span>
        </div>
        <div class="text-xs text-zinc-500">
            {{ $package->label() }} · {{ number_format($package->price, 2, ',', '.') }} ₺
        </div>
    </header>

    <div class="flex-1 relative">
        {{--
            iyzico'nun verdiği threeDSHtmlContent içinde <form action="banka_3d_url"> var ve
            window.onload'da kendisi submit eder. Bunu raw HTML olarak basıyoruz; tarayıcı
            otomatik bankanın 3D Secure sayfasına geçer. SMS doğrulama sonra iyzico'ya, iyzico
            da bizim callback URL'imize POST eder.
        --}}
        {!! $htmlContent !!}
    </div>

</body>
</html>
