{{-- Una tarjeta por sector: semáforo (lo deciden sus alarmas activas), la cifra principal y 4 lecturas. Solo datos medidos. --}}
@php
    $num = fn ($v, string $suf = '') => $v === null ? 'Sin dato' : number_format($v) . $suf;
    $mbps = fn ($v) => $v === null ? 'Sin dato' : ($v >= 1000 ? number_format($v / 1000, 2) . ' Gbps' : number_format($v, 1) . ' Mbps');
    $pct = fn ($v) => $v === null ? 'Sin dato' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%';
    $s = $sectores;

    // [clave => [escena, icono, cifra principal, texto de la cifra, lecturas [[etiqueta, valor, tono], ...], nota]]
    $tarjetas = [
        'red' => ['red', '<rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3M12 12V8"/>',
            $s['red']['en_linea'] . ' / ' . $s['red']['total'], 'equipos en línea', [
                ['Sin respuesta', $s['red']['caidos'], $s['red']['caidos'] ? 'crit' : 'ok'],
                ['Tráfico (Σ puertos)', $mbps($s['red']['trafico_mbps']), null, 'Suma de entrada + salida de todos los puertos físicos: cada enlace cuenta en sus dos extremos'],
                ['Latencia prom.', $s['red']['latencia_ms'] === null ? 'Sin dato' : $s['red']['latencia_ms'] . ' ms', null],
                ['Disponibilidad 24 h', $pct($s['red']['disponibilidad_24h']), $s['red']['disponibilidad_24h'] === null ? null : ($s['red']['disponibilidad_24h'] >= 99.9 ? 'ok' : ($s['red']['disponibilidad_24h'] >= 99 ? 'warn' : 'crit'))],
            ], null],
        'firewalls' => ['escudo', '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            $s['firewalls']['en_linea'] . ' / ' . $s['firewalls']['total'], 'FortiGate en línea', [
                ['Sesiones activas', $num($s['firewalls']['sesiones']), null],
                ['CPU máxima', $num($s['firewalls']['cpu_max'], '%'), $s['firewalls']['cpu_max'] === null ? null : ($s['firewalls']['cpu_max'] >= 90 ? 'crit' : ($s['firewalls']['cpu_max'] >= 80 ? 'warn' : 'ok'))],
                ['Túneles IPsec', $s['firewalls']['tuneles_total'] ? $s['firewalls']['tuneles_arriba'] . ' / ' . $s['firewalls']['tuneles_total'] . ' arriba' : 'Sin dato',
                    $s['firewalls']['tuneles_total'] ? ($s['firewalls']['tuneles_arriba'] < $s['firewalls']['tuneles_total'] ? 'crit' : 'ok') : null],
                ['Con SNMP', $s['firewalls']['con_snmp'] . ' de ' . $s['firewalls']['total'], null],
            ], $s['firewalls']['con_snmp'] < $s['firewalls']['en_linea'] ? 'Sesiones, CPU y túneles solo de los que responden SNMP' : null],
        'virtualizacion' => ['servidores', '<rect width="20" height="8" x="2" y="2" rx="2"/><rect width="20" height="8" x="2" y="14" rx="2"/><path d="M6 6h.01M6 18h.01"/>',
            $s['virtualizacion']['en_linea'] . ' / ' . $s['virtualizacion']['hosts'], 'hosts ESXi en línea', [
                ['CPU promedio', $num($s['virtualizacion']['cpu_prom'], '%'), $s['virtualizacion']['cpu_prom'] === null ? null : ($s['virtualizacion']['cpu_prom'] >= 90 ? 'crit' : ($s['virtualizacion']['cpu_prom'] >= 80 ? 'warn' : 'ok'))],
                ['Memoria promedio', $num($s['virtualizacion']['ram_prom'], '%'), $s['virtualizacion']['ram_prom'] === null ? null : ($s['virtualizacion']['ram_prom'] >= 95 ? 'crit' : ($s['virtualizacion']['ram_prom'] >= 85 ? 'warn' : 'ok'))],
                ['VMs encendidas', $s['virtualizacion']['vms_total'] === null ? 'Sin dato' : $s['virtualizacion']['vms_encendidas'] . ' / ' . $s['virtualizacion']['vms_total'], null],
                ['Datastore más lleno', $s['virtualizacion']['datastore_max'] ? $pct($s['virtualizacion']['datastore_max']['pct']) : 'Sin dato',
                    $s['virtualizacion']['datastore_max'] ? ($s['virtualizacion']['datastore_max']['pct'] >= 90 ? 'crit' : ($s['virtualizacion']['datastore_max']['pct'] >= 80 ? 'warn' : 'ok')) : null],
            ], $s['virtualizacion']['leidos'] < $s['virtualizacion']['en_linea'] ? 'API leída en ' . $s['virtualizacion']['leidos'] . ' de ' . $s['virtualizacion']['en_linea'] . ' hosts' : null],
        'web' => ['nube', '<circle cx="12" cy="12" r="9.5"/><path d="M12 2.5a14 14 0 0 0 0 19 14 14 0 0 0 0-19M2.5 12h19"/>',
            $s['web']['en_linea'] . ' / ' . $s['web']['total'], 'sitios en línea', [
                ['Caídos', $s['web']['caidos'], $s['web']['caidos'] ? 'crit' : 'ok'],
                ['Lentos', $s['web']['lentos'], $s['web']['lentos'] ? 'warn' : 'ok'],
                ['Respuesta prom.', $s['web']['respuesta_ms'] === null ? 'Sin dato' : number_format($s['web']['respuesta_ms']) . ' ms', null],
                ['SSL más próximo', $s['web']['ssl_proximo'] === null ? 'Sin dato' : ($s['web']['ssl_proximo'] < 0 ? 'Vencido' : $s['web']['ssl_proximo'] . ' días'),
                    $s['web']['ssl_proximo'] === null ? null : ($s['web']['ssl_proximo'] <= 7 ? 'crit' : ($s['web']['ssl_proximo'] <= 30 ? 'warn' : 'ok'))],
            ], null],
    ];
    $tonoTexto = ['ok' => 'text-emerald-600 dark:text-emerald-400', 'warn' => 'text-amber-600 dark:text-amber-400', 'crit' => 'text-red-600 dark:text-red-400'];
    $semaforo = [
        'ok' => ['bg-emerald-500', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400'],
        'warn' => ['bg-amber-500', 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400'],
        'crit' => ['bg-red-500 animate-pulse', 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-400'],
        'sin_dato' => ['bg-slate-400', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
    ];
@endphp
<div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-4 mb-6">
    @foreach($tarjetas as $k => [$escena, $icono, $cifra, $cifraTexto, $lecturas, $nota])
        @php
            $sec = $s[$k];
            $meta = \App\Services\ResumenSectores::SECTORES[$k];
            [$punto, $pastilla] = $semaforo[$sec['tono']];
            $estado = match ($sec['tono']) {
                'crit' => $sec['criticas'] . ($sec['criticas'] === 1 ? ' crítica' : ' críticas') . ($sec['advertencias'] ? ' · ' . $sec['advertencias'] . ' adv.' : ''),
                'warn' => $sec['advertencias'] . ($sec['advertencias'] === 1 ? ' advertencia' : ' advertencias'),
                'ok' => 'Sin alarmas',
                default => 'Sin equipos',
            };
        @endphp
        <a href="{{ route($meta['ruta']) }}" class="floating-card relative overflow-hidden p-5 flex flex-col gap-4 group hover:-translate-y-0.5 transition">
            <x-kpi-escena :tipo="$escena" :tono="$sec['tono'] === 'crit' ? 'crit' : null" />
            <div class="relative flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center text-white shrink-0" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                        <svg class="w-[18px] h-[18px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $icono !!}</svg>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-slate-200">{{ $meta['nombre'] }}</h3>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[11px] font-bold whitespace-nowrap {{ $pastilla }}" title="Alarmas activas del sector">
                    <span class="w-2 h-2 rounded-full {{ $punto }}"></span>{{ $estado }}
                </span>
            </div>

            <div class="relative">
                <p class="text-3xl font-extrabold tracking-tight leading-none text-gray-900 dark:text-white font-mono">{{ $cifra }}</p>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">{{ $cifraTexto }}</p>
            </div>

            <dl class="relative grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
                @foreach($lecturas as $l)
                    @php [$etiqueta, $valor, $tono] = $l; @endphp
                    <div class="min-w-0" @isset($l[3]) title="{{ $l[3] }}" @endisset>
                        <dt class="text-[11px] text-gray-400 dark:text-slate-500 truncate">{{ $etiqueta }}</dt>
                        <dd class="font-bold font-mono truncate {{ $tonoTexto[$tono] ?? 'text-gray-800 dark:text-slate-200' }}">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="relative mt-auto pt-3 border-t border-gray-100 dark:border-slate-800/60 flex items-center justify-between gap-2 text-[11px]">
                <span class="text-gray-400 dark:text-slate-500 truncate">{{ $nota }}</span>
                <span class="font-semibold text-hacienda-blue dark:text-blue-400 whitespace-nowrap group-hover:underline">Ver {{ mb_strtolower($meta['nombre']) }} →</span>
            </div>
        </a>
    @endforeach
</div>
