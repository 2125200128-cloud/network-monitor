@echo off
title Network Monitor NOC - Lanzador
cd /d "%~dp0"

set PY=%LOCALAPPDATA%\Programs\Python\Python312\python.exe
if not exist "%PY%" set PY=python
set PHP=C:\MAMP\bin\php\php8.3.1\php.exe
if not exist "%PHP%" set PHP=php

rem IP del adaptador que tiene la puerta de enlace (la red real), no la del hotspot u otros adaptadores virtuales
set MY_IP=127.0.0.1
for /f "usebackq tokens=*" %%i in (`powershell -NoProfile -Command "(Get-NetRoute -DestinationPrefix 0.0.0.0/0 | Sort-Object { $_.RouteMetric + $_.InterfaceMetric } | ForEach-Object { Get-NetIPAddress -InterfaceIndex $_.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue } | Where-Object { $_.IPAddress -notlike '169.254.*' -and $_.IPAddress -notlike '127.*' } | Select-Object -ExpandProperty IPAddress -First 1)"`) do set MY_IP=%%i

echo ==========================================================
echo   NETWORK MONITOR NOC - Iniciando servicios
echo ==========================================================

echo [0/7] Preparando cache de configuracion, rutas y vistas...
"%PHP%" artisan optimize:clear >nul 2>&1
"%PHP%" artisan optimize >nul 2>&1

echo [1/7] Servidor web (http://0.0.0.0:8000)...
start "NOC - Servidor Web" cmd /k "%PHP% artisan serve --host=0.0.0.0 --port=8000 --no-reload"

echo [2/7] Sondeo SNMP/ICMP en vivo (continuo, ~8s por vuelta, repartido en varios procesos)...
start "NOC - SNMP Poller" cmd /k "%PY% -u worker\snmp_poller.py"

echo [3/7] Colector SNMP detallado (cada 5 min)...
start "NOC - SNMP Detalle" cmd /k "%PY% -u worker\snmp_detail_collector.py"

echo [4/7] Monitoreo de Servicios Web (cada 15s)...
start "NOC - Servicios Web" cmd /k "for /l %%i in (0,0,1) do (%PHP% artisan web:poll & timeout /t 15 /nobreak >nul)"

echo [5/7] Clasificacion de equipos por evidencia SNMP/CDP/LLDP (cada hora)...
start "NOC - Clasificador" cmd /k "%PY% -u worker\device_classifier.py --apply --loop 3600"

echo [6/7] Hosts ESXi de virtualizacion por su API (cada minuto)...
rem Si el colector se detiene por cualquier motivo, se vuelve a iniciar a los 10 s
start "NOC - ESXi" cmd /k "for /l %%i in (0,0,1) do (%PY% -u worker\esxi_collector.py & echo [ESXi] El colector se detuvo; reiniciando en 10 s... & timeout /t 10 /nobreak >nul)"

echo [7/7] Historial de alarmas, avisos de Telegram, consolidado horario y certificados SSL (cada 10s)...
start "NOC - Alarmas y Consolidado" cmd /k "for /l %%i in (0,0,1) do (%PHP% artisan noc:sync & timeout /t 10 /nobreak >nul)"

echo.
echo ==========================================================
echo  Todos los servicios fueron lanzados en ventanas separadas.
echo  NO cierres esas ventanas mientras quieras monitorear.
echo.
echo  Direcciones para acceder:
echo    - Para compartir en la red: http://%MY_IP%:8000
echo    - En este mismo equipo:    http://localhost:8000
echo ==========================================================
echo.
pause
