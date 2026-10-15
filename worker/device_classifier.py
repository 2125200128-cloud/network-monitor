"""
worker/device_classifier.py — Clasificación de equipos por evidencia SNMP / CDP / LLDP.

El tipo de un equipo (switch, router, firewall, servidor, teléfono, PC, AP...) se decide
con datos que el propio equipo o sus vecinos reportan, en este orden de confianza:

  1. sysObjectID   -> fabricante (número de empresa IANA) y familia de producto.
  2. sysDescr + entPhysicalModelName del chasis (ENTITY-MIB) -> plataforma y modelo real.
  3. sysServices   -> capas que el equipo declara operar (L2 bridge / L3 router / L7 host).
  4. Capacidades CDP / LLDP anunciadas y vistas por los vecinos (sirve para teléfonos,
     APs y PCs que no responden SNMP).
  5. Nombre del equipo: solo como último recurso, con confianza "baja".

Uso:
  python device_classifier.py --audit [--out reporte.json]   Solo lectura: compara con la app.
  python device_classifier.py --apply                        Guarda la clasificación en la BD.
"""
import re
import sys
import time
import json
import asyncio
import argparse
import mysql.connector

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

from pysnmp.hlapi.v3arch.asyncio import (
    SnmpEngine, CommunityData, UdpTransportTarget, ContextData,
    ObjectType, ObjectIdentity, get_cmd, bulk_walk_cmd
)

try:
    from nm_common import db_config, COMUNIDAD_EFECTIVA_SQL
except ImportError:
    from worker.nm_common import db_config, COMUNIDAD_EFECTIVA_SQL

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)

OID_SYS_DESCR = '1.3.6.1.2.1.1.1.0'
OID_SYS_OBJECT = '1.3.6.1.2.1.1.2.0'
OID_SYS_NAME = '1.3.6.1.2.1.1.5.0'
OID_SYS_SERVICES = '1.3.6.1.2.1.1.7.0'
OID_ENT_CLASS = '1.3.6.1.2.1.47.1.1.1.1.5'        # 3 = chassis
OID_ENT_MODEL = '1.3.6.1.2.1.47.1.1.1.1.13'
OID_ENT_DESCR = '1.3.6.1.2.1.47.1.1.1.1.2'

CDP = '1.3.6.1.4.1.9.9.23.1.2.1.1'
CDP_ADDR, CDP_DEVID, CDP_PLAT, CDP_CAPS = (f'{CDP}.{c}' for c in (4, 6, 8, 9))
LLDP = '1.0.8802.1.1.2.1.4.1.1'
LLDP_SYSNAME, LLDP_SYSDESC, LLDP_CAP_ENABLED = (f'{LLDP}.{c}' for c in (9, 10, 12))
LLDP_MANADDR = '1.0.8802.1.1.2.1.4.2.1.3'

TIPOS = ('switch', 'router', 'firewall', 'wlc', 'access_point', 'servidor', 'telefono', 'pc', 'impresora', 'desconocido')

# Número de empresa IANA (2º componente tras 1.3.6.1.4.1) -> (fabricante, tipo por defecto o None)
ENTERPRISES = {
    9: ('Cisco', None), 12356: ('Fortinet', None), 311: ('Microsoft', 'pc'), 8072: ('Net-SNMP (Linux/Unix)', 'servidor'),
    2021: ('UCD-SNMP (Linux/Unix)', 'servidor'), 6876: ('VMware', 'servidor'), 2: ('IBM', 'servidor'),
    674: ('Dell', 'servidor'), 232: ('HPE / Compaq', 'servidor'), 11: ('HP', None), 2636: ('Juniper', None),
    30065: ('Arista', 'switch'), 1916: ('Extreme Networks', 'switch'), 25506: ('H3C / HPE Comware', 'switch'),
    2011: ('Huawei', None), 14823: ('Aruba', None), 41112: ('Ubiquiti', None), 14988: ('MikroTik', 'router'),
    25461: ('Palo Alto Networks', 'firewall'), 2620: ('Check Point', 'firewall'), 3375: ('F5', 'servidor'),
    6486: ('Alcatel-Lucent', 'switch'), 4526: ('Netgear', 'switch'), 11863: ('TP-Link', 'switch'), 171: ('D-Link', 'switch'),
    1991: ('Brocade / Foundry', 'switch'), 1588: ('Brocade', 'switch'), 12325: ('pfSense / FreeBSD', 'firewall'),
    5003: ('Polycom', 'telefono'), 6889: ('Avaya', 'telefono'),
    1248: ('Epson', 'impresora'), 2435: ('Brother', 'impresora'), 641: ('Lexmark', 'impresora'), 367: ('Ricoh', 'impresora'),
    1602: ('Canon', 'impresora'), 253: ('Xerox', 'impresora'), 1347: ('Kyocera', 'impresora'),
    18334: ('Konica Minolta', 'impresora'), 236: ('Samsung', 'impresora'),
}

