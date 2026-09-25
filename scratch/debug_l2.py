import asyncio
from pysnmp.hlapi.v3arch.asyncio import *
import sys

async def walk_oid(engine, target, oid_str):
    iterator = walk_cmd(
        engine,
        CommunityData('public', mpModel=1),
        target,
        ContextData(),
        ObjectType(ObjectIdentity(oid_str)),
        lexicographicMode=False
    )
    count = 0
    try:
        async for err_ind, err_stat, err_idx, varBinds in iterator:
            if err_ind or err_stat:
                print(f"Error: {err_ind} {err_stat}")
                break
            for vb in varBinds:
                print(vb[0].prettyPrint(), "=", vb[1].prettyPrint())
                count += 1
                if count > 10:
                    return # just show first 10
    except Exception as e:
        print("Exception:", e)

async def _main():
    engine = SnmpEngine()
    target = await UdpTransportTarget.create(('10.4.254.14', 161), timeout=2.0, retries=1)
    
    print("--- Testing sysDescr ---")
    await walk_oid(engine, target, '1.3.6.1.2.1.1.1')
    
    print("\n--- Testing ARP (1.3.6.1.2.1.4.22.1.2) ---")
    await walk_oid(engine, target, '1.3.6.1.2.1.4.22.1.2')
    
    print("\n--- Testing MAC/CAM (1.3.6.1.2.1.17.4.3.1.2) ---")
    await walk_oid(engine, target, '1.3.6.1.2.1.17.4.3.1.2')

if __name__ == '__main__':
    if sys.platform == 'win32':
        asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
    asyncio.run(_main())
