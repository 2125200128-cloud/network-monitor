import asyncio
from pysnmp.hlapi.v3arch.asyncio import *

async def test():
    engine = SnmpEngine()
    target = await UdpTransportTarget.create(('10.4.254.3', 161), timeout=1, retries=1)
    results = []
    
    # 1.3.6.1.2.1.1.1 is sysDescr
    iterator = walkCmd(
        engine,
        CommunityData('public', mpModel=1),
        target,
        ContextData(),
        ObjectType(ObjectIdentity('1.3.6.1.2.1.1'))
    )
    
    async for errIndication, errStatus, errIndex, varBinds in iterator:
        if errIndication or errStatus:
            break
        for vb in varBinds:
            results.append((str(vb[0]), str(vb[1])))
            
    print(results)

if __name__ == '__main__':
    asyncio.run(test())
