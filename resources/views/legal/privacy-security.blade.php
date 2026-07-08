@extends('legal._layout')

@section('title', 'Gizlilik ve Güvenlik Politikası · FerXGo')
@section('description', 'FerXGo gizlilik ve güvenlik politikası — kişisel verilerin korunması, veri güvenliği önlemleri ve kullanıcı gizliliği taahhütleri.')

@section('legal-title', 'Gizlilik ve Güvenlik Politikası')

@section('legal-body')

<p>
    FerXGo olarak, paylaşımlı yolculuk platformumuzu kullanan yolcu ve üye sürücülerin gizliliğine ve
    kişisel verilerinin güvenliğine büyük önem veriyoruz. Bu Gizlilik ve Güvenlik Politikası, hangi verileri
    topladığımızı, bu verileri nasıl kullandığımızı, koruduğumuzu ve haklarınızı nasıl kullanabileceğinizi açıklar.
    Platformu kullanarak bu politikada açıklanan uygulamaları kabul etmiş olursunuz.
</p>

<h2>1. Kapsam</h2>
<p>
    Bu politika; <a href="https://ferxgo.com.tr">ferxgo.com.tr</a> web sitesi, FerXGo mobil uygulamaları ve
    FerXGo tarafından sunulan tüm dijital hizmetler için geçerlidir. Kişisel verilerin işlenmesine ilişkin
    yasal aydınlatma yükümlülüğümüz <a href="{{ route('legal.kvkk') }}">KVKK Aydınlatma Metni</a> ile,
    çerez uygulamalarımız ise <a href="{{ route('legal.cookies') }}">Çerez Politikası</a> ile birlikte değerlendirilmelidir.
</p>

<h2>2. Topladığımız Bilgiler</h2>
<ul>
    <li><strong>Hesap bilgileri:</strong> Ad, soyad, telefon numarası, e-posta adresi.</li>
    <li><strong>Üye sürücü bilgileri:</strong> Kimlik, ehliyet, araç ve doğrulama belgeleri.</li>
    <li><strong>Konum verisi:</strong> Yalnızca yolculuk talebi ve eşleştirme için, uygulama aktifken.</li>
    <li><strong>Kullanım verileri:</strong> Yolculuk geçmişi, tercihleri ve platform içi etkileşimler.</li>
    <li><strong>Teknik veriler:</strong> IP adresi, cihaz bilgisi ve çerezler (sahtekârlık önleme ve güvenlik amacıyla).</li>
</ul>

<h2>3. Bilgileri Kullanma Amaçlarımız</h2>
<ul>
    <li>Yolcu ile üye sürücüyü güvenli biçimde eşleştirmek</li>
    <li>Hesap oluşturma ve kimlik doğrulama</li>
    <li>Yolculuk öncesi, sırası ve sonrası iletişimi sağlamak</li>
    <li>Güvenliği artırmak, sahtekârlık ve kötüye kullanımı tespit etmek</li>
    <li>Kullanıcı destek taleplerini yanıtlamak</li>
    <li>Yasal yükümlülükleri yerine getirmek ve hizmet kalitesini geliştirmek</li>
</ul>

<h2>4. Gizlilik İlkelerimiz</h2>
<p>
    FerXGo, kişisel verilerinizi <strong>asla satmaz</strong> ve pazarlama amacıyla üçüncü taraflara açık rızanız olmadan devretmez.
    Verileriniz yalnızca hizmetin sunulması için gerekli olduğu ölçüde ve yalnızca aşağıdaki taraflarla paylaşılır:
</p>
<ul>
    <li><strong>Yolcu ↔ Üye Sürücü:</strong> Eşleştirme sonrası iletişim için ad ve telefon bilgisi.</li>
    <li><strong>Altyapı sağlayıcıları:</strong> SMS, e-posta, ödeme ve bulut hizmetleri (yalnızca gerekli veriyle).</li>
    <li><strong>Yetkili kamu otoriteleri:</strong> Yasal talep (mahkeme, savcılık, emniyet) halinde.</li>
