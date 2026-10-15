"""
worker/snmp_poller.py — Sondeo en vivo (cada 15 s) de todos los dispositivos.

Principio: solo se guarda lo que el equipo realmente reporta. Si un dato no se pudo
obtener (OID no soportado, SNMP sin respuesta, primer ciclo sin delta de contadores),
se guarda NULL y la interfaz lo muestra como "Sin datos", nunca un valor inventado.
"""
import sys
import re
import time
import json
import asyncio
import subprocess
import mysql.connector

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

from pysnmp.hlapi.v3arch.asyncio import (
    SnmpEngine, CommunityData, UdpTransportTarget, ContextData,
    ObjectType, ObjectIdentity, get_cmd, bulk_walk_cmd
)

try:
    from nm_common import db_config, PHYSICAL_IF_TYPES, COMUNIDAD_EFECTIVA_SQL
except ImportError:  # importado como paquete desde la raíz (monitor:poll)
    from worker.nm_common import db_config, PHYSICAL_IF_TYPES, COMUNIDAD_EFECTIVA_SQL

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)

DB_CONFIG = db_config()
POLL_INTERVAL = 15
PING_COUNT = 3
SATURATION_PCT = 80.0
MAX_RATE_FACTOR = 1.1   # tolerancia sobre ifHighSpeed antes de descartar una tasa por imposible
MAX_WALK_ROWS = 5000
PING_TIME_RE = re.compile(r'(?:time|tiempo)\s*([=<])\s*([\d.,]+)')

# Estado entre ciclos para calcular tasas reales a partir de contadores:
# { dev_id: { if_index: {'in', 'out', 'err', 'ts', 'hc'} } }
_prev_counters = {}

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


def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
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
def icmp_probe(host, count=PING_COUNT):
    """Ping real. Devuelve (enviados, recibidos, latencia_promedio_ms | None)."""
    if sys.platform == 'win32':
        cmd = ['ping', '-n', str(count), '-w', '1000', host]
    else:
        cmd = ['ping', '-c', str(count), '-W', '1', host]
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
        if m:
            val = float(m.group(2).replace(',', '.'))
            times.append(val if m.group(1) == '=' else val / 2)  # "<1ms" -> 0.5
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
    auth = CommunityData(community, mpModel=1)
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

    # Temperatura (CISCO-ENVMON-MIB). Sin sensores -> sin dato.
    temps = await snmp_walk(engine, auth, target, TEMP_VALUE)
    sensores = []
    if temps:
        states = await snmp_walk(engine, auth, target, TEMP_STATE)
        descrs = await snmp_walk_text(engine, auth, target, TEMP_DESCR)
        for idx, tv in temps.items():
            sv = states.get(idx)
            st = {1: 'OK', 2: 'WARNING', 3: 'CRITICAL', 4: 'SHUTDOWN', 5: 'NOT PRESENT', 6: 'NOT FUNCTIONING'}.get(sv, 'DESCONOCIDO')
            sensores.append({'sensor': descrs.get(idx, f'Sensor {idx}'), 'temp_c': tv, 'status': st})
    if not temps:
        sensores = await entity_temperature_sensors(engine, auth, target)
    data['sensores'] = sensores
    data['temp_c'] = max(s['temp_c'] for s in sensores) if sensores else None

    # Interfaces
    data['if_type'] = await snmp_walk(engine, auth, target, IF_TYPE)
    data['oper'] = await snmp_walk(engine, auth, target, IF_OPER)
    data['speed'] = await snmp_walk(engine, auth, target, IF_HIGH_SPEED)
    hc_in = await snmp_walk(engine, auth, target, IF_HC_IN)
    if hc_in:
        data['hc'] = True
        data['in'] = hc_in
        data['out'] = await snmp_walk(engine, auth, target, IF_HC_OUT)
    else:
        data['hc'] = False
        data['in'] = await snmp_walk(engine, auth, target, IF_IN)
        data['out'] = await snmp_walk(engine, auth, target, IF_OUT)
    data['in_err'] = await snmp_walk(engine, auth, target, IF_IN_ERR)
    data['out_err'] = await snmp_walk(engine, auth, target, IF_OUT_ERR)
    data['in_disc'] = await snmp_walk(engine, auth, target, IF_IN_DISC)
    data['out_disc'] = await snmp_walk(engine, auth, target, IF_OUT_DISC)
    return data


def counter_delta(cur, prev, is_hc):
    if cur is None or prev is None:
        return None
    if cur >= prev:
        return cur - prev
    if not is_hc:  # vuelta del contador de 32 bits
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
    cursor.execute("""
        INSERT INTO telemetria_chasis (dispositivo_id, uptime_str, cpu_utilization, ram_utilization,
            temperatura_c, created_at, updated_at)
        VALUES (%s, 'Inaccesible', NULL, NULL, NULL, NOW(), NOW())
        ON DUPLICATE KEY UPDATE uptime_str='Inaccesible', cpu_utilization=NULL, ram_utilization=NULL,
            temperatura_c=NULL, updated_at=NOW()
    """, (dev_id,))
    # El estado de los puertos es desconocido mientras el equipo no responda.
    cursor.execute("""
        UPDATE telemetria_interfaces ti JOIN interfaces_red ir ON ti.interfaz_id = ir.id
        SET ti.oper_status=NULL, ti.bandwidth_util_pct=NULL, ti.updated_at=NOW()
        WHERE ir.dispositivo_id=%s
    """, (dev_id,))


