import os
import sys
import logging
import ipaddress
import concurrent.futures
from dotenv import load_dotenv
import mysql.connector
from mysql.connector import Error
from ping3 import ping
from pysnmp.hlapi.v3arch.asyncio import *
import asyncio
if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

# ==================== CONFIGURACIÓN DE LOGGING ====================
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger('DeepDiscovery')

# ==================== LECTURA DE ENTORNO ====================
# Asumiendo que el script corre desde la carpeta 'worker', el .env está un nivel arriba
ENV_PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '.env')
load_dotenv(dotenv_path=ENV_PATH)

DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_DATABASE = os.getenv('DB_DATABASE', 'network_monitor')
DB_USERNAME = os.getenv('DB_USERNAME', 'root')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')

# Configuración de red centralizada
SNMP_COMMUNITY = os.getenv('SNMP_COMMUNITY', 'public')
DEFAULT_GATEWAY = os.getenv('DEFAULT_GATEWAY', '10.4.254.254')
DISCOVERY_SUBNETS = [s.strip() for s in os.getenv('DISCOVERY_SUBNETS', '10.4.254.0/24').split(',')]

# ==================== CONEXIÓN A BASE DE DATOS ====================
def connect_db():
    """Establece conexión con la base de datos MySQL de Laravel."""
    try:
        connection = mysql.connector.connect(
            host=DB_HOST,
            database=DB_DATABASE,
            user=DB_USERNAME,
            password=DB_PASSWORD
        )
        if connection.is_connected():
            logger.info("Conexión exitosa a la base de datos MySQL.")
            return connection
    except Error as e:
        logger.error(f"Error al conectar a MySQL: {e}")
        return None

# ==================== BARRIDO ICMP (PING SWEEP) ====================
def ping_host(ip):
    """Realiza un ping ICMP a una dirección IP. Devuelve la IP si responde, None en caso contrario."""
    try:
        # timeout en segundos (ej. 1 segundo = 1000ms)
        delay = ping(str(ip), timeout=1)
        if delay is not None and delay is not False:
            return str(ip)
    except Exception as e:
        logger.debug(f"Error haciendo ping a {ip}: {e}")
    return None

def ping_sweep(subnets, max_workers=50):
    """Realiza un barrido de ping asíncrono sobre una lista de subredes CIDR."""
    active_ips = []
    all_ips = []
    
    # Generar todas las IPs válidas a partir de los CIDRs
    for subnet in subnets:
        try:
            network = ipaddress.ip_network(subnet, strict=False)
            for ip in network.hosts():
                all_ips.append(ip)
        except ValueError as e:
            logger.error(f"CIDR inválido ({subnet}): {e}")
            
    logger.info(f"Iniciando barrido Ping Sweep sobre {len(all_ips)} direcciones IP ({max_workers} threads concurrentes)...")
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = {executor.submit(ping_host, ip): ip for ip in all_ips}
        for future in concurrent.futures.as_completed(futures):
            result = future.result()
            if result:
                active_ips.append(result)
                
    logger.info(f"Barrido completado. Se encontraron {len(active_ips)} IPs activas.")
    return active_ips

# ==================== LECTURA DE TABLA ARP ====================
def get_arp_table(gateway_ip, community='public', target_subnets=[]):
    return asyncio.run(_get_arp_table_async(gateway_ip, community, target_subnets))

