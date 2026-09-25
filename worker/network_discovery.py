import sys
import os

# Garantizar variables de entorno de Windows para Winsock / asyncio _overlapped (evita WinError 10106)
if sys.platform == 'win32':
    if 'SystemRoot' not in os.environ and 'SYSTEMROOT' not in os.environ:
        os.environ['SystemRoot'] = 'C:\\Windows'
        os.environ['SYSTEMROOT'] = 'C:\\Windows'
    if 'WINDIR' not in os.environ:
        os.environ['WINDIR'] = 'C:\\Windows'
    if 'PATH' not in os.environ:
        os.environ['PATH'] = 'C:\\Windows\\system32;C:\\Windows;C:\\Windows\\System32\\Wbem'

import time
import json
import argparse
import asyncio
import ipaddress
import subprocess
import re
from concurrent.futures import ThreadPoolExecutor
import mysql.connector
from pysnmp.hlapi.v3arch.asyncio import *

# Forzar salida en UTF-8 para compatibilidad con Windows y Laravel
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8')

DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'network_monitor'
}

def get_db():
    return mysql.connector.connect(**DB_CONFIG)

def log(msg, log_list=None):
    timestamp = time.strftime("%H:%M:%S")
    formatted = f"[{timestamp}] {msg}"
    print(formatted, flush=True)
    if log_list is not None:
        log_list.append(formatted)

def icmp_ping_fast(host, timeout_ms=500):
    """Prueba rápida de ICMP Ping."""
    try:
        res = subprocess.run(
            ["ping", "-n", "1", "-w", str(timeout_ms), host],
            capture_output=True,
            text=True
        )
        return "TTL=" in res.stdout
    except Exception:
        return False

def parse_snmp_ip(val):
    """Decodifica direcciones IP devueltas por CDP/LLDP (OctetString, hex o texto)."""
    if val is None:
        return None
    try:
        if hasattr(val, 'asNumbers'):
            raw = bytes(val.asNumbers())
            if len(raw) == 4:
                return f"{raw[0]}.{raw[1]}.{raw[2]}.{raw[3]}"
        elif isinstance(val, (bytes, bytearray)):
            if len(val) == 4:
                return f"{val[0]}.{val[1]}.{val[2]}.{val[3]}"
    except Exception:
        pass

    s = str(val).strip()
    if s.startswith('0x') and len(s) == 10:
        try:
            b = bytes.fromhex(s[2:])
            return f"{b[0]}.{b[1]}.{b[2]}.{b[3]}"
        except Exception:
            pass

    try:
        ipaddress.ip_address(s)
        return s
    except Exception:
        return None

async def snmp_get_system_info(ip, community):
    """Consulta sysName (1.3.6.1.2.1.1.5.0) y sysDescr (1.3.6.1.2.1.1.1.0)."""
    snmpEngine = SnmpEngine()
    try:
        transport = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=1)
        oids = [
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.5.0')), # sysName
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1.0'))  # sysDescr
        ]
        errorIndication, errorStatus, errorIndex, varBinds = await get_cmd(
            snmpEngine,
            CommunityData(community, mpModel=1),
            transport,
            ContextData(),
            *oids
        )
        if not errorIndication and not errorStatus and len(varBinds) >= 2:
            sys_name = str(varBinds[0][1]).strip() or f"Dispositivo-{ip.replace('.', '-')}"
            sys_descr = str(varBinds[1][1]).strip()
            return {"name": sys_name, "model": sys_descr, "online": True}
    except Exception:
        pass
    return None

async def snmp_walk(ip, community, root_oid):
    """Ejecuta un walk SNMP asíncrono sobre una rama OID."""
    snmpEngine = SnmpEngine()
    results = []
    try:
        transport = await UdpTransportTarget.create((ip, 161), timeout=2.0, retries=1)
        current_oid = root_oid
        while True:
            res = await next_cmd(
                snmpEngine,
                CommunityData(community, mpModel=1),
                transport,
                ContextData(),
                ObjectType(ObjectIdentity(current_oid))
            )
            errorIndication, errorStatus, errorIndex, varBinds = res
            if errorIndication or errorStatus or not varBinds:
                break
            vb = varBinds[0]
            oid_str = str(vb[0])
            if not oid_str.startswith(root_oid):
                break
            results.append((oid_str, vb[1]))
            current_oid = oid_str
    except Exception as e:
        pass
    return results

