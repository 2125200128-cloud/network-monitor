"""
worker/snmp_poller.py — Sondeo en vivo (cada 10 s) de todos los dispositivos.

Principio: solo se guarda lo que el equipo realmente reporta. Si un dato no se pudo
obtener (OID no soportado, SNMP sin respuesta, primer ciclo sin delta de contadores),
se guarda NULL y la interfaz lo muestra como "Sin datos", nunca un valor inventado.

Decodificar SNMP en Python consume CPU de un solo núcleo; por eso los equipos se reparten
entre varios procesos (SHARDS), cada uno con su propio ciclo asíncrono. Un equipo siempre
cae en el mismo proceso (id % SHARDS) para conservar sus contadores y calcular tasas.
"""
import os
import sys
import re
import time
import json
import asyncio
import subprocess
import multiprocessing

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

from pysnmp.hlapi.v3arch.asyncio import (
    SnmpEngine, CommunityData, UdpTransportTarget, ContextData,
    ObjectType, ObjectIdentity, get_cmd, bulk_walk_cmd
)

try:
    from nm_common import db_config, PHYSICAL_IF_TYPES, COMUNIDAD_EFECTIVA_SQL, conectar, upsert, contador, credencial_snmp, auth_snmp
except ImportError:  # importado como paquete desde la raíz (monitor:poll)
    from worker.nm_common import db_config, PHYSICAL_IF_TYPES, COMUNIDAD_EFECTIVA_SQL, conectar, upsert, contador, credencial_snmp, auth_snmp

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)

DB_CONFIG = db_config()
POLL_INTERVAL = 5       # mínimo entre ciclos: en la práctica un ciclo empieza en cuanto termina el anterior (~6-8 s)
# Procesos de sondeo: casi todo el tiempo esperan respuestas de ping/SNMP, así que uno por núcleo no satura
# (16 núcleos → 8 en el equipo Windows, 6 → 6 en el servidor). NM_PROCESOS en el entorno lo fija a mano.
SHARDS = int(os.environ.get('NM_PROCESOS') or max(2, min(8, os.cpu_count() or 2)))
CONCURRENCY = 24        # equipos a la vez por proceso (ping y SNMP de cada uno van en paralelo)
LIMITE_SHARD_S = 120    # si un proceso no termina su parte en este tiempo, se reinicia
PING_COUNT = 3
SATURATION_PCT = 80.0
MAX_RATE_FACTOR = 1.1   # tolerancia sobre ifHighSpeed antes de descartar una tasa por imposible
MAX_RATE_SIN_VELOCIDAD_MBPS = 400_000   # puerto sin velocidad informada: más de 400 Gbps no es físico
MAX_WALK_ROWS = 5000
PING_TIME_RE = re.compile(r'(?:time|tiempo)\s*([=<])\s*([\d.,]+)')

# Estado entre ciclos para calcular tasas reales a partir de contadores:
# { dev_id: { if_index: {'in', 'out', 'err', 'ts', 'hc'} } }
_prev_counters = {}

# Equipos que no contestan SNMP (la mayoría solo responde ICMP): { dev_id: (fallos_seguidos, reintentar_desde) }.
# Tras FALLOS_SNMP_PARA_PAUSA fallos seguidos, su SNMP se vuelve a probar cada REINTENTO_SNMP_S en vez de en
# cada ciclo, para no esperar su tiempo de espera cada 10 s. Una credencial nueva se detecta en ese plazo.
_sin_snmp = {}
_sin_ping = set()       # equipos que no respondieron ping en su último sondeo
FALLOS_SNMP_PARA_PAUSA = 2
REINTENTO_SNMP_S = 60

OID_UPTIME = '1.3.6.1.2.1.1.3.0'
OID_DESCR = '1.3.6.1.2.1.1.1.0'
SCALAR_OIDS = [
    OID_UPTIME,                             # 0 sysUpTime
    OID_DESCR,                              # 1 sysDescr
    '1.3.6.1.4.1.9.9.109.1.1.1.1.8.1',      # 2 cpmCPUTotal5minRev
    '1.3.6.1.4.1.9.2.1.58.0',               # 3 avgBusy5 (Catalyst legacy)
    '1.3.6.1.4.1.9.9.48.1.1.1.5.1',         # 4 ciscoMemoryPoolUsed
    '1.3.6.1.4.1.9.9.48.1.1.1.6.1',         # 5 ciscoMemoryPoolFree
    '1.3.6.1.4.1.9.9.109.1.1.1.1.12.1',     # 6 cpmCPUMemoryUsed (KB, NX-OS)
    '1.3.6.1.4.1.9.9.109.1.1.1.1.13.1',     # 7 cpmCPUMemoryFree (KB, NX-OS)
    '1.3.6.1.4.1.9.9.109.1.1.1.1.7.1',      # 8 cpmCPUTotal1minRev
    '1.3.6.1.4.1.12356.101.4.1.3.0',        # 9 fgSysCpuUsage
    '1.3.6.1.4.1.12356.101.4.1.4.0',        # 10 fgSysMemUsage
    '1.3.6.1.4.1.12356.101.4.1.8.0',        # 11 fgSysSesCount
]

