"""
worker/network_discovery.py  —  Motor de Auto-Descubrimiento v2
=================================================================
Crawler de topología L2 (CDP + LLDP) y barrido CIDR basado en SNMPv2c.

Mejoras clave frente a v1:
  * BFS concurrente por niveles (asyncio + semáforo) en lugar de rastreo serie.
  * GETBULK (bulk_walk_cmd) sobre columnas específicas, con un único SnmpEngine.
  * Resolución real del puerto local vía IF-MIB::ifName (antes se guardaba el ifIndex).
  * IPs de vecinos LLDP desde lldpRemManAddrTable.
  * Deduplicación por identidad (sysName normalizado) -> evita duplicar un mismo
    equipo visto por varias IPs (p.ej. IPs punto-a-punto de enlaces).
  * Normalización de nombres de interfaz (Eth3/1 == Ethernet3/1).
  * Progreso en vivo a un archivo JSON (--job-file), cancelación (--cancel-file)
    y deadline global (--timeout) con resultados parciales.
  * Modo --probe para validar semilla/comunidad en ~1 s.
"""
import sys
import os

if sys.platform == 'win32':
    os.environ.setdefault('SystemRoot', 'C:\\Windows')
    os.environ.setdefault('SYSTEMROOT', os.environ['SystemRoot'])
    os.environ.setdefault('WINDIR', 'C:\\Windows')

import time
import json
import argparse
import asyncio
import ipaddress
import re
import traceback

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8')

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# ============================================================================
#  OIDs
# ============================================================================
OID_SYS_DESCR = '1.3.6.1.2.1.1.1.0'
OID_SYS_OBJECT = '1.3.6.1.2.1.1.2.0'
OID_SYS_UPTIME = '1.3.6.1.2.1.1.3.0'
OID_SYS_NAME = '1.3.6.1.2.1.1.5.0'

OID_IF_NAME = '1.3.6.1.2.1.31.1.1.1.1'           # index: ifIndex

CDP_BASE = '1.3.6.1.4.1.9.9.23.1.2.1.1'          # index: ifIndex.devIndex
CDP_ADDR, CDP_DEVID, CDP_PORT, CDP_PLAT = (f'{CDP_BASE}.{c}' for c in (4, 6, 7, 8))

LLDP_REM = '1.0.8802.1.1.2.1.4.1.1'              # index: timeMark.localPort.remIndex
LLDP_REM_PORTID, LLDP_REM_PORTDESC, LLDP_REM_SYSNAME, LLDP_REM_SYSDESC = (
    f'{LLDP_REM}.{c}' for c in (7, 8, 9, 10))
LLDP_REM_MANADDR = '1.0.8802.1.1.2.1.4.2.1.3'    # index: tm.lp.ri.addrSubtype.len.addr...
LLDP_LOC_PORTID = '1.0.8802.1.1.2.1.3.7.1.3'     # index: localPort
LLDP_LOC_PORTDESC = '1.0.8802.1.1.2.1.3.7.1.4'

# ============================================================================
#  Utilidades
# ============================================================================
IFACE_ABBR = [
    (r'^hundredgige?|^hu(?=\d)', 'HundredGigE'),
    (r'^fortygigabitethernet|^fo(?=\d)', 'FortyGigabitEthernet'),
    (r'^twentyfivegige?|^twe(?=\d)', 'TwentyFiveGigE'),
    (r'^tengigabitethernet|^te(?=\d)', 'TenGigabitEthernet'),
    (r'^gigabitethernet|^gi(?=\d)', 'GigabitEthernet'),
    (r'^fastethernet|^fa(?=\d)', 'FastEthernet'),
    (r'^ethernet|^eth?(?=\d)', 'Ethernet'),
    (r'^port-channel|^po(?=\d)', 'Port-channel'),
    (r'^mgmt', 'mgmt'),
]


def normalize_iface(name):
    """Expande abreviaturas Cisco para que Eth3/1 y Ethernet3/1 sean la misma interfaz."""
    if not name:
        return ''
    s = str(name).strip()
    low = s.lower().replace(' ', '')
    for pattern, full in IFACE_ABBR:
        m = re.match(pattern, low)
        if m:
            return full + s.replace(' ', '')[m.end():]
    return s


def norm_identity(name):
    """Identidad estable de un equipo: sin serial '(...)', sin dominio, minúsculas."""
    if not name:
        return ''
    base = re.sub(r'\(.*?\)', '', str(name)).strip()
    try:
        ipaddress.ip_address(base)
        return base
    except ValueError:
        pass
    return base.split('.')[0].strip().lower()


def clean_hostname(name):
    base = re.sub(r'\(.*?\)', '', str(name or '')).strip()
    return base or str(name or '').strip()


def snmp_text(val):
    """Convierte un valor SNMP a texto legible (o MAC hex si es binario)."""
    if val is None:
        return ''
    try:
        if hasattr(val, 'asOctets'):
            raw = val.asOctets()
            if raw and all(32 <= b < 127 for b in raw):
                return raw.decode('ascii').strip()
            if len(raw) == 6:
                return ':'.join(f'{b:02x}' for b in raw)
            try:
                return raw.decode('utf-8').strip('\x00 ').strip()
            except Exception:
                return raw.hex()
    except Exception:
        pass
    return str(val).strip()