# Capacidades CDP (cdpCacheCapabilities, máscara de 32 bits)
CDP_ROUTER, CDP_TB, CDP_SRB, CDP_SWITCH, CDP_HOST, CDP_IGMP, CDP_REPEATER, CDP_PHONE = 0x01, 0x02, 0x04, 0x08, 0x10, 0x20, 0x40, 0x80
# Capacidades LLDP (BITS: el bit 0 es el más significativo del primer octeto)
LLDP_BITS = {0: 'other', 1: 'repeater', 2: 'bridge', 3: 'wlanAccessPoint', 4: 'router', 5: 'telephone', 6: 'docsis', 7: 'stationOnly'}


def snmp_text(val):
    if val is None:
        return ''
    try:
        raw = val.asOctets()
        if raw and all(32 <= b < 127 or b in (9, 10, 13) for b in raw):
            return raw.decode('ascii').strip()
        try:
            return raw.decode('utf-8').strip('\x00 ').strip()
        except Exception:
            return raw.hex()
    except Exception:
        return str(val.prettyPrint()).strip()


def octets(val):
    try:
        return bytes(val.asOctets())
    except Exception:
        return b''


def cdp_caps_text(mask):
    nombres = [(CDP_ROUTER, 'Router'), (CDP_TB, 'Trans-Bridge'), (CDP_SRB, 'SR-Bridge'), (CDP_SWITCH, 'Switch'),
               (CDP_HOST, 'Host'), (CDP_IGMP, 'IGMP'), (CDP_REPEATER, 'Repeater'), (CDP_PHONE, 'Phone')]
    return ','.join(n for b, n in nombres if mask & b) or 'ninguna'


def lldp_caps_set(raw):
    caps = set()
    for i, nombre in LLDP_BITS.items():
        byte, bit = divmod(i, 8)
        if byte < len(raw) and raw[byte] & (0x80 >> bit):
            caps.add(nombre)
    return caps


def norm_name(name):
    base = re.sub(r'\(.*?\)', '', str(name or '')).strip()
    return base.split('.')[0].strip().lower()


# ============================================================================
#  Recolección SNMP
# ============================================================================
async def _target(ip, timeout=2.0, retries=1):
    return await UdpTransportTarget.create((ip, 161), timeout=timeout, retries=retries)


async def walk(engine, auth, target, base_oid, max_rows=2000):
    root = tuple(int(x) for x in base_oid.split('.'))
    out = []
    try:
        async for err_ind, err_stat, _, vbs in bulk_walk_cmd(
                engine, auth, target, ContextData(), 0, 40, ObjectType(ObjectIdentity(base_oid)),
                lexicographicMode=False, ignoreNonIncreasingOid=True):
            if err_ind or err_stat:
                break
            for name, val in vbs:
                oid_t = tuple(name)
                if oid_t[:len(root)] != root or val.__class__.__name__ in ('NoSuchObject', 'NoSuchInstance', 'EndOfMibView'):
                    return out
                out.append((oid_t[len(root):], val))
                if len(out) >= max_rows:
                    return out
    except Exception:
        pass
    return out