IF_TYPE = '1.3.6.1.2.1.2.2.1.3'
IF_OPER = '1.3.6.1.2.1.2.2.1.8'
IF_HIGH_SPEED = '1.3.6.1.2.1.31.1.1.1.15'
IF_HC_IN = '1.3.6.1.2.1.31.1.1.1.6'
IF_HC_OUT = '1.3.6.1.2.1.31.1.1.1.10'
IF_IN = '1.3.6.1.2.1.2.2.1.10'
IF_OUT = '1.3.6.1.2.1.2.2.1.16'
IF_IN_ERR = '1.3.6.1.2.1.2.2.1.14'
IF_OUT_ERR = '1.3.6.1.2.1.2.2.1.20'
IF_IN_DISC = '1.3.6.1.2.1.2.2.1.13'
IF_OUT_DISC = '1.3.6.1.2.1.2.2.1.19'
TEMP_DESCR = '1.3.6.1.4.1.9.9.13.1.3.1.2'
TEMP_VALUE = '1.3.6.1.4.1.9.9.13.1.3.1.3'
TEMP_STATE = '1.3.6.1.4.1.9.9.13.1.3.1.6'
CISCO_ENT_SENSOR = '1.3.6.1.4.1.9.9.91.1.1.1.1'
ENT_SENSOR = '1.3.6.1.2.1.99.1.1.1'
ENT_PHYS_NAME = '1.3.6.1.2.1.47.1.1.1.1.7'


_COLS_PUERTO = ['oper_status', 'in_octets', 'out_octets', 'bandwidth_util_pct',
                'in_errors', 'out_errors', 'in_discards', 'out_discards']
