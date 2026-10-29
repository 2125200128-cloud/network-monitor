{{-- Franja de salud operativa: tarjetas KPI con mini gráfica y acceso al diagnóstico --}}
@php
    $inc = $paneles['incidentes'];
    $disp = $paneles['disponibilidad'];
    $sla = $disp['global'];
    $cob = $paneles['cobertura'];
    $web = $paneles['servicios_web'];
    $reinicios = count($paneles['hardware']['reinicios_24h']);
    $pct = fn ($v) => $v === null ? 'Sin dato' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%';
    $sla24 = $sla['24h']['pct'] ?? null;
    $sla7 = $sla['7d']['pct'] ?? null;
    $sslDias = $web['ssl_proximo'] ?? null;
    $workersOk = collect($cob['workers'])->where('ok', true)->count();
    $azul = '59, 89, 152';

    // ---- Mini gráficas SVG (solo números; los textos de <title> se escapan) ----
    // Barras con degradado de opacidad, la última más intensa
    // $espacios: ancho mínimo en barras, para que 1-2 valores no se dibujen como un bloque (se alinean a la derecha)
    $barras = function (array $vals, array $titulos, string $rgb, int $espacios = 0) {
        $n = count($vals);
        if ($n === 0) return '<span class="text-[11px] text-gray-400">Sin datos</span>';
        $slots = max($n, $espacios);
        $max = max(1, max($vals));
        $gap = 2;
        $w = (96 - $gap * ($slots - 1)) / $slots;
        $svg = '<svg viewBox="0 0 96 48" class="w-24 h-12" preserveAspectRatio="none">';
        foreach (array_values($vals) as $i => $v) {
            $h = max(3, $v / $max * 46);
            $op = $n > 1 ? 0.3 + 0.7 * $i / ($n - 1) : 1;
            $svg .= sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="1.5" fill="rgba(%s,%.2f)"><title>%s</title></rect>',
                ($slots - $n + $i) * ($w + $gap), 48 - $h, $w, $h, $rgb, $op, e($titulos[$i] ?? ''));
        }
        return $svg . '</svg>';
    };
    // Área con línea, escalada al rango propio de la serie (con margen) para que las variaciones se vean
    $area = function (array $vals, string $rgb) {
        $n = count($vals);
        if ($n < 2) return '<span class="text-[11px] text-gray-400">Sin serie</span>';
        $margen = max(0.5, (max($vals) - min($vals)) * 0.2);
        $min = min($vals) - $margen;
        $rango = max($vals) + $margen - $min;
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = sprintf('%.2f,%.2f', $i / ($n - 1) * 96, 46 - ($v - $min) / $rango * 42);
        }
        $linea = implode(' ', $pts);
        return '<svg viewBox="0 0 96 48" class="w-24 h-12" preserveAspectRatio="none">'
            . '<defs><linearGradient id="kpiArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="rgba(' . $rgb . ',0.35)"/><stop offset="1" stop-color="rgba(' . $rgb . ',0)"/></linearGradient></defs>'
            . '<polygon points="0,48 ' . $linea . ' 96,48" fill="url(#kpiArea)"/>'
            . '<polyline points="' . $linea . '" fill="none" stroke="rgb(' . $rgb . ')" stroke-width="1.8" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
    };
    // Dona por segmentos [[valor, color, título], ...]
    $dona = function (array $segmentos) {
        $total = array_sum(array_column($segmentos, 0));
        $svg = '<svg viewBox="0 0 36 36" class="w-12 h-12 -rotate-90"><circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" class="text-gray-100 dark:text-slate-800" stroke-width="5"/>';
        $acum = 0;
        foreach ($total > 0 ? $segmentos : [] as [$v, $color, $titulo]) {
            $p = $v / $total * 100;
            if ($p <= 0) continue;
            $svg .= sprintf('<circle cx="18" cy="18" r="15.915" fill="none" stroke="%s" stroke-width="5" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f"><title>%s</title></circle>',
                $color, $p, 100 - $p, -$acum, e($titulo));
            $acum += $p;
        }
        return $svg . '</svg>';
    };

    $porHora = $inc['nuevas_por_hora'] ?? [];
    $slaHora = $disp['por_hora'] ?? [];
    $resueltas = array_reverse($inc['recientes'] ?? []);
    $sslTop = array_slice($web['ssl_por_vencer'] ?? [], 0, 5);
    $deltaSla = $sla24 !== null && $sla7 !== null ? round($sla24 - $sla7, 1) : null;
    $estables = max(0, $cob['snmp'] - $reinicios);
