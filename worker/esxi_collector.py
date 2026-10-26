"""
worker/esxi_collector.py — Lee cada minuto los hosts ESXi de la sección Virtualización por su API (vim25).

Por host: CPU y memoria en uso (quickStats), hardware, versión, sensores, alarmas disparadas, VMs y datastores.
Solo lectura. Las credenciales son las de Ajustes → ESXi (contraseña cifrada con APP_KEY). Lo que no se pudo leer
queda en NULL y el error se guarda tal cual: nunca se inventan valores.

Uso: python worker/esxi_collector.py            (bucle cada INTERVALO segundos)
     python worker/esxi_collector.py --una-vez   (una sola lectura, para probar)
"""
import json
import sys
import time
from concurrent.futures import ThreadPoolExecutor

try:
    from nm_common import conectar, upsert, descifrar_laravel
    import esxi_api
except ImportError:
    from worker.nm_common import conectar, upsert, descifrar_laravel
    from worker import esxi_api

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)

INTERVALO = 60
RETENCION_DIAS = 7
UBICACION = 'Centro de datos · Virtualización'


def credenciales():
    with conectar() as conn:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT esxi_usuario, esxi_password FROM configuraciones_generales ORDER BY id LIMIT 1")
        fila = cur.fetchone() or {}
    if not fila.get('esxi_usuario') or not fila.get('esxi_password'):
        return None
    return fila['esxi_usuario'], descifrar_laravel(fila['esxi_password'])


def hosts():
    with conectar() as conn:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id, nombre, ip FROM dispositivos WHERE ubicacion = %s AND LOWER(nombre) NOT LIKE %s ORDER BY nombre",
                    (UBICACION, '%vcenter%'))
        return cur.fetchall()


def _entero(v):
    try:
        return int(v)
    except (TypeError, ValueError):
        return None


def _sensores(lista):
    """Sensores del hardware. En los que miden algo el valor real es currentReading * 10^unitModifier
    (los campos anidados llegan como texto); los "discretos" solo informan estado y no tienen valor."""
    salida = []
    for s in lista or []:
        unidad = s.get('baseUnits')
        lectura, mod = _entero(s.get('currentReading')), _entero(s.get('unitModifier')) or 0
        discreto = not unidad or 'discrete' in unidad or unidad == 'unspecified'
        estado = ((s.get('healthState') or {}).get('key') or '').lower()
        salida.append({
            'nombre': s.get('name'),
            'tipo': s.get('sensorType'),
            'valor': round(lectura * (10 ** mod), 2) if lectura is not None and not discreto else None,
            'unidad': None if discreto else unidad,
            'estado': estado or None,   # green / yellow / red / unknown
        })
    return salida


def _ips_vm(nics):
    """IPv4 de todas las tarjetas de red de la VM (guest.net; las informa VMware Tools). Sirve para saber
    en qué VM está un sitio web aunque su IP no sea la principal."""
    if isinstance(nics, dict):
        nics = [nics]
    ips = []
    for n in nics or []:
        valor = n.get('ipAddress') if isinstance(n, dict) else None
        for ip in valor if isinstance(valor, list) else [valor]:
            if ip and ':' not in ip and not ip.startswith('169.254.') and ip not in ips:
                ips.append(ip)
    return ips


def _discos_vm(discos):
    """Particiones vistas desde dentro del SO (guest.disk, vía VMware Tools): ruta, capacidad y libre en bytes.
    Los campos anidados llegan como texto."""
    if isinstance(discos, dict):
        discos = [discos]
    salida = []
    for d in discos or []:
        if not isinstance(d, dict):
            continue
        capacidad, libre = _entero(d.get('capacity')), _entero(d.get('freeSpace'))
        if capacidad:
            salida.append({'ruta': d.get('diskPath'), 'capacidad': capacidad, 'libre': libre})
    return salida


