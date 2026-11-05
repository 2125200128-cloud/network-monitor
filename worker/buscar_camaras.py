"""
worker/buscar_camaras.py — Busca cámaras de seguridad en las tablas ARP de los equipos de red.

Cada equipo de capa 3 (core, routers, switches con interfaces de VLAN) sabe qué IP tiene cada MAC de las redes que
enruta. Los 3 primeros bytes de la MAC identifican al fabricante (registro OUI de la IEEE): las de fabricantes de
cámaras (Hikvision, Axis, Dahua, Hanwha…) se reportan, con una prueba de los puertos de video (RTSP 554) y web (80/443).
Solo lectura: no se toca ningún equipo ni cámara más allá de abrir y cerrar la conexión TCP.
La lectura de las tablas ARP es la de tablas_arp.py (la misma de la búsqueda de equipos nuevos).

Uso: NM_OBJETIVOS='[{"nombre":..,"ip":..,"comunidad":..}, ...]' python worker/buscar_camaras.py database/data/oui.csv
Salida (stdout): JSON {"equipos": [{nombre, ip, entradas, error}], "camaras": [{ip, mac, fabricante, visto_en, rtsp, http, https}]}
"""
import asyncio
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tablas_arp import cargar_oui, fabricante, leer_todas   # noqa: E402

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

CONCURRENCIA_PUERTOS = 64
ESPERA_PUERTO_S = 1.5

# Fabricantes de cámaras de video vigilancia (y de sus grabadoras NVR/DVR) según el nombre registrado en la IEEE
CAMARAS = re.compile(
    r'hikvision|ezviz|dahua|imou|axis communications|hanwha|samsung techwin|bosch security|avigilon|uniview|'
    r'vivotek|pelco|mobotix|milesight|i-pro|panasonic.*(security|system networks)|tiandy|geovision|'
    r'digital watchdog|vicon industries|flir|lorex|reolink|amcrest|annke|provision-isr|ganz|everfocus|'
    r'honeywell.*(security|video)|sony.*(imaging|visual)|hangzhou.*(hik|ezviz)', re.I)


async def puerto_abierto(sem, ip, puerto):
    async with sem:
        try:
            _, escritor = await asyncio.wait_for(asyncio.open_connection(ip, puerto), ESPERA_PUERTO_S)
            escritor.close()
            return True
        except Exception:
            return False


async def main():
    oui = cargar_oui(sys.argv[1])
    objetivos = json.loads(os.environ.get('NM_OBJETIVOS') or '[]')

    equipos, camaras = [], {}
    for o, filas, error in await leer_todas(objetivos):
        equipos.append({'nombre': o['nombre'], 'ip': o['ip'], 'entradas': len(filas), 'error': error if not filas else None})
        for ip, mac in filas:
            marca = fabricante(oui, mac)
            # La misma MAC puede verse en varios equipos (core primario y secundario): basta una vez
            if marca and CAMARAS.search(marca) and mac not in camaras:
                camaras[mac] = {'ip': ip, 'mac': mac, 'fabricante': marca, 'visto_en': o['nombre'], 'visto_en_ip': o['ip']}

    sem_p = asyncio.Semaphore(CONCURRENCIA_PUERTOS)
    lista = list(camaras.values())
    pruebas = await asyncio.gather(*(puerto_abierto(sem_p, c['ip'], p) for c in lista for p in (554, 80, 443)))
    for i, c in enumerate(lista):
        c['rtsp'], c['http'], c['https'] = pruebas[i * 3:i * 3 + 3]

    print(json.dumps({'equipos': equipos, 'camaras': lista}, ensure_ascii=False))


if __name__ == '__main__':
    asyncio.run(main())