@endphp
<div class="mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <h3 class="text-base font-bold text-gray-900 dark:text-white tracking-tight">Salud operativa</h3>
        <div class="flex items-center gap-2">
            {{-- Estado de los procesos de recolección, plegado en un menú --}}
            <div class="relative" x-data="{ abierto: false }" @click.outside="abierto = false">
                <button type="button" @click="abierto = !abierto"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold border bg-white dark:bg-nm-surface border-gray-200 dark:border-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800/60 transition">
                    <span class="w-2 h-2 rounded-full {{ $workersOk === count($cob['workers']) ? 'bg-emerald-500' : 'bg-red-500 animate-pulse' }}"></span>
                    Recolección {{ $workersOk }}/{{ count($cob['workers']) }} al día
                    <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                </button>
                <div x-show="abierto" x-transition style="display: none;"
                     class="absolute right-0 mt-2 w-80 z-30 rounded-xl nm-glass bg-white dark:bg-nm-surface border border-gray-100 dark:border-slate-800 shadow-xl p-2">
                    @foreach($cob['workers'] as $w)
                        <div class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg text-xs">
                            <span class="flex items-center gap-2 text-gray-700 dark:text-slate-300">
                                <span class="w-1.5 h-1.5 rounded-full {{ $w['ok'] ? 'bg-emerald-500' : 'bg-red-500' }}"></span>{{ $w['nombre'] }}
                            </span>
                            <span class="font-mono text-[11px] {{ $w['ok'] ? 'text-gray-400' : 'text-red-600 dark:text-red-400 font-bold' }}">{{ $w['edad'] }}</span>
                        </div>
                    @endforeach
                    <p class="px-2 pt-1.5 mt-1 border-t border-gray-100 dark:border-slate-800 text-[11px] text-gray-400">Un proceso atrasado deja de actualizar sus métricas: revisar su ventana en iniciar_monitor.bat.</p>
                </div>
            </div>
            <button type="button" onclick="abrirDiagnostico('incidentes')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border bg-white dark:bg-nm-surface border-gray-200 dark:border-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800/60 transition">
                <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <polyline points="12 6 12 12 16 14" /></svg>
                Actividad reciente
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <x-kpi-card titulo="Alarmas críticas" :valor="$inc['criticas']"
                    :valor-tono="$inc['criticas'] > 0 ? 'crit' : 'ok'"
                    :comparacion="$inc['advertencias'] . ' advertencias'"
                    :badge="'+' . ($inc['nuevas_24h'] ?? 0) . ' en 24 h'" :badge-tono="($inc['nuevas_24h'] ?? 0) > 0 ? 'crit' : 'ok'"
                    detalle="incidentes" ayuda="Barras: alarmas nuevas por hora (últimas 12 h)"
                    escena="servidores" :escena-tono="$inc['criticas'] > 0 ? 'crit' : null">
            {!! $barras(array_column($porHora, 'n'), array_map(fn ($h) => $h['hora'] . ': ' . $h['n'] . ' nuevas', $porHora), '239, 68, 68') !!}
        </x-kpi-card>

        <x-kpi-card titulo="Disponibilidad 24 h" :valor="$pct($sla24)"
                    :valor-tono="$sla24 === null ? null : ($sla24 >= 99.9 ? 'ok' : ($sla24 >= 99 ? 'warn' : 'crit'))"
                    :comparacion="'vs 7 días (' . $pct($sla7) . ')'"
                    :badge="$deltaSla === null ? null : ($deltaSla >= 0 ? '↑ ' : '↓ ') . abs($deltaSla) . ' pts'"
                    :badge-tono="$deltaSla === null ? 'neutral' : ($deltaSla >= 0 ? 'ok' : 'crit')"
                    detalle="disponibilidad" ayuda="Muestras en que el equipo respondió ICMP o SNMP / muestras totales. Línea: disponibilidad global por hora."
                    escena="nube">
            {!! $area(array_column($slaHora, 'pct'), $azul) !!}
        </x-kpi-card>

        <x-kpi-card titulo="Tiempo medio de reparación (MTTR)" :valor="$inc['mttr'] ?? 'Sin dato'"
                    :comparacion="'MTBF ' . ($inc['mtbf'] ?? 'sin caídas')"
                    :badge="($inc['resueltas_7d'] ?? 0) . ' resueltas 7 d'" badge-tono="neutral"
                    detalle="incidentes" ayuda="Barras: duración de las últimas alarmas resueltas"
                    escena="reloj">
            {!! $barras(array_column($resueltas, 'segundos'), array_map(fn ($r) => $r['titulo'] . ': ' . $r['duracion'], $resueltas), $azul, 6) !!}
        </x-kpi-card>

        <x-kpi-card titulo="Cobertura SNMP" :valor="$cob['snmp']" :unidad="'/ ' . $cob['en_linea']"
                    :comparacion="$cob['solo_icmp'] . ' solo ICMP · ' . $cob['offline'] . ' sin respuesta'"
                    :badge="$cob['en_linea'] ? round($cob['snmp'] / $cob['en_linea'] * 100) . '%' : null" badge-tono="neutral"
                    detalle="cobertura" ayuda="Equipos en línea que responden SNMP (telemetría completa)"
                    escena="molecula">
            {!! $dona([[$cob['snmp'], 'rgb(59, 89, 152)', 'Con SNMP: ' . $cob['snmp']], [$cob['solo_icmp'], 'rgb(147, 167, 214)', 'Solo ICMP: ' . $cob['solo_icmp']], [$cob['offline'], 'rgb(239, 68, 68)', 'Sin respuesta: ' . $cob['offline']]]) !!}
        </x-kpi-card>

        <x-kpi-card titulo="Reinicios en 24 h" :valor="$reinicios"
                    :valor-tono="$reinicios ? 'warn' : 'ok'"
                    :comparacion="$reinicios ? collect($paneles['hardware']['reinicios_24h'])->take(1)->map(fn ($r) => $r['dispositivo'] . ' hace ' . $r['hace'])->first() : 'Uptime ≥ 24 h en todos'"
                    :badge="$reinicios ? 'Revisar' : 'Estable'" :badge-tono="$reinicios ? 'warn' : 'ok'"
                    detalle="hardware" ayuda="Equipos SNMP cuyo sysUpTime es menor a 24 h"
                    escena="reinicio">
            {!! $dona([[$estables, 'rgb(16, 185, 129)', 'Uptime ≥ 24 h: ' . $estables], [$reinicios, 'rgb(245, 158, 11)', 'Reiniciados: ' . $reinicios]]) !!}
        </x-kpi-card>

        <x-kpi-card titulo="Certificado SSL más próximo" :valor="$sslDias === null ? 'Sin dato' : $sslDias" :unidad="$sslDias === null ? null : 'días'"
                    :valor-tono="$sslDias === null ? null : ($sslDias <= 7 ? 'crit' : ($sslDias <= 30 ? 'warn' : 'ok'))"
                    :comparacion="($web['ssl_medidos'] ?? 0) . ' certificados medidos'"
                    :badge="count($web['ssl_por_vencer'] ?? []) . ' por vencer'" :badge-tono="count($web['ssl_por_vencer'] ?? []) ? 'warn' : 'ok'"
                    detalle="web" ayuda="Barras: certificados que vencen en ≤ 30 días; más alta = vence antes"
                    escena="escudo">
            {!! $barras(array_map(fn ($c) => max(1, 30 - $c['dias']), $sslTop), array_map(fn ($c) => $c['nombre'] . ': ' . $c['dias'] . ' días', $sslTop), '245, 158, 11', 5) !!}
        </x-kpi-card>
    </div>
</div>
