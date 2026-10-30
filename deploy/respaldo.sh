#!/usr/bin/env bash
# Respaldo diario de la base (formato custom de pg_dump). Lo ejecuta nm-respaldo.timer como usuario postgres.
# Conserva los últimos DIAS_RESPALDO días en /var/backups/network-monitor.
set -euo pipefail

DESTINO="${DESTINO_RESPALDO:-/var/backups/network-monitor}"
DIAS="${DIAS_RESPALDO:-14}"
BASE="${DB_RESPALDO:-network_monitor}"

mkdir -p "$DESTINO"
ARCHIVO="$DESTINO/${BASE}_$(date +%Y%m%d_%H%M).dump"
pg_dump --format=custom --compress=6 --file="$ARCHIVO" "$BASE"
find "$DESTINO" -name "${BASE}_*.dump" -mtime +"$DIAS" -delete
echo "Respaldo: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))"
