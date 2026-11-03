{{-- Tendencias históricas de global_metrics con selector de rango y métrica --}}
@php
    $tr = $paneles['trafico'];
    $mbps = fn ($v) => $v === null ? 'Sin dato' : ($v >= 1000 ? number_format($v / 1000, 2) . ' Gbps' : number_format($v, 1) . ' Mbps');
    $etiquetas = ['trafico' => 'Tráfico', 'latencia' => 'Latencia', 'perdida' => 'Pérdida', 'cpu' => 'CPU prom.'];
@endphp
<div class="floating-card p-6 mb-6" id="trendCard">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white tracking-tight">Tendencias por sector</h3>
            <p class="text-xs text-gray-500 dark:text-slate-400">Promedios de las lecturas reales de cada sector. Huecos = sin lecturas en ese periodo.</p>
        </div>
        <div class="flex items-center bg-gray-100/80 dark:bg-nm-sunken p-1 rounded-xl text-xs font-semibold self-start lg:self-auto" role="group" aria-label="Rango">
            @foreach(['1h' => '1 h', '24h' => '24 h', '7d' => '7 d'] as $k => $label)
                <button type="button" data-trend-range="{{ $k }}" class="trend-btn px-2.5 py-1.5 rounded-lg transition {{ $k === '24h' ? 'trend-btn-active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Una gráfica por métrica, todas a la vez; el JS del dashboard las dibuja por data-trend-chart --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach([
            'trafico' => ['Red', 'Tráfico (Σ puertos)'],
            'latencia' => ['Red', 'Latencia y pérdida'],
            'recursos' => ['Red', 'CPU / RAM de los equipos'],
            'firewalls' => ['Firewalls', 'Sesiones activas'],
            'virtualizacion' => ['Virtualización', 'CPU / memoria de los hosts'],
            'web' => ['Servicios web', 'Tiempo de respuesta'],
        ] as $k => [$sector, $titulo])
            <div class="rounded-xl border border-gray-100 dark:border-slate-800/60 p-3">
                <p class="text-xs font-bold text-gray-700 dark:text-slate-200">
                    <span class="text-[10px] uppercase tracking-wider text-gray-400 dark:text-slate-500">{{ $sector }} ·</span> {{ $titulo }}
                </p>
                <div class="relative w-full h-44 mt-1">
                    <canvas data-trend-chart="{{ $k }}"></canvas>
                    <p data-trend-empty="{{ $k }}" class="absolute inset-0 items-center justify-center text-xs text-gray-400" style="display: none;">Sin mediciones en este rango</p>
                </div>
            </div>
        @endforeach
    </div>

    <p class="mt-5 pt-4 border-t border-gray-100 dark:border-slate-800/40 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500">Red · resumen</p>
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mt-2">
        <div title="Percentil 95 del tráfico total (entrada + salida) de las últimas 24 h">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">P95 tráfico 24 h</p>
            <p class="text-sm font-extrabold font-mono text-gray-900 dark:text-white">{{ $mbps($tr['p95_total']) }}</p>
            <p class="text-[11px] text-gray-500">↓ {{ $mbps($tr['p95_in']) }} · ↑ {{ $mbps($tr['p95_out']) }}</p>
        </div>
        <div>
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">Pico 24 h</p>
            <p class="text-sm font-extrabold font-mono text-gray-900 dark:text-white">{{ $mbps($tr['pico']) }}</p>
            <p class="text-[11px] text-gray-500">{{ $tr['muestras_24h'] }} muestras</p>
        </div>
        @foreach($tr['vs_ayer'] as $k => $c)
            @php
                $d = $c['delta_pct'];
                // Para todas estas métricas, subir es peor excepto el tráfico (neutral)
                $color = $d === null || $k === 'trafico' ? 'text-gray-500' : ($d > 10 ? 'text-red-600' : ($d < -10 ? 'text-emerald-600' : 'text-gray-500'));
                $unidad = ['trafico' => '', 'latencia' => ' ms', 'perdida' => '%', 'cpu' => '%'][$k];
                $actual = $c['actual'] === null ? 'Sin dato' : ($k === 'trafico' ? $mbps($c['actual']) : $c['actual'] . $unidad);
            @endphp
            <div title="Promedio de la última hora contra la misma hora de ayer">
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">{{ $etiquetas[$k] }} (1 h)</p>
                <p class="text-sm font-extrabold font-mono text-gray-900 dark:text-white">{{ $actual }}</p>
                <p class="text-[11px] font-semibold {{ $color }}">
                    @if($d === null)
                        Sin dato de ayer para comparar
                    @else
                        {{ $d > 0 ? '▲' : ($d < 0 ? '▼' : '=') }} {{ abs($d) }}% vs ayer
                    @endif
                </p>
            </div>
        @endforeach
    </div>
</div>
