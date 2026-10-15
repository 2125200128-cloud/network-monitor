{{-- Diagnóstico de red por pestañas. Todos los valores provienen de DashboardMetricsService (solo datos medidos). --}}
@php
    $p = $paneles['puertos'];
    $hw = $paneles['hardware'];
    $top = $paneles['top_equipos'];
    $sla = $paneles['disponibilidad'];
    $web = $paneles['servicios_web'];
    $inc = $paneles['incidentes'];
    $seg = $paneles['seguridad'];
    $ep = $paneles['endpoints'];
    $enl = $paneles['enlaces'];
    $cob = $paneles['cobertura'];

    $n = fn ($v) => $v === null ? '—' : number_format($v);
    $pct = fn ($v) => $v === null ? 'Sin dato' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%';
    $vel = fn ($v) => $v === null ? '—' : ($v >= 1000 ? ($v / 1000) . 'G' : $v . 'M');
    $colDisp = ['key' => 'dispositivo', 'label' => 'Equipo'];
    $colPuerto = ['key' => 'puerto', 'label' => 'Puerto', 'class' => 'font-mono'];

    $tabs = [
        'puertos' => ['Puertos', count($p['err_disabled']) + $p['caidos_con_uso_total']],
        'hardware' => ['Hardware', count($hw['fallas'])],
        'rendimiento' => ['Rendimiento', $enl['down']],
        'disponibilidad' => ['Disponibilidad', $sla['equipos_bajo_99']],
        'web' => ['Servicios web', ($web['offline'] ?? 0) + count($web['ssl_por_vencer'] ?? [])],
        'incidentes' => ['Incidentes', $inc['criticas']],
        'seguridad' => ['Firewall / Wi-Fi', $seg['vpn_total'] - $seg['vpn_up'] + $seg['sdwan_caidos'] + count($seg['sensores_alarma'])],
        'endpoints' => ['Equipos finales', 0],
        'cobertura' => ['Cobertura', collect($cob['workers'])->where('ok', false)->count()],
    ];
@endphp