def parse_ip_value(val):
    if val is None:
        return None
    try:
        if hasattr(val, 'asOctets'):
            raw = val.asOctets()
            if len(raw) == 4:
                return '.'.join(str(b) for b in raw)
    except Exception:
        pass
    s = str(val).strip()
    if s.startswith('0x') and len(s) == 10:
        try:
            return '.'.join(str(b) for b in bytes.fromhex(s[2:]))
        except Exception:
            return None
    try:
        ipaddress.IPv4Address(s)
        return s
    except Exception:
        return None


def looks_like_iface(s):
    return bool(s) and bool(re.search(r'[A-Za-z]', s)) and bool(re.search(r'\d', s)) and ':' not in s


NON_INFRA_PLATFORM = ('IP PHONE', 'PHONE', 'UNIFIED IP', 'VOIP', 'VMWARE', 'ESX', 'LINUX',
                      'WINDOWS', 'UBUNTU', 'RED HAT', 'HYPER-V', 'PRINTER')


def is_infrastructure(name, platform):
    n = (name or '').upper()
    p = (platform or '').upper()
    if not n or n in ('NOT ADVERTISED', 'UNKNOWN'):
        return False
    if n.startswith('SEP') or n.startswith('ATA'):
        return False
    if any(x in p for x in NON_INFRA_PLATFORM):
        return False
    if '-AP' in n or n.startswith('AP-') or p.startswith('AIR-') or 'AIRONET' in p:
        return False
    if re.match(r'^[0-9A-F]{12}$', n) or re.match(r'^([0-9A-F]{2}[:-]){5}[0-9A-F]{2}$', n):
        return False
    return True


def load_env():
    env = {}
    try:
        with open(os.path.join(BASE_DIR, '.env'), encoding='utf-8') as fh:
            for line in fh:
                line = line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                k, v = line.split('=', 1)
                env[k.strip()] = v.strip().strip('"').strip("'")
    except Exception:
        pass
    return env


# ============================================================================
#  Reporter de progreso (archivo JSON del job)
# ============================================================================
class Reporter:
    MAX_LOGS = 500

    def __init__(self, job_file=None, cancel_file=None, params=None):
        self.job_file = job_file
        self.cancel_file = cancel_file
        self.start = time.time()
        self._last_flush = 0.0
        self.state = {
            'job_id': os.path.splitext(os.path.basename(job_file))[0] if job_file else None,
            'status': 'running',
            'phase': 'init',
            'phase_label': 'Inicializando motor',
            'pid': os.getpid(),
            'params': params or {},
            'started_at': time.strftime('%Y-%m-%d %H:%M:%S'),
            'updated_at': time.time(),
            'elapsed_seconds': 0,
            'progress': {'depth': 0, 'max_depth': (params or {}).get('max_depth', 0),
                         'scanned': 0, 'queued': 0, 'responded': 0, 'unreachable': 0},
            'counters': {'devices': 0, 'new_devices': 0, 'links': 0, 'new_links': 0},
            'devices': [],
            'unreachable': [],
            'logs': [],
            'message': None,
            'error': None,
            'hints': [],
        }

    def log(self, msg, level='info'):
        ts = time.strftime('%H:%M:%S')
        print(f'[{ts}] {msg}', flush=True)
        self.state['logs'].append({'t': ts, 'level': level, 'msg': msg})
        if len(self.state['logs']) > self.MAX_LOGS:
            self.state['logs'] = self.state['logs'][-self.MAX_LOGS:]
        self.flush()

    def phase(self, key, label):
        self.state['phase'] = key
        self.state['phase_label'] = label
        self.flush(force=True)

    def cancelled(self):
        return bool(self.cancel_file) and os.path.exists(self.cancel_file)

    def flush(self, force=False):
        now = time.time()
        self.state['updated_at'] = now
        self.state['elapsed_seconds'] = round(now - self.start, 1)
        if not self.job_file or (not force and now - self._last_flush < 0.4):
            return
        self._last_flush = now
        tmp = f'{self.job_file}.{os.getpid()}.tmp'
        data = json.dumps(self.state, ensure_ascii=False, default=str)
        for _ in range(8):
            try:
                with open(tmp, 'w', encoding='utf-8') as fh:
                    fh.write(data)
                os.replace(tmp, self.job_file)
                return
            except PermissionError:
                time.sleep(0.05)
            except Exception:
                return

    def finish(self, status, message=None, error=None, hints=None):
        self.state['status'] = status
        self.state['phase'] = 'done'
        self.state['phase_label'] = 'Finalizado'
        self.state['message'] = message
        self.state['error'] = error
        if hints:
            self.state['hints'] = hints
        self.flush(force=True)