async def discover_cdp_neighbors(ip, community, logs):
    """Descubre vecinos Cisco mediante la tabla cdpCacheTable (1.3.6.1.4.1.9.9.23.1.2.1.1)."""
    cdp_root = "1.3.6.1.4.1.9.9.23.1.2.1.1"
    raw_entries = await snmp_walk(ip, community, cdp_root)
    if not raw_entries:
        return []

    # cdpCacheAddressType = .3, cdpCacheAddress = .4, cdpCacheDeviceId = .6, cdpCacheDevicePort = .7, cdpCachePlatform = .8
    neighbors_map = {} # (if_idx, dev_idx) -> dict
    for oid, val in raw_entries:
        suffix = oid[len(cdp_root):].strip('.')
        parts = suffix.split('.')
        if len(parts) >= 3:
            col_id = parts[0]
            if_idx = parts[1]
            dev_idx = parts[2]
            key = f"{if_idx}.{dev_idx}"
            if key not in neighbors_map:
                neighbors_map[key] = {
                    "local_if_index": int(if_idx),
                    "ip": None,
                    "device_id": None,
                    "remote_port": None,
                    "platform": None,
                    "protocol": "CDP"
                }

            if col_id == "4": # cdpCacheAddress
                neighbors_map[key]["ip"] = parse_snmp_ip(val)
            elif col_id == "6": # cdpCacheDeviceId
                neighbors_map[key]["device_id"] = str(val).strip()
            elif col_id == "7": # cdpCacheDevicePort
                neighbors_map[key]["remote_port"] = str(val).strip()
            elif col_id == "8": # cdpCachePlatform
                neighbors_map[key]["platform"] = str(val).strip()

    valid_neighbors = [n for n in neighbors_map.values() if n["device_id"] or n["ip"]]
    if valid_neighbors:
        log(f"[{ip}] CDP: Encontrados {len(valid_neighbors)} vecinos activos.", logs)
    return valid_neighbors

async def discover_lldp_neighbors(ip, community, logs):
    """Descubre vecinos estándar IEEE 802.1AB mediante LLDP (lldpRemTable)."""
    lldp_root = "1.0.8802.1.1.2.1.4.1.1"
    raw_entries = await snmp_walk(ip, community, lldp_root)
    if not raw_entries:
        return []

    # .7 = lldpRemPortId, .8 = lldpRemPortDesc, .9 = lldpRemSysName, .10 = lldpRemSysDesc
    neighbors_map = {}
    for oid, val in raw_entries:
        suffix = oid[len(lldp_root):].strip('.')
        parts = suffix.split('.')
        if len(parts) >= 3:
            col_id = parts[0]
            time_mark = parts[1]
            local_port_num = parts[2]
            rem_idx = parts[3] if len(parts) > 3 else "1"
            key = f"{local_port_num}.{rem_idx}"
            if key not in neighbors_map:
                neighbors_map[key] = {
                    "local_if_index": int(local_port_num) if local_port_num.isdigit() else 1,
                    "ip": None,
                    "device_id": None,
                    "remote_port": None,
                    "platform": None,
                    "protocol": "LLDP"
                }
            if col_id == "7":
                neighbors_map[key]["remote_port"] = str(val).strip()
            elif col_id == "8" and not neighbors_map[key]["remote_port"]:
                neighbors_map[key]["remote_port"] = str(val).strip()
            elif col_id == "9":
                neighbors_map[key]["device_id"] = str(val).strip()
            elif col_id == "10":
                neighbors_map[key]["platform"] = str(val).strip()

    valid_neighbors = [n for n in neighbors_map.values() if n["device_id"] or n["remote_port"]]
    if valid_neighbors:
        log(f"[{ip}] LLDP: Encontrados {len(valid_neighbors)} vecinos activos.", logs)
    return valid_neighbors

