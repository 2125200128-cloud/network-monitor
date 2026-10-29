#!/usr/bin/env bash
# =============================================================================
#  Network Monitor NOC — instalación en Ubuntu 24.04 LTS
#  Se ejecuta desde la carpeta del proyecto ya clonada; se puede repetir sin romper nada.
#
#  Uso:
#    sudo APP_KEY_ORIGEN='base64:...' DOMINIO=noc.ejemplo.gob.mx bash deploy/instalar.sh
#
#  Variables (todas opcionales salvo APP_KEY_ORIGEN en la primera instalación con datos existentes):
#    APP_KEY_ORIGEN   APP_KEY del servidor actual (ver LEEME.md). Sin ella no se leen los datos cifrados.
#    NUEVA_INSTALACION=1  Instalación desde cero (sin datos previos): genera una APP_KEY nueva.
#    DOMINIO          Nombre o IP con el que se abrirá la web (por defecto, la IP del servidor).
#    USUARIO          Usuario que ejecuta PHP y los workers (por defecto www-data).
#    PHP_V / PG_V / NODE_V   Versiones (8.3 / 18 / 22).
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
USUARIO="${USUARIO:-www-data}"
DUENO="${SUDO_USER:-root}"                 # quien actualiza el código (git pull)
PHP_V="${PHP_V:-8.3}"
PG_V="${PG_V:-18}"
ZONA="${ZONA:-America/Mexico_City}"           # la de config/app.php: Laravel y los workers deben ver la misma hora
NODE_V="${NODE_V:-22}"
DB_NOMBRE=network_monitor
DB_USUARIO=noc_monitor
IP_SERVIDOR="$(hostname -I | awk '{print $1}')"
DOMINIO="${DOMINIO:-$IP_SERVIDOR}"
ENV_FILE="$APP_DIR/.env"

paso()  { echo -e "\n\033[1;34m==> $*\033[0m"; }
aviso() { echo -e "\033[1;33m[!] $*\033[0m"; }
error() { echo -e "\033[1;31m[x] $*\033[0m" >&2; exit 1; }
pg()    { (cd /tmp && sudo -u postgres "$@"); }

[[ $EUID -eq 0 ]] || error "Ejecuta con sudo: sudo bash deploy/instalar.sh"
[[ -f "$APP_DIR/artisan" ]] || error "No encuentro artisan en $APP_DIR"
. /etc/os-release
[[ "${ID:-}" == "ubuntu" ]] || aviso "Probado en Ubuntu 24.04; este sistema es ${PRETTY_NAME:-desconocido}"

# La APP_KEY se valida antes de instalar nada
APP_KEY_ACTUAL="$( [[ -f "$ENV_FILE" ]] && grep -E '^APP_KEY=' "$ENV_FILE" | cut -d= -f2- | tr -d '"' || true )"
if [[ -z "$APP_KEY_ACTUAL" && -z "${APP_KEY_ORIGEN:-}" && "${NUEVA_INSTALACION:-0}" != "1" ]]; then
    error "Falta APP_KEY_ORIGEN (la APP_KEY del servidor actual, ver deploy/LEEME.md).
    Sin ella no se podrán leer el token de Telegram, las contraseñas SNMPv3 ni las credenciales SSH.
    Si es una instalación desde cero sin datos previos, usa NUEVA_INSTALACION=1."
fi

# -----------------------------------------------------------------------------
paso "Zona horaria ${ZONA}"
# Ubuntu Server se instala en UTC. Los workers guardan NOW() de PostgreSQL y Laravel compara en hora de México:
# con zonas distintas cada medición quedaría 6 h adelantada.
timedatectl set-timezone "$ZONA"

paso "Paquetes del sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git curl unzip ca-certificates gnupg lsb-release openssl \
    nginx iputils-ping iproute2 snmp fonts-dejavu-core \
    python3 python3-venv python3-pip \
    "php${PHP_V}-fpm" "php${PHP_V}-cli" "php${PHP_V}-pgsql" "php${PHP_V}-gd" "php${PHP_V}-curl" \
    "php${PHP_V}-mbstring" "php${PHP_V}-xml" "php${PHP_V}-zip" "php${PHP_V}-intl" "php${PHP_V}-bcmath" composer

