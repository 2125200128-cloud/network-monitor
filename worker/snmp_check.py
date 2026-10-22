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

async def probar_v3_firewall(ip):
    """Prueba las credenciales SNMPv3 de firewalls guardadas en Configuraciones (no se pasan por la línea de comandos)."""
    import os
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from nm_common import credencial_v3_firewall
    cred = credencial_v3_firewall()
    if cred is None:
        return {'ok': False, 'mensaje': 'No hay credenciales SNMPv3 guardadas (o no se pudieron descifrar).'}
    try:
        target = await UdpTransportTarget.create((ip, 161), timeout=2.5, retries=1)
        err_ind, err_stat, _, varBinds = await get_cmd(
            SnmpEngine(), cred, target, ContextData(),
            ObjectType(ObjectIdentity('1.3.6.1.2.1.1.5.0')), ObjectType(ObjectIdentity('1.3.6.1.2.1.1.1.0')))
    except Exception as e:
        return {'ok': False, 'mensaje': f'Error: {e}'}
    if err_ind or err_stat:
        texto = str(err_ind or err_stat.prettyPrint())
        pista = {
            'unknownUserName': 'el usuario no existe en el equipo',
            'wrongDigest': 'la contraseña o el protocolo de autenticación no coinciden',
            'decryptionError': 'la contraseña o el protocolo de cifrado no coinciden',
            'unsupportedSecLevel': 'el nivel de seguridad no coincide con el del equipo',
            'timeout': 'sin respuesta: revisa que la IP de este servidor esté permitida en el SNMP del FortiGate',
        }
        detalle = next((v for k, v in pista.items() if k.lower() in texto.lower().replace(' ', '')), None)
        return {'ok': False, 'mensaje': f'{ip}: {texto}' + (f' ({detalle})' if detalle else '')}
    vals = [vb[1].prettyPrint() for vb in varBinds]
    return {'ok': True, 'mensaje': f'{ip} respondió por SNMPv3: {vals[0]} · {vals[1][:120]}'}


async def detectar_v3(ip):
    """Prueba las combinaciones habituales de SNMPv3 con un solo usuario/contraseña (variables de entorno
    NM_V3_USUARIO / NM_V3_CONTRASENA) y devuelve la primera que responde."""
    import os
    usuario, clave = os.environ.get('NM_V3_USUARIO', ''), os.environ.get('NM_V3_CONTRASENA', '')
    if not usuario:
        return {'ok': False, 'mensaje': 'Escribe el usuario.'}
    auth = {'SHA': usmHMACSHAAuthProtocol, 'SHA256': usmHMAC192SHA256AuthProtocol,
            'SHA512': usmHMAC384SHA512AuthProtocol, 'MD5': usmHMACMD5AuthProtocol}
    priv = {'AES': usmAesCfb128Protocol, 'AES256': usmAesCfb256Protocol, 'DES': usmDESPrivProtocol}
    # De la más común a la menos común en FortiGate; la misma contraseña para autenticación y cifrado
    combinaciones = [('authPriv', 'SHA', 'AES'), ('authNoPriv', 'SHA', None), ('authPriv', 'SHA256', 'AES'),
                     ('authNoPriv', 'SHA256', None), ('authPriv', 'SHA', 'AES256'), ('authPriv', 'SHA256', 'AES256'),
                     ('authPriv', 'MD5', 'AES'), ('authNoPriv', 'MD5', None), ('authPriv', 'SHA', 'DES'),
                     ('authPriv', 'SHA512', 'AES'), ('authNoPriv', 'SHA512', None), ('authPriv', 'MD5', 'DES')]
    # Al final, el usuario sin contraseña (noAuthNoPriv); si no se dio contraseña es lo único que se prueba
    combinaciones = (combinaciones if len(clave) >= 8 else []) + [('noAuthNoPriv', None, None)]
    respuestas = 0
    for nivel, a, p in combinaciones:
        if nivel == 'noAuthNoPriv':
            cred = UsmUserData(usuario)
        else:
            cred = (UsmUserData(usuario, clave, clave, authProtocol=auth[a], privProtocol=priv[p]) if p
                    else UsmUserData(usuario, clave, authProtocol=auth[a]))
        try:
            target = await UdpTransportTarget.create((ip, 161), timeout=1.5, retries=0)
            # Motor nuevo por intento: el anterior guarda en caché el usuario con otros protocolos
            err_ind, err_stat, _, vbs = await get_cmd(SnmpEngine(), cred, target, ContextData(),
                                                      ObjectType(ObjectIdentity('1.3.6.1.2.1.1.5.0')))
        except Exception:
            continue
        if not err_ind and not err_stat:
            como = ('sin contraseña (noAuthNoPriv)' if nivel == 'noAuthNoPriv'
                    else f'{a}' + (f' + cifrado {p}' if p else ' sin cifrado'))
            return {'ok': True, 'nivel': nivel, 'auth': a or 'SHA', 'priv': p or 'AES',
                    'mensaje': f'Funciona {como}: {ip} es {vbs[0][1].prettyPrint()}. Pulsa Guardar.'}
        if 'timeout' not in str(err_ind).lower().replace(' ', ''):
            respuestas += 1
        if 'unknownusername' in str(err_ind).lower().replace(' ', ''):
            return {'ok': False, 'mensaje': f'{ip} responde, pero no reconoce el usuario "{usuario}".'}
    if not respuestas:
        return {'ok': False, 'mensaje': f'{ip} no contestó a ningún intento. En el FortiGate revisa que el SNMP esté habilitado '
                                        'en la interfaz y que la IP de este servidor esté en los hosts permitidos del usuario SNMPv3.'}
    return {'ok': False, 'mensaje': f'{ip} responde pero rechaza la contraseña con todas las combinaciones habituales. '
                                    'Verifica la contraseña o pide el protocolo exacto y captúralo en Opciones avanzadas.'}


if __name__ == '__main__':
    if len(sys.argv) >= 3 and sys.argv[1] == '--v3-detectar':
        print(json.dumps(asyncio.run(detectar_v3(sys.argv[2])), ensure_ascii=False))
        sys.exit(0)
    if len(sys.argv) >= 3 and sys.argv[1] == '--v3-firewall':
        print(json.dumps(asyncio.run(probar_v3_firewall(sys.argv[2])), ensure_ascii=False))
        sys.exit(0)
    ips_arg = sys.argv[1]
    community = sys.argv[2]
    ips = [ip.strip() for ip in ips_arg.split(',') if ip.strip()]
    res = asyncio.run(get_multiple(ips, community))
    print(json.dumps(res))
