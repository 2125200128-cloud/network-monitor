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
    /**
     * Genera el diagnóstico inteligente de causa raíz para todas las fallas activas en la red.
     */
    public function diagnosticarAlarmasRed(bool $aplicarEstadoUsuario = true): array
    {
        $alarmas = $this->compilarAlarmas();
        return $aplicarEstadoUsuario ? $this->aplicarEstadoUsuario($alarmas) : $alarmas;
    }

    /**
     * Marca como leídas u oculta las descartadas según la cuenta autenticada.
     */
    public function aplicarEstadoUsuario(array $alarmas): array
    {
        if (!auth()->check()) {
            return $alarmas;
        }
        $userReadMap = \App\Models\NotificacionLeida::where('user_id', auth()->id())
            ->get()
            ->keyBy('notificacion_id');

        $resultado = [];
        foreach ($alarmas as $alarma) {
            $record = $userReadMap[$alarma['id']] ?? null;
            if ($record?->estado === 'descartada') {
                continue;
            }
            if ($record?->estado === 'leida') {
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
            ->with(['interfaz.dispositivo'])
            ->get();

        foreach ($errDisabledList as $telemetria) {
            $interfaz = $telemetria->interfaz;
            $disp = $interfaz ? $interfaz->dispositivo : null;
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
                    $sub->where('nombre', 'LIKE', '%wan%')
                        ->orWhere('nombre', 'LIKE', '%port1%')
                        ->orWhere('nombre', 'LIKE', '%port2%')
                        ->orWhere('nombre', 'LIKE', '%ipsec%')
                        ->orWhere('nombre', 'LIKE', '%vpn%')
                        ->orWhere('nombre', 'LIKE', '%ssl%');
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
                    'causa_raiz' => "Proceso de plano de control demandante. Causas comunes: Tormenta de broadcast/multicast (STP Loop), ráfaga de consultas SNMP, debugs activos en consola o ruteo intensivo.",
                    'impacto' => "Retardo en el procesamiento de paquetes de control (CDP, LLDP, OSPF/BGP, STP) y latencia en la administración web/SSH.",
                    'accion_sugerida' => "Ejecutar comando 'show processes cpu sorted 5sec' en la terminal SSH para identificar el proceso o hilo causante.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " (CPU {$cpu}%)",
                    'leida' => false,
                    'link' => route('dispositivos.show', $disp->id),
                    'accion' => 'Inspeccionar Procesos'
                ];
            }

            if ($mem >= 85) {
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
            $isForti = str_contains(strtolower($disp->nombre . ' ' . $disp->comunidad_snmp), 'forti') || str_starts_with(strtolower($disp->nombre), 'fg-') || str_starts_with(strtolower($disp->nombre), 'fw-');
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
                    'mensaje' => "Página inaccesible o con error. Estado: {$codeStr}{$latenciaStr}. URL: {$web->url}",
                    'causa_raiz' => $web->detalles_error ?: ($isOffline ? 'El servidor web no respondió a la solicitud o se agotó el tiempo de espera.' : 'La página devolvió un error de cliente o latencia elevada.'),
                    'impacto' => "Interrupción en la atención a usuarios / trámites digitales en línea.",
                    'accion_sugerida' => "1. Verificar servicio Apache/IIS/Tomcat o balanceador en el servidor. 2. Probar conectividad hacia la URL: {$web->url}.",
                    'fecha_falla' => $fechaFalla,
                    'hora_falla' => $horaFalla,
                    'hace_cuanto' => $dt->diffForHumans(),
                    'tiempo' => $horaFalla . " ({$codeStr})",
                    'leida' => false,
                    'link' => route('servicios_web.index'),
                    'accion' => 'Ver Servicios Web'
                ];
            }
        } catch (\Throwable $e) {
            // Silencioso si la tabla aún se está migrando
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