# ============================================================================
#  Capa SNMP (pysnmp 7, asyncio)
# ============================================================================
class Snmp:
    def __init__(self, community, timeout=1.0, retries=0):
        from pysnmp.hlapi.v3arch.asyncio import SnmpEngine, CommunityData, ContextData
        self.engine = SnmpEngine()
        self.auth = CommunityData(community, mpModel=1)
        self.ctx = ContextData()
        self.timeout = timeout
        self.retries = retries

    async def _target(self, ip, timeout=None, retries=None):
        from pysnmp.hlapi.v3arch.asyncio import UdpTransportTarget
        return await UdpTransportTarget.create(
            (ip, 161),
            timeout=self.timeout if timeout is None else timeout,
            retries=self.retries if retries is None else retries,
        )

    async def system_info(self, ip, timeout=None, retries=None):
        """Devuelve (info, error). info = {name, descr, object_id, uptime_s, latency_ms}."""
        from pysnmp.hlapi.v3arch.asyncio import get_cmd, ObjectType, ObjectIdentity
        t0 = time.time()
        try:
            tgt = await self._target(ip, timeout, retries)
            err_ind, err_stat, _, vbs = await get_cmd(
                self.engine, self.auth, tgt, self.ctx,
                ObjectType(ObjectIdentity(OID_SYS_NAME)),
                ObjectType(ObjectIdentity(OID_SYS_DESCR)),
                ObjectType(ObjectIdentity(OID_SYS_OBJECT)),
                ObjectType(ObjectIdentity(OID_SYS_UPTIME)),
            )
        except Exception as exc:
            return None, f'Error de transporte: {exc}'
        if err_ind:
            txt = str(err_ind)
            if 'timeout' in txt.lower() or 'No SNMP response' in txt:
                return None, 'Sin respuesta SNMP (timeout UDP/161)'
            return None, txt
        if err_stat:
            return None, f'Error SNMP: {err_stat.prettyPrint()}'
        vals = [v for _, v in vbs]
        name = snmp_text(vals[0]) or f"Dispositivo-{ip.replace('.', '-')}"
        try:
            uptime = int(vals[3]) // 100
        except Exception:
            uptime = None
        return {
            'name': name,
            'descr': snmp_text(vals[1]),
            'object_id': str(vals[2]),
            'uptime_s': uptime,
            'latency_ms': round((time.time() - t0) * 1000, 1),
        }, None

    async def walk(self, ip, root, max_rows=3000, timeout=None):
        """GETBULK walk de una columna. Devuelve [(index_tuple, value)]."""
        from pysnmp.hlapi.v3arch.asyncio import bulk_walk_cmd, ObjectType, ObjectIdentity
        root_t = tuple(int(x) for x in root.split('.'))
        out = []
        try:
            tgt = await self._target(ip, timeout or max(self.timeout, 1.5), 1)
            async for err_ind, err_stat, _, vbs in bulk_walk_cmd(
                    self.engine, self.auth, tgt, self.ctx, 0, 30,
                    ObjectType(ObjectIdentity(root)),
                    lexicographicMode=False, ignoreNonIncreasingOid=True):
                if err_ind or err_stat:
                    break
                for name, val in vbs:
                    oid_t = tuple(name)
                    if oid_t[:len(root_t)] != root_t:
                        return out
                    if val.__class__.__name__ in ('EndOfMibView', 'NoSuchObject', 'NoSuchInstance'):
                        return out
                    out.append((oid_t[len(root_t):], val))
                    if len(out) >= max_rows:
                        return out
        except Exception:
            pass
        return out


