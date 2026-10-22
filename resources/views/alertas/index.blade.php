@extends('layouts.app')

@section('title', 'Alertas')

@section('styles')
<style>
    .al-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 9999px; white-space: nowrap; }
    .al-critica { color: #b91c1c; background: #fee2e2; }
    .al-advertencia { color: #b45309; background: #fef3c7; }
    .dark .al-critica { color: #fca5a5; background: rgb(127 29 29 / 0.45); }
    .dark .al-advertencia { color: #fcd34d; background: rgb(120 53 15 / 0.45); }
    .al-tabla th { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--nm-border); white-space: nowrap; }
    .al-tabla td { padding: 9px 10px; border-bottom: 1px solid var(--nm-border); font-size: 13px; color: var(--nm-text); vertical-align: top; }
    .al-tabla tr.al-fila:hover td { background: rgb(var(--nm-primary-rgb) / 0.04); }
    .al-campo { height: 2.25rem; padding: 0 0.7rem; border-radius: var(--nm-input-radius); border: 1px solid var(--nm-input-border); background: var(--nm-surface); color: var(--nm-text); font-size: 13px; }
    .al-kpi { padding: 12px 14px; border-radius: var(--nm-radius-lg); background: var(--nm-surface); border: 1px solid var(--nm-border); }
    .al-kpi span { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .al-kpi b { display: block; font-size: 22px; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 2px; }
    .al-accion { font-size: 12px; font-weight: 700; color: var(--nm-primary); white-space: nowrap; }
    .al-accion:hover { text-decoration: underline; }
</style>
@endsection

@section('content')
<div class="flex flex-col space-y-4 pb-6">

    {{-- Encabezado y pestañas --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-lg font-black" style="color: var(--nm-text)">Alertas</h1>
            <nav class="inline-flex gap-[2px] p-[2px] rounded-[10px]" style="background: var(--nm-sunken); border: 1px solid var(--nm-border)" aria-label="Vistas de alertas">
                @foreach(['activas' => 'Activas', 'historial' => 'Historial'] as $clave => $texto)
                    <a href="{{ route('alertas.index', ['pestana' => $clave]) }}" @if($pestana === $clave) aria-current="page" @endif
                       class="inline-flex items-center h-8 px-4 rounded-lg text-[13px] font-semibold transition"
                       style="{{ $pestana === $clave ? 'color:#fff; background: var(--nm-metal-sheen), var(--nm-metal-azure);' : 'color: var(--nm-text-muted);' }}">{{ $texto }}</a>
                @endforeach
            </nav>
        </div>
        <p class="text-xs" style="color: var(--nm-text-muted)">
            @if($pestana === 'activas')
                Incidencias que siguen ocurriendo ahora. Se actualiza cada 30 s; desaparecen solas al resolverse.
            @else
                Todo lo registrado por noc:sync desde {{ \Carbon\Carbon::parse(\Illuminate\Support\Facades\DB::table('alarmas_red')->min('fecha_inicio'))->format('d/m/Y') }}.
            @endif
        </p>
    </div>

@if($pestana === 'activas')
    {{-- ==================== ACTIVAS ==================== --}}
    {{-- Datos que el refresco automático actualiza; la tabla los relee --}}
    @php $jsonActivas = json_encode($activas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); @endphp
    <script type="application/json" id="alertasActivasJson" data-refrescar="alertas-activas" data-refrescar-cada="5">{!! $jsonActivas !!}</script>

    <div x-data="alertasActivas()" class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="al-kpi"><span>Activas</span><b x-text="alarmas.length"></b></div>
            <div class="al-kpi"><span>Críticas</span><b style="color:#dc2626" x-text="alarmas.filter(a => a.severidad === 'critica').length"></b></div>
            <div class="al-kpi"><span>Advertencias</span><b style="color:#d97706" x-text="alarmas.filter(a => a.severidad !== 'critica').length"></b></div>
            <div class="al-kpi"><span>Equipos afectados</span><b x-text="new Set(alarmas.filter(a => a.equipo).map(a => a.equipo)).size"></b></div>
        </div>

        {{-- Caídas en cascada: el diagnóstico detectó que sus vecinos tampoco responden (falla aguas arriba) --}}
        <template x-if="alarmas.some(a => a.cascada)">
            <div class="floating-card px-4 py-3 flex flex-wrap items-center justify-between gap-2" style="border-left: 4px solid #dc2626">
                <p class="text-sm" style="color: var(--nm-text)">
                    <b x-text="alarmas.filter(a => a.cascada).length"></b> equipos sin respuesta por una <b>falla aguas arriba</b>
                    (sus vecinos tampoco responden). Atender primero el equipo del que dependen resuelve varias a la vez.
                </p>
                <button type="button" @click="soloCascada = !soloCascada" class="al-accion" x-text="soloCascada ? 'Ver todas' : 'Ver solo estas'"></button>
            </div>
        </template>

        <div class="floating-card p-4">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <input type="search" x-model="buscar" placeholder="Buscar equipo, IP o alerta…" class="al-campo flex-1 min-w-[14rem]" aria-label="Buscar">
                <select x-model="severidad" class="al-campo" aria-label="Severidad">
                    <option value="">Todas las severidades</option>
                    <option value="critica">Críticas</option>
                    <option value="advertencia">Advertencias</option>
                </select>
                <select x-model="categoria" class="al-campo" aria-label="Tipo">
                    <option value="">Todos los tipos</option>
                    @foreach($categorias as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
                <select x-model="agrupar" class="al-campo" aria-label="Agrupar">
                    <option value="">Sin agrupar</option>
                    <option value="equipo">Agrupar por equipo</option>
                    <option value="titulo">Agrupar por tipo de falla</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="al-tabla w-full">
                    <thead><tr>
                        @foreach(['severidad' => 'Severidad', 'titulo' => 'Alerta', 'equipo' => 'Equipo', 'inicio_ts' => 'Desde', 'duracion' => 'Duración'] as $col => $txt)
                            <th :aria-sort="orden === '{{ $col }}' ? (desc ? 'descending' : 'ascending') : 'none'">
                                <button type="button" @click="ordenar('{{ $col }}')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:underline"
                                        title="Ordenar por {{ mb_strtolower($txt) }}">
                                    {{ $txt }}
                                    <span class="font-mono" x-text="orden === '{{ $col }}' ? (desc ? '↓' : '↑') : ''"></span>
                                </button>
                            </th>
                        @endforeach
                        <th class="text-right">Acciones</th>
                    </tr></thead>
                    <template x-for="grupo in grupos" :key="grupo.clave">
                        <tbody>
                            <tr x-show="agrupar">
                                <td colspan="6" class="font-black" style="background: var(--nm-sunken)">
                                    <span x-text="grupo.titulo"></span>
                                    <span class="ml-2 text-xs font-semibold" style="color: var(--nm-text-muted)" x-text="grupo.items.length + (grupo.items.length === 1 ? ' alerta' : ' alertas')"></span>
                                </td>
                            </tr>
                            <template x-for="a in grupo.items" :key="a.id">
                                <tr class="al-fila cursor-pointer" @click="abierta = abierta === a.id ? null : a.id">
                                    <td><span class="al-pill" :class="a.severidad === 'critica' ? 'al-critica' : 'al-advertencia'" x-text="a.severidad === 'critica' ? 'Crítica' : 'Advertencia'"></span></td>
                                    <td>
                                        <div class="font-bold" x-text="a.titulo"></div>
                                        <div class="text-xs mt-0.5" style="color: var(--nm-text-muted)" x-text="a.mensaje"></div>
                                        <div x-show="abierta === a.id" x-cloak class="mt-2 text-xs space-y-1.5 p-3 rounded-lg" style="background: var(--nm-sunken)">
                                            <p x-show="a.causa"><b>Causa probable:</b> <span x-text="a.causa"></span></p>
                                            <p x-show="a.impacto"><b>Impacto:</b> <span x-text="a.impacto"></span></p>
                                            <p x-show="a.accion" style="color:#047857"><b>Acción sugerida:</b> <span x-text="a.accion"></span></p>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <div class="font-semibold" x-text="a.equipo || '—'"></div>
                                        <div class="text-xs font-mono" style="color: var(--nm-text-muted)" x-text="a.ip"></div>
                                    </td>
                                    <td class="whitespace-nowrap font-mono text-xs" x-text="a.inicio || '—'"></td>
                                    <td class="whitespace-nowrap font-mono text-xs" x-text="a.duracion"></td>
                                    <td class="text-right whitespace-nowrap space-x-3" @click.stop>
                                        <template x-if="a.dispositivo_id">
                                            <span class="space-x-3">
                                                <a class="al-accion" :href="'{{ url('/dispositivos') }}/' + a.dispositivo_id">Ficha</a>
                                                <a class="al-accion" :href="'{{ route('topologia.index') }}?foco=' + a.dispositivo_id">Topología</a>
                                                <a class="al-accion" :href="'{{ route('consola.index') }}?dispositivo_id=' + a.dispositivo_id">Consola</a>
                                            </span>
                                        </template>
                                        <template x-if="!a.dispositivo_id && a.link"><a class="al-accion" :href="a.link">Ver</a></template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </template>
                </table>
                <p x-show="!filtradas.length" class="text-center text-sm py-10" style="color: var(--nm-text-muted)"
                   x-text="alarmas.length ? 'Ninguna alerta coincide con los filtros.' : 'No hay incidencias activas. La red opera normalmente.'"></p>
            </div>
        </div>
    </div>

    <script>
        function alertasActivas() {
            const leer = () => { try { return JSON.parse(document.getElementById('alertasActivasJson').textContent); } catch (e) { return []; } };
            return {
                alarmas: leer(), buscar: '', severidad: '', categoria: '', agrupar: '', soloCascada: false, abierta: null,
                orden: 'inicio_ts', desc: true,   // por defecto: la más reciente arriba
                // Primer clic en una columna: fechas y severidad de mayor a menor, textos de A a Z; otro clic invierte
                ordenar(col) {
                    if (this.orden === col) { this.desc = !this.desc; return; }
                    this.orden = col;
                    this.desc = ['inicio_ts', 'severidad', 'duracion'].includes(col);
                },
                init() {
                    document.addEventListener('nm:refrescado', e => { if (e.detail.claves.includes('alertas-activas')) this.alarmas = leer(); });
                },
                get filtradas() {
                    const q = this.buscar.toLowerCase().trim();
                    return this.alarmas
                        .filter(a => !this.severidad || a.severidad === this.severidad)
                        .filter(a => !this.categoria || a.categoria === this.categoria)
                        .filter(a => !this.soloCascada || a.cascada)
                        .filter(a => !q || [a.titulo, a.equipo, a.ip, a.mensaje].join(' ').toLowerCase().includes(q))
                        .sort((x, y) => {
                            const valor = a => this.orden === 'severidad' ? (a.severidad === 'critica' ? 2 : 1)
                                // Duración = tiempo desde el inicio: más antigua, más larga
                                : this.orden === 'duracion' ? -a.inicio_ts
                                : this.orden === 'inicio_ts' ? a.inicio_ts
                                : String(a[this.orden] || '').toLowerCase();
                            const vx = valor(x), vy = valor(y);
                            const c = typeof vx === 'number' ? vx - vy : vx.localeCompare(vy, 'es', { numeric: true });
                            // Empate: la más reciente primero
                            return (this.desc ? -c : c) || (y.inicio_ts - x.inicio_ts);
                        });
                },
                get grupos() {
                    if (!this.agrupar) return [{ clave: 'todas', titulo: '', items: this.filtradas }];
                    const mapa = new Map();
                    this.filtradas.forEach(a => {
                        const k = this.agrupar === 'equipo' ? (a.equipo || 'Sin equipo') : a.titulo.replace(/[0-9.]+\s*°C|\([^)]*\)|Gi[\d\/]+|Te[\d\/]+/g, '').trim();
                        if (!mapa.has(k)) mapa.set(k, { clave: k, titulo: k, items: [] });
                        mapa.get(k).items.push(a);
                    });
                    // Los grupos con más alertas primero
                    return [...mapa.values()].sort((x, y) => y.items.length - x.items.length);
                },
            };
        }
    </script>

@else
    {{-- ==================== HISTORIAL ==================== --}}
    @php
        $dur = fn ($s) => \App\Services\HistorialAlertas::duracion($s);
        $qs = request()->query();
    @endphp

    <form method="GET" action="{{ route('alertas.index') }}" class="floating-card p-4 flex flex-wrap items-end gap-2">
        <input type="hidden" name="pestana" value="historial">
        <label class="text-xs font-bold" style="color: var(--nm-text-muted)">Desde<br><input type="date" name="desde" value="{{ $filtros['desde']->format('Y-m-d') }}" class="al-campo mt-1"></label>
        <label class="text-xs font-bold" style="color: var(--nm-text-muted)">Hasta<br><input type="date" name="hasta" value="{{ $filtros['hasta']->format('Y-m-d') }}" class="al-campo mt-1"></label>
        <label class="text-xs font-bold" style="color: var(--nm-text-muted)">Severidad<br>
            <select name="severidad" class="al-campo mt-1">
                <option value="">Todas</option>
                <option value="critica" @selected($filtros['severidad'] === 'critica')>Críticas</option>
                <option value="advertencia" @selected($filtros['severidad'] === 'advertencia')>Advertencias</option>
            </select>
        </label>
        <label class="text-xs font-bold" style="color: var(--nm-text-muted)">Tipo<br>
            <select name="categoria" class="al-campo mt-1">
                <option value="">Todos</option>
                @foreach($categorias as $k => $v)<option value="{{ $k }}" @selected($filtros['categoria'] === $k)>{{ $v }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs font-bold" style="color: var(--nm-text-muted)">Estado<br>
            <select name="estado" class="al-campo mt-1">
                <option value="">Todas</option>
                <option value="activa" @selected($filtros['estado'] === 'activa')>Activas</option>
                <option value="resuelta" @selected($filtros['estado'] === 'resuelta')>Resueltas</option>
            </select>
        </label>
        <label class="text-xs font-bold flex-1 min-w-[12rem]" style="color: var(--nm-text-muted)">Buscar<br>
            <input type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Equipo, IP o alerta" class="al-campo mt-1 w-full">
        </label>
        <label class="text-xs font-bold" style="color: var(--nm-text-muted)">Ver<br>
            <select name="vista" class="al-campo mt-1" title="Agrupada: una fila por alarma con cuántas veces ocurrió">
                <option value="alarma" @selected($filtros['vista'] === 'alarma')>Agrupado por alarma</option>
                <option value="ocurrencias" @selected($filtros['vista'] === 'ocurrencias')>Cada ocurrencia</option>
            </select>
        </label>
        <button type="submit" class="fancy fancy-sm">
            <span class="top-key"></span>
            <span class="text">Aplicar</span>
            <span class="bottom-key-1"></span>
            <span class="bottom-key-2"></span>
        </button>
        <div class="flex gap-2 ml-auto">
            <a href="{{ route('alertas.exportar', array_merge($qs, ['formato' => 'csv'])) }}" class="al-campo inline-flex items-center font-bold">Excel (CSV)</a>
            <a href="{{ route('alertas.exportar', array_merge($qs, ['formato' => 'pdf'])) }}" target="_blank" class="al-campo inline-flex items-center font-bold">PDF</a>
        </div>
    </form>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="al-kpi" title="Cada vez que una falla empezó"><span>Incidencias</span><b>{{ number_format($resumen['total']) }}</b></div>
        <div class="al-kpi" title="Fallas distintas (la misma falla repetida cuenta una vez)"><span>Alarmas distintas</span><b>{{ number_format($resumen['alarmas']) }}</b></div>
        <div class="al-kpi"><span>Críticas</span><b style="color:#dc2626">{{ number_format($resumen['criticas']) }}</b></div>
        <div class="al-kpi" title="Tiempo medio de reparación de las resueltas"><span>Tiempo medio de reparación</span><b>{{ $dur($resumen['mttr_s']) }}</b></div>
        <div class="al-kpi" title="Alarmas que se repitieron {{ \App\Services\HistorialAlertas::UMBRAL_INTERMITENTE }} veces o más en el periodo: suelen ser umbrales al límite o enlaces inestables">
            <span>Intermitentes</span><b style="color:#d97706">{{ number_format($resumen['intermitentes']) }}</b>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Incidencias por día: barras apiladas (críticas + advertencias) con escala, valor y fecha --}}
        <div class="floating-card p-4 lg:col-span-2 flex flex-col">
            @php
                $dias = $resumen['por_dia'];
                $maxDia = max(1, ...array_column($dias, 'total'));
                // Escala redonda (1, 2, 5 × 10^n) con 4 divisiones
                $paso = 1;
                foreach ([1, 2, 5, 10, 20, 50, 100, 200, 250, 500, 1000, 2000, 2500, 5000, 10000] as $p) { if ($p * 4 >= $maxDia) { $paso = $p; break; } $paso = $p; }
                $tope = $paso * 4;
                $muchosDias = count($dias) > 16;
            @endphp
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-black" style="color: var(--nm-text)">Incidencias por día</h2>
                <div class="flex items-center gap-3 text-[11px] font-semibold" style="color: var(--nm-text-muted)">
                    <span class="inline-flex items-center gap-1"><i class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#dc2626"></i>Críticas</span>
                    <span class="inline-flex items-center gap-1"><i class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#f59e0b"></i>Advertencias</span>
                </div>
            </div>
            <div class="relative flex-1 min-h-[15rem] pl-10 pb-6" role="img"
                 aria-label="Incidencias por día del {{ $dias[0]['dia'] ?? '' }} al {{ end($dias)['dia'] ?? '' }}">
                {{-- Escala (eje izquierdo) y líneas guía, en la misma zona que las barras --}}
                <div class="absolute left-0 w-8 top-0 bottom-6">
                    @for($i = 0; $i <= 4; $i++)
                        <span class="absolute right-0 text-[11px] font-mono leading-none translate-y-1/2" style="bottom: {{ $i * 25 }}%; color: var(--nm-text-muted)">{{ number_format($paso * $i) }}</span>
                    @endfor
                </div>
                <div class="absolute left-10 right-0 top-0 bottom-6">
                    @for($i = 0; $i <= 4; $i++)
                        <div class="absolute left-0 right-0 border-t {{ $i ? 'border-dashed' : '' }}" style="bottom: {{ $i * 25 }}%; border-color: var(--nm-border-strong)"></div>
                    @endfor
                </div>
                {{-- Barras --}}
                <div class="absolute left-10 right-0 top-0 bottom-6 flex items-end gap-1 sm:gap-2">
                    @foreach($dias as $d)
                        @php
                            $adv = max(0, $d['total'] - $d['criticas']);
                            $altoTotal = $d['total'] / $tope * 100;
                            $altoCrit = $d['total'] ? $d['criticas'] / $d['total'] * 100 : 0;
                        @endphp
                        <div class="flex-1 h-full flex flex-col justify-end items-center min-w-0 group"
                             title="{{ $d['dia'] }}: {{ number_format($d['total']) }} incidencias ({{ number_format($d['criticas']) }} críticas, {{ number_format($adv) }} advertencias)">
                            @if($d['total'] && !$muchosDias)
                                <span class="text-[11px] font-mono font-bold mb-0.5" style="color: var(--nm-text)">{{ number_format($d['total']) }}</span>
                            @endif
                            {{-- Mínimo visible de 3 px para los días con pocas incidencias --}}
                            <div class="w-full max-w-[3.5rem] rounded-t-md overflow-hidden flex flex-col transition-opacity group-hover:opacity-80"
                                 style="height: {{ $d['total'] ? 'max(3px, ' . round($altoTotal, 2) . '%)' : '0' }}">
                                <div style="height: {{ round(100 - $altoCrit, 2) }}%; background:#f59e0b"></div>
                                <div class="flex-1" style="background:#dc2626"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                {{-- Fechas --}}
                <div class="absolute left-10 right-0 bottom-0 h-6 flex gap-1 sm:gap-2">
                    @foreach($dias as $i => $d)
                        <span class="flex-1 min-w-0 flex justify-center text-[11px] font-mono pt-1.5 whitespace-nowrap overflow-visible" style="color: var(--nm-text-muted)">
                            {{ !$muchosDias || $i % (int) ceil(count($dias) / 12) === 0 ? $d['dia'] : '' }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="floating-card p-4">
            <h2 class="text-sm font-black mb-1" style="color: var(--nm-text)">Equipos con más incidencias</h2>
            <p class="text-[11px] mb-3" style="color: var(--nm-text-muted)">Incidencias · tiempo acumulado (suma de sus alarmas; varias pueden ocurrir a la vez)</p>
            <ul class="space-y-2 text-sm">
                @forelse($resumen['top_equipos'] as $e)
                    <li class="flex items-center justify-between gap-2">
                        <a href="{{ route('dispositivos.show', $e->dispositivo_id) }}" class="truncate font-semibold hover:underline" style="color: var(--nm-text)" title="{{ $e->ip }}">{{ $e->nombre }}</a>
                        <span class="font-mono text-xs whitespace-nowrap" style="color: var(--nm-text-muted)">{{ number_format($e->n) }} · {{ $dur($e->total_s) }}</span>
                    </li>
                @empty
                    <li class="text-xs" style="color: var(--nm-text-muted)">Sin incidencias de equipos en el periodo.</li>
                @endforelse
            </ul>
            @if(count($resumen['por_categoria']))
                <h3 class="text-[11px] font-extrabold uppercase tracking-wider mt-4 mb-2" style="color: var(--nm-text-muted)">Por tipo</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($resumen['por_categoria']->sortDesc() as $c => $n)
                        <span class="al-pill" style="background: var(--nm-sunken); color: var(--nm-text)">{{ $categorias[$c] ?? $c }} · {{ number_format($n) }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="floating-card p-4">
        <div class="overflow-x-auto">
            <table class="al-tabla w-full">
                <thead>
                    @if($filtros['vista'] === 'alarma')
                        <tr><th>Severidad</th><th>Alarma</th><th>Equipo</th><th class="text-right">Veces</th><th>Primera</th><th>Última</th><th title="Suma de la duración de todas sus ocurrencias">Tiempo acumulado</th></tr>
                    @else
                        <tr><th>Severidad</th><th>Alarma</th><th>Equipo</th><th>Inicio</th><th>Resolución</th><th>Duración</th></tr>
                    @endif
                </thead>
                <tbody>
                    @forelse($filas as $r)
                        <tr class="al-fila">
                            <td><span class="al-pill {{ $r->severidad === 'critica' ? 'al-critica' : 'al-advertencia' }}">{{ $r->severidad === 'critica' ? 'Crítica' : 'Advertencia' }}</span></td>
                            <td>
                                <div class="font-bold">{{ $r->titulo }}</div>
                                <div class="text-xs" style="color: var(--nm-text-muted)">{{ $categorias[$r->categoria] ?? $r->categoria }}
                                    @if($filtros['vista'] === 'alarma' && $r->abiertas > 0) · <b style="color:#dc2626">activa ahora</b>@endif
                                    @if($filtros['vista'] === 'alarma' && $r->ocurrencias >= \App\Services\HistorialAlertas::UMBRAL_INTERMITENTE) · <b style="color:#d97706">intermitente</b>@endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                @if($r->dispositivo_id)
                                    <a href="{{ route('dispositivos.show', $r->dispositivo_id) }}" class="font-semibold hover:underline">{{ $r->equipo }}</a>
                                @else — @endif
                                <div class="text-xs font-mono" style="color: var(--nm-text-muted)">{{ $r->ip }}</div>
                            </td>
                            @if($filtros['vista'] === 'alarma')
                                <td class="text-right font-mono font-bold">{{ number_format($r->ocurrencias) }}</td>
                                <td class="whitespace-nowrap font-mono text-xs">{{ \Carbon\Carbon::parse($r->primera)->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap font-mono text-xs">{{ \Carbon\Carbon::parse($r->ultima)->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap font-mono text-xs">{{ $dur($r->total_s) }}</td>
                            @else
                                <td class="whitespace-nowrap font-mono text-xs">{{ \Carbon\Carbon::parse($r->fecha_inicio)->format('d/m/Y H:i:s') }}</td>
                                <td class="whitespace-nowrap font-mono text-xs">
                                    @if($r->fecha_resolucion){{ \Carbon\Carbon::parse($r->fecha_resolucion)->format('d/m/Y H:i:s') }}@else<b style="color:#dc2626">Activa</b>@endif
                                </td>
                                <td class="whitespace-nowrap font-mono text-xs">{{ $dur($r->duracion_s) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-10" style="color: var(--nm-text-muted)">No hay incidencias con estos filtros en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($filas->lastPage() > 1)
            <div class="flex items-center justify-between mt-3 text-sm" style="color: var(--nm-text-muted)">
                <span>{{ number_format($filas->total()) }} filas · página {{ $filas->currentPage() }} de {{ $filas->lastPage() }}</span>
                <span class="space-x-3">
                    @if($filas->previousPageUrl())<a href="{{ $filas->previousPageUrl() }}" class="al-accion">← Anterior</a>@endif
                    @if($filas->nextPageUrl())<a href="{{ $filas->nextPageUrl() }}" class="al-accion">Siguiente →</a>@endif
                </span>
            </div>
        @endif
    </div>
@endif
</div>
@endsection
