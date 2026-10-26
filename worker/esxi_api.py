"""
worker/esxi_api.py — Cliente mínimo de la API de un host ESXi (SOAP vim25) sin dependencias externas.

Es la misma API que usa el SDK oficial de VMware (pyVmomi): se inicia sesión en el SessionManager del host y se
leen propiedades con el PropertyCollector. Solo lectura: no se invoca ningún método que cambie el host o las VMs.
"""
import ssl
import urllib.request
import xml.etree.ElementTree as ET
from http.cookiejar import CookieJar
from xml.sax.saxutils import escape

NS = {'s': 'http://schemas.xmlsoap.org/soap/envelope/', 'v': 'urn:vim25', 'x': 'http://www.w3.org/2001/XMLSchema-instance'}
XSI_TYPE = '{http://www.w3.org/2001/XMLSchema-instance}type'

# Propiedades que se leen de cada objeto (solo lectura)
PROPS_HOST = [
    'summary.quickStats.overallCpuUsage', 'summary.quickStats.overallMemoryUsage', 'summary.quickStats.uptime',
    'summary.hardware.vendor', 'summary.hardware.model', 'summary.hardware.cpuModel', 'summary.hardware.cpuMhz',
    'summary.hardware.numCpuCores', 'summary.hardware.numCpuThreads', 'summary.hardware.memorySize',
    'summary.config.product.fullName', 'summary.overallStatus',
    'runtime.connectionState', 'runtime.inMaintenanceMode', 'runtime.powerState',
    'runtime.healthSystemRuntime.systemHealthInfo.numericSensorInfo',
    'triggeredAlarmState',
]
PROPS_VM = [
    'name', 'runtime.powerState', 'summary.overallStatus', 'summary.quickStats.overallCpuUsage',
    'summary.quickStats.guestMemoryUsage', 'summary.quickStats.hostMemoryUsage', 'summary.quickStats.uptimeSeconds',
    'config.hardware.numCPU', 'config.hardware.memoryMB', 'guest.ipAddress', 'guest.guestFullName',
    'guest.toolsRunningStatus', 'guest.net', 'guest.disk', 'guestHeartbeatStatus',
    'summary.quickStats.balloonedMemory', 'summary.quickStats.swappedMemory',
]
PROPS_DATASTORE = ['summary.name', 'summary.type', 'summary.capacity', 'summary.freeSpace', 'summary.accessible', 'summary.url']


class ErrorEsxi(Exception):
    pass


def _valor(nodo):
    """Convierte un <val> de vim25 en tipos de Python según su xsi:type (objetos -> dict, ArrayOf* -> list)."""
    tipo = (nodo.get(XSI_TYPE) or '').split(':')[-1]
    hijos = list(nodo)
    if tipo.startswith('ArrayOf'):
        return [_valor(h) for h in hijos]
    if hijos:
        objeto = {}
        for h in hijos:
            clave = h.tag.split('}')[-1]
            v = _valor(h)
            # Campos repetidos (listas dentro de un objeto)
            if clave in objeto:
                objeto[clave] = objeto[clave] if isinstance(objeto[clave], list) else [objeto[clave]]
                objeto[clave].append(v)
            else:
                objeto[clave] = v
        return objeto
    texto = (nodo.text or '').strip()
    if tipo in ('int', 'long', 'short', 'byte'):
        return int(texto) if texto else None
    if tipo in ('float', 'double'):
        return float(texto) if texto else None
    if tipo == 'boolean':
        return texto == 'true'
    if tipo == 'ManagedObjectReference':
        return {'_ref': texto, '_tipo': nodo.get('type')}
    return texto