# ============================================================================
#  Inspección de un equipo (L2 adjacency)
# ============================================================================
async def inspect_device(snmp, ip, is_seed, probe_timeout):
    info, err = await snmp.system_info(ip, timeout=2.0 if is_seed else probe_timeout,
                                       retries=1 if is_seed else 0)
    if not info:
        return {'ip': ip, 'ok': False, 'reason': err}

    cols = await asyncio.gather(
        snmp.walk(ip, OID_IF_NAME),
        snmp.walk(ip, CDP_ADDR), snmp.walk(ip, CDP_DEVID),
        snmp.walk(ip, CDP_PORT), snmp.walk(ip, CDP_PLAT),
        snmp.walk(ip, LLDP_REM_PORTID), snmp.walk(ip, LLDP_REM_PORTDESC),
        snmp.walk(ip, LLDP_REM_SYSNAME), snmp.walk(ip, LLDP_REM_SYSDESC),
        snmp.walk(ip, LLDP_REM_MANADDR),
        snmp.walk(ip, LLDP_LOC_PORTID), snmp.walk(ip, LLDP_LOC_PORTDESC),
    )
    (if_name, c_addr, c_dev, c_port, c_plat,
     l_pid, l_pdesc, l_sname, l_sdesc, l_man, l_loc_id, l_loc_desc) = cols

    ifnames = {idx[0]: snmp_text(v) for idx, v in if_name if idx}

    # ---------------- CDP ----------------
    cdp = {}
    for rows, field in ((c_addr, 'ip'), (c_dev, 'name'), (c_port, 'remote_port'), (c_plat, 'platform')):
        for idx, val in rows:
            if len(idx) < 2:
                continue
            key = (idx[0], idx[1])
            entry = cdp.setdefault(key, {'protocol': 'CDP', 'if_index': idx[0]})
            entry[field] = parse_ip_value(val) if field == 'ip' else snmp_text(val)

    # ---------------- LLDP ----------------
    loc_ports = {}
    for idx, v in l_loc_desc:
        if idx:
            loc_ports[idx[0]] = snmp_text(v)
    for idx, v in l_loc_id:
        if idx and not looks_like_iface(loc_ports.get(idx[0], '')):
            loc_ports[idx[0]] = snmp_text(v)

    lldp = {}
    for rows, field in ((l_pid, 'port_id'), (l_pdesc, 'port_desc'), (l_sname, 'name'), (l_sdesc, 'platform')):
        for idx, val in rows:
            if len(idx) < 3:
                continue
            key = (idx[1], idx[2])
            entry = lldp.setdefault(key, {'protocol': 'LLDP', 'local_port_num': idx[1]})
            entry[field] = snmp_text(val)
    for idx, _ in l_man:
        # tm.lp.ri.subtype.len.a.b.c.d
        if len(idx) >= 9 and idx[3] == 1 and idx[4] == 4:
            key = (idx[1], idx[2])
            if key in lldp and not lldp[key].get('ip'):
                lldp[key]['ip'] = '.'.join(str(x) for x in idx[5:9])

    neighbors = []
    for e in cdp.values():
        local = ifnames.get(e['if_index']) or f"ifIndex{e['if_index']}"
        neighbors.append({
            'protocol': 'CDP', 'ip': e.get('ip'), 'name': e.get('name') or '',
            'platform': e.get('platform') or '', 'remote_port': normalize_iface(e.get('remote_port')),
            'local_port': normalize_iface(local), 'local_if_index': e['if_index'],
        })
    for e in lldp.values():
        lp = e['local_port_num']
        local = loc_ports.get(lp) if looks_like_iface(loc_ports.get(lp, '')) else None
        local = local or ifnames.get(lp) or f'Port{lp}'
        rport = e.get('port_id') if looks_like_iface(e.get('port_id', '')) else (e.get('port_desc') or e.get('port_id'))
        neighbors.append({
            'protocol': 'LLDP', 'ip': e.get('ip'), 'name': e.get('name') or '',
            'platform': e.get('platform') or '', 'remote_port': normalize_iface(rport),
            'local_port': normalize_iface(local), 'local_if_index': lp if lp in ifnames else None,
        })

    # Merge CDP + LLDP de la misma adyacencia (mismo puerto local + misma identidad)
    merged = {}
    for n in neighbors:
        key = (n['local_port'].lower(), norm_identity(n['name']) or n.get('ip') or n['remote_port'])
        if key in merged:
            m = merged[key]
            for f in ('ip', 'platform', 'remote_port'):
                if not m.get(f) and n.get(f):
                    m[f] = n[f]
            m['protocol'] = 'CDP+LLDP'
        else:
            merged[key] = dict(n)

    return {
        'ip': ip, 'ok': True, 'info': info,
        'neighbors': list(merged.values()),
        'cdp_count': len(cdp), 'lldp_count': len(lldp), 'if_count': len(ifnames),
    }


