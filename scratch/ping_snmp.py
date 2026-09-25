import asyncio
from pysnmp.hlapi.v3arch.asyncio import *
import sys
import mysql.connector

# Conectar a la BD para sacar IPs (hardcoding mysql credentials for local test)
def get_devices():
    try:
        conn = mysql.connector.connect(host="127.0.0.1", user="root", password="", database="network_monitor")
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT ip, nombre, comunidad_snmp FROM dispositivos WHERE nombre NOT LIKE 'SEP%' LIMIT 20")
        return cursor.fetchall()
    except Exception as e:
        print("DB error:", e)
        return []

async def test_ping(engine, ip, comm):
    if not comm:
        comm = 'public'
    target = await UdpTransportTarget.create((ip, 161), timeout=0.5, retries=0)
    iterator = walk_cmd(engine, CommunityData(comm, mpModel=1), target, ContextData(), ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1')), lexicographicMode=False)
    try:
        async for err_ind, err_stat, err_idx, varBinds in iterator:
            if not err_ind and not err_stat:
                return True
            break
    except:
        pass
    return False

async def _main():
    engine = SnmpEngine()
    devices = get_devices()
    for d in devices:
        res = await test_ping(engine, d['ip'], d['comunidad_snmp'])
        print(f"{d['ip']} ({d['nombre']}): {'OK' if res else 'FAIL'}")

if __name__ == '__main__':
    if sys.platform == 'win32':
        asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
    asyncio.run(_main())
