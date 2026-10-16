"""
worker/snmp_detail_collector.py — Inventario de interfaces y estado ambiental (cada 5 min).

Lee del equipo (IF-MIB / CISCO-ENVMON-MIB): nombre, alias, MAC, velocidad, estado
administrativo y tipo de cada interfaz; fuentes de poder y ventiladores.
Además, cuando el equipo soporta la MIB correspondiente:
  - EtherLike-MIB: errores FCS/CRC y de alineación, dúplex negociado.
  - IF-MIB: contadores broadcast/multicast (y tasa de broadcast entre ciclos).
  - CISCO-ERR-DISABLE-MIB: puertos err-disabled y su causa.
  - POWER-ETHERNET-MIB: presupuesto y consumo PoE del chasis.
  - BRIDGE-MIB: cambios de topología Spanning Tree.
  - CISCO-ENTITY-SENSOR-MIB: potencia óptica Rx/Tx, temperatura, voltaje y bias de transceptores.
  - FORTINET-FORTIGATE-MIB: túneles VPN IPsec y estado HA.
  - AIRESPACE-WIRELESS-MIB: APs asociados al WLC y clientes por AP.
Los contadores de tráfico y el estado operativo los actualiza snmp_poller.py.

Si un equipo no responde SNMP NO se escribe nada: antes este worker generaba
puertos simulados, ahora el equipo simplemente queda sin inventario. Si una MIB
no responde, la columna correspondiente queda en NULL ("sin dato").
"""
import re
import sys
import time
import json
import asyncio

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

from pysnmp.hlapi.v3arch.asyncio import (
    SnmpEngine, CommunityData, UdpTransportTarget, ContextData,
    ObjectType, ObjectIdentity, get_cmd, bulk_walk_cmd
)

try:
    from nm_common import db_config, normalize_iface, COMUNIDAD_EFECTIVA_SQL, conectar, upsert, contador
except ImportError:
    from worker.nm_common import db_config, normalize_iface, COMUNIDAD_EFECTIVA_SQL, conectar, upsert, contador

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

OID_DOT3_ALIGN = '1.3.6.1.2.1.10.7.2.1.2'          # dot3StatsAlignmentErrors
OID_DOT3_FCS = '1.3.6.1.2.1.10.7.2.1.3'            # dot3StatsFCSErrors (CRC)
OID_DOT3_DUPLEX = '1.3.6.1.2.1.10.7.2.1.19'        # dot3StatsDuplexStatus
OID_IF_HC_IN_MCAST = '1.3.6.1.2.1.31.1.1.1.8'
OID_IF_HC_IN_BCAST = '1.3.6.1.2.1.31.1.1.1.9'
OID_IF_HC_OUT_MCAST = '1.3.6.1.2.1.31.1.1.1.12'
OID_IF_HC_OUT_BCAST = '1.3.6.1.2.1.31.1.1.1.13'
OID_ERRDIS_FEATURE = '1.3.6.1.4.1.9.9.548.1.1.1.1.2'   # cErrDisableFeatureDetectionEnabled (MIB presente)
OID_ERRDIS_CAUSE = '1.3.6.1.4.1.9.9.548.1.3.1.1.2'     # cErrDisableIfStatusCause, índice ifIndex.vlan
OID_PETH_POWER = '1.3.6.1.2.1.105.1.3.1.1.2'           # pethMainPsePower (W)
OID_PETH_CONSUMPTION = '1.3.6.1.2.1.105.1.3.1.1.4'     # pethMainPseConsumptionPower (W)
OID_STP_TIME_SINCE = '1.3.6.1.2.1.17.2.3.0'            # dot1dStpTimeSinceTopologyChange (centésimas)
OID_STP_TOP_CHANGES = '1.3.6.1.2.1.17.2.4.0'           # dot1dStpTopChanges
OID_CISCO_ENT_SENSOR = '1.3.6.1.4.1.9.9.91.1.1.1.1'    # .1 tipo .2 escala .3 precisión .4 valor
OID_FG_VPN = '1.3.6.1.4.1.12356.101.12.2.2.1'          # fgVpnTunEntry: .2 fase1 .3 fase2 .4 gw remoto .18/.19 octetos in/out .20 estado
OID_FG_HA_MODE = '1.3.6.1.4.1.12356.101.13.1.1.0'      # fgHaSystemMode
OID_FG_HA_STATS = '1.3.6.1.4.1.12356.101.13.2.1.1'     # fgHaStatsEntry: .3 CPU .4 RAM .6 sesiones .11 hostname .12 sincronía
OID_FG_SYS = '1.3.6.1.4.1.12356.101.4.1'               # .6 disco usado (MB) .7 disco total (MB) .11 sesiones nuevas/s (1 min) .15 sesiones IPv6
OID_FG_HW_SENSOR = '1.3.6.1.4.1.12356.101.4.3.2.1'     # fgHwSensorEntry: .2 nombre .3 valor .4 alarma
OID_FG_SDWAN = '1.3.6.1.4.1.12356.101.4.9.2.1'         # fgVWLHealthCheckLinkEntry: .2 nombre .4 estado .5 latencia .6 jitter
                                                       #   .7 enviados .8 recibidos .9 pérdida .14 interfaz (FortiOS 6.4+)
