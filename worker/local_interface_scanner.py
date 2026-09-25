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

import re
import time
import json
import socket
import asyncio
import argparse
import ipaddress
import subprocess
from concurrent.futures import ThreadPoolExecutor
import mysql.connector
from pysnmp.hlapi.v3arch.asyncio import *

# Forzar salida en UTF-8 para compatibilidad absoluta con Windows y Laravel
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

def detect_local_ethernet():
    """
    Detecta la interfaz de red física Ethernet por cable RJ-45 priorizando
    aquella con Gateway asignado y excluyendo adaptadores virtuales.
    """
    try:
        res = subprocess.run(['ipconfig', '/all'], capture_output=True, text=True)
        sections = re.split(r'\n(?=[A-Za-z0-9])', res.stdout)
        candidates = []
        
        for s in sections:
            header = s.splitlines()[0] if s.splitlines() else ''
            lower_s = s.lower()
            lower_h = header.lower()
            
            # Filtrar adaptadores virtuales, bluetooth, wifi o loopback
            is_virtual = any(x in lower_s for x in ['virtualbox', 'vbox', 'host-only', 'vmware', 'vethernet', 'wsl', 'bluetooth', 'loopback']) or '192.168.56.' in s
            is_wifi = 'wi-fi' in lower_h or 'wireless' in lower_s
            
            if 'ethernet' in lower_h and not is_virtual and not is_wifi:
                # Comprobar si los medios están desconectados
                is_disconnected = bool(re.search(r'Media State[.\s]+:\s*Media disconnected', s, re.I) or 
                                      re.search(r'Estado de los medios[.\s]+:\s*medios desconectados', s, re.I))
                
                ip_m = re.search(r'IPv4 Address[.\s]+:\s*([\d\.]+)', s) or re.search(r'Direcci[oó]n IPv4[.\s]+:\s*([\d\.]+)', s)
                mask_m = re.search(r'Subnet Mask[.\s]+:\s*([\d\.]+)', s) or re.search(r'M[aá]scara de subred[.\s]+:\s*([\d\.]+)', s)
                gw_m = re.search(r'Default Gateway[.\s]+:\s*([\d\.]+)', s) or re.search(r'Puerta de enlace predeterminada[.\s]+:\s*([\d\.]+)', s)
                
                if ip_m and not is_disconnected:
                    ip = ip_m.group(1).strip()
                    if not ip.startswith('169.254.'):
                        mask = mask_m.group(1).strip() if mask_m else '255.255.255.0'
                        gw = gw_m.group(1).strip() if gw_m else None
                        net = ipaddress.IPv4Network(f"{ip}/{mask}", strict=False)
                        
                        has_gw = 1 if gw else 0
                        is_exact_ethernet = 1 if re.match(r'^ethernet adapter ethernet:?$', header.strip(), re.I) else 0
                        score = (has_gw * 10) + (is_exact_ethernet * 5)
                        
                        candidates.append({
                            'score': score,
                            'name': header.strip().rstrip(':'),
                            'ip': ip,
                            'mask': mask,
                            'gateway': gw,
                            'cidr': str(net),
                            'connected': True
                        })
                elif is_disconnected:
                    candidates.append({
                        'score': -1,
                        'name': header.strip().rstrip(':') + ' (Cable Desconectado)',
                        'ip': None,
                        'mask': None,
                        'gateway': None,
                        'cidr': None,
                        'connected': False
                    })
                    
        if candidates:
            candidates.sort(key=lambda x: x['score'], reverse=True)
            return candidates[0]
    except Exception:
        pass
        
    return {
        "name": "Ethernet (Cable RJ-45 Desconectado)",
        "ip": None,
        "mask": None,
        "gateway": None,
        "cidr": None,
        "connected": False
    }

