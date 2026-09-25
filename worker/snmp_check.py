import sys, json, asyncio
if sys.platform == 'win32': asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
from pysnmp.hlapi.v3arch.asyncio import *

async def get_info(ip, community):
    try:
        target = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=1)
        err_ind, err_stat, err_idx, varBinds = await get_cmd(
            SnmpEngine(),
            CommunityData(community, mpModel=1), target, ContextData(),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.5.0')),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1.0')),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.6.0'))
        )
        if err_ind or err_stat: return json.dumps({'status':'error', 'msg': str(err_ind)})
        res = {'status':'ok'}
        for vb in varBinds:
            oid_tuple = vb[0].get_oid().asTuple()
            val = vb[1].prettyPrint()
            if oid_tuple == (1,3,6,1,2,1,1,5,0): res['sysName'] = val
            elif oid_tuple == (1,3,6,1,2,1,1,1,0): res['sysDescr'] = val
            elif oid_tuple == (1,3,6,1,2,1,1,6,0): res['sysLocation'] = val
        return json.dumps(res)
    except Exception as e:
        return json.dumps({'status':'error', 'msg': str(e)})

async def get_multiple(ips, community):
    tasks = [get_info(ip, community) for ip in ips]
    results = await asyncio.gather(*tasks)
    return {ip: json.loads(res) for ip, res in zip(ips, results)}

if __name__ == '__main__':
    ips_arg = sys.argv[1]
    community = sys.argv[2]
    ips = [ip.strip() for ip in ips_arg.split(',') if ip.strip()]
    res = asyncio.run(get_multiple(ips, community))
    print(json.dumps(res))