async def gather_evidence(engine, ip, community):
    """Datos propios del equipo. Devuelve None si no responde SNMP."""
    auth = CommunityData(community, mpModel=1)
    try:
        target = await _target(ip)
        err_ind, err_stat, _, vbs = await get_cmd(
            engine, auth, target, ContextData(),
            ObjectType(ObjectIdentity(OID_SYS_DESCR)), ObjectType(ObjectIdentity(OID_SYS_OBJECT)),
            ObjectType(ObjectIdentity(OID_SYS_NAME)), ObjectType(ObjectIdentity(OID_SYS_SERVICES)))
    except Exception:
        return None
    if err_ind or err_stat or not vbs:
        return None

    def ok(v):
        return v.__class__.__name__ not in ('NoSuchObject', 'NoSuchInstance', 'EndOfMibView')

    descr, obj, name, services = (v if ok(v) else None for _, v in vbs)
    ev = {
        'sys_descr': snmp_text(descr) if descr is not None else '',
        'sys_object_id': str(obj) if obj is not None else '',
        'sys_name': snmp_text(name) if name is not None else '',
        'sys_services': None,
        'modelo_chasis': None,
    }
    try:
        ev['sys_services'] = int(services) if services is not None else None
    except Exception:
        pass

    # Modelo real del chasis: primera entidad con entPhysicalClass = chassis(3)
    clases = await walk(engine, auth, target, OID_ENT_CLASS, max_rows=600)
    chasis = [idx[0] for idx, v in clases if idx and _int(v) == 3]
    if chasis:
        try:
            _, _, _, mv = await get_cmd(engine, auth, target, ContextData(),
                                        ObjectType(ObjectIdentity(f'{OID_ENT_MODEL}.{chasis[0]}')),
                                        ObjectType(ObjectIdentity(f'{OID_ENT_DESCR}.{chasis[0]}')))
            modelo = snmp_text(mv[0][1]) if mv and ok(mv[0][1]) else ''
            descr_ch = snmp_text(mv[1][1]) if mv and len(mv) > 1 and ok(mv[1][1]) else ''
            ev['modelo_chasis'] = modelo or descr_ch or None
        except Exception:
            pass
    return ev


async def gather_neighbors(engine, ip, community):
    """Vecinos CDP/LLDP vistos por este equipo, con sus capacidades anunciadas."""
    auth = CommunityData(community, mpModel=1)
    try:
        target = await _target(ip, timeout=2.5, retries=1)
    except Exception:
        return []
    c_addr, c_dev, c_plat, c_caps, l_name, l_desc, l_caps, l_man = await asyncio.gather(
        walk(engine, auth, target, CDP_ADDR), walk(engine, auth, target, CDP_DEVID),
        walk(engine, auth, target, CDP_PLAT), walk(engine, auth, target, CDP_CAPS),
        walk(engine, auth, target, LLDP_SYSNAME), walk(engine, auth, target, LLDP_SYSDESC),
        walk(engine, auth, target, LLDP_CAP_ENABLED), walk(engine, auth, target, LLDP_MANADDR))

    cdp = {}
    for rows, campo in ((c_addr, 'ip'), (c_dev, 'name'), (c_plat, 'platform'), (c_caps, 'caps')):
        for idx, val in rows:
            if len(idx) < 2:
                continue
            e = cdp.setdefault(idx[:2], {'proto': 'CDP'})
            if campo == 'ip':
                raw = octets(val)
                e['ip'] = '.'.join(str(b) for b in raw) if len(raw) == 4 else None
            elif campo == 'caps':
                raw = octets(val)
                e['cdp_caps'] = int.from_bytes(raw[-4:], 'big') if raw else 0
            else:
                e[campo] = snmp_text(val)

    lldp = {}
    for rows, campo in ((l_name, 'name'), (l_desc, 'platform'), (l_caps, 'caps')):
        for idx, val in rows:
            if len(idx) < 3:
                continue
            e = lldp.setdefault(idx[1:3], {'proto': 'LLDP'})
            if campo == 'caps':
                e['lldp_caps'] = sorted(lldp_caps_set(octets(val)))
            else:
                e[campo] = snmp_text(val)
    for idx, _ in l_man:
        if len(idx) >= 9 and idx[3] == 1 and idx[4] == 4 and idx[1:3] in lldp:
            lldp[idx[1:3]].setdefault('ip', '.'.join(str(x) for x in idx[5:9]))

    return [n for n in list(cdp.values()) + list(lldp.values()) if n.get('ip') or n.get('name')]


