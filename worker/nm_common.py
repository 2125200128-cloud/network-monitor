"""
Utilidades compartidas por los workers: conexión a PostgreSQL con los datos del .env de Laravel,
fragmentos de SQL comunes y normalización de interfaces.
"""
import os
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


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
    except OSError:
        pass
    return env


def db_config():
    env = load_env()
    return {
        'host': env.get('DB_HOST', '127.0.0.1'),
        'port': int(env.get('DB_PORT') or 5432),
        'user': env.get('DB_USERNAME', 'postgres'),
        'password': env.get('DB_PASSWORD', ''),
        'database': env.get('DB_DATABASE', 'network_monitor'),
    }


# ----------------------------------------------------------------------------
#  Conexión a PostgreSQL (psycopg 3). Las consultas usan %s como marcador.
# ----------------------------------------------------------------------------
class ConexionBD:
    """Envoltura mínima sobre psycopg: conn.cursor(dictionary=True) devuelve filas como dict;
    commit(), rollback() y close() pasan directo a la conexión."""

    def __init__(self, conn):
        self._conn = conn

    def cursor(self, dictionary=False):
        from psycopg.rows import dict_row, tuple_row
        return self._conn.cursor(row_factory=dict_row if dictionary else tuple_row)

    def commit(self):
        self._conn.commit()

    def rollback(self):
        self._conn.rollback()

    def close(self):
        self._conn.close()

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        self.close()


def conectar(config=None, autocommit=False):
    """Conexión a la BD configurada en el .env (lanza la excepción del driver si falla)."""
    import psycopg
    cfg = config or db_config()
    conn = psycopg.connect(host=cfg['host'], port=cfg['port'], user=cfg['user'], password=cfg['password'],
                           dbname=cfg['database'], connect_timeout=10, autocommit=autocommit)
    return ConexionBD(conn)


def upsert(tabla, columnas, claves, actualizar):
    """INSERT … ON CONFLICT (claves) DO UPDATE.

    columnas:   columnas del INSERT; cada una recibe un %s, salvo las que se indiquen como
                {'col': 'NOW()'} (expresión SQL literal).
    claves:     columnas del índice único.
    actualizar: dict {columna: expresión} con los marcadores {nuevo} (valor que se intentó insertar)
                y {actual} (valor guardado). Ej.: {'model_name': 'COALESCE({nuevo}, {actual})',
                'uptime_str': "'Inaccesible'"}. Una lista de columnas equivale a {col: '{nuevo}'}.
    """
    nombres, valores = [], []
    for c in columnas:
        if isinstance(c, dict):
            (nombre, expr), = c.items()
            nombres.append(nombre)
            valores.append(expr)
        else:
            nombres.append(c)
            valores.append('%s')
    if isinstance(actualizar, (list, tuple)):
        actualizar = {c: '{nuevo}' for c in actualizar}
    sets = ', '.join(f"{col}=" + expr.format(nuevo=f'EXCLUDED.{col}', actual=f'{tabla}.{col}')
                     for col, expr in actualizar.items())
    return (f"INSERT INTO {tabla} ({', '.join(nombres)}) VALUES ({', '.join(valores)}) "
            f"ON CONFLICT ({', '.join(claves)}) DO UPDATE SET {sets}")


# Mayor contador que cabe en BIGINT (PostgreSQL no tiene enteros sin signo)
CONTADOR_MAX = 2 ** 63 - 1


def contador(valor):
    """Contador SNMP para guardar: None si no es un entero válido o está fuera de rango (contador roto del equipo)."""
    if valor is None:
        return None
    try:
        n = int(valor)
    except (TypeError, ValueError):
        return None
    return n if 0 <= n <= CONTADOR_MAX else None


def hace(cantidad, unidad='MINUTE'):
    """Expresión SQL "ahora menos N unidades" (unidad: SECOND, MINUTE, HOUR, DAY)."""
    return f"(NOW() - INTERVAL '{int(cantidad)} {unidad.lower()}s')"


