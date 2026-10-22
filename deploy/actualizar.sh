#!/usr/bin/env bash
# =============================================================================
#  Actualiza el servidor a la última versión del repositorio (rama actual).
#  Uso:  sudo bash deploy/actualizar.sh
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
USUARIO="${USUARIO:-www-data}"
DUENO="${SUDO_USER:-root}"
PHP_V="${PHP_V:-8.3}"

[[ $EUID -eq 0 ]] || { echo "Ejecuta con sudo"; exit 1; }
cd "$APP_DIR"

echo "==> Código"
sudo -u "$DUENO" git pull --ff-only

echo "==> Dependencias"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --no-audit --no-fund --loglevel=error
npm run build
.venv/bin/pip install -q -r worker/requirements.txt

echo "==> Base de datos y cachés"
chown -R "$DUENO:$USUARIO" "$APP_DIR"
chown -R "$USUARIO:$USUARIO" storage bootstrap/cache public/images/topology/modelos
chmod 640 .env
sudo -u "$USUARIO" php artisan migrate --force
sudo -u "$USUARIO" php artisan optimize:clear >/dev/null
sudo -u "$USUARIO" php artisan optimize

echo "==> Reinicio de servicios"
# Los servicios systemd pudieron cambiar en el repositorio
for f in deploy/systemd/*; do
    sed -e "s#@APP_DIR@#${APP_DIR}#g" -e "s#@USUARIO@#${USUARIO}#g" "$f" > "/etc/systemd/system/$(basename "$f")"
done
systemctl daemon-reload
systemctl restart "php${PHP_V}-fpm" nm-poller.service nm-detalle.service nm-clasificador.service
systemctl reload nginx
echo "Listo: $(git log -1 --format='%h %s')"