def is_network_infrastructure_device(name, model):
    """
    Filtra teléfonos IP (SEP...), Access Points (AP), estaciones de trabajo
    y endpoints para que la base de datos SOLO contenga Switches, Routers y Firewalls.
    """
    clean_n = (name or "").upper()
    clean_m = (model or "").upper()
    
    # 1. Teléfonos IP Cisco (SEP + MAC address) y adaptadores ATA
    if clean_n.startswith("SEP") or clean_n.startswith("ATA"):
        return False
    if any(x in clean_m for x in ["IP PHONE", "CISCO IP PHONE", "PHONE", "UNIFIED IP", "VOIP"]):
        return False
        
    # 2. Access Points Wi-Fi
    if "-AP" in clean_n or clean_n.startswith("AP-") or clean_m.startswith("AIR-"):
        return False
        
    # 3. MAC addresses directas sin nombre de host
    if re.match(r'^[0-9a-fA-F]{12}$', clean_n):
        return False
        
    return True

def normalize_device_name(name):
    """Extrae el hostname base quitando números de serie o sufijos entre paréntesis."""
    if not name:
        return ""
    base = re.sub(r'\(.*?\)', '', name).strip()
    return base or name.strip()

def get_or_create_device(conn, ip, name, model, community):
    """Verifica si el dispositivo existe en MySQL, o lo crea de inmediato."""
    cur = conn.cursor(dictionary=True)
    norm_name = normalize_device_name(name)
    
    # 1. Buscar si ya existe por IP exacta
    cur.execute("SELECT id, nombre, ip, estado FROM dispositivos WHERE ip = %s LIMIT 1", (ip,))
    row = cur.fetchone()
    
    # 2. Si no existe por IP, buscar por nombre o nombre normalizado para evitar duplicados del mismo switch
    if not row and norm_name:
        cur.execute("SELECT id, nombre, ip, estado FROM dispositivos WHERE nombre = %s OR nombre LIKE %s LIMIT 1", (name, f"{norm_name}%"))
        row = cur.fetchone()
        
    if row:
        dev_id = row['id']
        cur.execute("UPDATE dispositivos SET estado='online', updated_at=NOW() WHERE id=%s", (dev_id,))
        conn.commit()
        return dev_id, False
    
    # Crear nuevo dispositivo descubierto
    clean_name = norm_name or name or f"Switch-{ip.replace('.', '-')}"
    cur.execute("""
        INSERT INTO dispositivos 
        (nombre, ip, comunidad_snmp, ubicacion, estado, ssh_port, created_at, updated_at)
        VALUES (%s, %s, %s, 'Auto-Descubierto por Crawler', 'online', 22, NOW(), NOW())
    """, (clean_name, ip, community))
    dev_id = cur.lastrowid
    
    # Crear telemetría de chasis inicial
    cur.execute("""
        INSERT INTO telemetria_chasis 
        (dispositivo_id, model_name, uptime_str, ram_total_mb, ram_used_mb, poe_total_budget_w, poe_used_w, poe_available_w, created_at, updated_at)
        VALUES (%s, %s, '0d 0h 0m', 512, 128, 0, 0, 0, NOW(), NOW())
    """, (dev_id, model or "Cisco Switch Descubierto"))
    
    conn.commit()
    return dev_id, True