OID_FG_VPN_SSL = '1.3.6.1.4.1.12356.101.12.2.3.1'      # fgVpnSslStatsEntry (por VDOM): .2 usuarios .3 máx usuarios .6 túneles
OID_FG_IPS = '1.3.6.1.4.1.12356.101.9.2.1.1'           # fgIpsStatsEntry (por VDOM): .1 detectadas .2 bloqueadas .3 críticas .4 altas
OID_FG_AV = '1.3.6.1.4.1.12356.101.8.2.1.1'            # fgAvStatsEntry (por VDOM): .1 virus detectados .2 virus bloqueados
OID_BSN_AP_NAME = '1.3.6.1.4.1.14179.2.2.1.1.3'        # bsnAPName, índice MAC del AP
OID_BSN_AP_CLIENTS = '1.3.6.1.4.1.14179.2.2.13.1.4'    # bsnAPIfLoadNumOfClients, índice MAC.slot

CISCO_PREFIX = '1.3.6.1.4.1.9.'
FORTINET_PREFIX = '1.3.6.1.4.1.12356'

ENV_STATE = {1: 'OK', 2: 'WARNING', 3: 'CRITICAL', 4: 'SHUTDOWN', 5: 'NOT PRESENT', 6: 'NOT FUNCTIONING'}
DUPLEX = {2: 'Half', 3: 'Full'}
ERRDIS_CAUSE = {1: 'UDLD', 2: 'BPDU-GUARD', 3: 'CHANNEL-MISCONFIG', 4: 'PAGP-FLAP', 5: 'DTP-FLAP',
                6: 'LINK-FLAP', 7: 'L2PT-GUARD', 8: 'DOT1X-SECURITY-VIOLATION', 9: 'PORT-SECURITY-VIOLATION',
                10: 'GBIC-INVALID', 11: 'DHCP-RATE-LIMIT', 12: 'UNICAST-FLOOD', 13: 'VMPS',
                14: 'STORM-CONTROL', 15: 'INLINE-POWER', 16: 'ARP-INSPECTION', 17: 'PORT-LOOPBACK',
                18: 'PACKET-BUFFER', 19: 'MAC-LIMIT', 20: 'LINK-MONITOR-FAILURE'}
HA_MODE = {1: 'standalone', 2: 'active-active', 3: 'active-passive'}
# Sensores DOM: (patrón en entPhysicalName, clave, entSensorType esperado, rango válido)
DOM_SENSORS = [
    (re.compile(r'receive power|rx power', re.I), 'rx', 14, (-40, 10)),
    (re.compile(r'transmit power|tx power', re.I), 'tx', 14, (-40, 10)),
    (re.compile(r'bias', re.I), 'bias', 5, (0, 200)),
    (re.compile(r'voltage', re.I), 'volt', 4, (1, 6)),
    (re.compile(r'temperature', re.I), 'temp', 8, (-20, 120)),
]
IFACE_TOKEN = re.compile(r'^[A-Za-z][A-Za-z-]*\d+(?:/\d+)*$')

