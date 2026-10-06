import asyncio
import socket
import subprocess
import mysql.connector
from pysnmp.hlapi.asyncio import (
    SnmpEngine, CommunityData, UdpTransportTarget,
    ContextData, ObjectType, ObjectIdentity, get_cmd
)

DB_CFG = dict(host='127.0.0.1', user='root', password='root', database='network_monitor', port=3306)

def test_ping(ip):
    try:
        res = subprocess.run(['ping', '-n', '1', '-w', '1000', ip], capture_output=True, text=True)
        return res.returncode == 0
    except Exception:
        return False

def test_tcp_port(ip, port, timeout=1.0):
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(timeout)
        r = s.connect_ex((ip, port))
        s.close()
        return r == 0
    except Exception:
        return False

async def test_snmp(snmpEngine, ip, community):
    try:
        target = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=1)
        errI, errS, _, varBinds = await get_cmd(
            snmpEngine,
            CommunityData(community, mpModel=1),
            target,
            ContextData(),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1.0'))
        )
        if not errI and not errS and varBinds:
            return True, str(varBinds[0][1])
    except Exception as e:
        return False, str(e)
    return False, "No response / Timeout"

async def main():
    db = mysql.connector.connect(**DB_CFG)
    cur = db.cursor(dictionary=True)
    cur.execute("SELECT id, nombre, ip, comunidad_snmp, ubicacion, estado FROM dispositivos WHERE estado = 'offline' OR id IN (1, 68, 13, 67, 66, 26, 73, 24, 11, 14, 76, 49, 50)")
    devices = cur.fetchall()

    print(f"DIAGNOSTICO EN TIEMPO REAL DE {len(devices)} EQUIPOS INACCESIBLES:\n")
    print(f"{'ID':<4} | {'Nombre':<32} | {'IP':<16} | {'ICMP Ping':<10} | {'SSH(22)':<8} | {'HTTP':<8} | {'SNMP(161)':<10} | {'Comunidad':<12}")
    print("-" * 115)

    snmpEngine = SnmpEngine()

    for d in devices:
        ip = d['ip']
        comm = d.get('comunidad_snmp') or 'redjalisco'
        
        # 1. Test ICMP
        ping_ok = test_ping(ip)
        
        # 2. Test TCP ports
        ssh_ok = test_tcp_port(ip, 22)
        http_ok = test_tcp_port(ip, 80) or test_tcp_port(ip, 443)
        
        # 3. Test SNMP with device community and fallbacks
        snmp_ok, snmp_resp = await test_snmp(snmpEngine, ip, comm)
        if not snmp_ok and comm != 'public':
            snmp_ok2, _ = await test_snmp(snmpEngine, ip, 'public')
            if snmp_ok2:
                snmp_ok = True
                comm = 'public (OK!)'
        if not snmp_ok and comm != 'redjalisco':
            snmp_ok3, _ = await test_snmp(snmpEngine, ip, 'redjalisco')
            if snmp_ok3:
                snmp_ok = True
                comm = 'redjalisco (OK!)'

        ping_str = "RESPONDE" if ping_ok else "SIN RESP."
        ssh_str = "ABIERTO" if ssh_ok else "CERRADO"
        http_str = "ABIERTO" if http_ok else "CERRADO"
        snmp_str = "OK" if snmp_ok else "TIMEOUT"

        print(f"{d['id']:<4} | {d['nombre'][:32]:<32} | {ip:<16} | {ping_str:<10} | {ssh_str:<8} | {http_str:<8} | {snmp_str:<10} | {comm:<12}")

    db.close()

if __name__ == '__main__':
    asyncio.run(main())
