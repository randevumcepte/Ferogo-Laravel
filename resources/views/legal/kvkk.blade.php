@extends('legal._layout')

@section('title', 'KVKK Aydınlatma Metni · Ferxgo')
@section('description', 'Ferxgo Kişisel Verilerin Korunması Kanunu (6698) kapsamında aydınlatma metni.')

@section('legal-title', 'KVKK Aydınlatma Metni')

@section('legal-body')

<p>
    Ferxgo olarak, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamında <strong>veri sorumlusu</strong> sıfatıyla,
    paylaşımlı yolculuk platformumuzu kullanan kullanıcıların kişisel verilerinin korunmasına önem veriyoruz.
    Bu aydınlatma metni KVKK m.10 kapsamında hazırlanmıştır.
</p>

<h2>1. Veri Sorumlusu Kimliği</h2>
<ul>
    <li><strong>Unvan:</strong> Ferxgo</li>
    <li><strong>Adres:</strong> İzmir / Türkiye</li>
    <li><strong>İletişim:</strong> <a href="mailto:kvkk@ferxgo.com.tr">kvkk@ferxgo.com.tr</a></li>
    <li><strong>VERBİS Kayıt:</strong> Başvuru tamamlandığında bu alanda yayınlanacaktır.</li>
</ul>

<h2>2. İşlenen Kişisel Veri Kategorileri</h2>

<h3>Yolcu için:</h3>
<ul>
    <li>Kimlik bilgileri (ad, soyad)</li>
    <li>İletişim bilgileri (telefon, e-posta)</li>
    <li>Konum verisi (yolculuk talep edildiği an + güzergah)</li>
    <li>Yolculuk geçmişi ve tercihleri</li>
    <li>Cihaz/işlem bilgisi (IP, cihaz parmak izi — sahtekarlık önleme için)</li>
</ul>

<h3>Üye Sürücü için:</h3>
<ul>
    <li>Kimlik bilgileri (ad, soyad, T.C. kimlik no, doğum tarihi)</li>
    <li>İletişim bilgileri (telefon, e-posta, adres)</li>
    <li>Ehliyet bilgileri</li>
    <li>Adli sicil belgesi</li>
    <li>Araç bilgileri ve fotoğraflar</li>
    <li>Vergi mükellefiyet bilgisi (opsiyonel)</li>
    <li>Konum verisi (online iken aktif konum)</li>
    <li>Yolculuk geçmişi ve performans verileri</li>
</ul>

<h2>3. İşleme Amaçları</h2>
<ul>
    <li>Paylaşımlı yolculuk koordinasyonu için yolcu ve üye sürücüyü eşleştirmek</li>
    <li>Hesap oluşturma ve kimlik doğrulama</li>
    <li>Yolculuk öncesi/sırası/sonrası iletişim</li>
    <li>Üyelik bedeli faturalandırma</li>
    <li>Sahtekarlık ve kötüye kullanım tespiti</li>
    <li>Kullanıcı destek hizmetleri</li>
    <li>Yasal yükümlülüklerin yerine getirilmesi (vergi, KVKK, yargı bilgi talepleri)</li>
    <li>Platform iyileştirme ve analiz</li>
</ul>

<h2>4. Hukuki Sebepler (KVKK m.5)</h2>
<ul>
    <li>Sözleşmenin kurulması ve ifası için zorunlu olması</li>
    <li>Hukuki yükümlülüklerin yerine getirilmesi</li>
    <li>Veri sorumlusunun meşru menfaati</li>
    <li>Açık rıza (pazarlama iletişimi için)</li>
</ul>

<h2>5. Üçüncü Kişilerle Paylaşım</h2>
<p>Kişisel veriler, aşağıdaki durumlar haricinde üçüncü kişilerle paylaşılmaz:</p>
<ul>
    <li><strong>Yolcu ↔ Üye Sürücü:</strong> Eşleştirme sonrası iletişim için ad ve telefon paylaşılır</li>
    <li><strong>Ödeme altyapısı sağlayıcıları:</strong> Üyelik bedeli tahsilatı için kart bilgileri PCI-DSS uyumlu sağlayıcıya iletilir</li>
    <li><strong>SMS / e-posta sağlayıcıları:</strong> Bildirim gönderimi için</li>
    <li><strong>Bulut altyapı (Türkiye içi sunucular):</strong> Veri depolama</li>
    <li><strong>Yetkili kamu otoriteleri:</strong> Yasal talep halinde (mahkeme, savcılık, emniyet)</li>
</ul>

<h2>6. Yurt Dışına Aktarım</h2>
<p>
    Ferxgo, kişisel verileri kural olarak Türkiye'de tutar. Zorunlu bulut altyapı entegrasyonlarında, KVKK Kurulu izinli veya
    güvenli kabul edilen ülkelerdeki sağlayıcılarla çalışılır; bu durumda KVKK m.9 hükümleri uygulanır.
</p>

<h2>7. Saklama Süreleri</h2>
<ul>
    <li>Hesap aktif olduğu sürece</li>
    <li>Hesap kapatıldıktan sonra yasal saklama süreleri (vergi: 10 yıl, ticari: 5 yıl)</li>
    <li>Trafik kazası kayıtları: 10 yıl</li>
    <li>Pazarlama amaçlı veriler: rıza geri çekilene kadar</li>
</ul>

<h2>8. Veri Sahibinin Hakları (KVKK m.11)</h2>
<p>Kullanıcı, KVKK m.11 kapsamında aşağıdaki haklara sahiptir:</p>
<ul>
    <li>İşlenen kişisel verileri hakkında bilgi talep etme</li>
    <li>İşleme amacını öğrenme</li>
    <li>Yurt içi/dışı aktarımlar hakkında bilgi alma</li>
    <li>Eksik/yanlış işlenmiş verilerin düzeltilmesini isteme</li>
    <li>KVKK m.7 çerçevesinde silinmesini veya yok edilmesini talep etme</li>
    <li>Otomatik sistemler ile yapılan analizler aleyhine itiraz etme</li>
    <li>Zarar halinde tazminat talep etme</li>
</ul>

<p>
    Başvurular için: <a href="mailto:kvkk@ferxgo.com.tr">kvkk@ferxgo.com.tr</a> veya Veri Sahibi Başvuru Formu doldurularak posta yoluyla iletilebilir.
    KVKK m.13 uyarınca taleplere en geç <strong>30 gün</strong> içinde cevap verilir.
</p>

<h2>9. Çerez Kullanımı</h2>
<p>
    Çerezlere ilişkin detaylı bilgi için <a href="{{ route('legal.cookies') }}">Çerez Politikası</a>'nı inceleyiniz.
</p>

<h2>10. Güncellemeler</h2>
<p>
    Bu aydınlatma metni güncellendikçe değişiklikler bu sayfada yayınlanacaktır. Güncel metnin son güncellenme tarihi sayfanın başında belirtilir.
</p>

@endsection
