@extends('rehber._layout')

@section('title', 'Korsan Taksi Yasal mı? İzmir’de Güvenli ve Yasal Ulaşım Alternatifi · FerXGo')
@section('description', 'Korsan taksi yasal mı, riskleri neler? İzmir’de korsan taksiye güvenli ve yasal alternatif: FerXGo paylaşımlı yolculuk. Mahkeme kararlarıyla pekişmiş model, şeffaf katkı payı, doğrulanmış üye sürücü.')

@section('kicker', 'Bilgi Rehberi')
@section('h1', 'Korsan Taksi Yasal mı? İzmir İçin Güvenli ve Yasal Alternatif')
@section('lead', 'Uygun fiyat ararken “korsan taksi” seçeneğine yönelen çok kişi var. Ancak bu yolun ciddi yasal ve güvenlik riskleri var. İyi haber: aynı uygunluğu yasal zeminde sunan bir alternatif mevcut — paylaşımlı yolculuk.')

@section('body')

<p>
    İzmir’de ulaşım ararken sıkça karşılaşılan bir kavram: <strong>korsan taksi</strong>. Peki korsan taksi tam olarak nedir,
    yasal mıdır ve binen kişi ne gibi risklerle karşılaşır? Bu rehber konuyu açıklar ve <strong>yasal, güvenli ve uygun</strong>
    bir alternatif sunar.
</p>

<h2>Korsan Taksi Nedir?</h2>
<p>
    Korsan taksi, gerekli <strong>ticari plaka, taksi durağı bağlantısı veya yolcu taşıma yetki belgesi</strong> olmadan,
    ücret karşılığı yolcu taşıyan araçlara verilen genel addır. Bu faaliyet mevzuata aykırıdır ve hem sürücü hem de bazı
    durumlarda yolcu açısından risk taşır.
</p>

<h2>Korsan Taksinin Riskleri</h2>
<ul>
    <li><strong>Yasal risk:</strong> Yetkisiz taşımacılık idari para cezalarına ve araç bağlanmasına yol açabilir.</li>
    <li><strong>Güvenlik riski:</strong> Sürücü ve araç kimliği doğrulanmamıştır; herhangi bir kayıt tutulmaz.</li>
    <li><strong>Sigorta/sorumluluk boşluğu:</strong> Olası bir kazada yolcunun hakları belirsiz kalabilir.</li>
    <li><strong>Fiyat belirsizliği:</strong> Ücret pazarlığa dayalıdır; şeffaf ve önceden belli değildir.</li>
</ul>

<h2>Yasal Alternatif: Paylaşımlı Yolculuk</h2>
<p>
    Korsan taksinin sunduğu “uygun fiyat” beklentisini, riskleri olmadan karşılayan yasal bir model var:
    <strong><a href="{{ route('legal.ride-sharing') }}">paylaşımlı yolculuk</a></strong>. Bu modelde araç ticari taksi
    değildir; kendi güzergâhında seyahat eden bir <strong>üye sürücü</strong>, aynı yöne giden yolcuyu aracına alır ve
    yolculuğun değişken giderlerine <strong>katkı payı</strong> paylaşılır.
</p>
<p>FerXGo, bu modeli dijital ve güvenli bir platform üzerinden sunar:</p>
<ul>
    <li><strong>Doğrulanmış üye sürücü ve araç bilgisi</strong> — yolculuk öncesi görünür.</li>
    <li><strong>Şeffaf katkı payı</strong> — tutar önceden ekranda gösterilir, pazarlık yoktur.</li>
    <li><strong>Kayıt ve değerlendirme sistemi</strong> — her yolculuk platformda iz bırakır.</li>
    <li><strong>Kadın sürücü seçeneği</strong> ve favori sürücü tercihi.</li>
</ul>

<h2>Paylaşımlı Yolculuk Neden Korsan Taşımacılık Değildir?</h2>
<p>
    Paylaşımlı yolculuk modeli, Türkiye’de mahkeme kararları ile pekişmiş bir hukuki temele sahiptir:
</p>
<ul>
    <li><strong>İstanbul Bölge Adliye Mahkemesi 14. Hukuk Dairesi:</strong> paylaşımlı yolculuk hizmetinin “korsan taşımacılık” kapsamına girmediğine karar vermiştir.</li>
    <li><strong>Danıştay (1 Kasım 2025):</strong> paylaşımlı yolculuk platformlarının elektronik ulaşım yönetim lisansı alma hakkını kesinleştirmiştir.</li>
    <li><strong>Antalya 2. İdare Mahkemesi:</strong> paylaşımlı yolculuk kullanıcılarına verilen idari para cezalarının iptaline karar vermiştir.</li>
</ul>
<p>
    FerXGo, 6563 sayılı Elektronik Ticaretin Düzenlenmesi Hakkında Kanun kapsamında <strong>aracı hizmet sağlayıcı</strong>
    sıfatıyla faaliyet gösterir; kendisi ticari taşımacılık yapmaz, yolculuk hizmetinin tarafı değildir.
</p>

<h2>Sıkça Sorulan Sorular</h2>
<h3>Korsan taksi yasal mı?</h3>
<p>Hayır. Yetki belgesi olmadan ücret karşılığı yolcu taşımak mevzuata aykırıdır ve idari yaptırımlara tabidir.</p>
<h3>FerXGo korsan taksi mi?</h3>
<p>
    Kesinlikle hayır. FerXGo bir taksi ya da taşımacılık firması değildir; bağımsız üye sürücüler ile yolcuları eşleştiren
    <a href="{{ route('legal.ride-sharing') }}">paylaşımlı yolculuk</a> platformudur. Model mahkeme kararlarıyla korsan
    taşımacılıktan ayrılmıştır.
</p>
<h3>Uygun fiyat ve yasal güvenceyi birlikte istiyorum, ne yapmalıyım?</h3>
<p>
    <a href="{{ route('ride.show') }}">Yolculuk planlama ekranından</a> güzergâhını gir, tahmini katkı payını gör ve
    doğrulanmış bir üye sürücüyle güvenle eşleş.
</p>

@endsection
