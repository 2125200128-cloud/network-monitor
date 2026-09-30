"""
worker/metrics_collector.py
============================
Colector de Métricas Globales de Red.

Recolecta CPU, RAM, latencia y pérdida de paquetes de todos los dispositivos
de infraestructura activos en la base de datos, calcula promedios globales
y los persiste en la tabla `global_metrics`.

Diseñado para ejecutarse mediante un Cron Job cada 1 minuto:
    * * * * * cd /ruta/al/proyecto && python worker/metrics_collector.py
"""

import os
import sys
import logging
import subprocess
import asyncio
import json
from datetime import datetime
from dotenv import load_dotenv
import mysql.connector
from mysql.connector import Error
from pysnmp.hlapi.v3arch.asyncio import (
    SnmpEngine, CommunityData, UdpTransportTarget,
    ContextData, ObjectType, ObjectIdentity, get_cmd
)

sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)

# ==================== LOGGING ====================
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger('MetricsCollector')

# ==================== CARGA DE ENTORNO ====================
ENV_PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '.env')
load_dotenv(dotenv_path=ENV_PATH)

DB_CONFIG = {
    'host':     os.getenv('DB_HOST', '127.0.0.1'),
    'user':     os.getenv('DB_USERNAME', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_DATABASE', 'network_monitor'),
    'charset':  'utf8mb4',
}
SNMP_COMMUNITY = os.getenv('SNMP_COMMUNITY', 'public')


# ==================== BASE DE DATOS ====================
def get_db_connection():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            return conn
    except Error as e:
        logger.error(f"[DB] Error de conexion: {e}")
    return None


def fetch_infrastructure_devices(cursor):
    cursor.execute("""
        SELECT id, ip, nombre, comunidad_snmp
        FROM dispositivos
        WHERE estado = 'online'
          AND nombre NOT LIKE 'Nodo Generico%'
          AND nombre NOT LIKE 'Nodo Gen__rico%'
        ORDER BY id
    """)
    return cursor.fetchall()


def count_active_nodes(cursor):
    cursor.execute("SELECT COUNT(*) AS total FROM dispositivos WHERE estado = 'online'")
    row = cursor.fetchone()
    return row['total'] if row else 0


# ==================== ICMP PING ====================
def icmp_ping(host, timeout_ms=800):
    try:
        is_win = sys.platform.startswith('win')
        cmd = (
            ['ping', '-n', '2', '-w', '1000', host]
            if is_win else
            ['ping', '-c', '2', '-W', '1', host]
        )
        out = subprocess.run(cmd, capture_output=True, text=True, timeout=5)
        stdout = out.stdout.lower()
        if 'ttl=' in stdout or 'ttl =' in stdout:
            for line in stdout.splitlines():
                for marker in ('time=', 'tiempo=', 'tiempo<'):
                    if marker in line:
                        after = line.split(marker)[1].split('ms')[0].strip()
                        try:
                            return float(after)
                        except ValueError:
                            return 1.0
            return 1.0
    except Exception as e:
        logger.debug(f"[ICMP] Error en {host}: {e}")
    return None


# ==================== SNMP ====================
async def _snmp_get_device_metrics_async(ip, community):
    try:
        engine = SnmpEngine()
        target = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=1)
        err_ind, err_stat, _, var_binds = await get_cmd(
            engine,
            CommunityData(community, mpModel=1),
            target,
            ContextData(),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.3.0')),                # sysUpTime
            ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.109.1.1.1.1.8.1')), # CPU Nexus/IOS-XE
            ObjectType(ObjectIdentity('1.3.6.1.4.1.9.2.1.58.0')),           # CPU Catalyst
            ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.48.1.1.1.5.1')),    # Memoria Usada
            ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.48.1.1.1.6.1')),    # Memoria Libre
            ObjectType(ObjectIdentity('1.3.6.1.2.1.2.2.1.10.1')),           # ifInOctets
            ObjectType(ObjectIdentity('1.3.6.1.2.1.2.2.1.16.1')),           # ifOutOctets
        )
        if err_ind or err_stat:
            return None

        result = {}

        # CPU: intenta Nexus/IOS-XE primero, fallback Catalyst
        try:
            cpu = int(var_binds[1][1])
            result['cpu'] = cpu if 0 <= cpu <= 100 else None
        except Exception:
            result['cpu'] = None
        if result['cpu'] is None:
            try:
                cpu = int(var_binds[2][1])
                result['cpu'] = cpu if 0 <= cpu <= 100 else None
            except Exception:
                result['cpu'] = None

        # RAM
        try:
            m_used = int(var_binds[3][1])
            m_free = int(var_binds[4][1])
            total = m_used + m_free
            result['ram'] = round((m_used / total) * 100, 1) if total > 0 else None
        except Exception:
            result['ram'] = None

        # Trafico Mbps
        try:
            in_oct  = int(var_binds[5][1])
            out_oct = int(var_binds[6][1])
            result['traffic_mbps'] = round((in_oct + out_oct) * 8 / 1_000_000, 2)
        except Exception:
            result['traffic_mbps'] = None

        return result
    except Exception as e:
        logger.debug(f"[{ip}] Excepcion SNMP: {e}")
        return None


def get_device_metrics(ip, community):
    return asyncio.run(_snmp_get_device_metrics_async(ip, community))