paso "Parches de seguridad automáticos"
# Ubuntu publica las correcciones (PHP, nginx, OpenSSL...) en noble-security; se instalan solas cada día
apt-get install -y -qq unattended-upgrades
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
# PHP2Shell (CVE-2026-109618/109619): Ubuntu corrige sin cambiar el número de versión; se confirma en el changelog
zgrep -qs 'CVE-2026-109618' /usr/share/doc/php${PHP_V}-*/changelog.Debian.gz \
    || aviso "PHP ${PHP_V} todavía sin el parche de PHP2Shell (CVE-2026-109618). En cuanto Ubuntu lo publique: sudo apt update && sudo apt upgrade"

paso "PostgreSQL ${PG_V} (repositorio oficial de PostgreSQL)"
if ! dpkg -s "postgresql-${PG_V}" >/dev/null 2>&1; then
    apt-get install -y -qq postgresql-common
    /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
    apt-get install -y -qq "postgresql-${PG_V}"
fi
systemctl enable --now postgresql

paso "Node.js ${NODE_V} (solo para compilar los estilos)"
if ! command -v node >/dev/null || (( $(node -v | sed 's/^v\([0-9]*\).*/\1/') < 20 )); then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_V}.x" | bash -
    apt-get install -y -qq nodejs
fi

# -----------------------------------------------------------------------------
paso "Base de datos ${DB_NOMBRE}"
DB_PASSWORD="$( [[ -f "$ENV_FILE" ]] && grep -E '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2- | tr -d '"' || true )"
if [[ -z "$DB_PASSWORD" || "$DB_PASSWORD" == "@DB_PASSWORD@" ]]; then
    DB_PASSWORD="$(openssl rand -base64 36 | tr -dc 'A-Za-z0-9' | head -c 32)"
fi
if pg psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USUARIO}'" | grep -q 1; then
    pg psql -q -c "ALTER ROLE ${DB_USUARIO} WITH LOGIN PASSWORD '${DB_PASSWORD}'"
else
    pg psql -q -c "CREATE ROLE ${DB_USUARIO} WITH LOGIN PASSWORD '${DB_PASSWORD}'"
fi
if ! pg psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NOMBRE}'" | grep -q 1; then
    pg createdb -O "${DB_USUARIO}" -E UTF8 -T template0 "${DB_NOMBRE}"
fi
pg psql -q -d "${DB_NOMBRE}" -c "ALTER SCHEMA public OWNER TO ${DB_USUARIO}"
# La base usa la zona de la aplicación aunque PostgreSQL se haya inicializado en UTC
pg psql -q -c "ALTER DATABASE ${DB_NOMBRE} SET timezone TO '${ZONA}'"

# -----------------------------------------------------------------------------
paso ".env de producción"
if [[ ! -f "$ENV_FILE" ]]; then
    sed -e "s#@DOMINIO@#${DOMINIO}#g" -e "s#@DB_PASSWORD@#${DB_PASSWORD}#g" -e "s#@APP_DIR@#${APP_DIR}#g" \
        "$APP_DIR/deploy/env.produccion.example" > "$ENV_FILE"
else
    sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=${DB_PASSWORD}#" "$ENV_FILE"
fi
if [[ -n "${APP_KEY_ORIGEN:-}" ]]; then
    sed -i "s#^APP_KEY=.*#APP_KEY=${APP_KEY_ORIGEN}#" "$ENV_FILE"
fi
chown "$DUENO:$USUARIO" "$ENV_FILE"
chmod 640 "$ENV_FILE"

# -----------------------------------------------------------------------------
paso "Dependencias de PHP, estilos y entorno de Python"
cd "$APP_DIR"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --no-audit --no-fund --loglevel=error
npm run build
python3 -m venv .venv
.venv/bin/pip install -q --upgrade pip
.venv/bin/pip install -q -r worker/requirements.txt

if [[ -z "$(grep -E '^APP_KEY=.+' "$ENV_FILE" || true)" ]]; then
    aviso "Instalación nueva: se genera una APP_KEY nueva."
    php artisan key:generate --force
fi

