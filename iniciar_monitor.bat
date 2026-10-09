@echo off
title Network Monitor NOC - Lanzador
cd /d "%~dp0"

set PY="%LOCALAPPDATA%\Programs\Python\Python312\python.exe"
if not exist %PY% set PY=python
set PHP="C:\MAMP\bin\php\php8.3.1\php.exe"
if not exist %PHP% set PHP=php

set MY_IP=127.0.0.1
for /f "usebackq tokens=*" %%i in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } | Select-Object -ExpandProperty IPAddress -First 1)"`) do set MY_IP=%%i

echo ==========================================================
echo   NETWORK MONITOR NOC - Iniciando servicios
echo ==========================================================

echo [0/4] Limpiando cache de vistas y configuracion...
%PHP% artisan optimize:clear >nul 2>&1

echo [1/4] Servidor web (http://0.0.0.0:8000)...
start "NOC - Servidor Web" cmd /k "%PHP% artisan serve --host=0.0.0.0 --port=8000"

echo [2/4] Sondeo SNMP en vivo (cada 15s)...
start "NOC - SNMP Poller" cmd /k "%PY% -u worker\snmp_poller.py"

echo [3/4] Colector SNMP detallado (cada 5 min)...
start "NOC - SNMP Detalle" cmd /k "%PY% -u worker\snmp_detail_collector.py"

echo [4/4] Monitoreo de Servicios Web (cada 15s)...
start "NOC - Servicios Web" cmd /k "for /l %%i in (0,0,1) do (%PHP% artisan web:poll & timeout /t 15 /nobreak >nul)"

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
