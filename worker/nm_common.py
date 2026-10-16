"""
Utilidades compartidas por los workers: conexión a la BD leída del .env de Laravel (MySQL o
PostgreSQL según DB_CONNECTION), SQL portable entre ambos motores y normalización de interfaces.
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


def motor():
    """'pgsql' o 'mysql', según DB_CONNECTION del .env de Laravel."""
    return 'pgsql' if load_env().get('DB_CONNECTION', 'mysql').lower() in ('pgsql', 'postgres', 'postgresql') else 'mysql'


def es_pgsql():
    return motor() == 'pgsql'


def db_config():
    env = load_env()
    return {
        'host': env.get('DB_HOST', '127.0.0.1'),
        'port': int(env.get('DB_PORT') or (5432 if es_pgsql() else 3306)),
        'user': env.get('DB_USERNAME', 'postgres' if es_pgsql() else 'root'),
        'password': env.get('DB_PASSWORD', ''),
        'database': env.get('DB_DATABASE', 'network_monitor'),
    }


# ----------------------------------------------------------------------------
#  Conexión portable MySQL / PostgreSQL
#  Ambos drivers usan %s como marcador, así que las consultas parametrizadas no cambian.
#  Las diferencias de dialecto se resuelven con upsert(), hace() y insertar_con_id().
# ----------------------------------------------------------------------------
class ConexionBD:
    """Envoltura mínima para que el código use la misma API con mysql.connector y psycopg 3:
    conn.cursor(dictionary=True), commit(), rollback(), close()."""

    def __init__(self, conn, pg):
        self._conn = conn
        self.pg = pg

    def cursor(self, dictionary=False, buffered=None):
        if self.pg:
            # psycopg ya trae los resultados completos al cliente: "buffered" no aplica
            from psycopg.rows import dict_row, tuple_row
            return self._conn.cursor(row_factory=dict_row if dictionary else tuple_row)
        return self._conn.cursor(dictionary=dictionary, **({'buffered': buffered} if buffered is not None else {}))

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
    cfg = config or db_config()
    if es_pgsql():
        import psycopg
        conn = psycopg.connect(host=cfg['host'], port=cfg['port'], user=cfg['user'], password=cfg['password'],
                               dbname=cfg['database'], connect_timeout=10, autocommit=autocommit)
        return ConexionBD(conn, True)
    import mysql.connector
    return ConexionBD(mysql.connector.connect(**cfg, autocommit=autocommit), False)


def upsert(tabla, columnas, claves, actualizar):
    """INSERT … ON DUPLICATE KEY UPDATE (MySQL) / ON CONFLICT … DO UPDATE (PostgreSQL).

    columnas:   columnas del INSERT; cada una recibe un %s, salvo las que se indiquen como
                {'col': 'NOW()'} (expresión SQL literal).
    claves:     columnas del índice único (solo PostgreSQL las necesita).
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
    pg = es_pgsql()
    sets = ', '.join(
        f"{col}=" + expr.format(nuevo=(f'EXCLUDED.{col}' if pg else f'VALUES({col})'),
                                actual=(f'{tabla}.{col}' if pg else col))
        for col, expr in actualizar.items())
    sql = f"INSERT INTO {tabla} ({', '.join(nombres)}) VALUES ({', '.join(valores)}) "
    if pg:
        return sql + f"ON CONFLICT ({', '.join(claves)}) DO UPDATE SET {sets}"
    return sql + f"ON DUPLICATE KEY UPDATE {sets}"


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
    unidad = unidad.upper()
    if es_pgsql():
        return f"(NOW() - INTERVAL '{int(cantidad)} {unidad.lower()}s')"
    return f"(NOW() - INTERVAL {int(cantidad)} {unidad})"


def insertar_con_id(cursor, sql, params=()):
    """Ejecuta un INSERT y devuelve el id generado (lastrowid en MySQL, RETURNING id en PostgreSQL)."""
    if es_pgsql():
        cursor.execute(sql.rstrip().rstrip(';') + ' RETURNING id', params)
        fila = cursor.fetchone()
        return fila['id'] if isinstance(fila, dict) else fila[0]
    cursor.execute(sql, params)
    return cursor.lastrowid


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
    "comunidad_snmp) AS comunidad_snmp"
)


# ifType de IF-MIB que corresponden a puertos físicos Ethernet
PHYSICAL_IF_TYPES = {6, 62, 69, 117}
