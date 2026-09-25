<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dispositivo;
use App\Models\InterfazRed;
use App\Models\TelemetriaChasis;
use App\Models\TablasDispositivo;

class DispositivoShowController extends Controller
{
    public function show($id)
    {
        $dispositivo = Dispositivo::findOrFail($id);
        
        $interfaces = InterfazRed::where('dispositivo_id', $id)
            ->with(['telemetria' => function($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('if_index')
            ->get();
            
        $chasis = TelemetriaChasis::where('dispositivo_id', $id)->latest()->first();
        $ultimaMetrica = $dispositivo->ultimaMetrica;
        $tablas = TablasDispositivo::where('dispositivo_id', $id)->latest()->first();
        if (!$tablas) {
            $tablas = new TablasDispositivo();
            $tablas->mac_table = [];
            $tablas->arp_table = [];
            $tablas->vlans_table = [];
        }

        // Estadísticas agregadas para Faceplate y Hero
        $upPortsCount = 0;
        $downPortsCount = 0;
        $errDisabledCount = 0;
        $poeActiveCount = 0;
        $totalPoeWatts = 0.0;
        $totalInBytes = 0;
        $totalOutBytes = 0;

        $interfacesData = [];
        $portIndex = 1;

        foreach ($interfaces as $iface) {
            $t = $iface->telemetria->first();
            $isErr = $t ? (bool)$t->is_errdisabled : false;
            $oper = $isErr ? 'err-disabled' : ($t ? $t->oper_status : ($iface->oper_status ?? 'down'));

            if ($isErr) {
                $errDisabledCount++;
            } elseif ($oper === 'up') {
                $upPortsCount++;
            } else {
                $downPortsCount++;
            }

            if ($t) {
                if ($t->poe_status === 'Delivering' || ($t->poe_watts_consumo ?? 0) > 0) {
                    $poeActiveCount++;
                    $totalPoeWatts += (float)($t->poe_watts_consumo ?? 0);
                }
                $totalInBytes += (int)($t->in_octets ?? 0);
                $totalOutBytes += (int)($t->out_octets ?? 0);
            }

            // Extraer el índice numérico del puerto (ej. GigabitEthernet1 -> 1, Gi1 -> 1)
            $portNum = $portIndex;
            if (preg_match('/(\d+)$/', $iface->nombre, $matches)) {
                $portNum = (int)$matches[1];
            }

            // Client data map for instant drawer rendering
            $data = [
                'id' => $iface->id,
                'port_num' => $portNum,
                'if_index' => $iface->if_index,
                'name' => $iface->nombre,
                'short_name' => str_replace(['GigabitEthernet', 'TenGigabitEthernet', 'gigabitethernet'], ['Gi', 'Te', 'Gi'], $iface->nombre),
                'mac' => $iface->mac_address,
                'speed' => $iface->velocidad_mbps,
                'duplex' => $iface->duplex ?? 'Full',
                'autoneg' => (bool)($iface->autoneg ?? true),
                'port_type' => $iface->port_type ?? 'copper',
                'alias' => $iface->alias ?? '',
                'admin_status' => $iface->admin_status,
                'oper_status' => $oper,
                'is_errdisabled' => $isErr,
                'errdisabled_reason' => $t?->errdisabled_reason ?? null,
                'vlan_id' => $iface->vlan_id,
                'mode' => $iface->mode ?? 'access',
                'port_channel' => $iface->port_channel,
                'is_poe' => (bool)$iface->is_poe,
                'in_octets' => $t?->in_octets ?? 0,
                'out_octets' => $t?->out_octets ?? 0,
                'in_unicast' => $t?->in_unicast_pkts ?? 0,
                'out_unicast' => $t?->out_unicast_pkts ?? 0,
                'in_multicast' => $t?->in_multicast_pkts ?? 0,
                'out_multicast' => $t?->out_multicast_pkts ?? 0,
                'in_broadcast' => $t?->in_broadcast_pkts ?? 0,
                'out_broadcast' => $t?->out_broadcast_pkts ?? 0,
                'bandwidth_util_pct' => (float)($t?->bandwidth_util_pct ?? 0),
                'last_change' => $t?->last_change_str ?? 'N/A',
                'in_errors' => $t?->in_errors ?? 0,
                'out_errors' => $t?->out_errors ?? 0,
                'in_discards' => $t?->in_discards ?? 0,
                'out_discards' => $t?->out_discards ?? 0,
                'crc_errors' => $t?->crc_errors ?? 0,
                'collisions' => $t?->collisions ?? 0,
                'runts' => $t?->runts ?? 0,
                'giants' => $t?->giants ?? 0,
                'jabbers' => $t?->jabbers ?? 0,
                'alignment_errors' => $t?->alignment_errors ?? 0,
                'fcs_errors' => $t?->fcs_errors ?? 0,
                'optica_rx' => $t?->optica_rx_dbm,
                'optica_tx' => $t?->optica_tx_dbm,
                'optica_temp' => $t?->optica_temp_c,
                'optica_volt' => $t?->optica_voltage_v,
                'optica_bias' => $t?->optica_bias_ma,
                'optica_fault' => (bool)($t?->optica_tx_fault ?? false),
                'poe_watts' => (float)($t?->poe_watts_consumo ?? 0),
                'poe_class' => $t?->poe_class ?? 'Class 3',
                'poe_status' => $t?->poe_status ?? ($iface->is_poe ? 'Delivering' : 'Disabled'),
                'poe_volt' => (float)($t?->poe_voltage_v ?? 52.4),
                'poe_max' => (float)($t?->poe_max_watts ?? 30.0),
            ];

            // Indexar tanto por ID de base de datos como por número físico de puerto
            $interfacesData[$iface->id] = $data;
            $interfacesData[$portNum] = $data;
            $portIndex++;
        }

        $totalPorts = $interfaces->count();

        // Clasificación de interfaces físicas según medio de transmisión (Cobre RJ-45 vs Fibra Óptica SFP)
        $copperPorts = collect();
        $sfpPorts = collect();

        foreach ($interfaces as $iface) {
            $nameLower = strtolower($iface->nombre);
            $typeLower = strtolower($iface->port_type ?? '');

            // FastEthernet / Fa / 100BaseTX es SIEMPRE cobre RJ-45
            $isFastEth = str_contains($nameLower, 'fastethernet') || preg_match('/^fa\d+/i', $nameLower) || preg_match('/^fe\d+/i', $nameLower);

            if ($isFastEth) {
                $isSfp = false;
            } else {
                $isSfp = ($typeLower === 'sfp_fiber' || $typeLower === 'sfp' || $typeLower === 'fiber') || 
                         str_contains($nameLower, 'tengigabit') || 
                         str_contains($nameLower, 'twentyfive') || 
                         str_contains($nameLower, 'sfp') || 
                         str_contains($nameLower, 'twe') ||
                         preg_match('/^te\d+/i', $nameLower);
            }

            if ($isSfp) {
                $sfpPorts->push($iface);
            } else {
                $copperPorts->push($iface);
            }
        }

        $interfacesList = [];
        $copperPortsList = [];
        $sfpPortsList = [];

        foreach ($interfaces as $iface) {
            $dMap = $interfacesData[$iface->id];
            $isSfp = $sfpPorts->contains('id', $iface->id);
            $dMap['is_sfp'] = $isSfp;
            $interfacesList[] = $dMap;
            if ($isSfp) {
                $sfpPortsList[] = $dMap;
            } else {
                $copperPortsList[] = $dMap;
            }
        }

        // Mapeo dinámico de hardware fotorrealista para la Ficha Técnica según el modelo real
        $modeloExacto = $chasis->model_name ?? '';
        $osVersion = $chasis->os_version ?? '';
        $deviceHardware = $this->resolveDeviceImageAndRole($dispositivo->nombre, $modeloExacto, $osVersion);

        return view('dispositivos.show', compact(
            'dispositivo',
            'interfaces',
            'interfacesData',
            'interfacesList',
            'copperPorts',
            'copperPortsList',
            'sfpPorts',
            'sfpPortsList',
            'chasis',
            'tablas',
            'upPortsCount',
            'downPortsCount',
            'errDisabledCount',
            'poeActiveCount',
            'totalPoeWatts',
            'totalInBytes',
            'totalOutBytes',
            'totalPorts',
            'deviceHardware',
            'ultimaMetrica'
        ));
    }

    /**
     * Mapeo dinámico de hardware fotorrealista según el modelo exacto del equipo.
     */
    protected function resolveDeviceImageAndRole(string $nombre, string $modelo, string $sysDescr = ''): array
    {
        $haystack = strtolower($nombre . ' ' . $modelo . ' ' . $sysDescr);

        // 1. Chasis modular Nexus / 7000
        if (str_contains($haystack, 'nexus') || str_contains($haystack, '7000') || str_contains($haystack, 'n7000') || str_contains($haystack, 'nx-os') || str_contains($haystack, 'n9k') || str_contains($haystack, 'n3000')) {
            return [
                'image' => 'images/topology/switch-nexus.svg',
                'rol' => 'CORE / MODULAR',
                'tipo_equipo' => 'Chasis Modular de Núcleo',
                'factor_forma' => 'Modular (Multi-Slot Chassis)'
            ];
        }

        // 2. Cisco Catalyst 9300 / 93 / 9600 / 9800
        if (str_contains($haystack, '9300') || str_contains($haystack, 'catalyst 93') || str_contains($haystack, 'cat9k') || str_contains($haystack, 'c93') || str_contains($haystack, 'c9606') || str_contains($haystack, 'c9800')) {
            return [
                'image' => 'images/topology/switch-core.svg',
                'rol' => 'DISTRIBUTION / CORE',
                'tipo_equipo' => 'Switch Multicapa L3 Enterprise',
                'factor_forma' => '1U Rackmount Enterprise'
            ];
        }

        // 3. Switch de acceso 24/48 puertos (2960 / SG200 / C1000 / 3750 / 9200)
        if (str_contains($haystack, '2960') || str_contains($haystack, 'sg200') || str_contains($haystack, 'c1000') || str_contains($haystack, '3750') || str_contains($haystack, 'c9200')) {
            return [
                'image' => 'images/topology/switch-access.svg',
                'rol' => 'ACCESS',
                'tipo_equipo' => 'Switch de Acceso Gigabit Managed',
                'factor_forma' => '1U Rackmount Fixed'
            ];
        }

        // 4. Chasis del router de borde (ISR / Router / 1841 / 4400 / edge)
        if (str_contains($haystack, 'isr') || str_contains($haystack, 'router') || str_contains($haystack, '1841') || str_contains($haystack, '4400') || str_contains($haystack, 'edge')) {
            return [
                'image' => 'images/topology/router-edge.svg',
                'rol' => 'ROUTER / EDGE',
                'tipo_equipo' => 'Router de Borde WAN',
                'factor_forma' => '1U/2U Modular Router'
            ];
        }

        // 5. Switch estándar limpio de 1U por defecto
        return [
            'image' => 'images/topology/switch-standard-1u.svg',
            'rol' => 'SWITCH 1U',
            'tipo_equipo' => 'Switch Gestionado 1U',
            'factor_forma' => '1U Rackmount'
        ];
    }

    public function descargarPdf($id, \App\Services\PdfReportService $pdfService)
    {
        $dispositivo = Dispositivo::findOrFail($id);
        return $pdfService->descargarReporteDispositivo($dispositivo);
    }
}