# -----------------------------------------------------------------------------
paso "Permisos"
chown -R "$DUENO:$USUARIO" "$APP_DIR"
chown -R "$USUARIO:$USUARIO" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
# Ilustraciones de modelos que la app genera al vuelo
mkdir -p "$APP_DIR/public/images/topology/modelos"
chown -R "$USUARIO:$USUARIO" "$APP_DIR/public/images/topology/modelos"
chown "$DUENO:$USUARIO" "$ENV_FILE"; chmod 640 "$ENV_FILE"
chmod +x "$APP_DIR/deploy/"*.sh

# -----------------------------------------------------------------------------
paso "Migraciones y cachés de Laravel"
sudo -u "$USUARIO" php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true
sudo -u "$USUARIO" php artisan optimize

# -----------------------------------------------------------------------------
paso "PHP-FPM ${PHP_V}"
cat > "/etc/php/${PHP_V}/fpm/conf.d/99-network-monitor.ini" <<EOF
memory_limit = 512M
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 180
expose_php = Off
date.timezone = America/Mexico_City
; Mitigación de PHP2Shell (CVE-2026-109618/109619); el monitor no usa el progreso de subida
session.upload_progress.enabled = Off
EOF
cp "/etc/php/${PHP_V}/fpm/conf.d/99-network-monitor.ini" "/etc/php/${PHP_V}/cli/conf.d/99-network-monitor.ini"
# Varias TV y usuarios a la vez con refresco cada 30 s
sed -i 's/^pm.max_children = .*/pm.max_children = 24/' "/etc/php/${PHP_V}/fpm/pool.d/www.conf"
systemctl restart "php${PHP_V}-fpm"

paso "Nginx"
sed -e "s#@APP_DIR@#${APP_DIR}#g" -e "s#@DOMINIO@#${DOMINIO}#g" -e "s#@PHP_SOCK@#/run/php/php${PHP_V}-fpm.sock#g" \
    "$APP_DIR/deploy/nginx.conf" > /etc/nginx/sites-available/network-monitor
ln -sf ../sites-available/network-monitor /etc/nginx/sites-enabled/network-monitor
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx
systemctl reload nginx

# -----------------------------------------------------------------------------
paso "Servicios (workers, tareas periódicas y respaldo diario)"
for f in "$APP_DIR"/deploy/systemd/*; do
    sed -e "s#@APP_DIR@#${APP_DIR}#g" -e "s#@USUARIO@#${USUARIO}#g" "$f" > "/etc/systemd/system/$(basename "$f")"
done
mkdir -p /var/backups/network-monitor
chown postgres:postgres /var/backups/network-monitor
chmod 750 /var/backups/network-monitor
systemctl daemon-reload
systemctl enable --now nm-poller.service nm-detalle.service nm-clasificador.service nm-esxi.service \
    nm-noc-sync.timer nm-web-poll.timer nm-respaldo.timer
systemctl restart nm-poller.service nm-detalle.service nm-clasificador.service nm-esxi.service

if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    paso "Firewall (ufw)"
    ufw allow 'Nginx Full' >/dev/null
fi

# -----------------------------------------------------------------------------
paso "Listo"
cat <<EOF
  Web:            $([[ -f /etc/nginx/snippets/network-monitor-tls.conf ]] && echo https || echo http)://${DOMINIO}
  Proyecto:       ${APP_DIR}
  Base de datos:  ${DB_NOMBRE} (usuario ${DB_USUARIO}; contraseña en ${ENV_FILE})
  Respaldos:      /var/backups/network-monitor (diario 02:30, 14 días)

  Estado:   systemctl status 'nm-*'
  Bitácora: journalctl -u nm-poller -f      (también nm-detalle, nm-clasificador, nm-noc-sync, nm-web-poll)

  Siguiente paso si vienes de otro servidor:  sudo bash deploy/importar-datos.sh /ruta/network_monitor.dump
  Para quitar el aviso "No seguro" del navegador (HTTPS):  sudo bash deploy/https.sh
  Recuerda autorizar la IP de este servidor (${IP_SERVIDOR}) en el SNMP de los equipos (ACL / hosts permitidos).
EOF