async def poll_device(dev, engine, sem, thresholds):
    async with sem:
        dev_id = dev['id']
        ip = dev['ip']
        name = dev['nombre']
        comm = dev['comunidad_snmp'] or 'public'
        cpu_warn, loss_warn = thresholds

        loop = asyncio.get_running_loop()
        sent, received, ping_ms = await loop.run_in_executor(None, icmp_probe, ip)
        snmp = await collect_snmp(engine, ip, comm)

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
                    new_prev[idx] = {'in': cur_in, 'out': cur_out, 'err': cur_err, 'ts': now_ts}
                    oper = 'up' if oper_raw == 1 else ('down' if oper_raw == 2 else None)
                    speed = snmp['speed'].get(idx)
                    util = None
                    p = prev.get(idx)
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
                cursor.execute("""
                    INSERT INTO telemetria_chasis (dispositivo_id, uptime_str, model_name, cpu_utilization,
                        ram_utilization, ram_total_mb, ram_used_mb, temperatura_c, sensores_temperatura,
                        created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE uptime_str=VALUES(uptime_str),
                        model_name=COALESCE(VALUES(model_name), model_name),
                        cpu_utilization=VALUES(cpu_utilization), ram_utilization=VALUES(ram_utilization),
                        ram_total_mb=COALESCE(VALUES(ram_total_mb), ram_total_mb),
                        ram_used_mb=COALESCE(VALUES(ram_used_mb), ram_used_mb),
                        temperatura_c=VALUES(temperatura_c), sensores_temperatura=VALUES(sensores_temperatura),
                        updated_at=NOW()
                """, (dev_id, format_uptime(uptime), snmp['sys_descr'], cpu, mem,
                      snmp['ram_total_mb'], snmp['ram_used_mb'], temp,
                      json.dumps(snmp['sensores']) if snmp['sensores'] else None))

                cursor.execute("SELECT id, if_index FROM interfaces_red WHERE dispositivo_id=%s", (dev_id,))
                for iface in cursor.fetchall():
                    row = iface_rows.get(iface['if_index'])
                    if row is None:
                        continue  # la interfaz no existe en el equipo; el colector de detalle la depura
                    oper, cur_in, cur_out, util, idx = row
                    cursor.execute("""
                        INSERT INTO telemetria_interfaces (interfaz_id, oper_status, in_octets, out_octets,
                            bandwidth_util_pct, in_errors, out_errors, in_discards, out_discards, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE oper_status=VALUES(oper_status), in_octets=VALUES(in_octets),
                            out_octets=VALUES(out_octets), bandwidth_util_pct=VALUES(bandwidth_util_pct),
                            in_errors=VALUES(in_errors), out_errors=VALUES(out_errors),
                            in_discards=VALUES(in_discards), out_discards=VALUES(out_discards), updated_at=NOW()
                    """, (iface['id'], oper, cur_in, cur_out, util,
                          snmp['in_err'].get(idx), snmp['out_err'].get(idx),
                          snmp['in_disc'].get(idx), snmp['out_disc'].get(idx)))
            else:
                cursor.execute("""
                    INSERT INTO telemetria_chasis (dispositivo_id, uptime_str, cpu_utilization, ram_utilization,
                        temperatura_c, created_at, updated_at)
                    VALUES (%s, 'Sin respuesta SNMP', NULL, NULL, NULL, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE uptime_str='Sin respuesta SNMP', cpu_utilization=NULL,
                        ram_utilization=NULL, temperatura_c=NULL, updated_at=NOW()
                """, (dev_id,))

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


async def run_poll_cycle():
    conn = get_db_connection()
    if not conn:
        return
    cursor = conn.cursor(dictionary=True)
    cursor.execute(f"SELECT id, nombre, ip, {COMUNIDAD_EFECTIVA_SQL} FROM dispositivos WHERE ip IS NOT NULL AND ip <> '0.0.0.0'")
    devices = cursor.fetchall()
    conn.close()

    thresholds = load_thresholds()
    engine = SnmpEngine()
    sem = asyncio.Semaphore(25)
    start_t = time.time()
    results = await asyncio.gather(*[poll_device(d, engine, sem, thresholds) for d in devices],
                                   return_exceptions=True)
    results = [r for r in results if isinstance(r, dict)]
    print(f"\n[CYCLE] {len(devices)} dispositivos procesados en {round(time.time() - start_t, 2)}s", flush=True)
    save_global_metrics(results)


def main():
    print("=== MOTOR DE MONITOREO SNMP/ICMP (solo datos reales) ===", flush=True)
    while True:
        started = time.time()
        try:
            asyncio.run(run_poll_cycle())
        except Exception as e:
            print(f"[ERROR EN CICLO]: {e}", flush=True)
        time.sleep(max(1, POLL_INTERVAL - (time.time() - started)))


if __name__ == '__main__':
    main()