async def _get_arp_table_async(gateway_ip, community, target_subnets):
    """Realiza un SNMP Walk a la tabla ARP del Gateway (ipNetToMediaTable)."""
    logger.info(f"[{gateway_ip}] Consultando Tabla ARP vía SNMP...")
    arp_entries = {}
    try:
        target = await UdpTransportTarget.create((gateway_ip, 161), timeout=2, retries=1)
        iterator = walk_cmd(
            SnmpEngine(),
            CommunityData(community, mpModel=1),
            target,
            ContextData(),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.4.22.1.2')), # ipNetToMediaPhysAddress
            lexicographicMode=False
        )
        async for errorIndication, errorStatus, errorIndex, varBinds in iterator:
            if errorIndication or errorStatus:
                break
            for varBind in varBinds:
                oid = str(varBind[0])
                ip_parts = oid.split('.')[-4:]
                ip = '.'.join(ip_parts)
                
                mac_bytes = varBind[1].asOctets()
                mac = ':'.join(f'{b:02x}' for b in mac_bytes).upper()
                
                in_target = False
                for subnet in target_subnets:
                    try:
                        if ipaddress.ip_address(ip) in ipaddress.ip_network(subnet, strict=False):
                            in_target = True
                            break
                    except ValueError:
                        pass
                
                if in_target:
                    arp_entries[ip] = mac
                        
        logger.info(f"[{gateway_ip}] ARP Descubrió {len(arp_entries)} direcciones MAC en subredes objetivo.")
        return arp_entries
    except Exception as e:
        logger.error(f"[{gateway_ip}] Excepción consultando ARP: {e}")
        return {}

# ==================== CONSULTAS SNMP BÁSICAS ====================
def get_snmp_data(ip, community='public'):
    return asyncio.run(_get_snmp_data_async(ip, community))

async def _get_snmp_data_async(ip, community):
    """Realiza una consulta SNMP GET para sysName, sysDescr y sysLocation."""
    logger.info(f"[{ip}] Consultando SNMP (Comunidad: {community})...")
    
    oids = [
        ObjectType(ObjectIdentity('1.3.6.1.2.1.1.5.0')), # sysName
        ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1.0')), # sysDescr
        ObjectType(ObjectIdentity('1.3.6.1.2.1.1.6.0'))  # sysLocation
    ]
    
    try:
        target = await UdpTransportTarget.create((ip, 161), timeout=1.2, retries=1)
        errorIndication, errorStatus, errorIndex, varBinds = await get_cmd(
            SnmpEngine(),
            CommunityData(community, mpModel=1), # v2c
            target,
            ContextData(),
            *oids
        )

        if errorIndication:
            logger.debug(f"[{ip}] SNMP Error: {errorIndication}")
            return None
        elif errorStatus:
            logger.debug(f"[{ip}] SNMP Error Status: {errorStatus.prettyPrint()}")
            return None
        else:
            result = {}
            for varBind in varBinds:
                oid = varBind[0].prettyPrint()
                value = varBind[1].prettyPrint()
                if '1.3.6.1.2.1.1.5.0' in oid:
                    result['sysName'] = value
                elif '1.3.6.1.2.1.1.1.0' in oid:
                    result['sysDescr'] = value
                elif '1.3.6.1.2.1.1.6.0' in oid:
                    result['sysLocation'] = value
            return result
    except Exception as e:
        logger.error(f"[{ip}] Excepción durante consulta SNMP: {e}")
        return None

# ==================== INSERCIÓN EN BASE DE DATOS ====================
def inferir_marca(sys_descr):
    """Infiere la marca/modelo basándose en el sysDescr."""
    descr = sys_descr.lower()
    if 'cisco' in descr:
        if 'nexus' in descr: return 'Cisco Nexus'
        if 'catalyst' in descr: return 'Cisco Catalyst'
        return 'Cisco'
    if 'infinet' in descr: return 'InfiNet'
    if 'linux' in descr: return 'Linux Server'
    if 'windows' in descr: return 'Windows Host'
    return 'Dispositivo SNMP'

