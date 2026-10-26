<?php

namespace App\Services;

use App\Models\Dispositivo;
use App\Models\InterfazRed;
use App\Models\EnlaceRed;
use App\Models\TelemetriaInterfaz;
use App\Models\ConfiguracionGeneral;
use App\Models\ConfiguracionDispositivo;

class AlarmDiagnosisService
{
    /** Memoria de FortiGate: umbrales por defecto de FortiOS (sale de "verde" al 82 %, modo conserva al 88 %). */
    public const FORTI_MEM_AVISO_PCT = 82;
    public const FORTI_MEM_CONSERVA_PCT = 88;

    /** Umbrales del NOC para el SLA de los enlaces SD-WAN (el FortiGate no expone sus propios umbrales por SNMP). */
    public const SDWAN_PERDIDA_PCT = 5;
    public const SDWAN_LATENCIA_MS = 150;
    public const SDWAN_JITTER_MS = 30;

    /** Segundos que se comparte el diagnóstico entre usuarios y pestañas (calcularlo tarda ~0.3 s). */
    public const CACHE_DIAGNOSTICO_S = 5;

    /**
     * Diagnóstico de toda la red sin estado de usuario, compartido unos segundos para que varias pantallas
     * refrescando a la vez no lo recalculen cada una. Una falla nueva aparece como mucho 5 s después de medirse.
     */
    public static function diagnosticoCompartido(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('diagnostico_alarmas_red_fast', self::CACHE_DIAGNOSTICO_S,
            fn () => (new self())->diagnosticarAlarmasRed(false));
    }

    /**
     * Genera el diagnóstico inteligente de causa raíz para todas las fallas activas en la red.
     */
    public function diagnosticarAlarmasRed(bool $aplicarEstadoUsuario = true): array
    {
        $alarmas = $this->ordenarRecientesPrimero($this->compilarAlarmas());
        return $aplicarEstadoUsuario ? $this->aplicarEstadoUsuario($alarmas) : $alarmas;
    }

    /**
     * La falla más reciente primero; las que no tienen hora, al final.
     * Para las que ya están en el historial (noc:sync) se usa su hora real de inicio: varias calculan la hora con la
     * última lectura del dato (temperatura, err-disabled, hardware) y una falla de días aparecería como "hace 5 s".
     */
    private function ordenarRecientesPrimero(array $alarmas): array
    {
        $inicios = \App\Models\AlarmaRed::whereIn('clave', array_column($alarmas, 'id'))
            ->where('estado', '!=', 'resuelta')
            ->pluck('fecha_inicio', 'clave');
        foreach ($alarmas as &$a) {
            if ($inicio = $inicios[$a['id']] ?? null) {
                $dt = \Carbon\Carbon::parse($inicio);
                $a['fecha_falla'] = $dt->format('d/m/Y H:i:s');
                $a['hora_falla'] = $dt->format('h:i:s A');
                $a['hace_cuanto'] = $dt->diffForHumans();
                $a['tiempo'] = $a['hora_falla'] . ' (' . $a['hace_cuanto'] . ')';
            }
        }
        unset($a);

        $momento = function (array $a): int {
            try {
                return \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', (string) ($a['fecha_falla'] ?? ''))->timestamp;
            } catch (\Throwable) {
                return 0;
            }
        };
        usort($alarmas, fn ($a, $b) => $momento($b) <=> $momento($a));
        return $alarmas;
    }

    /** Horas que dura un descarte si la falla sigue activa: después vuelve a mostrarse para no olvidarla. */
    public const HORAS_DESCARTE = 24;

    /** Minutos que vale una lectura SNMP para sostener una alarma (3 ciclos del colector de detalle). */
    public const MINUTOS_DATO_VALIDO = 15;

    private function datoReciente($fecha): bool
    {
        return $fecha && \Carbon\Carbon::parse($fecha)->gt(now()->subMinutes(self::MINUTOS_DATO_VALIDO));
    }

    /**
     * Marca como leídas u oculta las descartadas según la cuenta autenticada.
     * La marca vale solo para la ocurrencia actual de la falla: si el equipo se recuperó y volvió a fallar
     * (alarmas_red registra una ocurrencia posterior a la marca), la alarma reaparece. Un descarte además vence a
     * las HORAS_DESCARTE horas.
     */
    public function aplicarEstadoUsuario(array $alarmas): array
    {
        if (!auth()->check()) {
            return $alarmas;
        }
        $userReadMap = \App\Models\NotificacionLeida::where('user_id', auth()->id())
            ->whereIn('notificacion_id', array_column($alarmas, 'id'))
            ->get()
            ->keyBy('notificacion_id');
        // Inicio de la ocurrencia abierta de cada alarma (historial que registra noc:sync)
        $inicios = \App\Models\AlarmaRed::whereIn('clave', $userReadMap->keys())
            ->where('estado', '!=', 'resuelta')
            ->pluck('fecha_inicio', 'clave');
        $limiteDescarte = now()->subHours(self::HORAS_DESCARTE);

        $resultado = [];
        foreach ($alarmas as $alarma) {
            $record = $userReadMap[$alarma['id']] ?? null;
            $marcadoEn = $record ? \Carbon\Carbon::parse($record->fecha_evento ?? $record->updated_at) : null;
            $inicio = isset($inicios[$alarma['id']]) ? \Carbon\Carbon::parse($inicios[$alarma['id']]) : null;
            // La marca es de una ocurrencia anterior: no aplica a la falla actual
            $vigente = $marcadoEn && !($inicio && $marcadoEn->lt($inicio));

            if ($vigente && $record->estado === 'descartada' && $marcadoEn->gt($limiteDescarte)) {
                continue;
            }
            if ($vigente && $record->estado === 'leida') {
                $alarma['leida'] = true;
            }
            $resultado[] = $alarma;
        }
        return $resultado;
    }

