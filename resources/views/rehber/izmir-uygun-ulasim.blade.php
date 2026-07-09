@extends('rehber._layout')

@section('title', 'İzmir’de Uygun Fiyatlı Ulaşım | Şehir İçi Paylaşımlı Yolculuk · FerXGo')
@section('description', 'İzmir’de uygun ve ekonomik ulaşım mı arıyorsunuz? FerXGo paylaşımlı yolculuk ile şehir içi yolculuklarda maliyeti paylaşın; şeffaf katkı payı, 7/24 üye sürücü erişimi. Konak, Bornova, Karşıyaka ve tüm İzmir.')

@section('kicker', 'İzmir Uygun Ulaşım')
@section('h1', 'İzmir’de Uygun Fiyatlı Ulaşım')
@section('lead', 'İzmir’de şehir içi yolculuklarda daha uygun bir seçenek arıyorsanız, paylaşımlı yolculuk maliyeti paylaşmanın en akıllı yoludur. FerXGo ile aynı güzergâhtaki üye sürücülere ulaşın, şeffaf katkı payıyla ekonomik yolculuk yapın.')

@section('body')

<p>
    Şehir içi ulaşım masrafları giderek artarken, İzmirliler <strong>uygun fiyatlı ve pratik</strong> alternatifler arıyor.
    FerXGo, geleneksel taşımacılığa göre daha ekonomik bir model sunar: <strong>paylaşımlı yolculuk</strong>. Aynı yöne giden
    bağımsız bir üye sürücüyle eşleşir, yolculuğun değişken giderlerine <strong>katkı payı</strong> ödeyerek maliyeti paylaşırsın.
</p>

<h2>Paylaşımlı Yolculuk Neden Daha Uygun?</h2>
<p>
    Geleneksel ticari taşımacılıkta araç yalnızca senin için tahsis edilir ve sabit tarife uygulanır. Paylaşımlı yolculukta ise
    üye sürücü zaten o güzergâhta seyahat etmektedir; sen mevcut yolculuğun paylaşımcısı olursun. Bu nedenle:
</p>
<ul>
    <li><strong>Katkı payı</strong> taksimetre ücreti değildir — yakıt ve amortisman gibi değişken giderlere katkıdır.</li>
    <li>Tutar yolculuk öncesi <strong>şeffaf biçimde</strong> gösterilir; sürpriz ek ücret olmaz.</li>
    <li>Üyelik tabanlı model sayesinde aracılık maliyeti düşüktür.</li>
</ul>

<h2>İzmir’in Her Yerinde Şehir İçi Yolculuk</h2>
<p>
    FerXGo İzmir genelinde aktiftir. En yoğun şehir içi güzergâhlar arasında
    <strong>Konak, Alsancak, Bornova, Karşıyaka, Bayraklı, Buca, Çiğli, Gaziemir, Balçova ve Narlıdere</strong> bulunur.
    Nereden nereye gideceğini gir, uygun üye sürücüyü ve tahmini katkı payını gör.
</p>

<h2>Uygun Ulaşım İçin İpuçları</h2>
<ul>
    <li><strong>Yolculuğunu önceden planla:</strong> Güzergâhını erken girmek daha kolay eşleşme sağlar.</li>
    <li><strong>Katkı payını önceden gör:</strong> <a href="{{ route('ride.show') }}">Yolculuk planlama ekranında</a> tahmini tutarı kontrol et.</li>
    <li><strong>Favori sürücü:</strong> Memnun kaldığın üye sürücüyü favorine ekleyerek sonraki yolculuklarda tercih et.</li>
</ul>

<h2>Sıkça Sorulan Sorular</h2>
<h3>İzmir’de en uygun ulaşım hangisi?</h3>
<p>
    İhtiyaca göre değişir; toplu taşıma en ekonomik ama aktarmalı olabilir. Kapıdan kapıya konfor isteyip aynı zamanda
    maliyeti düşürmek isteyenler için paylaşımlı yolculuk dengeli bir seçenektir.
</p>
<h3>Katkı payı nasıl belirlenir?</h3>
<p>Mesafe ve güzergâha göre hesaplanır ve yolculuk öncesi şeffaf olarak gösterilir. Ödeme doğrudan üye sürücüye yapılır.</p>
<h3>FerXGo ucuz taksi mi?</h3>
<p>
    FerXGo bir taksi firması değil, <a href="{{ route('legal.ride-sharing') }}">paylaşımlı yolculuk</a> platformudur.
    Amaç yolculuk maliyetini paylaşarak ekonomik bir alternatif sunmaktır; ticari taşımacılık hizmeti verilmez.
</p>

@endsection