def guardar_dispositivo(db_conn, nodo):
    """Guarda o actualiza el dispositivo en la base de datos MySQL (Upsert)."""
    if not db_conn:
        logger.error(f"[{nodo['ip']}] Fallo al guardar: Sin conexión a base de datos.")
        return
        
    try:
        cursor = db_conn.cursor()
        
        # Mapeo y Clasificación
        ip = nodo['ip']
        mac = nodo.get('mac')
        
        if nodo['type'] == 'infrastructure':
            snmp = nodo['snmp']
            sys_descr = snmp.get('sysDescr', '')
            sys_name = snmp.get('sysName', '')
            sys_location = snmp.get('sysLocation', '')
            
            marca_modelo = inferir_marca(sys_descr)
            nombre = sys_name if sys_name else f"{marca_modelo} ({ip})"
            ubicacion = sys_location if sys_location else "Ubicación desconocida"
            estado = 'online'
        else:
            # Nodo Genérico
            mac_str = f" | MAC: {mac}" if mac else ""
            nombre = f"Nodo Genérico / Computadora ({ip}{mac_str})"
            ubicacion = "Ubicación Pendiente (ARP/Ping)"
            estado = 'online'

        # Dado que mac_address ahora es UNIQUE, el motor asimilará el DHCP 
        # actualizando la IP dinámicamente si la MAC ya existe.
        query = """
            INSERT INTO dispositivos (nombre, ip, mac_address, ubicacion, estado, ultima_vez_visto, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                ip = VALUES(ip),
                ubicacion = VALUES(ubicacion),
                estado = VALUES(estado),
                ultima_vez_visto = NOW(),
                updated_at = NOW()
        """
        valores = (nombre, ip, mac, ubicacion, estado)
        
        cursor.execute(query, valores)
        db_conn.commit()
        logger.info(f"[{ip}] Guardado exitoso en BD -> Nombre: {nombre}, Ubicación: {ubicacion}")
        
    except Error as e:
        db_conn.rollback()
        logger.error(f"[{nodo['ip']}] Error SQL al hacer UPSERT: {e}")
    finally:
        if cursor:
            cursor.close()

# ==================== CAPA 2 (CAM MAPPING) ====================
def build_l2_topology(db_conn, switches, generic_nodes, community='public'):
    """Recorre los switches, lee sus tablas CAM y enlaza los genéricos a sus puertos."""
    if not switches or not generic_nodes:
        return
        
    logger.info("=== Iniciando Descubrimiento de Capa 2 (L2) ===")
    
    cursor = db_conn.cursor(dictionary=True)
    cursor.execute("SELECT id, ip, nombre FROM dispositivos WHERE nombre LIKE '%Nodo Genérico%'")
    db_generics = cursor.fetchall()
    
    mac_to_node_id = {}
    for gen in db_generics:
        if "MAC: " in gen['nombre']:
            mac = gen['nombre'].split("MAC: ")[1].replace(")", "").strip()
            mac_to_node_id[mac] = gen['id']
            
    if not mac_to_node_id:
        logger.info("No hay nodos genéricos con MAC en BD para enlazar.")
        cursor.close()
        return

    switches_ips = [s['ip'] for s in switches]
    if switches_ips:
        placeholders = ','.join(['%s'] * len(switches_ips))
        cursor.execute(f"SELECT id, ip FROM dispositivos WHERE ip IN ({placeholders})", tuple(switches_ips))
        switch_db_info = {row['ip']: row['id'] for row in cursor.fetchall()}
    else:
        switch_db_info = {}

    for switch in switches:
        sw_ip = switch['ip']
        sw_id = switch_db_info.get(sw_ip)
        if not sw_id:
            continue
            
        logger.info(f"[{sw_ip}] Leyendo tabla CAM y BRIDGE-MIB...")
        cam_table = asyncio.run(_walk_oid_async(sw_ip, community, '1.3.6.1.2.1.17.4.3.1.2'))
        bridge_map = asyncio.run(_walk_oid_async(sw_ip, community, '1.3.6.1.2.1.17.1.4.1.2'))
        ifnames = asyncio.run(_walk_oid_async(sw_ip, community, '1.3.6.1.2.1.31.1.1.1.1'))
        
        if not cam_table or not bridge_map or not ifnames:
            logger.debug(f"[{sw_ip}] No se pudo obtener la CAM o MIBs puenteadas.")
            continue
            
        macs_per_port = {}
        for oid_suffix, bport_val in cam_table.items():
            bport = str(bport_val)
            mac_hex = ":".join([f"{int(x):02x}" for x in oid_suffix.split('.')[-6:]]).upper()
            if bport not in macs_per_port:
                macs_per_port[bport] = []
            macs_per_port[bport].append(mac_hex)
            
        for bport, macs in macs_per_port.items():
            ifindex_val = bridge_map.get(f"1.3.6.1.2.1.17.1.4.1.2.{bport}")
            if not ifindex_val:
                continue
                
            ifindex = str(ifindex_val)
            ifname = ifnames.get(f"1.3.6.1.2.1.31.1.1.1.1.{ifindex}")
            if not ifname:
                ifname = f"Port-{ifindex}"
            
            if isinstance(ifname, bytes):
                ifname = ifname.decode(errors='ignore')
            elif hasattr(ifname, 'prettyPrint'):
                ifname = ifname.prettyPrint()
            else:
                ifname = str(ifname)
                
            if "vlan" in ifname.lower() or "cpu" in ifname.lower() or "lo" in ifname.lower():
                continue

            for mac in macs:
                target_node_id = mac_to_node_id.get(mac)
                if target_node_id:
                    guardar_enlace_l2(db_conn, sw_id, target_node_id, ifname, ifindex)
                    logger.info(f"[{sw_ip}] -> Enlazado endpoint MAC {mac} en puerto {ifname}")

    cursor.close()

