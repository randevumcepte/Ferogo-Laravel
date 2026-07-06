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
git pull origin main --quiet

# Bekleyen migration'lari uygula (yalniz bekleyen varsa mesaj ureti)
"$PHP_BIN" artisan migrate --force

# Filament / Blade / config / route / view cache'lerini toplu temizle
"$PHP_BIN" artisan optimize:clear

# Storage sembolik linki yoksa olustur (public/storage → storage/app/public)
if [ ! -L public/storage ] && [ ! -d public/storage ]; then
    "$PHP_BIN" artisan storage:link || true
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deploy tamamlandi."
