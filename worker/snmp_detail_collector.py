"""
worker/snmp_detail_collector.py — Inventario de interfaces y estado ambiental (cada 5 min).

Lee del equipo (IF-MIB / CISCO-ENVMON-MIB): nombre, alias, MAC, velocidad, estado
administrativo y tipo de cada interfaz; fuentes de poder y ventiladores.
Los contadores de tráfico y el estado operativo los actualiza snmp_poller.py.

Si un equipo no responde SNMP NO se escribe nada: antes este worker generaba
puertos simulados, ahora el equipo simplemente queda sin inventario.
"""
import re
import sys
import time
import json
import asyncio
import mysql.connector

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

from pysnmp.hlapi.v3arch.asyncio import (
    SnmpEngine, CommunityData, UdpTransportTarget, ContextData,
    ObjectType, ObjectIdentity, get_cmd, bulk_walk_cmd
)

try:
    from nm_common import db_config, normalize_iface
except ImportError:
    from worker.nm_common import db_config, normalize_iface

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)

DB_CONFIG = db_config()
INTERVAL = 300
CONCURRENCY = 10

OID_IF_NAME = '1.3.6.1.2.1.31.1.1.1.1'
OID_IF_DESCR = '1.3.6.1.2.1.2.2.1.2'
OID_IF_ALIAS = '1.3.6.1.2.1.31.1.1.1.18'
OID_IF_MAC = '1.3.6.1.2.1.2.2.1.6'
OID_IF_HIGH_SPEED = '1.3.6.1.2.1.31.1.1.1.15'
OID_IF_SPEED = '1.3.6.1.2.1.2.2.1.5'
OID_IF_ADMIN = '1.3.6.1.2.1.2.2.1.7'
OID_IF_TYPE = '1.3.6.1.2.1.2.2.1.3'
OID_PSU_DESCR = '1.3.6.1.4.1.9.9.13.1.5.1.2'
OID_PSU_STATE = '1.3.6.1.4.1.9.9.13.1.5.1.3'
OID_FAN_DESCR = '1.3.6.1.4.1.9.9.13.1.4.1.2'
OID_FAN_STATE = '1.3.6.1.4.1.9.9.13.1.4.1.3'

OID_FRU_POWER_STATUS = '1.3.6.1.4.1.9.9.117.1.1.2.1.2'
OID_FRU_FAN_STATUS = '1.3.6.1.4.1.9.9.117.1.4.1.1.1'
OID_ENT_PHYS_NAME = '1.3.6.1.2.1.47.1.1.1.1.7'

ENV_STATE = {1: 'OK', 2: 'WARNING', 3: 'CRITICAL', 4: 'SHUTDOWN', 5: 'NOT PRESENT', 6: 'NOT FUNCTIONING'}
FRU_POWER_STATE = {1: 'APAGADA (ambiente)', 2: 'OK', 3: 'APAGADA (admin)', 4: 'APAGADA (denegada)',
                   5: 'APAGADA (energía)', 6: 'APAGADA (temperatura)', 7: 'APAGADA (ventilador)', 8: 'FALLA',
                   9: 'OK (falla ventilador)', 10: 'APAGADA (enfriamiento)', 11: 'APAGADA (conector)',
                   12: 'OK (falla PoE)'}
FRU_FAN_STATE = {1: 'DESCONOCIDO', 2: 'OK', 3: 'FALLA', 4: 'WARNING'}
PLACEHOLDER_NAME = re.compile(r'^(GigabitEthernet\d{5,}|ifIndex\d+|Port\d+|Desconocido)$', re.I)


def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Exception as e:
        print(f"[DB] Error: {e}", flush=True)
        return None


def snmp_text(val):
    if val is None:
        return ''
    try:
        raw = val.asOctets()
        if raw and all(32 <= b < 127 for b in raw):
            return raw.decode('ascii').strip()
        try:
            return raw.decode('utf-8').strip('\x00 ').strip()
        except Exception:
            return raw.hex()
    except Exception:
        return str(val.prettyPrint()).strip()


def snmp_mac(val):
    try:
        raw = val.asOctets()
        if len(raw) == 6 and any(raw):
            return ':'.join(f'{b:02x}' for b in raw)
    except Exception:
        pass
    return None


async def walk(engine, auth, target, base_oid):
    root = tuple(int(x) for x in base_oid.split('.'))
    out = {}
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
                    return out
                if val.__class__.__name__ in ('NoSuchObject', 'NoSuchInstance', 'EndOfMibView'):
                    return out
                out[oid_t[-1]] = val
    except Exception:
        pass
    return out


def as_int(v):
    try:
        return int(v)
    except Exception:
        return None


