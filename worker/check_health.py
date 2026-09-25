import sys
import json
import asyncio
import subprocess
import platform
from pysnmp.hlapi.v3arch.asyncio import *

if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

async def ping_device(ip):
    try:
        if platform.system().lower() == 'windows':
            command = ['ping', '-n', '1', '-w', '1000', ip]
        else:
            command = ['ping', '-c', '1', '-W', '1', ip]
            
        result = subprocess.run(command, capture_output=True, text=True)
        stdout = result.stdout.lower()
        return 'ttl=' in stdout
    except Exception as e:
        return False

async def get_snmp_data(ip, community):
    snmp_engine = SnmpEngine()
    auth_data = CommunityData(community, mpModel=1) # SNMPv2c
    transport_target = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=1)
    context_data = ContextData()

    # OIDs for Cisco
    # CPU Modern: cpmCPUTotal5min (1.3.6.1.4.1.9.9.109.1.1.1.1.5.1)
    # CPU Legacy: avgBusy5 (1.3.6.1.4.1.9.2.1.58.0)
    # CPU NX-OS: cseSysCPUUtilization (1.3.6.1.4.1.9.9.305.1.1.1.0)
    # Memory Used: ciscoMemoryPoolUsed (1.3.6.1.4.1.9.9.48.1.1.1.5.1)
    # Memory Free: ciscoMemoryPoolFree (1.3.6.1.4.1.9.9.48.1.1.1.6.1)
    # Mem NX-OS: cseSysMemoryUtilization (1.3.6.1.4.1.9.9.305.1.1.2.0)
    
    oids = [
        ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.109.1.1.1.1.5.1')), # CPU Modern
        ObjectType(ObjectIdentity('1.3.6.1.4.1.9.2.1.58.0')),          # CPU Legacy
        ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.305.1.1.1.0')),     # CPU NX-OS
        ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.48.1.1.1.5.1')),    # Mem Used
        ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.48.1.1.1.6.1')),    # Mem Free
        ObjectType(ObjectIdentity('1.3.6.1.4.1.9.9.305.1.1.2.0'))      # Mem NX-OS
    ]

    try:
        errorIndication, errorStatus, errorIndex, varBinds = await get_cmd(
            snmp_engine,
            auth_data,
            transport_target,
            context_data,
            *oids
        )

        if errorIndication or errorStatus:
            print(f"Error SNMP en {ip}: {errorIndication or errorStatus}", file=sys.stderr)
            return 0, 0

        cpu_usage = 0
        mem_used = None
        mem_free = None
        mem_nxos = None

        cpu_modern_val = None
        cpu_legacy_val = None
        cpu_nxos_val = None

        for idx, varBind in enumerate(varBinds):
            val = varBind[1]
            val_str = val.prettyPrint()
            
            is_valid = 'No Such Instance' not in val_str and 'No more variables' not in val_str
            
            if is_valid:
                if idx == 0:
                    try: cpu_modern_val = int(val)
                    except: pass
                elif idx == 1:
                    try: cpu_legacy_val = int(val)
                    except: pass
                elif idx == 2:
                    try: cpu_nxos_val = int(val)
                    except: pass
                elif idx == 3:
                    try: mem_used = int(val)
                    except: pass
                elif idx == 4:
                    try: mem_free = int(val)
                    except: pass
                elif idx == 5:
                    try: mem_nxos = int(val)
                    except: pass

        # Fallback CPU: Try nxos, modern, then legacy
        if cpu_nxos_val is not None:
            cpu_usage = cpu_nxos_val
        elif cpu_modern_val is not None:
            cpu_usage = cpu_modern_val
        elif cpu_legacy_val is not None:
            cpu_usage = cpu_legacy_val

        mem_usage_percent = 0
        if mem_nxos is not None:
            mem_usage_percent = mem_nxos
        elif mem_used is not None and mem_free is not None:
            total_mem = mem_used + mem_free
            if total_mem > 0:
                mem_usage_percent = int((mem_used / total_mem) * 100)

        return cpu_usage, mem_usage_percent
        
    except Exception as e:
        print(f"Excepcion SNMP en {ip}: {e}", file=sys.stderr)
        return 0, 0

async def main():
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Missing IP or Community"}))
        sys.exit(1)

    ip = sys.argv[1]
    community = sys.argv[2]

    # 1. Ping Check
    is_up = await ping_device(ip)
    
    if not is_up:
        print(json.dumps({
            "status": "offline",
            "cpu": 0,
            "memory": 0
        }))
        return

    # 2. SNMP Check
    cpu, mem = await get_snmp_data(ip, community)

    # Note: Even if SNMP fails, we know it's "Up" via Ping
    print(json.dumps({
        "status": "online",
        "cpu": cpu,
        "memory": mem
    }))

if __name__ == '__main__':
    asyncio.run(main())