def leer_host(dev, usuario, clave):
    with esxi_api.SesionEsxi(dev['ip'], usuario, clave) as s:
        h = s.host()
        vms = s.vms()
        ds = s.datastores()
        disparadas = h.get('triggeredAlarmState') or []
        refs = [(a.get('alarm') or {}).get('_ref') for a in disparadas if isinstance(a, dict)]
        nombres = s.nombres_alarmas(refs)

    mhz, nucleos = h.get('summary.hardware.cpuMhz'), h.get('summary.hardware.numCpuCores')
    cpu_total = mhz * nucleos if mhz and nucleos else None
    cpu_usado = h.get('summary.quickStats.overallCpuUsage')
    ram_total = round(h['summary.hardware.memorySize'] / 1048576) if h.get('summary.hardware.memorySize') else None
    ram_usado = h.get('summary.quickStats.overallMemoryUsage')

    alarmas = [{
        'nombre': nombres.get((a.get('alarm') or {}).get('_ref')) or (a.get('alarm') or {}).get('_ref'),
        'estado': a.get('overallStatus'),      # yellow / red
        'desde': a.get('time'),
        'reconocida': a.get('acknowledged'),
    } for a in disparadas if isinstance(a, dict)]

    lista_vms = [{
        'nombre': v.get('name'),
        'estado': v.get('runtime.powerState'),             # poweredOn / poweredOff / suspended
        'salud': v.get('summary.overallStatus'),
        'cpu_mhz': v.get('summary.quickStats.overallCpuUsage'),
        'ram_mb_uso': v.get('summary.quickStats.guestMemoryUsage'),
        'ram_mb_host': v.get('summary.quickStats.hostMemoryUsage'),
        'ram_mb': v.get('config.hardware.memoryMB'),
        'vcpu': v.get('config.hardware.numCPU'),
        'ip': v.get('guest.ipAddress') or None,
        'ips': _ips_vm(v.get('guest.net')),
        'so': v.get('guest.guestFullName') or None,
        'tools': v.get('guest.toolsRunningStatus') or None,
        'uptime_seg': v.get('summary.quickStats.uptimeSeconds'),
        'latido': v.get('guestHeartbeatStatus') or None,   # green / yellow / red / gray: el SO invitado responde
        'balloon_mb': v.get('summary.quickStats.balloonedMemory'),
        'swap_mb': v.get('summary.quickStats.swappedMemory'),
        'discos': _discos_vm(v.get('guest.disk')),
    } for v in vms]

    lista_ds = [{
        'nombre': d.get('summary.name'),
        'tipo': d.get('summary.type'),
        'capacidad': d.get('summary.capacity'),
        'libre': d.get('summary.freeSpace'),
        'accesible': d.get('summary.accessible'),
        'url': d.get('summary.url'),   # identifica el volumen: un datastore compartido tiene la misma en todos los hosts
    } for d in ds]

    return {
        'conexion': h.get('runtime.connectionState'),
        'mantenimiento': h.get('runtime.inMaintenanceMode'),
        'estado_general': h.get('summary.overallStatus'),
        'cpu_pct': round(cpu_usado / cpu_total * 100, 1) if cpu_usado is not None and cpu_total else None,
        'ram_pct': round(ram_usado / ram_total * 100, 1) if ram_usado is not None and ram_total else None,
        'cpu_mhz_usado': cpu_usado, 'cpu_mhz_total': cpu_total,
        'ram_mb_usado': ram_usado, 'ram_mb_total': ram_total,
        'uptime_seg': h.get('summary.quickStats.uptime'),
        'fabricante': h.get('summary.hardware.vendor'),
        'modelo': h.get('summary.hardware.model'),
        'cpu_modelo': h.get('summary.hardware.cpuModel'),
        'nucleos': nucleos,
        'hilos': h.get('summary.hardware.numCpuThreads'),
        'version': h.get('summary.config.product.fullName'),
        'sensores': _sensores(h.get('runtime.healthSystemRuntime.systemHealthInfo.numericSensorInfo')),
        'alarmas': alarmas,
        'vms': lista_vms,
        'datastores': lista_ds,
    }


COLS = ['conexion', 'mantenimiento', 'estado_general', 'cpu_pct', 'ram_pct', 'cpu_mhz_usado', 'cpu_mhz_total',
        'ram_mb_usado', 'ram_mb_total', 'uptime_seg', 'fabricante', 'modelo', 'cpu_modelo', 'nucleos', 'hilos', 'version',
        'sensores', 'alarmas', 'vms', 'datastores']
