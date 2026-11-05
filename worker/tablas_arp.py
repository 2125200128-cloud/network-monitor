"""
worker/tablas_arp.py — Lee las tablas ARP (IP ↔ MAC) de los equipos de red por SNMP y pone el fabricante de cada MAC
(registro OUI de la IEEE). Lo usan la búsqueda de cámaras (buscar_camaras.py) y la de equipos nuevos en la red.
Solo lectura.

Uso directo: NM_OBJETIVOS='[{"nombre":..,"ip":..,"comunidad":..}, ...]' python worker/tablas_arp.py database/data/oui.csv [red]
Salida (stdout): JSON {"equipos": [{nombre, ip, entradas, error}],
                       "macs": [{ip, mac, fabricante, aleatoria, visto_en, visto_en_ip}]}  (una vez cada MAC; solo la red dada)
"""
import asyncio
import csv
import ipaddress
import json
import os
import sys

from pysnmp.hlapi.v3arch.asyncio import (SnmpEngine, CommunityData, UdpTransportTarget, ContextData, ObjectType,
                                         ObjectIdentity, bulk_walk_cmd)

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

OID_ARP = '1.3.6.1.2.1.4.22.1.2'   # ipNetToMediaPhysAddress: índice ifIndex.a.b.c.d → MAC
CONCURRENCIA_SNMP = 24


def cargar_oui(ruta):
    oui = {}
    with open(ruta, encoding='utf-8', errors='replace') as fh:
        for fila in csv.DictReader(fh):
            oui[fila['Assignment'].upper()] = fila['Organization Name'].strip()
    return oui


def fabricante(oui, mac):
    return oui.get(mac.replace(':', '')[:6].upper())


def mac_aleatoria(mac):
    """MAC "administrada localmente" (bit 2 del primer byte): la que inventan celulares y laptops para no ser rastreados."""
    return bool(int(mac[:2], 16) & 0x02)


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
                    # Se descartan las MAC vacías o de difusión (entradas incompletas de la tabla)
                    if len(partes) >= 5 and len(raw) == 6 and raw not in (b'\x00' * 6, b'\xff' * 6):
                        filas.append(('.'.join(partes[1:5]), ':'.join(f'{b:02x}' for b in raw)))
        except Exception as e:
            error = str(e)
    return filas, error


async def leer_todas(objetivos):
    """[(objetivo, filas, error)] de todos los equipos, en paralelo."""
    engine = SnmpEngine()
    sem = asyncio.Semaphore(CONCURRENCIA_SNMP)
    leidos = await asyncio.gather(*(leer_arp(engine, sem, o) for o in objetivos))
    return [(o, filas, error) for o, (filas, error) in zip(objetivos, leidos)]


async def main():
    oui = cargar_oui(sys.argv[1])
    red = ipaddress.ip_network(sys.argv[2]) if len(sys.argv) > 2 else None
    objetivos = json.loads(os.environ.get('NM_OBJETIVOS') or '[]')
    equipos, macs = [], {}
    for o, filas, error in await leer_todas(objetivos):
        equipos.append({'nombre': o['nombre'], 'ip': o['ip'], 'entradas': len(filas), 'error': error if not filas else None})
        for ip, mac in filas:
            # La misma MAC puede verse en varios equipos (core primario y secundario): basta una vez
            if mac in macs or (red and ipaddress.ip_address(ip) not in red):
                continue
            macs[mac] = {'ip': ip, 'mac': mac, 'fabricante': fabricante(oui, mac), 'aleatoria': mac_aleatoria(mac),
                         'visto_en': o['nombre'], 'visto_en_ip': o['ip']}
    print(json.dumps({'equipos': equipos, 'macs': list(macs.values())}, ensure_ascii=False))


if __name__ == '__main__':
    asyncio.run(main())