def get_or_create_interface(conn, dev_id, port_name, if_index=None):
    """Busca o crea una interfaz de red para el dispositivo dado."""
    cur = conn.cursor(dictionary=True)
    clean_port = port_name or "GigabitEthernet1"
    
    cur.execute("SELECT id FROM interfaces_red WHERE dispositivo_id = %s AND (nombre = %s OR alias = %s) LIMIT 1", (dev_id, clean_port, clean_port))
    row = cur.fetchone()
    if row:
        return row['id']
    
    if if_index is None:
        cur.execute("SELECT COALESCE(MAX(if_index), 0) + 1 AS next_idx FROM interfaces_red WHERE dispositivo_id = %s", (dev_id,))
        r_idx = cur.fetchone()
        idx = r_idx['next_idx'] if r_idx else 1
    else:
        cur.execute("SELECT id FROM interfaces_red WHERE dispositivo_id = %s AND if_index = %s", (dev_id, if_index))
        if cur.fetchone():
            cur.execute("SELECT COALESCE(MAX(if_index), 0) + 1 AS next_idx FROM interfaces_red WHERE dispositivo_id = %s", (dev_id,))
            r_idx = cur.fetchone()
            idx = r_idx['next_idx'] if r_idx else 1
        else:
            idx = if_index

    # Determinar tipo de medio según nombre
    is_fiber = any(x in clean_port.lower() for x in ['te', 'tengig', 'sfp', 'fiber'])
    port_type = 'sfp_fiber' if is_fiber else 'copper_rj45'
    speed = 10000 if is_fiber else 1000
    
    cur.execute("""
        INSERT INTO interfaces_red 
        (dispositivo_id, if_index, nombre, velocidad_mbps, duplex, autoneg, port_type, admin_status, mode, is_poe, created_at, updated_at)
        VALUES (%s, %s, %s, %s, 'full', 1, %s, 'up', 'trunk', 0, NOW(), NOW())
    """, (dev_id, idx, clean_port, speed, port_type))
    iface_id = cur.lastrowid
    
    # Crear registro inicial de telemetría de interfaz
    cur.execute("""
        INSERT INTO telemetria_interfaces
        (interfaz_id, oper_status, in_octets, out_octets, bandwidth_util_pct, created_at, updated_at)
        VALUES (%s, 'up', 0, 0, 0.0, NOW(), NOW())
    """, (iface_id,))
    
    conn.commit()
    return iface_id

def get_or_create_link(conn, dev1_id, iface1_id, dev2_id, iface2_id, speed_mbps=1000):
    """Crea el enlace en enlaces_red si no existe entre el par de dispositivos."""
    cur = conn.cursor(dictionary=True)
    # Verificar en ambas direcciones para evitar duplicados
    cur.execute("""
        SELECT id FROM enlaces_red 
        WHERE (origen_dispositivo_id = %s AND destino_dispositivo_id = %s)
           OR (origen_dispositivo_id = %s AND destino_dispositivo_id = %s)
        LIMIT 1
    """, (dev1_id, dev2_id, dev2_id, dev1_id))
    row = cur.fetchone()
    if row:
        cur.execute("UPDATE enlaces_red SET estado='up', updated_at=NOW() WHERE id=%s", (row['id'],))
        conn.commit()
        return row['id'], False
    
    tipo_medio = 'fibra_10g' if speed_mbps >= 10000 else 'trunk'
    cur.execute("""
        INSERT INTO enlaces_red
        (origen_dispositivo_id, origen_interfaz_id, destino_dispositivo_id, destino_interfaz_id, tipo_medio, estado, velocidad_mbps, trafico_in_mbps, trafico_out_mbps, vlans_permitidas, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, 'up', %s, 0.0, 0.0, 'VLAN 1', NOW(), NOW())
    """, (dev1_id, iface1_id, dev2_id, iface2_id, tipo_medio, speed_mbps))
    link_id = cur.lastrowid
    conn.commit()
    return link_id, True