</ul>

<h2>5. Veri Güvenliği Önlemleri</h2>
<p>
    Kişisel verilerinizin yetkisiz erişime, kayba veya kötüye kullanıma karşı korunması için sektör standardında
    teknik ve idari tedbirler uyguluyoruz:
</p>
<ul>
    <li><strong>Şifreli iletişim:</strong> Tüm veri aktarımı SSL/TLS ile şifrelenir (HTTPS).</li>
    <li><strong>Erişim kontrolü:</strong> Verilere yalnızca yetkilendirilmiş personel, ihtiyaç ilkesi çerçevesinde erişebilir.</li>
    <li><strong>Parola güvenliği:</strong> Parolalar geri döndürülemez şekilde şifrelenmiş (hash) olarak saklanır.</li>
    <li><strong>Güvenli altyapı:</strong> Sunucular güvenlik duvarı, güncel yazılımlar ve düzenli yedekleme ile korunur.</li>
    <li><strong>İzleme ve denetim:</strong> Şüpheli erişimler ve kötüye kullanım girişimleri sürekli izlenir.</li>
    <li><strong>Veri minimizasyonu:</strong> Yalnızca gerekli olan veri toplanır ve süresi dolan veri güvenli şekilde imha edilir.</li>
</ul>
<p>
    Hiçbir sistem %100 güvenlik garantisi veremez; ancak FerXGo, verilerinizi korumak için makul ve güncel tüm
    önlemleri almayı taahhüt eder. Bir güvenlik ihlali yaşanması halinde, ilgili kullanıcılara ve yetkili kurumlara
    yasal süreler içinde bildirim yapılır.
</p>

<h2>6. Veri Saklama Süresi</h2>
<p>
    Kişisel verileriniz, hesabınız aktif olduğu sürece ve yasal saklama yükümlülükleri (vergi, ticari mevzuat vb.)
    gerektirdiği süre boyunca saklanır. Bu süre sona erdiğinde verileriniz güvenli biçimde silinir veya anonim hale getirilir.
</p>

<h2>7. Çerezler</h2>
<p>
    Platformumuz, deneyiminizi iyileştirmek ve güvenliği sağlamak için çerezler kullanır. Detaylı bilgi için
    <a href="{{ route('legal.cookies') }}">Çerez Politikası</a> sayfasını inceleyebilirsiniz.
</p>

<h2>8. Haklarınız</h2>
<p>
    6698 sayılı KVKK kapsamında; verilerinize erişme, düzeltme, silinmesini talep etme ve işlenmesine itiraz etme
    haklarına sahipsiniz. Bu hakların tam listesi ve başvuru yöntemi için
    <a href="{{ route('legal.kvkk') }}">KVKK Aydınlatma Metni</a> sayfasına bakınız.
</p>

<h2>9. Çocukların Gizliliği</h2>
<p>
    FerXGo hizmetleri 18 yaşını doldurmuş bireylere yöneliktir. Bilerek 18 yaşından küçük kişilerden kişisel veri toplamayız.
</p>

<h2>10. İletişim</h2>
<p>
    Gizlilik ve güvenlik ile ilgili soru, talep veya bildirimlerinizi aşağıdaki kanallardan iletebilirsiniz:
</p>
<ul>
    <li><strong>E-posta:</strong> <a href="mailto:kvkk@ferxgo.com.tr">kvkk@ferxgo.com.tr</a></li>
    <li><strong>Telefon:</strong> 0850 340 3039</li>
    <li><strong>Adres:</strong> İzmir / Türkiye</li>
</ul>

<h2>11. Güncellemeler</h2>
<p>
    Bu politika, mevzuat değişiklikleri veya hizmetlerimizdeki gelişmelere bağlı olarak güncellenebilir.
    Güncel metnin son güncellenme tarihi sayfanın başında belirtilir. Önemli değişikliklerde kullanıcılar bilgilendirilir.
</p>

@endsection