async def collect(engine, ip, community):
    auth = CommunityData(community, mpModel=1)
    try:
        target = await UdpTransportTarget.create((ip, 161), timeout=2.0, retries=1)
        err_ind, err_stat, _, vbs = await get_cmd(engine, auth, target, ContextData(),
                                                  ObjectType(ObjectIdentity('1.3.6.1.2.1.1.3.0')))
        if err_ind or err_stat or not vbs:
            return None
    except Exception:
        return None

    names = await walk(engine, auth, target, OID_IF_NAME)
    if not names:
        names = await walk(engine, auth, target, OID_IF_DESCR)
    if not names:
        return None
    high = await walk(engine, auth, target, OID_IF_HIGH_SPEED)
    low = {} if high else await walk(engine, auth, target, OID_IF_SPEED)
    data = {
        'names': {i: snmp_text(v) for i, v in names.items()},
        'alias': {i: snmp_text(v) for i, v in (await walk(engine, auth, target, OID_IF_ALIAS)).items()},
        'mac': {i: snmp_mac(v) for i, v in (await walk(engine, auth, target, OID_IF_MAC)).items()},
        'speed': {i: as_int(v) for i, v in high.items()} if high
                 else {i: (as_int(v) or 0) // 1_000_000 for i, v in low.items()},
        'admin': {i: as_int(v) for i, v in (await walk(engine, auth, target, OID_IF_ADMIN)).items()},
        'type': {i: as_int(v) for i, v in (await walk(engine, auth, target, OID_IF_TYPE)).items()},
    }

    env = {}
    for descr_oid, state_oid, key in ((OID_PSU_DESCR, OID_PSU_STATE, 'psu'),
                                      (OID_FAN_DESCR, OID_FAN_STATE, 'fan')):
        descrs = await walk(engine, auth, target, descr_oid)
        states = await walk(engine, auth, target, state_oid)
        env[key] = [{key: snmp_text(descrs.get(i)) or f'{key.upper()} {i}',
                     'status': ENV_STATE.get(as_int(st), 'DESCONOCIDO')}
                    for i, st in states.items()]

    # NX-OS / IOS-XE: CISCO-ENTITY-FRU-CONTROL-MIB cuando CISCO-ENVMON-MIB no responde
    if not env['psu'] and not env['fan']:
        psu = await walk(engine, auth, target, OID_FRU_POWER_STATUS)
        fan = await walk(engine, auth, target, OID_FRU_FAN_STATUS)
        if psu or fan:
            names = await walk(engine, auth, target, OID_ENT_PHYS_NAME)
            # La tabla FRU incluye módulos y ventiladores; solo se toman las fuentes de poder
            env['psu'] = [{'psu': snmp_text(names.get(i)),
                           'status': FRU_POWER_STATE.get(as_int(v), 'DESCONOCIDO')} for i, v in psu.items()
                          if re.search(r'power|psu|fuente', snmp_text(names.get(i)), re.I)]
            env['fan'] = [{'fan': snmp_text(names.get(i)) or f'Fan {i}',
                           'status': FRU_FAN_STATE.get(as_int(v), 'DESCONOCIDO')} for i, v in fan.items()]
    data['env'] = env
    return data


def port_type_for(name, if_type, speed):
    low = name.lower()
    if if_type == 6 and (low.startswith(('tengig', 'twentyfive', 'fortygig', 'hundredgig', 'te', 'twe', 'fo', 'hu'))
                         or 'sfp' in low or (speed or 0) >= 10000):
        return 'sfp_fiber'
    return 'copper_rj45' if if_type in (6, 62, 69, 117) else 'virtual'


def sync_device(conn, dev, data):
    dev_id = dev['id']
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT id, if_index, nombre FROM interfaces_red WHERE dispositivo_id=%s", (dev_id,))
    rows = cur.fetchall()

    real = data['names']
    real_by_norm = {normalize_iface(n).lower(): idx for idx, n in real.items() if n}

    # 1) Emparejar filas existentes con interfaces reales: primero por nombre, luego por ifIndex
    #    solo si el nombre guardado es un marcador (p.ej. "GigabitEthernet437256192").
    assigned = {}   # if_index real -> row id
    row_target = {}
    for r in rows:
        idx = real_by_norm.get(normalize_iface(r['nombre']).lower())
        if idx is not None and idx not in assigned:
            assigned[idx] = r['id']
            row_target[r['id']] = idx
    for r in rows:
        if r['id'] in row_target:
            continue
        idx = r['if_index']
        if idx in real and idx not in assigned and PLACEHOLDER_NAME.match(r['nombre'] or ''):
            assigned[idx] = r['id']
            row_target[r['id']] = idx

    # 2) Liberar los if_index (índice único por equipo) antes de reasignarlos
    cur.execute("UPDATE interfaces_red SET if_index = -id WHERE dispositivo_id=%s", (dev_id,))

    for idx, name in real.items():
        speed = data['speed'].get(idx)
        admin = data['admin'].get(idx)
        admin_st = 'up' if admin == 1 else ('down' if admin == 2 else None)
        if_type = data['type'].get(idx)
        values = (idx, name[:255] or f'ifIndex{idx}', data['mac'].get(idx), speed, admin_st,
                  (data['alias'].get(idx) or None), port_type_for(name, if_type, speed))
        if idx in assigned:
            cur.execute("""UPDATE interfaces_red SET if_index=%s, nombre=%s, mac_address=%s, velocidad_mbps=%s,
                           admin_status=%s, alias=%s, port_type=%s, updated_at=NOW() WHERE id=%s""",
                        values + (assigned[idx],))
        else:
            cur.execute("""INSERT INTO interfaces_red (if_index, nombre, mac_address, velocidad_mbps, admin_status,
                           alias, port_type, dispositivo_id, created_at, updated_at)
                           VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())""",
                        values + (dev_id,))

    # 3) Filas que no corresponden a ninguna interfaz real del equipo
    stale = [r['id'] for r in rows if r['id'] not in row_target]
    # Si el recorrido trajo muchas menos interfaces que las conocidas, puede haberse
    # cortado por timeout: en ese caso no se elimina nada.
    walk_seems_complete = len(real) >= len(rows) // 2
    removed = kept = 0
    for iid in stale:
        cur.execute("SELECT COUNT(*) AS n FROM enlaces_red WHERE origen_interfaz_id=%s OR destino_interfaz_id=%s",
                    (iid, iid))
        if cur.fetchone()['n'] or not walk_seems_complete:
            kept += 1  # la usa un enlace descubierto por CDP/LLDP; se conserva sin estado
            cur.execute("UPDATE telemetria_interfaces SET oper_status=NULL, bandwidth_util_pct=NULL WHERE interfaz_id=%s", (iid,))
        else:
            cur.execute("DELETE FROM telemetria_interfaces WHERE interfaz_id=%s", (iid,))
            cur.execute("DELETE FROM interfaces_red WHERE id=%s", (iid,))
            removed += 1

    env = data['env']
    cur.execute("""
        INSERT INTO telemetria_chasis (dispositivo_id, estado_fuentes, estado_ventiladores, created_at, updated_at)
        VALUES (%s, %s, %s, NOW(), NOW())
        ON DUPLICATE KEY UPDATE estado_fuentes=VALUES(estado_fuentes),
            estado_ventiladores=VALUES(estado_ventiladores), updated_at=NOW()
    """, (dev_id, json.dumps(env['psu']) if env['psu'] else None,
          json.dumps(env['fan']) if env['fan'] else None))
    conn.commit()
    return len(real), removed, kept


async def process_device(engine, sem, dev):
    async with sem:
        data = await collect(engine, dev['ip'], dev['comunidad_snmp'] or 'public')
    if not data:
        print(f"[{dev['ip']}] {dev['nombre']}: sin respuesta SNMP, inventario sin cambios.", flush=True)
        return
    conn = get_db_connection()
    if not conn:
        return
    try:
        total, removed, kept = sync_device(conn, dev, data)
        print(f"[{dev['ip']}] {dev['nombre']}: {total} interfaces reales"
              f"{f', {removed} inexistentes eliminadas' if removed else ''}"
              f"{f', {kept} sin correspondencia conservadas por enlaces' if kept else ''}.", flush=True)
    except Exception as e:
        conn.rollback()
        print(f"[{dev['ip']}] Error sincronizando inventario: {e}", flush=True)
    finally:
        conn.close()


async def run_cycle():
    conn = get_db_connection()
    if not conn:
        return
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT id, nombre, ip, comunidad_snmp FROM dispositivos "
                "WHERE estado <> 'offline' AND ip IS NOT NULL AND ip <> '0.0.0.0'")
    devices = cur.fetchall()
    conn.close()
    engine = SnmpEngine()
    sem = asyncio.Semaphore(CONCURRENCY)
    await asyncio.gather(*[process_device(engine, sem, d) for d in devices], return_exceptions=True)


def main():
    print("Colector de inventario de interfaces y estado ambiental (solo datos reales)...", flush=True)
    while True:
        started = time.time()
        try:
            asyncio.run(run_cycle())
        except Exception as e:
            print(f"[ERROR EN CICLO] {e}", flush=True)
        wait = max(10, INTERVAL - (time.time() - started))
        print(f"Ciclo completado. Próximo en {int(wait)} s.\n", flush=True)
        time.sleep(wait)


if __name__ == '__main__':
    main()
