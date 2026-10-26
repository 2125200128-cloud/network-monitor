@extends('layouts.app')

@section('title', 'Virtualización')

@section('styles')
<style>
    .vz-kpi { padding: 10px 14px; border-radius: var(--nm-radius-lg); background: var(--nm-surface); border: 1px solid var(--nm-border); }
    .vz-kpi span { display: block; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .vz-kpi b { display: block; font-size: 20px; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 1px; }
    .vz-kpi small { display: block; font-size: 11px; color: var(--nm-text-muted); }
    .vz-titulo { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .vz-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 9999px; white-space: nowrap; }
    .vz-ok { color: #047857; background: #d1fae5; } .dark .vz-ok { color: #6ee7b7; background: rgb(6 78 59 / 0.5); }
    .vz-warn { color: #b45309; background: #fef3c7; } .dark .vz-warn { color: #fcd34d; background: rgb(120 53 15 / 0.45); }
    .vz-crit { color: #b91c1c; background: #fee2e2; } .dark .vz-crit { color: #fca5a5; background: rgb(127 29 29 / 0.45); }
    .vz-gris { color: var(--nm-text-muted); background: var(--nm-sunken); }
    .vz-t-warn { color: #b45309 !important; } .dark .vz-t-warn { color: #fcd34d !important; }
    .vz-t-crit { color: #b91c1c !important; } .dark .vz-t-crit { color: #fca5a5 !important; }
    .vz-tabla { width: 100%; border-collapse: collapse; }
    .vz-tabla th { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--nm-border); white-space: nowrap; }
    .vz-tabla td { padding: 6px 8px; border-bottom: 1px solid var(--nm-border); font-size: 12.5px; color: var(--nm-text); vertical-align: middle; white-space: nowrap; }
    .vz-tabla .num { text-align: right; font-family: ui-monospace, Consolas, monospace; }
    .vz-hosts tbody tr { cursor: pointer; }
    .vz-hosts tbody tr:hover td { background: var(--nm-sunken); }
    .vz-hosts tbody tr.vz-sel td { background: rgb(var(--nm-primary-rgb) / 0.10); }
    .vz-barra { position: relative; height: 6px; border-radius: 9999px; background: var(--nm-border); overflow: hidden; }
    .vz-barra i { display: block; height: 100%; border-radius: 9999px; }
    .vz-uso { display: grid; grid-template-columns: 52px 70px 96px; align-items: center; column-gap: 8px; }
    .vz-uso b { font-family: ui-monospace, Consolas, monospace; font-size: 13px; text-align: right; }
    .vz-uso small { grid-column: 2; font-size: 10px; color: var(--nm-text-muted); line-height: 1.1; }
    .vz-spark { grid-row: 1 / span 2; grid-column: 3; position: relative; height: 30px; width: 96px; }
    .vz-punto { display: inline-block; width: 9px; height: 9px; border-radius: 9999px; flex-shrink: 0; }
    .vz-aviso { display: flex; align-items: flex-start; gap: 10px; padding: 7px 10px; border-radius: var(--nm-radius-md); background: var(--nm-sunken); border-left: 3px solid; }
    .vz-aviso.crit { border-left-color: #dc2626; } .vz-aviso.warn { border-left-color: #d97706; }
    .vz-filtro { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; border: 1px solid var(--nm-border); color: var(--nm-text-muted); background: var(--nm-surface); }
    .vz-filtro.activo { color: #fff; background: var(--nm-primary); border-color: var(--nm-primary); }
    .vz-campo { font-size: 12px; padding: 5px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); }
</style>
@endsection

@section('content')
@php
    $bytes = fn ($b) => $b === null ? '—' : ($b >= 1024 ** 4 ? number_format($b / 1024 ** 4, 2) . ' TB' : number_format($b / 1024 ** 3, 0) . ' GB');
    $tono = fn ($v, $aviso, $critico) => $v === null ? '' : ($v >= $critico ? 'vz-t-crit' : ($v >= $aviso ? 'vz-t-warn' : ''));
    $colorBarra = fn ($v, $aviso = 80, $critico = 90) => $v >= $critico ? '#dc2626' : ($v >= $aviso ? '#d97706' : '#10b981');
    $series = collect($filas)->mapWithKeys(fn ($f) => [$f['id'] => $f['historia']]);
    $vmsJson = collect($vms)->map(fn ($v) => [
        'n' => $v['nombre'], 'e' => $v['estado'], 's' => $v['salud'] ?? null, 'h' => $v['host'],
        'c' => $v['vcpu'] ?? null, 'mhz' => $v['cpu_mhz'] ?? null, 'ru' => $v['ram_mb_uso'] ?? null, 'rm' => $v['ram_mb'] ?? null,
        'ip' => $v['ip'] ?? null, 'so' => $v['so'] ?? null, 't' => $v['tools'] ?? null,
    ])->values();
    $compartidos = collect($datastores)->where('hosts', '>', 1)->values();
    $locales = collect($datastores)->where('hosts', 1)->sortBy('nombre')->values();
    $crit = collect($atencion)->where('nivel', 'crit')->count();
    $vc = $vcenter_fila;
@endphp
<div class="flex flex-col space-y-3 pb-6">

    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" /><rect width="20" height="8" x="2" y="14" rx="2" /><path d="M6 6h.01" /><path d="M6 18h.01" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Virtualización VMware</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">API de cada host ESXi leída cada minuto · la página se actualiza cada 15 s</p>
            </div>
        </div>
        <a href="{{ route('settings.index', ['section' => 'general']) }}" class="text-xs font-bold hover:underline" style="color: var(--nm-primary)">Credenciales ESXi / vCenter · Ajustes →</a>
    </div>

    {{-- x-data aquí: el refresco conserva el estado de este elemento (filtros de la tabla de VMs) --}}
    <div data-refrescar="virtualizacion" data-refrescar-cada="15" class="flex flex-col space-y-3"
         x-data="{ buscar: '', host: '', estado: 'todas', orden: 'nombre' }">
        <script type="application/json" id="vzSeries">{!! json_encode($series, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        <script type="application/json" id="vzVmsDatos">{!! json_encode($vmsJson, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

        {{-- Indicadores --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="vz-kpi"><span>Hosts ESXi</span><b class="{{ $kpi['hosts_en_linea'] < $kpi['hosts'] ? 'vz-t-crit' : '' }}">{{ $kpi['hosts_en_linea'] }} / {{ $kpi['hosts'] }}</b><small>en línea · {{ $kpi['hosts_leidos'] }} leídos por API</small></div>
            <div class="vz-kpi"><span>CPU promedio</span><b class="{{ $tono($kpi['cpu_prom'], 80, 90) }}">{{ $kpi['cpu_prom'] === null ? '—' : number_format($kpi['cpu_prom'], 1) . '%' }}</b><small>de los hosts leídos</small></div>
            <div class="vz-kpi"><span>Memoria promedio</span><b class="{{ $tono($kpi['ram_prom'], 85, 95) }}">{{ $kpi['ram_prom'] === null ? '—' : number_format($kpi['ram_prom'], 1) . '%' }}</b><small>de los hosts leídos</small></div>
            <div class="vz-kpi"><span>Máquinas virtuales</span><b>{{ $hay_api ? $kpi['vms_encendidas'] . ' / ' . $kpi['vms_total'] : '—' }}</b><small>encendidas / total</small></div>
            <div class="vz-kpi"><span>Almacenamiento libre</span><b>{{ $hay_api ? $bytes($kpi['ds_libre']) : '—' }}</b><small>{{ $hay_api ? 'de ' . $bytes($kpi['ds_capacidad']) . ' · ' . count($datastores) . ' datastores' : 'datastores' }}</small></div>
            <div class="vz-kpi"><span>Requiere atención</span><b class="{{ $crit ? 'vz-t-crit' : (count($atencion) ? 'vz-t-warn' : '') }}">{{ count($atencion) ?: 'Nada' }}</b><small>{{ $crit }} críticos · última lectura {{ $ultima_lectura ?? '—' }}</small></div>
        </div>

        {{-- Requiere atención --}}
        <div class="floating-card px-5 py-3">
            @if($atencion)
                <h2 class="vz-titulo mb-2">Requiere atención</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-2 max-h-[220px] overflow-y-auto">
                    @foreach($atencion as $x)
                        <div class="vz-aviso {{ $x['nivel'] }}">
                            <span class="vz-pill {{ $x['nivel'] === 'crit' ? 'vz-crit' : 'vz-warn' }}">{{ $x['nivel'] === 'crit' ? 'Crítico' : 'Aviso' }}</span>
                            <div class="min-w-0">
                                <p class="text-[12.5px] font-bold leading-tight" style="color: var(--nm-text)">{{ $x['que'] }}</p>
                                <p class="text-[11px] truncate" style="color: var(--nm-text-muted)">{{ $x['donde'] }}{{ $x['detalle'] ? ' · ' . $x['detalle'] : '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm font-bold flex items-center gap-2" style="color: var(--nm-text)"><span class="vz-punto" style="background: #10b981"></span> Sin problemas detectados en los hosts, sensores, datastores ni VMs.</p>
            @endif
        </div>

        {{-- Hosts: una fila por host para compararlos de un vistazo --}}
        <div class="floating-card p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                <h2 class="vz-titulo">Hosts ESXi</h2>
                <p class="text-[11px]" style="color: var(--nm-text-muted)">Tendencia: últimas 6 h (promedios de 5 min) · clic en un host para ver sus VMs</p>
            </div>
            <div class="overflow-x-auto">
                <table class="vz-tabla vz-hosts">
                    <thead><tr><th>Host</th><th>CPU</th><th>Memoria</th><th class="num">VMs</th><th>Sensores</th><th class="num">Ping</th><th class="num">Encendido</th><th>ESXi</th></tr></thead>
                    <tbody>
                        @foreach($filas as $f)
                            @php
                                $a = $f['api'];
                                $malos = $a ? collect($a['sensores'])->whereIn('estado', ['yellow', 'red']) : collect();
                                $nivel = $f['estado'] === 'offline' || ($a && $a['conexion'] !== 'connected') || $malos->where('estado', 'red')->isNotEmpty() ? '#dc2626'
                                    : (!$a || $a['mantenimiento'] || $malos->isNotEmpty() ? '#d97706' : '#10b981');
                            @endphp
                            <tr :class="host === {{ e(json_encode($f['nombre'])) }} && 'vz-sel'"
                                @click="host = host === {{ e(json_encode($f['nombre'])) }} ? '' : {{ e(json_encode($f['nombre'])) }}; $nextTick(() => document.getElementById('vzVms')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="vz-punto" style="background: {{ $nivel }}"></span>
                                        <div>
                                            <a href="{{ route('dispositivos.show', $f['id']) }}" @click.stop class="font-black hover:underline" style="color: var(--nm-text)">{{ \Illuminate\Support\Str::before($f['nombre'], '.') }}</a>
                                            <p class="text-[10.5px] font-mono" style="color: var(--nm-text-muted)">{{ $f['ip'] }}</p>
                                        </div>
                                        @if($a && $a['mantenimiento'])<span class="vz-pill vz-warn">Mantenimiento</span>@endif
                                        @if($a && $a['conexion'] !== 'connected')<span class="vz-pill vz-crit">{{ $a['conexion'] }}</span>@endif
                                    </div>
                                </td>
                                @if($a)
                                    <td>
                                        <div class="vz-uso">
                                            <b class="{{ $tono($a['cpu_pct'], 80, 90) }}">{{ $a['cpu_pct'] === null ? '—' : $a['cpu_pct'] . '%' }}</b>
                                            <div class="vz-barra"><i style="width: {{ min(100, $a['cpu_pct'] ?? 0) }}%; background: {{ $colorBarra($a['cpu_pct'] ?? 0) }}"></i></div>
                                            <div class="vz-spark"><canvas data-vz-spark="cpu" data-vz-id="{{ $f['id'] }}" aria-label="CPU últimas 6 h"></canvas></div>
                                            <small>{{ $a['cpu_ghz_usado'] }} / {{ $a['cpu_ghz_total'] }} GHz</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="vz-uso">
                                            <b class="{{ $tono($a['ram_pct'], 85, 95) }}">{{ $a['ram_pct'] === null ? '—' : $a['ram_pct'] . '%' }}</b>
                                            <div class="vz-barra"><i style="width: {{ min(100, $a['ram_pct'] ?? 0) }}%; background: {{ $colorBarra($a['ram_pct'] ?? 0, 85, 95) }}"></i></div>
                                            <div class="vz-spark"><canvas data-vz-spark="ram" data-vz-id="{{ $f['id'] }}" aria-label="Memoria últimas 6 h"></canvas></div>
                                            <small>{{ $a['ram_gb_usado'] }} / {{ $a['ram_gb_total'] }} GB</small>
                                        </div>
                                    </td>
                                    <td class="num">{{ collect($a['vms'])->where('estado', 'poweredOn')->count() }}<span style="color: var(--nm-text-muted)"> / {{ count($a['vms']) }}</span></td>
                                    <td>
                                        @if($malos->isNotEmpty())
                                            <span class="vz-pill {{ $malos->where('estado', 'red')->isNotEmpty() ? 'vz-crit' : 'vz-warn' }}" title="{{ $malos->pluck('nombre')->implode(', ') }}">{{ $malos->count() }} con falla</span>
                                        @else
                                            <span class="vz-pill vz-ok">{{ count($a['sensores']) }} OK</span>
                                        @endif
                                    </td>
                                @else
                                    <td colspan="4" class="text-xs {{ $f['api_error'] ? 'vz-t-crit' : '' }}" style="color: var(--nm-text-muted); white-space: normal">
                                        @if($f['api_error'])
                                            No se pudo leer su API: {{ $f['api_error'] }}{{ $f['api_leido_hace'] ? ' · última lectura correcta ' . $f['api_leido_hace'] : '' }}
                                        @elseif($f['api_leido_hace'])
                                            Sin lectura reciente de su API (última {{ $f['api_leido_hace'] }}).
                                        @else
                                            Aún no se lee su API.
                                        @endif
                                    </td>
                                @endif
                                <td class="num {{ $f['estado'] === 'offline' ? 'vz-t-crit' : '' }}">{{ $f['estado'] === 'offline' ? 'Sin resp.' : ($f['ping_ms'] === null ? '—' : $f['ping_ms'] . ' ms') }}</td>
                                <td class="num">{{ $a['uptime'] ?? '—' }}</td>
                                <td class="text-[11px] font-mono" style="color: var(--nm-text-muted)">{{ $a && $a['version'] ? \Illuminate\Support\Str::after($a['version'], 'VMware ESXi ') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($filas && $filas[0]['api'])
                <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)">{{ $filas[0]['api']['hardware'] }} · {{ $filas[0]['api']['cpu_modelo'] }} · {{ $filas[0]['api']['nucleos'] }} núcleos / {{ $filas[0]['api']['hilos'] }} hilos por host</p>
            @endif
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-3">
            {{-- Datastores --}}
            <div class="floating-card p-4 xl:col-span-2">
                <h2 class="vz-titulo mb-1">Datastores compartidos · {{ $compartidos->count() }}</h2>
                @if($compartidos->isEmpty())
                    <p class="text-xs py-3" style="color: var(--nm-text-muted)">Sin lectura de datastores.</p>
                @else
                    <table class="vz-tabla">
                        <thead><tr><th>Datastore</th><th style="width: 40%">Uso</th><th class="num">Libre</th><th class="num">Capacidad</th><th class="num">Hosts</th></tr></thead>
                        <tbody>
                            @foreach($compartidos as $ds)
                                <tr>
                                    <td class="font-bold">{{ $ds['nombre'] }}@if($ds['accesible'] === false) <span class="vz-pill vz-crit">Inaccesible</span>@endif</td>
                                    <td>
                                        @if($ds['usado_pct'] !== null && $ds['accesible'] !== false)
                                            <div class="flex items-center gap-2"><div class="vz-barra flex-1"><i style="width: {{ $ds['usado_pct'] }}%; background: {{ $colorBarra($ds['usado_pct']) }}"></i></div><span class="font-mono text-xs w-12 text-right {{ $tono($ds['usado_pct'], 80, 90) }}">{{ $ds['usado_pct'] }}%</span></div>
                                        @else — @endif
                                    </td>
                                    @if($ds['accesible'] === false)
                                        <td class="num" colspan="2" style="color: var(--nm-text-muted)">Sin lectura</td>
                                    @else
                                    <td class="num" @if($ds['libre_max']) title="Cada host reporta su propia lectura del espacio libre; se muestra la menor. Otros hosts reportan hasta {{ $bytes($ds['libre_max']) }}." @endif>{{ $bytes($ds['libre'] ?? null) }}@if($ds['libre_max'])<sup style="color: var(--nm-text-muted)">*</sup>@endif</td>
                                    <td class="num">{{ $bytes($ds['capacidad'] ?? null) }}</td>
                                    @endif
                                    <td class="num">{{ $ds['hosts'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($compartidos->whereNotNull('libre_max')->isNotEmpty())
                        <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)">* Los hosts no coinciden en el espacio libre de ese volumen; se muestra el menor que reportan (pasa el cursor para ver el mayor).</p>
                    @endif
                @endif
                @if($locales->isNotEmpty())
                    <details class="mt-2">
                        <summary class="text-xs font-bold cursor-pointer" style="color: var(--nm-text-muted)">Discos locales de cada host · {{ $locales->count() }} · {{ $bytes($locales->sum('libre')) }} libres de {{ $bytes($locales->sum('capacidad')) }}</summary>
                        <table class="vz-tabla mt-1">
                            <tbody>
                                @foreach($locales as $ds)
                                    <tr>
                                        <td class="font-bold">{{ $ds['nombre'] }}</td>
                                        <td class="text-xs font-mono">{{ \Illuminate\Support\Str::before($ds['host'], '.') }}</td>
                                        <td class="num {{ $tono($ds['usado_pct'], 80, 90) }}">{{ $ds['usado_pct'] ?? '—' }}%</td>
                                        <td class="num">{{ $bytes($ds['libre'] ?? null) }} libres de {{ $bytes($ds['capacidad'] ?? null) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </div>

            {{-- vCenter y clústeres --}}
            <div class="floating-card p-4">
                <h2 class="vz-titulo mb-2">vCenter Server</h2>
                @if($vc)
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <a href="{{ route('dispositivos.show', $vc['id']) }}" class="font-black hover:underline" style="color: var(--nm-text)">{{ $vc['nombre'] }}</a>
                            <p class="text-[10.5px] font-mono" style="color: var(--nm-text-muted)">{{ $vc['ip'] }}</p>
                        </div>
                        <span class="vz-pill {{ $vc['estado'] === 'offline' ? 'vz-crit' : 'vz-ok' }}">{{ $vc['estado'] === 'offline' ? 'Sin respuesta' : 'En línea' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-2 text-center">
                        <div class="vz-kpi" style="padding: 6px"><span>Latencia</span><b class="text-[15px]">{{ $vc['ping_ms'] === null ? '—' : $vc['ping_ms'] . ' ms' }}</b></div>
                        <div class="vz-kpi" style="padding: 6px"><span>Pérdida</span><b class="text-[15px] {{ $tono($vc['perdida_pct'], 1, 20) }}">{{ $vc['perdida_pct'] === null ? '—' : round($vc['perdida_pct']) . '%' }}</b></div>
                        <div class="vz-kpi" style="padding: 6px"><span>Disp. 24 h</span><b class="text-[15px]">{{ $vc['disponibilidad_24h'] === null ? '—' : $vc['disponibilidad_24h'] . '%' }}</b></div>
                    </div>
                @endif
                <h2 class="vz-titulo mt-4 mb-1">Clústeres</h2>
                @if(!$clusters)
                    <p class="text-xs" style="color: var(--nm-text-muted)">Los clústeres (HA y DRS) y las alarmas los informa el vCenter; {{ ($vcenter['error'] ?? '') === 'sin_configurar' ? 'conéctalo en Ajustes → vCenter para verlos.' : 'no se pudo leer: ' . ($vcenter['error'] ?? '') }}</p>
                @else
                    <table class="vz-tabla">
                        <thead><tr><th>Clúster</th><th>HA</th><th>DRS</th></tr></thead>
                        <tbody>
                            @foreach($clusters as $c)
                                <tr>
                                    <td class="font-bold">{{ $c['name'] }}</td>
                                    <td>{!! ($c['ha_enabled'] ?? false) ? '<span class="vz-pill vz-ok">Activo</span>' : '<span class="vz-pill vz-gris">Inactivo</span>' !!}</td>
                                    <td>{!! ($c['drs_enabled'] ?? false) ? '<span class="vz-pill vz-ok">Activo</span>' : '<span class="vz-pill vz-gris">Inactivo</span>' !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Máquinas virtuales: filtros arriba, se leen del JSON para poder ordenar sin recargar --}}
        @if($hay_api)
            <div id="vzVms" class="floating-card p-4 scroll-mt-4" x-data="vzTablaVms()">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h2 class="vz-titulo mr-2">Máquinas virtuales · <span x-text="filas.length"></span> de {{ count($vms) }}</h2>
                    <button type="button" class="vz-filtro" :class="estado === 'todas' && 'activo'" @click="estado = 'todas'">Todas</button>
                    <button type="button" class="vz-filtro" :class="estado === 'poweredOn' && 'activo'" @click="estado = 'poweredOn'">Encendidas</button>
                    <button type="button" class="vz-filtro" :class="estado === 'poweredOff' && 'activo'" @click="estado = 'poweredOff'">Apagadas</button>
                    <select x-model="host" class="vz-campo" aria-label="Host">
                        <option value="">Todos los hosts</option>
                        @foreach($filas as $f)<option value="{{ $f['nombre'] }}">{{ \Illuminate\Support\Str::before($f['nombre'], '.') }}</option>@endforeach
                    </select>
                    <select x-model="orden" class="vz-campo" aria-label="Ordenar">
                        <option value="nombre">Ordenar por nombre</option>
                        <option value="cpu">Más CPU</option>
                        <option value="ram">Más memoria en uso</option>
                        <option value="vcpu">Más vCPU</option>
                    </select>
                    <input type="search" x-model="buscar" placeholder="Buscar VM, IP o sistema…" class="vz-campo ml-auto w-56">
                </div>
                <div class="overflow-x-auto max-h-[520px] overflow-y-auto">
                    <table class="vz-tabla">
                        <thead class="sticky top-0" style="background: var(--nm-surface)">
                            <tr><th>VM</th><th>Estado</th><th>Host</th><th class="num">vCPU</th><th class="num">CPU</th><th class="num">Memoria en uso</th><th>IP</th><th>Sistema operativo</th><th>Tools</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="v in filas" :key="v.h + '|' + v.n">
                                <tr>
                                    <td class="font-bold"><span x-text="v.n"></span> <span x-show="v.s === 'yellow' || v.s === 'red'" class="vz-pill" :class="v.s === 'red' ? 'vz-crit' : 'vz-warn'">!</span></td>
                                    <td><span class="vz-pill" :class="{ poweredOn: 'vz-ok', poweredOff: 'vz-gris', suspended: 'vz-warn' }[v.e] || 'vz-gris'" x-text="{ poweredOn: 'Encendida', poweredOff: 'Apagada', suspended: 'Suspendida' }[v.e] || v.e"></span></td>
                                    <td class="text-xs font-mono" x-text="v.h.split('.')[0]"></td>
                                    <td class="num" x-text="v.c ?? '—'"></td>
                                    <td class="num" x-text="v.e === 'poweredOn' && v.mhz !== null ? (v.mhz / 1000).toFixed(2) + ' GHz' : '—'"></td>
                                    <td class="num" x-text="v.e === 'poweredOn' && v.ru !== null ? (v.ru / 1024).toFixed(1) + ' / ' + Math.round((v.rm || 0) / 1024) + ' GB' : Math.round((v.rm || 0) / 1024) + ' GB asig.'"></td>
                                    <td class="text-xs font-mono" x-text="v.ip || '—'"></td>
                                    <td class="text-xs truncate max-w-[220px]" :title="v.so" x-text="v.so || '—'"></td>
                                    <td class="text-xs" x-text="{ guestToolsRunning: 'Activas', guestToolsNotRunning: 'Detenidas', guestToolsExecutingScripts: 'Iniciando' }[v.t] || '—'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="!filas.length" class="text-xs py-4 text-center" style="color: var(--nm-text-muted)">Ninguna VM coincide con los filtros.</p>
                </div>
                <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)">CPU y memoria en uso que reporta cada host por su API (lectura cada minuto).</p>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Tabla de VMs: los datos vienen del JSON de la zona; los filtros (buscar, host, estado, orden) son del
    // elemento de la zona, así que sobreviven al refresco.
    function vzTablaVms() {
        return {
            lista: [],
            init() { this.lista = JSON.parse(document.getElementById('vzVmsDatos')?.textContent || '[]'); },
            get filas() {
                const q = (this.buscar || '').toLowerCase();
                const criterio = { cpu: v => v.e === 'poweredOn' ? (v.mhz ?? -1) : -1, ram: v => v.e === 'poweredOn' ? (v.ru ?? -1) : -1, vcpu: v => v.c ?? -1 }[this.orden];
                const filas = this.lista.filter(v => (this.estado === 'todas' || v.e === this.estado)
                    && (!this.host || v.h === this.host)
                    && (!q || [v.n, v.ip, v.so, v.h].join(' ').toLowerCase().includes(q)));
                return filas.sort(criterio ? (a, b) => criterio(b) - criterio(a) : (a, b) => a.n.localeCompare(b.n, 'es', { sensitivity: 'base' }));
            },
        };
    }

    // Tendencia de 6 h por host: una mini-gráfica por métrica (CPU o memoria), escala fija 0–100 %
    (function () {
        const instancias = [];
        function pintar() {
            instancias.splice(0).forEach(g => g.destroy());
            const fuente = document.getElementById('vzSeries');
            if (!fuente || typeof Chart === 'undefined') return;
            const series = JSON.parse(fuente.textContent || '{}');
            const color = '#667d93';   // azul institucional
            document.querySelectorAll('canvas[data-vz-spark]').forEach(canvas => {
                const campo = canvas.dataset.vzSpark;
                const puntos = series[canvas.dataset.vzId] || [];
                instancias.push(new Chart(canvas, {
                    type: 'line',
                    data: { labels: puntos.map(p => p.h), datasets: [{ data: puntos.map(p => p[campo]), borderColor: color, backgroundColor: color + '22',
                        fill: true, borderWidth: 1.5, pointRadius: puntos.length === 1 ? 2 : 0, pointHoverRadius: 3, tension: 0.3 }] },
                    options: { responsive: true, maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false }, tooltip: { displayColors: false, callbacks: {
                            title: i => i[0].label, label: i => (campo === 'cpu' ? 'CPU ' : 'Memoria ') + i.parsed.y + '%' } } },
                        scales: { x: { display: false }, y: { display: false, min: 0, max: 100 } } },
                }));
            });
        }
        document.addEventListener('DOMContentLoaded', pintar);
        document.addEventListener('nm:refrescado', e => { if (e.detail.claves.includes('virtualizacion')) pintar(); });
    })();
</script>
@endsection
