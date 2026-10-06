@echo off
title Network Monitor NOC - Lanzador
cd /d "%~dp0"

set PY="C:\Users\ssocial_redes1\AppData\Local\Programs\Python\Python312\python.exe"
if not exist %PY% set PY=python
set PHP="C:\MAMP\bin\php\php8.3.1\php.exe"
if not exist %PHP% set PHP=php

echo ==========================================================
echo   NETWORK MONITOR NOC - Iniciando servicios
echo ==========================================================

echo [1/4] Servidor web (http://10.4.25.191:8000)...
start "NOC - Servidor Web" cmd /k %PHP% artisan serve --host=0.0.0.0 --port=8000

echo [2/4] Sondeo SNMP en vivo (cada 15s)...
start "NOC - SNMP Poller" cmd /k %PY% -u worker\snmp_poller.py

echo [3/4] Colector SNMP detallado (cada 5 min)...
start "NOC - SNMP Detalle" cmd /k %PY% -u worker\snmp_detail_collector.py

echo [4/4] Metricas globales + Servicios Web (cada 60s)...
start "NOC - Metricas y Webs" cmd /k "for /l %%i in (0,0,1) do (%PY% worker\metrics_collector.py & %PHP% artisan web:poll & timeout /t 60 /nobreak >nul)"

echo.
echo Todos los servicios fueron lanzados en ventanas separadas.
echo NO cierres esas ventanas mientras quieras monitorear.
echo Abre en el navegador: http://10.4.25.191:8000
echo.
pause
