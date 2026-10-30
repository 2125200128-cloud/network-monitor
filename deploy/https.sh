#!/usr/bin/env bash
# =============================================================================
#  Network Monitor NOC — HTTPS en Nginx (quita el aviso "No seguro" del navegador)
#  Se puede repetir: vuelve a emitir el certificado del servidor y deja la misma CA.
#
#  Uso:
#    sudo bash deploy/https.sh
#        Red interna sin CA institucional: crea una autoridad certificadora (CA) propia del NOC y firma
#        con ella el certificado del servidor. Después se instala ca.crt UNA vez en cada PC (o por GPO).
#    sudo CERT=/ruta/servidor.crt LLAVE=/ruta/servidor.key bash deploy/https.sh
#        Certificado emitido por la CA institucional o comprado: se usa tal cual.
#
#  Variables opcionales:
#    NOMBRES   Nombres e IPs con los que se abrirá la web, separados por espacios
#              (por defecto: el host de APP_URL y la IP del servidor). El primero queda en APP_URL.
#    DIAS      Vigencia del certificado del servidor firmado por la CA propia (por defecto 825).
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
USUARIO="${USUARIO:-www-data}"
ENV_FILE="$APP_DIR/.env"
DIR=/etc/ssl/network-monitor
SITIO=/etc/nginx/sites-available/network-monitor
SNIPPET=/etc/nginx/snippets/network-monitor-tls.conf
DIAS="${DIAS:-825}"
IP_SERVIDOR="$(hostname -I | awk '{print $1}')"

paso()  { echo -e "\n\033[1;34m==> $*\033[0m"; }
error() { echo -e "\033[1;31m[x] $*\033[0m" >&2; exit 1; }

[[ $EUID -eq 0 ]] || error "Ejecuta con sudo: sudo bash deploy/https.sh"
[[ -f "$ENV_FILE" && -f "$SITIO" ]] || error "Primero ejecuta deploy/instalar.sh"

HOST_APP="$(grep -E '^APP_URL=' "$ENV_FILE" | cut -d= -f2- | tr -d '"' | sed -E 's#^https?://##; s#[:/].*$##')"
NOMBRES="${NOMBRES:-$(printf '%s\n' $HOST_APP $IP_SERVIDOR | awk 'NF && !visto[$0]++' | xargs)}"
PRINCIPAL="${NOMBRES%% *}"
mkdir -p "$DIR"
chmod 755 "$DIR"

if [[ -n "${CERT:-}" || -n "${LLAVE:-}" ]]; then
    [[ -f "${CERT:-}" && -f "${LLAVE:-}" ]] || error "CERT y LLAVE deben apuntar a archivos existentes"
    paso "Certificado proporcionado"
    [[ "$(openssl x509 -noout -pubkey -in "$CERT")" == "$(openssl pkey -pubout -in "$LLAVE")" ]] \
        || error "La llave no corresponde al certificado"
    install -m 644 "$CERT" "$DIR/servidor.crt"
    install -m 600 "$LLAVE" "$DIR/servidor.key"
    CA_PROPIA=0
