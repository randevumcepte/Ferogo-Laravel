@extends('rehber._layout')

@section('title', 'İzmir Havalimanı Ulaşım ve Transfer | Uygun Paylaşımlı Yolculuk · FerXGo')
@section('description', 'İzmir Adnan Menderes Havalimanı’ndan şehir merkezine uygun fiyatlı ulaşım. FerXGo paylaşımlı yolculuk ile havalimanı transferinde şeffaf katkı payı, 7/24 üye sürücü erişimi. Konak, Alsancak, Bornova, Karşıyaka ve tüm İzmir.')

@section('kicker', 'İzmir Havalimanı Ulaşım')
@section('h1', 'İzmir Adnan Menderes Havalimanı Ulaşım ve Transfer')
@section('lead', 'İzmir Havalimanı’ndan şehir merkezine uygun ve güvenli ulaşım mı arıyorsunuz? FerXGo paylaşımlı yolculuk platformu ile bağımsız üye sürücülere saniyeler içinde ulaşın; şeffaf katkı payıyla planlı yolculuk yapın.')

@section('body')

<p>
    İzmir Adnan Menderes Havalimanı (ADB), şehir merkezine yaklaşık 18 km mesafededir. Uçuş sonrası
    <strong>havalimanı ulaşımı</strong> için birçok seçenek olsa da, yolcuların çoğu <strong>uygun fiyatlı, güvenli ve
    beklemesiz</strong> bir çözüm arar. FerXGo, bu ihtiyaç için <strong>paylaşımlı yolculuk</strong> modelini sunar:
    aynı güzergâhta seyahat eden bağımsız üye sürücüler ile yolcuları dijital ortamda buluşturur.
</p>

<h2>İzmir Havalimanı’ndan Şehir Merkezine Nasıl Gidilir?</h2>
<p>
    Havalimanından Konak, Alsancak, Bornova, Karşıyaka, Buca ve çevre ilçelere ulaşmak için başlıca seçenekler:
</p>
<ul>
    <li><strong>Toplu taşıma (İZBAN / otobüs):</strong> Ekonomik ama valizle ve aktarmalı olduğunda yorucu olabilir.</li>
    <li><strong>Özel araç / geleneksel taşımacılık:</strong> Konforlu ama genellikle en pahalı seçenek.</li>
    <li><strong>Paylaşımlı yolculuk (FerXGo):</strong> Aynı yöne giden üye sürücüyle eşleşerek maliyeti paylaşırsınız —
        konfor ile uygunluğun dengesi.</li>
</ul>

<h2>Havalimanı Yolculuğunda FerXGo Neden Avantajlı?</h2>
<ul>
    <li><strong>Şeffaf katkı payı:</strong> Yolculuk öncesi tahmini katkı payı ekranda açıkça gösterilir; sürpriz yoktur.</li>
    <li><strong>7/24 erişim:</strong> Gece inen uçuşlar dahil, platform her saat aktiftir.</li>
    <li><strong>Uçuş yoğunluğuna uygun planlama:</strong> Kalkış/varış saatine göre yolculuğunu önceden ayarlayabilirsin.</li>
    <li><strong>Güvenlik:</strong> Üye sürücü ve araç bilgisi, yolculuk öncesi doğrulama ve değerlendirme sistemi.</li>
    <li><strong>Kadın sürücü seçeneği:</strong> Dileyen yolcular için kadın üye sürücü tercihi.</li>
</ul>

<h2>Hangi Bölgelere Havalimanı Yolculuğu Yapılır?</h2>
<p>
    FerXGo İzmir genelinde aktiftir. Havalimanı yolculuğu en çok şu bölgelerde talep görür:
    <strong>Konak, Alsancak, Bayraklı, Bornova, Karşıyaka, Çiğli, Buca, Gaziemir, Balçova, Narlıdere</strong> ve
    Çeşme–Urla hattı. Güzergâhını girip uygun üye sürücüyü görebilirsin.
</p>

<h2>İzmir Havalimanı Ulaşım Ücreti Ne Kadar?</h2>
<p>
    Paylaşımlı yolculukta yolcunun ödediği tutar sabit bir taksimetre ücreti değil, yolculuğun değişken giderlerine
    (yakıt, amortisman) <strong>katkı payı</strong> niteliğindedir. Mesafe ve güzergâha göre değişir ve
    <strong>yolculuk öncesi şeffaf biçimde</strong> gösterilir. Tahmini katkı payını görmek için
    <a href="{{ route('ride.show') }}">yolculuk planlama ekranını</a> kullanabilirsin.
</p>

<h2>Sıkça Sorulan Sorular</h2>
<h3>Havalimanına önceden yolculuk planlayabilir miyim?</h3>
<p>Evet. Uçuş saatine göre güzergâhını girip uygun üye sürücüyle önceden planlama yapabilirsin.</p>
<h3>Gece geç saatte de sürücü bulabilir miyim?</h3>
<p>Platform 7/24 aktiftir; gece inen uçuşlar için de üye sürücülere ulaşabilirsin (yoğunluğa göre süre değişebilir).</p>
<h3>FerXGo bir taksi firması mı?</h3>
<p>
    Hayır. FerXGo <a href="{{ route('legal.ride-sharing') }}">paylaşımlı yolculuk</a> platformudur; ticari taşımacılık
    hizmeti sunmaz. Yolculuk, bağımsız üye sürücü ile yolcu arasında gerçekleşir; FerXGo yalnızca eşleştirme hizmeti verir.
</p>

@endsection