    private function compilarAlarmas(): array
    {
        $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])->get();
        $config = ConfiguracionGeneral::first();
        $umbralCpu = $config->umbral_cpu_warning ?? 80;
        $umbralLoss = $config->umbral_loss_warning ?? 5;

        // Cargar enlaces para análisis de topología y dependencias ascendentes
        $enlaces = EnlaceRed::with([
            'dispositivoOrigen',
            'dispositivoDestino',
            'interfazOrigen.ultimaTelemetria',
            'interfazDestino.ultimaTelemetria'
        ])->get();

        $alarmas = [];

        // -------------------------------------------------------------
        // 1. ANÁLISIS DE DISPOSITIVOS OFFLINE / DESCONECTADOS
        // -------------------------------------------------------------
        $dispositivosOffline = $dispositivos->where('estado', 'offline');

        foreach ($dispositivosOffline as $disp) {
            $diagnostico = $this->analizarCausaDesconexion($disp, $enlaces);
            // ultima_vez_visto = última vez que respondió ICMP/SNMP (el poller no la toca si está caído)
            $dt = $disp->ultima_vez_visto ? \Carbon\Carbon::parse($disp->ultima_vez_visto) : ($disp->updated_at ? \Carbon\Carbon::parse($disp->updated_at) : now());
            $fechaFalla = $dt->format('d/m/Y H:i:s');
            $horaFalla = $dt->format('h:i:s A');

            $alarmas[] = [
                'id' => 'off-' . $disp->id,
                'dispositivo_id' => $disp->id,
                'tipo' => 'critica',
                'severidad' => 'critica',
                'categoria' => 'dispositivo',
                'codigo_evento' => $diagnostico['codigo'],
                'titulo' => $diagnostico['titulo'],
                'dispositivo' => $disp->nombre,
                'ip' => $disp->ip,
                'ubicacion' => $disp->ubicacion ?? 'No especificada',
                'mensaje' => $diagnostico['resumen'],
                'causa_raiz' => $diagnostico['causa_raiz'],
                'impacto' => $diagnostico['impacto'],
                'accion_sugerida' => $diagnostico['accion_sugerida'],
                'fecha_falla' => $fechaFalla,
                'hora_falla' => $horaFalla,
                'hace_cuanto' => $dt->diffForHumans(),
                'tiempo' => $horaFalla . ' (' . $dt->diffForHumans() . ')',
                'leida' => false,
                'link' => route('dispositivos.show', $disp->id),
                'accion' => 'Diagnosticar Causa'
            ];
        }

        // -------------------------------------------------------------
        // 2. ANÁLISIS DE PUERTOS EN ERR-DISABLED (SEGURIDAD / BUCLES)
        // -------------------------------------------------------------
        $errDisabledList = TelemetriaInterfaz::where('is_errdisabled', true)
            ->with(['interfaz.dispositivo.telemetriaChasis'])
            ->get();

        foreach ($errDisabledList as $telemetria) {
            $interfaz = $telemetria->interfaz;
            $disp = $interfaz ? $interfaz->dispositivo : null;
            // Solo con lectura reciente del colector: si el switch dejó de responder, el último valor ya no es real
            if (!$disp || $disp->estado === 'offline' || !$this->datoReciente($disp->telemetriaChasis?->detalle_medido_en)) {
                continue;
            }
            $dispNombre = $disp ? $disp->nombre : 'Switch';
            $intfNombre = $interfaz ? $interfaz->nombre : 'Puerto';
            $motivo = strtoupper($telemetria->errdisabled_reason ?: 'BPDU-Guard / Loop');

            $causa = "El switch detectó una condición anómala en el puerto {$intfNombre}. Motivo: {$motivo}. ";
            $accion = "1. Inspeccionar el extremo del cable para remover bucles o dispositivos no autorizados. 2. En la consola ejecutar 'shutdown' y luego 'no shutdown' en {$intfNombre}.";

            if (str_contains($motivo, 'BPDU')) {
                $causa .= "Se recibió un paquete BPDU de Spanning Tree en un puerto configurado como PortFast (posible conexión de switch secundario o bucle de cable).";
                $accion = "Retirar switch o router conectado no autorizado que está inyectando tramas Spanning Tree.";
            } elseif (str_contains($motivo, 'SECURITY') || str_contains($motivo, 'MAC')) {
                $causa .= "Violación de Port-Security: Se conectó un dispositivo con una MAC diferente a la autorizada.";
                $accion = "Verificar la dirección MAC conectada en la roseta o ajustar la política de Port-Security.";
            } elseif (str_contains($motivo, 'STORM') || str_contains($motivo, 'BROADCAST')) {
                $causa .= "Tormenta de broadcast detectada en la VLAN. Se superó el umbral de paquetes por segundo.";
                $accion = "Aislar el segmento de red para localizar la tarjeta de red defectuosa o el bucle.";
            }

            $dt = $telemetria->updated_at ? \Carbon\Carbon::parse($telemetria->updated_at) : ($telemetria->created_at ? \Carbon\Carbon::parse($telemetria->created_at) : now());
            $fechaFalla = $dt->format('d/m/Y H:i:s');
            $horaFalla = $dt->format('h:i:s A');

            $alarmas[] = [
                'id' => 'errdis-' . $telemetria->id,
                'dispositivo_id' => $disp ? $disp->id : null,
                'interfaz_id' => $interfaz ? $interfaz->id : null,
                'tipo' => 'critica',
                'severidad' => 'critica',
                'categoria' => 'seguridad',
                'codigo_evento' => 'ERR_DISABLED',
                'titulo' => "Puerto {$intfNombre} Suspendido (Err-Disabled)",
                'dispositivo' => $dispNombre,
                'ip' => $disp ? $disp->ip : '',
                'ubicacion' => $disp ? $disp->ubicacion : '',
                'mensaje' => "Puerto suspendido por protección {$motivo} en {$dispNombre}.",
                'causa_raiz' => $causa,
                'impacto' => "Interrupción total de tráfico en el puerto {$intfNombre}.",
                'accion_sugerida' => $accion,
                'fecha_falla' => $fechaFalla,
                'hora_falla' => $horaFalla,
                'hace_cuanto' => $dt->diffForHumans(),
                'tiempo' => $horaFalla . ' (' . $dt->diffForHumans() . ')',
                'leida' => false,
                'link' => $disp ? route('dispositivos.show', $disp->id) : route('topologia.index'),
                'accion' => 'Inspeccionar Puerto'
            ];
        }

        // -------------------------------------------------------------
        // 3. ANÁLISIS DE ENLACES TRONCALES CAÍDOS (TOPOLOGÍA DOWN)
        // -------------------------------------------------------------
        $enlacesCaidos = $enlaces->where('estado', 'down');

        foreach ($enlacesCaidos as $enlace) {
            $orig = $enlace->dispositivoOrigen;
            $dest = $enlace->dispositivoDestino;
            $origName = $orig ? $orig->nombre : 'Origen';
            $destName = $dest ? $dest->nombre : 'Destino';
            $origPort = $enlace->interfazOrigen ? $enlace->interfazOrigen->nombre : 'P1';
            $destPort = $enlace->interfazDestino ? $enlace->interfazDestino->nombre : 'P2';
            $medio = strtoupper(str_replace('_', ' ', $enlace->tipo_medio ?? 'Fibra Óptica'));

            $dt = $enlace->updated_at ? \Carbon\Carbon::parse($enlace->updated_at) : now();
            $fechaFalla = $dt->format('d/m/Y H:i:s');
            $horaFalla = $dt->format('h:i:s A');

            $alarmas[] = [
                'id' => 'link-down-' . $enlace->id,
                'enlace_id' => $enlace->id,
                'tipo' => 'critica',
                'severidad' => 'critica',
                'categoria' => 'enlace',
                'codigo_evento' => 'LINK_DOWN',
                'titulo' => "Enlace Troncal Caído ({$medio})",
                'dispositivo' => "{$origName} ↔ {$destName}",
                'ip' => ($orig ? $orig->ip : '') . ' / ' . ($dest ? $dest->ip : ''),
                'ubicacion' => "Interconexión Troncal",
                'mensaje' => "Pérdida de enlace entre {$origName} ({$origPort}) y {$destName} ({$destPort}).",
                'causa_raiz' => "Corte físico de medio ({$medio}), transceptor SFP/GBIC desconectado o puerto apagado administrativamente en uno de los extremos.",
                'impacto' => "Aislamiento de tráfico entre los racks o pisos conectados por este enlace.",
                'accion_sugerida' => "1. Verificar luces LED de enlace (LINK/ACT) en {$origPort} y {$destPort}. 2. Limpiar o reemplazar conector de fibra/UTP. 3. Comprobar niveles de potencia óptica DDM en el SFP.",
                'fecha_falla' => $fechaFalla,
                'hora_falla' => $horaFalla,
                'hace_cuanto' => $dt->diffForHumans(),
                'tiempo' => $horaFalla . ' (' . $dt->diffForHumans() . ')',
                'leida' => false,
                'link' => route('topologia.index'),
                'accion' => 'Ver en Topología'
            ];
        }

        // -------------------------------------------------------------
        // 3.B. ANÁLISIS DE INTERFACES WAN Y TÚNELES VPN FORTIGATE (DOWN)
        // -------------------------------------------------------------
        $fortiInterfacesDown = TelemetriaInterfaz::where('oper_status', 'down')
            ->whereHas('interfaz', function($q) {
                $q->where(function($sub) {
                    $sub->where('nombre', \App\Support\Sql::like(), '%wan%')
                        ->orWhere('nombre', \App\Support\Sql::like(), '%port1%')
                        ->orWhere('nombre', \App\Support\Sql::like(), '%port2%')
                        ->orWhere('nombre', \App\Support\Sql::like(), '%ipsec%')
                        ->orWhere('nombre', \App\Support\Sql::like(), '%vpn%')
                        ->orWhere('nombre', \App\Support\Sql::like(), '%ssl%');
                });
            })
            ->with(['interfaz.dispositivo'])
            ->get();

        foreach ($fortiInterfacesDown as $tIf) {
            $intf = $tIf->interfaz;
            $disp = $intf ? $intf->dispositivo : null;
            $isForti = $disp && (str_contains(strtolower($disp->nombre . ' ' . $disp->comunidad_snmp), 'forti') || str_starts_with(strtolower($disp->nombre), 'fg-') || str_starts_with(strtolower($disp->nombre), 'fw-'));

            if ($isForti) {
                $intfNombre = $intf->nombre;
                $dispNombre = $disp->nombre;
                $dt = $tIf->updated_at ? \Carbon\Carbon::parse($tIf->updated_at) : now();
                $fechaFalla = $dt->format('d/m/Y H:i:s');
                $horaFalla = $dt->format('h:i:s A');

                $isVpn = str_contains(strtolower($intfNombre), 'vpn') || str_contains(strtolower($intfNombre), 'ipsec') || str_contains(strtolower($intfNombre), 'ssl');

                $alarmas[] = [
                    'id' => 'forti-if-down-' . $tIf->id,
                    'dispositivo_id' => $disp->id,
                    'interfaz_id' => $intf->id,
                    'tipo' => 'critica',
                    'severidad' => 'critica',
                    'categoria' => $isVpn ? 'vpn' : 'wan',
                    'codigo_evento' => $isVpn ? 'VPN_TUNNEL_DOWN' : 'FORTI_WAN_DOWN',
                    'titulo' => $isVpn ? "Túnel VPN FortiGate Caído ({$intfNombre})" : "Interfaz WAN FortiGate Inaccesible ({$intfNombre})",
                    'dispositivo' => $dispNombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion ?? 'Seguridad Perimetral',
                    'mensaje' => $isVpn ? "El túnel VPN IPsec/SSL '{$intfNombre}' en {$dispNombre} perdió conectividad." : "La interfaz WAN '{$intfNombre}' de {$dispNombre} cambió a estado DOWN.",
                    'causa_raiz' => $isVpn ? "Falló la fase 1/2 IKE del túnel IPsec o se perdió la IP pública del extremo remoto." : "Corte de enlace con el Proveedor de Internet (ISP) o caída de port port1/port2.",
                    'impacto' => $isVpn ? "Pérdida de comunicación segura entre sedes/sitios remotos." : "Conmutación por failover a enlace secundario o pérdida de conectividad a Internet.",
                    'accion_sugerida' => $isVpn ? "Ejecutar 'diagnose vpn tunnel list' en la consola CLI e inspeccionar las llaves IKE." : "Comprobar enlace físico del módem/ONT ISP y revisar tabla SD-WAN SLA.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . ' (' . $dt->diffForHumans() . ')',
                    'leida' => false,
                    'link' => route('dispositivos.show', $disp->id),
                    'accion' => 'Inspeccionar WAN/VPN'
                ];
            }
        }

        // -------------------------------------------------------------
        // 4. ANÁLISIS DE ALERTAS TÉRMICAS DE HARDWARE
        // -------------------------------------------------------------
        foreach ($dispositivos as $disp) {
            // La temperatura la lee el sondeo en cada ciclo: un valor viejo (equipo caído o sin SNMP) no es real
            if ($disp->estado === 'offline' || !$this->datoReciente($disp->telemetriaChasis?->updated_at)) {
                continue;
            }
            $temp = (float)($disp->telemetriaChasis->temperatura_c ?? 0);
            if ($temp >= 55.0) {
                $esCritica = ($temp >= 65.0);
                $dt = ($disp->telemetriaChasis && $disp->telemetriaChasis->updated_at) ? \Carbon\Carbon::parse($disp->telemetriaChasis->updated_at) : ($disp->updated_at ? \Carbon\Carbon::parse($disp->updated_at) : now());
                $fechaFalla = $dt->format('d/m/Y H:i:s');
                $horaFalla = $dt->format('h:i:s A');

                $alarmas[] = [
                    'id' => 'temp-' . $disp->id,
                    'dispositivo_id' => $disp->id,
                    'tipo' => $esCritica ? 'critica' : 'advertencia',
                    'severidad' => $esCritica ? 'critica' : 'advertencia',
                    'categoria' => 'hardware',
                    'codigo_evento' => 'TEMP_CRITICAL',
                    'titulo' => "Alerta Térmica: {$temp}°C en Chasis",
                    'dispositivo' => $disp->nombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion ?? 'Rack',
                    'mensaje' => "Chasis o módulos ASIC operando a {$temp}°C (Umbral térmico seguro: < 50°C).",
                    'causa_raiz' => "Temperatura interna anormalmente alta. Posible obstrucción de flujo de aire en el rack, falla en abanicos (FAN tray) o falla del aire acondicionado en el IDF/MDF.",
                    'impacto' => "Riesgo de reinicio térmico de protección o degradación permanente de silicio en los circuitos ASIC.",
                    'accion_sugerida' => "1. Inspeccionar ventiladores con comando 'show env all'. 2. Verificar que las rejillas frontales/traseras no estén obstruidas. 3. Comprobar temperatura ambiente en el IDF.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " ({$temp}°C)",
                    'leida' => false,
                    'link' => route('dispositivos.show', $disp->id),
                    'accion' => 'Ver Sensores'
                ];
            }
        }

        // -------------------------------------------------------------
        // 5. ANÁLISIS DE SOBRECARGA DE CPU Y MEMORIA RAM
        // -------------------------------------------------------------
        foreach ($dispositivos as $disp) {
            $ultimaMetrica = $disp->ultimaMetrica;
            $cpu = $ultimaMetrica->cpu_usage ?? 0;
            $mem = $ultimaMetrica->memory_usage ?? 0;
            $dt = ($ultimaMetrica && $ultimaMetrica->fecha_registro) ? \Carbon\Carbon::parse($ultimaMetrica->fecha_registro) : ($disp->updated_at ? \Carbon\Carbon::parse($disp->updated_at) : now());
            $fechaFalla = $dt->format('d/m/Y H:i:s');
            $horaFalla = $dt->format('h:i:s A');
            $isForti = $disp->esFortigate();

            // FortiGate: memoria con los umbrales de FortiOS (sale de "verde" a 82 % y entra en modo conserva a 88 %)
            if ($isForti && $mem >= self::FORTI_MEM_AVISO_PCT) {
                $conserva = $mem >= self::FORTI_MEM_CONSERVA_PCT;
                $alarmas[] = [
                    'id' => 'ram-' . $disp->id,
                    'dispositivo_id' => $disp->id,
                    'tipo' => $conserva ? 'critica' : 'advertencia',
                    'severidad' => $conserva ? 'critica' : 'advertencia',
                    'categoria' => 'rendimiento',
                    'codigo_evento' => $conserva ? 'FORTI_CONSERVE_MODE' : 'FORTI_MEMORY_HIGH',
                    'titulo' => $conserva ? "FortiGate en modo conserva por memoria ({$mem}%)" : "Memoria del FortiGate alta ({$mem}%)",
                    'dispositivo' => $disp->nombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion ?? '',
                    'mensaje' => "La memoria del FortiGate está al {$mem}%"
                        . ($conserva ? ': a partir de ' . self::FORTI_MEM_CONSERVA_PCT . '% FortiOS entra en modo conserva.'
                                     : ' (FortiOS entra en modo conserva al ' . self::FORTI_MEM_CONSERVA_PCT . '%).'),
                    'causa_raiz' => 'Muchas sesiones concurrentes, inspección profunda (proxy/AV/IPS/SSL), logs en memoria o una fuga de memoria del firmware.',
                    'impacto' => $conserva
                        ? 'En modo conserva el FortiGate deja de aceptar o de inspeccionar sesiones nuevas que requieren proxy (AV, filtrado web), según su configuración de fail-open.'
                        : 'Si sigue subiendo, el equipo entra en modo conserva y empieza a afectar el tráfico nuevo.',
                    'accion_sugerida' => "En la consola: 'diagnose hardware sysinfo conserve' (umbrales y estado), 'diagnose sys top-mem' (procesos que más usan) y 'get system performance status'.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " (RAM {$mem}%)",
                    'leida' => false,
                    'link' => route('dispositivos.show', $disp->id),
                    'accion' => 'Ver Memoria'
                ];
            }

            if ($cpu >= $umbralCpu) {
                $alarmas[] = [
                    'id' => 'cpu-' . $disp->id,
                    'dispositivo_id' => $disp->id,
                    'tipo' => 'advertencia',
                    'severidad' => 'advertencia',
                    'categoria' => 'rendimiento',
                    'codigo_evento' => 'CPU_OVERLOAD',
                    'titulo' => "Sobrecarga de CPU ({$cpu}%)",
                    'dispositivo' => $disp->nombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion ?? '',
                    'mensaje' => "El procesador alcanzó el {$cpu}% de utilización (Umbral límite: {$umbralCpu}%).",
                    'causa_raiz' => $isForti
                        ? 'Inspección intensiva (IPS, antivirus, inspección SSL), muchas sesiones nuevas por segundo o un proceso del sistema (ipsengine, wad, miglogd) consumiendo de más.'
                        : "Proceso de plano de control demandante. Causas comunes: Tormenta de broadcast/multicast (STP Loop), ráfaga de consultas SNMP, debugs activos en consola o ruteo intensivo.",
                    'impacto' => $isForti
                        ? 'Lentitud en el tráfico que pasa por el firewall, en las VPN y en la administración web/SSH.'
                        : "Retardo en el procesamiento de paquetes de control (CDP, LLDP, OSPF/BGP, STP) y latencia en la administración web/SSH.",
                    'accion_sugerida' => $isForti
                        ? "En la consola del FortiGate: 'diagnose sys top 5 20' (procesos que más CPU usan) y 'get system performance status'."
                        : "Ejecutar comando 'show processes cpu sorted 5sec' en la terminal SSH para identificar el proceso o hilo causante.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " (CPU {$cpu}%)",
                    'leida' => false,
                    'link' => route('dispositivos.show', $disp->id),
                    'accion' => 'Inspeccionar Procesos'
                ];
            }

            if (!$isForti && $mem >= 85) {
                $alarmas[] = [
                    'id' => 'ram-' . $disp->id,
                    'dispositivo_id' => $disp->id,
                    'tipo' => 'advertencia',
                    'severidad' => 'advertencia',
                    'categoria' => 'rendimiento',
                    'codigo_evento' => 'RAM_OVERLOAD',
                    'titulo' => "Saturación de Memoria RAM ({$mem}%)",
                    'dispositivo' => $disp->nombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion ?? '',
                    'mensaje' => "La memoria RAM está ocupada al {$mem}%.",
                    'causa_raiz' => "Poco espacio libre en la memoria del procesador (RAM/Pool I/O). Puede deberse a tablas de rutas/BGP muy grandes o fuga de memoria en versión de firmware.",
                    'impacto' => "Imposibilidad de asignar buffers para nuevos paquetes, pérdida de tráfico en ráfagas.",
                    'accion_sugerida' => "Ejecutar 'show memory statistics' y revisar si es necesario depurar tablas o actualizar IOS/firmware.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " (RAM {$mem}%)",
                    'leida' => false,
                    'link' => route('dispositivos.show', $disp->id),
                    'accion' => 'Ver Memoria'
                ];
            }

            // 6. ANÁLISIS ESPECIAL DE FIREWALL FORTINET / FORTIGATE (SESIONES ACTIVAS Y SEGURIDAD)
            if ($isForti && $ultimaMetrica && ($ultimaMetrica->conexiones_activas > 50000)) {
                $ses = number_format($ultimaMetrica->conexiones_activas);
                $alarmas[] = [
                    'id' => 'forti-ses-' . $disp->id,
                    'dispositivo_id' => $disp->id,
                    'tipo' => 'critica',
                    'severidad' => 'critica',
                    'categoria' => 'seguridad',
                    'codigo_evento' => 'FORTI_SESSION_OVERLOAD',
                    'titulo' => "Saturación de Tabla de Sesiones Firewall ({$ses} sesiones)",
                    'dispositivo' => $disp->nombre,
                    'ip' => $disp->ip,
                    'ubicacion' => $disp->ubicacion ?? '',
                    'mensaje' => "El Firewall FortiGate registra {$ses} sesiones concurrentes en la tabla NAT/Stateful.",
                    'causa_raiz' => "Posible ataque de denegación de servicio (DDoS SYN Flood), botnet o ráfaga inusual de conexiones desde la red interna.",
                    'impacto' => "Agotamiento de la tabla de conexiones de FortiOS, denegación de nuevos accesos web/VPN e incremento en la latencia.",
                    'accion_sugerida' => "Ejecutar 'diagnose sys session stat' en la consola CLI de FortiGate e inspeccionar las políticas de DoS / rate-limit.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " ({$ses} ses.)",
                    'leida' => false,
                    'link' => route('dispositivos.show', $disp->id),
                    'accion' => 'Inspeccionar Sesiones CLI'
                ];
            }
        }

        // -------------------------------------------------------------
        // 6. ANÁLISIS DE DEGRADACIÓN DE ENLACE Y PÉRDIDA DE PAQUETES
        // -------------------------------------------------------------
        foreach ($dispositivos->where('estado', 'warning') as $devWarn) {
            $loss = $devWarn->ultimaMetrica?->packet_loss;
            $ping = $devWarn->ultimaMetrica?->ping_ms;
            // El estado warning también lo produce la CPU; esa alarma ya se generó en la sección 5.
            if ($loss === null || (float) $loss < $umbralLoss) {
                continue;
            }
            $loss = round((float) $loss, 1);
            $ping = $ping !== null ? round((float) $ping, 1) : 'N/D';
            $dt = ($devWarn->ultimaMetrica && $devWarn->ultimaMetrica->fecha_registro) ? \Carbon\Carbon::parse($devWarn->ultimaMetrica->fecha_registro) : now();
            $fechaFalla = $dt->format('d/m/Y H:i:s');
            $horaFalla = $dt->format('h:i:s A');

            $alarmas[] = [
                'id' => 'warn-' . $devWarn->id,
                'dispositivo_id' => $devWarn->id,
                'tipo' => 'advertencia',
                'severidad' => 'advertencia',
                'categoria' => 'rendimiento',
                'codigo_evento' => 'PACKET_LOSS_HIGH',
                'titulo' => "Degradación de Enlace ({$loss}% Pérdida)",
                'dispositivo' => $devWarn->nombre,
                'ip' => $devWarn->ip,
                'ubicacion' => $devWarn->ubicacion ?? '',
                'mensaje' => "Pérdida de paquetes detectada ({$loss}%) con latencia de {$ping} ms en {$devWarn->ip}.",
                'causa_raiz' => "Pérdida ICMP medida por el poller ({$loss}% de los pings sin respuesta). Causas probables a verificar: errores CRC por cableado, duplex mismatch o saturación del enlace.",
                'impacto' => "Lentitud para los usuarios conectados, cortes en llamadas de voz IP (jitter) y retransmisiones TCP.",
                'accion_sugerida' => "1. Verificar si hay errores CRC con 'show interfaces counters errors'. 2. Comprobar que la velocidad y dúplex coincidan en ambos extremos (ej. Auto/1000Mbps Full).",
                'fecha_falla' => $fechaFalla,
                'hora_falla' => $horaFalla,
                'hace_cuanto' => $dt->diffForHumans(),
                'tiempo' => $horaFalla . " (Pérdida {$loss}%)",
                'leida' => false,
                'link' => route('dispositivos.show', $devWarn->id),
                'accion' => 'Ver Métricas'
            ];
        }

        // -------------------------------------------------------------
        // 7. RESPALDO NCM & ESTADO DEL MOTOR SNMP (INFORMATIVAS)
        // -------------------------------------------------------------
        $ultimoBackup = ConfiguracionDispositivo::with('dispositivo')->latest()->first();
        if ($ultimoBackup && $ultimoBackup->dispositivo) {
            $dt = $ultimoBackup->created_at ? \Carbon\Carbon::parse($ultimoBackup->created_at) : now();
            $fechaFalla = $dt->format('d/m/Y H:i:s');
            $horaFalla = $dt->format('h:i:s A');

            $alarmas[] = [
                'id' => 'ncm-' . $ultimoBackup->id,
                'dispositivo_id' => $ultimoBackup->dispositivo_id,
                'tipo' => 'sistema',
                'severidad' => 'informativa',
                'categoria' => 'ncm',
                'codigo_evento' => 'NCM_SNAPSHOT',
                'titulo' => "Instantánea NCM Generada",
                'dispositivo' => $ultimoBackup->dispositivo->nombre,
                'ip' => $ultimoBackup->dispositivo->ip,
                'ubicacion' => $ultimoBackup->dispositivo->ubicacion ?? '',
                'mensaje' => "Respaldo {$ultimoBackup->tipo}-config verificado para {$ultimoBackup->dispositivo->nombre}.",
                'causa_raiz' => "Respaldo programado de configuración ejecutado con éxito.",
                'impacto' => "Copia de seguridad disponible para recuperación ante desastres.",
                'accion_sugerida' => "No se requiere acción. Archivo versionado en base de datos.",
                'fecha_falla' => $fechaFalla,
                'hora_falla' => $horaFalla,
                'hace_cuanto' => $dt->diffForHumans(),
                'tiempo' => $horaFalla . ' (' . $dt->diffForHumans() . ')',
                'leida' => true,
                'link' => route('dispositivos.show', $ultimoBackup->dispositivo_id),
                'accion' => 'Ver Snapshot'
            ];
        }

        // -------------------------------------------------------------
        // 8. ANÁLISIS DE DISPONIBILIDAD DE SERVICIOS Y PÁGINAS WEB
        // -------------------------------------------------------------
        try {
            // Casi todos los sitios fallaron a la vez: una sola alarma (el problema es del monitor o de su red)
            if ($sinRed = \App\Services\WebServicePoller::sinConectividad()) {
                $desde = \Carbon\Carbon::parse($sinRed['desde']);
                $alarmas[] = [
                    'id' => 'web-monitor-sin-red',
                    'tipo' => 'critica',
                    'severidad' => 'critica',
                    'categoria' => 'servicio_web',
                    'codigo_evento' => 'WEB_MONITOR_NO_CONNECTIVITY',
                    'titulo' => 'El monitor no alcanza los sitios web',
                    'dispositivo' => 'Monitor de servicios web',
                    'ip' => gethostname() ?: 'servidor NOC',
                    'ubicacion' => 'Módulo Servicios Web',
                    'mensaje' => "Fallaron {$sinRed['fallidos']} de {$sinRed['total']} sitios a la vez"
                        . ($sinRed['error'] ? " ({$sinRed['error']})" : '') . '. Mientras dure, el estado de cada sitio no se modifica.',
                    'causa_raiz' => 'Cuando casi todos los sitios fallan al mismo tiempo, lo más probable es que el servidor del NOC haya perdido su salida de red o su DNS, no que todos los sitios estén caídos.',
                    'impacto' => 'No se puede verificar la disponibilidad de los sitios web mientras dure.',
                    'accion_sugerida' => '1. Revisar la conexión de red y el DNS del servidor del NOC. 2. Probar desde otro equipo si los sitios abren.',
                    'fecha_falla' => $desde->format('d/m/Y H:i:s'),
                    'hora_falla' => $desde->format('h:i:s A'),
                    'hace_cuanto' => $desde->diffForHumans(),
                    'tiempo' => $desde->format('h:i:s A'),
                    'leida' => false,
                    'link' => route('servicios_web.index'),
                    'accion' => 'Ver Servicios Web',
                ];
            }

            $serviciosWebCaidos = \App\Models\ServicioWeb::where('es_activo', true)
                ->whereIn('estado', ['offline', 'warning'])
                ->get();

            foreach ($serviciosWebCaidos as $web) {
                $dt = $web->ultimo_chequeo ? \Carbon\Carbon::parse($web->ultimo_chequeo) : now();
                $fechaFalla = $dt->format('d/m/Y H:i:s');
                $horaFalla = $dt->format('h:i:s A');
                $isOffline = ($web->estado === 'offline');
                $codeStr = $web->codigo_http ? "HTTP {$web->codigo_http}" : "SIN RESPUESTA";
                $latenciaStr = $web->tiempo_respuesta_ms ? " ({$web->tiempo_respuesta_ms} ms)" : "";
                // VM(s) que atienden el sitio (leídas de los ESXi): dónde corre y si la VM o su host tienen un problema
                $vmsWeb = \App\Services\VmsServiciosWeb::para($web);
                $servidorStr = $vmsWeb ? ' Servidor: ' . implode(', ', array_map(fn ($vm) => $vm['nombre']
                    . (!empty($vm['host']) ? " (host {$vm['host']})" : '') . ($vm['aviso'] ? " — {$vm['aviso']}" : ''), $vmsWeb)) . '.' : '';
                $avisosVm = array_values(array_filter(array_column($vmsWeb, 'aviso')));

                $alarmas[] = [
                    'id' => ($isOffline ? 'web-off-' : 'web-warn-') . $web->id,
                    'servicio_web_id' => $web->id,
                    'tipo' => $isOffline ? 'critica' : 'advertencia',
                    'severidad' => $isOffline ? 'critica' : 'advertencia',
                    'categoria' => 'servicio_web',
                    'codigo_evento' => $isOffline ? 'WEB_SERVICE_DOWN' : 'WEB_SERVICE_DEGRADED',
                    'titulo' => ($isOffline ? "Servicio Web Caído: " : "Degradación en Web: ") . $web->nombre,
                    'dispositivo' => $web->nombre,
                    'ip' => parse_url($web->url, PHP_URL_HOST) ?? $web->url,
                    'ubicacion' => "Módulo Servicios Web [{$web->categoria}]",
                    'mensaje' => ($isOffline ? 'Página inaccesible o con error' : 'Página degradada (lenta, con error de contenido o 4xx)')
                        . ". Estado: {$codeStr}{$latenciaStr}. URL: {$web->url}.{$servidorStr}",
                    'causa_raiz' => ($web->detalles_error ?: ($isOffline ? 'El servidor web no respondió a la solicitud o se agotó el tiempo de espera.' : 'La página devolvió un error de cliente o latencia elevada.'))
                        . ($avisosVm ? ' En su servidor: ' . implode('; ', $avisosVm) . '.' : ''),
                    'impacto' => "Interrupción en la atención a usuarios / trámites digitales en línea.",
                    'accion_sugerida' => ($avisosVm
                        ? '1. Revisar la VM en Virtualización / vCenter (' . implode('; ', $avisosVm) . '). 2. '
                        : '1. Verificar servicio Apache/IIS/Tomcat o balanceador en el servidor. 2. ')
                        . "Probar conectividad hacia la URL: {$web->url}.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " ({$codeStr})",
                    'leida' => false,
                    'link' => route('servicios_web.index'),
                    'accion' => 'Ver Servicios Web'
                ];
            }

            // El colector de ESXi (worker/esxi_collector.py) dejó de leer: sin él no hay datos de las VMs ni de los hosts
            $esxiConfigurado = (bool) \App\Models\ConfiguracionGeneral::query()->value('esxi_usuario');
            $ultimaEsxi = $esxiConfigurado ? \Illuminate\Support\Facades\DB::table('esxi_estado')->max('leido_en') : null;
            if ($esxiConfigurado && (!$ultimaEsxi || \Carbon\Carbon::parse($ultimaEsxi)->lt(now()->subMinutes(5)))) {
                $desde = $ultimaEsxi ? \Carbon\Carbon::parse($ultimaEsxi) : null;
                $alarmas[] = [
                    'id' => 'esxi-colector-detenido',
                    'tipo' => 'advertencia',
                    'severidad' => 'advertencia',
                    'categoria' => 'sistema',
                    'codigo_evento' => 'ESXI_COLECTOR_DETENIDO',
                    'titulo' => 'El colector de VMware (ESXi) no está leyendo',
                    'dispositivo' => 'Colector ESXi',
                    'ip' => '—',
                    'ubicacion' => 'Servidor del NOC',
                    'mensaje' => 'Sin lecturas de los hosts ESXi ' . ($desde ? 'desde ' . $desde->format('H:i') . " ({$desde->locale('es')->diffForHumans()})" : 'todavía') . '.',
                    'causa_raiz' => 'El proceso worker/esxi_collector.py está detenido o no logra conectarse a los hosts.',
                    'impacto' => 'Virtualización y la relación de los sitios web con sus VMs muestran datos viejos; no se detectan discos llenos ni VMs apagadas.',
                    'accion_sugerida' => 'Revisar la ventana "NOC - ESXi" (o el servicio nm-esxi en Ubuntu) y reiniciarla.',
                    'fecha_falla' => ($desde ?? now())->format('d/m/Y H:i:s'),
                    'hora_falla' => ($desde ?? now())->format('h:i:s A'),
                    'hace_cuanto' => $desde ? $desde->locale('es')->diffForHumans() : 'ahora',
                    'tiempo' => ($desde ?? now())->format('h:i:s A'),
                    'leida' => false,
                    'link' => route('virtualizacion.index'),
                    'accion' => 'Ver Virtualización',
                ];
            }

            // Preventivo: la VM que atiende un sitio tiene un problema (disco, SO sin latido, memoria a disco,
            // reinicio, CPU) aunque el sitio todavía responda. Una alarma por VM con los sitios que dependen de ella.
            // Lo que ya tumba al sitio (VM apagada, host caído) lo informa la alarma del sitio.
            $porVm = [];
            foreach (\App\Models\ServicioWeb::where('es_activo', true)->get() as $web) {
                foreach (\App\Services\VmsServiciosWeb::para($web) as $vm) {
                    $avisos = array_filter($vm['avisos'] ?? [], fn ($a) => !preg_match('/apagada|suspendida|no responde$|desconectado|Sin lectura/', $a['texto']));
                    if (!$avisos) {
                        continue;
                    }
                    $porVm[$vm['nombre']] ??= ['vm' => $vm, 'avisos' => array_values($avisos), 'sitios' => []];
                    $porVm[$vm['nombre']]['sitios'][] = $web->nombre;
                }
            }
            foreach ($porVm as $nombreVm => $x) {
                $critica = collect($x['avisos'])->contains('nivel', 'crit');
                $textos = array_column($x['avisos'], 'texto');
                $alarmas[] = [
                    'id' => 'web-vm-' . substr(md5($nombreVm), 0, 12),
                    'tipo' => $critica ? 'critica' : 'advertencia',
                    'severidad' => $critica ? 'critica' : 'advertencia',
                    'categoria' => 'servicio_web',
                    'codigo_evento' => 'WEB_VM_RIESGO',
                    'titulo' => "Servidor de sitios web en riesgo: {$nombreVm}",
                    'dispositivo' => $nombreVm,
                    'ip' => implode(', ', array_slice($x['vm']['ips'] ?? [], 0, 2)),
                    'ubicacion' => 'VM en host ' . ($x['vm']['host'] ?? '—'),
                    'mensaje' => implode('; ', $textos) . '. Sitios que atiende: ' . implode(', ', array_unique($x['sitios'])) . '.',
                    'causa_raiz' => implode('; ', $textos) . ' (leído del host ESXi).',
                    'impacto' => 'Los sitios siguen respondiendo, pero pueden caer si el problema avanza.',
                    'accion_sugerida' => str_contains(implode(' ', $textos), 'Disco')
                        ? '1. Liberar espacio en la partición indicada (logs, temporales, despliegues viejos). 2. Si crece rápido, ampliar el disco de la VM.'
                        : '1. Revisar la VM en Virtualización / vCenter. 2. Revisar el servicio web dentro de la VM.',
                    'fecha_falla' => now()->format('d/m/Y H:i:s'),
                    'hora_falla' => now()->format('h:i:s A'),
                    'hace_cuanto' => 'ahora',
                    'tiempo' => now()->format('h:i:s A'),
                    'leida' => false,
                    'link' => route('servicios_web.index'),
                    'accion' => 'Ver Servicios Web',
                ];
            }
        } catch (\Throwable $e) {
            // Silencioso si la tabla aún se está migrando
        }

        // -------------------------------------------------------------
        // 9. HARDWARE, PoE, VPN Y HA (estado reportado por SNMP)
        // -------------------------------------------------------------
        foreach ($dispositivos as $disp) {
            $chasis = $disp->telemetriaChasis;
            // Fuentes, ventiladores, PoE, VPN y HA los lee el colector de detalle (cada 5 min): sin lectura reciente
            // se retira la alarma en lugar de seguir mostrando el último estado conocido
            if (!$chasis || $disp->estado === 'offline' || !$this->datoReciente($chasis->detalle_medido_en)) {
                continue;
            }
            $dt = $chasis->detalle_medido_en ?? $chasis->updated_at ?? now();
            $base = fn (string $clave, string $sev, string $cat, string $codigo, string $titulo, string $mensaje, string $causa, string $impacto, string $accion) => [
                'id' => $clave,
                'dispositivo_id' => $disp->id,
                'tipo' => $sev,
                'severidad' => $sev,
                'categoria' => $cat,
                'codigo_evento' => $codigo,
                'titulo' => $titulo,
                'dispositivo' => $disp->nombre,
                'ip' => $disp->ip,
                'ubicacion' => $disp->ubicacion ?? '',
                'mensaje' => $mensaje,
                'causa_raiz' => $causa,
                'impacto' => $impacto,
                'accion_sugerida' => $accion,
                'fecha_falla' => $dt->format('d/m/Y H:i:s'),
                'hora_falla' => $dt->format('h:i:s A'),
                'hace_cuanto' => $dt->diffForHumans(),
                'tiempo' => $dt->format('h:i:s A') . ' (' . $dt->diffForHumans() . ')',
                'leida' => false,
                'link' => route('dispositivos.show', $disp->id),
                'accion' => 'Ver Equipo',
            ];

            foreach (['estado_fuentes' => ['psu', 'Fuente de poder'], 'estado_ventiladores' => ['fan', 'Ventilador']] as $col => [$key, $etiqueta]) {
                foreach ((array) ($chasis->$col ?? []) as $i => $comp) {
                    $status = strtoupper((string) ($comp['status'] ?? ''));
                    if (!self::componenteEnFalla($status)) {
                        continue;
                    }
                    $nombreComp = $comp[$key] ?? "{$etiqueta} {$i}";
                    $alarmas[] = $base("hw-{$key}-{$disp->id}-{$i}", 'critica', 'hardware', $key === 'psu' ? 'PSU_FAILURE' : 'FAN_FAILURE',
                        "{$etiqueta} en falla: {$nombreComp}",
                        "{$disp->nombre} reporta {$nombreComp} en estado {$status}.",
                        "Estado reportado por el equipo vía SNMP (CISCO-ENVMON / ENTITY-FRU-CONTROL): {$status}.",
                        $key === 'psu' ? 'Pérdida de redundancia eléctrica; una segunda falla apaga el equipo.' : 'Riesgo de sobrecalentamiento y apagado térmico.',
                        "Ejecutar 'show environment all' en {$disp->nombre} y reemplazar el componente si la falla persiste.");
                }
            }

            $poeTotal = (float) ($chasis->poe_total_budget_w ?? 0);
            if ($poeTotal > 0 && $chasis->poe_used_w !== null) {
                $pct = round($chasis->poe_used_w / $poeTotal * 100, 1);
                if ($pct >= 90) {
                    $alarmas[] = $base("poe-{$disp->id}", 'advertencia', 'hardware', 'POE_BUDGET_HIGH',
                        "Presupuesto PoE al {$pct}%",
                        "{$disp->nombre} consume {$chasis->poe_used_w} W de {$poeTotal} W disponibles para PoE.",
                        'Consumo PoE medido por POWER-ETHERNET-MIB cercano al presupuesto del chasis.',
                        'Nuevos teléfonos IP o APs conectados podrían quedarse sin energía.',
                        "Revisar 'show power inline' y redistribuir equipos PoE entre switches.");
                }
            }

            foreach ((array) ($chasis->vpn_tuneles ?? []) as $i => $tunel) {
                if (($tunel['estado'] ?? null) !== 'down') {
                    continue;
                }
                $nombreTunel = $tunel['fase1'] ?? "Túnel {$i}";
                $remoto = $tunel['remoto'] ?? 'sin dato';
                $alarmas[] = $base("vpn-{$disp->id}-" . md5($nombreTunel . ($tunel['fase2'] ?? '')), 'critica', 'vpn', 'VPN_TUNNEL_DOWN',
                    "Túnel VPN caído: {$nombreTunel}",
                    "El túnel IPsec {$nombreTunel} hacia {$remoto} está DOWN en {$disp->nombre}.",
                    'Estado reportado por FORTINET-FORTIGATE-MIB (fgVpnTunEntStatus = down).',
                    'Sin comunicación con la sede remota a través de este túnel.',
                    "Ejecutar 'diagnose vpn tunnel list name {$nombreTunel}' y verificar conectividad al gateway {$remoto}.");
            }

            // ---- FortiGate: SD-WAN, sensores de hardware, disco, VPN SSL e IPS ----
            foreach ((array) ($chasis->forti_sdwan ?? []) as $enlace) {
                $nombreSla = $enlace['nombre'] ?? 'SLA';
                $clave = "sdwan-{$disp->id}-" . md5($nombreSla . ($enlace['interfaz'] ?? ''));
                $via = !empty($enlace['interfaz']) ? " ({$enlace['interfaz']})" : '';
                if (($enlace['estado'] ?? null) === 'dead') {
                    $alarmas[] = $base($clave, 'critica', 'wan', 'SDWAN_LINK_DEAD',
                        "Enlace SD-WAN caído: {$nombreSla}{$via}",
                        "El health-check {$nombreSla}{$via} de {$disp->nombre} reporta el enlace sin respuesta.",
                        'Estado reportado por el FortiGate (fgVWLHealthCheckLinkState = dead).',
                        'El tráfico se conmuta a los enlaces restantes; si no hay otro, se pierde la salida.',
                        "Ejecutar 'diagnose sys sdwan health-check' y verificar el enlace con el proveedor.");
                    continue;
                }
                $motivos = array_filter([
                    ($enlace['perdida_pct'] ?? null) !== null && $enlace['perdida_pct'] >= self::SDWAN_PERDIDA_PCT ? "pérdida {$enlace['perdida_pct']}%" : null,
                    ($enlace['latencia_ms'] ?? null) !== null && $enlace['latencia_ms'] >= self::SDWAN_LATENCIA_MS ? "latencia {$enlace['latencia_ms']} ms" : null,
                    ($enlace['jitter_ms'] ?? null) !== null && $enlace['jitter_ms'] >= self::SDWAN_JITTER_MS ? "jitter {$enlace['jitter_ms']} ms" : null,
                ]);
                if ($motivos) {
                    $alarmas[] = $base($clave, 'advertencia', 'wan', 'SDWAN_SLA_DEGRADED',
                        "SLA SD-WAN degradado: {$nombreSla}{$via}",
                        "{$disp->nombre}: " . implode(', ', $motivos) . '.',
                        'Medición del health-check del FortiGate fuera de los umbrales del NOC (pérdida ≥ ' . self::SDWAN_PERDIDA_PCT
                            . '%, latencia ≥ ' . self::SDWAN_LATENCIA_MS . ' ms o jitter ≥ ' . self::SDWAN_JITTER_MS . ' ms).',
                        'Lentitud o cortes en servicios que salen por este proveedor.',
                        'Revisar el enlace con el proveedor y las reglas SD-WAN que lo prefieren.');
                }
            }

            foreach ((array) ($chasis->forti_sensores ?? []) as $sensor) {
                if (empty($sensor['alarma'])) {
                    continue;
                }
                $nombreSensor = $sensor['nombre'] ?? 'Sensor';
                $valor = $sensor['valor'] ?? null;
                $alarmas[] = $base("fgsensor-{$disp->id}-" . md5($nombreSensor), 'critica', 'hardware', 'FORTI_HW_SENSOR_ALARM',
                    "Sensor de hardware en alarma: {$nombreSensor}" . ($valor !== null ? " ({$valor})" : ''),
                    "{$disp->nombre} reporta el sensor {$nombreSensor} fuera de rango.",
                    'Alarma del propio equipo (fgHwSensorEntAlarmStatus = true).',
                    'Riesgo de apagado por temperatura, falla de ventilador o de alimentación.',
                    "Ejecutar 'execute sensor list' y revisar ventilación y fuentes del equipo.");
            }

            $sistema = $chasis->forti_sistema ?? [];
            if (($sistema['disco_pct'] ?? null) !== null && $sistema['disco_pct'] >= 90) {
                $alarmas[] = $base("fgdisco-{$disp->id}", 'advertencia', 'hardware', 'FORTI_DISK_HIGH',
                    "Disco del firewall al {$sistema['disco_pct']}%",
                    "{$disp->nombre} usa {$sistema['disco_usado_mb']} MB de {$sistema['disco_total_mb']} MB.",
                    'Uso de disco reportado por el FortiGate (fgSysDiskUsage / fgSysDiskCapacity).',
                    'Al llenarse, el equipo deja de guardar logs y reportes.',
                    "Revisar 'diagnose sys logdisk usage' y la retención de logs, o enviarlos a FortiAnalyzer.");
            }

            $ssl = $chasis->forti_vpn_ssl ?? [];
            if (!empty($ssl['max_usuarios']) && ($ssl['usuarios'] ?? 0) >= 0.9 * $ssl['max_usuarios']) {
                $alarmas[] = $base("fgssl-{$disp->id}", 'advertencia', 'vpn', 'FORTI_SSLVPN_CAPACITY',
                    "VPN SSL cerca de su capacidad ({$ssl['usuarios']} / {$ssl['max_usuarios']})",
                    "{$disp->nombre} tiene {$ssl['usuarios']} usuarios conectados por VPN SSL.",
                    'Usuarios conectados contra el máximo histórico reportado por el equipo.',
                    'Nuevos usuarios remotos podrían no poder conectarse.',
                    'Revisar licenciamiento/capacidad de VPN SSL y sesiones inactivas.');
            }

            $seg = $chasis->forti_seguridad ?? [];
            if (($seg['ips_criticas_ciclo'] ?? 0) > 0) {
                $ventana = !empty($seg['ventana_seg']) ? round($seg['ventana_seg'] / 60) . ' min' : 'el último ciclo';
                $alarmas[] = $base("fgips-{$disp->id}", 'advertencia', 'seguridad', 'FORTI_IPS_CRITICAL',
                    "Detecciones IPS críticas: {$seg['ips_criticas_ciclo']} en {$ventana}",
                    "{$disp->nombre} detectó {$seg['ips_criticas_ciclo']} intrusiones de severidad crítica en {$ventana}.",
                    'Contadores del motor IPS del FortiGate (fgIpsCritSevDetections).',
                    'Posible ataque o equipo comprometido en la red protegida.',
                    "Revisar los logs de IPS en el FortiGate (Log & Report → Intrusion Prevention).");
            }

            $ha = $chasis->ha_estado;
            if (is_array($ha) && !empty($ha['miembros'])) {
                $desincronizados = collect($ha['miembros'])->where('sincronizado', false)->pluck('nombre');
                if ($desincronizados->isNotEmpty()) {
                    $alarmas[] = $base("ha-{$disp->id}", 'advertencia', 'seguridad', 'HA_OUT_OF_SYNC',
                        'Clúster HA sin sincronizar',
                        "Miembros sin sincronizar: {$desincronizados->implode(', ')}.",
                        'fgHaStatsSyncStatus reporta configuración no sincronizada entre miembros del clúster.',
                        'Un failover podría activar un miembro con configuración desactualizada.',
                        "Ejecutar 'diagnose sys ha checksum cluster' en el FortiGate.");
                }
            }
        }

        // -------------------------------------------------------------
        // 10. CERTIFICADOS SSL PRÓXIMOS A VENCER
        // -------------------------------------------------------------
        try {
            $porVencer = \App\Models\ServicioWeb::where('es_activo', true)
                ->whereNotNull('ssl_dias_expiracion')
                ->where('ssl_dias_expiracion', '<=', 30)
                ->get();
            foreach ($porVencer as $web) {
                $dias = (int) $web->ssl_dias_expiracion;
                $sev = $dias <= 7 ? 'critica' : 'advertencia';
                $dt = $web->ultimo_chequeo ? \Carbon\Carbon::parse($web->ultimo_chequeo) : now();
                $alarmas[] = [
                    'id' => 'ssl-' . $web->id,
                    'servicio_web_id' => $web->id,
                    'tipo' => $sev,
                    'severidad' => $sev,
                    'categoria' => 'servicio_web',
                    'codigo_evento' => $dias < 0 ? 'SSL_EXPIRED' : 'SSL_EXPIRING',
                    'titulo' => $dias < 0 ? "Certificado SSL vencido: {$web->nombre}" : "Certificado SSL vence en {$dias} días: {$web->nombre}",
                    'dispositivo' => $web->nombre,
                    'ip' => parse_url($web->url, PHP_URL_HOST) ?? $web->url,
                    'ubicacion' => "Módulo Servicios Web [{$web->categoria}]",
                    'mensaje' => $dias < 0 ? "El certificado de {$web->url} venció hace " . abs($dias) . ' días.' : "El certificado de {$web->url} vence en {$dias} días.",
                    'causa_raiz' => 'Fecha de expiración leída del certificado presentado por el servidor.',
                    'impacto' => 'Los navegadores mostrarán advertencia de seguridad y los clientes API rechazarán la conexión.',
                    'accion_sugerida' => 'Renovar e instalar el certificado antes de la fecha de expiración.',
                    'fecha_falla' => $dt->format('d/m/Y H:i:s'),
                    'hora_falla' => $dt->format('h:i:s A'),
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $dt->format('h:i:s A') . " ({$dias} días)",
                    'leida' => false,
                    'link' => route('servicios_web.index'),
                    'accion' => 'Ver Servicios Web',
                ];
            }

            // Certificado que no corresponde al dominio de la URL (los que se usan por IP o con CA interna solo
            // se muestran en Servicios Web: es lo habitual en sistemas internos y no amerita alarma)
            $sinDominio = \App\Models\ServicioWeb::where('es_activo', true)
                ->where('ssl_problema', 'like', \App\Services\SslCertificateChecker::PROBLEMA_DOMINIO . '%')
                ->get()
                ->reject(fn ($w) => filter_var(parse_url($w->url, PHP_URL_HOST), FILTER_VALIDATE_IP) !== false);
            foreach ($sinDominio as $web) {
                $dt = $web->ultimo_chequeo ? \Carbon\Carbon::parse($web->ultimo_chequeo) : now();
                $host = parse_url($web->url, PHP_URL_HOST) ?? $web->url;
                $alarmas[] = [
                    'id' => 'ssl-dominio-' . $web->id,
                    'servicio_web_id' => $web->id,
                    'tipo' => 'advertencia',
                    'severidad' => 'advertencia',
                    'categoria' => 'servicio_web',
                    'codigo_evento' => 'SSL_NAME_MISMATCH',
                    'titulo' => "Certificado SSL de otro dominio: {$web->nombre}",
                    'dispositivo' => $web->nombre,
                    'ip' => $host,
                    'ubicacion' => "Módulo Servicios Web [{$web->categoria}]",
                    'mensaje' => "El certificado que presenta {$host} no lo nombra. {$web->ssl_problema}",
                    'causa_raiz' => 'El servidor entrega un certificado emitido para otro nombre (sitio por defecto, balanceador o certificado equivocado).',
                    'impacto' => 'Los navegadores muestran "la conexión no es privada" y los clientes API rechazan la conexión.',
                    'accion_sugerida' => "Instalar en el servidor de {$host} un certificado que incluya ese nombre, o revisar el enlace del sitio en IIS/Apache/balanceador.",
                    'fecha_falla' => $dt->format('d/m/Y H:i:s'),
                    'hora_falla' => $dt->format('h:i:s A'),
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $dt->format('h:i:s A'),
                    'leida' => false,
                    'link' => route('servicios_web.index'),
                    'accion' => 'Ver Servicios Web',
                ];
            }
        } catch (\Throwable $e) {
            // Silencioso si la tabla aún se está migrando
        }

        try {
            $alarmas = array_merge($alarmas, $this->alarmasVirtualizacion());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Alarmas de virtualización: ' . $e->getMessage());
        }

        // Ordenar por severidad (crítica -> advertencia -> informativa)
        usort($alarmas, function ($a, $b) {
            $peso = ['critica' => 1, 'advertencia' => 2, 'sistema' => 3, 'informativa' => 3];
            $pA = $peso[$a['severidad'] ?? $a['tipo']] ?? 4;
            $pB = $peso[$b['severidad'] ?? $b['tipo']] ?? 4;
            return $pA <=> $pB;
        });

        return $alarmas;
    }

    /** Umbrales de los hosts ESXi y datastores (los mismos que muestra la sección Virtualización). */
    private const ESXI_CPU_AVISO = 80, ESXI_CPU_CRITICO = 90, ESXI_RAM_AVISO = 85, ESXI_RAM_CRITICO = 95;
    private const DATASTORE_AVISO = 80, DATASTORE_CRITICO = 90;

    /**
     * Hosts ESXi y datastores, con lo que leyó worker/esxi_collector.py (tabla esxi_estado). Se usa la última
     * lectura de cada host aunque sea vieja, aclarándolo: si se dejaran de informar los problemas por falta de
     * lectura, las alarmas se darían por resueltas sin que nada haya cambiado (eso lo avisa ESXI_COLECTOR_DETENIDO).
     */
    private function alarmasVirtualizacion(): array
    {
        $hosts = \Illuminate\Support\Facades\DB::table('esxi_estado as e')->join('dispositivos as d', 'd.id', '=', 'e.dispositivo_id')
            ->whereNotNull('e.leido_en')
            ->get(['d.id', 'd.nombre', 'd.ip', 'e.conexion', 'e.mantenimiento', 'e.cpu_pct', 'e.ram_pct', 'e.sensores', 'e.alarmas', 'e.datastores', 'e.leido_en']);
        $alarmas = [];
        $datastores = [];
        $link = route('virtualizacion.index');

        foreach ($hosts as $h) {
            $leido = \Carbon\Carbon::parse($h->leido_en);
            $viejo = $leido->lt(now()->subMinutes(5)) ? ' (última lectura del ESXi ' . $leido->locale('es')->diffForHumans() . ')' : '';
            $corto = strtok($h->nombre, '.');
            $nueva = function (string $id, string $sev, string $categoria, string $codigo, string $titulo, string $mensaje, string $causa, string $impacto, string $accion)
                use (&$alarmas, $h, $leido, $viejo, $link) {
                $alarmas[] = [
                    'id' => $id, 'dispositivo_id' => $h->id, 'tipo' => $sev, 'severidad' => $sev, 'categoria' => $categoria,
                    'codigo_evento' => $codigo, 'titulo' => $titulo, 'dispositivo' => $h->nombre, 'ip' => $h->ip,
                    'ubicacion' => 'Centro de datos · Virtualización', 'mensaje' => $mensaje . $viejo, 'causa_raiz' => $causa,
                    'impacto' => $impacto, 'accion_sugerida' => $accion,
                    'fecha_falla' => $leido->format('d/m/Y H:i:s'), 'hora_falla' => $leido->format('h:i:s A'),
                    'hace_cuanto' => $leido->diffForHumans(), 'tiempo' => $leido->format('h:i:s A'),
                    'leida' => false, 'link' => $link, 'accion' => 'Ver Virtualización',
                ];
            };

            if ($h->conexion && $h->conexion !== 'connected') {
                $nueva("esxi-conexion-{$h->id}", 'critica', 'virtualizacion', 'ESXI_HOST_DISCONNECTED',
                    "Host ESXi {$corto} " . ($h->conexion === 'notResponding' ? 'sin respuesta' : 'desconectado') . ' del vCenter',
                    "Estado de conexión: {$h->conexion}.", 'El host perdió la comunicación con su administración (vCenter).',
                    'Sus VMs pueden seguir corriendo pero ya no se administran ni se protegen con HA.',
                    '1. Revisar la red de administración del host. 2. Reconectar el host en el vCenter.');
            }
            if ($h->mantenimiento) {
                $nueva("esxi-mantenimiento-{$h->id}", 'advertencia', 'virtualizacion', 'ESXI_HOST_MAINTENANCE',
                    "Host ESXi {$corto} en modo mantenimiento", 'El host está en modo mantenimiento.',
                    'Alguien lo puso en mantenimiento (actualización o reparación).', 'No acepta VMs nuevas; menos capacidad en el clúster.',
                    'Confirmar que es un mantenimiento programado y sacarlo del modo al terminar.');
            }
            if ($h->cpu_pct !== null && $h->cpu_pct >= self::ESXI_CPU_AVISO) {
                $crit = $h->cpu_pct >= self::ESXI_CPU_CRITICO;
                $nueva("esxi-cpu-{$h->id}", $crit ? 'critica' : 'advertencia', 'virtualizacion', 'ESXI_HOST_CPU_HIGH',
                    "CPU del host ESXi {$corto} al {$h->cpu_pct}%", "Uso de CPU del host: {$h->cpu_pct}%.",
                    'Las VMs del host están pidiendo más procesador del que conviene.', 'Las VMs del host se vuelven lentas.',
                    '1. Ver en Virtualización qué VMs usan más CPU. 2. Mover VMs a hosts con menos carga.');
            }
            if ($h->ram_pct !== null && $h->ram_pct >= self::ESXI_RAM_AVISO) {
                $crit = $h->ram_pct >= self::ESXI_RAM_CRITICO;
                $nueva("esxi-ram-{$h->id}", $crit ? 'critica' : 'advertencia', 'virtualizacion', 'ESXI_HOST_MEMORY_HIGH',
                    "Memoria del host ESXi {$corto} al {$h->ram_pct}%", "Uso de memoria del host: {$h->ram_pct}%.",
                    'Las VMs del host tienen asignada y en uso casi toda la memoria física.',
                    'El host empieza a reclamar memoria a las VMs (balloon/swap) y se vuelven lentas.',
                    '1. Mover VMs a otro host. 2. Revisar VMs con memoria asignada de más.');
            }
            $malos = collect(json_decode($h->sensores ?? '[]', true) ?: [])->whereIn('estado', ['yellow', 'red'])->values();
            if ($malos->isNotEmpty()) {
                $rojo = $malos->contains('estado', 'red');
                $nueva("esxi-sensores-{$h->id}", $rojo ? 'critica' : 'advertencia', 'virtualizacion', 'ESXI_HARDWARE_SENSOR',
                    "Hardware del host ESXi {$corto}: " . $malos->count() . ' sensor(es) ' . ($rojo ? 'en rojo' : 'en amarillo'),
                    'Sensores con problema: ' . $malos->map(fn ($s) => "{$s['nombre']} ({$s['tipo']}" . ($s['valor'] !== null ? ", {$s['valor']} {$s['unidad']}" : '') . ')')->implode('; ') . '.',
                    'El servidor físico reporta una pieza fuera de lo normal (disco, memoria, fuente, ventilador o temperatura).',
                    'Si la pieza falla, el host y todas sus VMs pueden caer.',
                    '1. Revisar el hardware del servidor en su consola (iDRAC/OpenManage). 2. Abrir caso con el fabricante si la pieza está dañada.');
            }
            foreach (json_decode($h->alarmas ?? '[]', true) ?: [] as $al) {
                $nueva("esxi-alarma-{$h->id}-" . substr(md5($al['nombre'] ?? ''), 0, 10), ($al['estado'] ?? '') === 'red' ? 'critica' : 'advertencia',
                    'virtualizacion', 'ESXI_VMWARE_ALARM', "Alarma de VMware en {$corto}: " . ($al['nombre'] ?? 'sin nombre'),
                    'VMware disparó la alarma «' . ($al['nombre'] ?? '') . '».', 'Alarma definida en VMware.', 'Según la alarma.',
                    'Revisar la alarma en el vCenter.');
            }
            foreach (json_decode($h->datastores ?? '[]', true) ?: [] as $ds) {
                $clave = $ds['url'] ?? ($h->id . '|' . ($ds['nombre'] ?? ''));
                $datastores[$clave] ??= $ds + ['hosts' => [], 'libres' => [], 'host_id' => $h->id, 'viejo' => $viejo, 'leido' => $leido];
                $datastores[$clave]['hosts'][] = $corto;
                if (($ds['libre'] ?? null) !== null) {
                    $datastores[$clave]['libres'][] = $ds['libre'];
                }
            }
        }

        foreach ($datastores as $clave => $ds) {
            $tb = fn ($b) => round($b / 1024 ** 4, 2) . ' TB';
            $hostsTxt = implode(', ', array_unique($ds['hosts']));
            $base = [
                'dispositivo_id' => $ds['host_id'], 'categoria' => 'virtualizacion', 'dispositivo' => "Datastore {$ds['nombre']}",
                'ip' => '—', 'ubicacion' => 'Centro de datos · Virtualización',
                'fecha_falla' => $ds['leido']->format('d/m/Y H:i:s'), 'hora_falla' => $ds['leido']->format('h:i:s A'),
                'hace_cuanto' => $ds['leido']->diffForHumans(), 'tiempo' => $ds['leido']->format('h:i:s A'),
                'leida' => false, 'link' => $link, 'accion' => 'Ver Virtualización',
            ];
            if (($ds['accesible'] ?? true) === false) {
                $alarmas[] = $base + [
                    'id' => 'esxi-ds-inaccesible-' . substr(md5($clave), 0, 12), 'tipo' => 'critica', 'severidad' => 'critica',
                    'codigo_evento' => 'ESXI_DATASTORE_INACCESSIBLE', 'titulo' => "Datastore inaccesible: {$ds['nombre']}",
                    'mensaje' => "Los hosts no pueden acceder al datastore {$ds['nombre']} (hosts: {$hostsTxt}).{$ds['viejo']}",
                    'causa_raiz' => 'Se perdió el acceso al almacenamiento (LUN desconectada, ruta de almacenamiento caída o volumen retirado).',
                    'impacto' => 'Las VMs con discos en ese datastore no pueden leer ni escribir.',
                    'accion_sugerida' => '1. Revisar el arreglo de almacenamiento y sus rutas (iSCSI/FC). 2. Si el volumen se retiró a propósito, desmontarlo de los hosts.',
                ];
                continue;
            }
            $libre = $ds['libres'] ? min($ds['libres']) : null;
            if (!$ds['capacidad'] || $libre === null) {
                continue;
            }
            $usado = round(($ds['capacidad'] - $libre) / $ds['capacidad'] * 100, 1);
            if ($usado >= self::DATASTORE_AVISO) {
                $crit = $usado >= self::DATASTORE_CRITICO;
                $max = max($ds['libres']);
                $alarmas[] = $base + [
                    'id' => 'esxi-ds-lleno-' . substr(md5($clave), 0, 12), 'tipo' => $crit ? 'critica' : 'advertencia', 'severidad' => $crit ? 'critica' : 'advertencia',
                    'codigo_evento' => 'ESXI_DATASTORE_FULL', 'titulo' => "Datastore {$ds['nombre']} al {$usado}%",
                    'mensaje' => "Libres {$tb($libre)} de {$tb($ds['capacidad'])}"
                        . ($max - $libre > $ds['capacidad'] * 0.01 ? " (el menor que reportan los hosts; otros reportan hasta {$tb($max)})" : '')
                        . ". Hosts: {$hostsTxt}.{$ds['viejo']}",
                    'causa_raiz' => 'El almacenamiento de las VMs se está llenando (discos que crecen, snapshots o VMs nuevas).',
                    'impacto' => 'Si se llena, las VMs con discos ahí se pausan y no se pueden crear snapshots ni respaldos.',
                    'accion_sugerida' => '1. Borrar snapshots viejos y VMs que ya no se usan. 2. Mover VMs a otro datastore o ampliar el volumen.',
                ];
            }
        }
        return $alarmas;
    }

    /**
     * Estado de fuente/ventilador que indica falla. "NOT PRESENT" (bahía vacía) y "DESCONOCIDO" no son falla.
     */
    public static function componenteEnFalla(string $status): bool
    {
        $status = strtoupper($status);
        // FRU-CONTROL reporta "OK (falla ventilador)" / "OK (falla PoE)": encendida pero degradada
        return str_contains($status, 'FALLA')
            || ($status !== '' && !str_starts_with($status, 'OK') && !in_array($status, ['NOT PRESENT', 'DESCONOCIDO'], true));
    }

    /**
     * Diagnóstico de desconexión basado en lo que realmente se sabe: estado de los vecinos
     * (enlaces CDP/LLDP registrados) y el estado operativo que reporta el puerto remoto por SNMP.
     * Lo que no está medido se presenta como hipótesis a verificar, no como hecho.
     */
    private function analizarCausaDesconexion(Dispositivo $disp, $enlaces): array
    {
        $nombre = $disp->nombre;
        $ip = $disp->ip;

        $vecinos = $enlaces->filter(fn ($e) => $e->origen_dispositivo_id === $disp->id || $e->destino_dispositivo_id === $disp->id)
            ->map(function ($e) use ($disp) {
                $esOrigen = $e->origen_dispositivo_id === $disp->id;
                return [
                    'vecino' => $esOrigen ? $e->dispositivoDestino : $e->dispositivoOrigen,
                    'puerto_remoto' => $esOrigen ? $e->interfazDestino : $e->interfazOrigen,
                ];
            })
            ->filter(fn ($v) => $v['vecino'] !== null)
            ->values();

        $base = [
            'resumen' => "Sin respuesta a ICMP ni SNMP en {$ip}.",
            'impacto' => "Servicios y administración de {$nombre} inalcanzables.",
        ];

        if ($vecinos->isNotEmpty() && $vecinos->every(fn ($v) => $v['vecino']->estado === 'offline')) {
            $lista = $vecinos->map(fn ($v) => "{$v['vecino']->nombre} ({$v['vecino']->ip})")->implode(', ');
            return $base + [
                'codigo' => 'CASCADE_FAILURE',
                'titulo' => 'Aislado: sus vecinos también están caídos',
                'causa_raiz' => "Todos los equipos vecinos registrados de {$nombre} tampoco responden: {$lista}. Probable falla aguas arriba.",
                'accion_sugerida' => "Atender primero a: {$lista}.",
            ];
        }

        // Vecino en línea cuyo puerto hacia este equipo tiene estado medido por SNMP
        foreach ($vecinos as $v) {
            $vecino = $v['vecino'];
            $puerto = $v['puerto_remoto'];
            if ($vecino->estado === 'offline' || !$puerto) {
                continue;
            }
            $oper = $puerto->ultimaTelemetria?->oper_status;
            $medido = $puerto->ultimaTelemetria?->updated_at?->diffForHumans();
            if ($oper === 'down') {
                return $base + [
                    'codigo' => 'PHYSICAL_DISCONNECT',
                    'titulo' => "Puerto {$puerto->nombre} de {$vecino->nombre} en DOWN",
                    'causa_raiz' => "{$vecino->nombre} reporta por SNMP el puerto {$puerto->nombre} (hacia {$nombre}) en estado DOWN (medido {$medido}): no hay enlace físico.",
                    'accion_sugerida' => "Revisar cable/transceptor en {$puerto->nombre} de {$vecino->nombre} y la energía de {$nombre}.",
                ];
            }
            if ($oper === 'up') {
                return $base + [
                    'codigo' => 'HOST_UNREACHABLE_LINK_UP',
                    'titulo' => "Sin respuesta con enlace físico activo",
                    'causa_raiz' => "{$vecino->nombre} reporta el puerto {$puerto->nombre} (hacia {$nombre}) en estado UP (medido {$medido}), pero {$nombre} no responde a ICMP/SNMP. Posibles causas: equipo colgado, IP/ACL de gestión o VLAN de gestión.",
                    'accion_sugerida' => "Revisar consola de {$nombre}, su configuración de gestión y la VLAN del puerto {$puerto->nombre}.",
                ];
            }
        }

        return $base + [
            'codigo' => 'HOST_UNREACHABLE',
            'titulo' => "Equipo inaccesible ({$ip})",
            'causa_raiz' => $vecinos->isEmpty()
                ? "No hay enlaces registrados para {$nombre} y no se puede determinar la causa con los datos disponibles."
                : "No hay estado SNMP del puerto vecino hacia {$nombre}; la causa no se puede determinar con los datos disponibles.",
            'accion_sugerida' => "1. Verificar energía del equipo. 2. Ping manual a {$ip}. 3. Revisar el puerto del switch donde está conectado.",
        ];
    }
}