# Contadores broadcast del ciclo anterior para calcular la tasa: {dev_id: {ifIndex: (valor, ts)}}
_prev_bcast = {}
# Contadores FortiGate del ciclo anterior (octetos por túnel, detecciones IPS/AV): {dev_id: {...}}
_prev_forti = {}
FRU_POWER_STATE = {1: 'APAGADA (ambiente)', 2: 'OK', 3: 'APAGADA (admin)', 4: 'APAGADA (denegada)',
                   5: 'APAGADA (energía)', 6: 'APAGADA (temperatura)', 7: 'APAGADA (ventilador)', 8: 'FALLA',
                   9: 'OK (falla ventilador)', 10: 'APAGADA (enfriamiento)', 11: 'APAGADA (conector)',
                   12: 'OK (falla PoE)'}
FRU_FAN_STATE = {1: 'DESCONOCIDO', 2: 'OK', 3: 'FALLA', 4: 'WARNING'}
PLACEHOLDER_NAME = re.compile(r'^(GigabitEthernet\d{5,}|ifIndex\d+|Port\d+|Desconocido)$', re.I)


# Columnas de chasis que escribe este colector (UPSERT por dispositivo)
_COLS_CHASIS = ['estado_fuentes', 'estado_ventiladores', 'poe_total_budget_w', 'poe_used_w', 'poe_available_w',
                'stp_cambios_topologia', 'stp_seg_desde_cambio', 'vpn_tuneles', 'ha_estado', 'wifi_aps',
                'wifi_clientes', 'forti_sistema', 'forti_sensores', 'forti_sdwan', 'forti_vpn_ssl', 'forti_seguridad']
