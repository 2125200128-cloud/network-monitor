"""
Utilidades compartidas por los workers: configuración de BD leída del .env de Laravel
y normalización de nombres de interfaz.
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
        'port': int(env.get('DB_PORT') or 3306),
        'user': env.get('DB_USERNAME', 'root'),
        'password': env.get('DB_PASSWORD', ''),
        'database': env.get('DB_DATABASE', 'network_monitor'),
    }


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


# ifType de IF-MIB que corresponden a puertos físicos Ethernet
PHYSICAL_IF_TYPES = {6, 62, 69, 117}
