<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispositivo extends Model
{
    use HasFactory;

    protected $table = 'dispositivos';

    protected $fillable = [
        'nombre',
        'ip',
        'mac_address',
        'comunidad_snmp',
        'ubicacion',
        'estado',
        'ultima_vez_visto',
        'ssh_user',
        'ssh_password_encrypted',
        'ssh_port'
    ];

    public function metricas()
    {
        return $this->hasMany(MetricaRed::class);
    }

    public function ultimaMetrica()
    {
        return $this->hasOne(MetricaRed::class, 'dispositivo_id')->latestOfMany('fecha_registro');
    }

    public function auditoriaComandos()
    {
        return $this->hasMany(AuditoriaComando::class);
    }

    public function configuraciones()
    {
        return $this->hasMany(ConfiguracionDispositivo::class);
    }

    public function interfaces()
    {
        return $this->hasMany(InterfazRed::class, 'dispositivo_id');
    }

    public function telemetriaChasis()
    {
        return $this->hasOne(TelemetriaChasis::class, 'dispositivo_id')->latestOfMany();
    }

    public function tablasDispositivo()
    {
        return $this->hasOne(TablasDispositivo::class, 'dispositivo_id')->latestOfMany();
    }

    public function enlacesOrigen()
    {
        return $this->hasMany(EnlaceRed::class, 'origen_dispositivo_id');
    }

    public function enlacesDestino()
    {
        return $this->hasMany(EnlaceRed::class, 'destino_dispositivo_id');
    }

    /**
     * Resuelve dinámicamente la clasificación, etiqueta, modelo limpio y estilos del dispositivo
     */
    public function resolveTypeInfo(): array
    {
        $nombre = strtoupper($this->nombre ?? '');
        $rawModel = $this->telemetriaChasis->model_name ?? '';
        $rawDescr = $this->telemetriaChasis->os_version ?? '';
        $model = strtoupper($rawModel);
        $descr = strtoupper($rawDescr);
        $full = $nombre . ' ' . $model . ' ' . $descr;

        // 1. Teléfonos IP Cisco (VoIP)
        if (str_starts_with($nombre, 'SEP') || str_contains($model, 'IP PHONE') || str_contains($model, 'CP-') || str_contains($descr, 'IP PHONE') || str_contains($full, 'VOIP')) {
            $clean = 'Cisco IP Phone';
            if (preg_match('/(?:IP PHONE|CP-)\s*([0-9]{4}[A-Z]*)/i', $model . ' ' . $descr, $matches)) {
                $clean = 'Cisco IP Phone ' . $matches[1];
            } elseif (preg_match('/(?:79\d\d|78\d\d|88\d\d)/', $full, $matches)) {
                $clean = 'Cisco IP Phone ' . $matches[0];
            }
            return [
                'tipo' => 'telefono',
                'label' => 'Teléfono IP (VoIP)',
                'clean_model' => $clean,
                'badge_classes' => 'bg-pink-50 dark:bg-pink-950/60 text-pink-700 dark:text-pink-300 border-pink-200 dark:border-pink-800/40',
                'icon_container' => 'bg-pink-50 dark:bg-pink-950/60 text-pink-600 dark:text-pink-400'
            ];
        }

        // 2. Access Points & Wireless Controllers (Wi-Fi)
        if (
            str_contains($full, 'WLC') || str_contains($model, 'C9800') || str_contains($model, 'AIR-CT') ||
            (
                (str_contains($model, 'C9115') || str_contains($model, 'AIR-AP') || str_contains($model, 'AIR-CAP') || str_contains($descr, 'AP SOFTWARE') || str_starts_with($nombre, 'AP-') || str_starts_with($nombre, 'WAP-')) &&
                !str_contains($full, 'SWITCH') && !str_starts_with($nombre, 'SW')
            )
        ) {
            $clean = 'Cisco Access Point / WLC';
            if (str_contains($full, '9115')) $clean = 'Cisco Catalyst 9115AX AP';
            elseif (str_contains($full, '9800') || str_contains($full, 'WLC')) $clean = 'Cisco Catalyst 9800 WLC';
            return [
                'tipo' => 'access_point',
                'label' => 'Access Point / WLC',
                'clean_model' => $clean,
                'badge_classes' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800/40',
                'icon_container' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400'
            ];
        }

        // 3. Routers WAN / ISR / Gateways de Voz
        if (
            (
                str_contains($model, 'ISR') || str_contains($model, '1841') || str_contains($descr, 'ISR SOFTWARE') || 
                str_contains($nombre, 'ROUTER') || str_contains($nombre, 'RTR') || str_contains($nombre, 'CUBE') || 
                str_contains($nombre, 'GW-') || str_contains($nombre, '-GW') || str_contains($nombre, 'GW_') ||
                str_contains($full, 'SAT.') || str_contains($full, '-SAT')
            ) &&
            !str_contains($full, 'CATALYST') && !str_contains($full, 'CAT9K') && !str_contains($full, 'WS-C') && 
            !str_starts_with($nombre, 'SW') && !str_contains($nombre, 'SWC') && !str_contains($nombre, 'SWE') &&
            !str_starts_with($nombre, 'NX') && !str_contains($nombre, 'NEXUS')
        ) {
            $clean = 'Cisco Router WAN / Gateway';
            if (str_contains($full, '4451')) $clean = 'Cisco ISR 4451-X';
            elseif (str_contains($full, '4331')) $clean = 'Cisco ISR 4331';
            elseif (str_contains($full, '1841')) $clean = 'Cisco Router 1841';
            elseif (str_contains($full, 'CUBE')) $clean = 'Cisco CUBE Voice Gateway';
            return [
                'tipo' => 'router',
                'label' => 'Router WAN / Gateway',
                'clean_model' => $clean,
                'badge_classes' => 'bg-orange-50 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800/40',
                'icon_container' => 'bg-orange-50 dark:bg-orange-950/60 text-orange-600 dark:text-orange-400'
            ];
        }

        // 4. Servidores Enterprise Físicos Dedicados (IBM, Dell, HPE, Cisco UCS, ESXi)
        if (
            (
                str_contains($full, 'IBM') || str_contains($full, 'SYSTEM X') || str_contains($full, 'BLADECENTER') || str_contains($full, 'IMM') ||
                str_contains($full, 'THINKSYSTEM') || str_contains($full, 'POWEREDGE') || str_contains($full, 'PROLIANT') || str_contains($full, 'UCS-B') ||
                str_contains($full, 'ESXI') || str_contains($full, 'HYPER-V') || str_contains($descr, 'WINDOWS SERVER')
            ) &&
            !str_starts_with($nombre, 'SW') && !str_starts_with($nombre, 'NX') && !str_contains($full, 'SWITCH') && !str_contains($full, 'CATALYST')
        ) {
            $clean = 'Servidor Dedicado Enterprise';
            if (str_contains($full, 'IBM') || str_contains($full, 'SYSTEM X') || str_contains($full, 'BLADECENTER') || str_contains($full, 'THINKSYSTEM')) {
                $clean = 'Servidor IBM System x / BladeCenter';
            } elseif (str_contains($full, 'POWEREDGE')) {
                $clean = 'Servidor Dell PowerEdge';
            } elseif (str_contains($full, 'PROLIANT')) {
                $clean = 'Servidor HPE ProLiant';
            } elseif (str_contains($full, 'UCS')) {
                $clean = 'Servidor Cisco UCS';
            }

            return [
                'tipo' => 'servidor',
                'label' => 'Servidor Enterprise',
                'clean_model' => $clean,
                'badge_classes' => 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800/40',
                'icon_container' => 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400'
            ];
        }

        // 5. PC / Workstations (Solo si explícitamente es una estación de trabajo)
        if (
            (str_starts_with($nombre, 'PC-') || str_starts_with($nombre, 'DESKTOP-') || str_starts_with($nombre, 'LAPTOP-') || str_starts_with($nombre, 'HOST-') || str_contains($full, 'WORKSTATION')) &&
            !str_contains($full, 'CISCO') && !str_contains($full, 'SWITCH') && !str_starts_with($nombre, 'SW') && !str_contains($full, 'WS-C') && !str_contains($full, 'IOS')
        ) {
            return [
                'tipo' => 'pc',
                'label' => 'PC / Estación',
                'clean_model' => 'Estación de Trabajo PC',
                'badge_classes' => 'bg-cyan-50 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800/40',
                'icon_container' => 'bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400'
            ];
        }

        // 6. Switches L2/L3 / Nexus / Catalyst / SG / Core / Distribución / Acceso
        $clean = 'Cisco Switch L2/L3';
        if (str_contains($full, 'N7000') || str_contains($full, 'NEXUS 7') || str_contains($full, 'N7K')) $clean = 'Cisco Nexus 7000';
        elseif (str_contains($full, 'N3000') || str_contains($full, 'NEXUS 3') || str_contains($full, 'NX3K') || str_contains($full, 'N3K')) $clean = 'Cisco Nexus 3000';
        elseif (str_contains($full, 'N9K') || str_contains($full, 'NX9K') || str_contains($full, 'NEXUS 9') || str_contains($full, 'NX-OS') || str_contains($full, 'NXOS') || str_contains($full, 'LEAF') || str_contains($full, 'SPINE') || str_contains($full, 'ACI-') || str_contains($full, '93180') || str_contains($full, '93240') || str_contains($full, '9372')) $clean = 'Cisco Nexus 9000';
        elseif (str_contains($full, 'C9606') || str_contains($full, '9600')) $clean = 'Cisco Catalyst 9606R Core';
        elseif (str_contains($full, 'C9200') || str_contains($full, '9200L')) $clean = 'Cisco Catalyst 9200L';
        elseif (str_contains($full, 'C9300') || str_contains($full, '9300L') || str_contains($full, '9300')) $clean = 'Cisco Catalyst 9300 Core/Dist';
        elseif (str_contains($full, 'C1000')) $clean = 'Cisco Catalyst 1000';
        elseif (str_contains($full, 'CAT9K') || str_contains($full, 'CATALYST L3 SWITCH SOFTWARE (CAT9K')) $clean = 'Cisco Catalyst 9000-L';
        elseif (str_contains($full, '2960X') || str_contains($full, '2960-X')) $clean = 'Cisco Catalyst 2960X';
        elseif (str_contains($full, '2960S')) $clean = 'Cisco Catalyst 2960S';
        elseif (str_contains($full, '2960L')) $clean = 'Cisco Catalyst 2960L';
        elseif (str_contains($full, '2960')) $clean = 'Cisco Catalyst 2960';
        elseif (str_contains($full, '3750E')) $clean = 'Cisco Catalyst 3750E';
        elseif (str_contains($full, '3750')) $clean = 'Cisco Catalyst 3750';
        elseif (str_contains($full, '3560')) $clean = 'Cisco Catalyst 3560';
        elseif (str_contains($full, 'SG200')) $clean = 'Cisco SG200-26';
        elseif (str_contains($full, 'SG220')) $clean = 'Cisco SG220-50P';
        elseif (str_contains($full, 'SG300')) $clean = 'Cisco SG300-28P';
        elseif (str_contains($full, 'S2T54') || str_contains($full, '6500')) $clean = 'Cisco Catalyst 6500 / Sup2T';
        elseif (str_contains($full, 'CAT3K') || str_contains($full, '3850')) $clean = 'Cisco Catalyst 3850/3650';
        elseif (str_contains($full, 'CORE')) $clean = 'Cisco Switch Core L3';

        return [
            'tipo' => 'switch',
            'label' => 'Switch L2/L3',
            'clean_model' => $clean,
            'badge_classes' => 'bg-blue-50 dark:bg-blue-950/60 text-hacienda-blue dark:text-blue-300 border-blue-200 dark:border-blue-800/40',
            'icon_container' => 'bg-blue-50 dark:bg-blue-950/60 text-hacienda-blue dark:text-blue-400'
        ];
    }

    public function getTipoDispositivoAttribute(): string
    {
        return $this->resolveTypeInfo()['tipo'];
    }

    public function getTipoLabelAttribute(): string
    {
        return $this->resolveTypeInfo()['label'];
    }

    public function getCleanModelAttribute(): string
    {
        return $this->resolveTypeInfo()['clean_model'];
    }

    public function getTipoBadgeClassesAttribute(): string
    {
        return $this->resolveTypeInfo()['badge_classes'];
    }

    public function getTipoIconContainerClassesAttribute(): string
    {
        return $this->resolveTypeInfo()['icon_container'];
    }
}