SQL_CHASIS_DETALLE = upsert(
    'telemetria_chasis',
    ['dispositivo_id', *_COLS_CHASIS, {'detalle_medido_en': 'NOW()'}, {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
    ['dispositivo_id'],
    {**{c: '{nuevo}' for c in _COLS_CHASIS}, 'detalle_medido_en': 'NOW()', 'updated_at': 'NOW()'})

# Contadores y estado por puerto (UPSERT por interfaz)
_COLS_DETALLE_PUERTO = ['in_multicast_pkts', 'out_multicast_pkts', 'in_broadcast_pkts', 'out_broadcast_pkts',
                        'broadcast_pps', 'crc_errors', 'fcs_errors', 'alignment_errors', 'is_errdisabled',
                        'errdisabled_reason', 'optica_rx_dbm', 'optica_tx_dbm', 'optica_temp_c', 'optica_voltage_v',
                        'optica_bias_ma']
SQL_DETALLE_PUERTO = upsert(
    'telemetria_interfaces',
    ['interfaz_id', *_COLS_DETALLE_PUERTO, {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
    ['interfaz_id'],
    _COLS_DETALLE_PUERTO)


def get_db_connection():
    try:
        return conectar(DB_CONFIG)
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


async def walk_tail(engine, auth, target, base_oid):
    """Como walk(), pero conserva el índice completo: {(sub, ids, ...): valor}."""
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
                out[oid_t[len(root):]] = val
    except Exception:
        pass
    return out


async def get_scalars(engine, auth, target, oids):
    """GET de varios escalares. Devuelve [int | None] en el mismo orden."""
    try:
        err_ind, err_stat, _, vbs = await get_cmd(engine, auth, target, ContextData(),
                                                  *[ObjectType(ObjectIdentity(o)) for o in oids])
    except Exception:
        return [None] * len(oids)
    if err_ind or err_stat or not vbs:
        return [None] * len(oids)
    out = []
    for _, v in vbs:
        out.append(None if v.__class__.__name__ in ('NoSuchObject', 'NoSuchInstance', 'EndOfMibView', 'Null')
                   else as_int(v))
    return out


async def collect_port_counters(engine, auth, target):
    """EtherLike-MIB e IF-MIB: CRC/FCS, alineación, dúplex y contadores broadcast/multicast por ifIndex."""
    async def ints(oid):
        return {i: as_int(v) for i, v in (await walk(engine, auth, target, oid)).items()}
    return {
        'fcs': await ints(OID_DOT3_FCS),
        'align': await ints(OID_DOT3_ALIGN),
        'duplex': {i: DUPLEX.get(v) for i, v in (await ints(OID_DOT3_DUPLEX)).items()},
        'in_bcast': await ints(OID_IF_HC_IN_BCAST),
        'out_bcast': await ints(OID_IF_HC_OUT_BCAST),
        'in_mcast': await ints(OID_IF_HC_IN_MCAST),
        'out_mcast': await ints(OID_IF_HC_OUT_MCAST),
    }


async def collect_errdisabled(engine, auth, target):
    """{ifIndex: causa} o None si el equipo no implementa CISCO-ERR-DISABLE-MIB."""
    causes = await walk_tail(engine, auth, target, OID_ERRDIS_CAUSE)
    if not causes and not await walk(engine, auth, target, OID_ERRDIS_FEATURE):
        return None
    out = {}
    for idx, v in causes.items():
        code = as_int(v)
        out[idx[0]] = ERRDIS_CAUSE.get(code, f'CAUSA {code}')
    return out


async def collect_poe(engine, auth, target):
    """Suma de los PSE del chasis (un PSE por miembro del stack). None si no hay PoE."""
    power = await walk(engine, auth, target, OID_PETH_POWER)
    if not power:
        return None
    used = await walk(engine, auth, target, OID_PETH_CONSUMPTION)
    total = sum(as_int(v) or 0 for v in power.values())
    if total <= 0:
        return None
    consumed = sum(as_int(v) or 0 for v in used.values()) if used else None
    return {'total_w': total, 'used_w': consumed,
            'available_w': (total - consumed) if consumed is not None else None}


async def collect_dom(engine, auth, target, iface_by_name):
    """Diagnóstico óptico (DOM) de los transceptores: {ifIndex: {'rx','tx','temp','volt','bias'}}."""
    types = await walk(engine, auth, target, f'{OID_CISCO_ENT_SENSOR}.1')
    candidates = {i for i, t in types.items() if as_int(t) in (4, 5, 8, 14)}
    if not candidates:
        return {}
    names = await walk(engine, auth, target, OID_ENT_PHYS_NAME)
    scales = await walk(engine, auth, target, f'{OID_CISCO_ENT_SENSOR}.2')
    precs = await walk(engine, auth, target, f'{OID_CISCO_ENT_SENSOR}.3')
    values = await walk(engine, auth, target, f'{OID_CISCO_ENT_SENSOR}.4')
    dom = {}
    for i in candidates:
        name = snmp_text(names.get(i))
        token = name.split(' ')[0] if name else ''
        if not IFACE_TOKEN.match(token):
            continue
        if_index = iface_by_name.get(normalize_iface(token).lower())
        if if_index is None or i not in values:
            continue
        for pattern, key, sensor_type, (lo, hi) in DOM_SENSORS:
            if not pattern.search(name) or as_int(types[i]) != sensor_type:
                continue
            v = as_int(values[i]) / (10 ** (as_int(precs.get(i)) or 0)) * (1000 ** ((as_int(scales.get(i)) or 9) - 9))
            if key == 'bias':
                v *= 1000  # amperes -> mA
            if lo <= v <= hi:
                # Transceptores multi-lane reportan un sensor por lane: se conserva el peor Rx/Tx
                prev = dom.setdefault(if_index, {}).get(key)
                dom[if_index][key] = round(min(v, prev) if prev is not None and key in ('rx', 'tx') else v, 2)
            break
    return dom


def numero(v, minimo=None, maximo=None):
    """Valor numérico de un Integer o de un OctetString como "12.345"; None si no es número o sale del rango."""
    if v is None:
        return None
    try:
        n = float(as_int(v)) if as_int(v) is not None else float(re.sub(r'[^0-9.\-]', '', snmp_text(v)) or 'x')
    except Exception:
        return None
    if (minimo is not None and n < minimo) or (maximo is not None and n > maximo):
        return None
    return round(n, 3)


def tipo_sensor(nombre):
    n = (nombre or '').lower()
    if re.search(r'fan', n):
        return 'ventilador'
    if re.search(r'temp|°c|\bdts|\bts\d|cpu.*core|thermal', n):
        return 'temperatura'
    if re.search(r'volt|vcc|vdd|\bp?\d+v\d*\b|\d+\.\d+v|vin|vout|vbat', n):
        return 'voltaje'
    if re.search(r'psu|power|pwr|\bps\d', n):
        return 'fuente'
    return 'otro'


async def collect_fortigate(engine, auth, target):
    """Datos propios de FortiGate (FORTINET-FORTIGATE-MIB). None si el equipo no implementa la MIB.
    Los contadores acumulados (octetos por túnel, IPS/AV) se convierten en tasas en calcular_tasas_forti()."""
    forti = {}

    # ---- Túneles IPsec: estado y octetos ----
    status = await walk(engine, auth, target, f'{OID_FG_VPN}.20')
    if status:
        p1 = await walk(engine, auth, target, f'{OID_FG_VPN}.2')
        p2 = await walk(engine, auth, target, f'{OID_FG_VPN}.3')
        gw = await walk(engine, auth, target, f'{OID_FG_VPN}.4')
        o_in = await walk(engine, auth, target, f'{OID_FG_VPN}.18')
        o_out = await walk(engine, auth, target, f'{OID_FG_VPN}.19')
        forti['vpn'] = [{'fase1': snmp_text(p1.get(i)) or f'Túnel {i}', 'fase2': snmp_text(p2.get(i)) or None,
                         'remoto': str(gw[i].prettyPrint()) if i in gw else None,
                         'estado': 'up' if as_int(st) == 2 else 'down',
                         'octetos_in': as_int(o_in.get(i)), 'octetos_out': as_int(o_out.get(i))}
                        for i, st in sorted(status.items())]

    # ---- HA: modo, sincronía y carga de cada miembro ----
    mode = (await get_scalars(engine, auth, target, [OID_FG_HA_MODE]))[0]
    if mode in HA_MODE:
        ha = {'modo': HA_MODE[mode], 'miembros': []}
        if mode != 1:
            cols = {c: await walk(engine, auth, target, f'{OID_FG_HA_STATS}.{c}') for c in (3, 4, 6, 11, 12)}
            ha['miembros'] = [{'nombre': snmp_text(h), 'sincronizado': as_int(cols[12].get(i)) == 1,
                               'cpu': numero(cols[3].get(i), 0, 100), 'ram': numero(cols[4].get(i), 0, 100),
                               'sesiones': as_int(cols[6].get(i))}
                              for i, h in sorted(cols[11].items())]
        forti['ha'] = ha

    # ---- Sistema: disco, tasa de sesiones nuevas, sesiones IPv6 ----
    disco_usado, disco_total, ses_rate, ses6 = await get_scalars(
        engine, auth, target, [f'{OID_FG_SYS}.6.0', f'{OID_FG_SYS}.7.0', f'{OID_FG_SYS}.11.0', f'{OID_FG_SYS}.15.0'])
    if any(v is not None for v in (disco_usado, disco_total, ses_rate, ses6)):
        forti['sistema'] = {
            'disco_usado_mb': disco_usado, 'disco_total_mb': disco_total,
            'disco_pct': round(disco_usado / disco_total * 100, 1) if disco_usado is not None and disco_total else None,
            'sesiones_nuevas_seg': ses_rate, 'sesiones_ipv6': ses6,
        }

    # ---- Sensores de hardware (temperaturas, ventiladores, voltajes) ----
    nombres = await walk(engine, auth, target, f'{OID_FG_HW_SENSOR}.2')
    if nombres:
        valores = await walk(engine, auth, target, f'{OID_FG_HW_SENSOR}.3')
        alarmas = await walk(engine, auth, target, f'{OID_FG_HW_SENSOR}.4')
        forti['sensores'] = [{'nombre': snmp_text(n), 'tipo': tipo_sensor(snmp_text(n)),
                              'valor': numero(valores.get(i)), 'alarma': as_int(alarmas.get(i)) == 1}
                             for i, n in sorted(nombres.items())]

    # ---- SLA de SD-WAN por enlace (health-check) ----
    nombres = await walk_tail(engine, auth, target, f'{OID_FG_SDWAN}.2')
    if nombres:
        cols = {c: await walk_tail(engine, auth, target, f'{OID_FG_SDWAN}.{c}') for c in (4, 5, 6, 7, 8, 9, 14)}
        enlaces = []
        for idx, nombre in sorted(nombres.items()):
            enviados, recibidos = numero(cols[7].get(idx), 0), numero(cols[8].get(idx), 0)
            perdida = numero(cols[9].get(idx), 0, 100)
            if perdida is None and enviados:
                perdida = round(max(0.0, (enviados - (recibidos or 0)) / enviados * 100), 2)
            estado = as_int(cols[4].get(idx))
            enlaces.append({'nombre': snmp_text(nombre), 'interfaz': snmp_text(cols[14].get(idx)) or None,
                            'estado': {0: 'alive', 1: 'dead'}.get(estado),
                            'latencia_ms': numero(cols[5].get(idx), 0, 10000), 'jitter_ms': numero(cols[6].get(idx), 0, 10000),
                            'perdida_pct': perdida})
        forti['sdwan'] = enlaces

    # ---- VPN SSL (sumado entre VDOMs) ----
    usuarios = await walk(engine, auth, target, f'{OID_FG_VPN_SSL}.2')
    if usuarios:
        maximo = await walk(engine, auth, target, f'{OID_FG_VPN_SSL}.3')
        tuneles_ssl = await walk(engine, auth, target, f'{OID_FG_VPN_SSL}.6')
        forti['vpn_ssl'] = {'usuarios': sum(as_int(v) or 0 for v in usuarios.values()),
                            'max_usuarios': sum(as_int(v) or 0 for v in maximo.values()) if maximo else None,
                            'tuneles': sum(as_int(v) or 0 for v in tuneles_ssl.values()) if tuneles_ssl else None}

    # ---- IPS / Antivirus: contadores acumulados (sumados entre VDOMs) ----
    seguridad = {}
    for clave, oid, col in (('ips_detectadas', OID_FG_IPS, 1), ('ips_bloqueadas', OID_FG_IPS, 2),
                            ('ips_criticas', OID_FG_IPS, 3), ('ips_altas', OID_FG_IPS, 4),
                            ('av_detectados', OID_FG_AV, 1), ('av_bloqueados', OID_FG_AV, 2)):
        filas = await walk(engine, auth, target, f'{oid}.{col}')
        if filas:
            seguridad[clave] = sum(as_int(v) or 0 for v in filas.values())
    if seguridad:
        forti['seguridad'] = seguridad

    return forti or None


def calcular_tasas_forti(dev_id, forti, ahora=None):
    """Convierte los contadores acumulados en tasas desde el ciclo anterior (requiere dos ciclos).
    Un contador que baja (reinicio del equipo o del túnel) no produce tasa."""
    if not forti:
        _prev_forti.pop(dev_id, None)
        return forti
    ahora = ahora or time.time()
    prev = _prev_forti.get(dev_id)
    dt = ahora - prev['ts'] if prev else None
    nuevo = {'ts': ahora, 'tuneles': {}, 'seguridad': dict(forti.get('seguridad') or {})}

    for t in forti.get('vpn') or []:
        clave = (t['fase1'], t['fase2'], t['remoto'])
        nuevo['tuneles'][clave] = (t.get('octetos_in'), t.get('octetos_out'))
        t['mbps_in'] = t['mbps_out'] = None
        anterior = prev['tuneles'].get(clave) if prev else None
        if anterior and dt and dt > 0:
            for i, campo in ((0, 'mbps_in'), (1, 'mbps_out')):
                actual, antes = nuevo['tuneles'][clave][i], anterior[i]
                if actual is not None and antes is not None and actual >= antes:
                    t[campo] = round((actual - antes) * 8 / dt / 1e6, 3)

    if forti.get('seguridad'):
        for clave, actual in list(forti['seguridad'].items()):
            antes = (prev or {}).get('seguridad', {}).get(clave)
            forti['seguridad'][clave + '_ciclo'] = actual - antes if antes is not None and actual >= antes else None
        forti['seguridad']['ventana_seg'] = round(dt) if dt else None

    _prev_forti[dev_id] = nuevo
    return forti


async def collect_wlc(engine, auth, target):
    """APs asociados al WLC y clientes por AP. None si el equipo no es un WLC."""
    names = await walk_tail(engine, auth, target, OID_BSN_AP_NAME)
    if not names:
        return None
    clients = await walk_tail(engine, auth, target, OID_BSN_AP_CLIENTS)
    per_ap = {}
    for idx, v in clients.items():
        per_ap[idx[:6]] = per_ap.get(idx[:6], 0) + (as_int(v) or 0)
    aps = [{'ap': snmp_text(name), 'clientes': per_ap.get(mac, 0) if clients else None}
           for mac, name in names.items()]
    aps.sort(key=lambda a: a['clientes'] or 0, reverse=True)
    return {'aps': aps, 'clientes': sum(per_ap.values()) if clients else None}


async def collect(engine, ip, community, sys_object_id=None, tipo=None):
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

    # MIBs opcionales. sysObjectID evita consultar MIBs de otro fabricante; si se desconoce, se prueban todas.
    oid = sys_object_id or ''
    is_forti = oid.startswith(FORTINET_PREFIX) or tipo == 'firewall'
    is_cisco = oid.startswith(CISCO_PREFIX)
    try_cisco = is_cisco or not oid
    iface_by_name = {normalize_iface(n).lower(): i for i, n in data['names'].items() if n}

    data['counters'] = await collect_port_counters(engine, auth, target)
    stp = await get_scalars(engine, auth, target, [OID_STP_TOP_CHANGES, OID_STP_TIME_SINCE])
    data['stp'] = {'cambios': stp[0], 'seg_desde_cambio': stp[1] // 100 if stp[1] is not None else None}
    data['poe'] = await collect_poe(engine, auth, target)
    data['errdis'] = await collect_errdisabled(engine, auth, target) if try_cisco and not is_forti else None
    data['dom'] = await collect_dom(engine, auth, target, iface_by_name) if try_cisco and not is_forti else {}
    data['wlc'] = await collect_wlc(engine, auth, target) if try_cisco and not is_forti else None
    data['forti'] = (await collect_fortigate(engine, auth, target)) if (is_forti or not is_cisco) else None
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

    sync_port_details(cur, dev_id, data)

    env = data['env']
    poe = data['poe'] or {}
    wlc = data['wlc'] or {}
    forti = calcular_tasas_forti(dev_id, data['forti']) or {}
    as_json = lambda v: json.dumps(v) if v else None
    cur.execute(SQL_CHASIS_DETALLE, (dev_id, as_json(env['psu']), as_json(env['fan']),
          poe.get('total_w'), poe.get('used_w'), poe.get('available_w'),
          data['stp']['cambios'], data['stp']['seg_desde_cambio'],
          as_json(forti.get('vpn')), as_json(forti.get('ha')), as_json(wlc.get('aps')), wlc.get('clientes'),
          as_json(forti.get('sistema')), as_json(forti.get('sensores')), as_json(forti.get('sdwan')),
          as_json(forti.get('vpn_ssl')), as_json(forti.get('seguridad'))))
    conn.commit()
    return len(real), removed, kept


def sync_port_details(cur, dev_id, data):
    """Contadores de errores/broadcast, err-disabled, DOM y dúplex por puerto (solo lo que el equipo reporta)."""
    c = data['counters']
    errdis = data['errdis']
    dom = data['dom']
    now_ts = time.time()
    prev = _prev_bcast.get(dev_id, {})
    new_prev = {}

    cur.execute("SELECT id, if_index FROM interfaces_red WHERE dispositivo_id=%s AND if_index > 0", (dev_id,))
    rows = []
    duplex_rows = []
    for r in cur.fetchall():
        idx = r['if_index']
        bcast = contador(c['in_bcast'].get(idx))
        pps = None
        if bcast is not None:
            new_prev[idx] = (bcast, now_ts)
            p = prev.get(idx)
            if p and bcast >= p[0] and now_ts > p[1]:
                pps = round((bcast - p[0]) / (now_ts - p[1]), 2)
        fcs = contador(c['fcs'].get(idx))
        d = dom.get(idx, {})
        rows.append((r['id'], contador(c['in_mcast'].get(idx)), contador(c['out_mcast'].get(idx)), bcast,
                     contador(c['out_bcast'].get(idx)), pps, fcs, fcs, contador(c['align'].get(idx)),
                     None if errdis is None else (idx in errdis),
                     errdis.get(idx) if errdis else None,
                     d.get('rx'), d.get('tx'), d.get('temp'), d.get('volt'), d.get('bias')))
        duplex_rows.append((c['duplex'].get(idx), r['id']))
    _prev_bcast[dev_id] = new_prev

    if rows:
        cur.executemany(SQL_DETALLE_PUERTO, rows)
        cur.executemany("UPDATE interfaces_red SET duplex=%s WHERE id=%s", duplex_rows)


async def process_device(engine, sem, dev):
    async with sem:
        data = await collect(engine, dev['ip'], dev['comunidad_snmp'] or 'public',
                             dev.get('sys_object_id'), dev.get('tipo_detectado'))
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
    cur.execute(f"SELECT id, nombre, ip, {COMUNIDAD_EFECTIVA_SQL}, sys_object_id, tipo_detectado FROM dispositivos "
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