# ==================== RECOLECCION GLOBAL ====================
def collect_global_metrics(devices, active_nodes):
    cpu_readings     = []
    ram_readings     = []
    latency_readings = []
    loss_readings    = []
    traffic_readings = []

    for dev in devices:
        ip        = dev['ip']
        community = dev['comunidad_snmp'] or SNMP_COMMUNITY
        nombre    = dev['nombre']

        ping_ms = icmp_ping(ip)
        if ping_ms is not None:
            latency_readings.append(ping_ms)
            loss_readings.append(0.0)
            logger.info(f"  [{ip}] ICMP OK -> {ping_ms:.1f} ms  ({nombre})")
        else:
            loss_readings.append(100.0)
            logger.warning(f"  [{ip}] ICMP FAIL  ({nombre})")

        metrics = get_device_metrics(ip, community)
        if metrics:
            if metrics.get('cpu') is not None:
                cpu_readings.append(metrics['cpu'])
            if metrics.get('ram') is not None:
                ram_readings.append(metrics['ram'])
            
            # GET TRAFFIC VIA TEST_SNMP.PY (Robusto y Probado)
            try:
                root_test_snmp = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'test_snmp.py')
                out = subprocess.run([sys.executable, root_test_snmp], capture_output=True, text=True, timeout=10)
                if out.returncode == 0:
                    for line in out.stdout.splitlines():
                        if line.startswith('{'):
                            data = json.loads(line)
                            if data.get('status') == 'success':
                                in_mbps = float(data.get('in_mbps', 0))
                                out_mbps = float(data.get('out_mbps', 0))
                                total_mbps = max(in_mbps, out_mbps) # Conservador: ancho de banda total usado
                                traffic_readings.append(total_mbps)
                                metrics['traffic_mbps'] = total_mbps
            except Exception as e:
                logger.debug(f"Error parseando test_snmp.py: {e}")

            logger.info(
                f"  [{ip}] SNMP OK -> CPU {metrics.get('cpu','N/A')} % | "
                f"RAM {metrics.get('ram','N/A')} % | "
                f"Traffic {metrics.get('traffic_mbps','N/A')} Mbps"
            )
        else:
            logger.warning(f"  [{ip}] SNMP sin respuesta")

    def avg(lst):
        return round(sum(lst) / len(lst), 2) if lst else 0.0

    total_sampled = len(devices)
    offline_count = sum(1 for l in loss_readings if l >= 100.0)
    port_sat = round((offline_count / total_sampled) * 100, 1) if total_sampled > 0 else 0.0

    return {
        'traffic_mbps':        avg(traffic_readings),
        'latency_ms':          avg(latency_readings),
        'packet_loss_pct':     avg(loss_readings),
        'cpu_avg_pct':         avg(cpu_readings),
        'ram_avg_pct':         avg(ram_readings),
        'active_nodes':        active_nodes,
        'port_saturation_pct': port_sat,
    }


# ==================== INSERCION EN BD ====================
def insert_global_metrics(cursor, metrics):
    query = """
        INSERT INTO global_metrics
            (recorded_at, traffic_mbps, latency_ms, packet_loss_pct,
             cpu_avg_pct, ram_avg_pct, active_nodes, port_saturation_pct)
        VALUES
            (NOW(), %s, %s, %s, %s, %s, %s, %s)
    """
    cursor.execute(query, (
        metrics['traffic_mbps'],
        metrics['latency_ms'],
        metrics['packet_loss_pct'],
        metrics['cpu_avg_pct'],
        metrics['ram_avg_pct'],
        metrics['active_nodes'],
        metrics['port_saturation_pct'],
    ))


# ==================== MAIN ====================
def main():
    logger.info("=" * 55)
    logger.info("  MetricsCollector - Inicio de ciclo de recoleccion")
    logger.info(f"  {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    logger.info("=" * 55)

    conn = get_db_connection()
    if not conn:
        logger.error("Sin conexion a BD. Abortando.")
        sys.exit(1)

    try:
        cursor = conn.cursor(dictionary=True)

        devices      = fetch_infrastructure_devices(cursor)
        active_nodes = count_active_nodes(cursor)
        logger.info(f"Dispositivos de infraestructura: {len(devices)}  |  Nodos activos totales: {active_nodes}")

        metrics = collect_global_metrics(devices, active_nodes)
        logger.info(
            f"Promedios -> CPU: {metrics['cpu_avg_pct']} % | "
            f"RAM: {metrics['ram_avg_pct']} % | "
            f"Latencia: {metrics['latency_ms']} ms | "
            f"Perdida: {metrics['packet_loss_pct']} % | "
            f"Trafico: {metrics['traffic_mbps']} Mbps | "
            f"SatPuertos: {metrics['port_saturation_pct']} %"
        )

        insert_global_metrics(cursor, metrics)
        conn.commit()
        logger.info("Metricas globales insertadas correctamente en 'global_metrics'.")

    except Error as e:
        logger.error(f"Error SQL: {e}")
        if conn.is_connected():
            conn.rollback()
    except Exception as e:
        logger.error(f"Error inesperado: {e}", exc_info=True)
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()
            logger.info("Conexion a BD cerrada.")


if __name__ == '__main__':
    main()
