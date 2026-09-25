import os, sys, json, asyncio
import warnings
warnings.filterwarnings("ignore", category=DeprecationWarning)

from pysnmp.hlapi.v3arch.asyncio import (
    walk_cmd, SnmpEngine, CommunityData, UdpTransportTarget,
    ContextData, ObjectType, ObjectIdentity
)
import socket

IP_SWITCH = sys.argv[1] if len(sys.argv) > 1 else '127.0.0.1'
COMUNIDAD = sys.argv[2] if len(sys.argv) > 2 else 'public'

async def walk_oid(engine, target, oid_str):
    from pysnmp.hlapi.v3arch.asyncio import walk_cmd
    results = {}
    iterator = walk_cmd(
        engine,
        CommunityData(COMUNIDAD, mpModel=1),
        target,
        ContextData(),
        ObjectType(ObjectIdentity(oid_str)),
        lexicographicMode=False
    )
    try:
        async for err_ind, err_stat, err_idx, varBinds in iterator:
            if err_ind or err_stat:
                break
            for vb in varBinds:
                oid = str(vb[0])
                idx = oid.replace(oid_str + '.', '')
                results[idx] = vb[1]
    except Exception:
        pass
    return results

async def _main():
    engine = SnmpEngine()
    try:
        target = await UdpTransportTarget.create(
            (IP_SWITCH, 161), timeout=1.5, retries=1
        )
    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))
        return

    # ARP Table
    arp_table = []
    try:
        arp_macs = await walk_oid(engine, target, '1.3.6.1.2.1.4.22.1.2')
        for idx, mac_vb in arp_macs.items():
            parts = idx.split('.')
            if len(parts) >= 5:
                if_index = parts[0]
                ip = '.'.join(parts[1:5])
                mac_bytes = mac_vb.asOctets()
                mac_str = ':'.join(f'{b:02X}' for b in mac_bytes) if mac_bytes else ''
                arp_table.append({
                    "ip": ip,
                    "mac": mac_str,
                    "interface": if_index
                })
    except Exception:
        pass

    # CAM / MAC Table
    mac_table = []
    try:
        cam_ports = await walk_oid(engine, target, '1.3.6.1.2.1.17.4.3.1.2')
        cam_status = await walk_oid(engine, target, '1.3.6.1.2.1.17.4.3.1.3')
        for idx, port_vb in cam_ports.items():
            parts = idx.split('.')
            if len(parts) == 6:
                mac_str = ':'.join(f'{int(b):02X}' for b in parts)
                port_num = str(port_vb)
                status_val = str(cam_status.get(idx, '3'))
                
                type_str = "Dynamic"
                if status_val == '4' or status_val == '5':
                    type_str = "Static"
                    
                mac_table.append({
                    "mac": mac_str,
                    "port": port_num,
                    "vlan": 1,
                    "type": type_str,
                    "age_sec": "-"
                })
    except Exception:
        pass

    # VLANs
    vlans_table = []
    try:
        vlan_names = await walk_oid(engine, target, '1.3.6.1.4.1.9.9.46.1.3.1.1.4.1')
        for idx, name_vb in vlan_names.items():
            parts = idx.split('.')
            vlan_id = parts[-1] if len(parts) > 0 else idx
            vlans_table.append({
                "id": vlan_id,
                "name": name_vb.prettyPrint(),
                "status": "Active"
            })
    except Exception:
        pass

    print(json.dumps({
        "status": "success",
        "ip": IP_SWITCH,
        "arp_table": arp_table,
        "mac_table": mac_table,
        "vlans_table": vlans_table
    }))

if __name__ == '__main__':
    if sys.platform == 'win32':
        asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
    asyncio.run(_main())
