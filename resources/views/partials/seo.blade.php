{{--
    Site geneli SEO meta bloğu — canonical, Open Graph, Twitter Card,
    favicon, tema rengi, Search Console doğrulama ve Schema.org (Organization).
    Sayfa başlığı/açıklaması için child view'ların @section('title')/@section('description')
    değerlerini kullanır; yoksa mantıklı varsayılanlara düşer.
--}}
@php
    $seoTitle       = trim($__env->yieldContent('title', 'FerXGo · İzmir Paylaşımlı Yolculuk Platformu'));
    $seoDescription = trim($__env->yieldContent('description', 'FerXGo, İzmir\'de bağımsız üye sürücüler ile yolcuları buluşturan dijital paylaşımlı yolculuk platformudur. Havalimanı, şehir içi ve kurumsal yolculuklarda şeffaf katkı payı, 7/24 platform erişimi.'));
    $seoCanonical   = trim($__env->yieldContent('canonical', url()->current()));
    $seoImage       = asset('images/og-image.png');
    $seoSiteUrl     = config('services.seo.site_url');

    $seoSchema = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Organization',
        'name'          => 'FerXGo',
        'alternateName' => 'FerXGo Paylaşımlı Yolculuk Platformu',
        'url'           => $seoSiteUrl,
        'logo'          => asset('images/ferxgo-logo.png'),
        'image'         => $seoImage,
        'description'   => 'FerXGo, İzmir\'de bağımsız üye sürücüler ile yolcuları buluşturan dijital paylaşımlı yolculuk ve yazılım platformudur. Ticari taşımacılık hizmeti sunmaz; aracı hizmet sağlayıcı olarak faaliyet gösterir.',
        'telephone'     => config('services.seo.phone'),
        'areaServed'    => ['@type' => 'City', 'name' => 'İzmir'],
        'address'       => ['@type' => 'PostalAddress', 'addressLocality' => 'İzmir', 'addressCountry' => 'TR'],
    ];
@endphp

<link rel="canonical" href="{{ $seoCanonical }}">

{{-- Open Graph (WhatsApp, Facebook, LinkedIn) --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="FerXGo">
<meta property="og:locale" content="tr_TR">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

{{-- Twitter / X --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

{{-- Favicon & tema --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('images/ferxgo-logo.png') }}">
<meta name="theme-color" content="#000000">

@if(config('services.seo.verification'))
<meta name="google-site-verification" content="{{ config('services.seo.verification') }}">
@endif

{{-- Schema.org yapısal veri --}}
<script type="application/ld+json">{!! json_encode($seoSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
