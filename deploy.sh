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
"$PHP_BIN" artisan optimize:clear
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deploy tamamlandi."
