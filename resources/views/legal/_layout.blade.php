@extends('layouts.public')

@section('content')
<main class="max-w-3xl mx-auto px-6 pt-28 pb-20 text-zinc-200">
    <div class="mb-8">
        <a href="{{ route('home') }}" class="text-xs uppercase tracking-[0.25em] text-brand hover:text-white transition">← Ana Sayfa</a>
    </div>

    <h1 class="text-3xl md:text-4xl font-extrabold text-white leading-tight mb-3">@yield('legal-title')</h1>
    <p class="text-sm text-zinc-500 mb-10">Son güncelleme: {{ now()->format('d.m.Y') }}</p>

    <div class="prose prose-invert max-w-none
        prose-headings:text-white prose-headings:font-bold
        prose-h2:text-xl prose-h2:mt-10 prose-h2:mb-3 prose-h2:border-b prose-h2:border-white/10 prose-h2:pb-2
        prose-h3:text-lg prose-h3:mt-6 prose-h3:mb-2 prose-h3:text-brand
        prose-p:text-zinc-300 prose-p:leading-relaxed
        prose-strong:text-white
        prose-ul:text-zinc-300
        prose-a:text-brand prose-a:no-underline hover:prose-a:underline">
        @yield('legal-body')
    </div>

    <div class="mt-16 pt-8 border-t border-white/5 text-xs text-zinc-500 leading-relaxed">
        Bu metin Ferxgo'nun bağımsız üye sürücüler ile yolcuları dijital ortamda buluşturan bir paylaşımlı yolculuk platformu olarak faaliyet gösterdiği esasına dayanır.
        Ferxgo, 6563 sayılı Elektronik Ticaretin Düzenlenmesi Hakkında Kanun kapsamında <strong>aracı hizmet sağlayıcı</strong> sıfatına sahiptir; ticari taşımacılık hizmeti sağlamaz.
        Yolculuk hizmeti, bağımsız vergi mükellefi üye sürücü ile yolcu arasında gerçekleşir.
    </div>
</main>
@endsection
