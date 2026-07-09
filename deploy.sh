#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
# FerXGo otomatik deploy scripti.
# Cron ile tetiklenir: uzakta (GitHub main) yeni commit varsa çeker
# ve Laravel önbelleğini temizler. Değişiklik yoksa hiçbir şey yapmaz
# (sadece hafif bir "git fetch"), o yüzden sık çalıştırmak ucuzdur.
#
# Kurulum (sunucuda TEK SEFER):
#   chmod +x deploy.sh
#   crontab -e   → şu satırı ekle (her 2 dakikada bir kontrol):
#   */2 * * * * /var/www/www-root/data/www/randevumcepteyenimimari/deploy.sh >> /var/www/www-root/data/www/randevumcepteyenimimari/storage/logs/deploy.log 2>&1
# ─────────────────────────────────────────────────────────────
set -euo pipefail

# Scriptin bulunduğu klasöre geç (repo kökü)
cd "$(dirname "$0")"

# Cron'un dar PATH'i için PHP'yi güvenli bul
PHP_BIN="$(command -v php || echo /usr/bin/php)"

git fetch origin main --quiet

LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse origin/main)"

# Değişiklik yoksa sessizce çık
if [ "$LOCAL" = "$REMOTE" ]; then
    exit 0
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Yeni surum bulundu: $REMOTE — deploy basliyor"

# git pull yerine reset --hard: sunucudaki yerel (tracked) degisiklikler pull'u
# ENGELLEMESIN diye zorla origin/main'e esitle. Untracked dosyalar (.env vb.) korunur.
git reset --hard origin/main --quiet

# Reset sonrasi deploy.sh'in kendisinin +x izni korunsun (Windows'tan commit
# edilmis dosyalarda mode bit bazen kayboluyor).
chmod +x "$0" 2>/dev/null || true

# Bekleyen migration'lari uygula. Bir migration hata verse bile deploy'u durdurma
# (yoksa cache temizlenmez ve sunucu eski surumde takili kalir).
"$PHP_BIN" artisan migrate --force || echo "  ! migrate hata verdi, devam ediliyor"

# Katalog seeder'lari — idempotent (updateOrCreate). Yeni marka/model/kategori
# eklendiginde otomatik canliya iner. Var olan kayitlar korunur, sadece
# eksikler eklenir. Hata verse deploy'u durdurma.
"$PHP_BIN" artisan db:seed --class="Database\\Seeders\\DriverCategorySeeder" --force 2>/dev/null || echo "  ! DriverCategorySeeder hata verdi, gecildi"
"$PHP_BIN" artisan db:seed --class="Database\\Seeders\\VehicleCatalogSeeder" --force 2>/dev/null || echo "  ! VehicleCatalogSeeder hata verdi, gecildi"

# Filament / Blade / config / route / view cache'lerini toplu temizle
"$PHP_BIN" artisan optimize:clear || true

# OPcache'i de sifirla (derlenmis Blade/PHP bytecode'u bellekten dusur)
"$PHP_BIN" -r "if (function_exists('opcache_reset')) { opcache_reset(); echo '  opcache reset\n'; }" || true

# Storage sembolik linki yoksa olustur (public/storage → storage/app/public)
if [ ! -L public/storage ] && [ ! -d public/storage ]; then
    "$PHP_BIN" artisan storage:link || true
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deploy tamamlandi."