SQL_TELEMETRIA_PUERTO = upsert(
    'telemetria_interfaces',
    ['interfaz_id', *_COLS_PUERTO, {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
    ['interfaz_id'],
    {**{c: '{nuevo}' for c in _COLS_PUERTO}, 'updated_at': 'NOW()'})


def get_db_connection():
    try:
        return conectar(DB_CONFIG)
    except Exception as e:
        print(f"[DB] Error de conexión: {e}", flush=True)
        return None


def load_thresholds():
    conn = get_db_connection()
    if not conn:
        return 85, 5
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT umbral_cpu_warning, umbral_loss_warning FROM configuraciones_generales ORDER BY id LIMIT 1")
        row = cur.fetchone() or {}
        return int(row.get('umbral_cpu_warning') or 85), float(row.get('umbral_loss_warning') or 5)
    finally:
        conn.close()


# ----------------------------------------------------------------------------
#  ICMP
# ----------------------------------------------------------------------------
if sys.platform == 'win32':
    import ctypes
    import socket

    # API nativa de Windows (la misma que usa ping.exe): no abre un proceso por equipo ni espera 1 s entre
    # paquetes, y no requiere permisos de administrador.
    _iphlpapi = ctypes.WinDLL('iphlpapi')
    _iphlpapi.IcmpCreateFile.restype = ctypes.c_void_p
    _iphlpapi.IcmpCloseHandle.argtypes = [ctypes.c_void_p]
    _iphlpapi.IcmpSendEcho.argtypes = [ctypes.c_void_p, ctypes.c_ulong, ctypes.c_void_p, ctypes.c_ushort,
                                       ctypes.c_void_p, ctypes.c_void_p, ctypes.c_ulong, ctypes.c_ulong]
    _iphlpapi.IcmpSendEcho.restype = ctypes.c_ulong
    _ICMP_DATOS = b'NOC-monitor-ping'
    ICMP_TIMEOUT_MS = 1000
    ICMP_PAUSA_S = 0.2


def _icmp_windows(host, count):
    """Ping con IcmpSendEcho. Solo cuenta respuestas IP_SUCCESS (igual que las líneas con TTL de ping.exe).
    La latencia es la medida con reloj de alta resolución, no el valor redondeado a ms de Windows."""
    try:
        destino = int.from_bytes(socket.inet_aton(socket.gethostbyname(host)), 'little')
    except OSError as e:
        print(f"[PING] Dirección inválida {host}: {e}", flush=True)
        return count, 0, None
    manejador = _iphlpapi.IcmpCreateFile()
    if not manejador or manejador == ctypes.c_void_p(-1).value:
        return count, 0, None
    datos = ctypes.create_string_buffer(_ICMP_DATOS, len(_ICMP_DATOS))
    respuesta = ctypes.create_string_buffer(256)   # ICMP_ECHO_REPLY + datos + margen
    received, times = 0, []
    try:
        for i in range(count):
            if i:
                # 200 ms entre paquetes (como "ping -i 0.2" en Linux): una pausa breve del equipo
                # no se lleva los tres paquetes y no se reporta como caída
                time.sleep(ICMP_PAUSA_S)
            inicio = time.perf_counter()
            n = _iphlpapi.IcmpSendEcho(manejador, destino, datos, len(_ICMP_DATOS), None,
                                       respuesta, ctypes.sizeof(respuesta), ICMP_TIMEOUT_MS)
            transcurrido_ms = (time.perf_counter() - inicio) * 1000
            if n == 0:
                continue
            # ICMP_ECHO_REPLY empieza con Address, Status y RoundTripTime (ULONG cada uno)
            status = int.from_bytes(respuesta.raw[4:8], 'little')
            if status != 0:
                continue
            received += 1
            # RoundTripTime de Windows viene en milisegundos enteros (0 = "<1 ms"): se usa el tiempo medido
            # aquí con un reloj de alta resolución, que es el viaje real del paquete (más ~0.05 ms de la llamada)
            times.append(round(transcurrido_ms, 3))
    finally:
        _iphlpapi.IcmpCloseHandle(manejador)
    avg = round(sum(times) / len(times), 2) if times else None
    return count, received, avg


def icmp_probe(host, count=PING_COUNT):
    """Ping real. Devuelve (enviados, recibidos, latencia_promedio_ms | None)."""
    if sys.platform == 'win32':
        return _icmp_windows(host, count)
    else:
        # -i 0.2: los 3 pings en ~0.5 s (mínimo permitido sin privilegios)
        cmd = ['ping', '-c', str(count), '-i', '0.2', '-W', '1', host]
    try:
        out = subprocess.run(cmd, capture_output=True, text=True, timeout=count * 2 + 3)
    except Exception as e:
        print(f"[PING] Error en {host}: {e}", flush=True)
        return count, 0, None

    received = 0
    times = []
    for line in out.stdout.lower().splitlines():
        if 'ttl=' not in line:
            continue
        received += 1
        m = PING_TIME_RE.search(line)
        # Solo tiempos exactos ("time=0.41 ms"); "<1ms" no es una medición y no se convierte en un número
        if m and m.group(1) == '=':
            times.append(float(m.group(2).replace(',', '.')))
    avg = round(sum(times) / len(times), 2) if times else None
    return count, min(received, count), avg


# ----------------------------------------------------------------------------
#  SNMP
# ----------------------------------------------------------------------------
def _int(v):
    try:
        if v is None or v.__class__.__name__ in ('NoSuchObject', 'NoSuchInstance', 'EndOfMibView', 'Null'):
            return None
        return int(v)
    except Exception:
        return None


async def snmp_walk(engine, auth, target, base_oid):
    """GETBULK de una columna completa. Devuelve {ultimo_indice: valor_int}."""
    root = tuple(int(x) for x in base_oid.split('.'))
    result = {}
    try:
        async for err_ind, err_stat, _, vbs in bulk_walk_cmd(
                engine, auth, target, ContextData(), 0, 40,
                ObjectType(ObjectIdentity(base_oid)),
                lexicographicMode=False, ignoreNonIncreasingOid=True):
            if err_ind or err_stat:
                break
            for name, val in vbs:
                oid_t = tuple(name)
                if oid_t[:len(root)] != root:
                    return result
                v = _int(val)
                if v is not None:
                    result[oid_t[-1]] = v
                if len(result) >= MAX_WALK_ROWS:
                    return result
    except Exception:
        pass
    return result


async def snmp_walk_text(engine, auth, target, base_oid):
    root = tuple(int(x) for x in base_oid.split('.'))
    result = {}
    try:
        async for err_ind, err_stat, _, vbs in bulk_walk_cmd(
                engine, auth, target, ContextData(), 0, 40,
                ObjectType(ObjectIdentity(base_oid)),
                lexicographicMode=False, ignoreNonIncreasingOid=True):
            if err_ind or err_stat:
                break
            for name, val in vbs:
                oid_t = tuple(name)
                if oid_t[:len(root)] != root:
                    return result
                result[oid_t[-1]] = str(val.prettyPrint()).strip()
    except Exception:
        pass
    return result


async def entity_temperature_sensors(engine, auth, target):
    """CISCO-ENTITY-SENSOR-MIB (NX-OS) o ENTITY-SENSOR-MIB estándar: sensores celsius(8).
    Ambas tablas tienen las mismas columnas: .1 tipo, .2 escala, .3 precisión, .4 valor, .5 estado."""
    for base in (CISCO_ENT_SENSOR, ENT_SENSOR):
        types = await snmp_walk(engine, auth, target, f'{base}.1')
        if any(t == 8 for t in types.values()):
            break
    else:
        return []
    celsius = [i for i, t in types.items() if t == 8]
    scales = await snmp_walk(engine, auth, target, f'{base}.2')
    precs = await snmp_walk(engine, auth, target, f'{base}.3')
    values = await snmp_walk(engine, auth, target, f'{base}.4')
    status = await snmp_walk(engine, auth, target, f'{base}.5')
    names = await snmp_walk_text(engine, auth, target, ENT_PHYS_NAME)
    sensores = []
    for i in celsius:
        if i not in values:
            continue
        v = values[i] / (10 ** precs.get(i, 0)) * (1000 ** (scales.get(i, 9) - 9))
        if not -40 <= v <= 200:
            continue
        st = {1: 'OK', 2: 'NO DISPONIBLE', 3: 'NO OPERATIVO'}.get(status.get(i), 'DESCONOCIDO')
        sensores.append({'sensor': names.get(i) or f'Sensor {i}', 'temp_c': round(v, 1), 'status': st})
    return sensores


async def collect_snmp(engine, ip, community):
    """Consulta SNMP real. Devuelve None si el equipo no responde SNMP."""
    auth = auth_snmp(community)
    try:
        target = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=1)
        err_ind, err_stat, _, vbs = await get_cmd(
            engine, auth, target, ContextData(),
            *[ObjectType(ObjectIdentity(o)) for o in SCALAR_OIDS]
        )
    except Exception:
        return None
    if err_ind or err_stat or not vbs:
        return None
    vals = [v for _, v in vbs]
    if _int(vals[0]) is None:
        return None

    data = {
        'uptime_sec': _int(vals[0]) // 100,
        'sys_descr': None, 'cpu': None, 'mem': None,
        'ram_total_mb': None, 'ram_used_mb': None, 'sessions': None,
    }
    try:
        d = str(vals[1].prettyPrint()).strip()
        data['sys_descr'] = d or None
    except Exception:
        pass

    for idx in (9, 2, 8, 3):  # FortiGate, IOS-XE/NX-OS 5min, 1min, Catalyst legacy
        v = _int(vals[idx])
        if v is not None and 0 <= v <= 100:
            data['cpu'] = v
            break

    fg_mem = _int(vals[10])
    if fg_mem is not None and 0 <= fg_mem <= 100:
        data['mem'] = fg_mem
    else:
        used, free = _int(vals[4]), _int(vals[5])
        if used is not None and free is not None and used + free > 0:
            data['mem'] = round(used / (used + free) * 100, 1)
            data['ram_used_mb'] = round(used / 1048576)
            data['ram_total_mb'] = round((used + free) / 1048576)
        else:
            used, free = _int(vals[6]), _int(vals[7])
            if used is not None and free is not None and used + free > 0:
                data['mem'] = round(used / (used + free) * 100, 1)
                data['ram_used_mb'] = round(used / 1024)
                data['ram_total_mb'] = round((used + free) / 1024)

    data['sessions'] = _int(vals[11])

    # Las consultas de tabla van en paralelo (antes una tras otra: era lo que alargaba el ciclo)
    (temps, if_type, oper, speed, hc_in, hc_out, in_err, out_err, in_disc, out_disc) = await asyncio.gather(
        snmp_walk(engine, auth, target, TEMP_VALUE),
        snmp_walk(engine, auth, target, IF_TYPE), snmp_walk(engine, auth, target, IF_OPER),
        snmp_walk(engine, auth, target, IF_HIGH_SPEED),
        snmp_walk(engine, auth, target, IF_HC_IN), snmp_walk(engine, auth, target, IF_HC_OUT),
        snmp_walk(engine, auth, target, IF_IN_ERR), snmp_walk(engine, auth, target, IF_OUT_ERR),
        snmp_walk(engine, auth, target, IF_IN_DISC), snmp_walk(engine, auth, target, IF_OUT_DISC),
    )

    # Temperatura (CISCO-ENVMON-MIB). Sin sensores -> sin dato.
    sensores = []
    if temps:
        states, descrs = await asyncio.gather(snmp_walk(engine, auth, target, TEMP_STATE),
                                              snmp_walk_text(engine, auth, target, TEMP_DESCR))
        for idx, tv in temps.items():
            sv = states.get(idx)
            st = {1: 'OK', 2: 'WARNING', 3: 'CRITICAL', 4: 'SHUTDOWN', 5: 'NOT PRESENT', 6: 'NOT FUNCTIONING'}.get(sv, 'DESCONOCIDO')
            sensores.append({'sensor': descrs.get(idx, f'Sensor {idx}'), 'temp_c': tv, 'status': st})
    if not temps:
        sensores = await entity_temperature_sensors(engine, auth, target)
    data['sensores'] = sensores
    data['temp_c'] = max(s['temp_c'] for s in sensores) if sensores else None

    # Interfaces
    data['if_type'], data['oper'], data['speed'] = if_type, oper, speed
    if hc_in:
        data['hc'] = True
        data['in'], data['out'] = hc_in, hc_out
    else:
        # Equipos sin contadores de 64 bits
        data['hc'] = False
        data['in'], data['out'] = await asyncio.gather(snmp_walk(engine, auth, target, IF_IN),
                                                       snmp_walk(engine, auth, target, IF_OUT))
    data['in_err'], data['out_err'] = in_err, out_err
    data['in_disc'], data['out_disc'] = in_disc, out_disc
    return data


def counter_delta(cur, prev, is_hc):
    """Diferencia entre dos lecturas del mismo contador; None si no hay una diferencia válida."""
    if cur is None or prev is None:
        return None
    if cur >= prev:
        return cur - prev
    # Vuelta del contador de 32 bits: solo si ambas lecturas caben en 32 bits (si la anterior era de 64 bits,
    # restarla daba tasas de millones de Mbps negativas)
    if not is_hc and prev < 2 ** 32 and cur < 2 ** 32:
        return cur + 2 ** 32 - prev
    return None    # reinicio del equipo/contador: no hay delta válido


def format_uptime(sec):
    if sec is None:
        return None
    return f"{sec // 86400}d {(sec % 86400) // 3600}h {(sec % 3600) // 60}m"


# ----------------------------------------------------------------------------
#  Persistencia
# ----------------------------------------------------------------------------
def mark_device_offline(cursor, dev_id):
    """Sin ICMP ni SNMP: el equipo está inaccesible. No se inventan métricas."""
    cursor.execute("UPDATE dispositivos SET estado='offline' WHERE id=%s", (dev_id,))
    cursor.execute("""
        INSERT INTO metricas_red (dispositivo_id, bytes_in, bytes_out, ping_ms, cpu_usage, memory_usage,
            packet_loss, uptime, temperatura_celsius, conexiones_activas, errores_interfaz,
            traffic_in_mbps, traffic_out_mbps, fecha_registro)
        VALUES (%s, 0, 0, NULL, NULL, NULL, 100.0, NULL, NULL, NULL, NULL, NULL, NULL, NOW())
    """, (dev_id,))
    cursor.execute(upsert(
        'telemetria_chasis',
        ['dispositivo_id', {'uptime_str': "'Inaccesible'"}, {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
        ['dispositivo_id'],
        {'uptime_str': "'Inaccesible'", 'cpu_utilization': 'NULL', 'ram_utilization': 'NULL',
         'temperatura_c': 'NULL', 'updated_at': 'NOW()'}), (dev_id,))
    # El estado de los puertos es desconocido mientras el equipo no responda.
    cursor.execute("""
        UPDATE telemetria_interfaces SET oper_status=NULL, bandwidth_util_pct=NULL, updated_at=NOW()
        WHERE interfaz_id IN (SELECT id FROM interfaces_red WHERE dispositivo_id=%s)
    """, (dev_id,))


# ----------------------------------------------------------------------------
#  Ping en un proceso aparte
# ----------------------------------------------------------------------------
# El proceso de sondeo está ocupado decodificando SNMP: si el ping corriera ahí, el reloj contaría también
# lo que Python tarda en atender cada respuesta y la latencia saldría inflada. Un proceso que solo hace ping
# (sus hilos esperan en Windows/ping sin trabajo de Python) mide únicamente el viaje del paquete.
_pool_ping = None


def ping_lote(ips):
    """Corre en el proceso de ping: todos los equipos a la vez. {ip: (enviados, recibidos, latencia_ms)}."""
    from concurrent.futures import ThreadPoolExecutor
    if not ips:
        return {}
    with ThreadPoolExecutor(max_workers=min(64, len(ips))) as ex:
        return dict(zip(ips, ex.map(icmp_probe, ips)))


def _proceso_ping():
    global _pool_ping
    from concurrent.futures import ProcessPoolExecutor
    if _pool_ping is None or getattr(_pool_ping, '_broken', False):
        _pool_ping = ProcessPoolExecutor(max_workers=1)
    return _pool_ping


async def _ping_de(pings, ip):
    """Resultado del ping de un equipo dentro del lote del ciclo (se lanzó al empezar el ciclo)."""
    try:
        return (await asyncio.shield(pings)).get(ip, (PING_COUNT, 0, None))
    except Exception as e:
        print(f"[PING] Error en el proceso de ping: {e}", flush=True)
        return PING_COUNT, 0, None


async def poll_device(dev, engine, sem, thresholds, pings):
    async with sem:
        dev_id = dev['id']
        ip = dev['ip']
        name = dev['nombre']
        comm = credencial_snmp(dev)
        cpu_warn, loss_warn = thresholds

        fallos_snmp, reintento = _sin_snmp.get(dev_id, (0, 0))
        # Si en el ciclo anterior tampoco respondió ping, ping y SNMP van juntos (no uno tras otro)
        if fallos_snmp >= FALLOS_SNMP_PARA_PAUSA and time.time() < reintento and dev_id not in _sin_ping:
            # Equipo que no contesta SNMP (solo ICMP): basta el ping. Si tampoco responde ping,
            # se confirma por SNMP antes de darlo por caído (puede tener ICMP filtrado).
            sent, received, ping_ms = await _ping_de(pings, ip)
            snmp = await collect_snmp(engine, ip, comm) if received == 0 else None
            if snmp is not None:
                _sin_snmp.pop(dev_id, None)
        else:
            # Ping y SNMP al mismo tiempo: un equipo caído ya no suma los dos tiempos de espera
            (sent, received, ping_ms), snmp = await asyncio.gather(
                _ping_de(pings, ip),
                collect_snmp(engine, ip, comm))
            if snmp is None:
                fallos_snmp += 1
                _sin_snmp[dev_id] = (fallos_snmp, time.time() + REINTENTO_SNMP_S)
            else:
                _sin_snmp.pop(dev_id, None)
        if received == 0:
            _sin_ping.add(dev_id)
        else:
            _sin_ping.discard(dev_id)

        conn = get_db_connection()
        if not conn:
            return None
        cursor = conn.cursor(dictionary=True)
        try:
            if received == 0 and snmp is None:
                print(f"[{ip}] OFFLINE ({name}) sin respuesta ICMP ni SNMP", flush=True)
                mark_device_offline(cursor, dev_id)
                _prev_counters.pop(dev_id, None)
                conn.commit()
                return {'id': dev_id, 'online': False}

            # Pérdida real solo si ICMP respondió al menos una vez; si ICMP está filtrado
            # pero SNMP responde, la pérdida es desconocida.
            packet_loss = round((sent - received) / sent * 100, 1) if received > 0 else None

            now_ts = time.time()
            prev = _prev_counters.get(dev_id, {})
            new_prev = {}
            tot_in = tot_out = 0
            have_rate = False
            err_delta_total = None
            iface_rows = {}
            util_values = []

            if snmp:
                is_hc = snmp['hc']
                for idx, oper_raw in snmp['oper'].items():
                    cur_in = snmp['in'].get(idx)
                    cur_out = snmp['out'].get(idx)
                    cur_err = (snmp['in_err'].get(idx) or 0) + (snmp['out_err'].get(idx) or 0) \
                        if (idx in snmp['in_err'] or idx in snmp['out_err']) else None
                    new_prev[idx] = {'in': cur_in, 'out': cur_out, 'err': cur_err, 'ts': now_ts, 'hc': is_hc}
                    oper = 'up' if oper_raw == 1 else ('down' if oper_raw == 2 else None)
                    speed = snmp['speed'].get(idx)
                    util = None
                    p = prev.get(idx)
                    # Si el equipo pasó de contadores de 64 a 32 bits (o al revés) entre ciclos, las dos lecturas
                    # no se pueden restar: se descarta esta muestra
                    if p and p.get('hc', is_hc) != is_hc:
                        p = None
                    if p:
                        dt = now_ts - p['ts']
                        d_in = counter_delta(cur_in, p['in'], is_hc)
                        d_out = counter_delta(cur_out, p['out'], is_hc)
                        physical = snmp['if_type'].get(idx) in PHYSICAL_IF_TYPES
                        rate_ok = dt > 0 and d_in is not None and d_out is not None
                        if rate_ok:
                            in_mbps = d_in * 8 / dt / 1e6
                            out_mbps = d_out * 8 / dt / 1e6
                            # Una tasa mayor que la velocidad del puerto no es física: es una discontinuidad
                            # de contadores (reinicio de interfaz, ifIndex reasignado). Se descarta la muestra.
                            if speed and speed > 0 and max(in_mbps, out_mbps) > speed * MAX_RATE_FACTOR:
                                rate_ok = False
                            # Sin velocidad conocida: ningún puerto pasa de 400 Gbps; tampoco hay tasas negativas
                            elif max(in_mbps, out_mbps) > MAX_RATE_SIN_VELOCIDAD_MBPS or min(in_mbps, out_mbps) < 0:
                                rate_ok = False
                        if rate_ok:
                            if speed and speed > 0:
                                util = round(min(100.0, max(in_mbps, out_mbps) / speed * 100), 2)
                            if physical:
                                tot_in += in_mbps
                                tot_out += out_mbps
                                have_rate = True
                                if oper == 'up' and util is not None:
                                    util_values.append(util)
                        d_err = counter_delta(cur_err, p.get('err'), False) if cur_err is not None else None
                        if d_err is not None and physical:
                            err_delta_total = (err_delta_total or 0) + d_err
                    iface_rows[idx] = (oper, cur_in, cur_out, util, idx)
                _prev_counters[dev_id] = new_prev

            mbps_in = round(tot_in, 2) if have_rate else None
            mbps_out = round(tot_out, 2) if have_rate else None
            cpu = snmp['cpu'] if snmp else None
            mem = snmp['mem'] if snmp else None
            temp = snmp['temp_c'] if snmp else None
            uptime = snmp['uptime_sec'] if snmp else None

            estado = 'online'
            if (cpu is not None and cpu >= cpu_warn) or (packet_loss is not None and packet_loss >= loss_warn):
                estado = 'warning'

            print(f"[{ip}] {estado.upper()} {name} ping={ping_ms}ms loss={packet_loss}% "
                  f"snmp={'si' if snmp else 'no'} cpu={cpu} ram={mem} temp={temp} "
                  f"in={mbps_in} out={mbps_out} Mbps", flush=True)

            cursor.execute("UPDATE dispositivos SET estado=%s, ultima_vez_visto=NOW() WHERE id=%s", (estado, dev_id))
            cursor.execute("""
                INSERT INTO metricas_red (dispositivo_id, bytes_in, bytes_out, ping_ms, cpu_usage, memory_usage,
                    packet_loss, uptime, temperatura_celsius, conexiones_activas, errores_interfaz,
                    traffic_in_mbps, traffic_out_mbps, fecha_registro)
                VALUES (%s, 0, 0, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
            """, (dev_id, ping_ms, round(cpu) if cpu is not None else None,
                  round(mem) if mem is not None else None, packet_loss, uptime,
                  round(temp) if temp is not None else None,
                  snmp['sessions'] if snmp else None, err_delta_total, mbps_in, mbps_out))

            if snmp:
                cursor.execute(upsert(
                    'telemetria_chasis',
                    ['dispositivo_id', 'uptime_str', 'model_name', 'cpu_utilization', 'ram_utilization',
                     'ram_total_mb', 'ram_used_mb', 'temperatura_c', 'sensores_temperatura',
                     {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
                    ['dispositivo_id'],
                    {'uptime_str': '{nuevo}', 'model_name': 'COALESCE({nuevo}, {actual})',
                     'cpu_utilization': '{nuevo}', 'ram_utilization': '{nuevo}',
                     'ram_total_mb': 'COALESCE({nuevo}, {actual})', 'ram_used_mb': 'COALESCE({nuevo}, {actual})',
                     'temperatura_c': '{nuevo}', 'sensores_temperatura': '{nuevo}', 'updated_at': 'NOW()'}),
                    (dev_id, format_uptime(uptime), snmp['sys_descr'], cpu, mem,
                      snmp['ram_total_mb'], snmp['ram_used_mb'], temp,
                      json.dumps(snmp['sensores']) if snmp['sensores'] else None))

                cursor.execute("SELECT id, if_index FROM interfaces_red WHERE dispositivo_id=%s", (dev_id,))
                for iface in cursor.fetchall():
                    row = iface_rows.get(iface['if_index'])
                    if row is None:
                        continue  # la interfaz no existe en el equipo; el colector de detalle la depura
                    oper, cur_in, cur_out, util, idx = row
                    cursor.execute(SQL_TELEMETRIA_PUERTO, (iface['id'], oper, contador(cur_in), contador(cur_out), util,
                          contador(snmp['in_err'].get(idx)), contador(snmp['out_err'].get(idx)),
                          contador(snmp['in_disc'].get(idx)), contador(snmp['out_disc'].get(idx))))
            else:
                cursor.execute(upsert(
                    'telemetria_chasis',
                    ['dispositivo_id', {'uptime_str': "'Sin respuesta SNMP'"}, {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
                    ['dispositivo_id'],
                    {'uptime_str': "'Sin respuesta SNMP'", 'cpu_utilization': 'NULL', 'ram_utilization': 'NULL',
                     'temperatura_c': 'NULL', 'updated_at': 'NOW()'}), (dev_id,))

            conn.commit()
            return {
                'id': dev_id, 'online': True, 'cpu': cpu, 'mem': mem, 'ping': ping_ms,
                'loss': packet_loss, 'in': mbps_in, 'out': mbps_out, 'util': util_values,
            }
        except Exception as e:
            print(f"[{ip}] Error guardando métricas: {e}", flush=True)
            conn.rollback()
            return None
        finally:
            conn.close()


def _avg(values):
    vals = [v for v in values if v is not None]
    return round(sum(vals) / len(vals), 2) if vals else None


def save_global_metrics(results):
    """Promedios globales calculados con lo medido en ESTE ciclo."""
    online = [r for r in results if r and r.get('online')]
    if not results:
        return
    with_rate = [r for r in online if r.get('in') is not None]
    traffic_in = round(sum(r['in'] for r in with_rate), 2) if with_rate else None
    traffic_out = round(sum(r['out'] for r in with_rate), 2) if with_rate else None
    traffic = round(traffic_in + traffic_out, 2) if with_rate else None
    utils = [u for r in online for u in r.get('util', [])]
    saturation = round(sum(1 for u in utils if u >= SATURATION_PCT) / len(utils) * 100, 1) if utils else None
    # Pérdida global: dispositivos inaccesibles cuentan como 100 %
    losses = [r.get('loss') for r in online] + [100.0 for r in results if r and not r.get('online')]

    conn = get_db_connection()
    if not conn:
        return
    try:
        cur = conn.cursor()
        cur.execute("""
            INSERT INTO global_metrics (recorded_at, traffic_mbps, traffic_in_mbps, traffic_out_mbps, latency_ms,
                packet_loss_pct, cpu_avg_pct, ram_avg_pct, active_nodes, port_saturation_pct)
            VALUES (NOW(), %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """, (traffic, traffic_in, traffic_out, _avg([r.get('ping') for r in online]), _avg(losses),
              _avg([r.get('cpu') for r in online]), _avg([r.get('mem') for r in online]),
              len(online), saturation))
        conn.commit()
        print(f"[GLOBAL] nodos={len(online)}/{len(results)} trafico={traffic} Mbps "
              f"(in={traffic_in} out={traffic_out}) saturacion={saturation}%", flush=True)
    except Exception as e:
        print(f"[GLOBAL] Error: {e}", flush=True)
    finally:
        conn.close()


async def run_poll_cycle(shard=None):
    """Sondea los equipos (todos, o solo los de shard=(k, n): id % n == k).
    Sin shard guarda también las métricas globales; con shard devuelve los resultados al proceso principal."""
    conn = get_db_connection()
    if not conn:
        return []
    cursor = conn.cursor(dictionary=True)
    sql = f"SELECT id, nombre, ip, {COMUNIDAD_EFECTIVA_SQL} FROM dispositivos WHERE ip IS NOT NULL AND ip <> '0.0.0.0'"
    if shard:
        sql += f" AND MOD(id, {int(shard[1])}) = {int(shard[0])}"
    cursor.execute(sql)
    devices = cursor.fetchall()
    conn.close()

    thresholds = load_thresholds()
    engine = SnmpEngine()
    concurrencia = CONCURRENCY if shard else CONCURRENCY * SHARDS
    sem = asyncio.Semaphore(concurrencia)
    # Todos los pings del ciclo salen ya, en el proceso de ping, mientras aquí se consulta SNMP
    pings = asyncio.get_running_loop().run_in_executor(_proceso_ping(), ping_lote, [d['ip'] for d in devices])
    results = await asyncio.gather(*[poll_device(d, engine, sem, thresholds, pings) for d in devices],
                                   return_exceptions=True)
    results = [r for r in results if isinstance(r, dict)]
    if not shard:
        save_global_metrics(results)
    return results


def _proceso_shard(k, n, canal):
    """Proceso hijo: espera la orden de cada ciclo, sondea su parte y devuelve los resultados.
    Vive mientras el principal, así conserva los contadores de sus equipos entre ciclos.
    Si el principal termina, recv() lanza EOFError y este proceso (con su proceso de ping) termina también."""
    while True:
        orden = canal.recv()
        if orden is None:
            return
        try:
            resultados = asyncio.run(run_poll_cycle(shard=(k, n)))
        except Exception as e:
            print(f"[SHARD {k}] Error en ciclo: {e}", flush=True)
            resultados = []
        canal.send(resultados)


def _iniciar_shard(k):
    principal, hijo = multiprocessing.Pipe()
    # No daemon: un proceso daemon no puede crear su propio proceso de ping
    p = multiprocessing.Process(target=_proceso_shard, args=(k, SHARDS, hijo), daemon=False, name=f'poller-{k}')
    p.start()
    return p, principal


def main():
    print(f"=== MOTOR DE MONITOREO SNMP/ICMP (solo datos reales) · {SHARDS} procesos · ciclo {POLL_INTERVAL}s ===", flush=True)
    shards = [_iniciar_shard(k) for k in range(SHARDS)]
    while True:
        started = time.time()
        resultados = []
        for k, (proceso, canal) in enumerate(shards):
            if not proceso.is_alive():
                print(f"[SHARD {k}] El proceso terminó; se reinicia", flush=True)
                shards[k] = _iniciar_shard(k)
            shards[k][1].send('ciclo')
        for k, (proceso, canal) in enumerate(shards):
            try:
                # Un proceso trabado no detiene a los demás: pasado el límite se reemplaza
                if canal.poll(max(5, LIMITE_SHARD_S - (time.time() - started))):
                    resultados.extend(canal.recv())
                else:
                    print(f"[SHARD {k}] Excedió {LIMITE_SHARD_S}s; se reinicia", flush=True)
                    proceso.terminate()
                    shards[k] = _iniciar_shard(k)
            except (EOFError, OSError) as e:
                print(f"[SHARD {k}] Sin respuesta del proceso: {e}", flush=True)
                shards[k] = _iniciar_shard(k)
        print(f"\n[CYCLE] {len(resultados)} dispositivos procesados en {round(time.time() - started, 2)}s "
              f"({SHARDS} procesos)", flush=True)
        try:
            save_global_metrics(resultados)
        except Exception as e:
            print(f"[ERROR EN CICLO]: {e}", flush=True)
        time.sleep(max(1, POLL_INTERVAL - (time.time() - started)))


if __name__ == '__main__':
    main()
