"""
worker/buscar_camaras.py — Busca cámaras de seguridad en las tablas ARP de los equipos de red.

Cada equipo de capa 3 (core, routers, switches con interfaces de VLAN) sabe qué IP tiene cada MAC de las redes que
enruta. Los 3 primeros bytes de la MAC identifican al fabricante (registro OUI de la IEEE): las de fabricantes de
cámaras (Hikvision, Axis, Dahua, Hanwha…) se reportan, con una prueba de los puertos de video (RTSP 554) y web (80/443).
Solo lectura: no se toca ningún equipo ni cámara más allá de abrir y cerrar la conexión TCP.

Uso: NM_OBJETIVOS='[{"nombre":..,"ip":..,"comunidad":..}, ...]' python worker/buscar_camaras.py database/data/oui_camaras.csv
     (oui_camaras.csv: prefijos de fabricantes de cámaras extraídos del oui.csv oficial de la IEEE con el patrón CAMARAS)
Salida (stdout): JSON {"equipos": [{nombre, ip, entradas, error}], "camaras": [{ip, mac, fabricante, visto_en, rtsp, http, https}]}
"""
import asyncio
import csv
import json
import os
import re
import sys

from pysnmp.hlapi.v3arch.asyncio import (SnmpEngine, CommunityData, UdpTransportTarget, ContextData, ObjectType,
                                         ObjectIdentity, bulk_walk_cmd)

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

OID_ARP = '1.3.6.1.2.1.4.22.1.2'   # ipNetToMediaPhysAddress: índice ifIndex.a.b.c.d → MAC
CONCURRENCIA_SNMP = 24
CONCURRENCIA_PUERTOS = 64
ESPERA_PUERTO_S = 1.5

# Fabricantes de cámaras de video vigilancia (y de sus grabadoras NVR/DVR) según el nombre registrado en la IEEE
CAMARAS = re.compile(
    r'hikvision|ezviz|dahua|imou|axis communications|hanwha|samsung techwin|bosch security|avigilon|uniview|'
    r'vivotek|pelco|mobotix|milesight|i-pro|panasonic.*(security|system networks)|tiandy|geovision|'
    r'digital watchdog|vicon industries|flir|lorex|reolink|amcrest|annke|provision-isr|ganz|everfocus|'
    r'honeywell.*(security|video)|sony.*(imaging|visual)|hangzhou.*(hik|ezviz)', re.I)


def cargar_oui(ruta):
    oui = {}
    with open(ruta, encoding='utf-8', errors='replace') as fh:
        for fila in csv.DictReader(fh):
            oui[fila['Assignment'].upper()] = fila['Organization Name'].strip()
    return oui


async def leer_arp(engine, sem, objetivo):
    filas, error = [], None
    async with sem:
        try:
            destino = await UdpTransportTarget.create((objetivo['ip'], 161), timeout=3, retries=1)
            async for ei, es, _, vbs in bulk_walk_cmd(engine, CommunityData(objetivo['comunidad'], mpModel=1), destino,
                                                      ContextData(), 0, 50, ObjectType(ObjectIdentity(OID_ARP)),
                                                      lexicographicMode=False, lookupMib=False):
                if ei or es:
                    error = str(ei or es.prettyPrint())
                    break
                for nombre, val in vbs:
                    partes = str(nombre)[len(OID_ARP) + 1:].split('.')
                    raw = val.asOctets() if hasattr(val, 'asOctets') else b''
                    if len(partes) >= 5 and len(raw) == 6:
                        filas.append(('.'.join(partes[1:5]), ':'.join(f'{b:02x}' for b in raw)))
        except Exception as e:
            error = str(e)
    return filas, error


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
    engine = SnmpEngine()
    sem = asyncio.Semaphore(CONCURRENCIA_SNMP)
    leidos = await asyncio.gather(*(leer_arp(engine, sem, o) for o in objetivos))

    equipos, camaras = [], {}
    for o, (filas, error) in zip(objetivos, leidos):
        equipos.append({'nombre': o['nombre'], 'ip': o['ip'], 'entradas': len(filas), 'error': error if not filas else None})
        for ip, mac in filas:
            fabricante = oui.get(mac.replace(':', '')[:6].upper())
            # La misma MAC puede verse en varios equipos (core primario y secundario): basta una vez
            if fabricante and CAMARAS.search(fabricante) and mac not in camaras:
                camaras[mac] = {'ip': ip, 'mac': mac, 'fabricante': fabricante, 'visto_en': o['nombre'], 'visto_en_ip': o['ip']}

    sem_p = asyncio.Semaphore(CONCURRENCIA_PUERTOS)
    lista = list(camaras.values())
    pruebas = await asyncio.gather(*(puerto_abierto(sem_p, c['ip'], p) for c in lista for p in (554, 80, 443)))
    for i, c in enumerate(lista):
        c['rtsp'], c['http'], c['https'] = pruebas[i * 3:i * 3 + 3]

    print(json.dumps({'equipos': equipos, 'camaras': lista}, ensure_ascii=False))


if __name__ == '__main__':
    asyncio.run(main())