else
    CA_PROPIA=1
    if [[ ! -f "$DIR/ca.key" ]]; then
        paso "Autoridad certificadora del NOC (se crea una sola vez, vale 10 años)"
        openssl req -x509 -newkey rsa:3072 -nodes -days 3650 -sha256 \
            -keyout "$DIR/ca.key" -out "$DIR/ca.crt" \
            -subj "/O=NOC Network Monitor/CN=NOC Network Monitor CA" \
            -addext "basicConstraints=critical,CA:TRUE,pathlen:0" \
            -addext "keyUsage=critical,keyCertSign,cRLSign"
        chmod 600 "$DIR/ca.key"
        chmod 644 "$DIR/ca.crt"
    fi

    paso "Certificado del servidor para: $NOMBRES"
    # Los navegadores solo miran subjectAltName: cada nombre e IP con la que se abra la web debe estar aquí
    SAN=""
    for n in $NOMBRES; do
        if [[ $n =~ ^[0-9.]+$ || $n == *:* ]]; then SAN+="IP:$n,"; else SAN+="DNS:$n,"; fi
    done
    openssl req -newkey rsa:2048 -nodes -sha256 -subj "/CN=$PRINCIPAL" \
        -keyout "$DIR/servidor.key.nuevo" -out "$DIR/servidor.csr" 2>/dev/null
    openssl x509 -req -in "$DIR/servidor.csr" -CA "$DIR/ca.crt" -CAkey "$DIR/ca.key" -CAcreateserial \
        -days "$DIAS" -sha256 -out "$DIR/servidor.crt.nuevo" -extfile <(printf '%s\n' \
            "subjectAltName=${SAN%,}" \
            "basicConstraints=CA:FALSE" \
            "keyUsage=critical,digitalSignature,keyEncipherment" \
            "extendedKeyUsage=serverAuth")
    chmod 600 "$DIR/servidor.key.nuevo"
    mv "$DIR/servidor.key.nuevo" "$DIR/servidor.key"
    mv "$DIR/servidor.crt.nuevo" "$DIR/servidor.crt"
    rm -f "$DIR/servidor.csr"
fi

# -----------------------------------------------------------------------------
paso "Nginx"
{
    echo "# Generado por deploy/https.sh; lo incluye el sitio network-monitor"
    echo "listen 443 ssl http2;"
    echo "listen [::]:443 ssl http2;"
    echo "ssl_certificate     $DIR/servidor.crt;"
    echo "ssl_certificate_key $DIR/servidor.key;"
    echo "ssl_protocols TLSv1.2 TLSv1.3;"
    echo "ssl_session_cache shared:nm_tls:10m;"
    echo "ssl_session_timeout 1d;"
    echo "# Lo que llegue por http se manda a https"
    echo "if (\$scheme = http) { return 301 https://\$host\$request_uri; }"
    if [[ $CA_PROPIA == 1 ]]; then
        echo "# La CA del NOC (solo la parte pública) para instalarla en las PCs"
        echo "location = /noc-ca.crt { alias $DIR/ca.crt; default_type application/x-x509-ca-cert; }"
    fi
} > "$SNIPPET"
# Sitios instalados antes de que nginx.conf trajera el include
grep -q 'network-monitor-tl' "$SITIO" \
    || sed -i "0,/server_name .*;/s##&\n    include /etc/nginx/snippets/network-monitor-tl[s].conf;#" "$SITIO"
nginx -t
systemctl reload nginx
if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow 'Nginx Full' >/dev/null
fi

paso "Laravel"
sed -i "s#^APP_URL=.*#APP_URL=https://${PRINCIPAL}#" "$ENV_FILE"
if grep -q '^SESSION_SECURE_COOKIE=' "$ENV_FILE"; then
    sed -i 's#^SESSION_SECURE_COOKIE=.*#SESSION_SECURE_COOKIE=true#' "$ENV_FILE"
else
    echo 'SESSION_SECURE_COOKIE=true' >> "$ENV_FILE"
fi
cd "$APP_DIR"
sudo -u "$USUARIO" php artisan optimize >/dev/null

# -----------------------------------------------------------------------------
paso "Listo"
echo "  Web: https://${PRINCIPAL}   (http redirige a https)"
echo "  Vigente hasta: $(openssl x509 -noout -enddate -in "$DIR/servidor.crt" | cut -d= -f2)"
if [[ $CA_PROPIA == 1 ]]; then
    cat <<EOF

  Para que el navegador confíe, instala la CA del NOC UNA vez en cada PC:
    1. Descárgala de https://${PRINCIPAL}/noc-ca.crt  (esa primera vez acepta el aviso: Avanzado → Continuar)
    2. En PowerShell como administrador, en la carpeta de Descargas:
         Import-Certificate -FilePath .\\noc-ca.crt -CertStoreLocation Cert:\\LocalMachine\\Root
    3. Cierra y abre el navegador.
  Para todas las PCs de la institución: pide que la distribuyan por GPO (Entidades de certificación raíz de confianza).
  Si cambias el nombre o la IP del servidor, vuelve a ejecutar este script con NOMBRES="nombre ip"; la CA no cambia.
EOF
fi