async def crawl_network_topology(seed_ip, community, max_depth, logs):
    """Ejecuta el crawler recursivo en cascada a partir de la IP semilla."""
    log(f"Iniciando Crawler de Topología desde IP semilla: {seed_ip} (Comunidad: {community}, Profundidad máx: {max_depth})", logs)
    
    conn = get_db()
    visited_ips = set()
    queue = [seed_ip]
    depth_map = {seed_ip: 0}
    
    discovered_devices = []
    discovered_links = []
    
    while queue:
        current_ip = queue.pop(0)
        current_depth = depth_map.get(current_ip, 0)
        
        if current_ip in visited_ips:
            continue
        visited_ips.add(current_ip)
        
        log(f"-> Analizando nodo [{current_ip}] (Nivel {current_depth})...", logs)
        
        # 1. Verificar conectividad ICMP / SNMP
        sys_info = await snmp_get_system_info(current_ip, community)
        if not sys_info:
            log(f"[{current_ip}] No responde a SNMPv2c con la comunidad '{community}'. Omitiendo recursión.", logs)
            continue
            
        dev_name = sys_info['name']
        dev_model = sys_info['model']
        local_dev_id, was_created = get_or_create_device(conn, current_ip, dev_name, dev_model, community)
        
        status_txt = "NUEVO REGISTRO" if was_created else "EXISTENTE"
        log(f"[{current_ip}] {status_txt}: {dev_name} ({dev_model}) [ID {local_dev_id}]", logs)
        discovered_devices.append({"id": local_dev_id, "ip": current_ip, "name": dev_name, "model": dev_model, "new": was_created})
        
        # 2. Descubrir vecinos CDP y LLDP
        cdp_neighbors = await discover_cdp_neighbors(current_ip, community, logs)
        lldp_neighbors = await discover_lldp_neighbors(current_ip, community, logs)
        all_neighbors = cdp_neighbors + lldp_neighbors
        
        log(f"[{current_ip}] Total de adyacencias detectadas: {len(all_neighbors)}", logs)
        
        for neighbor in all_neighbors:
            rem_ip = neighbor.get('ip')
            rem_name = neighbor.get('device_id') or "Vecino-Remoto"
            rem_port = neighbor.get('remote_port') or "GigabitEthernet1"
            rem_platform = neighbor.get('platform') or "Cisco Switch"
            loc_port_num = neighbor.get('local_if_index', 1)
            loc_port_name = f"GigabitEthernet{loc_port_num}"
            
            # FILTRO ANTI-SATURACIÓN: Ignorar teléfonos IP (SEP...), APs y endpoints
            if not is_network_infrastructure_device(rem_name, rem_platform):
                continue
            
            # Si no tenemos la IP directa en CDP/LLDP, intentar resolver por nombre o verificar si coincide con la semilla
            if not rem_ip:
                # Si el nombre ya está registrado en DB, buscar su IP
                cur = conn.cursor(dictionary=True)
                norm_rem = normalize_device_name(rem_name)
                cur.execute("SELECT ip FROM dispositivos WHERE nombre = %s OR nombre LIKE %s LIMIT 1", (rem_name, f"{norm_rem}%"))
                row = cur.fetchone()
                if row:
                    rem_ip = row['ip']
            
            if not rem_ip:
                log(f"[{current_ip}] Vecino '{rem_name}' en puerto {loc_port_name} sin dirección IP accesible. Enlace omitido.", logs)
                continue
                
            # Registrar dispositivo vecino
            rem_dev_id, rem_created = get_or_create_device(conn, rem_ip, rem_name, rem_platform, community)
            
            # Interfaces
            loc_iface_id = get_or_create_interface(conn, local_dev_id, loc_port_name, loc_port_num)
            rem_iface_id = get_or_create_interface(conn, rem_dev_id, rem_port)
            
            # Enlace físico
            link_id, link_created = get_or_create_link(conn, local_dev_id, loc_iface_id, rem_dev_id, rem_iface_id)
            link_status = "NUEVO CABLE" if link_created else "ENLACE SINCRONIZADO"
            log(f"[{current_ip}:{loc_port_name}] <===> [{rem_ip}:{rem_port}] ({link_status} [ID {link_id}])", logs)
            
            discovered_links.append({
                "id": link_id,
                "from": local_dev_id,
                "to": rem_dev_id,
                "from_port": loc_port_name,
                "to_port": rem_port,
                "new": link_created
            })
            
            # Encadenar recursión si no supera profundidad máxima
            if current_depth + 1 <= max_depth and rem_ip not in visited_ips and rem_ip not in queue:
                depth_map[rem_ip] = current_depth + 1
                queue.append(rem_ip)
                log(f"  + Agregado a la cola de rastreo: {rem_name} ({rem_ip})", logs)

    conn.close()
    return discovered_devices, discovered_links

