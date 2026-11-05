"""
worker/revisar_camaras.py — ¿Responde cada cámara? Primero ping (la misma función del sondeo, icmp_probe); si no
contesta, una conexión TCP a su puerto de video (554) o web (80/443) por si bloquea el ping. Solo lectura: no pide
video ni usa credenciales.

Entrada (stdin): JSON [{"id": 1, "ip": "10.4.9.20", "puertos": [554, 80]}, ...]
Salida (stdout): JSON [{"id": 1, "ok": true, "ms": 3.2, "por": "ping"}, ...]
"""
import asyncio
import json
import os
import sys
import time
from concurrent.futures import ThreadPoolExecutor

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from snmp_poller import icmp_probe   # noqa: E402

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

PINGS = 2
HILOS_PING = 64
ESPERA_TCP_S = 2.0


async def por_puerto(camara):
    for puerto in camara['puertos']:
        inicio = time.perf_counter()
        try:
            _, escritor = await asyncio.wait_for(asyncio.open_connection(camara['ip'], puerto), ESPERA_TCP_S)
            escritor.close()
            return {'id': camara['id'], 'ok': True, 'ms': round((time.perf_counter() - inicio) * 1000, 1), 'por': f'tcp/{puerto}'}
        except Exception:
            continue
    return {'id': camara['id'], 'ok': False, 'ms': None, 'por': None}


async def main():
    camaras = json.load(sys.stdin)
    with ThreadPoolExecutor(max_workers=max(1, min(HILOS_PING, len(camaras)))) as ex:
        pings = list(ex.map(lambda c: icmp_probe(c['ip'], PINGS), camaras))

    resultados, sin_ping = [], []
    for c, (_, recibidos, ms) in zip(camaras, pings):
        if recibidos:
            resultados.append({'id': c['id'], 'ok': True, 'ms': ms, 'por': 'ping'})
        else:
            sin_ping.append(c)
    resultados += await asyncio.gather(*(por_puerto(c) for c in sin_ping))
    print(json.dumps(resultados))


if __name__ == '__main__':
    asyncio.run(main())