def _int(v):
    try:
        return int(v)
    except Exception:
        return None


# ============================================================================
#  Reglas de clasificación
# ============================================================================
def _cisco_por_texto(t):
    """t = sysDescr + modelo en mayúsculas. Devuelve (tipo, motivo) o (None, None)."""
    reglas = [
        (r'ADAPTIVE SECURITY APPLIANCE|FIREPOWER|\bFTD\b|\bASA\d', 'firewall', 'plataforma ASA/Firepower'),
        (r'CISCO CONTROLLER|C9800|AIREOS|\bWLC\b|AIR-CT', 'wlc', 'controlador inalámbrico'),
        (r'AP SOFTWARE|\bAIR-[A-Z]*AP|C91\d\dAX|CISCO AP\b|ACCESS POINT', 'access_point', 'software de AP'),
        (r'IP PHONE|\bCP-\d{4}|SIP\d{2}|SCCP\d{2}', 'telefono', 'teléfono IP'),
        (r'NX-OS|NEXUS|\bN[3579]K-|\bN77-|\bC93\d{3}|\bC9372|\bN5K|\bN7K', 'switch', 'NX-OS / Nexus'),
        (r'CATALYST|C2960|C3560|C3750|C3650|C3850|C9200|C9300|C9400|C9500|C9600|CAT9K|CAT3K|C1000|C6500|C6800|S2T54|\bIE-\d|\bSG\d{3}|\bCBS\d{3}|\bWS-C', 'switch', 'familia Catalyst / switch Cisco'),
        (r'\bISR|\bASR\d|C1100|C1111|C8[23]\d{2}|\bCSR1000|C2900|C2911|C2921|C3900|C1900|C800|C890|\b1841\b|\b2811\b|\b2821\b|\b4331\b|\b4351\b|\b4431\b|\b4451', 'router', 'familia ISR/ASR (router)'),
        (r'UNIFIED COMMUNICATIONS|CUCM|UCS\b', 'servidor', 'servidor Cisco UC/UCS'),
    ]
    for rx, tipo, motivo in reglas:
        if re.search(rx, t):
            return tipo, motivo
    return None, None


def _por_servicios(s):
    """sysServices: 2 = enlace (bridge), 4 = red (router), 64 = aplicación (host)."""
    if s is None:
        return None, None
    l2, l3, l7 = bool(s & 2), bool(s & 4), bool(s & 64)
    if l2 and not l7:
        return 'switch', f'sysServices={s} (capa 2{" + 3" if l3 else ""})'
    if l3 and not l2 and not l7:
        return 'router', f'sysServices={s} (capa 3)'
    if l7 and not l2:
        return 'host', f'sysServices={s} (host / capa 7)'
    return None, None


