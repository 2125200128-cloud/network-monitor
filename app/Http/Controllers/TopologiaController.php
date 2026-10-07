<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dispositivo;
use App\Models\EnlaceRed;
use App\Models\PosicionTopologia;

class TopologiaController extends Controller
{
    /**
     * Vista principal del mapa de topología de red
     */
    public function index()
    {
        $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])->get();

        $enlaces = EnlaceRed::with([
            'dispositivoOrigen',
            'dispositivoDestino',
            'interfazOrigen',
            'interfazDestino'
        ])->get();

        $this->enriquecerEnlacesConTelemetriaViva($enlaces);

        $grafoData = $this->compilarGrafoData($dispositivos, $enlaces);

        // Métricas de topología precisas
        $totalNodos = $dispositivos->count();
        $totalEnlaces = $enlaces->count();
        $enlacesActivos = $enlaces->where('estado', 'up')->count();
        $enlacesAlerta = $enlaces->where('estado', '!=', 'up')->count();
        $anchoBandaTotalGbps = round($enlaces->sum('velocidad_mbps') / 1000, 1);
        $traficoTotalMbps = round($enlaces->sum('trafico_in_mbps') + $enlaces->sum('trafico_out_mbps'), 1);

        $config = \App\Models\ConfiguracionGeneral::first();

        return view('topologia.index', compact(
            'dispositivos',
            'enlaces',
            'grafoData',
            'totalNodos',
            'totalEnlaces',
            'enlacesActivos',
            'enlacesAlerta',
            'anchoBandaTotalGbps',
            'traficoTotalMbps',
            'config'
        ));
    }

    /**
     * Endpoint API JSON para sincronización y telemetría en vivo del grafo
     */
    public function datosGrafos()
    {
        $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])->get();

        $enlaces = EnlaceRed::with([
            'dispositivoOrigen',
            'dispositivoDestino',
            'interfazOrigen',
            'interfazDestino'
        ])->get();

        $this->enriquecerEnlacesConTelemetriaViva($enlaces);

        $grafoData = $this->compilarGrafoData($dispositivos, $enlaces);

        return response()->json($grafoData);
    }

    /**
     * Guarda la disposición cartográfica / coordenadas de los nodos de la topología para la cuenta autenticada
     */
    public function guardarPosiciones(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $posiciones = $request->input('posiciones');
        if (!is_array($posiciones)) {
            return response()->json(['success' => false, 'message' => 'Estructura de posiciones inválida'], 400);
        }

        $posicionesSanitizadas = [];
        foreach ($posiciones as $nodeId => $pos) {
            if (is_array($pos) && isset($pos['x']) && isset($pos['y'])) {
                $posicionesSanitizadas[$nodeId] = [
                    'x' => round((float)$pos['x'], 2),
                    'y' => round((float)$pos['y'], 2),
                ];
            }
        }

        PosicionTopologia::updateOrCreate(
            ['user_id' => $user->id],
            ['posiciones' => $posicionesSanitizadas]
        );

        return response()->json([
            'success' => true,
            'message' => 'Acomodo de topología guardado correctamente para la cuenta.',
            'saved_count' => count($posicionesSanitizadas)
        ]);
    }

    /**
     * Ajusta la velocidad y el estado en vivo de los enlaces según la salud de los equipos conectados
     */
    private function enriquecerEnlacesConTelemetriaViva($enlaces)
    {
        $enlaces->each(function ($enlace) {
            $origInt = $enlace->interfazOrigen ? $enlace->interfazOrigen->nombre : '';
            $destInt = $enlace->interfazDestino ? $enlace->interfazDestino->nombre : '';

            // Detección automática de velocidad según el nombre de la interfaz (10G, 25G, 100G, 100M, etc.)
            if (stripos($origInt, 'TenGigabit') !== false || stripos($destInt, 'TenGigabit') !== false || stripos($origInt, 'Te') === 0 || stripos($destInt, 'Te') === 0) {
                $enlace->velocidad_mbps = 10000;
                $enlace->tipo_medio = 'fibra_optica';
            } elseif (stripos($origInt, 'TwentyFiveGigabit') !== false || stripos($destInt, 'TwentyFiveGigabit') !== false || stripos($origInt, '25G') === 0) {
                $enlace->velocidad_mbps = 25000;
                $enlace->tipo_medio = 'fibra_optica';
            } elseif (stripos($origInt, 'HundredGigabit') !== false || stripos($destInt, 'HundredGigabit') !== false || stripos($origInt, 'Hu') === 0) {
                $enlace->velocidad_mbps = 100000;
                $enlace->tipo_medio = 'fibra_optica';
            } elseif (stripos($origInt, 'FastEthernet') !== false || stripos($destInt, 'FastEthernet') !== false || stripos($origInt, 'Fa') === 0) {
                $enlace->velocidad_mbps = 100;
                $enlace->tipo_medio = 'cobre_utp';
            }

            // Estado dinámico: si alguno de los equipos de los extremos está caído, el enlace físico se reporta como caído (DOWN)
            $origEstado = $enlace->dispositivoOrigen->estado ?? 'online';
            $destEstado = $enlace->dispositivoDestino->estado ?? 'online';
            if ($origEstado === 'offline' || $destEstado === 'offline') {
                $enlace->estado = 'down';
            } elseif ($origEstado === 'warning' || $destEstado === 'warning') {
                $enlace->estado = 'degraded';
            }
        });
    }

    /**
     * Transforma modelos Eloquent en la estructura optimizada para Vis.js / Canvas
     */
    private function compilarGrafoData($dispositivos, $enlaces)
    {
        $nodes = [];
        $edges = [];

        // Cargar posiciones personalizadas guardadas para la cuenta autenticada
        $userPosiciones = [];
        if (auth()->check()) {
            $posModel = PosicionTopologia::where('user_id', auth()->id())->first();
            if ($posModel && is_array($posModel->posiciones)) {
                $userPosiciones = $posModel->posiciones;
            }
        }

        // Coordenadas predeterminadas tipo Blueprint para un layout limpio inicial
        $coordenadas = [
            1 => ['x' => 0, 'y' => 200],       // Switch Core abajo en el centro
            2 => ['x' => -360, 'y' => -180],   // Router Edge arriba a la izquierda
            3 => ['x' => 360, 'y' => -180],    // Switch Access arriba a la derecha
            4 => ['x' => 0, 'y' => -180],      // Cisco SG200 Lab arriba en el centro
        ];

        // Función de ayuda para abreviar nombres largos de interfaces Cisco
        $abrev = function ($name) {
            if (!$name) return 'Port';
            return preg_replace('/^GigabitEthernet/i', 'Gi',
                   preg_replace('/^FastEthernet/i', 'Fa',
                   preg_replace('/^TenGigabitEthernet/i', 'Te',
                   preg_replace('/^TwentyFiveGigabitEthernet/i', '25G',
                   preg_replace('/^FortyGigabitEthernet/i', 'Fo',
                   preg_replace('/^HundredGigabitEthernet/i', 'Hu',
                   preg_replace('/^Ethernet/i', 'Eth', $name)))))));
        };

        // Pre-compilar índice de conexiones por dispositivo (para el panel inspector y badges)
        $conexionesPorDispositivo = [];
        $conteoEnlacesPar = [];

        foreach ($enlaces as $enlace) {
            $origenNombre = $enlace->interfazOrigen ? $enlace->interfazOrigen->nombre : 'P1';
            $destinoNombre = $enlace->interfazDestino ? $enlace->interfazDestino->nombre : 'P2';
            $origenAbrev = $abrev($origenNombre);
            $destinoAbrev = $abrev($destinoNombre);
            $velStr = ($enlace->velocidad_mbps >= 1000 ? ($enlace->velocidad_mbps / 1000) . 'G' : $enlace->velocidad_mbps . 'M');

            $conexionesPorDispositivo[$enlace->origen_dispositivo_id][] = [
                'enlace_id' => $enlace->id,
                'puerto_local' => $origenAbrev,
                'puerto_local_completo' => $origenNombre,
                'remoto_id' => $enlace->destino_dispositivo_id,
                'remoto_nombre' => $enlace->dispositivoDestino->nombre ?? 'Equipo Destino',
                'remoto_ip' => $enlace->dispositivoDestino->ip ?? '',
                'puerto_remoto' => $destinoAbrev,
                'puerto_remoto_completo' => $destinoNombre,
                'tipo_medio' => strtoupper(str_replace('_', ' ', $enlace->tipo_medio)),
                'velocidad' => $velStr,
                'estado' => strtoupper($enlace->estado),
                'color' => $enlace->estado === 'up' ? '#10b981' : ($enlace->estado === 'down' ? '#ef4444' : '#f26419')
            ];

            $conexionesPorDispositivo[$enlace->destino_dispositivo_id][] = [
                'enlace_id' => $enlace->id,
                'puerto_local' => $destinoAbrev,
                'puerto_local_completo' => $destinoNombre,
                'remoto_id' => $enlace->origen_dispositivo_id,
                'remoto_nombre' => $enlace->dispositivoOrigen->nombre ?? 'Equipo Origen',
                'remoto_ip' => $enlace->dispositivoOrigen->ip ?? '',
                'puerto_remoto' => $origenAbrev,
                'puerto_remoto_completo' => $origenNombre,
                'tipo_medio' => strtoupper(str_replace('_', ' ', $enlace->tipo_medio)),
                'velocidad' => $velStr,
                'estado' => strtoupper($enlace->estado),
                'color' => $enlace->estado === 'up' ? '#10b981' : ($enlace->estado === 'down' ? '#ef4444' : '#f26419')
            ];
        }

        foreach ($dispositivos as $disp) {
            $ultimaMetrica = $disp->ultimaMetrica;
            $cpu = $ultimaMetrica && isset($ultimaMetrica->cpu_usage) ? $ultimaMetrica->cpu_usage : 15;
            $mem = $ultimaMetrica && isset($ultimaMetrica->memory_usage) ? $ultimaMetrica->memory_usage : 30;
            $ping = $ultimaMetrica && isset($ultimaMetrica->ping_ms) ? $ultimaMetrica->ping_ms : 1.2;

            $modeloExacto = $disp->telemetriaChasis->model_name ?? '';
            $osVersion = $disp->telemetriaChasis->os_version ?? '';
            
            // Mapeo dinámico de hardware fotorrealista según el modelo real
            $macVendor = '';
            if (!empty($disp->mac_address)) {
                $macVendor = \App\Services\MacVendorResolver::resolveVendor($disp->mac_address);
            }
            $numConexiones = count($conexionesPorDispositivo[$disp->id] ?? []);

            $deviceType = $this->resolveDeviceImageAndRole($disp->nombre, $modeloExacto, $osVersion, $numConexiones, $macVendor);
            $rol = $deviceType['rol'];
            $imagePath = $deviceType['image'];
            $nodeSize = $deviceType['size'];

            // Indicador de estado del equipo
            $statusColor = '#10b981'; // online
            if ($disp->estado === 'warning') {
                $statusColor = '#f26419';
            } elseif ($disp->estado === 'offline') {
                $statusColor = '#ef4444';
            }

            if (isset($userPosiciones[$disp->id]) && isset($userPosiciones[$disp->id]['x']) && isset($userPosiciones[$disp->id]['y'])) {
                $pos = [
                    'x' => (float)$userPosiciones[$disp->id]['x'],
                    'y' => (float)$userPosiciones[$disp->id]['y'],
                ];
            } else {
                $pos = $coordenadas[$disp->id] ?? ['x' => rand(-200, 200), 'y' => rand(-200, 200)];
            }

            $imageUrl = $imagePath ? asset($imagePath) : null;

            $nodes[] = [
                'id' => $disp->id,
                'label' => $disp->nombre . "\n" . $disp->ip,
                'title' => $disp->nombre . ' (' . $disp->ip . ')',
                'x' => $pos['x'],
                'y' => $pos['y'],
                'shape' => 'image',
                'image' => $imageUrl,
                'size' => $nodeSize,
                'borderWidth' => 0,
                'borderWidthSelected' => 0,
                'font' => [
                    'color' => '#1e293b',
                    'size' => 15,
                    'face' => 'Inter, system-ui, sans-serif',
                    'strokeWidth' => 3,
                    'strokeColor' => '#ffffff',
                    'vadjust' => 34,
                    'align' => 'center'
                ],
                'shadow' => [
                    'enabled' => true,
                    'color' => 'rgba(15, 23, 42, 0.22)',
                    'size' => 14,
                    'x' => 0,
                    'y' => 5
                ],
                'customData' => [
                    'id' => $disp->id,
                    'nombre' => $disp->nombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion,
                    'estado' => $disp->estado,
                    'statusColor' => $statusColor,
                    'rol' => strtoupper($rol),
                    'tipo_equipo' => $deviceType['tipo_equipo'],
                    'factor_forma' => $deviceType['factor_forma'],
                    'cpu' => $cpu,
                    'memoria' => $mem,
                    'ping' => $ping,
                    'modelo' => !empty($modeloExacto) ? $modeloExacto : (str_contains(strtolower($disp->nombre), 'genérico') ? 'Desconocido' : 'Cisco Catalyst 9300'),
                    'serial' => $disp->telemetriaChasis->serial_number ?? (str_contains(strtolower($disp->nombre), 'genérico') ? 'N/A' : 'FOC2438L8PQ'),
                    'uptime' => $disp->telemetriaChasis->uptime_str ?? 'Desconocido',
                    'sysDescr' => $osVersion,
                    'url' => route('dispositivos.show', $disp->id),
                    'image' => $imageUrl,
                    'conexiones' => $conexionesPorDispositivo[$disp->id] ?? [],
                    'sensores_temperatura' => $disp->telemetriaChasis->sensores_temperatura ?? [],
                    'pc_conectada' => (str_starts_with(strtoupper($disp->nombre), 'SEP') || str_contains(strtoupper($rol), 'VOIP') || str_contains(strtoupper($disp->nombre), 'PHONE')) ? \App\Services\PhonePcLinkResolver::resolveAttachedPc($disp) : null
                ]
            ];
        }

        foreach ($enlaces as $enlace) {
            $origenNombre = $enlace->interfazOrigen ? $enlace->interfazOrigen->nombre : 'P1';
            $destinoNombre = $enlace->interfazDestino ? $enlace->interfazDestino->nombre : 'P2';
            $origenAbrev = $abrev($origenNombre);
            $destinoAbrev = $abrev($destinoNombre);

            // Colores y grosor según estado y velocidad
            $color = '#10b981'; // up (esmeralda)
            $dashes = false;
            $width = 3;

            if ($enlace->estado === 'down') {
                $color = '#ef4444'; // down (rojo)
                $dashes = [6, 4];
                $width = 2.5;
            } elseif ($enlace->estado === 'degraded' || $enlace->porcentaje_saturacion > 80) {
                $color = '#f26419'; // warning / saturado (naranja)
                $width = 4;
            }

            // Enlaces de 10G más gruesos que 1G
            if ($enlace->velocidad_mbps >= 10000 && $enlace->estado !== 'down') {
                $width = 4.5;
            }

            $etiquetaVelocidad = ($enlace->velocidad_mbps >= 1000)
                ? ($enlace->velocidad_mbps / 1000) . 'G'
                : $enlace->velocidad_mbps . 'M';

            $traficoTotal = round($enlace->trafico_in_mbps + $enlace->trafico_out_mbps, 1);
            
            // Etiqueta concisa de velocidad para evitar saturación visual en la topología
            $label = $etiquetaVelocidad . ($enlace->estado === 'down' ? ' (CAÍDO)' : '');

            $origenDisp = $enlace->dispositivoOrigen->nombre ?? 'Origen';
            $destinoDisp = $enlace->dispositivoDestino->nombre ?? 'Destino';

            // Tooltip informativo al pasar el cursor sobre el cable
            $title = "Conexión:\n" .
                     "• " . $origenDisp . " [" . $origenNombre . "]\n" .
                     "       ↕\n" .
                     "• " . $destinoDisp . " [" . $destinoNombre . "]\n\n" .
                     "Velocidad: " . ($enlace->velocidad_mbps >= 1000 ? ($enlace->velocidad_mbps / 1000) . ' Gbps' : $enlace->velocidad_mbps . ' Mbps') . "\n" .
                     "Tráfico: " . $traficoTotal . " Mbps (In: {$enlace->trafico_in_mbps}M / Out: {$enlace->trafico_out_mbps}M)\n" .
                     "Medio: " . strtoupper(str_replace('_', ' ', $enlace->tipo_medio)) . "\n" .
                     "VLANs: " . ($enlace->vlans_permitidas ?? 'VLAN 1') . "\n" .
                     "Estado: " . strtoupper($enlace->estado);

            // Separación de curvatura si hay múltiples enlaces entre el mismo par
            $parKey = min($enlace->origen_dispositivo_id, $enlace->destino_dispositivo_id) . '_' . 
                      max($enlace->origen_dispositivo_id, $enlace->destino_dispositivo_id);
            $conteoEnlacesPar[$parKey] = ($conteoEnlacesPar[$parKey] ?? 0) + 1;
            $roundness = 0.2 + (($conteoEnlacesPar[$parKey] - 1) * 0.2);
            $smoothType = ($conteoEnlacesPar[$parKey] % 2 === 0) ? 'curvedCCW' : 'curvedCW';

            $edges[] = [
                'id' => $enlace->id,
                'from' => $enlace->origen_dispositivo_id,
                'to' => $enlace->destino_dispositivo_id,
                'label' => $label,
                'title' => $title,
                'font' => [
                    'align' => 'horizontal',
                    'size' => 9,
                    'color' => '#64748b',
                    'background' => '#ffffff',
                    'strokeWidth' => 0
                ],
                'color' => [
                    'color' => $color,
                    'highlight' => '#f26419',
                    'hover' => '#3b5998',
                    'opacity' => 0.9
                ],
                'width' => $width,
                'dashes' => $dashes,
                'smooth' => [
                    'enabled' => true,
                    'type' => $smoothType,
                    'roundness' => $roundness
                ],
                'customData' => [
                    'id' => $enlace->id,
                    'origen_id' => $enlace->origen_dispositivo_id,
                    'origen_disp' => $origenDisp,
                    'origen_puerto' => $origenNombre,
                    'origen_puerto_abrev' => $origenAbrev,
                    'destino_id' => $enlace->destino_dispositivo_id,
                    'destino_disp' => $destinoDisp,
                    'destino_puerto' => $destinoNombre,
                    'destino_puerto_abrev' => $destinoAbrev,
                    'tipo_medio' => strtoupper(str_replace('_', ' ', $enlace->tipo_medio)),
                    'velocidad_mbps' => $enlace->velocidad_mbps,
                    'estado' => strtoupper($enlace->estado),
                    'color' => $color,
                    'trafico_in' => $enlace->trafico_in_mbps,
                    'trafico_out' => $enlace->trafico_out_mbps,
                    'saturacion_pct' => $enlace->porcentaje_saturacion,
                    'vlans' => $enlace->vlans_permitidas ?? 'VLAN 1, 10, 20'
                ]
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'hasSavedPositions' => !empty($userPosiciones),
            'userPosiciones' => $userPosiciones
        ];
    }

    /**
     * Mapeo dinámico de hardware fotorrealista según el modelo exacto del equipo.
     */
    protected function resolveDeviceImageAndRole(string $nombre, string $modelo, string $sysDescr = '', int $numConexiones = 0, string $vendor = ''): array
    {
        $nombreUpper = strtoupper(trim($nombre));
        $haystack = strtolower($nombre . ' ' . $modelo . ' ' . $sysDescr);

        // 1. Teléfonos IP Cisco (VoIP)
        if (
            str_starts_with($nombreUpper, 'SEP') || 
            str_contains($haystack, 'ip phone') || 
            str_contains($haystack, 'cp-') || 
            str_contains($haystack, 'voip')
        ) {
            return [
                'image' => 'images/topology/ip-phone.svg',
                'rol' => 'TELÉFONO IP (VOIP)',
                'tipo_equipo' => 'Teléfono IP Cisco',
                'factor_forma' => 'VoIP Endpoint',
                'size' => 110
            ];
        }

        // 2. Wireless LAN Controllers (WLC) y Access Points dedicados (Wi-Fi)
        if (
            str_contains($haystack, 'wlc') || 
            str_contains($haystack, 'c9800') || 
            str_contains($haystack, 'air-ct') ||
            (
                (str_contains($haystack, 'c9115') || str_contains($haystack, 'air-ap') || str_contains($haystack, 'air-cap') || str_contains($haystack, 'ap software') || str_starts_with($nombreUpper, 'AP-') || str_starts_with($nombreUpper, 'WAP-')) &&
                !str_contains($haystack, 'switch') && !str_starts_with($nombreUpper, 'SW')
            )
        ) {
            return [
                'image' => 'images/topology/access-point.svg',
                'rol' => 'ACCESS POINT / WLC',
                'tipo_equipo' => 'Controlador / AP Inalámbrico Cisco',
                'factor_forma' => 'Ceiling / Wall Mount AP',
                'size' => 125
            ];
        }

        // 3. Routers de borde WAN y Voice Gateways (ISR / Router / 1841 / 4400 / 4451 / 4331 / CUBE / GW)
        if (
            (
                str_contains($haystack, 'isr') || str_contains($haystack, 'router') || 
                str_contains($haystack, '1841') || str_contains($haystack, '4400') || 
                str_contains($haystack, '4451') || str_contains($haystack, '4331') || 
                str_contains($haystack, 'cube') || str_contains($haystack, 'gw-') || 
                str_contains($haystack, '-gw') || str_contains($haystack, 'gw_') || 
                str_contains($haystack, 'rtr') || str_contains($haystack, 'edge') || 
                str_contains($haystack, 'sat.') || str_contains($haystack, '-sat')
            ) &&
            !str_contains($haystack, 'catalyst') && !str_contains($haystack, 'cat9k') && 
            !str_contains($haystack, 'ws-c') && !str_starts_with($nombreUpper, 'SW') && 
            !str_contains($haystack, 'swc') && !str_contains($haystack, 'swe') && 
            !str_contains($haystack, 'switch') && !str_starts_with($nombreUpper, 'NX') && 
            !str_contains($haystack, 'nexus')
        ) {
            return [
                'image' => 'images/topology/router.svg',
                'rol' => 'ROUTER / EDGE',
                'tipo_equipo' => 'Router de Borde WAN / Gateway',
                'factor_forma' => 'Router Cisco ISR',
                'size' => 140
            ];
        }

        // 4. Chasis modular Nexus / Datacenter / ACI (Nexus 7000 / 9000 / 3000 / Leaf / Spine)
        if (
            str_contains($haystack, 'nexus') || str_contains($haystack, 'nx-os') || 
            str_contains($haystack, 'nxos') || str_contains($haystack, 'n7000') || 
            str_contains($haystack, 'n7k') || str_contains($haystack, 'nx7k') || 
            str_contains($haystack, 'n9k') || str_contains($haystack, 'nx9k') || 
            str_contains($haystack, 'n3k') || str_contains($haystack, 'nx3k') || 
            str_contains($haystack, 'leaf') || str_contains($haystack, 'spine') || 
            str_contains($haystack, 'aci-') || str_contains($haystack, 'aci_') ||
            str_contains($haystack, '93180') || str_contains($haystack, '93240') || 
            str_contains($haystack, '9372')
        ) {
            return [
                'image' => 'images/topology/switch-nexus.svg',
                'rol' => 'NEXUS / DATACENTER',
                'tipo_equipo' => 'Switch Cisco Nexus Datacenter / ACI',
                'factor_forma' => 'Modular Multi-Slot Chassis',
                'size' => 170
            ];
        }

        // 5. Switches Core / Distribución / Multicapa (Catalyst 9300 / 9600 / 6500 / Core / MDF)
        if (
            str_contains($haystack, '9300') || str_contains($haystack, 'catalyst 93') || 
            str_contains($haystack, 'cat9k') || str_contains($haystack, 'c93') || 
            str_contains($haystack, 'c9606') || str_contains($haystack, '6500') || 
            str_contains($haystack, 'core01') || str_contains($haystack, 'core-') || 
            str_contains($haystack, 'core_') || str_contains($haystack, 'mdf')
        ) {
            return [
                'image' => 'images/topology/switch-core.svg',
                'rol' => 'DISTRIBUTION / CORE',
                'tipo_equipo' => 'Switch Multicapa L3 Enterprise Core',
                'factor_forma' => '1U/Modular Enterprise Chassis',
                'size' => 155
            ];
        }

        // 6. Servidores Enterprise Físicos Dedicados (IBM / Dell / HPE / Cisco UCS / ESXi)
        if (
            (
                str_contains($haystack, 'ibm') || str_contains($haystack, 'system x') || 
                str_contains($haystack, 'bladecenter') || str_contains($haystack, 'imm') || 
                str_contains($haystack, 'thinksystem') || str_contains($haystack, 'poweredge') || 
                str_contains($haystack, 'proliant') || str_contains($haystack, 'ucs-b') || 
                str_contains($haystack, 'esxi') || str_contains($haystack, 'hyper-v') || 
                str_contains($haystack, 'windows server')
            ) &&
            !str_starts_with($nombreUpper, 'SW') && !str_starts_with($nombreUpper, 'NX') && 
            !str_contains($haystack, 'switch') && !str_contains($haystack, 'catalyst')
        ) {
            $tipoStr = 'Servidor Enterprise';
            if (str_contains($haystack, 'ibm') || str_contains($haystack, 'system x') || str_contains($haystack, 'bladecenter')) {
                $tipoStr = 'Servidor IBM System x / BladeCenter';
            }
            return [
                'image' => 'images/topology/server.png',
                'rol' => 'SERVIDOR',
                'tipo_equipo' => $tipoStr,
                'factor_forma' => 'Rackmount / Blade Enterprise',
                'size' => 200
            ];
        }

        // 7. PC / Workstation / Desktop (Solo estaciones de trabajo explícitas)
        if (
            (
                str_starts_with($nombreUpper, 'PC-') || 
                str_starts_with($nombreUpper, 'DESKTOP-') || 
                str_starts_with($nombreUpper, 'LAPTOP-') || 
                str_starts_with($nombreUpper, 'HOST-') || 
                str_contains($haystack, 'workstation')
            ) &&
            !str_contains($haystack, 'cisco') && !str_contains($haystack, 'switch') && 
            !str_starts_with($nombreUpper, 'SW') && !str_contains($haystack, 'sw-') && 
            !str_contains($haystack, 'sw_') && !str_contains($haystack, 'swc') && 
            !str_contains($haystack, 'ws-c') && !str_contains($haystack, 'ios')
        ) {
            return [
                'image' => 'images/topology/pc.svg',
                'rol' => 'PC / WORKSTATION',
                'tipo_equipo' => 'Estación de Trabajo / PC',
                'factor_forma' => 'Desktop / Host',
                'size' => 120
            ];
        }

        // 8. Por defecto en infraestructura -> Switch de Acceso L2/L3 Gestionado
        return [
            'image' => 'images/topology/switch-access.svg',
            'rol' => 'ACCESS / SWITCH',
            'tipo_equipo' => 'Switch de Acceso Gigabit Managed',
            'factor_forma' => '1U Rackmount Fixed',
            'size' => 145
        ];
    }
}
