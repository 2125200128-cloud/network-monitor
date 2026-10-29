@echo off
rem Doble clic: pide permiso de administrador y crea la VM de pr ctica (deploy\crear-vm-practica.ps1)
cd /d "%~dp0.."
powershell -NoProfile -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -NoExit -File \"%~dp0crear-vm-practica.ps1\"'"
