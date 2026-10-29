#Requires -RunAsAdministrator
# =============================================================================
#  VM de práctica en Hyper-V con Ubuntu Server 24.04, para ensayar deploy/instalar.sh
#  antes de hacerlo en el servidor real.
#
#  Uso (PowerShell como administrador, dentro de la carpeta del proyecto):
#    powershell -ExecutionPolicy Bypass -File deploy\crear-vm-practica.ps1
#
#  - Si Hyper-V no está activo, lo activa y pide reiniciar (vuelve a ejecutarlo después).
#  - Descarga la ISO oficial de releases.ubuntu.com y comprueba su SHA-256 antes de usarla.
#  - La VM usa el "Default Switch" (NAT): sale a la red por la IP de este equipo, así que
#    alcanza los equipos igual que el monitor actual. Desde otras PCs no se ve la VM.
# =============================================================================
param(
    [string]$Nombre = 'NOC-Practica',
    [int]$Cpu = 2,
    [int]$RamMinGB = 2,
    [int]$RamMaxGB = 4,
    [int]$DiscoGB = 40,
    [string]$Carpeta = "$env:PUBLIC\Documents\Hyper-V\NOC-Practica"
)
$ErrorActionPreference = 'Stop'

# 1. Hyper-V (viene en Windows 11 Pro; activarlo requiere reiniciar)
$hv = Get-WindowsOptionalFeature -Online -FeatureName Microsoft-Hyper-V-All
if ($hv.State -ne 'Enabled') {
    Enable-WindowsOptionalFeature -Online -FeatureName Microsoft-Hyper-V-All -All -NoRestart | Out-Null
    Write-Host 'Hyper-V quedó activado. Reinicia el equipo y vuelve a ejecutar este script.' -ForegroundColor Yellow
    Write-Host 'Después del reinicio abre de nuevo iniciar_monitor.bat para que el monitor siga funcionando.' -ForegroundColor Yellow
    return
}
if (Get-VM -Name $Nombre -ErrorAction SilentlyContinue) {
    throw "Ya existe una VM llamada '$Nombre'. Ábrela desde el Administrador de Hyper-V o bórrala antes de crear otra."
}

# 2. ISO oficial de la versión 24.04 más reciente, verificada con su SHA-256
New-Item -ItemType Directory -Force -Path $Carpeta | Out-Null
$base = 'https://releases.ubuntu.com/24.04'
$respuesta = Invoke-WebRequest -UseBasicParsing "$base/SHA256SUMS"
# El servidor no manda tipo de contenido: PowerShell 5.1 lo entrega como bytes
$texto = if ($respuesta.Content -is [byte[]]) { [Text.Encoding]::ASCII.GetString($respuesta.Content) } else { $respuesta.Content }
# La lista trae varias versiones puntuales (24.04.3, 24.04.4...): se usa la más reciente
$isos = foreach ($l in $texto -split "`n") {
    if ($l -match '^([0-9a-f]{64}) \*?(ubuntu-24\.04(?:\.(\d+))?-live-server-amd64\.iso)\s*$') {
        [pscustomobject]@{ Hash = $Matches[1]; Archivo = $Matches[2]; Version = [int]('0' + $Matches[3]) }
    }
}
$ultima = $isos | Sort-Object Version | Select-Object -Last 1
if (-not $ultima) { throw "No encontré la ISO de Ubuntu Server en $base/SHA256SUMS" }
$hashEsperado, $archivo = $ultima.Hash, $ultima.Archivo
$iso = Join-Path $Carpeta $archivo
if (-not (Test-Path $iso)) {
    Write-Host "Descargando $archivo (unos 4 GB)..." -ForegroundColor Cyan
    curl.exe -L --fail -o $iso "$base/$archivo"
    if ($LASTEXITCODE -ne 0) { Remove-Item $iso -ErrorAction SilentlyContinue; throw 'La descarga falló.' }
}
Write-Host 'Verificando la ISO...' -ForegroundColor Cyan
if ((Get-FileHash $iso -Algorithm SHA256).Hash -ne $hashEsperado.ToUpper()) {
    Remove-Item $iso
    throw 'La ISO no coincide con el SHA-256 oficial (descarga dañada o alterada). Se borró; vuelve a ejecutar el script.'
}

# 3. VM generación 2: arranque UEFI con Secure Boot para Linux, memoria dinámica (empieza con lo mínimo)
$vhd = Join-Path $Carpeta "$Nombre.vhdx"
New-VM -Name $Nombre -Generation 2 -MemoryStartupBytes ($RamMinGB * 1GB) -NewVHDPath $vhd -NewVHDSizeBytes ($DiscoGB * 1GB) `
       -SwitchName 'Default Switch' -Path $Carpeta | Out-Null
Set-VM -Name $Nombre -ProcessorCount $Cpu -DynamicMemory -MemoryMinimumBytes ($RamMinGB * 1GB) -MemoryMaximumBytes ($RamMaxGB * 1GB) `
       -AutomaticCheckpointsEnabled $false -AutomaticStartAction Nothing -AutomaticStopAction ShutDown
Set-VMFirmware -VMName $Nombre -SecureBootTemplate MicrosoftUEFICertificateAuthority
Add-VMDvdDrive -VMName $Nombre -Path $iso
Set-VMFirmware -VMName $Nombre -FirstBootDevice (Get-VMDvdDrive -VMName $Nombre)

Start-VM -Name $Nombre
vmconnect.exe localhost $Nombre
Write-Host ''
Write-Host "VM '$Nombre' creada y encendida: $Cpu vCPU, $RamMinGB-$RamMaxGB GB de RAM, disco de $DiscoGB GB." -ForegroundColor Green
Write-Host 'Instala Ubuntu en la ventana que se abrió (marca "Install OpenSSH server").' -ForegroundColor Green
Write-Host "Al terminar, crea un punto de control para poder volver a empezar:  Checkpoint-VM -Name $Nombre -SnapshotName 'Ubuntu recien instalado'" -ForegroundColor Green