def insertar_con_id(cursor, sql, params=()):
    """Ejecuta un INSERT y devuelve el id generado (RETURNING id)."""
    cursor.execute(sql.rstrip().rstrip(';') + ' RETURNING id', params)
    fila = cursor.fetchone()
    return fila['id'] if isinstance(fila, dict) else fila[0]


IFACE_ABBR = [
    (r'^hundredgige?|^hu(?=\d)', 'HundredGigE'),
    (r'^fortygigabitethernet|^fo(?=\d)', 'FortyGigabitEthernet'),
    (r'^twentyfivegige?|^twe(?=\d)', 'TwentyFiveGigE'),
    (r'^tengigabitethernet|^te(?=\d)', 'TenGigabitEthernet'),
    (r'^gigabitethernet|^gi(?=\d)', 'GigabitEthernet'),
    (r'^fastethernet|^fa(?=\d)', 'FastEthernet'),
    (r'^ethernet|^eth?(?=\d)', 'Ethernet'),
    (r'^port-channel|^po(?=\d)', 'Port-channel'),
]


def normalize_iface(name):
    """Expande abreviaturas Cisco para que Gi1/0/1 y GigabitEthernet1/0/1 sean la misma interfaz."""
    if not name:
        return ''
    s = str(name).strip()
    low = s.lower().replace(' ', '')
    for pattern, full in IFACE_ABBR:
        m = re.match(pattern, low)
        if m:
            return full + s.replace(' ', '')[m.end():]
    return s


# Comunidad SNMP efectiva de cada equipo (columna para un SELECT sobre `dispositivos`), por prioridad:
#   1. la fijada en la ficha técnica (comunidad_snmp_manual)
#   2. la de firewalls de Configuraciones (comunidad_snmp_firewall), si el equipo es firewall
#   3. la registrada en el alta / descubrimiento (comunidad_snmp)
# Misma regla que Dispositivo::comunidadConOrigen() en Laravel.
COMUNIDAD_EFECTIVA_SQL = (
    "COALESCE(NULLIF(TRIM(comunidad_snmp_manual), ''), "
    "CASE WHEN COALESCE(tipo_manual, tipo_detectado) = 'firewall' THEN "
    "NULLIF(TRIM((SELECT comunidad_snmp_firewall FROM configuraciones_generales ORDER BY id LIMIT 1)), '') END, "
    "comunidad_snmp) AS comunidad_snmp, "
    "(NULLIF(TRIM(comunidad_snmp_manual), '') IS NOT NULL) AS tiene_comunidad_manual, "
    "(COALESCE(tipo_manual, tipo_detectado) = 'firewall' OR EXISTS (SELECT 1 FROM telemetria_chasis tc "
    "WHERE tc.dispositivo_id = dispositivos.id AND UPPER(tc.model_name) LIKE 'FORTIGATE%')) AS es_firewall"
)