def guardar_enlace_l2(db_conn, origen_id, destino_id, nombre_interfaz, ifindex):
    try:
        cursor = db_conn.cursor()
        
        cursor.execute("SELECT id FROM interfaces_red WHERE dispositivo_id = %s AND nombre = %s", (origen_id, nombre_interfaz))
        iface = cursor.fetchone()
        
        if iface:
            origen_interfaz_id = iface[0]
        else:
            cursor.execute("INSERT INTO interfaces_red (dispositivo_id, nombre, admin_status, if_index) VALUES (%s, %s, 'up', %s)", (origen_id, nombre_interfaz, ifindex))
            origen_interfaz_id = cursor.lastrowid
            
        cursor.execute("SELECT id FROM enlaces_red WHERE origen_dispositivo_id = %s AND destino_dispositivo_id = %s", (origen_id, destino_id))
        if cursor.fetchone():
            return
            
        query = """
            INSERT INTO enlaces_red (origen_dispositivo_id, origen_interfaz_id, destino_dispositivo_id, tipo_medio, estado, velocidad_mbps, trafico_in_mbps, trafico_out_mbps, created_at, updated_at)
            VALUES (%s, %s, %s, 'UTP', 'up', 1000, 0, 0, NOW(), NOW())
        """
        cursor.execute(query, (origen_id, origen_interfaz_id, destino_id))
        db_conn.commit()
    except Error as e:
        db_conn.rollback()
        logger.error(f"Error guardando enlace L2: {e}")
    finally:
        if cursor:
            cursor.close()

async def _walk_oid_async(ip, community, base_oid):
    results = {}
    try:
        target = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=1)
        iterator = walk_cmd(
            SnmpEngine(),
            CommunityData(community, mpModel=1),
            target,
            ContextData(),
            ObjectType(ObjectIdentity(base_oid)),
            lexicographicMode=False
        )
        async for errorIndication, errorStatus, errorIndex, varBinds in iterator:
            if errorIndication or errorStatus:
                break
            for varBind in varBinds:
                oid = str(varBind[0])
                val = varBind[1]
                results[oid] = val
        return results
    except Exception as e:
        return {}

