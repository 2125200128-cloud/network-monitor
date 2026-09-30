<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dispositivo;
use App\Models\EnlaceRed;

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

        $grafoData = $this->compilarGrafoData($dispositivos, $enlaces);

        // Métricas de topología
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

        $grafoData = $this->compilarGrafoData($dispositivos, $enlaces);

        return response()->json($grafoData);
    }

    /**
     * Transforma modelos Eloquent en la estructura optimizada para Vis.js / Canvas
     */
    private function compilarGrafoData($dispositivos, $enlaces)
    {
        $nodes = [];
        $edges = [];

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

            $pos = $coordenadas[$disp->id] ?? ['x' => rand(-200, 200), 'y' => rand(-200, 200)];
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
                    'size' => 11,
                    'face' => 'Inter, system-ui, sans-serif',
                    'strokeWidth' => 0,
                    'vadjust' => 14,
                    'align' => 'center'
                ],
                'shadow' => [
                    'enabled' => true,
                    'color' => 'rgba(15, 23, 42, 0.12)',
                    'size' => 8,
                    'x' => 0,
                    'y' => 3
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
                    'sensores_temperatura' => $disp->telemetriaChasis->sensores_temperatura ?? []
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
            'edges' => $edges
        ];
    }

    /**
     * Mapeo dinámico de hardware fotorrealista según el modelo exacto del equipo.
     */
    protected function resolveDeviceImageAndRole(string $nombre, string $modelo, string $sysDescr = '', int $numConexiones = 0, string $vendor = ''): array
    {
        $haystack = strtolower($nombre . ' ' . $modelo . ' ' . $sysDescr);

        // 1. Chasis modular Nexus / 7000
        if (str_contains($haystack, 'nexus') || str_contains($haystack, '7000') || str_contains($haystack, 'n7000') || str_contains($haystack, 'nx-os') || str_contains($haystack, 'n9k') || str_contains($haystack, 'n3000')) {
            return [
                'image' => 'images/topology/switch-nexus.svg',
                'rol' => 'CORE / MODULAR',
                'tipo_equipo' => 'Chasis Modular de Núcleo',
                'factor_forma' => 'Modular (Multi-Slot Chassis)',
                'size' => 30
            ];
        }

        // 2. Cisco Catalyst 9300 / 93 / 9600 / 9800
        if (str_contains($haystack, '9300') || str_contains($haystack, 'catalyst 93') || str_contains($haystack, 'cat9k') || str_contains($haystack, 'c93') || str_contains($haystack, 'c9606') || str_contains($haystack, 'c9800')) {
            return [
                'image' => 'images/topology/switch-core.svg',
                'rol' => 'DISTRIBUTION / CORE',
                'tipo_equipo' => 'Switch Multicapa L3 Enterprise',
                'factor_forma' => '1U Rackmount Enterprise',
                'size' => 26
            ];
        }

        // 3. Switch de acceso 24/48 puertos (2960 / SG200 / C1000 / 3750 / 9200)
        if (str_contains($haystack, '2960') || str_contains($haystack, 'sg200') || str_contains($haystack, 'c1000') || str_contains($haystack, '3750') || str_contains($haystack, 'c9200')) {
            return [
                'image' => 'images/topology/switch-access.svg',
                'rol' => 'ACCESS',
                'tipo_equipo' => 'Switch de Acceso Gigabit Managed',
                'factor_forma' => '1U Rackmount Fixed',
                'size' => 26
            ];
        }

        // 4. Chasis del router de borde (ISR / Router / 1841 / 4400 / edge)
        if (str_contains($haystack, 'isr') || str_contains($haystack, 'router') || str_contains($haystack, '1841') || str_contains($haystack, '4400') || str_contains($haystack, 'edge')) {
            return [
                'image' => 'images/topology/router-edge.svg',
                'rol' => 'ROUTER / EDGE',
                'tipo_equipo' => 'Router de Borde WAN',
                'factor_forma' => '1U/2U Modular Router',
                'size' => 26
            ];
        }

        // 5. Nodos Genéricos / Computadoras (Endpoints)
        if (str_contains($haystack, 'genérico') || str_contains($haystack, 'computadora') || str_contains($haystack, 'endpoint') || str_contains($haystack, 'generico') || str_contains($haystack, 'pc') || empty($modelo)) {
            
            // Heurística de conexiones para detectar Switches no administrados o sin SNMP
            if ($numConexiones > 3) {
                if (str_contains(strtolower($vendor), 'cisco')) {
                    return [
                        'image' => 'images/topology/switch-l2.svg',
                        'rol' => 'CISCO SWITCH (SIN SNMP)',
                        'tipo_equipo' => 'Switch Infraestructura Cisco (SNMP Fallido)',
                        'factor_forma' => 'Switch Detectado por MAC y CDP',
                        'size' => 26
                    ];
                } else {
                    return [
                        'image' => 'images/topology/switch-standard-1u.svg',
                        'rol' => 'SWITCH NO ADMINISTRADO',
                        'tipo_equipo' => 'Switch Genérico (Deducido por enlaces)',
                        'factor_forma' => 'Switch 1U Genérico',
                        'size' => 26
                    ];
                }
            }

            return [
                'image' => 'images/topology/switch-access.svg',
                'rol' => 'ENDPOINT',
                'tipo_equipo' => 'Dispositivo Final (PC/Servidor/Impresora)',
                'factor_forma' => 'Endpoint',
                'size' => 35
            ];
        }

        // 6. Switch estándar limpio de 1U por defecto
        return [
            'image' => 'images/topology/switch-standard-1u.svg',
            'rol' => 'SWITCH 1U',
            'tipo_equipo' => 'Switch Gestionado 1U',
            'factor_forma' => '1U Rackmount',
            'size' => 26
        ];
    }
}
