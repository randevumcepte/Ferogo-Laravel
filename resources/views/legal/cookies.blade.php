@extends('legal._layout')

@section('title', 'Çerez Politikası · Ferxgo')
@section('description', 'Ferxgo dijital platformunda kullanılan çerezler ve amaçları.')

@section('legal-title', 'Çerez Politikası')

@section('legal-body')

<p>
    Ferxgo web sitesinde ve mobil uygulamasında, kullanıcı deneyimini iyileştirmek, güvenliği sağlamak ve hizmet kalitesini
    artırmak amacıyla çerezler ve benzeri teknolojiler kullanılır. Bu metin, KVKK ve 5651 sayılı Kanun çerçevesinde hangi
    çerezlerin kullanıldığı hakkında bilgi verir.
</p>

<h2>1. Çerez Nedir?</h2>
<p>
    Çerez, bir web sitesini ziyaret ettiğinizde tarayıcınız aracılığıyla cihazınıza yerleştirilen küçük bir metin dosyasıdır.
    Çerezler, sitenin sizi tanımasını, tercihlerinizi hatırlamasını ve oturumunuzu sürdürmesini sağlar.
</p>

<h2>2. Kullandığımız Çerez Türleri</h2>

<h3>Zorunlu Çerezler (Onay gerektirmez)</h3>
<ul>
    <li><strong>Oturum çerezi (Laravel session):</strong> Platforma giriş yapmış kullanıcının oturumunu sürdürmek için</li>
    <li><strong>CSRF token çerezi:</strong> Form güvenliği için</li>
    <li><strong>Çerez tercihi çerezi:</strong> Sizin çerez tercihinizi hatırlamak için</li>
</ul>

<h3>İşlevsel Çerezler</h3>
<ul>
    <li><strong>Konum tercihi:</strong> Son seçtiğiniz şehir/bölge bilgisi</li>
    <li><strong>Dil tercihi:</strong> Arayüz dil seçimi</li>
    <li><strong>Tema tercihi:</strong> Karanlık/aydınlık mod</li>
</ul>

<h3>Analitik Çerezler (Açık rıza ile)</h3>
<ul>
    <li><strong>Kullanım analizi:</strong> Hangi sayfaların ne kadar ziyaret edildiği</li>
    <li><strong>Hata izleme:</strong> Teknik aksaklıkların tespiti</li>
    <li><strong>A/B test:</strong> Yeni özelliklerin test edilmesi</li>
</ul>

<h3>Pazarlama Çerezleri (Açık rıza ile)</h3>
<ul>
    <li>Ürün önerileri</li>
    <li>Kampanya hedefleme</li>
    <li>Yeniden pazarlama</li>
</ul>

<h2>3. Üçüncü Taraf Çerezler</h2>
<ul>
    <li><strong>OpenStreetMap / Leaflet:</strong> Harita gösterimi</li>
    <li><strong>Nominatim:</strong> Adres arama</li>
    <li><strong>Ödeme altyapı sağlayıcısı:</strong> Kart işlemleri (3D Secure)</li>
    <li><strong>SMS sağlayıcısı:</strong> OTP gönderimi</li>
</ul>

<h2>4. Çerezleri Yönetme</h2>
<p>
    Tarayıcınız üzerinden çerezleri silebilir veya engelleyebilirsiniz. Ancak bazı zorunlu çerezler devre dışı bırakıldığında
    platformumuzun bazı özellikleri çalışmayabilir.
</p>

<p>Popüler tarayıcılarda çerez yönetimi:</p>
<ul>
    <li>Chrome: Ayarlar → Gizlilik ve güvenlik → Çerezler</li>
    <li>Safari: Tercihler → Gizlilik</li>
    <li>Firefox: Seçenekler → Gizlilik ve Güvenlik</li>
    <li>Edge: Ayarlar → Çerezler ve site izinleri</li>
</ul>

<h2>5. Saklama Süreleri</h2>
<ul>
    <li>Oturum çerezleri: Tarayıcı kapatılınca silinir</li>
    <li>Konum/dil tercihi: 1 yıl</li>
    <li>Analitik çerezler: 2 yıl</li>
    <li>Pazarlama çerezleri: Rıza geri çekilene kadar</li>
</ul>

<h2>6. İletişim</h2>
<p>
    Çerez kullanımına ilişkin sorularınız için: <a href="mailto:kvkk@ferxgo.com.tr">kvkk@ferxgo.com.tr</a>
</p>

@endsection