def detect_all_network_adapters():
    """
    Retorna todos los adaptadores físicos o inalámbricos activos,
    excluyendo interfaces virtuales (VirtualBox, VMware, WSL, loopback).
    """
    adapters = []
    try:
        res = subprocess.run(['ipconfig', '/all'], capture_output=True, text=True)
        sections = re.split(r'\n(?=[A-Za-z0-9])', res.stdout)
        for s in sections:
            header = s.splitlines()[0] if s.splitlines() else ''
            lower_s = s.lower()
            lower_h = header.lower()
            
            is_virtual = any(x in lower_s for x in ['virtualbox', 'vbox', 'host-only', 'vmware', 'vethernet', 'wsl', 'bluetooth', 'loopback']) or '192.168.56.' in s
            if is_virtual:
                continue
                
            is_disconnected = bool(re.search(r'Media State[.\s]+:\s*Media disconnected', s, re.I) or 
                                  re.search(r'Estado de los medios[.\s]+:\s*medios desconectados', s, re.I))
            if is_disconnected:
                continue
                
            ip_m = re.search(r'IPv4 Address[.\s]+:\s*([\d\.]+)', s) or re.search(r'Direcci[oó]n IPv4[.\s]+:\s*([\d\.]+)', s)
            mask_m = re.search(r'Subnet Mask[.\s]+:\s*([\d\.]+)', s) or re.search(r'M[aá]scara de subred[.\s]+:\s*([\d\.]+)', s)
            gw_m = re.search(r'Default Gateway[.\s]+:\s*([\d\.]+)', s) or re.search(r'Puerta de enlace predeterminada[.\s]+:\s*([\d\.]+)', s)
            
            if ip_m:
                ip = ip_m.group(1).strip()
                if not ip.startswith('169.254.') and not ip.startswith('127.'):
                    mask = mask_m.group(1).strip() if mask_m else '255.255.255.0'
                    gw = gw_m.group(1).strip() if gw_m else None
                    net = ipaddress.IPv4Network(f"{ip}/{mask}", strict=False)
                    is_ethernet = 'ethernet' in lower_h
                    is_wifi = 'wi-fi' in lower_h or 'wireless' in lower_s
                    
                    adapters.append({
                        'name': header.strip().rstrip(':'),
                        'type': 'Ethernet' if is_ethernet else ('Wi-Fi' if is_wifi else 'Red Local'),
                        'ip': ip,
                        'mask': mask,
                        'gateway': gw,
                        'cidr': str(net),
                        'connected': True,
                        'has_gateway': bool(gw)
                    })
    except Exception:
        pass
    return adapters

def get_arp_table_ips(interface_ip=None):
    """Extrae IPs de la tabla ARP local (arp -a)."""
    ips = []
    try:
        res = subprocess.run(["arp", "-a"], capture_output=True, text=True)
        lines = res.stdout.splitlines()
        current_iface = None
        for line in lines:
            line_str = line.strip()
            if line_str.startswith("Interface:") or line_str.startswith("Interfaz:"):
                parts = line_str.split()
                if len(parts) >= 2:
                    current_iface = parts[1]
                continue
            
            if interface_ip and current_iface and current_iface != interface_ip:
                continue
                
            m = re.search(r'^\s*([\d\.]+)\s+([a-fA-F0-9\-]{17})\s+(\w+)', line)
            if m:
                ip = m.group(1)
                if not ip.endswith('.255') and not ip.startswith('224.') and not ip.startswith('239.'):
                    ips.append(ip)
    except Exception:
        pass
    return list(dict.fromkeys(ips))

def icmp_ping_fast(host, timeout_ms=500):
    """Envía un paquete ICMP Echo Request rápido con timeout de 0.5s."""
    try:
        res = subprocess.run(
            ["ping", "-n", "1", "-w", str(timeout_ms), host],
            capture_output=True,
            text=True
        )
        return "TTL=" in res.stdout or "ttl=" in res.stdout
    except Exception:
        return False

def parse_snmp_ip(val):
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

