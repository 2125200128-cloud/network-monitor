# Network Monitor NOC — Enterprise Infrastructure Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Python Version](https://img.shields.io/badge/Python-3.12%2B-3776AB?style=flat-square&logo=python&logoColor=white)](https://python.org)
[![Tailwind CSS](https://img.shields.io/badge/TailwindCSS-v4.0-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![SNMP Protocol](https://img.shields.io/badge/SNMP-v2c%20%2F%20v3-0A66C2?style=flat-square)](https://en.wikipedia.org/wiki/Simple_Network_Management_Protocol)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE)

**Network Monitor NOC** es una plataforma integral de monitoreo de infraestructura de red y servicios web diseñada para centros de operaciones de red (NOC) empresariales y gubernamentales. Ofrece supervisión en tiempo real de conmutadores (Switches L2/L3, Nexus, Core), routers WAN, gateways de voz, teléfonos IP, controladores inalámbricos (WLC) y servicios web críticos.

---

## Modulos y Capacidades Principales

### 1. Mapa de Topologia Dinamica e Interactiva (L2 / L3)
* **Visualizacion de Grafos en Tiempo Real:** Motor de fisica interactivo con **Vis.js** que renderiza enlaces troncales, interfaces origen/destino, VLANs permitidas y porcentaje de saturacion de ancho de banda (1G, 10G, 40G).
* **Mapeo de Hardware:** Identificacion automatica y renderizado de hardware especifico:
  * **Cisco Nexus / Modular:** Nexus 7000, 9000, 3000, ACI Leaf/Spine.
  * **Switches Core / Distribucion:** Catalyst 9300, 9600, 6500.
  * **Switches de Acceso:** Catalyst 2960, 3750, 3560, 9200, SG200.
  * **Routers de Borde WAN & Gateways:** Cisco ISR 4400, 4300, 1841, CUBE Voice Gateways.
  * **Telefonia VoIP:** Telefonos IP Cisco Serie 7900, 7800, 8800 con vinculacion automatica a estaciones de trabajo PC adjuntas.
* **Persistencia de Coordenadas:** Guardado y restauracion de posiciones personalizadas por operador.

### 2. Telemetria y Monitoreo SNMP en Vivo
* **Motor Asincrono de Sondeo (Python AsyncIO):** `worker/snmp_poller.py` realiza barridos continuos de baja latencia con sondeo de CPU, memoria RAM, ping ICMP, estado operacional de puertos y trafico In/Out.
* **Colector de Chasis y Sensores:** Deteccion de numero de serie, version de firmware (IOS/NX-OS), uptime, sensores de temperatura y estado de fuentes de alimentacion.
* **Gestion de Alarmas y Notificaciones:** Deteccion instantanea de enlaces caidos, saturacion excesiva de ancho de banda y degradacion de latencia.

### 3. Monitoreo de Servicios & Paginas Web
* **Vigilancia HTTP/HTTPS:** Verificacion de portales web, aplicaciones de recaudacion y endpoints bancarios (`Multipagos`, `Bancos Reimprime`, `Tramites`).
* **Metricas de Rendimiento:** Latencia en milisegundos, codigos de respuesta HTTP (200 OK, 4xx, 5xx) y deteccion de caidas/timeouts.
* **Dashboard:** Interfaz compacta con filtros por categoria y verificacion masiva concurrente.

### 4. Auto-Descubrimiento de Red (Network Discovery Crawler)
* **Descubrimiento por Vecindad CDP / LLDP:** Mapeo automatico de topologia saltando de equipo semilla a dispositivos vecinos.
* **Barrido por Rango CIDR:** Escaneo de subredes completas con resolucion de MAC Vendor y deteccion de servicios SNMP.

### 5. Aprovisionamiento de VLANs y Switchports
* **Gestion de VLANs:** Creacion, edicion y asignacion de VLANs e interfaces SVI.
* **Asignacion de Puertos:** Configuracion de puertos en modo *Access* o *Trunk*.

---

## Stack Tecnologico

| Capa | Tecnologias |
| :--- | :--- |
| **Backend** | PHP 8.3+, Laravel 12, Eloquent ORM, Fortify |
| **Workers de Red** | Python 3.12+, AsyncIO, PySNMP, Requests |
| **Frontend** | Blade Templates, Tailwind CSS, Alpine.js, Vis.js Network |
| **Base de Datos** | MySQL / MariaDB / SQLite |
| **Protocolos de Red** | SNMP v2c/v3, ICMP Ping, CDP, LLDP, HTTP/HTTPS, ARP |

---

## Arquitectura del Proyecto

```
network-monitor/
|-- app/
|   |-- Console/Commands/        # Comandos Artisan (Polling, Web Checks)
|   |-- Http/Controllers/       # Controladores (Topologia, Dispositivos, ServiciosWeb, Discovery)
|   |-- Models/                 # Modelos Eloquent (Dispositivo, EnlaceRed, MetricaRed, ServicioWeb)
|   `-- Services/               # Servicios (WebServicePoller, PhonePcLinkResolver, MacVendorResolver)
|-- resources/
|   |-- views/                  # Vistas Blade (Topologia, Dashboard, Servicios Web, VLANs)
|   |-- css/                    # Estilos Tailwind CSS
|   `-- js/                     # Scripts y componentes frontend
|-- worker/
|   |-- snmp_poller.py          # Worker asincrono de sondeo continuo
|   |-- snmp_detail_collector.py# Colector detallado de hardware y tablas MAC/ARP
|   |-- network_discovery.py    # Crawler de auto-descubrimiento CDP/LLDP y CIDR
|   `-- metrics_collector.py    # Agregador de metricas historicas
|-- public/
|   `-- images/topology/        # Iconos de hardware (Nexus, Core, Access, Routers, VoIP, Servers)
`-- iniciar_monitor.bat         # Lanzador de todos los servicios del NOC
```

---

## Instalacion y Puesta en Marcha

### Prerrequisitos
* PHP >= 8.3 con extensiones `pdo`, `mbstring`, `openssl`, `curl`, `snmp`
* Composer
* Node.js & NPM
* Python >= 3.10 con dependencias de red (`pysnmp`, `requests`)

### Pasos de Instalacion

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/2125200128-cloud/network-monitor.git
   cd network-monitor
   ```

2. **Instalar dependencias de PHP y Node:**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Configurar el entorno:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Ejecutar migraciones y seeds:**
   ```bash
   php artisan migrate --seed
   ```

5. **Iniciar la plataforma (Servidor Web + Workers de Red):**
   ```bash
   # En Windows:
   iniciar_monitor.bat

   # O manualmente:
   php artisan serve --host=0.0.0.0 --port=8000
   python worker/snmp_poller.py
   ```

6. **Abrir en el navegador:**
   Accede a `http://localhost:8000` o `http://<IP-DEL-SERVIDOR>:8000`.

---

## Licencia

Este proyecto esta bajo la Licencia MIT.
