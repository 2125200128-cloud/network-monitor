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
            ->with('ultimaTelemetria')
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

        // Función inteligente de extracción de número físico de puerto
        $extractPortNumber = function(string $name, int $fallbackIndex): int {
            $name = trim($name);
            // 1. Matches slot/subslot/port: Gi1/0/24 -> 24, Fa0/12 -> 12, Te1/1/1 -> 1, Gi0/21 -> 21
            if (preg_match('/\/(\d+)$/', $name, $m)) {
                return (int)$m[1];
            }
            // 2. Matches Cisco MIB 5-digit format: GigabitEthernet10124 -> 24, GigabitEthernet10101 -> 1
            if (preg_match('/(\d{5})$/', $name, $m)) {
                return ((int)$m[1]) % 100;
            }
            // 3. Matches trailing numbers: Gi24 -> 24, Port12 -> 12
            if (preg_match('/(\d+)$/', $name, $m)) {
                $num = (int)$m[1];
                return ($num > 1000) ? ($num % 100) : $num;
            }
            return $fallbackIndex;
        };

        foreach ($interfaces as $iface) {
            $t = $iface->ultimaTelemetria;
            $isErr = $t ? (bool)$t->is_errdisabled : false;
            // null = estado desconocido (equipo sin respuesta o puerto aún no sondeado)
            $oper = $isErr ? 'err-disabled' : $t?->oper_status;

            if ($isErr) {
                $errDisabledCount++;
            } elseif ($oper === 'up') {
                $upPortsCount++;
            } elseif ($oper === 'down') {
                $downPortsCount++;
            }

            if ($t) {
                if (($t->poe_watts_consumo ?? 0) > 0) {
                    $poeActiveCount++;
                    $totalPoeWatts += (float)$t->poe_watts_consumo;
                }
                $totalInBytes += (int)($t->in_octets ?? 0);
                $totalOutBytes += (int)($t->out_octets ?? 0);
            }

            $portNum = $extractPortNumber($iface->nombre, $portIndex);

            // Datos del puerto para el panel lateral. Los contadores que ningún worker
            // recolecta (unicast/multicast/broadcast, CRC, colisiones, PoE, óptica) van en null.
            $data = [
                'id' => $iface->id,
                'port_num' => $portNum,
                'if_index' => $iface->if_index,
                'name' => $iface->nombre,
                'short_name' => str_replace(['GigabitEthernet', 'TenGigabitEthernet', 'gigabitethernet', 'FastEthernet', 'fastethernet'], ['Gi', 'Te', 'Gi', 'Fa', 'Fa'], $iface->nombre),
                'mac' => $iface->mac_address,
                'speed' => $iface->velocidad_mbps,
                'duplex' => $iface->duplex,
                'autoneg' => $iface->autoneg === null ? null : (bool)$iface->autoneg,
                'port_type' => $iface->port_type,
                'alias' => $iface->alias ?? '',
                'admin_status' => $iface->admin_status,
                'oper_status' => $oper,
                'is_errdisabled' => $isErr,
                'errdisabled_reason' => $t?->errdisabled_reason,
                'vlan_id' => $iface->vlan_id,
                'mode' => $iface->mode,
                'port_channel' => $iface->port_channel,
                'is_poe' => (bool)$iface->is_poe,
                'in_octets' => $t?->in_octets,
                'out_octets' => $t?->out_octets,
                'in_unicast' => null,
                'out_unicast' => null,
                'in_multicast' => null,
                'out_multicast' => null,
                'in_broadcast' => null,
                'out_broadcast' => null,
                'bandwidth_util_pct' => $t?->bandwidth_util_pct !== null ? (float)$t->bandwidth_util_pct : null,
                'last_change' => $t?->last_change_str,
                'medido_en' => $t?->updated_at?->format('d/m/Y H:i:s'),
                'in_errors' => $t?->in_errors,
                'out_errors' => $t?->out_errors,
                'in_discards' => $t?->in_discards,
                'out_discards' => $t?->out_discards,
                'crc_errors' => null,
                'collisions' => null,
                'runts' => null,
                'giants' => null,
                'jabbers' => null,
                'alignment_errors' => null,
                'fcs_errors' => null,
                'optica_rx' => $t?->optica_rx_dbm,
                'optica_tx' => $t?->optica_tx_dbm,
                'optica_temp' => $t?->optica_temp_c,
                'optica_volt' => $t?->optica_voltage_v,
                'optica_bias' => $t?->optica_bias_ma,
                'optica_fault' => (bool)($t?->optica_tx_fault ?? false),
                'poe_watts' => $t?->poe_watts_consumo !== null ? (float)$t->poe_watts_consumo : null,
                'poe_class' => $t?->poe_class,
                'poe_status' => $t?->poe_status,
                'poe_volt' => $t?->poe_voltage_v,
                'poe_max' => $t?->poe_max_watts,
            ];

            $interfacesData[$iface->id] = $data;
            $portIndex++;
        }

        // Filtrar interfaces físicas reales (excluir VLANs virtuales y Null)
        $physicalInterfaces = $interfaces->filter(function($iface) {
            $n = strtolower($iface->nombre);
            return !str_starts_with($n, 'vlan') && 
                   !str_starts_with($n, 'vl') && 
                   !str_starts_with($n, 'null') && 
                   !str_starts_with($n, 'loopback') && 
                   !str_starts_with($n, 'lo') && 
                   !str_starts_with($n, 'stackport');
        });

        $totalPorts = $physicalInterfaces->count() > 0 ? $physicalInterfaces->count() : $interfaces->count();

        // Clasificación de interfaces físicas según medio de transmisión (Cobre RJ-45 vs Fibra Óptica SFP)
        $copperPorts = collect();
        $sfpPorts = collect();

        foreach ($physicalInterfaces as $iface) {
            $nameLower = strtolower($iface->nombre);
            $typeLower = strtolower($iface->port_type ?? '');
            $portNum = $extractPortNumber($iface->nombre, 1);

            // FastEthernet / Fa / 100BaseTX es SIEMPRE cobre RJ-45
            if (str_contains($nameLower, 'fastethernet') || preg_match('/^fa/i', $nameLower)) {
                $isSfp = false;
            } elseif (
                str_contains($nameLower, 'tengigabit') || 
                str_contains($nameLower, 'twentyfive') || 
                str_contains($nameLower, 'sfp') || 
                str_contains($nameLower, 'twe') || 
                preg_match('/^te\d+/i', $nameLower) ||
                ($typeLower === 'sfp_fiber' && ($portNum > 24 || ($iface->velocidad_mbps ?? 1000) >= 10000))
            ) {
                $isSfp = true;
            } else {
                $isSfp = false;
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

        foreach ($physicalInterfaces as $iface) {
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

        // Ordenamiento natural por número físico de puerto
        usort($copperPortsList, fn($a, $b) => $a['port_num'] <=> $b['port_num']);
        usort($sfpPortsList, fn($a, $b) => $a['port_num'] <=> $b['port_num']);

        // Mapeo dinámico de hardware fotorrealista para la Ficha Técnica según el modelo real
        $modeloExacto = $chasis->model_name ?? '';
        $osVersion = $chasis->os_version ?? '';
        // Tipo por evidencia (fuente única); las especificaciones son solo las que reporta el equipo
        $t = $dispositivo->resolveTypeInfo();
        $deviceHardware = [
            'tipo' => $t['tipo'],
            'image' => $t['image'],
            'rol' => $t['rol'],
            'tipo_equipo' => $t['label'],
            'factor_forma' => $t['modelo'] ?? 'Modelo no reportado',
            'clean_model' => $t['modelo'] ?? 'Modelo no reportado',
            'fabricante' => $t['fabricante'],
            'confianza' => $t['confianza'],
            'fuente' => $t['fuente'],
            'evidencia' => $t['evidencia'],
        ];
        $deviceHardware['specs'] = array_filter([
            'Tipo de equipo' => $t['label'] . ' (confianza ' . $t['confianza'] . ')',
            'Fabricante' => $t['fabricante'],
            'Modelo' => $t['modelo'],
            'sysObjectID' => $dispositivo->sys_object_id,
            'Descripción SNMP (sysDescr)' => $modeloExacto ?: null,
            'Versión de sistema' => $osVersion ?: null,
            'Número de serie' => $chasis?->serial_number,
            'Uptime' => $chasis?->uptime_str,
            'Interfaces inventariadas' => $interfaces->count() ? (string) $interfaces->count() : null,
            'Última respuesta' => $dispositivo->ultima_vez_visto ? \Carbon\Carbon::parse($dispositivo->ultima_vez_visto)->format('d/m/Y H:i:s') : null,
        ]) ?: ['Sin datos' => 'El equipo aún no ha reportado información por SNMP'];

        // Resuelve la computadora conectada al puerto PC (Pass-Through) si es un Teléfono IP
        $attachedPc = \App\Services\PhonePcLinkResolver::resolveAttachedPc($dispositivo);

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
            'ultimaMetrica',
            'attachedPc'
        ));
    }


    public function descargarPdf($id, \App\Services\PdfReportService $pdfService)
    {
        $dispositivo = Dispositivo::findOrFail($id);
        return $pdfService->descargarReporteDispositivo($dispositivo);
    }
}