JSON_COLS = {'sensores', 'alarmas', 'vms', 'datastores'}
SQL_OK = upsert('esxi_estado',
                ['dispositivo_id', *COLS, {'error': 'NULL'}, {'leido_en': 'NOW()'}, {'intentado_en': 'NOW()'},
                 {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
                ['dispositivo_id'],
                {**{c: '{nuevo}' for c in COLS}, 'error': 'NULL', 'leido_en': 'NOW()', 'intentado_en': 'NOW()', 'updated_at': 'NOW()'})
# Si falla la lectura se conserva la última lectura buena (con su leido_en) y se anota el error
SQL_ERROR = upsert('esxi_estado',
                   ['dispositivo_id', 'error', {'intentado_en': 'NOW()'}, {'created_at': 'NOW()'}, {'updated_at': 'NOW()'}],
                   ['dispositivo_id'],
                   {'error': '{nuevo}', 'intentado_en': 'NOW()', 'updated_at': 'NOW()'})


def guardar(dev, datos, error):
    with conectar() as conn:
        cur = conn.cursor()
        if error:
            cur.execute(SQL_ERROR, (dev['id'], error[:1000]))
        else:
            valores = [json.dumps(datos[c], ensure_ascii=False) if c in JSON_COLS else datos[c] for c in COLS]
            cur.execute(SQL_OK, (dev['id'], *valores))
            encendidas = sum(1 for v in datos['vms'] if v['estado'] == 'poweredOn')
            cur.execute("INSERT INTO esxi_metricas (dispositivo_id, cpu_pct, ram_pct, vms_encendidas, fecha) VALUES (%s, %s, %s, %s, NOW())",
                        (dev['id'], datos['cpu_pct'], datos['ram_pct'], encendidas))
        conn.commit()


def ciclo():
    cred = credenciales()
    if not cred:
        print('[ESXi] Sin credenciales en Ajustes → ESXi; no se lee nada.', flush=True)
        return
    usuario, clave = cred
    equipos = hosts()

    def uno(dev):
        t = time.time()
        try:
            datos = leer_host(dev, usuario, clave)
            guardar(dev, datos, None)
            print(f"[{dev['ip']}] {dev['nombre']}: CPU {datos['cpu_pct']}% RAM {datos['ram_pct']}% · "
                  f"{sum(1 for v in datos['vms'] if v['estado'] == 'poweredOn')}/{len(datos['vms'])} VMs · "
                  f"{len(datos['alarmas'])} alarmas · {time.time() - t:.1f}s", flush=True)
        except Exception as e:
            guardar(dev, None, str(e))
            print(f"[{dev['ip']}] {dev['nombre']}: ERROR {e}", flush=True)

    with ThreadPoolExecutor(max_workers=max(1, len(equipos))) as ex:
        list(ex.map(uno, equipos))

    with conectar() as conn:
        conn.cursor().execute(f"DELETE FROM esxi_metricas WHERE fecha < NOW() - INTERVAL '{RETENCION_DIAS} days'"
                              if getattr(conn, 'pg', False) else
                              f"DELETE FROM esxi_metricas WHERE fecha < NOW() - INTERVAL {RETENCION_DIAS} DAY")
        conn.commit()


def probar(ip):
    """Botón "Probar" de Ajustes: credenciales por variables de entorno (las escritas) o las guardadas."""
    import os
    usuario, clave = os.environ.get('NM_ESXI_USUARIO'), os.environ.get('NM_ESXI_CLAVE')
    if not usuario or not clave:
        cred = credenciales()
        if not cred:
            return {'ok': False, 'mensaje': 'Escribe el usuario y la contraseña.'}
        usuario, clave = usuario or cred[0], clave or cred[1]
    try:
        d = leer_host({'id': None, 'ip': ip, 'nombre': ip}, usuario, clave)
    except Exception as e:
        return {'ok': False, 'mensaje': f'{ip}: {e}'}
    encendidas = sum(1 for v in d['vms'] if v['estado'] == 'poweredOn')
    return {'ok': True, 'mensaje': f"{ip} respondió: {d['version']} · {d['modelo']} · CPU {d['cpu_pct']}% · RAM {d['ram_pct']}% · "
                                   f"{encendidas}/{len(d['vms'])} VMs encendidas."}


def main():
    if len(sys.argv) >= 3 and sys.argv[1] == '--probar':
        print(json.dumps(probar(sys.argv[2]), ensure_ascii=False))
        return
    if '--una-vez' in sys.argv:
        ciclo()
        return
    print(f'=== Colector ESXi (API vim25, solo lectura) · cada {INTERVALO}s ===', flush=True)
    while True:
        inicio = time.time()
        try:
            ciclo()
        except Exception as e:
            print(f'[ESXi] Error en ciclo: {e}', flush=True)
        time.sleep(max(5, INTERVALO - (time.time() - inicio)))


if __name__ == '__main__':
    main()
