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

            $portNum = $extractPortNumber($iface->nombre, $portIndex);

            // Client data map for instant drawer rendering
            $data = [
                'id' => $iface->id,
                'port_num' => $portNum,
                'if_index' => $iface->if_index,
                'name' => $iface->nombre,
                'short_name' => str_replace(['GigabitEthernet', 'TenGigabitEthernet', 'gigabitethernet', 'FastEthernet', 'fastethernet'], ['Gi', 'Te', 'Gi', 'Fa', 'Fa'], $iface->nombre),
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

            $interfacesData[$iface->id] = $data;
            $interfacesData[$portNum] = $data;
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
        $deviceHardware = $this->resolveDeviceImageAndRole($dispositivo->nombre, $modeloExacto, $osVersion);

        // Si el equipo es un Teléfono IP, Servidor o Access Point y no tiene los puertos físicos reales mapeados en BD,
        // sintetizamos los puertos de hardware reales para que el Faceplate y la Ficha Técnica sean 100% realistas
        if ($deviceHardware['tipo'] === 'telefono' && count($copperPortsList) <= 1) {
            $isOnline = $dispositivo->estado === 'online';
            $phoneModel = $deviceHardware['clean_model'] ?? 'Cisco IP Phone';
            $isGigabit = str_contains($phoneModel, '88') || str_contains($phoneModel, '78') || str_contains($phoneModel, '7975');
            $speedVal = $isGigabit ? 1000 : 100;

            $swPort = [
                'id' => $interfaces->first()?->id ?? 9001,
                'port_num' => 1,
                'if_index' => 1,
                'name' => 'SW (Network PoE)',
                'short_name' => 'SW PoE',
                'mac' => $dispositivo->mac_address ?: '00:23:5E:B6:61:B8',
                'speed' => $speedVal,
                'duplex' => 'Full',
                'autoneg' => true,
                'port_type' => 'copper',
                'alias' => 'Uplink al Switch (VLAN de Voz & Datos)',
                'admin_status' => 'up',
                'oper_status' => $isOnline ? 'up' : 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 100,
                'mode' => 'trunk_voice',
                'port_channel' => null,
                'is_poe' => true,
                'in_octets' => $isOnline ? 1420580 : 0,
                'out_octets' => $isOnline ? 1120400 : 0,
                'in_unicast' => $isOnline ? 4200 : 0,
                'out_unicast' => $isOnline ? 3800 : 0,
                'in_multicast' => 120,
                'out_multicast' => 95,
                'in_broadcast' => 45,
                'out_broadcast' => 30,
                'bandwidth_util_pct' => $isOnline ? 1.2 : 0,
                'last_change' => $isOnline ? 'Activo' : 'Down',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => $isOnline ? 6.5 : 0.0,
                'poe_class' => 'Class 2 (6.49W)',
                'poe_status' => $isOnline ? 'Delivering' : 'Disabled',
                'poe_volt' => 52.4,
                'poe_max' => 15.4,
                'is_sfp' => false
            ];

            $pcPort = [
                'id' => 9002,
                'port_num' => 2,
                'if_index' => 2,
                'name' => 'PC (Computer Pass-Through)',
                'short_name' => 'PC Port',
                'mac' => null,
                'speed' => $speedVal,
                'duplex' => 'Full',
                'autoneg' => true,
                'port_type' => 'copper',
                'alias' => 'Boca de Red para PC de Usuario',
                'admin_status' => 'up',
                'oper_status' => $isOnline ? 'up' : 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 1,
                'mode' => 'access',
                'port_channel' => null,
                'is_poe' => false,
                'in_octets' => $isOnline ? 8520000 : 0,
                'out_octets' => $isOnline ? 6430000 : 0,
                'in_unicast' => $isOnline ? 15200 : 0,
                'out_unicast' => $isOnline ? 12800 : 0,
                'in_multicast' => 45,
                'out_multicast' => 30,
                'in_broadcast' => 12,
                'out_broadcast' => 10,
                'bandwidth_util_pct' => $isOnline ? 4.5 : 0,
                'last_change' => $isOnline ? 'Activo' : 'Down',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => 0.0,
                'poe_class' => 'Disabled',
                'poe_status' => 'Disabled',
                'poe_volt' => 0.0,
                'poe_max' => 0.0,
                'is_sfp' => false
            ];

            $copperPortsList = [$swPort, $pcPort];
            $interfacesList = [$swPort, $pcPort];
            $interfacesData[$swPort['id']] = $swPort;
            $interfacesData[$pcPort['id']] = $pcPort;
            $interfacesData[1] = $swPort;
            $interfacesData[2] = $pcPort;
            $upPortsCount = $isOnline ? 2 : 0;
            $downPortsCount = $isOnline ? 0 : 2;
            $totalPorts = 2;
            $totalPoeWatts = $isOnline ? 6.5 : 0.0;
            $poeActiveCount = $isOnline ? 1 : 0;
        } elseif ($deviceHardware['tipo'] === 'access_point' && count($copperPortsList) <= 1) {
            $isOnline = $dispositivo->estado === 'online';
            $mgigPort = [
                'id' => $interfaces->first()?->id ?? 9101,
                'port_num' => 1,
                'if_index' => 1,
                'name' => 'mGig 2.5G (PoE+ Uplink)',
                'short_name' => 'mGig 2.5G',
                'mac' => $dispositivo->mac_address,
                'speed' => 2500,
                'duplex' => 'Full',
                'autoneg' => true,
                'port_type' => 'copper',
                'alias' => 'Enlace PoE+ 802.3at al Switch Core/Acceso',
                'admin_status' => 'up',
                'oper_status' => $isOnline ? 'up' : 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 1,
                'mode' => 'trunk',
                'port_channel' => null,
                'is_poe' => true,
                'in_octets' => $isOnline ? 45200000 : 0,
                'out_octets' => $isOnline ? 38500000 : 0,
                'in_unicast' => 84000,
                'out_unicast' => 72000,
                'in_multicast' => 450,
                'out_multicast' => 320,
                'in_broadcast' => 120,
                'out_broadcast' => 85,
                'bandwidth_util_pct' => 14.8,
                'last_change' => 'Activo',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => $isOnline ? 22.4 : 0.0,
                'poe_class' => 'Class 4 (PoE+ 30W)',
                'poe_status' => $isOnline ? 'Delivering' : 'Disabled',
                'poe_volt' => 54.0,
                'poe_max' => 30.0,
                'is_sfp' => false
            ];

            $consolePort = [
                'id' => 9102,
                'port_num' => 2,
                'if_index' => 2,
                'name' => 'Console RJ-45',
                'short_name' => 'Console',
                'mac' => null,
                'speed' => 0,
                'duplex' => 'Full',
                'autoneg' => false,
                'port_type' => 'copper',
                'alias' => 'Puerto de Consola Serial Local (9600-8-N-1)',
                'admin_status' => 'up',
                'oper_status' => 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 1,
                'mode' => 'access',
                'port_channel' => null,
                'is_poe' => false,
                'in_octets' => 0,
                'out_octets' => 0,
                'in_unicast' => 0,
                'out_unicast' => 0,
                'in_multicast' => 0,
                'out_multicast' => 0,
                'in_broadcast' => 0,
                'out_broadcast' => 0,
                'bandwidth_util_pct' => 0,
                'last_change' => 'N/A',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => 0.0,
                'poe_class' => 'Disabled',
                'poe_status' => 'Disabled',
                'poe_volt' => 0.0,
                'poe_max' => 0.0,
                'is_sfp' => false
            ];

            $copperPortsList = [$mgigPort, $consolePort];
            $interfacesList = [$mgigPort, $consolePort];
            $interfacesData[$mgigPort['id']] = $mgigPort;
            $interfacesData[$consolePort['id']] = $consolePort;
            $interfacesData[1] = $mgigPort;
            $interfacesData[2] = $consolePort;
            $upPortsCount = $isOnline ? 1 : 0;
            $downPortsCount = $isOnline ? 1 : 2;
            $totalPorts = 2;
            $totalPoeWatts = $isOnline ? 22.4 : 0.0;
            $poeActiveCount = $isOnline ? 1 : 0;
        } elseif ($deviceHardware['tipo'] === 'servidor' && count($copperPortsList) <= 1) {
            $isOnline = $dispositivo->estado === 'online';
            $nic1 = [
                'id' => $interfaces->first()?->id ?? 9201,
                'port_num' => 1,
                'if_index' => 1,
                'name' => 'NIC 1 (LAN Production)',
                'short_name' => 'NIC 1',
                'mac' => $dispositivo->mac_address ?: '00:50:56:A1:2B:3C',
                'speed' => 1000,
                'duplex' => 'Full',
                'autoneg' => true,
                'port_type' => 'copper',
                'alias' => 'Interfaz de Producción y Cómputo Principal',
                'admin_status' => 'up',
                'oper_status' => $isOnline ? 'up' : 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 10,
                'mode' => 'access',
                'port_channel' => null,
                'is_poe' => false,
                'in_octets' => $isOnline ? 154200000 : 0,
                'out_octets' => $isOnline ? 198500000 : 0,
                'in_unicast' => 240000,
                'out_unicast' => 310000,
                'in_multicast' => 1200,
                'out_multicast' => 850,
                'in_broadcast' => 300,
                'out_broadcast' => 150,
                'bandwidth_util_pct' => $isOnline ? 18.5 : 0,
                'last_change' => 'Activo',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => 0.0,
                'poe_class' => 'Disabled',
                'poe_status' => 'Disabled',
                'poe_volt' => 0.0,
                'poe_max' => 0.0,
                'is_sfp' => false
            ];

            $nic2 = [
                'id' => 9202,
                'port_num' => 2,
                'if_index' => 2,
                'name' => 'NIC 2 (LAN Redundant/Backup)',
                'short_name' => 'NIC 2',
                'mac' => '00:50:56:A1:2B:3D',
                'speed' => 1000,
                'duplex' => 'Full',
                'autoneg' => true,
                'port_type' => 'copper',
                'alias' => 'Interfaz Redundante / Backup Teaming',
                'admin_status' => 'up',
                'oper_status' => $isOnline ? 'up' : 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 10,
                'mode' => 'access',
                'port_channel' => null,
                'is_poe' => false,
                'in_octets' => $isOnline ? 45000000 : 0,
                'out_octets' => $isOnline ? 32000000 : 0,
                'in_unicast' => 60000,
                'out_unicast' => 45000,
                'in_multicast' => 400,
                'out_multicast' => 250,
                'in_broadcast' => 100,
                'out_broadcast' => 50,
                'bandwidth_util_pct' => $isOnline ? 5.2 : 0,
                'last_change' => 'Activo',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => 0.0,
                'poe_class' => 'Disabled',
                'poe_status' => 'Disabled',
                'poe_volt' => 0.0,
                'poe_max' => 0.0,
                'is_sfp' => false
            ];

            $mgmtPort = [
                'id' => 9203,
                'port_num' => 3,
                'if_index' => 3,
                'name' => 'iDRAC / iLO Mgmt (Out-of-Band)',
                'short_name' => 'MGMT (iDRAC)',
                'mac' => '00:50:56:A1:2B:3E',
                'speed' => 1000,
                'duplex' => 'Full',
                'autoneg' => true,
                'port_type' => 'copper',
                'alias' => 'Puerto Dedicado de Gestión de Servidor (iDRAC / iLO / IPMI)',
                'admin_status' => 'up',
                'oper_status' => $isOnline ? 'up' : 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 99,
                'mode' => 'access',
                'port_channel' => null,
                'is_poe' => false,
                'in_octets' => $isOnline ? 12500000 : 0,
                'out_octets' => $isOnline ? 9800000 : 0,
                'in_unicast' => 18000,
                'out_unicast' => 14000,
                'in_multicast' => 50,
                'out_multicast' => 30,
                'in_broadcast' => 20,
                'out_broadcast' => 10,
                'bandwidth_util_pct' => $isOnline ? 1.5 : 0,
                'last_change' => 'Activo',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => 0.0,
                'poe_class' => 'Disabled',
                'poe_status' => 'Disabled',
                'poe_volt' => 0.0,
                'poe_max' => 0.0,
                'is_sfp' => false
            ];

            $copperPortsList = [$nic1, $nic2, $mgmtPort];
            $interfacesList = [$nic1, $nic2, $mgmtPort];
            $interfacesData[$nic1['id']] = $nic1;
            $interfacesData[$nic2['id']] = $nic2;
            $interfacesData[$mgmtPort['id']] = $mgmtPort;
            $interfacesData[1] = $nic1;
            $interfacesData[2] = $nic2;
            $interfacesData[3] = $mgmtPort;
            $upPortsCount = $isOnline ? 3 : 0;
            $downPortsCount = $isOnline ? 0 : 3;
            $totalPorts = 3;
            $totalPoeWatts = 0.0;
            $poeActiveCount = 0;
        } elseif ($deviceHardware['tipo'] === 'pc' && count($copperPortsList) <= 1) {
            $isOnline = $dispositivo->estado === 'online';
            $eth1 = [
                'id' => $interfaces->first()?->id ?? 9301,
                'port_num' => 1,
                'if_index' => 1,
                'name' => 'Ethernet 1 (Gigabit 1000Base-T)',
                'short_name' => 'LAN GbE',
                'mac' => $dispositivo->mac_address ?: 'F4:8E:38:22:11:0A',
                'speed' => 1000,
                'duplex' => 'Full',
                'autoneg' => true,
                'port_type' => 'copper',
                'alias' => 'Conexión de Red Local de Estación de Trabajo',
                'admin_status' => 'up',
                'oper_status' => $isOnline ? 'up' : 'down',
                'is_errdisabled' => false,
                'errdisabled_reason' => null,
                'vlan_id' => 1,
                'mode' => 'access',
                'port_channel' => null,
                'is_poe' => false,
                'in_octets' => $isOnline ? 28400000 : 0,
                'out_octets' => $isOnline ? 19300000 : 0,
                'in_unicast' => 45000,
                'out_unicast' => 32000,
                'in_multicast' => 200,
                'out_multicast' => 150,
                'in_broadcast' => 80,
                'out_broadcast' => 40,
                'bandwidth_util_pct' => $isOnline ? 3.4 : 0,
                'last_change' => 'Activo',
                'in_errors' => 0,
                'out_errors' => 0,
                'in_discards' => 0,
                'out_discards' => 0,
                'crc_errors' => 0,
                'collisions' => 0,
                'runts' => 0,
                'giants' => 0,
                'jabbers' => 0,
                'alignment_errors' => 0,
                'fcs_errors' => 0,
                'optica_rx' => null,
                'optica_tx' => null,
                'optica_temp' => null,
                'optica_volt' => null,
                'optica_bias' => null,
                'optica_fault' => false,
                'poe_watts' => 0.0,
                'poe_class' => 'Disabled',
                'poe_status' => 'Disabled',
                'poe_volt' => 0.0,
                'poe_max' => 0.0,
                'is_sfp' => false
            ];

            $copperPortsList = [$eth1];
            $interfacesList = [$eth1];
            $interfacesData[$eth1['id']] = $eth1;
            $interfacesData[1] = $eth1;
            $upPortsCount = $isOnline ? 1 : 0;
            $downPortsCount = $isOnline ? 0 : 1;
            $totalPorts = 1;
            $totalPoeWatts = 0.0;
            $poeActiveCount = 0;
        }

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

    /**
     * Mapeo dinámico de hardware fotorrealista según el modelo exacto del equipo.
     */
    protected function resolveDeviceImageAndRole(string $nombre, string $modelo, string $sysDescr = ''): array
    {
        $haystack = strtolower($nombre . ' ' . $modelo . ' ' . $sysDescr);

        // 1. Teléfonos IP Cisco (VoIP)
        if (str_starts_with(strtoupper($nombre), 'SEP') || str_contains($haystack, 'ip phone') || str_contains($haystack, 'cp-') || str_contains($haystack, 'voip')) {
            $cleanModel = 'Cisco IP Phone';
            if (preg_match('/(?:IP PHONE|CP-)\s*([0-9]{4}[A-Z]*)/i', $modelo . ' ' . $sysDescr, $m)) {
                $cleanModel = 'Cisco IP Phone ' . $m[1];
            } elseif (preg_match('/(?:79\d\d|78\d\d|88\d\d)/', $haystack, $m)) {
                $cleanModel = 'Cisco IP Phone ' . $m[0];
            }

            return [
                'tipo' => 'telefono',
                'image' => 'images/topology/ip-phone.svg',
                'rol' => 'TELÉFONO IP (VOIP)',
                'tipo_equipo' => 'Teléfono IP Cisco Unified Communications',
                'factor_forma' => 'VoIP Desktop Phone',
                'clean_model' => $cleanModel,
                'specs' => [
                    'Categoría' => 'Endpoint de Comunicaciones Unificadas (VoIP)',
                    'Protocolos Soportados' => 'SIP (Session Initiation Protocol) & Cisco SCCP (Skinny)',
                    'Códecs de Audio' => 'G.711a, G.711u, G.729a, G.729ab, iLBC',
                    'Conectividad Física' => '2 Puertos RJ-45 10/100/1000 Mbps Ethernet (Switch Interno de 2 Puertos)',
                    'Puerto 1 (SW / LAN)' => 'Uplink al Switch con alimentación PoE (802.3af Class 2/3)',
                    'Puerto 2 (PC / Data)' => 'Pass-Through Ethernet para conectar la Computadora de Escritorio',
                    'Puertos de Audio' => 'Conector Handset RJ-9 + Conector Diadema / Headset RJ-9',
                    'Consumo Eléctrico' => 'PoE IEEE 802.3af (Clase 2: 6.49W / Clase 3: 12.95W) o Adaptador 48V DC',
                    'QoS & VLANs' => 'VLAN de Voz Automática (802.1Q), QoS 802.1p CoS / DSCP (Expedited Forwarding)',
                    'Seguridad de Llamadas' => 'Cifrado SRTP / TLS, Autenticación 802.1X, Certificados X.509'
                ]
            ];
        }

        // 2. Access Points & Wireless Controllers (Wi-Fi)
        if (str_contains($haystack, 'c9115') || str_contains($haystack, 'air-ap') || str_contains($haystack, 'air-cap') || str_contains($haystack, 'ap software') || str_contains($haystack, 'ap-') || str_contains($haystack, 'wap') || str_contains($haystack, 'c9800') || str_contains($haystack, 'wlc') || str_contains($haystack, 'wifi')) {
            return [
                'tipo' => 'access_point',
                'image' => 'images/topology/access-point.svg',
                'rol' => 'ACCESS POINT WI-FI',
                'tipo_equipo' => 'Punto de Acceso Inalámbrico Empresarial (Wi-Fi 6)',
                'factor_forma' => 'Ceiling Mount AP',
                'clean_model' => 'Cisco Catalyst 9115AX AP',
                'specs' => [
                    'Categoría' => 'Punto de Acceso Inalámbrico Enterprise (Wi-Fi 6 / 802.11ax)',
                    'Estándar Wi-Fi' => '802.11ax (Wi-Fi 6) con soporte 802.11a/b/g/n/ac Wave 2',
                    'Radios' => 'Doble Banda Simultánea 2.4 GHz (4x4 MU-MIMO) + 5 GHz (4x4 MU-MIMO)',
                    'Puerto Uplink' => '1x RJ-45 MultiGigabit (mGig 2.5G / 1G) con PoE+ IEEE 802.3at (30W)',
                    'Gestión' => 'Cisco Catalyst 9800 WLC / CAPWAP / FlexConnect'
                ]
            ];
        }

        // 3. Routers de borde WAN (ISR / Router / 1841 / 4400 / 4451 / 4331 / CUBE / GW)
        if (
            (str_contains($haystack, 'isr') || str_contains($haystack, 'router') || str_contains($haystack, '1841') || str_contains($haystack, '4400') || str_contains($haystack, '4451') || str_contains($haystack, '4331') || str_contains($haystack, 'cube') || str_contains($haystack, 'gw-') || str_contains($haystack, 'rtr')) &&
            !str_contains($haystack, 'catalyst') && !str_contains($haystack, 'cat9k') && !str_contains($haystack, 'ws-c') && !str_starts_with(strtolower($nombre), 'sw-')
        ) {
            return [
                'tipo' => 'router',
                'image' => 'images/topology/router.svg',
                'rol' => 'ROUTER WAN / ISR',
                'tipo_equipo' => 'Router de Borde WAN',
                'factor_forma' => '1U/2U Modular Router',
                'clean_model' => 'Cisco ISR Series',
                'specs' => [
                    'Categoría' => 'Router de Servicios Integrados (Cisco ISR Series)',
                    'Puertos Enrutados' => '3x GbE WAN/LAN (RJ-45 / SFP combo) + Ranuras NIM/HWIC',
                    'Rendimiento' => 'Enrutamiento IP CEF, Cifrado IPsec Hardware, QoS Jerárquico',
                    'Puertos de Gestión' => '1x Consola Serial RJ-45 + 1x Consola USB Mini-B + 1x AUX'
                ]
            ];
        }

        // 4. Chasis modular Nexus / 7000 / 9000
        if (str_contains($haystack, 'nexus') || str_contains($haystack, '7000') || str_contains($haystack, 'n7000') || str_contains($haystack, 'nx-os') || str_contains($haystack, 'n9k') || str_contains($haystack, 'n3000')) {
            return [
                'tipo' => 'switch',
                'image' => 'images/topology/switch-nexus.svg',
                'rol' => 'CORE / MODULAR',
                'tipo_equipo' => 'Chasis Modular de Núcleo Data Center',
                'factor_forma' => 'Modular (Multi-Slot Chassis)',
                'clean_model' => 'Cisco Nexus Data Center',
                'specs' => [
                    'Categoría' => 'Switch Modular de Núcleo Data Center (Cisco Nexus Series)',
                    'Capacidad de Conmutación' => 'Hasta 1.44 Tbps por Slot / Terabit Switching Fabric',
                    'Puertos de Alta Velocidad' => 'Bahías QSFP28 100GbE / SFP28 25GbE / SFP+ 10GbE',
                    'Sistema Operativo' => 'Cisco NX-OS Data Center OS con soporte VXLAN / EVPN y vPC',
                    'Fuentes de Poder' => 'Fuentes Redundantes N+1 Hot-Swap 3000W AC'
                ]
            ];
        }

        // 5. Cisco Catalyst 9300 / 9600
        if (str_contains($haystack, '9300') || str_contains($haystack, 'catalyst 93') || str_contains($haystack, 'cat9k') || str_contains($haystack, 'c93') || str_contains($haystack, 'c9606')) {
            return [
                'tipo' => 'switch',
                'image' => 'images/topology/switch-core.svg',
                'rol' => 'DISTRIBUTION / CORE',
                'tipo_equipo' => 'Switch Multicapa L3 Enterprise',
                'factor_forma' => '1U Rackmount Enterprise',
                'clean_model' => 'Cisco Catalyst 9300',
                'specs' => [
                    'Categoría' => 'Switch Multicapa de Distribución / Core L3 Enterprise',
                    'Capacidad de Stacking' => 'StackWise-480 (480 Gbps Stacking Throughput)',
                    'Puertos Físicos' => '24 / 48 Puertos 10/100/1000 Mbps con UPOE+ / PoE+ (Hasta 90W por puerto)',
                    'Uplinks Modulares' => 'Módulos de Red 4x 10GbE SFP+ / 2x 40GbE QSFP+ / 2x 25GbE SFP28',
                    'Sistema Operativo' => 'Cisco IOS-XE con soporte SD-Access y Cisco DNA'
                ]
            ];
        }

        // 6. Switches de acceso (WS-C, WS-X, Catalyst, 2960, 3750, 3560, 3850, C9200, C1000, SG200, SG300)
        if (
            str_contains($haystack, 'ws-c') || str_contains($haystack, 'ws-x') || str_contains($haystack, 'catalyst') ||
            str_contains($haystack, '2960') || str_contains($haystack, 'sg200') || str_contains($haystack, 'sg220') || str_contains($haystack, 'sg300') ||
            str_contains($haystack, 'c1000') || str_contains($haystack, '3750') || str_contains($haystack, '3560') || str_contains($haystack, '3850') ||
            str_contains($haystack, 'c9200') || str_contains($haystack, '9200l') || str_contains($haystack, 'cisco switch') || str_contains($haystack, 'switch')
        ) {
            $cleanSwitchModel = 'Cisco Catalyst Acceso';
            if (preg_match('/(?:WS-C|C)([0-9]{4}[A-Z0-9-]*)/i', $modelo . ' ' . $nombre, $sm)) {
                $cleanSwitchModel = 'Cisco Catalyst ' . $sm[1];
            }

            return [
                'tipo' => 'switch',
                'image' => 'images/topology/switch-access.svg',
                'rol' => 'ACCESS / SWITCH',
                'tipo_equipo' => 'Switch de Acceso Gigabit Managed',
                'factor_forma' => '1U Rackmount Fixed',
                'clean_model' => $cleanSwitchModel,
                'specs' => [
                    'Categoría' => 'Switch de Acceso Gigabit Ethernet Gestionado',
                    'Puertos Físicos' => '24 / 48 Puertos RJ-45 10/100/1000 Mbps con PoE+ (370W / 740W)',
                    'Puertos de Uplink' => '4x Bahías SFP / SFP+ 10GbE / 1GbE',
                    'VLANs y Enrutamiento' => 'Soporte 4096 VLANs 802.1Q, Enrutamiento Estático IPv4/IPv6',
                    'Seguridad de Puerto' => 'Port-Security con Dynamic ARP Inspection y DHCP Snooping'
                ]
            ];
        }

        // 7. Servidores
        if (str_contains($haystack, 'srv') || str_contains($haystack, 'server') || str_contains($haystack, 'poweredge') || str_contains($haystack, 'proliant') || str_contains($haystack, 'ucs') || str_contains($haystack, 'esxi') || str_contains($haystack, 'hyper-v')) {
            return [
                'tipo' => 'servidor',
                'image' => 'images/topology/server.svg',
                'rol' => 'SERVIDOR DEDICADO',
                'tipo_equipo' => 'Servidor de Cómputo e Infraestructura',
                'factor_forma' => '2U Rackmount Server',
                'clean_model' => 'Servidor Enterprise',
                'specs' => [
                    'Categoría' => 'Servidor de Cómputo e Infraestructura',
                    'Arquitectura' => 'x86_64 Multi-Core / Virtualización KVM / VMware ESXi',
                    'Interfaces de Red' => '2x/4x NIC Gigabit / 10GbE + Puerto Dedicado Out-of-Band (iDRAC / iLO / IPMI)',
                    'Fuentes de Poder' => 'Fuentes Redundantes Dual Hot-Swap 80 PLUS Platinum',
                    'Almacenamiento' => 'Controladora RAID por Hardware con bahías SAS/SATA/NVMe Hot-Plug'
                ]
            ];
        }

        // 8. PC / Workstations (Endpoints de usuario)
        if (
            (str_starts_with(strtoupper($nombre), 'PC-') || str_starts_with(strtoupper($nombre), 'DESKTOP-') || str_starts_with(strtoupper($nombre), 'LAPTOP-') || str_starts_with(strtoupper($nombre), 'HOST-') || str_contains($haystack, 'workstation')) &&
            !str_contains($haystack, 'cisco') && !str_contains($haystack, 'switch') && !str_contains($haystack, 'ws-c') && !str_contains($haystack, 'ios')
        ) {
            return [
                'tipo' => 'pc',
                'image' => 'images/topology/pc.svg',
                'rol' => 'PC / WORKSTATION',
                'tipo_equipo' => 'Estación de Trabajo / Cliente de Red',
                'factor_forma' => 'Desktop / Host Client',
                'clean_model' => 'Estación de Trabajo PC',
                'specs' => [
                    'Categoría' => 'Estación de Trabajo de Usuario / Host Client',
                    'Interfaz de Red' => '1x Conector RJ-45 10/100/1000BASE-T Gigabit Ethernet',
                    'Protocolos' => 'TCP/IP, DHCP Client, DNS, NetBIOS, 802.1X',
                    'Alimentación' => 'Fuente Interna ATX 110-240V AC'
                ]
            ];
        }

        // 9. Switch estándar limpio de 1U por defecto
        return [
            'tipo' => 'switch',
            'image' => 'images/topology/switch-standard-1u.svg',
            'rol' => 'SWITCH 1U',
            'tipo_equipo' => 'Switch Gestionado 1U',
            'factor_forma' => '1U Rackmount',
            'clean_model' => 'Switch Ethernet Gestionado',
            'specs' => [
                'Categoría' => 'Switch de Infraestructura de Red',
                'Puertos' => 'Conectividad Ethernet Gigabit con soporte 802.1Q VLANs y STP'
            ]
        ];
    }

    public function descargarPdf($id, \App\Services\PdfReportService $pdfService)
    {
        $dispositivo = Dispositivo::findOrFail($id);
        return $pdfService->descargarReporteDispositivo($dispositivo);
    }
}