# ============================================================================
#  Persistencia MySQL (con caché en memoria)
# ============================================================================
class Store:
    def __init__(self, community):
        import mysql.connector
        env = load_env()
        self.conn = mysql.connector.connect(
            host=env.get('DB_HOST', '127.0.0.1'),
            port=int(env.get('DB_PORT', '3306') or 3306),
            user=env.get('DB_USERNAME', 'root'),
            password=env.get('DB_PASSWORD', 'root'),
            database=env.get('DB_DATABASE', 'network_monitor'),
            autocommit=True,
        )
        self.community = community
        self.by_ip = {}
        self.by_identity = {}
        self.ifaces = {}  # dev_id -> {'by_name': {norm_lower: (id, idx)}, 'by_idx': {idx: (id, name)}}
        cur = self.conn.cursor(dictionary=True)
        cur.execute('SELECT id, nombre, ip FROM dispositivos')
        for r in cur.fetchall():
            if r['ip']:
                self.by_ip[r['ip']] = r
            ident = norm_identity(r['nombre'])
            if ident:
                self.by_identity.setdefault(ident, r)
        cur.close()

    def lookup(self, ip=None, name=None):
        ident = norm_identity(name) if name else ''
        if ident and ident in self.by_identity:
            return self.by_identity[ident]
        if ip and ip in self.by_ip:
            return self.by_ip[ip]
        return None

    def upsert_device(self, ip, name, model, online=True):
        row = self.lookup(ip, name)
        cur = self.conn.cursor()
        if row:
            if online:
                cur.execute("UPDATE dispositivos SET estado='online', ultima_vez_visto=NOW(), updated_at=NOW() WHERE id=%s", (row['id'],))
            cur.close()
            return row['id'], False, row['ip']
        hostname = clean_hostname(name) or f"Switch-{ip.replace('.', '-')}"
        cur.execute(
            """INSERT INTO dispositivos (nombre, ip, comunidad_snmp, ubicacion, estado, ssh_port, ultima_vez_visto, created_at, updated_at)
               VALUES (%s, %s, %s, 'Auto-Descubierto por Crawler', %s, 22, NOW(), NOW(), NOW())""",
            (hostname[:255], ip, self.community, 'online' if online else 'offline'))
        dev_id = cur.lastrowid
        try:
            cur.execute(
                """INSERT INTO telemetria_chasis (dispositivo_id, model_name, created_at, updated_at)
                   VALUES (%s, %s, NOW(), NOW())""",
                (dev_id, (model[:255] if model else None)))
        except Exception:
            pass
        cur.close()
        row = {'id': dev_id, 'nombre': hostname, 'ip': ip}
        self.by_ip[ip] = row
        self.by_identity[norm_identity(hostname)] = row
        return dev_id, True, ip

    def _load_ifaces(self, dev_id):
        if dev_id in self.ifaces:
            return self.ifaces[dev_id]
        cur = self.conn.cursor(dictionary=True)
        cur.execute('SELECT id, if_index, nombre FROM interfaces_red WHERE dispositivo_id=%s', (dev_id,))
        cache = {'by_name': {}, 'by_idx': {}}
        for r in cur.fetchall():
            cache['by_name'][normalize_iface(r['nombre']).lower()] = (r['id'], r['if_index'])
            if r['if_index'] is not None:
                cache['by_idx'][r['if_index']] = (r['id'], r['nombre'])
        cur.close()
        self.ifaces[dev_id] = cache
        return cache

    def upsert_iface(self, dev_id, name, if_index=None):
        name = normalize_iface(name) or 'Desconocido'
        cache = self._load_ifaces(dev_id)
        key = name.lower()
        if key in cache['by_name']:
            return cache['by_name'][key][0]
        cur = self.conn.cursor()
        # Reparar interfaces creadas por v1 con el ifIndex como nombre (GigabitEthernet437256192)
        if if_index is not None and if_index in cache['by_idx']:
            iid, old = cache['by_idx'][if_index]
            if re.fullmatch(r'(GigabitEthernet|ifIndex|Port)\d+', old or '') and old.lower() != key:
                cur.execute('UPDATE interfaces_red SET nombre=%s, updated_at=NOW() WHERE id=%s', (name, iid))
                cache['by_name'].pop(normalize_iface(old).lower(), None)
                cache['by_name'][key] = (iid, if_index)
                cache['by_idx'][if_index] = (iid, name)
                cur.close()
                return iid
        if if_index is None or if_index in cache['by_idx']:
            used = set(cache['by_idx'].keys())
            if_index = (max(used) + 1) if used else 1
        low = name.lower()
        is_fiber = low.startswith(('tengig', 'twentyfive', 'fortygig', 'hundredgig')) or 'sfp' in low
        speed = 100000 if low.startswith('hundred') else 40000 if low.startswith('forty') else \
            25000 if low.startswith('twentyfive') else 10000 if is_fiber else 1000
        # Velocidad y tipo se deducen del nombre anunciado por CDP/LLDP; el estado real
        # (oper/admin, contadores) lo llenan snmp_poller.py y snmp_detail_collector.py.
        cur.execute(
            """INSERT INTO interfaces_red (dispositivo_id, if_index, nombre, velocidad_mbps, port_type,
               is_poe, created_at, updated_at)
               VALUES (%s, %s, %s, %s, %s, 0, NOW(), NOW())""",
            (dev_id, if_index, name[:255], speed, 'sfp_fiber' if is_fiber else 'copper_rj45'))
        iid = cur.lastrowid
        cur.close()
        cache['by_name'][key] = (iid, if_index)
        cache['by_idx'][if_index] = (iid, name)
        return iid

    def upsert_link(self, d1, i1, d2, i2, speed):
        cur = self.conn.cursor()
        cur.execute(
            """SELECT id FROM enlaces_red WHERE (origen_dispositivo_id=%s AND destino_dispositivo_id=%s)
               OR (origen_dispositivo_id=%s AND destino_dispositivo_id=%s) LIMIT 1""", (d1, d2, d2, d1))
        row = cur.fetchone()
        if row:
            cur.execute("UPDATE enlaces_red SET estado='up', updated_at=NOW() WHERE id=%s", (row[0],))
            cur.close()
            return row[0], False
        cur.execute(
            """INSERT INTO enlaces_red (origen_dispositivo_id, origen_interfaz_id, destino_dispositivo_id, destino_interfaz_id,
               tipo_medio, estado, velocidad_mbps, trafico_in_mbps, trafico_out_mbps, vlans_permitidas, created_at, updated_at)
               VALUES (%s, %s, %s, %s, %s, 'up', %s, 0, 0, NULL, NOW(), NOW())""",
            (d1, i1, d2, i2, 'fibra_10g' if speed >= 10000 else 'trunk', speed))
        lid = cur.lastrowid
        cur.close()
        return lid, True

    def close(self):
        try:
            self.conn.close()
        except Exception:
            pass


