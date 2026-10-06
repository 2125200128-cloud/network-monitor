import sys
import os
import json
import asyncio
import re
import mysql.connector

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

from pysnmp.hlapi.v3arch.asyncio import (
    next_cmd, get_cmd, SnmpEngine, CommunityData, UdpTransportTarget,
    ContextData, ObjectType, ObjectIdentity
)

DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': 'root',
    'database': 'network_monitor'
}

def get_db():
    return mysql.connector.connect(**DB_CONFIG)

async def walk_oid(engine, target, comm, oid_str, max_rows=120):
    results = {}
    cur = oid_str
    for _ in range(max_rows):
        errI, errS, _, tbl = await next_cmd(
            engine,
            CommunityData(comm, mpModel=1),
            target,
            ContextData(),
            ObjectType(ObjectIdentity(cur)),
            lexicographicMode=False
        )
        if errI or errS or not tbl:
            break
        vb = tbl[0]
        oid_s = str(vb[0])
        if not oid_s.startswith(oid_str + '.'):
            break
        idx = oid_s[len(oid_str)+1:]
        results[idx] = vb[1]
        cur = oid_s
    return results

async def sync_device_data(dev, engine, sem):
    async with sem:
        dev_id = dev['id']
        ip = dev['ip']
        name = dev['nombre']
        comm = dev['comunidad_snmp'] or 'redjalisco'
        
        try:
            target = await UdpTransportTarget.create((ip, 161), timeout=2.0, retries=1)
        except Exception:
            return

        # 1. Fetch SysDescr, Serial Number, OS Version
        sys_descr = ""
        serial_number = ""
        os_version = ""
        try:
            errI, errS, _, vbs = await get_cmd(
                engine, CommunityData(comm, mpModel=1), target, ContextData(),
                ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1.0'))
            )
            if not errI and not errS and vbs:
                sys_descr = vbs[0][1].prettyPrint().strip()
                m_ver = re.search(r'Version\s+([\w\.\(\)\-]+)', sys_descr, re.I)
                if m_ver:
                    os_version = m_ver.group(1)
                elif 'NX-OS' in sys_descr:
                    os_version = "NX-OS"
                else:
                    os_version = "Cisco IOS"

            serials = await walk_oid(engine, target, comm, '1.3.6.1.2.1.47.1.1.1.1.11', 10)
            for val in serials.values():
                s = val.prettyPrint().strip()
                if s and len(s) >= 6 and not s.startswith('0x'):
                    serial_number = s
                    break
        except Exception:
            pass

        # 2. RAM Memory Total & Used
        ram_total_mb = None
        ram_used_mb = None
        try:
            mem_used = await walk_oid(engine, target, comm, '1.3.6.1.4.1.9.9.48.1.1.1.5', 5)
            mem_free = await walk_oid(engine, target, comm, '1.3.6.1.4.1.9.9.48.1.1.1.6', 5)
            if mem_used and mem_free:
                u_bytes = sum(int(v) for v in mem_used.values())
                f_bytes = sum(int(v) for v in mem_free.values())
                if u_bytes + f_bytes > 0:
                    ram_total_mb = round((u_bytes + f_bytes) / (1024 * 1024))
                    ram_used_mb = round(u_bytes / (1024 * 1024))
        except Exception:
            pass

        # 3. IF Names & Types
        if_names = {}
        if_types = {}
        if_speeds = {}
        if_admins = {}
        if_opers = {}
        try:
            raw_names = await walk_oid(engine, target, comm, '1.3.6.1.2.1.31.1.1.1.1', 80)
            if not raw_names:
                raw_names = await walk_oid(engine, target, comm, '1.3.6.1.2.1.2.2.1.2', 80)
            for idx, val in raw_names.items():
                if_names[str(idx)] = val.prettyPrint()

            raw_types = await walk_oid(engine, target, comm, '1.3.6.1.2.1.2.2.1.3', 80)
            for idx, val in raw_types.items():
                if_types[str(idx)] = int(val) if str(val).isdigit() else 6

            raw_speeds = await walk_oid(engine, target, comm, '1.3.6.1.2.1.31.1.1.1.15', 80)
            for idx, val in raw_speeds.items():
                if_speeds[str(idx)] = int(val) if str(val).isdigit() else 1000

            raw_admins = await walk_oid(engine, target, comm, '1.3.6.1.2.1.2.2.1.7', 80)
            for idx, val in raw_admins.items():
                if_admins[str(idx)] = 'up' if str(val) == '1' else 'down'

            raw_opers = await walk_oid(engine, target, comm, '1.3.6.1.2.1.2.2.1.8', 80)
            for idx, val in raw_opers.items():
                if_opers[str(idx)] = 'up' if str(val) == '1' else 'down'
        except Exception:
            pass

        # 4. ARP Table
        arp_table = []
        try:
            arp_macs = await walk_oid(engine, target, comm, '1.3.6.1.2.1.4.22.1.2', 80)
            for idx, mac_vb in arp_macs.items():
                parts = idx.split('.')
                if len(parts) >= 5:
                    if_index = parts[0]
                    ip_a = '.'.join(parts[1:5])
                    raw_bytes = mac_vb.asOctets() if hasattr(mac_vb, 'asOctets') else b''
                    mac_str = ':'.join(f'{b:02x}' for b in raw_bytes) if raw_bytes else mac_vb.prettyPrint()
                    iface_label = if_names.get(if_index, f"VLAN {if_index}")
                    arp_table.append({
                        "ip": ip_a,
                        "mac": mac_str,
                        "interface": iface_label,
                        "age_min": "-"
                    })
        except Exception:
            pass

        # 5. CAM Table
        mac_table = []
        try:
            cam_ports = await walk_oid(engine, target, comm, '1.3.6.1.2.1.17.4.3.1.2', 100)
            cam_status = await walk_oid(engine, target, comm, '1.3.6.1.2.1.17.4.3.1.3', 100)
            dot1d_to_ifindex = await walk_oid(engine, target, comm, '1.3.6.1.2.1.17.1.4.1.2', 80)
            
            for idx, port_vb in cam_ports.items():
                parts = idx.split('.')
                if len(parts) == 6:
                    mac_str = ':'.join(f'{int(b):02x}' for b in parts)
                    dot1d_port = str(port_vb)
                    real_ifindex = str(dot1d_to_ifindex.get(dot1d_port, dot1d_port))
                    port_label = if_names.get(real_ifindex, if_names.get(dot1d_port, f"Port {dot1d_port}"))
                    status_val = str(cam_status.get(idx, '3'))
                    type_str = "Static" if status_val in ('4', '5', 'static') else "Dynamic"
                    
                    mac_table.append({
                        "mac": mac_str,
                        "port": port_label,
                        "vlan": 1,
                        "type": type_str,
                        "age_sec": "-"
                    })
        except Exception:
            pass

        # 6. VLANs
        vlans_table = []
        try:
            vlan_names = await walk_oid(engine, target, comm, '1.3.6.1.4.1.9.9.46.1.3.1.1.4.1', 80)
            if not vlan_names:
                vlan_names = await walk_oid(engine, target, comm, '1.3.6.1.2.1.17.7.1.4.3.1.1', 80)
            for idx, name_vb in vlan_names.items():
                parts = idx.split('.')
                vlan_id = parts[-1] if len(parts) > 0 else idx
                try:
                    vlan_id_int = int(vlan_id)
                except ValueError:
                    continue
                vlans_table.append({
                    "id": vlan_id_int,
                    "name": name_vb.prettyPrint(),
                    "status": "Active",
                    "ports_access": [],
                    "ports_trunk": []
                })
        except Exception:
            pass

        # 7. STP
        spanning_tree = {}
        try:
            errI, errS, _, stp_vbs = await get_cmd(
                engine, CommunityData(comm, mpModel=1), target, ContextData(),
                ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.5.0')),
                ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.6.0')),
                ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.7.0')),
                ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.4.0')),
                ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.3.0'))
            )
            if not errI and not errS and stp_vbs:
                raw_root = stp_vbs[0][1].asOctets() if hasattr(stp_vbs[0][1], 'asOctets') else b''
                root_bridge_id = ':'.join(f'{b:02x}' for b in raw_root) if raw_root else stp_vbs[0][1].prettyPrint()
                root_cost = int(stp_vbs[1][1]) if stp_vbs[1][1] else 0
                root_port_num = int(stp_vbs[2][1]) if stp_vbs[2][1] else 0
                root_port_label = if_names.get(str(root_port_num), f"Port {root_port_num}") if root_port_num > 0 else "Root / Local"
                
                tcn_ticks = int(stp_vbs[3][1]) if stp_vbs[3][1] else 0
                tcn_sec = tcn_ticks // 100
                days = tcn_sec // 86400
                hours = (tcn_sec % 86400) // 3600
                mins = (tcn_sec % 3600) // 60
                last_tcn_str = f"hace {days}d {hours}h" if days > 0 else (f"hace {hours}h {mins}m" if hours > 0 else f"hace {mins} min")
                top_changes = int(stp_vbs[4][1]) if stp_vbs[4][1] else 0

                stp_states = await walk_oid(engine, target, comm, '1.3.6.1.2.1.17.2.15.1.3', 60)
                stp_costs = await walk_oid(engine, target, comm, '1.3.6.1.2.1.17.2.15.1.5', 60)
                stp_ports_list = []
                state_map = {1: 'Disabled', 2: 'Blocking', 3: 'Listening', 4: 'Learning', 5: 'Forwarding', 6: 'Broken'}

                for p_idx, p_st in stp_states.items():
                    p_st_int = int(p_st) if str(p_st).isdigit() else 1
                    p_cost_int = int(stp_costs.get(p_idx, 4))
                    p_name = if_names.get(str(p_idx), f"Port {p_idx}")
                    if 'vlan' in p_name.lower() or 'null' in p_name.lower():
                        continue
                    stp_ports_list.append({
                        "port": p_name,
                        "role": "Root" if str(p_idx) == str(root_port_num) else "Desg",
                        "state": state_map.get(p_st_int, 'Forwarding'),
                        "cost": p_cost_int,
                        "bpdu_guard": "Enabled" if p_st_int == 5 else "Disabled"
                    })

                spanning_tree = {
                    "protocol": "Rapid-PVST+",
                    "root_bridge_id": root_bridge_id,
                    "root_cost": root_cost,
                    "root_port": root_port_label,
                    "topology_changes": top_changes,
                    "last_tcn": last_tcn_str,
                    "ports": stp_ports_list
                }
        except Exception:
            pass

        # Database save
        conn = get_db()
        cursor = conn.cursor(dictionary=True, buffered=True)
        
        # 1. Update tablas_dispositivo
        cursor.execute("SELECT id FROM tablas_dispositivo WHERE dispositivo_id=%s", (dev_id,))
        row = cursor.fetchone()
        sec_json = json.dumps({"errdisabled_ports": [], "port_security": [], "qos_queues": [], "igmp_snooping": []})
        
        if row:
            cursor.execute("""
                UPDATE tablas_dispositivo 
                SET arp_table=%s, mac_table=%s, vlans_table=%s, spanning_tree=%s, lacp_port_channels='[]', security_qos_multicast=%s, updated_at=NOW()
                WHERE dispositivo_id=%s
            """, (json.dumps(arp_table), json.dumps(mac_table), json.dumps(vlans_table), json.dumps(spanning_tree), sec_json, dev_id))
        else:
            cursor.execute("""
                INSERT INTO tablas_dispositivo (dispositivo_id, arp_table, mac_table, vlans_table, spanning_tree, lacp_port_channels, security_qos_multicast, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, '[]', %s, NOW(), NOW())
            """, (dev_id, json.dumps(arp_table), json.dumps(mac_table), json.dumps(vlans_table), json.dumps(spanning_tree), sec_json))

        # 2. Update telemetria_chasis
        if serial_number or os_version or ram_total_mb:
            cursor.execute("""
                UPDATE telemetria_chasis 
                SET serial_number=COALESCE(NULLIF(%s, ''), serial_number),
                    os_version=COALESCE(NULLIF(%s, ''), os_version),
                    ram_total_mb=COALESCE(%s, ram_total_mb),
                    ram_used_mb=COALESCE(%s, ram_used_mb),
                    updated_at=NOW()
                WHERE dispositivo_id=%s
            """, (serial_number, os_version, ram_total_mb, ram_used_mb, dev_id))

        # 3. Clean and sync real interfaces if we got physical names
        if if_names:
            # Check if this device has clean physical interfaces
            for idx_s, if_name in if_names.items():
                if_idx_int = int(idx_s)
                if_type_val = if_types.get(idx_s, 6)
                
                # Exclude virtual (Vlan=53, Loopback=24, Null=1)
                is_virtual = if_type_val in (53, 24, 1) or any(x in if_name.lower() for x in ['vlan', 'null', 'loopback'])
                if is_virtual:
                    continue
                
                is_sfp = any(x in if_name.lower() for x in ['tengig', 'te1', 'twentyfive', 'sfp']) or (if_type_val == 6 and if_speeds.get(idx_s, 1000) >= 10000)
                port_type = 'sfp_fiber' if is_sfp else 'copper'
                speed_val = if_speeds.get(idx_s, 1000)
                admin_val = if_admins.get(idx_s, 'up')
                oper_val = if_opers.get(idx_s, 'down')

                # Check if interface exists
                cursor.execute("SELECT id FROM interfaces_red WHERE dispositivo_id=%s AND (if_index=%s OR nombre=%s)", (dev_id, if_idx_int, if_name))
                if_row = cursor.fetchone()
                if if_row:
                    if_id = if_row['id']
                    cursor.execute("""
                        UPDATE interfaces_red 
                        SET if_index=%s, nombre=%s, port_type=%s, velocidad_mbps=%s, admin_status=%s, updated_at=NOW()
                        WHERE id=%s
                    """, (if_idx_int, if_name, port_type, speed_val, admin_val, if_id))
                else:
                    cursor.execute("""
                        INSERT INTO interfaces_red (dispositivo_id, if_index, nombre, port_type, velocidad_mbps, admin_status, duplex, autoneg, mode, is_poe, created_at, updated_at)
                        VALUES (%s, %s, %s, %s, %s, %s, 'Full', 1, 'access', 0, NOW(), NOW())
                    """, (dev_id, if_idx_int, if_name, port_type, speed_val, admin_val))
                    if_id = cursor.lastrowid
                    cursor.execute("""
                        INSERT INTO telemetria_interfaces (interfaz_id, oper_status, in_octets, out_octets, bandwidth_util_pct, created_at, updated_at)
                        VALUES (%s, %s, 0, 0, 0.0, NOW(), NOW())
                    """, (if_id, oper_val))

        conn.commit()
        conn.close()
        print(f"[{ip}] [OK] {name}: Sync (ARP: {len(arp_table)}, MAC: {len(mac_table)}, VLANs: {len(vlans_table)}, Serial: {serial_number or 'N/A'})", flush=True)

async def main():
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, nombre, ip, comunidad_snmp FROM dispositivos WHERE estado='online'")
    devices = cursor.fetchall()
    conn.close()
    
    print(f"Iniciando sincronización profunda de {len(devices)} dispositivos online...")
    engine = SnmpEngine()
    sem = asyncio.Semaphore(15)
    tasks = [sync_device_data(d, engine, sem) for d in devices]
    await asyncio.gather(*tasks)
    print("Sincronización completa.")

if __name__ == '__main__':
    asyncio.run(main())
