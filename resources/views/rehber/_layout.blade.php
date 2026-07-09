{{--
    SEO landing / rehber sayfaları için ortak şablon.
    Her sayfa: @section('title'), @section('description') (SEO),
    @section('kicker'), @section('h1'), @section('lead') (hero),
    @section('body') (asıl içerik — prose) tanımlar.
    Amaç: İzmir ulaşım niyetli aramaları yasal-güvenli çerçevede yakalamak.
--}}
@extends('layouts.public')

@section('content')
<main class="pt-28 pb-20">

    {{-- Hero --}}
    <section class="max-w-4xl mx-auto px-6 text-center">
        <div class="mb-6">
            <a href="{{ route('home') }}" class="text-xs uppercase tracking-[0.25em] text-brand hover:text-white transition">← Ana Sayfa</a>
        </div>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-brand/15 border border-brand/30 text-brand text-[11px] uppercase tracking-[0.2em] font-semibold mb-6">
            <span>★</span> @yield('kicker', 'İzmir Paylaşımlı Yolculuk')
        </div>
        <h1 class="text-3xl md:text-5xl font-extrabold text-white leading-tight mb-5">@yield('h1')</h1>
        <p class="text-base md:text-lg text-zinc-400 leading-relaxed max-w-2xl mx-auto mb-8">@yield('lead')</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('ride.show') }}" class="px-7 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold transition">Hemen Yolculuk Planla</a>
            <a href="tel:+908503403039" class="px-7 py-3.5 rounded-2xl border border-white/15 hover:bg-white/5 text-white font-semibold transition">📞 0850 340 3039</a>
        </div>
    </section>

    {{-- İçerik --}}
    <article class="max-w-3xl mx-auto px-6 mt-16 prose prose-invert max-w-none
        prose-headings:text-white prose-headings:font-bold
        prose-h2:text-2xl prose-h2:mt-12 prose-h2:mb-4 prose-h2:border-b prose-h2:border-white/10 prose-h2:pb-2
        prose-h3:text-lg prose-h3:mt-6 prose-h3:mb-2 prose-h3:text-brand
        prose-p:text-zinc-300 prose-p:leading-relaxed
        prose-strong:text-white
        prose-ul:text-zinc-300 prose-li:marker:text-brand
        prose-a:text-brand prose-a:no-underline hover:prose-a:underline">
        @yield('body')
    </article>

    {{-- Alt CTA --}}
    <section class="max-w-3xl mx-auto px-6 mt-16">
        <div class="rounded-3xl bg-gradient-to-br from-brand/15 to-transparent border border-brand/20 p-8 md:p-10 text-center">
            <h2 class="text-2xl md:text-3xl font-extrabold text-white mb-3">İzmir'de uygun ve güvenli yolculuk</h2>
            <p class="text-zinc-400 leading-relaxed max-w-xl mx-auto mb-6">
                FerXGo ile bağımsız üye sürücüler arasında hızlı eşleştirme, şeffaf katkı payı ve 7/24 platform erişimi.
                Havalimanı, şehir içi ve kurumsal paylaşımlı yolculuklar için hemen planla.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('ride.show') }}" class="px-7 py-3.5 rounded-2xl bg-brand hover:bg-brand-600 text-black font-bold transition">Yolculuk Planla</a>
                <a href="{{ route('driver.apply') }}" class="px-7 py-3.5 rounded-2xl border border-white/15 hover:bg-white/5 text-white font-semibold transition">Üye Sürücü Ol</a>
            </div>
        </div>

        {{-- İç bağlantılar (SEO — konu kümesi) --}}
        <nav class="mt-10 text-sm text-zinc-500 leading-relaxed">
            <span class="text-zinc-400 font-semibold">İlgili sayfalar:</span>
            <a href="{{ url('/izmir-havalimani-ulasim') }}" class="text-brand hover:underline">İzmir Havalimanı Ulaşım</a> ·
            <a href="{{ url('/izmir-uygun-ulasim') }}" class="text-brand hover:underline">İzmir Uygun Ulaşım</a> ·
            <a href="{{ url('/korsan-taksi-yasal-mi') }}" class="text-brand hover:underline">Korsan Taksi Yasal mı?</a> ·
            <a href="{{ route('legal.ride-sharing') }}" class="text-brand hover:underline">Paylaşımlı Yolculuk Nedir?</a>
        </nav>
    </section>
</main>
@endsection
