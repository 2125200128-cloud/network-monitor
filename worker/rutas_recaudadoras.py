"""
worker/rutas_recaudadoras.py — Por dónde llega cada recaudadora: los saltos (traceroute) desde el servidor del NOC
hasta el FortiGate de cada sede. Con ellos el mapa de Topología dibuja el camino real hasta el core.
Solo lectura: en Ubuntu usa tracepath (no necesita permisos de administrador), si no hay, traceroute; en Windows tracert.

Entrada (stdin): JSON [{"id": 12, "ip": "10.4.28.1"}, ...]
Salida (stdout): JSON [{"id": 12, "saltos": ["10.4.25.3", "10.4.240.2", null, "10.4.28.1"], "llego": true}, ...]
                 (null = salto que no contestó; "saltos" vacío si no se pudo medir)
"""
import json
import re
import shutil
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor

HILOS = 24
MAX_SALTOS = 12
IP = re.compile(r'(?<![\d.])(\d{1,3}(?:\.\d{1,3}){3})(?![\d.])')
# Inicio de cada línea de salto: « 1:  10.4.25.3 …» (tracepath), « 1  <1 ms … 10.4.25.3» (tracert/traceroute)
SALTO = re.compile(r'^\s*(\d+)\??:?\s+(.*)$')


def comando(ip):
    if sys.platform == 'win32':
        return ['tracert', '-d', '-h', str(MAX_SALTOS), '-w', '800', ip]
    if shutil.which('tracepath'):
        return ['tracepath', '-n', '-m', str(MAX_SALTOS), ip]
    return ['traceroute', '-n', '-q', '1', '-w', '1', '-m', str(MAX_SALTOS), ip]


def ruta(objetivo):
    ip = objetivo['ip']
    try:
        salida = subprocess.run(comando(ip), capture_output=True, text=True, timeout=120, errors='replace').stdout
    except Exception:
        return {'id': objetivo['id'], 'saltos': [], 'llego': False}
    saltos = {}
    for linea in salida.splitlines():
        m = SALTO.match(linea)
        if not m or 'LOCALHOST' in linea.upper():
            continue
        n = int(m.group(1))
        # La primera IP de la línea es la del salto (tracepath repite saltos con «pmtu»: basta la primera respuesta)
        encontrada = IP.search(m.group(2))
        if encontrada and not saltos.get(n):
            saltos[n] = encontrada.group(1)
        else:
            saltos.setdefault(n, None)
    lista = [saltos.get(i) for i in range(1, max(saltos) + 1)] if saltos else []
    # Se recorta lo que sigue después de llegar
    if ip in lista:
        lista = lista[:lista.index(ip) + 1]
    return {'id': objetivo['id'], 'saltos': lista, 'llego': bool(lista) and lista[-1] == ip}


def main():
    objetivos = json.load(sys.stdin)
    with ThreadPoolExecutor(max_workers=max(1, min(HILOS, len(objetivos)))) as ex:
        print(json.dumps(list(ex.map(ruta, objetivos))))


if __name__ == '__main__':
    main()
