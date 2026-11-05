#!/usr/bin/env bash
# =============================================================================
#  go2rtc (video en vivo de las cámaras): instala la versión fijada y su configuración.
#  Lo llaman instalar.sh y actualizar.sh; se puede correr solo: sudo bash deploy/go2rtc.sh
#  - El binario se descarga de GitHub y se verifica contra su SHA-256 (si no coincide, no se instala).
#  - La configuración queda en /etc/network-monitor/go2rtc.yaml: el servicio (nm-go2rtc, ProtectSystem=strict)
#    no puede escribirla, así go2rtc nunca guarda en disco las credenciales de las cámaras que se le registran.
# =============================================================================
set -euo pipefail

VERSION="v1.9.14"
SHA256="32d616af226bd731678ffde328b94cfb94e30339bfefc469cfb76323144615a6"
BIN=/usr/local/bin/go2rtc
APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

[[ $EUID -eq 0 ]] || { echo "Ejecuta con sudo"; exit 1; }

if [[ -x "$BIN" ]] && [[ "$(sha256sum "$BIN" | cut -d' ' -f1)" == "$SHA256" ]]; then
    echo "go2rtc ${VERSION} ya instalado"
else
    TMP="$(mktemp)"
    curl -fsSL -o "$TMP" "https://github.com/AlexxIT/go2rtc/releases/download/${VERSION}/go2rtc_linux_amd64"
    if [[ "$(sha256sum "$TMP" | cut -d' ' -f1)" != "$SHA256" ]]; then
        rm -f "$TMP"
        echo "[!] La descarga de go2rtc no coincide con su SHA-256: no se instala"
        exit 1
    fi
    install -m 0755 "$TMP" "$BIN"
    rm -f "$TMP"
    echo "go2rtc ${VERSION} instalado en ${BIN}"
fi

install -d -m 0755 /etc/network-monitor
install -m 0644 -o root -g root "$APP_DIR/deploy/go2rtc.yaml" /etc/network-monitor/go2rtc.yaml