<div class="floating-card p-6 mb-6" id="diagnosticoRed"
     x-data="{ tab: 'puertos' }"
     @diag-tab.window="tab = $event.detail"
     x-init="try { const t = localStorage.getItem('dashDiagTab'); if (t) tab = t; } catch (e) {} $watch('tab', v => { try { localStorage.setItem('dashDiagTab', v) } catch (e) {} })">
    <div class="flex flex-col gap-3 mb-5 pb-4 border-b border-gray-100 dark:border-slate-800/40">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#3b5998] shadow-sm"></span>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">Diagnóstico de red</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Solo valores reportados por los equipos. "Sin dato" = el equipo no expone esa métrica por SNMP. Se recalcula cada minuto.</p>
        </div>
        <div class="flex flex-wrap gap-1.5 bg-gray-100/80 dark:bg-[#090b10] p-1 rounded-xl text-xs font-semibold" role="tablist">
            @foreach($tabs as $k => [$label, $count])
                <button type="button" role="tab" @click="tab = '{{ $k }}'" :aria-selected="tab === '{{ $k }}'"
                        :class="tab === '{{ $k }}' ? 'bg-white dark:bg-slate-800 text-hacienda-blue dark:text-blue-400 shadow-sm font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                        class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                    {{ $label }}
                    @if($count > 0)
                        <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-[#f26419] text-white text-[10px] font-black flex items-center justify-center">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ============================ PUERTOS ============================ --}}
    <div x-show="tab === 'puertos'" role="tabpanel">
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-4">
            @include('dashboard.partials._stat', ['label' => 'Puertos físicos', 'valor' => $n($p['total'])])
            @include('dashboard.partials._stat', ['label' => 'Arriba', 'valor' => $n($p['up']), 'tono' => 'ok'])
            @include('dashboard.partials._stat', ['label' => 'Libres', 'valor' => $n($p['libres']), 'title' => 'Habilitados, sin enlace y sin descripción'])
            @include('dashboard.partials._stat', ['label' => 'Caídos con uso', 'valor' => $n($p['caidos_con_uso_total']), 'tono' => $p['caidos_con_uso_total'] ? 'warn' : 'ok', 'title' => 'Habilitados y en DOWN, con descripción configurada o enlace CDP/LLDP'])
            @include('dashboard.partials._stat', ['label' => 'Err-disabled', 'valor' => count($p['err_disabled']), 'tono' => count($p['err_disabled']) ? 'crit' : 'ok'])
            @include('dashboard.partials._stat', ['label' => 'Deshabilitados', 'valor' => $n($p['admin_down']), 'title' => 'shutdown administrativo'])
            @include('dashboard.partials._stat', ['label' => 'Sin lectura', 'valor' => $n($p['sin_dato']), 'title' => 'Sin estado operativo reciente (≤ 10 min)'])
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('dashboard.partials._lista', [
                'titulo' => 'Puertos err-disabled', 'items' => $p['err_disabled'], 'vacio' => 'Ningún puerto suspendido',
                'badge' => count($p['err_disabled']), 'badgeClass' => count($p['err_disabled']) ? 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-400' : null,
                'cols' => [$colDisp, $colPuerto, ['key' => 'motivo', 'label' => 'Causa', 'class' => 'font-mono text-red-600 dark:text-red-400']],
                'nota' => 'CISCO-ERR-DISABLE-MIB. Se recupera con shutdown / no shutdown tras corregir la causa.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Caídos con descripción o enlace', 'items' => $p['caidos_con_uso'], 'vacio' => 'Ninguno',
                'badge' => $p['caidos_con_uso_total'],
                'cols' => [$colDisp, $colPuerto, ['key' => 'alias', 'label' => 'Descripción'],
                    ['label' => 'Enlace', 'fmt' => fn ($r) => $r['enlace'] ? 'CDP/LLDP' : '—']],
                'nota' => 'Un puerto con descripción o vecino registrado normalmente debería estar arriba.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Top 10 puertos más utilizados', 'items' => $p['saturados'], 'vacio' => 'Sin lecturas de utilización',
                'cols' => [$colDisp, $colPuerto, ['key' => 'velocidad', 'label' => 'Vel.', 'align' => 'text-right', 'fmt' => fn ($r) => $vel($r['velocidad'])],
                    ['label' => 'Uso', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['util'] . '%']],
                'nota' => 'Máximo entre entrada y salida respecto a la velocidad del puerto. Saturado: ≥ ' . \App\Services\DashboardMetricsService::SATURACION_PCT . '%.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Puertos con errores', 'items' => $p['errores'], 'vacio' => 'Sin errores registrados',
                'cols' => [$colDisp, $colPuerto,
                    ['label' => 'Errores', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['errores'])],
                    ['label' => 'CRC', 'align' => 'text-right', 'class' => 'font-mono text-red-600 dark:text-red-400', 'fmt' => fn ($r) => $n($r['crc'])],
                    ['label' => 'Descartes', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['descartes'])]],
                'nota' => 'Contadores acumulados desde el último reinicio o clear counters. CRC creciente = cableado, conector o dúplex.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Velocidad o dúplex degradado', 'items' => $p['degradados'], 'vacio' => 'Ningún enlace degradado',
                'cols' => [$colDisp, $colPuerto, ['label' => 'Vel.', 'align' => 'text-right', 'fmt' => fn ($r) => $vel($r['velocidad'])],
                    ['key' => 'duplex', 'label' => 'Dúplex', 'class' => 'font-mono'], ['label' => 'Tipo', 'fmt' => fn ($r) => $r['enlace'] ? 'Enlace' : 'Fibra']],
                'nota' => 'Puertos activos en half-dúplex, o enlaces/fibra negociados a 10 o 100 Mbps.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Mayor tráfico broadcast', 'items' => $p['broadcast'], 'vacio' => 'Sin tasa medida todavía (requiere dos ciclos del colector)',
                'cols' => [$colDisp, $colPuerto, ['label' => 'Paq/s', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => number_format($r['pps'], 1)]],
                'nota' => 'Broadcast de entrada por segundo entre los dos últimos ciclos (5 min). Valores de miles indican tormenta o bucle.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Más descartes', 'items' => $p['descartes'], 'vacio' => 'Sin descartes',
                'cols' => [$colDisp, $colPuerto, ['label' => 'Descartes', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['descartes'])]],
                'nota' => 'Paquetes descartados por congestión de buffers (acumulado).',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Potencia óptica Rx baja', 'items' => $p['optica_baja'], 'vacio' => 'Ningún puerto activo con Rx bajo',
                'badge' => $p['optica_medidos'] . ' con DOM',
                'cols' => [$colDisp, $colPuerto,
                    ['label' => 'Rx', 'align' => 'text-right', 'class' => 'font-mono text-amber-600', 'fmt' => fn ($r) => $r['rx'] . ' dBm'],
                    ['label' => 'Tx', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $r['tx'] === null ? '—' : $r['tx'] . ' dBm']],
                'nota' => 'Puertos arriba con Rx < ' . \App\Services\DashboardMetricsService::RX_BAJO_DBM . ' dBm (umbral genérico; el límite real depende del transceptor).',
            ])
        </div>
    </div>

    {{-- ============================ HARDWARE ============================ --}}
    <div x-show="tab === 'hardware'" role="tabpanel" style="display: none;">
        @php
            $psu = $hw['componentes']['psu'];
            $fan = $hw['componentes']['fan'];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-4">
            @include('dashboard.partials._stat', ['label' => 'Fuentes OK', 'valor' => $psu['total'] ? $psu['ok'] . ' / ' . $psu['total'] : 'Sin dato', 'tono' => $psu['ok'] < $psu['total'] ? 'crit' : 'ok'])
            @include('dashboard.partials._stat', ['label' => 'Ventiladores OK', 'valor' => $fan['total'] ? $fan['ok'] . ' / ' . $fan['total'] : 'Sin dato', 'tono' => $fan['ok'] < $fan['total'] ? 'crit' : 'ok'])
            @include('dashboard.partials._stat', ['label' => 'Temp. máxima', 'valor' => $hw['temp_max'] === null ? 'Sin dato' : round($hw['temp_max']) . ' °C', 'tono' => $hw['temp_max'] === null ? null : ($hw['temp_max'] >= 65 ? 'crit' : ($hw['temp_max'] >= 55 ? 'warn' : 'ok')), 'title' => $hw['temp_equipos'] . ' equipos reportan temperatura'])
            @include('dashboard.partials._stat', ['label' => 'PoE usado', 'valor' => $hw['poe'] ? $hw['poe']['usado_w'] . ' / ' . $hw['poe']['total_w'] . ' W' : 'Sin dato', 'title' => $hw['poe'] ? $hw['poe']['switches'] . ' switches con PoE' : ''])
            @include('dashboard.partials._stat', ['label' => 'Cambios STP < 1 h', 'valor' => count($hw['stp_recientes']), 'tono' => count($hw['stp_recientes']) ? 'warn' : 'ok', 'title' => $hw['stp_equipos'] . ' equipos reportan BRIDGE-MIB'])
            @include('dashboard.partials._stat', ['label' => 'Reinicios 24 h', 'valor' => count($hw['reinicios_24h']), 'tono' => count($hw['reinicios_24h']) ? 'warn' : 'ok'])
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('dashboard.partials._lista', [
                'titulo' => 'Fuentes y ventiladores en falla', 'items' => $hw['fallas'], 'vacio' => 'Todos los componentes reportan OK',
                'badge' => count($hw['fallas']), 'badgeClass' => count($hw['fallas']) ? 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-400' : null,
                'cols' => [$colDisp, ['key' => 'tipo', 'label' => 'Tipo'], ['key' => 'componente', 'label' => 'Componente'],
                    ['key' => 'estado', 'label' => 'Estado', 'class' => 'font-mono text-red-600 dark:text-red-400']],
                'nota' => 'CISCO-ENVMON-MIB / ENTITY-FRU-CONTROL. SHUTDOWN en una fuente suele indicar cable de energía desconectado: se pierde la redundancia.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Equipos más calientes', 'items' => $hw['temp_top'], 'vacio' => 'Ningún equipo reporta temperatura',
                'cols' => [$colDisp, ['label' => 'Temp.', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['temp'] . ' °C']],
                'nota' => 'Sensor más caliente de cada chasis. Alerta a partir de 55 °C, crítica a partir de 65 °C.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Consumo PoE por switch', 'items' => $hw['poe']['top'] ?? [], 'vacio' => 'Ningún equipo reporta POWER-ETHERNET-MIB',
                'badge' => $hw['poe'] ? $hw['poe']['pct'] . '% global' : null,
                'cols' => [$colDisp, ['label' => 'Usado', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $r['usado'] . ' / ' . $r['total'] . ' W'],
                    ['label' => '%', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['pct'] . '%']],
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Cambios de topología STP (última hora)', 'items' => $hw['stp_recientes'], 'vacio' => 'Sin cambios de topología en la última hora',
                'cols' => [$colDisp, ['key' => 'hace', 'label' => 'Último cambio'], ['label' => 'Total', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['cambios'])]],
                'nota' => 'BRIDGE-MIB (instancia por defecto). Cambios frecuentes = puertos de acceso sin PortFast o un bucle.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Equipos reiniciados en 24 h', 'items' => $hw['reinicios_24h'], 'vacio' => 'Ningún reinicio en las últimas 24 h',
                'cols' => [$colDisp, ['key' => 'hace', 'label' => 'Encendido hace']],
                'nota' => 'sysUpTime menor a 24 h (también se reinicia si se reinicia el agente SNMP).',
            ])
        </div>
    </div>

    {{-- ============================ RENDIMIENTO ============================ --}}
    <div x-show="tab === 'rendimiento'" role="tabpanel" style="display: none;">
        @php $colVal = fn (string $label, string $u, int $dec = 0) => ['label' => $label, 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => number_format($r['valor'], $dec) . $u]; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @include('dashboard.partials._lista', ['titulo' => 'Mayor CPU', 'items' => $top['cpu'], 'cols' => [$colDisp, $colVal('CPU', '%')]])
            @include('dashboard.partials._lista', ['titulo' => 'Mayor memoria', 'items' => $top['ram'], 'cols' => [$colDisp, $colVal('RAM', '%')]])
            @include('dashboard.partials._lista', ['titulo' => 'Mayor latencia', 'items' => $top['latencia'], 'cols' => [$colDisp, $colVal('Ping', ' ms', 1)]])
            @include('dashboard.partials._lista', ['titulo' => 'Mayor pérdida de paquetes', 'items' => $top['perdida'], 'vacio' => 'Sin pérdida en equipos en línea', 'cols' => [$colDisp, $colVal('Pérdida', '%', 1)]])
            @include('dashboard.partials._lista', [
                'titulo' => 'Mayor jitter (última hora)', 'items' => $top['jitter'], 'vacio' => 'Menos de 10 muestras por equipo en la última hora',
                'cols' => [$colDisp, ['label' => 'Ping prom.', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $r['ping'] . ' ms'], $colVal('Jitter', ' ms', 1)],
                'nota' => 'Desviación estándar de la latencia ICMP. Para voz IP conviene < 30 ms.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Enlaces más cargados', 'items' => $enl['mas_cargados'], 'vacio' => 'Sin utilización medida en enlaces',
                'badge' => $enl['up'] . ' arriba · ' . $enl['down'] . ' abajo',
                'cols' => [['label' => 'Enlace', 'fmt' => fn ($r) => $r['origen'] . ' → ' . $r['destino']], ['key' => 'puerto', 'label' => 'Puerto', 'class' => 'font-mono'],
                    ['label' => 'Uso', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['util'] . '%']],
            ])
        </div>
        <div class="mt-4">
            @include('dashboard.partials._lista', [
                'titulo' => 'Enlaces CDP/LLDP caídos', 'items' => $enl['caidos'], 'vacio' => 'Todos los enlaces con lectura están arriba',
                'badge' => $enl['down'], 'badgeClass' => $enl['down'] ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400' : null,
                'cols' => [['key' => 'origen', 'label' => 'Origen'], ['key' => 'destino', 'label' => 'Destino'], ['key' => 'puerto', 'label' => 'Puerto', 'class' => 'font-mono'],
                    ['label' => 'Vel.', 'align' => 'text-right', 'fmt' => fn ($r) => $vel($r['velocidad'])]],
                'nota' => $enl['sin_dato'] . ' enlaces sin lectura reciente de sus puertos. Incluye vecinos como teléfonos IP desconectados.',
            ])
        </div>
    </div>

    {{-- ============================ DISPONIBILIDAD ============================ --}}
    <div x-show="tab === 'disponibilidad'" role="tabpanel" style="display: none;">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 mb-4">
            @foreach(['24h' => 'Últimas 24 h', '7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días'] as $k => $label)
                @php $g = $sla['global'][$k]; @endphp
                @include('dashboard.partials._stat', [
                    'label' => $label, 'valor' => $pct($g['pct'] ?? null),
                    'tono' => $g === null ? null : ($g['pct'] >= 99.9 ? 'ok' : ($g['pct'] >= 99 ? 'warn' : 'crit')),
                    'title' => $g ? number_format($g['muestras']) . ' muestras desde ' . $g['desde'] : 'Sin muestras',
                ])
            @endforeach
            @include('dashboard.partials._stat', ['label' => 'Equipos al 100% (24 h)', 'valor' => $sla['equipos_100'] . ' / ' . $sla['equipos_24h'], 'tono' => 'ok'])
            @include('dashboard.partials._stat', ['label' => 'Equipos < 99% (24 h)', 'valor' => $sla['equipos_bajo_99'], 'tono' => $sla['equipos_bajo_99'] ? 'crit' : 'ok'])
        </div>
        @include('dashboard.partials._lista', [
            'titulo' => 'Menor disponibilidad (7 días)', 'items' => $sla['peores_7d'], 'vacio' => 'Todos los equipos al 100%',
            'cols' => [$colDisp,
                ['label' => 'Disponibilidad', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $pct($r['pct'])],
                ['label' => 'Muestras', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['muestras'])]],
            'nota' => 'Porcentaje de muestras del sondeo (cada 15 s) en las que el equipo respondió ICMP o SNMP. Los periodos sin sondeo no cuentan.',
        ])
    </div>

    {{-- ============================ SERVICIOS WEB ============================ --}}
    <div x-show="tab === 'web'" role="tabpanel" style="display: none;">
        @if(($web['total'] ?? 0) === 0)
            <p class="text-sm text-gray-400 text-center py-6">No hay servicios web activos. <a href="{{ route('servicios_web.index') }}" class="text-hacienda-blue hover:underline">Agregar servicios</a></p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-4">
                @include('dashboard.partials._stat', ['label' => 'En línea', 'valor' => $web['online'] . ' / ' . $web['total'], 'tono' => 'ok'])
                @include('dashboard.partials._stat', ['label' => 'Degradados', 'valor' => $web['warning'], 'tono' => $web['warning'] ? 'warn' : 'ok'])
                @include('dashboard.partials._stat', ['label' => 'Caídos', 'valor' => $web['offline'], 'tono' => $web['offline'] ? 'crit' : 'ok'])
                @include('dashboard.partials._stat', ['label' => 'Respuesta prom.', 'valor' => $web['latencia_prom'] === null ? 'Sin dato' : $web['latencia_prom'] . ' ms'])
                @include('dashboard.partials._stat', ['label' => 'Disponibilidad 24 h', 'valor' => $pct($web['disponibilidad_24h']), 'title' => 'Chequeos con respuesta (en línea o degradado) / chequeos totales'])
                @include('dashboard.partials._stat', ['label' => 'SSL por vencer', 'valor' => count($web['ssl_por_vencer']), 'tono' => count($web['ssl_por_vencer']) ? 'warn' : 'ok', 'title' => $web['ssl_medidos'] . ' certificados medidos'])
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @include('dashboard.partials._lista', [
                    'titulo' => 'Certificados SSL por vencer (≤ 30 días)', 'items' => $web['ssl_por_vencer'], 'vacio' => 'Ningún certificado vence en 30 días',
                    'cols' => [['key' => 'nombre', 'label' => 'Servicio'],
                        ['label' => 'Vence en', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['dias'] < 0 ? 'VENCIDO' : $r['dias'] . ' días']],
                    'nota' => 'Se revisa cada ' . \App\Services\SslCertificateChecker::INTERVALO_HORAS . ' h. Solo sitios HTTPS.',
                ])
                @include('dashboard.partials._lista', [
                    'titulo' => 'Respuesta más lenta', 'items' => $web['lentos'],
                    'cols' => [['key' => 'nombre', 'label' => 'Servicio'], ['label' => 'Tiempo', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => number_format($r['ms']) . ' ms']],
                ])
                @include('dashboard.partials._lista', [
                    'titulo' => 'Menor disponibilidad (24 h)', 'items' => $web['peor_disponibilidad'], 'vacio' => 'Todos al 100%',
                    'cols' => [['key' => 'nombre', 'label' => 'Servicio'], ['label' => 'Disp.', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['pct'] . '%']],
                ])
            </div>
        @endif
    </div>

    {{-- ============================ INCIDENTES ============================ --}}
    <div x-show="tab === 'incidentes'" role="tabpanel" style="display: none;">
        @if(!$inc['registro_desde'])
            <p class="text-sm text-gray-400 text-center py-6">Aún no hay historial de alarmas. Lo registra <code class="font-mono">php artisan noc:sync</code> (ventana "Alarmas y consolidado" de iniciar_monitor.bat).</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-4">
                @include('dashboard.partials._stat', ['label' => 'Activas críticas', 'valor' => $inc['criticas'], 'tono' => $inc['criticas'] ? 'crit' : 'ok'])
                @include('dashboard.partials._stat', ['label' => 'Activas advertencia', 'valor' => $inc['advertencias'], 'tono' => $inc['advertencias'] ? 'warn' : 'ok'])
                @include('dashboard.partials._stat', ['label' => 'Nuevas 24 h', 'valor' => $inc['nuevas_24h']])
                @include('dashboard.partials._stat', ['label' => 'Resueltas 7 d', 'valor' => $inc['resueltas_7d']])
                @include('dashboard.partials._stat', ['label' => 'MTTR (7 d)', 'valor' => $inc['mttr'] ?? 'Sin dato', 'title' => 'Tiempo medio desde que inicia una alarma hasta que deja de presentarse'])
                @include('dashboard.partials._stat', ['label' => 'MTBF equipos', 'valor' => $inc['mtbf'] ?? 'Sin caídas', 'title' => 'Tiempo de operación acumulado de todos los equipos / número de caídas en la ventana'])
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @include('dashboard.partials._lista', [
                    'titulo' => 'Equipos con más caídas (7 días)', 'items' => $inc['top_caidas'], 'vacio' => 'Sin caídas registradas',
                    'badge' => $inc['caidas_7d'] . ' caídas',
                    'cols' => [$colDisp, ['label' => 'Caídas', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['caidas']],
                        ['key' => 'caido', 'label' => 'Tiempo caído', 'align' => 'text-right']],
                ])
                @include('dashboard.partials._lista', [
                    'titulo' => 'Últimas alarmas resueltas', 'items' => $inc['recientes'], 'vacio' => 'Ninguna resuelta todavía',
                    'cols' => [['key' => 'titulo', 'label' => 'Alarma', 'class' => 'truncate max-w-[220px]'], ['key' => 'duracion', 'label' => 'Duró', 'align' => 'text-right'],
                        ['key' => 'resuelta', 'label' => 'Resuelta', 'align' => 'text-right']],
                ])
            </div>
            <p class="text-[10px] text-gray-400 mt-3">Historial desde {{ \Carbon\Carbon::parse($inc['registro_desde'])->format('d/m/Y H:i') }} (ventana efectiva: {{ $inc['ventana'] }}). Las alarmas que ya existían al iniciar el registro conservan su hora real de inicio cuando se conoce.</p>
        @endif
    </div>

    {{-- ============================ FIREWALL / WI-FI ============================ --}}
    <div x-show="tab === 'seguridad'" role="tabpanel" style="display: none;">
        @if($seg['firewalls_sin_snmp'] || $seg['wlc_sin_snmp'])
            <div class="mb-4 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 text-xs text-amber-800 dark:text-amber-300">
                <strong>{{ $seg['firewalls_sin_snmp'] }} firewall(s) y {{ $seg['wlc_sin_snmp'] }} WLC en línea no responden SNMP.</strong>
                Sin SNMP no hay datos de sesiones, túneles VPN, SD-WAN, HA, sensores, IPS ni clientes Wi-Fi. Habilitar SNMP v2c (comunidad configurada en el equipo) y permitir consultas desde este servidor.
            </div>
        @endif
        @php
            $sinDatoForti = 'Sin datos de FORTINET-FORTIGATE-MIB';
            $mbps = fn ($v) => $v === null ? '—' : ($v >= 1000 ? number_format($v / 1000, 2) . ' Gbps' : number_format($v, 2) . ' Mbps');
            $ms = fn ($v) => $v === null ? '—' : number_format($v, 1) . ' ms';
            // HA: una fila por miembro (o una por equipo standalone)
            $haFilas = collect($seg['ha'])->flatMap(fn ($h) => empty($h['miembros'])
                ? [['dispositivo' => $h['dispositivo'], 'disp_id' => $h['disp_id'], 'modo' => $h['modo'], 'miembro' => '—', 'sincronizado' => null, 'cpu' => null, 'ram' => null, 'sesiones' => null]]
                : collect($h['miembros'])->map(fn ($m) => ['dispositivo' => $h['dispositivo'], 'disp_id' => $h['disp_id'], 'modo' => $h['modo'], 'miembro' => $m['nombre']] + $m))->all();
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2 mb-4">
            @include('dashboard.partials._stat', ['label' => 'Sesiones firewall', 'valor' => $seg['sesiones_total'] === null ? 'Sin dato' : $n($seg['sesiones_total'])])
            @include('dashboard.partials._stat', ['label' => 'Sesiones nuevas/s', 'valor' => $seg['sesiones_nuevas_seg'] === null ? 'Sin dato' : $n($seg['sesiones_nuevas_seg']), 'title' => 'Promedio del último minuto (fgSysSesRate1)'])
            @include('dashboard.partials._stat', ['label' => 'Túneles IPsec arriba', 'valor' => $seg['vpn_total'] ? $seg['vpn_up'] . ' / ' . $seg['vpn_total'] : 'Sin dato', 'tono' => $seg['vpn_total'] ? ($seg['vpn_up'] < $seg['vpn_total'] ? 'crit' : 'ok') : null])
            @include('dashboard.partials._stat', ['label' => 'Usuarios VPN SSL', 'valor' => $seg['vpn_ssl_usuarios'] === null ? 'Sin dato' : $n($seg['vpn_ssl_usuarios'])])
            @include('dashboard.partials._stat', ['label' => 'Enlaces SD-WAN caídos', 'valor' => $seg['sdwan_total'] ? $seg['sdwan_caidos'] . ' / ' . $seg['sdwan_total'] : 'Sin dato', 'tono' => $seg['sdwan_total'] ? ($seg['sdwan_caidos'] ? 'crit' : 'ok') : null])
            @include('dashboard.partials._stat', ['label' => 'Sensores en alarma', 'valor' => $seg['sensores_total'] ? count($seg['sensores_alarma']) . ' / ' . $seg['sensores_total'] : 'Sin dato', 'tono' => $seg['sensores_total'] ? (count($seg['sensores_alarma']) ? 'crit' : 'ok') : null])
            @include('dashboard.partials._stat', ['label' => 'IPS (último ciclo)', 'valor' => $seg['ips_ciclo'] === null ? 'Sin dato' : $n($seg['ips_ciclo']), 'tono' => $seg['ips_criticas_ciclo'] ? 'warn' : null, 'title' => ($seg['ips_criticas_ciclo'] ?? 0) . ' críticas en el último ciclo del colector (5 min)'])
            @include('dashboard.partials._stat', ['label' => 'Virus (último ciclo)', 'valor' => $seg['av_ciclo'] === null ? 'Sin dato' : $n($seg['av_ciclo'])])
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('dashboard.partials._lista', [
                'titulo' => 'SLA de SD-WAN por enlace', 'items' => $seg['sdwan'], 'vacio' => $sinDatoForti,
                'cols' => [$colDisp, ['label' => 'Health-check', 'fmt' => fn ($r) => $r['nombre'] . (!empty($r['interfaz']) ? ' · ' . $r['interfaz'] : '')],
                    ['label' => 'Estado', 'fmt' => fn ($r) => ($r['estado'] ?? null) === 'dead' ? 'CAÍDO' : (($r['estado'] ?? null) === 'alive' ? 'OK' : '—'), 'class' => 'font-bold'],
                    ['label' => 'Latencia', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $ms($r['latencia_ms'] ?? null)],
                    ['label' => 'Jitter', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $ms($r['jitter_ms'] ?? null)],
                    ['label' => 'Pérdida', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => ($r['perdida_pct'] ?? null) === null ? '—' : $r['perdida_pct'] . '%']],
                'nota' => 'Health-checks configurados en el FortiGate. Alarma si cae, o si pérdida ≥ ' . \App\Services\AlarmDiagnosisService::SDWAN_PERDIDA_PCT
                    . '%, latencia ≥ ' . \App\Services\AlarmDiagnosisService::SDWAN_LATENCIA_MS . ' ms o jitter ≥ ' . \App\Services\AlarmDiagnosisService::SDWAN_JITTER_MS . ' ms.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Túneles IPsec caídos', 'items' => $seg['vpn_caidos'], 'vacio' => $seg['vpn_total'] ? 'Todos los túneles arriba' : $sinDatoForti,
                'cols' => [$colDisp, ['key' => 'fase1', 'label' => 'Túnel'], ['key' => 'remoto', 'label' => 'Gateway remoto', 'class' => 'font-mono']],
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Túneles IPsec con más tráfico', 'items' => $seg['tuneles_trafico'], 'vacio' => $seg['vpn_total'] ? 'Requiere dos ciclos del colector (5 min)' : $sinDatoForti,
                'cols' => [$colDisp, ['key' => 'fase1', 'label' => 'Túnel'],
                    ['label' => '↓ Entrada', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $mbps($r['mbps_in'] ?? null)],
                    ['label' => '↑ Salida', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $mbps($r['mbps_out'] ?? null)]],
                'nota' => 'Promedio entre los dos últimos ciclos del colector (5 min).',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Clúster HA', 'items' => $haFilas, 'vacio' => 'Sin datos de alta disponibilidad',
                'cols' => [$colDisp, ['key' => 'modo', 'label' => 'Modo'], ['key' => 'miembro', 'label' => 'Miembro'],
                    ['label' => 'Sync', 'fmt' => fn ($r) => $r['sincronizado'] === null ? '—' : ($r['sincronizado'] ? '✓' : '✗ NO'), 'class' => 'font-bold'],
                    ['label' => 'CPU', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $r['cpu'] === null ? '—' : $r['cpu'] . '%'],
                    ['label' => 'RAM', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $r['ram'] === null ? '—' : $r['ram'] . '%'],
                    ['label' => 'Sesiones', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['sesiones'])]],
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Sensores de hardware en alarma', 'items' => $seg['sensores_alarma'], 'vacio' => $seg['sensores_total'] ? 'Todos los sensores en rango' : $sinDatoForti,
                'badgeClass' => count($seg['sensores_alarma']) ? 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-400' : null,
                'badge' => $seg['sensores_total'] ? count($seg['sensores_alarma']) . ' en alarma' : null,
                'cols' => [$colDisp, ['key' => 'nombre', 'label' => 'Sensor'], ['key' => 'tipo', 'label' => 'Tipo'],
                    ['label' => 'Valor', 'align' => 'text-right', 'class' => 'font-mono font-bold text-red-600 dark:text-red-400', 'fmt' => fn ($r) => $r['valor'] ?? '—']],
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Firewalls más calientes', 'items' => $seg['temp_max_firewalls'], 'vacio' => $sinDatoForti,
                'cols' => [$colDisp, ['key' => 'sensor', 'label' => 'Sensor'], ['label' => 'Temp.', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['temp'] . ' °C']],
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Disco y sesiones nuevas', 'items' => $seg['sistemas'], 'vacio' => $sinDatoForti,
                'cols' => [$colDisp,
                    ['label' => 'Disco', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => ($r['disco_pct'] ?? null) === null ? '—' : $r['disco_pct'] . '%'],
                    ['label' => 'Usado / total', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => ($r['disco_usado_mb'] ?? null) === null ? '—' : $n($r['disco_usado_mb']) . ' / ' . $n($r['disco_total_mb'] ?? null) . ' MB'],
                    ['label' => 'Nuevas/s', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['sesiones_nuevas_seg'] ?? null)]],
                'nota' => 'Alarma con disco ≥ 90% (el equipo deja de guardar logs al llenarse).',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'VPN SSL', 'items' => $seg['vpn_ssl'], 'vacio' => $sinDatoForti,
                'cols' => [$colDisp,
                    ['label' => 'Usuarios', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $n($r['usuarios'] ?? null)],
                    ['label' => 'Máximo', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['max_usuarios'] ?? null)],
                    ['label' => 'Túneles', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['tuneles'] ?? null)]],
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'IPS y antivirus (último ciclo)', 'items' => $seg['ips'], 'vacio' => $sinDatoForti,
                'cols' => [$colDisp,
                    ['label' => 'IPS', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $n($r['ips_detectadas_ciclo'] ?? null)],
                    ['label' => 'Bloq.', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['ips_bloqueadas_ciclo'] ?? null)],
                    ['label' => 'Críticas', 'align' => 'text-right', 'class' => 'font-mono text-red-600 dark:text-red-400', 'fmt' => fn ($r) => $n($r['ips_criticas_ciclo'] ?? null)],
                    ['label' => 'Virus', 'align' => 'text-right', 'class' => 'font-mono', 'fmt' => fn ($r) => $n($r['av_detectados_ciclo'] ?? null)]],
                'nota' => 'Detecciones desde el ciclo anterior del colector (5 min). El primer ciclo solo registra la base.',
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'Sesiones concurrentes', 'items' => $seg['sesiones'], 'vacio' => 'Ningún firewall reporta sesiones',
                'cols' => [$colDisp, ['label' => 'Sesiones', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $n($r['sesiones'])]],
            ])
            @include('dashboard.partials._lista', [
                'titulo' => 'APs con más clientes (WLC)', 'items' => $seg['wifi_top'], 'vacio' => 'Sin datos del WLC',
                'cols' => [['key' => 'ap', 'label' => 'AP'], ['label' => 'Clientes', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $r['clientes'] ?? '—']],
            ])
        </div>
    </div>

    {{-- ============================ EQUIPOS FINALES ============================ --}}
    <div x-show="tab === 'endpoints'" role="tabpanel" style="display: none;">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
            @include('dashboard.partials._stat', ['label' => 'MACs aprendidas únicas', 'valor' => $n($ep['macs_unicas'])])
            @include('dashboard.partials._stat', ['label' => 'IPs en tablas ARP', 'valor' => $n($ep['ips_arp'])])
            @include('dashboard.partials._stat', ['label' => 'Switches con tabla MAC', 'valor' => $ep['switches']])
        </div>
        @include('dashboard.partials._lista', [
            'titulo' => 'Switches con más MACs aprendidas', 'items' => $ep['top'], 'vacio' => 'Sin tablas MAC sincronizadas',
            'cols' => [$colDisp, ['label' => 'MACs', 'align' => 'text-right', 'class' => 'font-mono font-bold', 'fmt' => fn ($r) => $n($r['macs'])]],
            'nota' => 'Datos de la última sincronización L2 (php artisan l2:sync): ' . ($ep['actualizado'] ? \Carbon\Carbon::parse($ep['actualizado'])->format('d/m/Y H:i') . ' (' . \Carbon\Carbon::parse($ep['actualizado'])->diffForHumans() . ')' : 'nunca') . '. No se ejecuta de forma automática.',
        ])
    </div>

    {{-- ============================ COBERTURA ============================ --}}
    <div x-show="tab === 'cobertura'" role="tabpanel" style="display: none;">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
            @include('dashboard.partials._stat', ['label' => 'Equipos registrados', 'valor' => $cob['total']])
            @include('dashboard.partials._stat', ['label' => 'Con SNMP', 'valor' => $cob['snmp'], 'tono' => 'ok'])
            @include('dashboard.partials._stat', ['label' => 'Solo ICMP', 'valor' => $cob['solo_icmp'], 'tono' => $cob['solo_icmp'] ? 'warn' : 'ok', 'title' => 'Responden ping pero no SNMP: sin CPU, RAM, puertos ni hardware'])
            @include('dashboard.partials._stat', ['label' => 'Sin respuesta', 'valor' => $cob['offline'], 'tono' => $cob['offline'] ? 'crit' : 'ok'])
        </div>
        @include('dashboard.partials._lista', [
            'titulo' => 'Procesos de recolección', 'items' => $cob['workers'],
            'cols' => [['key' => 'nombre', 'label' => 'Proceso'], ['key' => 'edad', 'label' => 'Última ejecución'],
                ['label' => 'Estado', 'align' => 'text-right', 'fmt' => fn ($r) => $r['ok'] ? 'OK' : 'DETENIDO / ATRASADO']],
            'nota' => 'Si un proceso aparece atrasado, revisar su ventana en iniciar_monitor.bat. Sus métricas dejan de actualizarse.',
        ])
    </div>
</div>