# ----------------------------------------------------------------------------
#  SNMPv3 de firewalls (Configuraciones → Motor SNMP). Las contraseñas las cifra Laravel con APP_KEY.
# ----------------------------------------------------------------------------
def descifrar_laravel(texto):
    """Descifra un valor del cast "encrypted" de Laravel (AES-256-CBC + HMAC-SHA256 con APP_KEY)."""
    import base64
    import hashlib
    import hmac
    import json
    from cryptography.hazmat.primitives import padding
    from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

    clave = load_env().get('APP_KEY', '')
    clave = base64.b64decode(clave[7:]) if clave.startswith('base64:') else clave.encode()
    carga = json.loads(base64.b64decode(texto))
    mac = hmac.new(clave, (carga['iv'] + carga['value']).encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(mac, carga.get('mac', '')):
        raise ValueError('MAC inválida: APP_KEY distinta a la que cifró el valor')
    descifrador = Cipher(algorithms.AES(clave), modes.CBC(base64.b64decode(carga['iv']))).decryptor()
    datos = descifrador.update(base64.b64decode(carga['value'])) + descifrador.finalize()
    quitar = padding.PKCS7(128).unpadder()
    return (quitar.update(datos) + quitar.finalize()).decode('utf-8')


_V3_CACHE = {'t': 0.0, 'cred': None}


def credencial_v3_firewall():
    """UsmUserData de los firewalls, o None si no hay SNMPv3 configurado. Se relee de la BD cada 60 s."""
    import time
    if time.time() - _V3_CACHE['t'] < 60:
        return _V3_CACHE['cred']
    cred = None
    try:
        with conectar() as conn:
            cur = conn.cursor(dictionary=True)
            cur.execute("SELECT snmpv3_usuario, snmpv3_nivel, snmpv3_auth_proto, snmpv3_auth_pass, snmpv3_priv_proto, snmpv3_priv_pass "
                        "FROM configuraciones_generales ORDER BY id LIMIT 1")
            fila = cur.fetchone()
        if fila and fila['snmpv3_usuario'] and fila['snmpv3_nivel'] == 'noAuthNoPriv':
            # Usuario sin contraseña (p. ej. el que ya traía el FortiGate): solo lectura, sin autenticación ni cifrado
            from pysnmp.hlapi.v3arch.asyncio import UsmUserData
            cred = UsmUserData(fila['snmpv3_usuario'])
        elif fila and fila['snmpv3_usuario'] and fila['snmpv3_auth_pass']:
            from pysnmp.hlapi.v3arch.asyncio import (
                UsmUserData, usmHMACMD5AuthProtocol, usmHMACSHAAuthProtocol, usmHMAC192SHA256AuthProtocol,
                usmHMAC384SHA512AuthProtocol, usmDESPrivProtocol, usmAesCfb128Protocol, usmAesCfb256Protocol)
            auth = {'MD5': usmHMACMD5AuthProtocol, 'SHA': usmHMACSHAAuthProtocol, 'SHA256': usmHMAC192SHA256AuthProtocol,
                    'SHA512': usmHMAC384SHA512AuthProtocol}[fila['snmpv3_auth_proto'] or 'SHA']
            priv = {'DES': usmDESPrivProtocol, 'AES': usmAesCfb128Protocol, 'AES256': usmAesCfb256Protocol}[fila['snmpv3_priv_proto'] or 'AES']
            if fila['snmpv3_nivel'] == 'authPriv' and fila['snmpv3_priv_pass']:
                cred = UsmUserData(fila['snmpv3_usuario'], descifrar_laravel(fila['snmpv3_auth_pass']),
                                   descifrar_laravel(fila['snmpv3_priv_pass']), authProtocol=auth, privProtocol=priv)
            else:
                cred = UsmUserData(fila['snmpv3_usuario'], descifrar_laravel(fila['snmpv3_auth_pass']), authProtocol=auth)
    except Exception as e:
        print(f"[SNMPv3] No se pudieron cargar las credenciales de firewalls: {e}", flush=True)
    _V3_CACHE.update(t=time.time(), cred=cred)
    return cred


def credencial_snmp(dev):
    """Credencial de un equipo leído con COMUNIDAD_EFECTIVA_SQL: la comunidad de su ficha manda; si no tiene y es
    firewall, SNMPv3 de Configuraciones (si está definido); si no, su comunidad v2c."""
    if not dev.get('tiene_comunidad_manual') and dev.get('es_firewall'):
        v3 = credencial_v3_firewall()
        if v3 is not None:
            return v3
    return dev.get('comunidad_snmp') or 'public'


def auth_snmp(credencial):
    """Objeto de autenticación de pysnmp: acepta una comunidad (texto, v2c) o un UsmUserData (v3)."""
    if isinstance(credencial, str):
        from pysnmp.hlapi.v3arch.asyncio import CommunityData
        return CommunityData(credencial, mpModel=1)
    return credencial


# ifType de IF-MIB que corresponden a puertos físicos Ethernet
PHYSICAL_IF_TYPES = {6, 62, 69, 117}
