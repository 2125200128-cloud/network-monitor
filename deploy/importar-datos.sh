#!/usr/bin/env bash
# =============================================================================
#  Restaura en este servidor la base exportada del servidor anterior (exportar-datos-windows.ps1).
#  Reemplaza el contenido actual de la base network_monitor.
#
#  Uso:  sudo bash deploy/importar-datos.sh /ruta/network_monitor.dump
# =============================================================================
set -euo pipefail

ARCHIVO="${1:?Indica el archivo .dump exportado del servidor anterior}"
APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
USUARIO="${USUARIO:-www-data}"
SERVICIOS="nm-poller.service nm-detalle.service nm-clasificador.service nm-noc-sync.timer nm-web-poll.timer"

[[ $EUID -eq 0 ]] || { echo "Ejecuta con sudo"; exit 1; }
[[ -f "$ARCHIVO" ]] || { echo "No existe $ARCHIVO"; exit 1; }

echo "==> Deteniendo workers para que nadie escriba durante la restauración"
systemctl stop $SERVICIOS || true

echo "==> Restaurando $(du -h "$ARCHIVO" | cut -f1) en network_monitor"
TMP="$(mktemp /tmp/nm_import_XXXX.dump)"
cp "$ARCHIVO" "$TMP"
chown postgres "$TMP"
# --no-owner + --role: todo queda a nombre del usuario de la app; --clean reemplaza lo que hubiera
(cd /tmp && sudo -u postgres pg_restore --clean --if-exists --no-owner --role=noc_monitor \
    --dbname=network_monitor "$TMP") || echo "[!] pg_restore reportó avisos (normal con --clean en una base vacía)."
rm -f "$TMP"

echo "==> Migraciones pendientes y cachés"
cd "$APP_DIR"
sudo -u "$USUARIO" php artisan migrate --force
sudo -u "$USUARIO" php artisan optimize:clear >/dev/null
sudo -u "$USUARIO" php artisan optimize

echo "==> Arrancando workers"
systemctl start $SERVICIOS

sudo -u postgres psql -d network_monitor -tAc \
    "SELECT 'Equipos: ' || (SELECT count(*) FROM dispositivos) || ' · enlaces: ' || (SELECT count(*) FROM enlaces_red) || ' · alarmas: ' || (SELECT count(*) FROM alarmas_red)"
echo "Listo. Revisa la web y: journalctl -u nm-poller -f"