async def snmp_get_system_info(ip, community="public"):
    """Consulta sysName (1.3.6.1.2.1.1.5.0) y sysDescr (1.3.6.1.2.1.1.1.0) vía SNMPv2c."""
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
            sys_name = str(varBinds[0][1]).strip() or f"Equipo-{ip.replace('.', '-')}"
            sys_descr = str(varBinds[1][1]).strip()
            return {"name": sys_name, "model": sys_descr, "online": True}
    except Exception:
        pass
    return None

async def snmp_walk(ip, community, root_oid):
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
    except Exception:
        pass
    return results

async def discover_cdp_neighbors(ip, community, logs):
    cdp_root = "1.3.6.1.4.1.9.9.23.1.2.1.1"
    raw = await snmp_walk(ip, community, cdp_root)
    if not raw:
        return []
    neighbors_map = {}
    for oid, val in raw:
        parts = oid[len(cdp_root):].strip('.').split('.')
        if len(parts) >= 3:
            col, if_idx, dev_idx = parts[0], parts[1], parts[2]
            k = f"{if_idx}.{dev_idx}"
            if k not in neighbors_map:
                neighbors_map[k] = {"local_if_index": int(if_idx), "ip": None, "device_id": None, "remote_port": None, "platform": None}
            if col == "4":
                neighbors_map[k]["ip"] = parse_snmp_ip(val)
            elif col == "6":
                neighbors_map[k]["device_id"] = str(val).strip()
            elif col == "7":
                neighbors_map[k]["remote_port"] = str(val).strip()
            elif col == "8":
                neighbors_map[k]["platform"] = str(val).strip()
    return [n for n in neighbors_map.values() if n["device_id"] or n["ip"]]

async def discover_lldp_neighbors(ip, community, logs):
    lldp_root = "1.0.8802.1.1.2.1.4.1.1"
    raw = await snmp_walk(ip, community, lldp_root)
    if not raw:
        return []
    neighbors_map = {}
    for oid, val in raw:
        parts = oid[len(lldp_root):].strip('.').split('.')
        if len(parts) >= 3:
            col, port, rem = parts[0], parts[2], parts[3] if len(parts) > 3 else "1"
            k = f"{port}.{rem}"
            if k not in neighbors_map:
                neighbors_map[k] = {"local_if_index": int(port) if port.isdigit() else 1, "ip": None, "device_id": None, "remote_port": None, "platform": None}
            if col == "7":
                neighbors_map[k]["remote_port"] = str(val).strip()
            elif col == "8" and not neighbors_map[k]["remote_port"]:
                neighbors_map[k]["remote_port"] = str(val).strip()
            elif col == "9":
                neighbors_map[k]["device_id"] = str(val).strip()
            elif col == "10":
                neighbors_map[k]["platform"] = str(val).strip()
    return [n for n in neighbors_map.values() if n["device_id"] or n["remote_port"]]

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
    cur = conn.cursor(dictionary=True)
    norm_name = normalize_device_name(name)
    
    # 1. Buscar si ya existe por IP exacta
    cur.execute("SELECT id, nombre, ip, estado FROM dispositivos WHERE ip = %s LIMIT 1", (ip,))
    row = cur.fetchone()
    
    # 2. Si no existe por IP, buscar por nombre o nombre normalizado
    if not row and norm_name:
        cur.execute("SELECT id, nombre, ip, estado FROM dispositivos WHERE nombre = %s OR nombre LIKE %s LIMIT 1", (name, f"{norm_name}%"))
        row = cur.fetchone()
        
    if row:
        cur.execute("UPDATE dispositivos SET estado='online', updated_at=NOW() WHERE id=%s", (row['id'],))
        conn.commit()
        return row['id'], False
    
    clean_name = norm_name or name or f"Dispositivo-{ip.replace('.', '-')}"
    cur.execute("""
        INSERT INTO dispositivos 
        (nombre, ip, comunidad_snmp, ubicacion, estado, ssh_port, created_at, updated_at)
        VALUES (%s, %s, %s, 'Auto-Descubierto Red', 'online', 22, NOW(), NOW())
    """, (clean_name, ip, community))
    dev_id = cur.lastrowid
    
    cur.execute("""
        INSERT INTO telemetria_chasis 
        (dispositivo_id, model_name, uptime_str, ram_total_mb, ram_used_mb, poe_total_budget_w, poe_used_w, poe_available_w, created_at, updated_at)
        VALUES (%s, %s, '0d 0h 0m', 512, 128, 0, 0, 0, NOW(), NOW())
    """, (dev_id, model or "Switch Cisco Detectado"))
    conn.commit()
    return dev_id, True

