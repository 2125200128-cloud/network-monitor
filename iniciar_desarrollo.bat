@echo off
title Network Monitor NOC - Desarrollo
cd /d "%~dp0"
rem Entorno de desarrollo: solo la web en esta PC, con la copia local de la base.
rem Sin workers ni noc:sync: no sondea la red ni manda Telegram (eso lo hace el servidor).

set PHP=C:\MAMP\bin\php\php8.3.36\php.exe
if not exist "%PHP%" set PHP=php

rem Sin caches: los cambios en vistas, rutas y configuracion se ven al recargar
"%PHP%" artisan optimize:clear >nul 2>&1

echo ==========================================================
echo   NETWORK MONITOR NOC - Desarrollo
echo     Web local:   http://localhost:8000
echo     Produccion:  https://10.4.150.194  (no se toca desde aqui)
echo.
echo   Los datos son la copia local de la base; no se actualizan solos.
echo   Si cambias resources\css o resources\js: npm run build
echo   Para subir los cambios al servidor: ver deploy\LEEME.md
echo ==========================================================
"%PHP%" artisan serve --host=127.0.0.1 --port=8000