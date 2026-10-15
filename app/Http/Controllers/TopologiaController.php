<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Dispositivo;
use App\Models\EnlaceRed;
use App\Models\NotaDispositivo;
use App\Models\TopologiaPlano;
use App\Models\ZonaTopologia;

class TopologiaController extends Controller
{
    private const CACHE_GRAFO = 'topologia_datos_grafos_fast';

    /**
     * El grafo serializado (~1 MB) supera max_allowed_packet de MySQL: se cachea en archivo.
     */
    public static function cacheGrafo(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store('file');
    }

    public static function olvidarCacheGrafo(): void
    {
        self::cacheGrafo()->forget(self::CACHE_GRAFO);
    }

    /**
     * Vista principal del mapa de topología de red
     */
    public function index()
    {
        $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])->get();

        $enlaces = EnlaceRed::with([
            'dispositivoOrigen',
            'dispositivoDestino',
            'interfazOrigen.ultimaTelemetria',
            'interfazDestino.ultimaTelemetria'
        ])->get();

        $this->enriquecerEnlacesConTelemetriaViva($enlaces);

        $grafoData = $this->compilarGrafoData($dispositivos, $enlaces);
        $resumen = $grafoData['resumen'];
        $totalEnlaces = $resumen['enl_total'];

        $config = \App\Models\ConfiguracionGeneral::first();
        $puedeEditar = in_array(auth()->user()?->role, ['Admin', 'Operador'], true);

        // Compilar notificaciones para el banner carrusel NOC LIVE
        $notificaciones = (new \App\Http\Controllers\DashboardController)->compilarNotificaciones();
        $unreadNotificaciones = count(array_filter($notificaciones, fn($n) => !$n['leida']));

        return view('topologia.index', compact(
            'grafoData',
            'resumen',
            'totalEnlaces',
            'config',
            'puedeEditar',
            'notificaciones',
            'unreadNotificaciones'
        ));
    }

    /**
     * Endpoint API JSON para sincronización y telemetría en vivo del grafo
     */
    public function datosGrafos()
    {
        // El plano es compartido: una sola caché para todos
        $grafoData = self::cacheGrafo()->remember(self::CACHE_GRAFO, 5, function () {
            $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])->get();

            $enlaces = EnlaceRed::with([
                'dispositivoOrigen',
                'dispositivoDestino',
                'interfazOrigen.ultimaTelemetria',
                'interfazDestino.ultimaTelemetria'
            ])->get();

            $this->enriquecerEnlacesConTelemetriaViva($enlaces);

            return $this->compilarGrafoData($dispositivos, $enlaces);
        });

        return response()->json($grafoData);
    }

    /**
     * Guarda el acomodo del plano compartido (Admin/Operador). Admite actualización parcial:
     * solo se reemplazan los nodos enviados.
     */
    public function guardarPosiciones(Request $request)
    {
        $posiciones = $request->input('posiciones');
        if (!is_array($posiciones)) {
            return response()->json(['success' => false, 'message' => 'Estructura de posiciones inválida'], 400);
        }

        $plano = TopologiaPlano::actual();
        $actuales = is_array($plano->posiciones) ? $plano->posiciones : [];

        $guardados = 0;
        foreach ($posiciones as $nodeId => $pos) {
            if (is_numeric($nodeId) && is_array($pos) && is_numeric($pos['x'] ?? null) && is_numeric($pos['y'] ?? null)) {
                $actuales[(int) $nodeId] = [
                    'x' => round((float) $pos['x'], 2),
                    'y' => round((float) $pos['y'], 2),
                ];
                $guardados++;
            }
        }

        $plano->update(['posiciones' => $actuales, 'updated_by' => auth()->id()]);
        self::olvidarCacheGrafo();

        return response()->json(['success' => true, 'saved_count' => $guardados]);
    }

    // ==================== ZONAS (recuadros tipo Packet Tracer) ====================

    private function validarZona(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:80',
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Lista separada por comas: 10.4.155.0/24, 10.4.20.*, 10.4.1.10-10.4.1.50, 10.4.9.7
            'rangos_ip' => ['nullable', 'string', 'max:500', 'regex:/^[0-9.\/*\-,\s]*$/'],
            'x' => 'required|numeric|between:-1000000,1000000',
            'y' => 'required|numeric|between:-1000000,1000000',
            'ancho' => 'required|numeric|between:50,1000000',
            'alto' => 'required|numeric|between:50,1000000',
        ]);
    }

    public function guardarZona(Request $request)
    {
        $zona = ZonaTopologia::create($this->validarZona($request) + ['created_by' => auth()->id()]);
        self::olvidarCacheGrafo();

        return response()->json(['success' => true, 'zona' => $zona]);
    }

    public function actualizarZona(Request $request, int $id)
    {
        $data = $this->validarZona($request);
        // Si la zona ya no existe (borrada desde otra pestaña) se vuelve a crear con lo que el mapa muestra
        $zona = ZonaTopologia::find($id) ?? new ZonaTopologia(['created_by' => auth()->id()]);
        $zona->fill($data)->save();
        self::olvidarCacheGrafo();

        return response()->json(['success' => true, 'zona' => $zona]);
    }

    public function eliminarZona(int $id)
    {
        // Idempotente: si ya no existe (otra pestaña la borró), el resultado es el mismo
        ZonaTopologia::whereKey($id)->delete();
        self::olvidarCacheGrafo();

        return response()->json(['success' => true]);
    }

    public function eliminarTodasLasZonas()
    {
        $n = ZonaTopologia::query()->delete();
        self::olvidarCacheGrafo();

        return response()->json(['success' => true, 'eliminadas' => $n]);
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

            // Estado: primero el estado operativo SNMP de los puertos de cada extremo;
            // si no hay dato de puertos, se infiere del estado de los equipos.
            $operO = $enlace->interfazOrigen?->ultimaTelemetria?->oper_status;
            $operD = $enlace->interfazDestino?->ultimaTelemetria?->oper_status;
            $origEstado = $enlace->dispositivoOrigen->estado ?? null;
            $destEstado = $enlace->dispositivoDestino->estado ?? null;
            if ($operO === 'down' || $operD === 'down' || $origEstado === 'offline' || $destEstado === 'offline') {
                $enlace->estado = 'down';
            } elseif ($operO === 'up' || $operD === 'up') {
                $enlace->estado = ($origEstado === 'warning' || $destEstado === 'warning') ? 'degraded' : 'up';
            } else {
                $enlace->estado = 'unknown';
            }

            // Utilización real (%) medida por el poller en los puertos de cada extremo
            $utils = array_filter([
                $enlace->interfazOrigen?->ultimaTelemetria?->bandwidth_util_pct,
                $enlace->interfazDestino?->ultimaTelemetria?->bandwidth_util_pct,
            ], fn ($v) => $v !== null);
            $enlace->utilizacion_pct = $utils ? round(max(array_map('floatval', $utils)), 1) : null;
        });
    }

    /**
     * Transforma modelos Eloquent en la estructura optimizada para Vis.js / Canvas
     */
    private function compilarGrafoData($dispositivos, $enlaces)
    {
        $nodes = [];
        $edges = [];

        // Acomodo del plano compartido del NOC
        $plano = TopologiaPlano::actual();
        $userPosiciones = is_array($plano->posiciones) ? $plano->posiciones : [];

        $notasPorDispositivo = NotaDispositivo::selectRaw('dispositivo_id, COUNT(*) n')
            ->groupBy('dispositivo_id')->pluck('n', 'dispositivo_id');

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
        $enlacesMap = [];

        foreach ($enlaces as $enlace) {
            $enlacesMap[$enlace->origen_dispositivo_id] = $enlace;
            $enlacesMap[$enlace->destino_dispositivo_id] = $enlace;
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
            $cpu = $ultimaMetrica?->cpu_usage;
            $mem = $ultimaMetrica?->memory_usage;
            $ping = $ultimaMetrica?->ping_ms;

            $modeloExacto = $disp->telemetriaChasis->model_name ?? '';
            $osVersion = $disp->telemetriaChasis->os_version ?? '';
            
            // Mapeo dinámico de hardware fotorrealista según el modelo real
            $macVendor = '';
            if (!empty($disp->mac_address)) {
                $macVendor = \App\Services\MacVendorResolver::resolveVendor($disp->mac_address);
            }

            // Tipo por evidencia (SNMP/CDP/LLDP), corrección manual o, en último caso, nombre
            $deviceType = $disp->resolveTypeInfo();
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
                'customData' => [
                    'id' => $disp->id,
                    'nombre' => $disp->nombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion,
                    'estado' => $disp->estado,
                    'statusColor' => $statusColor,
                    'rol' => strtoupper($rol),
                    'tipo' => $deviceType['tipo'],
                    'tipo_confianza' => $deviceType['confianza'],
                    'tipo_fuente' => $deviceType['fuente'],
                    'tipo_evidencia' => $deviceType['evidencia'],
                    'tipo_manual' => $disp->tipo_manual,
                    'tipo_equipo' => $deviceType['label'],
                    'factor_forma' => $deviceType['modelo'],
                    'vendor' => $deviceType['fabricante'] ?? ($macVendor !== '' && $macVendor !== 'Desconocido' ? $macVendor : null),
                    'cpu' => $cpu,
                    'memoria' => $mem,
                    'ping' => $ping,
                    'modelo' => $deviceType['modelo'] ?? ($modeloExacto !== '' ? \Illuminate\Support\Str::limit($modeloExacto, 80) : null),
                    'serial' => $disp->telemetriaChasis->serial_number ?? null,
                    'uptime' => $disp->telemetriaChasis->uptime_str ?? null,
                    'sysDescr' => $osVersion,
                    'url' => route('dispositivos.show', $disp->id),
                    'image' => $imageUrl,
                    'conexiones' => $conexionesPorDispositivo[$disp->id] ?? [],
                    'sensores_temperatura' => $disp->telemetriaChasis->sensores_temperatura ?? [],
                    'notas' => (int) ($notasPorDispositivo[$disp->id] ?? 0),
                    'pc_conectada' => $deviceType['tipo'] === 'telefono' ?\App\Services\PhonePcLinkResolver::resolveAttachedPc($disp, $enlacesMap[$disp->id] ?? null) : null
                ]
            ];
        }

        foreach ($enlaces as $enlace) {
            $origenNombre = $enlace->interfazOrigen ? $enlace->interfazOrigen->nombre : 'P1';
            $destinoNombre = $enlace->interfazDestino ? $enlace->interfazDestino->nombre : 'P2';
            $origenAbrev = $abrev($origenNombre);
            $destinoAbrev = $abrev($destinoNombre);

            [$color, $width, $dashes, $opacity] = $this->estiloEnlace($enlace->estado, $enlace->velocidad_mbps, $enlace->utilizacion_pct);

            $etiquetaVelocidad = ($enlace->velocidad_mbps >= 1000)
                ? ($enlace->velocidad_mbps / 1000) . 'G'
                : $enlace->velocidad_mbps . 'M';

            $utilStr = $enlace->utilizacion_pct !== null ? $enlace->utilizacion_pct . ' % (medida en los puertos)' : 'Sin dato';
            
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
                     "Utilización: " . $utilStr . "\n" .
                     "Medio: " . strtoupper(str_replace('_', ' ', $enlace->tipo_medio)) . "\n" .
                     "VLANs: " . ($enlace->vlans_permitidas ?? 'Sin dato') . "\n" .
                     "Estado: " . strtoupper($enlace->estado);

            // Cable recto; solo se curvan los enlaces paralelos adicionales entre el mismo par
            $parKey = min($enlace->origen_dispositivo_id, $enlace->destino_dispositivo_id) . '_' .
                      max($enlace->origen_dispositivo_id, $enlace->destino_dispositivo_id);
            $conteoEnlacesPar[$parKey] = ($conteoEnlacesPar[$parKey] ?? 0) + 1;
            $n = $conteoEnlacesPar[$parKey];
            $smooth = $n === 1
                ? ['enabled' => false]
                : ['enabled' => true, 'type' => ($n % 2 === 0) ? 'curvedCCW' : 'curvedCW', 'roundness' => 0.12 * intdiv($n, 2) + 0.06];

            $edges[] = [
                'id' => $enlace->id,
                'from' => $enlace->origen_dispositivo_id,
                'to' => $enlace->destino_dispositivo_id,
                'label' => $label,
                'title' => $title,
                'font' => [
                    'align' => 'horizontal',
                    'size' => 9,
                    'color' => '#475569',
                    'background' => 'rgba(255, 255, 255, 0.9)',
                    'strokeWidth' => 0
                ],
                'color' => [
                    'color' => $color,
                    'highlight' => '#2563eb',
                    'hover' => '#3b5998',
                    'opacity' => $opacity
                ],
                'width' => $width,
                'dashes' => $dashes,
                'smooth' => $smooth,
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
                    'ancho_px' => $width,
                    'dashes_px' => $dashes,
                    'trafico_in' => null,
                    'trafico_out' => null,
                    'saturacion_pct' => $enlace->utilizacion_pct,
                    'vlans' => $enlace->vlans_permitidas
                ]
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'hasSavedPositions' => !empty($userPosiciones),
            'userPosiciones' => $userPosiciones,
            'zonas' => ZonaTopologia::orderBy('id')->get(['id', 'nombre', 'color', 'rangos_ip', 'x', 'y', 'ancho', 'alto']),
            'resumen' => $this->resumenEstado($dispositivos, $enlaces),
        ];
    }

    /**
     * Estilo del cable: el color solo depende del estado real y el grosor de la velocidad.
     * Ancho y guiones están en píxeles de PANTALLA: la vista los divide entre el zoom actual para que
     * el cable se vea igual de grueso en la vista general que de cerca (los equipos miden ~1450 unidades).
     * Colores sólidos con contraste suficiente sobre el fondo claro y el oscuro del lienzo.
     * Devuelve [color, ancho_px, dashes_px, opacidad].
     */
    private function estiloEnlace(?string $estado, $velocidadMbps, $utilPct): array
    {
        $vel = (int) ($velocidadMbps ?? 0);
        $width = match (true) {
            $vel >= 40000 => 2.2,
            $vel >= 10000 => 1.8,
            $vel >= 1000 => 1.4,
            default => 1,
        };

        return match (true) {
            $estado === 'down' => ['#ef4444', $width, [6, 4], 1],
            $estado === 'unknown' => ['#64748b', 1, [4, 4], 0.9],
            $estado === 'degraded' || ($utilPct !== null && $utilPct >= 80) => ['#f59e0b', $width, false, 1],
            default => ['#16a34a', $width, false, 0.95],
        };
    }

    /**
     * Conteos reales para el encabezado (equipos por estado y enlaces por estado).
     */
    private function resumenEstado($dispositivos, $enlaces): array
    {
        return [
            'disp_total' => $dispositivos->count(),
            'disp_online' => $dispositivos->where('estado', 'online')->count(),
            'disp_warning' => $dispositivos->where('estado', 'warning')->count(),
            'disp_offline' => $dispositivos->where('estado', 'offline')->count(),
            'enl_total' => $enlaces->count(),
            'enl_up' => $enlaces->where('estado', 'up')->count(),
            'enl_degraded' => $enlaces->where('estado', 'degraded')->count(),
            'enl_down' => $enlaces->where('estado', 'down')->count(),
            'enl_unknown' => $enlaces->where('estado', 'unknown')->count(),
            'ultimo_sondeo' => ($u = $dispositivos->max('ultima_vez_visto')) ? \Carbon\Carbon::parse($u)->toIso8601String() : null,
            'generado_en' => now()->toIso8601String(),
        ];
    }
}
