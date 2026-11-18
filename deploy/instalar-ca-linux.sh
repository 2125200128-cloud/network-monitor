#!/usr/bin/env bash
# =============================================================================
#  deploy/instalar-ca-linux.sh — Instala la CA del NOC en una PC Ubuntu (las de las pantallas) para que
#  https://10.4.150.194 se vea como sitio seguro, sin aviso. Se ejecuta UNA vez en cada PC:
#
#    sudo bash instalar-ca-linux.sh [servidor]        (servidor por omisión: 10.4.150.194)
#
#  Qué hace:
#    1. Descarga la CA del NOC (solo la parte pública) de https://servidor/noc-ca.crt y muestra su huella.
#    2. La agrega a la confianza del sistema (curl, wget y apps).
#    3. La agrega a Chrome / Chromium / Edge del usuario (usan su propio almacén NSS, también el de snap).
#    4. Firefox (deb o snap): política para que confíe en ella.
#  Se puede repetir sin problema. No envía nada a ningún lado.
# =============================================================================
set -euo pipefail

SERVIDOR="${1:-10.4.150.194}"
NOMBRE="NOC Network Monitor CA"
DESTINO=/usr/local/share/ca-certificates/noc-network-monitor-ca.crt
POLITICA=/etc/firefox/policies/policies.json

[[ $EUID -eq 0 ]] || { echo "Ejecuta con sudo: sudo bash $0 [servidor]" >&2; exit 1; }
USUARIO="${SUDO_USER:-root}"
CASA="$(getent passwd "$USUARIO" | cut -d: -f6)"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

echo "==> 1. CA del NOC desde https://$SERVIDOR/noc-ca.crt"
# La primera vez el HTTPS aún no se puede verificar (justo es lo que se instala): por eso se muestra la huella
if command -v curl >/dev/null; then
    curl -fsSk "https://$SERVIDOR/noc-ca.crt" -o "$TMP"
else
    wget --no-check-certificate -qO "$TMP" "https://$SERVIDOR/noc-ca.crt"
fi
openssl x509 -in "$TMP" -noout 2>/dev/null || { echo "Lo descargado no es un certificado: revisa el servidor" >&2; exit 1; }
echo "    Emitida para: $(openssl x509 -in "$TMP" -noout -subject | sed 's/^subject=//')"
echo "    Huella SHA-256: $(openssl x509 -in "$TMP" -noout -fingerprint -sha256 | cut -d= -f2)"
echo "    (debe coincidir con: sudo openssl x509 -in /etc/ssl/network-monitor/ca.crt -noout -fingerprint -sha256 en el servidor)"

echo "==> 2. Confianza del sistema"
install -m 644 "$TMP" "$DESTINO"
update-ca-certificates >/dev/null

echo "==> 3. Chrome / Chromium / Edge del usuario $USUARIO"
command -v certutil >/dev/null || { apt-get update -qq; apt-get install -y -qq libnss3-tools >/dev/null; }
for db in "$CASA/.pki/nssdb" "$CASA/snap/chromium/current/.pki/nssdb"; do
    [[ "$db" == *"/snap/chromium/"* && ! -d "$CASA/snap/chromium" ]] && continue
    sudo -u "$USUARIO" mkdir -p "$db"
    [[ -f "$db/cert9.db" ]] || sudo -u "$USUARIO" certutil -d "sql:$db" -N --empty-password
    sudo -u "$USUARIO" certutil -d "sql:$db" -D -n "$NOMBRE" 2>/dev/null || true   # si ya estaba, se reemplaza
    sudo -u "$USUARIO" certutil -d "sql:$db" -A -t "C,," -n "$NOMBRE" -i "$DESTINO"
    echo "    Agregada en $db"
done

echo "==> 4. Firefox"
if [[ -f "$POLITICA" ]] && ! grep -q "noc-network-monitor-ca" "$POLITICA"; then
    # No se pisa una política que ya existe: se indica qué agregarle
    echo "    Ya hay una política de Firefox en $POLITICA: agrégale dentro de \"policies\":"
    echo "      \"Certificates\": { \"ImportEnterpriseRoots\": true, \"Install\": [\"$DESTINO\"] }"
else
    mkdir -p "$(dirname "$POLITICA")"
    cat > "$POLITICA" <<EOF
{
  "policies": {
    "Certificates": {
      "ImportEnterpriseRoots": true,
      "Install": ["$DESTINO"]
    }
  }
}
EOF
    echo "    Política instalada en $POLITICA"
fi

echo
echo "Listo. Cierra el navegador por completo y vuelve a abrir https://$SERVIDOR: debe mostrar el candado sin aviso."