def _por_vecinos(vistas):
    """vistas = lista de anuncios CDP/LLDP de este equipo hechos por sus vecinos.
    Se prefiere el anuncio con plataforma (más informativo) sobre el que solo trae capacidades."""
    vistas = sorted(vistas, key=lambda n: 0 if n.get('platform') else 1)
    for n in vistas:
        plat = (n.get('platform') or '').upper()
        caps = n.get('cdp_caps') or 0
        lcaps = set(n.get('lldp_caps') or [])
        fuente = f"{n['proto']} de {n.get('visto_por', '?')}"
        # La plataforma anunciada pesa más que las capacidades: un FortiGate anuncia solo
        # "router" por LLDP y un ISR con módulo de switch anuncia "Router,Switch".
        if re.search(r'FORTIGATE|FORTIOS|\bFGT|FORTIWIFI', plat):
            return 'firewall', 'alta', f'{fuente}: plataforma "{n.get("platform", "")}"'
        if re.search(r'FORTISWITCH', plat):
            return 'switch', 'alta', f'{fuente}: plataforma "{n.get("platform", "")}"'
        if caps & CDP_PHONE or 'telephone' in lcaps or re.search(r'IP PHONE|\bCP-\d{4}|PHONE', plat):
            return 'telefono', 'alta', f'{fuente}: capacidad Phone / plataforma "{n.get("platform", "")}"'
        if 'wlanAccessPoint' in lcaps or re.search(r'\bAIR-|C91\d\d|ACCESS POINT|\bAP\d{3,4}|FORTIAP', plat):
            return 'access_point', 'alta', f'{fuente}: AP / plataforma "{n.get("platform", "")}"'
        if re.search(r'VMWARE|ESX', plat):
            return 'servidor', 'alta', f'{fuente}: plataforma "{n.get("platform", "")}"'
        tipo_plat, motivo = _cisco_por_texto(plat)
        if tipo_plat:
            return tipo_plat, 'alta', f'{fuente}: plataforma "{n.get("platform", "")}" ({motivo})'
        if caps & CDP_SWITCH or 'bridge' in lcaps:
            return 'switch', 'media', f'{fuente}: capacidades {cdp_caps_text(caps) if caps else ",".join(sorted(lcaps))}'
        if caps & CDP_ROUTER or 'router' in lcaps:
            return 'router', 'media', f'{fuente}: capacidades {cdp_caps_text(caps) if caps else ",".join(sorted(lcaps))}'
        if caps & CDP_HOST or 'stationOnly' in lcaps:
            tipo = 'servidor' if re.search(r'LINUX|SERVER|UBUNTU|RED HAT', plat) else 'pc'
            return tipo, 'media', f'{fuente}: capacidad Host/Station, plataforma "{n.get("platform", "")}"'
    return None, None, None


def _por_nombre(nombre):
    n = (nombre or '').upper()
    if n.startswith('SEP'):
        return 'telefono', 'nombre empieza con SEP (convención Cisco de teléfonos)'
    if re.search(r'FORTI|^FG|^FW', n):
        return 'firewall', 'nombre sugiere FortiGate'
    if re.search(r'-AP\d|^AP-|^WAP', n):
        return 'access_point', 'nombre sugiere AP'
    if re.search(r'^PC-|^DESKTOP-|^LAPTOP-', n):
        return 'pc', 'nombre sugiere PC'
    if re.search(r'^XCC-|^IMM|IDRAC|^ILO', n):
        return 'servidor', 'nombre de controlador de servidor (XCC/IMM/iDRAC/iLO)'
    if re.search(r'SRV|SERVER|SERVIDOR', n):
        return 'servidor', 'nombre sugiere servidor'
    if re.search(r'^SW|SWITCH|CORE|NX|IDF|MDF', n):
        return 'switch', 'nombre sugiere switch'
    if re.search(r'RTR|ROUTER|GW', n):
        return 'router', 'nombre sugiere router'
    return None, None


