# Network Monitor NOC en un servidor Ubuntu

Guía para instalar la aplicación en **Ubuntu Server 24.04 LTS** y traer los datos del equipo Windows actual.

## Qué queda instalado

| Pieza | Qué hace |
|---|---|
| Nginx + PHP 8.3-FPM | La aplicación web (Laravel 12) |
| PostgreSQL 18 | La base de datos (misma versión que el equipo actual) |
| `nm-poller` | Sondeo ICMP/SNMP continuo de todos los equipos (~8 s por vuelta; repartido en varios procesos, uno por cada 2 núcleos, máx. 8) |
| `nm-detalle` | Colector SNMP de detalle cada 5 min (puertos, hardware, PoE, FortiGate) |
| `nm-clasificador` | Clasifica los equipos por evidencia SNMP/CDP/LLDP cada hora |
| `nm-noc-sync.timer` | Cada 10 s: alarmas, Telegram, consolidado horario, certificados SSL y limpieza |
| `nm-web-poll.timer` | Cada 15 s: monitoreo de servicios web |
| `nm-respaldo.timer` | Respaldo de la base todos los días a las 02:30 (se guardan 14 días) |

Todos arrancan solos con el servidor y se reinician si fallan. Equivalen a las ventanas de `iniciar_monitor.bat` en Windows.

## Requisitos

- Ubuntu Server 24.04 LTS con acceso a Internet durante la instalación (paquetes de Ubuntu, PostgreSQL, Node.js, Composer y Python).
- 4 vCPU, 8 GB de RAM y 60 GB de disco como punto de partida (hoy ~240 equipos; las métricas se guardan 7 días y el resumen horario 180).
- Desde el servidor hacia la red: **ICMP** y **SNMP (UDP 161)** a todos los equipos, **SSH (TCP 22)** para la consola y los respaldos de configuración, **HTTPS** saliente hacia `api.telegram.org` si se usan las alertas de Telegram.
- **Autorizar la IP del nuevo servidor en el SNMP de los equipos** (ACL de los switches, *trusted hosts* de los FortiGate). Si no, el monitoreo mostrará los equipos como "sin respuesta SNMP".

## 1. En el equipo Windows actual: exportar los datos

En PowerShell, dentro de la carpeta del proyecto:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\exportar-datos-windows.ps1
```

Genera `network_monitor.dump`. Cópialo al servidor (WinSCP, o `scp network_monitor.dump usuario@servidor:/tmp/`).

Anota también la línea **`APP_KEY=`** del archivo `.env` de Windows. Es obligatoria: con ella se cifraron el token de Telegram, las contraseñas SNMPv3 y las credenciales SSH. Trátala como una contraseña.

## 2. En el servidor: descargar el código

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <URL-del-repositorio> network-monitor
sudo chown -R "$USER" network-monitor
cd network-monitor
```

## 3. Instalar

```bash
sudo APP_KEY_ORIGEN='base64:...la APP_KEY de Windows...' DOMINIO=noc.ejemplo.gob.mx bash deploy/instalar.sh
```

- `DOMINIO` es el nombre o IP con el que se abrirá la web; si se omite, se usa la IP del servidor.
- Para una instalación desde cero, sin datos previos: `sudo NUEVA_INSTALACION=1 bash deploy/instalar.sh`.
- El script se puede volver a ejecutar sin problema; instala lo que falte y no borra datos.

## 4. Importar los datos de Windows

```bash
sudo bash deploy/importar-datos.sh /tmp/network_monitor.dump
```

Detiene los workers, restaura la base, aplica migraciones pendientes y los vuelve a arrancar. Al final muestra cuántos equipos, enlaces y alarmas se importaron.

Al terminar, **apaga los workers en Windows** (cierra las ventanas de `iniciar_monitor.bat`) para que no haya dos servidores sondeando la red ni enviando avisos de Telegram duplicados.

## 5. Comprobar

```bash
systemctl status 'nm-*'                 # todos "active"
journalctl -u nm-poller -n 30           # debe listar equipos procesados por ciclo
curl -I http://localhost                # 302 hacia /login
```

En la web: el dashboard debe mostrar equipos en línea en menos de un minuto y **Configuración → Notificaciones → Enviar prueba** debe llegar a Telegram.

## Operación diaria

| Tarea | Comando |
|---|---|
| Ver si todo corre | `systemctl status 'nm-*'` |
| Bitácora de un worker en vivo | `journalctl -u nm-poller -f` (o `nm-detalle`, `nm-clasificador`, `nm-noc-sync`, `nm-web-poll`) |
| Bitácora de la web | `storage/logs/laravel-AAAA-MM-DD.log` |
| Reiniciar los workers | `sudo systemctl restart nm-poller nm-detalle nm-clasificador` |
| Actualizar a la última versión | `sudo bash deploy/actualizar.sh` |
| Respaldo manual | `sudo -u postgres /var/www/network-monitor/deploy/respaldo.sh` |
| Restaurar un respaldo | `sudo bash deploy/importar-datos.sh /var/backups/network-monitor/<archivo>.dump` |

## HTTPS

- **Con un nombre público** y acceso a Internet: `sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx -d noc.ejemplo.gob.mx`.
- **Red interna**: instala el certificado de la CA institucional en Nginx (`ssl_certificate` / `ssl_certificate_key` dentro de `/etc/nginx/sites-available/network-monitor`).

En ambos casos, después cambia en `.env` `APP_URL=https://...` y `SESSION_SECURE_COOKIE=true`, y ejecuta `sudo -u www-data php artisan optimize`.

## Notas

- La aplicación y los workers corren como `www-data`. El código pertenece al usuario que ejecutó la instalación (para `git pull`).
- El `.env` queda con permisos `640`: no lo copies a otros lados; contiene la `APP_KEY` y la contraseña de la base.
- Para regresar temporalmente a Windows basta con volver a abrir `iniciar_monitor.bat` allá (y detener aquí: `sudo systemctl stop nm-poller nm-detalle nm-clasificador nm-noc-sync.timer nm-web-poll.timer`).