# ============================================================================
#  Orquestador
# ============================================================================
class Discovery:
    def __init__(self, args, rep):
        self.args = args
        self.rep = rep
        self.snmp = Snmp(args.community, timeout=args.probe_timeout, retries=0)
        self.store = Store(args.community)
        self.sem = asyncio.Semaphore(args.concurrency)
        self.deadline = time.time() + args.timeout
        self.seen_ids = set()          # identidades ya encoladas/visitadas
        self.seen_ips = set()
        self.device_ids = {}           # dev_id -> resumen
        self.link_ids = {}             # link_id -> nuevo?
        self.stopped_reason = None

    # -------- helpers --------
    def _p(self):
        return self.rep.state['progress']

    def _sync_counters(self):
        c = self.rep.state['counters']
        c['devices'] = len(self.device_ids)
        c['new_devices'] = sum(1 for d in self.device_ids.values() if d['new'])
        c['links'] = len(self.link_ids)
        c['new_links'] = sum(1 for v in self.link_ids.values() if v)
        self.rep.state['devices'] = sorted(self.device_ids.values(), key=lambda d: (d['depth'], d['name'].lower()))[:400]

    def _register_device(self, dev_id, created, ip, name, model, depth, reachable, role):
        prev = self.device_ids.get(dev_id)
        if prev:
            prev['reachable'] = prev['reachable'] or reachable
            return
        self.device_ids[dev_id] = {'id': dev_id, 'ip': ip, 'name': clean_hostname(name), 'model': (model or '')[:120],
                                   'new': created, 'depth': depth, 'reachable': reachable, 'role': role}

    async def _bounded_inspect(self, ip, is_seed):
        async with self.sem:
            if self.rep.cancelled():
                return {'ip': ip, 'ok': False, 'reason': 'Cancelado', 'cancelled': True}
            res = await inspect_device(self.snmp, ip, is_seed, self.args.probe_timeout)
            p = self._p()
            p['scanned'] += 1
            p['queued'] = max(0, p['queued'] - 1)
            if res['ok']:
                p['responded'] += 1
                self.rep.log(f"[{ip}] {res['info']['name']} respondió en {res['info']['latency_ms']} ms · "
                             f"CDP {res['cdp_count']} · LLDP {res['lldp_count']} · {res['if_count']} interfaces", 'ok')
            else:
                p['unreachable'] += 1
                self.rep.log(f"[{ip}] {res['reason']}", 'warn')
            return res

    async def _run_level(self, items, is_seed=False):
        """items: [(ip, depth)]. Ejecuta concurrente respetando el deadline global."""
        remaining = self.deadline - time.time()
        if remaining <= 0:
            return []
        tasks = [asyncio.ensure_future(self._bounded_inspect(ip, is_seed)) for ip, _ in items]
        done, pending = await asyncio.wait(tasks, timeout=remaining)
        for t in pending:
            t.cancel()
        if pending:
            self.stopped_reason = 'timeout'
            self.rep.log(f'Tiempo límite alcanzado: {len(pending)} equipos quedaron sin consultar.', 'warn')
        out = []
        for (ip, depth), t in zip(items, tasks):
            if t in done and not t.cancelled() and t.exception() is None:
                out.append((t.result(), depth))
        return out

    def _process_result(self, res, depth, crawl_deeper):
        """Persiste el nodo y sus adyacencias. Devuelve lista de (ip, depth) para el siguiente nivel."""
        ip = res['ip']
        info = res['info']
        dev_id, created, _ = self.store.upsert_device(ip, info['name'], info['descr'], online=True)
        self.seen_ids.add(norm_identity(info['name']))
        self._register_device(dev_id, created, ip, info['name'], info['descr'], depth, True,
                              'semilla' if depth == 0 else 'rastreado')
        tag = 'NUEVO' if created else 'existente'
        self.rep.log(f"[{ip}] {clean_hostname(info['name'])} registrado ({tag}, ID {dev_id})", 'node')

        nxt = []
        skipped = 0
        for n in res['neighbors']:
            name, platform = n['name'], n['platform']
            if not self.args.include_endpoints and not is_infrastructure(name, platform):
                skipped += 1
                continue
            known = self.store.lookup(n.get('ip'), name)
            # Preferir la IP de gestión ya conocida sobre la IP del enlace punto a punto
            rem_ip = (known or {}).get('ip') or n.get('ip')
            if not rem_ip:
                self.rep.log(f"  · {clean_hostname(name) or '?'} en {n['local_port']} no anuncia IP de gestión ({n['protocol']}).", 'muted')
                continue
            rem_id, rem_created, rem_ip = self.store.upsert_device(rem_ip, name or rem_ip, platform, online=False)
            self._register_device(rem_id, rem_created, rem_ip, name or rem_ip, platform, depth + 1, False, 'vecino')
            if rem_id == dev_id:
                continue
            li = self.store.upsert_iface(dev_id, n['local_port'], n.get('local_if_index'))
            ri = self.store.upsert_iface(rem_id, n['remote_port'] or 'Desconocido')
            low = (n['local_port'] or '').lower()
            speed = 100000 if low.startswith('hundred') else 40000 if low.startswith('forty') else \
                25000 if low.startswith('twentyfive') else 10000 if low.startswith('tengig') else 1000
            lid, lnew = self.store.upsert_link(dev_id, li, rem_id, ri, speed)
            if lid not in self.link_ids:
                self.link_ids[lid] = lnew
                self.rep.log(f"  ⇄ {n['local_port']} ↔ {clean_hostname(name)} {n['remote_port']} [{rem_ip}] "
                             f"({n['protocol']}{', nuevo enlace' if lnew else ''})", 'link')

            ident = norm_identity(name) or rem_ip
            if crawl_deeper and ident not in self.seen_ids and rem_ip not in self.seen_ips:
                self.seen_ids.add(ident)
                self.seen_ips.add(rem_ip)
                nxt.append((rem_ip, depth + 1))
        if skipped:
            self.rep.log(f'  · {skipped} adyacencias de endpoints (teléfonos/AP/servidores) filtradas.', 'muted')
        self._sync_counters()
        return nxt

    def _record_unreachable(self, res, depth):
        self.rep.state['unreachable'].append({'ip': res['ip'], 'reason': res.get('reason'), 'depth': depth})
        self.rep.state['unreachable'] = self.rep.state['unreachable'][-200:]

    # -------- modos --------
    async def crawl(self):
        a = self.args
        self.rep.phase('seed', f'Validando semilla {a.seed}')
        self.rep.log(f'Semilla {a.seed} · comunidad "{a.community}" · profundidad {a.max_depth} · '
                     f'concurrencia {a.concurrency} · timeout/equipo {a.probe_timeout}s', 'info')
        self.seen_ips.add(a.seed)
        self._p()['queued'] = 1
        level = [(a.seed, 0)]
        first = True
        while level:
            depth = level[0][1]
            self._p()['depth'] = depth
            if self.rep.cancelled():
                self.stopped_reason = 'cancelled'
                break
            if not first:
                self.rep.phase('crawl', f'Nivel {depth}: consultando {len(level)} equipos en paralelo')
                self.rep.log(f'── Nivel {depth}: {len(level)} equipos en paralelo ──', 'info')
            results = await self._run_level(level, is_seed=first)
            if first:
                seed_res = results[0][0] if results else None
                if not seed_res or not seed_res['ok']:
                    reason = (seed_res or {}).get('reason') or 'Sin respuesta'
                    raise SeedError(reason)
                self.rep.phase('crawl', 'Analizando adyacencias de la semilla')
            first = False
            nxt = []
            for res, d in results:
                if res.get('cancelled'):
                    continue
                if res['ok']:
                    nxt.extend(self._process_result(res, d, crawl_deeper=d + 1 <= a.max_depth))
                else:
                    self._record_unreachable(res, d)
            if len(self.device_ids) >= a.max_devices:
                self.rep.log(f'Límite de {a.max_devices} equipos alcanzado; se detiene la expansión.', 'warn')
                self.stopped_reason = 'limit'
                break
            if self.stopped_reason == 'timeout':
                break
            self._p()['queued'] = len(nxt)
            level = nxt
            self.rep.flush(force=True)

    async def sweep(self):
        a = self.args
        try:
            net = ipaddress.ip_network(a.cidr, strict=False)
        except ValueError as exc:
            raise SeedError(f'CIDR inválido: {exc}')
        hosts = [str(h) for h in net.hosts()] if net.num_addresses > 2 else [str(net.network_address)]
        if len(hosts) > 1024:
            raise SeedError(f'El segmento {a.cidr} tiene {len(hosts)} hosts. Máximo permitido: /22 (1022 hosts).')
        self.rep.phase('sweep', f'Barrido SNMP de {len(hosts)} hosts en {net}')
        self.rep.log(f'Barrido SNMP directo (no depende de ICMP) sobre {len(hosts)} hosts · concurrencia {a.concurrency}', 'info')
        self._p()['queued'] = len(hosts)
        self.seen_ips.update(hosts)

        async def probe(h):
            async with self.sem:
                if self.rep.cancelled():
                    return h, None
                info, _ = await self.snmp.system_info(h, timeout=a.probe_timeout, retries=0)
                p = self._p()
                p['scanned'] += 1
                p['queued'] = max(0, p['queued'] - 1)
                if info:
                    p['responded'] += 1
                    self.rep.log(f"[{h}] {info['name']} responde SNMP ({info['latency_ms']} ms)", 'ok')
                else:
                    self.rep.flush()
                return h, info

        tasks = [asyncio.ensure_future(probe(h)) for h in hosts]
        done, pending = await asyncio.wait(tasks, timeout=max(1, self.deadline - time.time()))
        for t in pending:
            t.cancel()
        alive = [t.result()[0] for t in done if not t.cancelled() and t.exception() is None and t.result()[1]]
        self._p()['unreachable'] = len(hosts) - len(alive)
        if self.rep.cancelled():
            self.stopped_reason = 'cancelled'
            return
        if not alive:
            raise SeedError(f'Ningún host de {net} respondió SNMP con la comunidad indicada.')

        self.rep.phase('crawl', f'Leyendo CDP/LLDP de {len(alive)} equipos gestionables')
        for h in alive:
            self.seen_ids.add(h)
        results = await self._run_level([(h, 0) for h in alive])
        for res, d in results:
            if res['ok']:
                self._process_result(res, d, crawl_deeper=False)
            else:
                self._record_unreachable(res, d)


