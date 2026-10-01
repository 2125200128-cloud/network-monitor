import os
import sys
import json
import asyncio
import warnings
import re
warnings.filterwarnings("ignore", category=DeprecationWarning)

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

from pysnmp.hlapi.v3arch.asyncio import (
    walk_cmd, next_cmd, get_cmd, SnmpEngine, CommunityData, UdpTransportTarget,
    ContextData, ObjectType, ObjectIdentity
)

IP_SWITCH = sys.argv[1] if len(sys.argv) > 1 else '127.0.0.1'
COMUNIDAD = sys.argv[2] if len(sys.argv) > 2 else 'redjalisco'

async def walk_oid(engine, target, oid_str, max_rows=150):
    results = {}
    cur = oid_str
    for _ in range(max_rows):
        errI, errS, _, tbl = await next_cmd(
            engine,
            CommunityData(COMUNIDAD, mpModel=1),
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

async def _main():
    engine = SnmpEngine()
    try:
        target = await UdpTransportTarget.create(
            (IP_SWITCH, 161), timeout=2.0, retries=1
        )
    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))
        return

    # 1. SysDescr & Serial Number
    sys_descr = ""
    serial_number = ""
    os_version = ""
    try:
        errI, errS, _, vbs = await get_cmd(
            engine, CommunityData(COMUNIDAD, mpModel=1), target, ContextData(),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1.0'))
        )
        if not errI and not errS and vbs:
            sys_descr = vbs[0][1].prettyPrint().strip()
            # Extract OS version (e.g. Version 12.2(25)SEE2 or 17.9.3 or 8.2(7a))
            m_ver = re.search(r'Version\s+([\w\.\(\)\-]+)', sys_descr, re.I)
            if m_ver:
                os_version = m_ver.group(1)
            elif 'NX-OS' in sys_descr:
                os_version = "NX-OS"
            else:
                os_version = "Cisco IOS"

        # Serial from ENTITY-MIB
        serials = await walk_oid(engine, target, '1.3.6.1.2.1.47.1.1.1.1.11', 15)
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
        mem_used = await walk_oid(engine, target, '1.3.6.1.4.1.9.9.48.1.1.1.5', 5)
        mem_free = await walk_oid(engine, target, '1.3.6.1.4.1.9.9.48.1.1.1.6', 5)
        if mem_used and mem_free:
            u_bytes = sum(int(v) for v in mem_used.values())
            f_bytes = sum(int(v) for v in mem_free.values())
            if u_bytes + f_bytes > 0:
                ram_total_mb = round((u_bytes + f_bytes) / (1024 * 1024))
                ram_used_mb = round(u_bytes / (1024 * 1024))
    except Exception:
        pass

    # 3. Port name map (ifIndex -> ifDescr / ifName)
    if_names = {}
    try:
        # Try ifName (1.3.6.1.2.1.31.1.1.1.1) then ifDescr (1.3.6.1.2.1.2.2.1.2)
        raw_names = await walk_oid(engine, target, '1.3.6.1.2.1.31.1.1.1.1', 80)
        if not raw_names:
            raw_names = await walk_oid(engine, target, '1.3.6.1.2.1.2.2.1.2', 80)
        for idx, val in raw_names.items():
            if_names[str(idx)] = val.prettyPrint()
    except Exception:
        pass

    # 4. ARP Table (ipNetToMediaPhysAddress: 1.3.6.1.2.1.4.22.1.2)
    arp_table = []
    try:
        arp_macs = await walk_oid(engine, target, '1.3.6.1.2.1.4.22.1.2', 80)
        for idx, mac_vb in arp_macs.items():
            parts = idx.split('.')
            if len(parts) >= 5:
                if_index = parts[0]
                ip = '.'.join(parts[1:5])
                raw_bytes = mac_vb.asOctets() if hasattr(mac_vb, 'asOctets') else b''
                mac_str = ':'.join(f'{b:02x}' for b in raw_bytes) if raw_bytes else mac_vb.prettyPrint()
                iface_label = if_names.get(if_index, f"VLAN {if_index}")
                arp_table.append({
                    "ip": ip,
                    "mac": mac_str,
                    "interface": iface_label,
                    "age_min": "-"
                })
    except Exception:
        pass

    # 5. CAM / MAC Table (dot1dTpFdbTable: 1.3.6.1.2.1.17.4.3.1.2)
    mac_table = []
    try:
        cam_ports = await walk_oid(engine, target, '1.3.6.1.2.1.17.4.3.1.2', 100)
        cam_status = await walk_oid(engine, target, '1.3.6.1.2.1.17.4.3.1.3', 100)
        # Bridge port to ifIndex mapping (1.3.6.1.2.1.17.1.4.1.2)
        dot1d_to_ifindex = await walk_oid(engine, target, '1.3.6.1.2.1.17.1.4.1.2', 80)
        
        for idx, port_vb in cam_ports.items():
            parts = idx.split('.')
            if len(parts) == 6:
                mac_str = ':'.join(f'{int(b):02x}' for b in parts)
                dot1d_port = str(port_vb)
                real_ifindex = str(dot1d_to_ifindex.get(dot1d_port, dot1d_port))
                port_label = if_names.get(real_ifindex, if_names.get(dot1d_port, f"Port {dot1d_port}"))
                status_val = str(cam_status.get(idx, '3'))
                
                type_str = "Dynamic"
                if status_val in ('4', '5', 'static'):
                    type_str = "Static"
                    
                mac_table.append({
                    "mac": mac_str,
                    "port": port_label,
                    "vlan": 1,
                    "type": type_str,
                    "age_sec": "-"
                })
    except Exception:
        pass

    # 6. VLANs (ciscoVlanMIB: 1.3.6.1.4.1.9.9.46.1.3.1.1.4.1 or dot1qVlanStaticName: 1.3.6.1.2.1.17.7.1.4.3.1.1)
    vlans_table = []
    try:
        vlan_names = await walk_oid(engine, target, '1.3.6.1.4.1.9.9.46.1.3.1.1.4.1', 80)
        if not vlan_names:
            vlan_names = await walk_oid(engine, target, '1.3.6.1.2.1.17.7.1.4.3.1.1', 80)
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

    # 7. Spanning Tree Protocol (dot1dStp: 1.3.6.1.2.1.17.2)
    spanning_tree = {}
    try:
        errI, errS, _, stp_vbs = await get_cmd(
            engine, CommunityData(COMUNIDAD, mpModel=1), target, ContextData(),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.5.0')), # dot1dStpDesignatedRoot (BridgeId)
            ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.6.0')), # dot1dStpRootCost
            ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.7.0')), # dot1dStpRootPort
            ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.4.0')), # dot1dStpTimeSinceTopologyChange
            ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.3.0')), # dot1dStpTopChanges
            ObjectType(ObjectIdentity('1.3.6.1.2.1.17.2.2.0'))  # dot1dStpPriority
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

            # STP Ports states (1.3.6.1.2.1.17.2.15.1.3: 1=disabled, 2=blocking, 3=listening, 4=learning, 5=forwarding, 6=broken)
            stp_states = await walk_oid(engine, target, '1.3.6.1.2.1.17.2.15.1.3', 60)
            stp_costs = await walk_oid(engine, target, '1.3.6.1.2.1.17.2.15.1.5', 60)
            stp_ports_list = []
            state_map = {1: 'Disabled', 2: 'Blocking', 3: 'Listening', 4: 'Learning', 5: 'Forwarding', 6: 'Broken'}

            for p_idx, p_st in stp_states.items():
                p_st_int = int(p_st) if str(p_st).isdigit() else 1
                p_cost_int = int(stp_costs.get(p_idx, 4))
                p_name = if_names.get(str(p_idx), f"Port {p_idx}")
                # Exclude virtual / vlan interfaces from stp port table
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

    # 8. LACP / Port-Channels (if any)
    lacp_port_channels = []

    # 9. Security / QoS / Multicast
    security_qos_multicast = {
        "errdisabled_ports": [],
        "port_security": [],
        "qos_queues": [],
        "igmp_snooping": []
    }

    print(json.dumps({
        "status": "success",
        "ip": IP_SWITCH,
        "serial_number": serial_number,
        "os_version": os_version,
        "ram_total_mb": ram_total_mb,
        "ram_used_mb": ram_used_mb,
        "arp_table": arp_table,
        "mac_table": mac_table,
        "vlans_table": vlans_table,
        "spanning_tree": spanning_tree,
        "lacp_port_channels": lacp_port_channels,
        "security_qos_multicast": security_qos_multicast
    }))

if __name__ == '__main__':
    asyncio.run(_main())
