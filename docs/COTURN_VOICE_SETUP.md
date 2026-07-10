# coturn (TURN) kurulumu — voice.ferxgo.com

Yolcu ↔ sürücü sesli görüşmesi **saf P2P WebRTC** (Laravel DB-polling sinyalleşmesi,
`resources/views/partials/call-widget.blade.php`). Türk mobil operatörleri simetrik
NAT/CGNAT arkasında olduğu için **STUN yeterli değil** — iki taraf birbirine doğrudan
paket gönderemez, ICE `connected` olamaz, ekran "Bağlanıyor…" da takılır ve **ses gelmez**.

Çözüm: paketleri röleleyen bir **TURN sunucusu**. Bunu santral (FreePBX) sunucusuna
`voice.ferxgo.com` olarak **bağımsız coturn** şeklinde kuruyoruz. Asterisk/SIP'e dokunmaz;
tek dikkat edilecek şey **RTP port çakışması** (aşağıda çözüldü).

> Bu kurulum santral sunucusunda **TEK SEFER** yapılır. Web app tarafında sadece `.env`
> güncellenir (repo'ya girmez, sunucudaki `.env` elle düzenlenir).

---

## 0) Ön koşullar

1. **DNS:** `voice.ferxgo.com` A kaydı → santral sunucusunun public IP'si.
   ```bash
   dig +short voice.ferxgo.com     # santral IP'sini döndürmeli
   ```
2. **Root SSH** santral sunucusuna.
3. Santral sunucusunun **80/tcp** portu Let's Encrypt doğrulaması için erişilebilir olmalı
   (FreePBX Apache'si zaten 80'de; webroot yöntemi kullanacağız).

---

## 1) coturn kurulumu (dağıtım otomatik algılanır)

FreePBX distro'su Sangoma/CentOS (`dnf`/`yum`) ya da Debian/Ubuntu (`apt`) olabilir.
Aşağıdaki blok ikisini de destekler:

```bash
if command -v apt-get >/dev/null 2>&1; then
    apt-get update && apt-get install -y coturn certbot
elif command -v dnf >/dev/null 2>&1; then
    dnf install -y epel-release && dnf install -y coturn certbot
else
    yum install -y epel-release && yum install -y coturn certbot
fi

# Zaten kurulu mu / çalışıyor mu kontrol (FreePBX bazen kendi coturn'ünü getirir)
which turnserver && turnserver --version | head -1
systemctl status coturn --no-pager 2>/dev/null | head -5 || true
```

> **FreePBX zaten coturn kurmuşsa:** yeni kurmak yerine mevcut `/etc/turnserver.conf`'u
> aşağıdaki ayarlarla güncelle. Aynı anda iki turnserver ÇALIŞTIRMA (port çakışır).

---

## 2) TLS sertifikası (voice.ferxgo.com)

FreePBX Apache'si 80'de çalıştığı için `--webroot` kullan (docroot genelde `/var/www/html`):

```bash
certbot certonly --webroot -w /var/www/html -d voice.ferxgo.com \
  --agree-tos -m webfirmam1035@gmail.com --non-interactive

# Sertifika yolu:
ls -l /etc/letsencrypt/live/voice.ferxgo.com/
```

coturn `turnserver` kullanıcısı sertifikayı okuyabilmeli:

```bash
# Debian'da coturn 'turnserver' kullanıcısıyla çalışır; letsencrypt dizinine okuma izni:
setfacl -R -m u:turnserver:rX /etc/letsencrypt/live /etc/letsencrypt/archive 2>/dev/null \
  || chmod -R 0755 /etc/letsencrypt/live /etc/letsencrypt/archive
```

---

## 3) Güçlü kimlik bilgisi üret

```bash
openssl rand -hex 24        # çıktıyı KOPYALA — hem coturn'e hem .env'e girecek
```

---

## 4) /etc/turnserver.conf

`CREDENTIAL_BURAYA` yerine 3. adımdaki çıktıyı yaz. Sunucu **1:1 NAT arkasındaysa**
(public IP arayüzde değil) `external-ip` satırını aç ve public IP'yi yaz; VPS'te public IP
doğrudan atanmışsa kapalı bırak.

```ini
# ── Dinleme ──────────────────────────────────────────────
listening-port=3478
tls-listening-port=5349
listening-ip=0.0.0.0
# external-ip=SANTRAL_PUBLIC_IP     # sadece 1:1 NAT arkasındaysa aç

# ── Relay port aralığı ───────────────────────────────────
# Asterisk RTP (varsayılan 10000-20000) ile ÇAKIŞMASIN diye 50000+ seçildi.
min-port=50000
max-port=50500

# ── Kimlik (uzun süreli statik kullanıcı) ────────────────
realm=voice.ferxgo.com
server-name=voice.ferxgo.com
lt-cred-mech
user=ferxgo:CREDENTIAL_BURAYA

# ── TLS ──────────────────────────────────────────────────
cert=/etc/letsencrypt/live/voice.ferxgo.com/fullchain.pem
pkey=/etc/letsencrypt/live/voice.ferxgo.com/privkey.pem
no-tlsv1
no-tlsv1_1

# ── Güvenlik: iç ağa relay engelle (SSRF koruması) ───────
no-multicast-peers
no-cli
fingerprint
stale-nonce=600
denied-peer-ip=0.0.0.0-0.255.255.255
denied-peer-ip=10.0.0.0-10.255.255.255
denied-peer-ip=100.64.0.0-100.127.255.255
denied-peer-ip=127.0.0.0-127.255.255.255
denied-peer-ip=169.254.0.0-169.254.255.255
denied-peer-ip=172.16.0.0-172.31.255.255
denied-peer-ip=192.168.0.0-192.168.255.255
denied-peer-ip=198.18.0.0-198.19.255.255

# ── Log ──────────────────────────────────────────────────
simple-log
log-file=/var/log/turnserver/turnserver.log
```

Debian'da servisin başlaması için `/etc/default/coturn` içinde şu satırı aç:

```bash
sed -i 's/^#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn 2>/dev/null || true
mkdir -p /var/log/turnserver && chown turnserver: /var/log/turnserver 2>/dev/null || true
```

---

## 5) Firewall

Açılacak portlar: **3478 tcp+udp**, **5349 tcp+udp**, **50000-50500 udp** (relay).

**firewalld (Sangoma/CentOS):**
```bash
firewall-cmd --permanent --add-port=3478/tcp --add-port=3478/udp
firewall-cmd --permanent --add-port=5349/tcp --add-port=5349/udp
firewall-cmd --permanent --add-port=50000-50500/udp
firewall-cmd --reload
```

**ufw (Ubuntu/Debian):**
```bash
ufw allow 3478/tcp && ufw allow 3478/udp
ufw allow 5349/tcp && ufw allow 5349/udp
ufw allow 50000:50500/udp
```

> **FreePBX "Responsive Firewall" uyarısı:** FreePBX'in kendi firewall'u bu portları
> bloklayabilir. Sangoma GUI → Connectivity → Firewall → Networks/Services'ten
> 3478/5349/50000-50500 portlarını (ya da bilinen istemci ağlarını) whitelist'e ekle.
> Mobil istemcilerin IP'si sabit olmadığı için bu portları **public** açmak gerekir.

Ayrıca **hosting/VPS panelinin dış güvenlik grubunda** (Hetzner/DO/AWS vb.) da aynı
portları aç — sunucu içi firewall yetmez.

---

## 6) Servisi başlat

```bash
systemctl enable coturn
systemctl restart coturn
systemctl status coturn --no-pager | head -15
journalctl -u coturn -n 40 --no-pager      # hata varsa burada görünür
```

---

## 7) Web app .env (WEB sunucusunda — santralde DEĞİL)

Santraldeki coturn hazır olunca, **web sunucusundaki** `.env`'i düzenle
(`/var/www/www-root/data/www/randevumcepteyenimimari/.env`):

```dotenv
STUN_URLS=stun:stun.l.google.com:19302,stun:voice.ferxgo.com:3478
TURN_URLS=turn:voice.ferxgo.com:3478?transport=udp,turn:voice.ferxgo.com:3478?transport=tcp,turns:voice.ferxgo.com:5349?transport=tcp
TURN_USERNAME=ferxgo
TURN_CREDENTIAL=CREDENTIAL_BURAYA
```

Sonra config cache'i tazele:

```bash
php artisan config:clear   # ya da deploy.sh zaten optimize:clear yapıyor
```

> `TURN_CREDENTIAL`, coturn'deki `user=ferxgo:...` ile **birebir aynı** olmalı.

---

## 8) Doğrulama

1. **Trickle ICE testi** (en hızlı): <https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/>
   - STUN/TURN URI'lerini gir, username=`ferxgo`, credential=üretilen değer.
   - **`relay` tipinde** aday satırı görünmeli. Görünmüyorsa TURN erişilemiyor (firewall/cert).
2. **Gerçek arama:** iki farklı cihaz/mobil hattan ara. Tarayıcı konsolunda:
   - `[call] ICE servers: 2 (TURN var)` görünmeli (artık "SADECE STUN" değil).
   - `[call] ICE state: connected` → widget "Bağlandı" + timer sayar.
   - Alt satırdaki ölçüm `xx kbps ✓` yeşil → ses paketi akıyor.

---

## Sık karşılaşılan sorunlar

| Belirti | Sebep | Çözüm |
|---|---|---|
| Trickle ICE'de `relay` adayı yok | Firewall / VPS güvenlik grubu portları kapalı | 5. adım — hem sunucu içi hem panel firewall |
| `turnserver` başlamıyor, cert hatası | `turnserver` kullanıcısı sertifikayı okuyamıyor | 2. adım setfacl/chmod |
| Konsolda hâlâ "SADECE STUN" | `.env` `TURN_URLS` boş / config cache eski | 7. adım + `config:clear` |
| Bağlanıyor ama `turns:5349` çalışmıyor | TLS cert domain uyuşmuyor / 5349 kapalı | cert'in `voice.ferxgo.com` olduğunu ve 5349 açık olduğunu doğrula |
| FreePBX araması bozuldu | Relay aralığı Asterisk RTP ile çakıştı | `min-port/max-port` Asterisk RTP aralığının dışında olmalı (varsayılan 10000-20000) |

---

## Not: Bu app Asterisk kullanmıyor

Bu sürümde arama Asterisk/SIP üzerinden gitmiyor — coturn sadece iki tarayıcı arasındaki
P2P medyayı röleliyor. İleride santral (FreePBX) üzerinden PSTN/SIP entegrasyonu istenirse
(WebRTC istemcisi Asterisk'e register olur) ayrı bir mimari gerekir; bu doküman onu kapsamaz.