def perform_cidr_sweep(cidr_str, community, logs):
    """Realiza un barrido masivo sobre un prefijo CIDR para descubrir equipos."""
    log(f"Iniciando barrido de subred CIDR: {cidr_str}...", logs)
    try:
        network = ipaddress.ip_network(cidr_str, strict=False)
        hosts = [str(ip) for ip in network.hosts()]
    except Exception as e:
        log(f"Error parseando CIDR '{cidr_str}': {e}", logs)
        return [], []

    log(f"Sondeando {len(hosts)} direcciones IP en paralelo con ICMP...", logs)
    
    # Ping concurrente rápido
    active_ips = []
    with ThreadPoolExecutor(max_workers=32) as executor:
        results = list(executor.map(lambda ip: ip if icmp_ping_fast(ip, timeout_ms=400) else None, hosts))
        active_ips = [ip for ip in results if ip is not None]

    log(f"Hosts activos detectados (ICMP Reply): {len(active_ips)} {active_ips}", logs)
    
    # Consultar SNMP sobre cada IP activa
    discovered_devices = []
    conn = get_db()
    for ip in active_ips:
        sys_info = asyncio.run(snmp_get_system_info(ip, community))
        if sys_info:
            dev_id, was_created = get_or_create_device(conn, ip, sys_info['name'], sys_info['model'], community)
            status_txt = "NUEVO SWITCH" if was_created else "ACTUALIZADO"
            log(f"[{ip}] SNMP Exitoso: {sys_info['name']} ({sys_info['model']}) [{status_txt}]", logs)
            discovered_devices.append({"id": dev_id, "ip": ip, "name": sys_info['name'], "model": sys_info['model'], "new": was_created})
        else:
            log(f"[{ip}] Responde a ping pero no a SNMP '{community}'. (Posible host/PC).", logs)

    # Ahora enlazar los switches detectados mediante CDP/LLDP
    discovered_links = []
    for dev in discovered_devices:
        cdp_n = asyncio.run(discover_cdp_neighbors(dev['ip'], community, logs))
        lldp_n = asyncio.run(discover_lldp_neighbors(dev['ip'], community, logs))
        for n in (cdp_n + lldp_n):
            rem_ip = n.get('ip')
            if rem_ip:
                rem_dev_id, _ = get_or_create_device(conn, rem_ip, n.get('device_id'), n.get('platform'), community)
                loc_iface = get_or_create_interface(conn, dev['id'], f"GigabitEthernet{n.get('local_if_index', 1)}")
                rem_iface = get_or_create_interface(conn, rem_dev_id, n.get('remote_port') or "GigabitEthernet1")
                lid, lnew = get_or_create_link(conn, dev['id'], loc_iface, rem_dev_id, rem_iface)
                discovered_links.append({"id": lid, "from": dev['id'], "to": rem_dev_id, "new": lnew})

    conn.close()
    return discovered_devices, discovered_links

def main():
    parser = argparse.ArgumentParser(description="Network Auto-Discovery Crawler (CDP/LLDP & CIDR)")
    parser.add_argument("--seed", default="192.168.1.254", help="Seed IP address")
    parser.add_argument("--community", default="public", help="SNMP Community string")
    parser.add_argument("--method", default="cdp_lldp", choices=["cdp_lldp", "cidr_sweep"], help="Discovery method")
    parser.add_argument("--cidr", default="192.168.1.0/24", help="Subnet CIDR for sweep")
    parser.add_argument("--max-depth", type=int, default=3, help="Max crawler depth")
    parser.add_argument("--json", action="store_true", help="Output summary in JSON format")

    args = parser.parse_args()
    logs = []
    
    log("=====================================================", logs)
    log(" MOTOR DE AUTO-DESCUBRIMIENTO Y MAPEADO DE TOPOLOGÍA ", logs)
    log("=====================================================", logs)

    start_time = time.time()
    devices = []
    links = []

    if args.method == "cdp_lldp":
        devices, links = asyncio.run(crawl_network_topology(args.seed, args.community, args.max_depth, logs))
    else:
        devices, links = perform_cidr_sweep(args.cidr, args.community, logs)

    elapsed = round(time.time() - start_time, 2)
    log("=====================================================", logs)
    log(f"Descubrimiento finalizado en {elapsed}s. Nodos: {len(devices)}, Enlaces: {len(links)}.", logs)
    log("=====================================================", logs)

    if args.json:
        payload = {
            "status": "success",
            "elapsed_seconds": elapsed,
            "method": args.method,
            "seed_ip": args.seed,
            "total_devices": len(devices),
            "new_devices": len([d for d in devices if d.get('new')]),
            "total_links": len(links),
            "new_links": len([l for l in links if l.get('new')]),
            "devices": devices,
            "links": links,
            "logs": logs
        }
        print("\n---JSON_OUTPUT_START---")
        print(json.dumps(payload, ensure_ascii=False, indent=2))
        print("---JSON_OUTPUT_END---")

if __name__ == '__main__':
    main()
