<#
  Exporta la base actual (PostgreSQL en este equipo Windows) para llevarla al servidor Ubuntu.
  Uso (PowerShell, en la carpeta del proyecto):
      powershell -ExecutionPolicy Bypass -File deploy\exportar-datos-windows.ps1
  Genera network_monitor.dump en la carpeta del proyecto (o la ruta de -Salida).
#>
param([string]$Salida = (Join-Path (Split-Path $PSScriptRoot -Parent) 'network_monitor.dump'))

$ErrorActionPreference = 'Stop'
$envFile = Join-Path (Split-Path $PSScriptRoot -Parent) '.env'
$cfg = @{}
foreach ($linea in Get-Content $envFile) {
    if ($linea -match '^\s*([A-Z_]+)=(.*)$') { $cfg[$matches[1]] = $matches[2].Trim().Trim('"') }
}
if ($cfg.DB_CONNECTION -ne 'pgsql') { throw "Este equipo no usa PostgreSQL (DB_CONNECTION=$($cfg.DB_CONNECTION))." }

$pgDump = Get-ChildItem 'C:\Program Files\PostgreSQL\*\bin\pg_dump.exe' | Sort-Object FullName -Descending | Select-Object -First 1
if (-not $pgDump) { throw 'No se encontró pg_dump.exe en C:\Program Files\PostgreSQL.' }

Write-Host "Exportando $($cfg.DB_DATABASE) con $($pgDump.FullName)..."
$env:PGPASSWORD = $cfg.DB_PASSWORD
try {
    & $pgDump.FullName --format=custom --compress=6 --host $cfg.DB_HOST --port $cfg.DB_PORT `
        --username $cfg.DB_USERNAME --file $Salida $cfg.DB_DATABASE
    if ($LASTEXITCODE -ne 0) { throw "pg_dump terminó con código $LASTEXITCODE" }
} finally {
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
}

$tam = '{0:N1} MB' -f ((Get-Item $Salida).Length / 1MB)
Write-Host ""
Write-Host "Listo: $Salida ($tam)" -ForegroundColor Green
Write-Host "Copia ese archivo al servidor (por ejemplo con WinSCP o: scp network_monitor.dump usuario@servidor:/tmp/)."
Write-Host ""
Write-Host "Necesitarás también la APP_KEY de este equipo (línea APP_KEY= del archivo .env)." -ForegroundColor Yellow
Write-Host "Trátala como una contraseña: con ella se descifran el token de Telegram, SNMPv3 y las credenciales SSH."
