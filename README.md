# Network Monitor NOC

Sistema web del Centro de Operaciones de Red de la Secretaría de la Hacienda Pública (Jalisco). Vigila en un solo lugar:

- **Equipos de red**: switches, routers, access points y teléfonos, por ping y SNMP; puertos, hardware y diagnóstico.
- **Firewalls FortiGate**: alta disponibilidad, sesiones, CPU, memoria y tráfico (SNMPv3).
- **Virtualización VMware**: hosts ESXi, máquinas virtuales y datastores.
- **Servicios web**: disponibilidad y tiempo de respuesta de los sitios institucionales.
- **Recaudadoras**: comunicación de cada oficina cada minuto, sus enlaces, sus equipos (kioscos, terminales) e historial de ping.
- **Cámaras**: inventario a partir de las tablas ARP y video en vivo.

Las fallas se registran como alertas y se avisan por Telegram y por correo (solo las de la red prioritaria, 10.4.0.0/16 por omisión).

## Tecnología

| Parte | Qué se usa |
|---|---|
| Web | PHP 8.3, Laravel 12, Blade, Alpine.js, Tailwind CSS |
| Base de datos | PostgreSQL |
| Recolectores | Python 3.12 (`worker/`) y comandos de Laravel (`app/Console/Commands/`) |
| Servidor | Ubuntu 24.04, Nginx, PHP-FPM y servicios systemd (`deploy/systemd/`) |

## Estructura

```
app/                 Controladores, modelos, servicios y comandos de Laravel
resources/views/     Vistas Blade de cada sección
worker/              Recolectores en Python (sondeo SNMP/ICMP, detalle, ESXi, cámaras, recaudadoras)
deploy/              Instalación y actualización del servidor (ver deploy/LEEME.md)
database/migrations/ Estructura de la base de datos
```

## Desarrollo (Windows)

1. Clonar el repositorio en `C:\MAMP\htdocs\network-monitor` y copiar `.env.example` a `.env` con los datos de la base local.
2. Instalar dependencias:
   ```
   composer install
   npm install
   npm run build
   ```
3. Ejecutar `iniciar_desarrollo.bat` y abrir http://localhost:8000.

En desarrollo solo corre la web, con la copia local de la base: no sondea la red ni manda avisos. No uses `iniciar_monitor.bat` mientras el servidor de producción esté funcionando, porque la red se sondearía dos veces y los avisos llegarían duplicados.

## Producción

El servidor de producción recibe las versiones ya probadas: en Windows se hace `git push` y en el servidor se ejecuta

```
cd /var/www/network-monitor
sudo bash deploy/actualizar.sh
```

La instalación desde cero, la restauración de respaldos, HTTPS y la operación diaria están en [deploy/LEEME.md](deploy/LEEME.md). Qué hacer si se detiene un recolector o se cae el servidor está en el manual de uso (sección 15).

## Autor

Desarrollado por Ing. adahir mendez para la Dirección de Tecnología de la Información Financiera.