def classify(nombre, ev, vistas):
    """Devuelve dict con tipo, fabricante, modelo, confianza y la evidencia usada."""
    evidencia = []
    fabricante, modelo = None, None

    if ev:
        oid = ev.get('sys_object_id') or ''
        m = re.match(r'^1\.3\.6\.1\.4\.1\.(\d+)', oid)
        ent = int(m.group(1)) if m else None
        fabricante, tipo_ent = ENTERPRISES.get(ent, (f'Empresa IANA {ent}' if ent else None, None))
        modelo = ev.get('modelo_chasis') or None
        texto = f"{ev.get('sys_descr', '')} {modelo or ''}".upper()
        if oid:
            evidencia.append(f'sysObjectID {oid} → {fabricante or "fabricante desconocido"}')
        if modelo:
            evidencia.append(f'modelo de chasis (ENTITY-MIB): {modelo}')

        tipo = None
        conf = 'alta'
        if ent == 12356:  # Fortinet: la familia está en el 7º componente del OID
            fam = oid.split('.')[7] if len(oid.split('.')) > 7 else ''
            tipo = {'101': 'firewall', '106': 'switch', '120': 'access_point', '102': 'servidor', '103': 'servidor'}.get(fam)
            if not tipo and 'FORTIGATE' in texto:
                tipo = 'firewall'
            if tipo:
                evidencia.append(f'familia Fortinet {fam} → {tipo}')
        elif ent == 9:
            tipo, motivo = _cisco_por_texto(texto)
            if tipo:
                evidencia.append(f'sysDescr/modelo Cisco: {motivo}')
            else:
                tipo, motivo = _por_servicios(ev.get('sys_services'))
                if tipo:
                    evidencia.append(f'Cisco sin familia reconocida; {motivo}')
                    conf = 'media'
        elif ent == 11:  # HP: impresoras JetDirect (11.2.3.9) o switches ProCurve (11.2.3.7)
            if oid.startswith('1.3.6.1.4.1.11.2.3.9'):
                tipo = 'impresora'
            elif oid.startswith('1.3.6.1.4.1.11.2.3.7'):
                tipo = 'switch'
            if tipo:
                evidencia.append(f'familia HP {oid[:24]} → {tipo}')
        elif ent == 311:
            tipo = 'servidor' if 'SERVER' in texto else 'pc'
            conf = 'media'
            evidencia.append('Windows: no siempre distingue servidor de PC')
        elif tipo_ent:
            tipo = tipo_ent
            conf = 'alta' if tipo_ent in ('impresora', 'firewall', 'telefono') else 'media'
            evidencia.append(f'tipo habitual de {fabricante}')

        if not tipo:
            tipo, motivo = _por_servicios(ev.get('sys_services'))
            if tipo:
                evidencia.append(motivo)
                conf = 'media'
        if tipo == 'host':
            tipo = 'servidor' if re.search(r'LINUX|SERVER|ESX|VMWARE|UNIX|BSD', texto) else 'pc'
            conf = 'media'
        if tipo:
            return _resultado(tipo, fabricante, modelo, conf, evidencia, 'snmp')

    # Sin SNMP propio (o sin decisión): lo que anuncian sus vecinos
    tipo, conf, motivo = _por_vecinos(vistas)
    if tipo:
        evidencia.append(motivo)
        plat = next((v.get('platform') for v in vistas if v.get('platform')), None)
        return _resultado(tipo, fabricante or _fabricante_de_plataforma(plat), modelo or plat, conf, evidencia, 'vecinos')

    tipo, motivo = _por_nombre(nombre)
    if tipo:
        evidencia.append(f'sin evidencia SNMP/CDP/LLDP; {motivo}')
        return _resultado(tipo, fabricante, modelo, 'baja', evidencia, 'nombre')

    evidencia.append('sin evidencia: el equipo no responde SNMP y ningún vecino lo anuncia por CDP/LLDP')
    return _resultado('desconocido', fabricante, modelo, 'ninguna', evidencia, 'ninguna')


def _fabricante_de_plataforma(p):
    p = (p or '').upper()
    if not p:
        return None
    if 'CISCO' in p or p.startswith(('WS-C', 'C9', 'AIR-', 'CP-', 'N')):
        return 'Cisco'
    if 'FORTI' in p:
        return 'Fortinet'
    return None


def _resultado(tipo, fabricante, modelo, confianza, evidencia, fuente):
    return {'tipo': tipo, 'fabricante': fabricante, 'modelo': (modelo or None) and str(modelo)[:120],
            'confianza': confianza, 'fuente': fuente, 'evidencia': evidencia}


# ============================================================================
#  Ejecución sobre el inventario
# ============================================================================
async def analizar_inventario(concurrency=20):
    conn = mysql.connector.connect(**db_config())
    cur = conn.cursor(dictionary=True)
    cur.execute(f"SELECT id, nombre, ip, estado, {COMUNIDAD_EFECTIVA_SQL} FROM dispositivos ORDER BY id")
    devices = cur.fetchall()
    cur.close()
    conn.close()

    engine = SnmpEngine()
    sem = asyncio.Semaphore(concurrency)

    async def uno(d):
        async with sem:
            ev = await gather_evidence(engine, d['ip'], d['comunidad_snmp'] or 'public')
            vecinos = await gather_neighbors(engine, d['ip'], d['comunidad_snmp'] or 'public') if ev else []
            return d, ev, vecinos

    resultados = await asyncio.gather(*(uno(d) for d in devices if d['ip']))

    # Índice de anuncios CDP/LLDP por IP y por nombre del equipo anunciado
    por_ip, por_nombre = {}, {}
    for d, _, vecinos in resultados:
        for n in vecinos:
            n = dict(n, visto_por=d['nombre'])
            if n.get('ip'):
                por_ip.setdefault(n['ip'], []).append(n)
            if n.get('name'):
                por_nombre.setdefault(norm_name(n['name']), []).append(n)

    salida = []
    for d, ev, _ in resultados:
        vistas = por_ip.get(d['ip'], []) + por_nombre.get(norm_name(d['nombre']), [])
        r = classify(d['nombre'], ev, vistas)
        r.update({'id': d['id'], 'nombre': d['nombre'], 'ip': d['ip'], 'estado': d['estado'],
                  'responde_snmp': ev is not None,
                  'sys_object_id': (ev or {}).get('sys_object_id') or None,
                  'sys_services': (ev or {}).get('sys_services'),
                  'sys_descr': ((ev or {}).get('sys_descr') or '')[:300] or None})
        salida.append(r)
    return salida


