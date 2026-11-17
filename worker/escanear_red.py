"""
worker/escanear_red.py — ¿Qué equipos contestan en la red interna de una recaudadora? Para encontrar kioscos, PCs,
impresoras y antenas sin tener que conocer su IP. Solo lectura y a bajo ritmo, para que el FortiGate no lo tome por
un ataque: primero un ping a cada IP del segmento (como el monitoreo de cada minuto) y solo a los que contestan se
les prueba un puñado de puertos para adivinar qué son; también su nombre en el DNS, si tiene.

Entrada (stdin): JSON {"ips": ["10.4.28.2", ...]}
Salida (stdout): JSON [{"ip": "10.4.28.20", "ms": 3.2, "puertos": [445, 3389], "nombre": "KIOSCO-01"}, ...]
"""
import asyncio
import json
import os
import socket
import sys
import time
from concurrent.futures import ThreadPoolExecutor

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from snmp_poller import icmp_probe   # noqa: E402

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

HILOS_PING = 32
# SSH, telnet, web, RPC/SMB de Windows, cámara (RTSP), escritorio remoto, web alterno, impresora (JetDirect/IPP)
PUERTOS = [22, 23, 80, 135, 443, 445, 554, 631, 3389, 8080, 9100]
CONEXIONES_A_LA_VEZ = 16
ESPERA_TCP_S = 1.5
ESPERA_DNS_S = 2.0


async def puertos_abiertos(sem, ip):
    async def uno(puerto):
        async with sem:
            try:
                _, escritor = await asyncio.wait_for(asyncio.open_connection(ip, puerto), ESPERA_TCP_S)
                escritor.close()
                return puerto
            except Exception:
                return None
    return [p for p in await asyncio.gather(*(uno(p) for p in PUERTOS)) if p]


def nombre_dns(ip):
    try:
        return socket.gethostbyaddr(ip)[0]
    except Exception:
        return None


async def main():
    ips = json.load(sys.stdin).get('ips', [])
    inicio = time.perf_counter()
    with ThreadPoolExecutor(max_workers=max(1, min(HILOS_PING, len(ips)))) as ex:
        pings = list(ex.map(lambda ip: icmp_probe(ip, 2), ips))
    vivos = [(ip, ms) for ip, (_, recibidos, ms) in zip(ips, pings) if recibidos]

    sem = asyncio.Semaphore(CONEXIONES_A_LA_VEZ)
    puertos = await asyncio.gather(*(puertos_abiertos(sem, ip) for ip, _ in vivos))

    # El DNS inverso puede tardar si no hay registro: se espera un máximo y se sigue sin nombre
    loop = asyncio.get_running_loop()
    ex = ThreadPoolExecutor(max_workers=16)
    tareas = [loop.run_in_executor(ex, nombre_dns, ip) for ip, _ in vivos]
    hecho, _ = await asyncio.wait(tareas, timeout=ESPERA_DNS_S) if tareas else (set(), set())
    nombres = [t.result() if t in hecho else None for t in tareas]

    print(json.dumps({
        'segundos': round(time.perf_counter() - inicio, 1),
        'equipos': [{'ip': ip, 'ms': ms, 'puertos': p, 'nombre': n} for (ip, ms), p, n in zip(vivos, puertos, nombres)],
    }), flush=True)
    # Una consulta DNS colgada no se puede cancelar: se sale sin esperarla
    os._exit(0)


if __name__ == '__main__':
    asyncio.run(main())