class SesionEsxi:
    """with SesionEsxi(ip, usuario, clave) as s: s.host(), s.vms(), s.datastores()"""

    def __init__(self, ip, usuario, clave, timeout=20):
        self.url = f'https://{ip}/sdk'
        self.usuario, self.clave, self.timeout = usuario, clave, timeout
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE   # certificado propio del ESXi (red interna)
        self.opener = urllib.request.build_opener(urllib.request.HTTPSHandler(context=ctx),
                                                  urllib.request.HTTPCookieProcessor(CookieJar()))
        self.contenido = None

    def _llamar(self, cuerpo):
        sobre = ('<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
                 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soapenv:Body>' + cuerpo + '</soapenv:Body></soapenv:Envelope>')
        pet = urllib.request.Request(self.url, data=sobre.encode('utf-8'), method='POST',
                                     headers={'Content-Type': 'text/xml; charset=utf-8', 'SOAPAction': 'urn:vim25/7.0'})
        try:
            with self.opener.open(pet, timeout=self.timeout) as r:
                raiz = ET.fromstring(r.read())
        except urllib.error.HTTPError as e:
            raiz = ET.fromstring(e.read() or b'<x/>')
            falla = raiz.find('.//faultstring')
            texto = falla.text if falla is not None else f'HTTP {e.code}'
            if 'InvalidLogin' in ET.tostring(raiz, encoding='unicode') or 'incorrect user name or password' in texto.lower():
                raise ErrorEsxi('El host rechazó el usuario o la contraseña.')
            raise ErrorEsxi(f'Error del host: {texto}')
        except OSError as e:
            raise ErrorEsxi(f'No se pudo conectar ({e}).')
        return raiz.find('s:Body', NS)[0]

    def __enter__(self):
        r = self._llamar('<RetrieveServiceContent xmlns="urn:vim25"><_this type="ServiceInstance">ServiceInstance</_this></RetrieveServiceContent>')
        rv = r.find('v:returnval', NS)
        self.contenido = {h.tag.split('}')[-1]: h.text for h in rv}
        self._llamar('<Login xmlns="urn:vim25"><_this type="SessionManager">' + escape(self.contenido['sessionManager'])
                     + '</_this><userName>' + escape(self.usuario) + '</userName><password>' + escape(self.clave) + '</password></Login>')
        return self

    def __exit__(self, *_):
        try:
            self._llamar('<Logout xmlns="urn:vim25"><_this type="SessionManager">' + escape(self.contenido['sessionManager']) + '</_this></Logout>')
        except Exception:
            pass

    def _propiedades(self, tipo, props, ref_host, recorrido=None):
        """RetrievePropertiesEx sobre el host o sobre los objetos alcanzables desde él (vm / datastore)."""
        seleccion = ''
        if recorrido:
            seleccion = (f'<selectSet xsi:type="TraversalSpec"><name>t</name><type>HostSystem</type>'
                         f'<path>{recorrido}</path><skip>false</skip></selectSet>')
        rutas = ''.join(f'<pathSet>{p}</pathSet>' for p in props)
        cuerpo = (f'<RetrievePropertiesEx xmlns="urn:vim25"><_this type="PropertyCollector">{escape(self.contenido["propertyCollector"])}</_this>'
                  f'<specSet><propSet><type>{tipo}</type>{rutas}</propSet>'
                  f'<objectSet><obj type="HostSystem">{escape(ref_host)}</obj><skip>{"true" if recorrido else "false"}</skip>{seleccion}</objectSet>'
                  f'</specSet><options/></RetrievePropertiesEx>')
        r = self._llamar(cuerpo)
        objetos = []
        for o in r.findall('v:returnval/v:objects', NS):
            fila = {'_ref': o.find('v:obj', NS).text}
            for p in o.findall('v:propSet', NS):
                fila[p.find('v:name', NS).text] = _valor(p.find('v:val', NS))
            objetos.append(fila)
        return objetos

    def nombres_alarmas(self, refs):
        """{ref de Alarm: nombre} para las alarmas disparadas (triggeredAlarmState solo trae la referencia)."""
        refs = [r for r in dict.fromkeys(refs) if r]
        if not refs:
            return {}
        objetos = ''.join(f'<objectSet><obj type="Alarm">{escape(r)}</obj><skip>false</skip></objectSet>' for r in refs)
        cuerpo = (f'<RetrievePropertiesEx xmlns="urn:vim25"><_this type="PropertyCollector">{escape(self.contenido["propertyCollector"])}</_this>'
                  f'<specSet><propSet><type>Alarm</type><pathSet>info.name</pathSet></propSet>{objetos}</specSet><options/></RetrievePropertiesEx>')
        nombres = {}
        for o in self._llamar(cuerpo).findall('v:returnval/v:objects', NS):
            p = o.find('v:propSet/v:val', NS)
            nombres[o.find('v:obj', NS).text] = (p.text or '').strip() if p is not None else ''
        return nombres

    def ref_host(self):
        """Referencia del HostSystem (en un ESXi suelto es 'ha-host')."""
        return 'ha-host'

    def host(self):
        filas = self._propiedades('HostSystem', PROPS_HOST, self.ref_host())
        return filas[0] if filas else {}

    def vms(self):
        return self._propiedades('VirtualMachine', PROPS_VM, self.ref_host(), 'vm')

    def datastores(self):
        return self._propiedades('Datastore', PROPS_DATASTORE, self.ref_host(), 'datastore')