CONFIANZA_ORDEN = {'ninguna': 0, 'baja': 1, 'media': 2, 'alta': 3}


def guardar(resultados):
    """Persiste la clasificación. Nunca reemplaza una clasificación con evidencia por una
    peor (p. ej. si un equipo deja de responder SNMP un rato, conserva lo que se supo de él)."""
    conn = mysql.connector.connect(**db_config())
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT id, tipo_confianza FROM dispositivos")
    previo = {r['id']: r['tipo_confianza'] for r in cur.fetchall()}
    cur.close()
    cur = conn.cursor()
    escritos = conservados = 0
    for r in resultados:
        if CONFIANZA_ORDEN.get(r['confianza'], 0) < CONFIANZA_ORDEN.get(previo.get(r['id']) or 'ninguna', 0):
            conservados += 1
            continue
        cur.execute(
            """UPDATE dispositivos SET tipo_detectado=%s, tipo_confianza=%s, tipo_fuente=%s, tipo_evidencia=%s,
                   fabricante=COALESCE(%s, fabricante), modelo_detectado=COALESCE(%s, modelo_detectado),
                   sys_object_id=COALESCE(%s, sys_object_id), sys_services=COALESCE(%s, sys_services),
                   clasificado_en=NOW()
               WHERE id=%s""",
            (r['tipo'], r['confianza'], r['fuente'], json.dumps(r['evidencia'], ensure_ascii=False),
             r['fabricante'], r['modelo'], r['sys_object_id'], r['sys_services'], r['id']))
        escritos += 1
    conn.commit()
    cur.close()
    conn.close()
    return escritos, conservados


def main():
    ap = argparse.ArgumentParser(description='Clasificación de equipos por evidencia SNMP/CDP/LLDP')
    ap.add_argument('--audit', action='store_true', help='Solo lectura: imprime/guarda el resultado')
    ap.add_argument('--apply', action='store_true', help='Guarda la clasificación en la BD')
    ap.add_argument('--loop', type=int, default=0, help='Repetir cada N segundos (con --apply)')
    ap.add_argument('--out', help='Archivo JSON de salida')
    ap.add_argument('--concurrency', type=int, default=20)
    args = ap.parse_args()

    while True:
        inicio = time.time()
        res = asyncio.run(analizar_inventario(args.concurrency))
        if args.out:
            with open(args.out, 'w', encoding='utf-8') as fh:
                json.dump(res, fh, ensure_ascii=False, indent=1)
        resumen = {}
        for r in res:
            resumen[(r['tipo'], r['confianza'])] = resumen.get((r['tipo'], r['confianza']), 0) + 1
        for (t, c), n in sorted(resumen.items()):
            print(f'{t:14s} {c:8s} {n}')
        if args.apply:
            escritos, conservados = guardar(res)
            print(f'[{time.strftime("%H:%M:%S")}] Clasificación guardada: {escritos} equipos '
                  f'({conservados} conservan una clasificación previa con más evidencia) · {time.time() - inicio:.0f}s', flush=True)
        if not args.loop:
            break
        time.sleep(max(60, args.loop - (time.time() - inicio)))


if __name__ == '__main__':
    main()
