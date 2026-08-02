@extends('legal._layout')

@section('title', 'Hesap Silme · FerXGo')
@section('description', 'FerXGo hesabınızı ve kişisel verilerinizi nasıl sileceğinizi öğrenin. Uygulama içinden veya bu sayfadaki talep yöntemiyle hesabınızı kalıcı olarak silebilirsiniz.')

@section('legal-title', 'Hesap ve Veri Silme Talebi')

@section('legal-body')

<p>
    FerXGo hesabınızı ve hesabınıza bağlı kişisel verilerinizi istediğiniz zaman silebilirsiniz.
    Bu sayfa, hesabınızı hangi yollarla silebileceğinizi, silme sonucunda hangi verilerin
    kaldırılacağını ve hangilerinin yasal zorunluluk gereği bir süre saklanacağını açıklar.
</p>

<h2>1. Uygulama İçinden Silme (Önerilen)</h2>
<p>Hesabınıza giriş yapabiliyorsanız en hızlı yol uygulama içinden silmektir:</p>
<ul>
    <li>FerXGo uygulamasını açın ve hesabınıza giriş yapın.</li>
    <li><strong>Profil / Ayarlar</strong> ekranına gidin.</li>
    <li><strong>“Hesabımı Sil”</strong> seçeneğine dokunun ve onaylayın.</li>
    <li>Hesabınız ve kişisel verileriniz aşağıda belirtilen esaslara göre silinir.</li>
</ul>

<h2>2. Bu Sayfa Üzerinden Silme Talebi</h2>
<p>
    Uygulamaya erişiminiz yoksa (uygulamayı kaldırdıysanız veya giriş yapamıyorsanız),
    hesabınızın silinmesini e-posta ile talep edebilirsiniz:
</p>
<ul>
    <li>
        <strong>E-posta:</strong>
        <a href="mailto:hesapsil@ferxgo.com.tr?subject=Hesap%20Silme%20Talebi">hesapsil@ferxgo.com.tr</a>
    </li>
    <li>
        E-postanızda hesabınızla ilişkili <strong>telefon numaranızı</strong> (yolcu hesabı) veya
        <strong>e-posta adresinizi</strong> (sürücü hesabı) belirtin.
    </li>
    <li>
        Kimlik doğrulaması sonrası talebiniz <strong>en geç 30 gün</strong> içinde sonuçlandırılır ve
        işlem tamamlandığında size bilgi verilir.
    </li>
</ul>

<h2>3. Silinen Veriler</h2>
<p>Hesap silme işlemi tamamlandığında aşağıdaki kişisel verileriniz kalıcı olarak silinir veya geri döndürülemez şekilde anonimleştirilir:</p>
<ul>
    <li>Ad ve soyad bilgisi</li>
    <li>Telefon numarası</li>
    <li>Sürücü hesabı için e-posta adresi ve giriş bilgileri</li>
    <li>Hesap ve cihaz tanımlayıcıları</li>
    <li>Kayıtlı adres/konum tercihleri ve favori sürücü kayıtları</li>
    <li>Uygulama içi mesajlaşma içerikleri</li>
</ul>

<h2>4. Yasal Nedenle Saklanan Veriler</h2>
<p>
    Yürürlükteki mevzuat (örneğin vergi, ticaret ve elektronik ticaret düzenlemeleri) gereği,
    tamamlanmış yolculuklara ait <strong>işlem/fatura kayıtları</strong> ile hukuki uyuşmazlık ve
    dolandırıcılık önleme amacıyla gerekli asgari kayıtlar, ilgili yasal saklama süresi boyunca
    (genel olarak <strong>10 yıla kadar</strong>) tutulabilir. Bu kayıtlar yalnızca yasal
    yükümlülüklerin yerine getirilmesi amacıyla saklanır ve pazarlama veya reklam amacıyla kullanılmaz.
</p>

<h2>5. İletişim</h2>
<p>
    Hesap silme veya kişisel verilerinize ilişkin diğer talepleriniz için bizimle iletişime geçebilirsiniz:
    <a href="mailto:hesapsil@ferxgo.com.tr">hesapsil@ferxgo.com.tr</a>
</p>

@endsection