class SeedError(Exception):
    pass


HINTS_SEED = [
    'Verifica que la comunidad SNMP sea correcta (sensible a mayúsculas).',
    'Confirma que la ACL SNMP del equipo permita la IP de este servidor.',
    'Revisa que UDP/161 no esté bloqueado por firewall entre este servidor y el equipo.',
    'Usa el botón "Probar" para validar la semilla antes de lanzar el rastreo.',
]


async def run_async(args, rep):
    disc = Discovery(args, rep)

    async def heartbeat():
        while True:
            await asyncio.sleep(1)
            rep.flush(force=True)
            if rep.cancelled() and not disc.stopped_reason:
                disc.stopped_reason = 'cancelled'

    hb = asyncio.ensure_future(heartbeat())
    try:
        if args.method == 'cdp_lldp':
            await disc.crawl()
        else:
            await disc.sweep()
    finally:
        hb.cancel()
        disc.store.close()
    return disc


def run_probe(args):
    async def go():
        snmp = Snmp(args.community)
        return await snmp.system_info(args.seed, timeout=2.0, retries=1)
    info, err = asyncio.run(go())
    print(json.dumps({'ok': bool(info), 'ip': args.seed, 'info': info, 'error': err}, ensure_ascii=False))


def main():
    ap = argparse.ArgumentParser(description='Network Auto-Discovery Crawler v2 (CDP/LLDP & CIDR)')
    ap.add_argument('--seed', default='192.168.1.254')
    ap.add_argument('--community', default='public')
    ap.add_argument('--method', default='cdp_lldp', choices=['cdp_lldp', 'cidr_sweep'])
    ap.add_argument('--cidr', default='192.168.1.0/24')
    ap.add_argument('--max-depth', type=int, default=3)
    ap.add_argument('--concurrency', type=int, default=24)
    ap.add_argument('--probe-timeout', type=float, default=1.2)
    ap.add_argument('--timeout', type=int, default=180, help='Deadline global en segundos')
    ap.add_argument('--max-devices', type=int, default=400)
    ap.add_argument('--include-endpoints', action='store_true')
    ap.add_argument('--job-file')
    ap.add_argument('--cancel-file')
    ap.add_argument('--probe', action='store_true', help='Solo valida la semilla/comunidad y sale')
    ap.add_argument('--json', action='store_true')
    args = ap.parse_args()

    if args.probe:
        run_probe(args)
        return

    params = {'method': args.method, 'seed_ip': args.seed, 'cidr': args.cidr, 'max_depth': args.max_depth,
              'concurrency': args.concurrency, 'probe_timeout': args.probe_timeout, 'timeout': args.timeout}
    rep = Reporter(args.job_file, args.cancel_file, params)
    rep.log('Motor de auto-descubrimiento v2 iniciado', 'info')
    rep.flush(force=True)

    disc = None
    try:
        disc = asyncio.run(run_async(args, rep))
        c = rep.state['counters']
        p = rep.state['progress']
        summary = (f"{c['devices']} equipos ({c['new_devices']} nuevos) · {c['links']} enlaces "
                   f"({c['new_links']} nuevos) · {p['responded']} respondieron SNMP, {p['unreachable']} sin respuesta")
        reason = disc.stopped_reason
        if reason == 'cancelled':
            rep.log('Rastreo cancelado por el usuario. Se conservaron los resultados parciales.', 'warn')
            rep.finish('cancelled', f'Cancelado · {summary}')
        elif reason in ('timeout', 'limit'):
            rep.finish('partial', f'Resultado parcial ({"tiempo límite" if reason == "timeout" else "límite de equipos"}) · {summary}')
        else:
            rep.log(f'Descubrimiento finalizado en {rep.state["elapsed_seconds"]}s', 'ok')
            rep.finish('completed', summary)
    except SeedError as exc:
        rep.log(f'La semilla no pudo ser consultada: {exc}', 'error')
        rep.finish('failed', 'No se pudo iniciar el rastreo', str(exc), HINTS_SEED)
    except Exception as exc:
        rep.log(f'Error interno: {exc}', 'error')
        rep.finish('failed', 'Error interno del motor de descubrimiento', f'{exc}\n{traceback.format_exc()[-1500:]}')

    if args.json:
        st = rep.state
        payload = {
            'status': st['status'], 'elapsed_seconds': st['elapsed_seconds'], 'method': args.method,
            'seed_ip': args.seed, 'total_devices': st['counters']['devices'],
            'new_devices': st['counters']['new_devices'], 'total_links': st['counters']['links'],
            'new_links': st['counters']['new_links'], 'devices': st['devices'],
            'unreachable': st['unreachable'], 'message': st['message'], 'error': st['error'],
            'logs': [f"[{l['t']}] {l['msg']}" for l in st['logs']],
        }
        print('\n---JSON_OUTPUT_START---')
        print(json.dumps(payload, ensure_ascii=False, indent=2, default=str))
        print('---JSON_OUTPUT_END---')


if __name__ == '__main__':
    main()