def get_or_create_interface(conn, dev_id, port_name, if_index=None):
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
    
    is_fiber = any(x in clean_port.lower() for x in ['te', 'sfp', 'fiber'])
    port_type = 'sfp_fiber' if is_fiber else 'copper_rj45'
    speed = 10000 if is_fiber else 1000
    
    cur.execute("""
        INSERT INTO interfaces_red 
        (dispositivo_id, if_index, nombre, velocidad_mbps, duplex, autoneg, port_type, admin_status, mode, is_poe, created_at, updated_at)
        VALUES (%s, %s, %s, %s, 'full', 1, %s, 'up', 'trunk', 0, NOW(), NOW())
    """, (dev_id, idx, clean_port, speed, port_type))
    iface_id = cur.lastrowid
    
    cur.execute("""
        INSERT INTO telemetria_interfaces
        (interfaz_id, oper_status, in_octets, out_octets, bandwidth_util_pct, created_at, updated_at)
        VALUES (%s, 'up', 0, 0, 0.0, NOW(), NOW())
    """, (iface_id,))
    conn.commit()
    return iface_id

def get_or_create_link(conn, dev1_id, iface1_id, dev2_id, iface2_id, speed_mbps=1000):
    cur = conn.cursor(dictionary=True)
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

def scan_local_physical_network(community="public", max_workers=50, target_subnet=None, logs=None):
    if logs is None:
        logs = []
        
    start_time = time.time()
    log("=======================================================", logs)
    log(" ESCANEO Y RASTREO DE RED / SUBRED LOCAL INICIADO ", logs)
    log("=======================================================", logs)
    
    available_adapters = detect_all_network_adapters()
    
    # 1. Determinar el objetivo o interfaz a escanear
    if target_subnet and target_subnet.strip():
        target_clean = target_subnet.strip()
        try:
            if '/' in target_clean:
                network = ipaddress.ip_network(target_clean, strict=False)
                seed_ip = None
            else:
                # Es una IP sola (ej. 10.4.155.1 o 192.168.1.254), calcular su /24
                network = ipaddress.ip_network(f"{target_clean}/24", strict=False)
                seed_ip = target_clean
                
            cidr = str(network)
            local_ip = seed_ip or str(network.network_address)
            gateway_ip = seed_ip
            adapter_name = f"Subred/IP Personalizada ({target_clean})"
            eth_info = {
                "name": adapter_name,
                "ip": target_clean,
                "mask": str(network.netmask),
                "gateway": gateway_ip,
                "cidr": cidr,
                "connected": True
            }
            log(f"Objetivo especificado por el usuario: '{target_clean}' -> Rango calculado: {cidr}", logs)
        except Exception as e:
            log(f"Error interpretando objetivo '{target_subnet}': {e}. Usando autodetección...", logs)
            target_subnet = None

    if not target_subnet or not target_subnet.strip():
        # Autodetección: preferir Ethernet física, si no tiene IP usar Wi-Fi u otra activa
        eth_info = detect_local_ethernet()
        if not eth_info.get('connected') or not eth_info.get('ip'):
            if available_adapters:
                eth_info = available_adapters[0]
                
        if not eth_info.get('connected') or not eth_info.get('ip'):
            log("ALERTA: No se detectó ninguna interfaz activa con dirección IP asignada.", logs)
            log("Por favor conecta el cable RJ-45 o especifica una subred/IP para escanear.", logs)
            return {
                "status": "warning",
                "message": "Sin interfaz de red activa. Especifica una subred o conecta un cable RJ-45.",
                "adapter": eth_info,
                "available_adapters": available_adapters,
                "elapsed_seconds": round(time.time() - start_time, 2),
                "active_hosts_count": 0,
                "ping_alive_hosts": [],
                "total_devices": 0,
                "new_devices": 0,
                "total_links": 0,
                "new_links": 0,
                "devices": [],
                "links": [],
                "logs": logs
            }
            
        local_ip = eth_info['ip']
        gateway_ip = eth_info['gateway']
        cidr = eth_info['cidr']
        adapter_name = eth_info['name']
        network = ipaddress.ip_network(cidr, strict=False)
        log(f"Interfaz de red detectada: {adapter_name}", logs)
        log(f"IP Local Asignada: {local_ip} | Puerta de Enlace (Gateway): {gateway_ip or 'No detectado'}", logs)
        log(f"Subred calculada para barrido: {cidr}", logs)

    # 2. Barrido concurrente de 50 hilos sobre el rango
    hosts = [str(h) for h in network.hosts() if str(h) != local_ip]
    if gateway_ip and gateway_ip in hosts:
        hosts.remove(gateway_ip)
        hosts.insert(0, gateway_ip) # Colocar el gateway o semilla de primero
        
    log(f"Iniciando barrido concurrente ({max_workers} hilos) sobre {len(hosts)} hosts...", logs)
    
    active_hosts = []
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        results = list(executor.map(lambda ip: ip if icmp_ping_fast(ip, timeout_ms=500) else None, hosts))
        active_hosts = [ip for ip in results if ip is not None]
        
    log(f"Hosts activos que respondieron a ICMP: {len(active_hosts)} {active_hosts}", logs)
    
    # 3. Sondeo SNMP a candidatos (hosts activos ICMP + Gateway predeterminado + tabla ARP)
    conn = get_db()
    managed_devices = []
    discovered_links = []
    
    snmp_candidates = list(active_hosts)
    if gateway_ip and gateway_ip not in snmp_candidates and gateway_ip != local_ip:
        snmp_candidates.insert(0, gateway_ip)
        
    arp_ips = get_arp_table_ips(local_ip)
    for a_ip in arp_ips:
        if a_ip not in snmp_candidates and a_ip != local_ip:
            snmp_candidates.append(a_ip)
            
    log(f"Candidatos evaluados para sondeo SNMP (Ping + Gateway + ARP): {len(snmp_candidates)} {snmp_candidates}", logs)
    
    for host_ip in snmp_candidates:
        log(f"Consultando SNMPv2c en candidato [{host_ip}]...", logs)
        sys_info = asyncio.run(snmp_get_system_info(host_ip, community))
        
        if sys_info:
            name = sys_info['name']
            model = sys_info['model']
            dev_id, was_created = get_or_create_device(conn, host_ip, name, model, community)
            status_tag = "NUEVO EQUIPO REGISTRADO" if was_created else "EQUIPO EXISTENTE (ACTUALIZADO)"
            log(f"-> SWITCH/ROUTER DETECTADO [{host_ip}]: {name} ({model}) [{status_tag}]", logs)
            
            managed_devices.append({
                "id": dev_id,
                "ip": host_ip,
                "name": name,
                "model": model,
                "new": was_created
            })
            
            # Consultar vecinos CDP y LLDP en el equipo encontrado
            cdp_n = asyncio.run(discover_cdp_neighbors(host_ip, community, logs))
            lldp_n = asyncio.run(discover_lldp_neighbors(host_ip, community, logs))
            all_n = cdp_n + lldp_n
            
            log(f"[{host_ip}] Adyacencias CDP/LLDP encontradas: {len(all_n)}", logs)
            
            for n in all_n:
                rem_ip = n.get('ip')
                rem_name = n.get('device_id') or "Switch-Vecino"
                rem_port = n.get('remote_port') or "GigabitEthernet1"
                rem_model = n.get('platform') or "Cisco Switch"
                loc_port = f"GigabitEthernet{n.get('local_if_index', 1)}"
                
                # Filtrar teléfonos IP (SEP...), Access Points y estaciones finales
                if not is_network_infrastructure_device(rem_name, rem_model):
                    continue
                
                if not rem_ip:
                    # Si no hay IP directa en la tabla de vecinos, verificar si ya fue descubierto
                    cur = conn.cursor(dictionary=True)
                    norm_rem = normalize_device_name(rem_name)
                    cur.execute("SELECT ip FROM dispositivos WHERE nombre = %s OR nombre LIKE %s LIMIT 1", (rem_name, f"{norm_rem}%"))
                    row = cur.fetchone()
                    if row:
                        rem_ip = row['ip']
                
                if rem_ip:
                    rem_dev_id, rem_created = get_or_create_device(conn, rem_ip, rem_name, rem_model, community)
                    loc_iface_id = get_or_create_interface(conn, dev_id, loc_port)
                    rem_iface_id = get_or_create_interface(conn, rem_dev_id, rem_port)
                    link_id, link_created = get_or_create_link(conn, dev_id, loc_iface_id, rem_dev_id, rem_iface_id)
                    
                    log(f"  * Enlace detectado: [{host_ip}:{loc_port}] <===> [{rem_ip}:{rem_port}] (ID {link_id})", logs)
                    discovered_links.append({
                        "id": link_id,
                        "from": dev_id,
                        "to": rem_dev_id,
                        "from_port": loc_port,
                        "to_port": rem_port,
                        "new": link_created
                    })
        else:
            log(f"[{host_ip}] Responde a ICMP pero no a SNMP '{community}'. (Host/PC/Impresora en el segmento).", logs)

    conn.close()
    elapsed = round(time.time() - start_time, 2)
    
    log("=======================================================", logs)
    log(f"Rastreo finalizado en {elapsed}s. Switches/Routers: {len(managed_devices)}, Enlaces físicos: {len(discovered_links)}", logs)
    log("=======================================================", logs)
    
    return {
        "status": "success",
        "elapsed_seconds": elapsed,
        "adapter": eth_info,
        "available_adapters": available_adapters,
        "active_hosts_count": len(active_hosts),
        "ping_alive_hosts": active_hosts,
        "total_devices": len(managed_devices),
        "new_devices": len([d for d in managed_devices if d.get('new')]),
        "total_links": len(discovered_links),
        "new_links": len([l for l in discovered_links if l.get('new')]),
        "devices": managed_devices,
        "links": discovered_links,
        "logs": logs
    }

def main():
    parser = argparse.ArgumentParser(description="Escaneo y Detección de Red Local / Subred")
    parser.add_argument("--community", default="public", help="Comunidad SNMP (default: public)")
    parser.add_argument("--threads", type=int, default=50, help="Hilos concurrentes para el barrido")
    parser.add_argument("--subnet", "--target", "-s", default=None, help="Subred o IP objetivo a escanear (ej. 10.4.155.0/24 o 192.168.1.0/24)")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON")
    
    args = parser.parse_args()
    res = scan_local_physical_network(community=args.community, max_workers=args.threads, target_subnet=args.subnet)
    
    if args.json:
        print("\n---JSON_OUTPUT_START---")
        print(json.dumps(res, ensure_ascii=False, indent=2))
        print("---JSON_OUTPUT_END---")

if __name__ == '__main__':
    main()