# ==================== MOTOR PRINCIPAL ====================
def main():
    logger.info("=== Iniciando Deep Network Discovery Crawler ===")
    
    db_conn = connect_db()
    if not db_conn:
        logger.error("No se pudo conectar a la base de datos. Abortando.")
        sys.exit(1)
        
    # Configuración de red (léida desde .env vía variables globales)
    test_subnets = DISCOVERY_SUBNETS
    snmp_community = SNMP_COMMUNITY
    gateway_ip = DEFAULT_GATEWAY
    logger.info(f"Subredes objetivo: {test_subnets}")
    logger.info(f"Gateway: {gateway_ip}")
    logger.info(f"Comunidad SNMP: {'*' * len(snmp_community)}")
    
    # 1. Sweep ICMP
    active_ips_ping = ping_sweep(test_subnets, max_workers=50)
    
    # 2. Descubrimiento Pasivo (Tabla ARP)
    arp_entries = get_arp_table(gateway_ip, snmp_community, test_subnets)
    
    # Unir resultados
    all_active_ips = {}
    for ip in active_ips_ping:
        all_active_ips[ip] = None
    for ip, mac in arp_entries.items():
        all_active_ips[ip] = mac
        
    # 3. Consultas SNMP Concurrentes via Subprocess
    logger.info("Iniciando sondeo SNMP robusto para nodos detectados...")
    # Aseguramos que la IP del gateway esté siempre en los targets para no perderla si no responde a ping
    target_ips = list(set([ip for ip in all_active_ips.keys() if ip in active_ips_ping] + [gateway_ip]))
    
    def chunked(iterable, n):
        for i in range(0, len(iterable), n):
            yield iterable[i:i + n]

    snmp_results = {}
    import json
    for chunk in chunked(target_ips, 50):
        ips_str = ",".join(chunk)
        try:
            out = subprocess.run([sys.executable, 'worker/snmp_check.py', ips_str, snmp_community], 
                                 capture_output=True, text=True, timeout=15)
            if out.returncode == 0:
                bulk_res = json.loads(out.stdout)
                for ip, data in bulk_res.items():
                    if data.get('status') == 'ok':
                        snmp_results[ip] = data
                    else:
                        snmp_results[ip] = None
        except Exception as e:
            logger.error(f"Error en bloque SNMP: {e}")
            for ip in chunk:
                snmp_results[ip] = None
    
    # 4. Construcción de Nodos y Guardado en BD
    discovered_nodes = []
    
    for ip, mac in all_active_ips.items():
        snmp_info = snmp_results.get(ip)
        
        if snmp_info:
            logger.info(f"[{ip}] INFRAESTRUCTURA DETECTADA (SNMP OK)")
            nodo = {
                'ip': ip,
                'mac': mac,
                'type': 'infrastructure',
                'snmp': snmp_info
            }
        else:
            logger.info(f"[{ip}] NODO SILENCIOSO / GENÉRICO DETECTADO.")
            nodo = {
                'ip': ip,
                'mac': mac,
                'type': 'generic'
            }
            
        discovered_nodes.append(nodo)
        # Realizamos el Upsert transaccional
        guardar_dispositivo(db_conn, nodo)
            
    # L2 MAPPING: Descubrimiento de topología en Capa 2
    switches = [n for n in discovered_nodes if n['type'] == 'infrastructure']
    generics = [n for n in discovered_nodes if n['type'] == 'generic']
    build_l2_topology(db_conn, switches, generics, snmp_community)
            
    logger.info("=== Resumen de Descubrimiento ===")
    logger.info(f"Total IPs activas: {len(all_active_ips)}")
    logger.info(f"Nodos de Infraestructura (SNMP): {sum(1 for n in discovered_nodes if n['type'] == 'infrastructure')}")
    logger.info(f"Nodos Genéricos (PCs/Otros): {sum(1 for n in discovered_nodes if n['type'] == 'generic')}")
    
    # 4. Garbage Collection (Limpiar nodos desconectados)
    limpiar_nodos_inactivos(db_conn)
    
    db_conn.close()
    logger.info("Conexión a BD cerrada exitosamente.")

def limpiar_nodos_inactivos(db_conn):
    """Marca como OFFLINE los nodos genéricos que no se han visto en las últimas 24 horas."""
    try:
        cursor = db_conn.cursor()
        query = """
            UPDATE dispositivos 
            SET estado = 'offline', updated_at = NOW() 
            WHERE nombre LIKE 'Nodo Genérico%' 
            AND ultima_vez_visto < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND estado != 'offline'
        """
        cursor.execute(query)
        filas_afectadas = cursor.rowcount
        db_conn.commit()
        if filas_afectadas > 0:
            logger.info(f"Garbage Collection: {filas_afectadas} nodos genéricos marcados como OFFLINE por inactividad (>24h).")
    except Error as e:
        db_conn.rollback()
        logger.error(f"Error en limpiar_nodos_inactivos: {e}")
    finally:
        if cursor:
            cursor.close()

if __name__ == '__main__':
    main()
