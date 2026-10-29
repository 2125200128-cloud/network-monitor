@extends('layouts.app')

@section('title', 'Virtualización')

@section('styles')
<style>
    [x-cloak] { display: none !important; }
    .vz-kpi { padding: 12px 14px; border-radius: var(--nm-radius-lg); background: var(--nm-surface); border: 1px solid var(--nm-border); }
    .vz-kpi span { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .vz-kpi b { display: block; font-size: 22px; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 2px; }
    .vz-kpi small { display: block; font-size: 11px; color: var(--nm-text-muted); margin-top: 1px; }
    .vz-metrica { padding: 10px 12px; border-radius: var(--nm-radius-md); background: var(--nm-sunken); }
    .vz-metrica > span { display: block; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .vz-metrica b { display: block; font-size: 24px; line-height: 1.15; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 3px; }
    .vz-metrica small { display: block; font-size: 11px; color: var(--nm-text-muted); }
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
    .vz-barra { position: relative; height: 6px; border-radius: 9999px; background: var(--nm-border); overflow: hidden; }
    .vz-barra i { display: block; height: 100%; border-radius: 9999px; }
    .vz-punto { display: inline-block; width: 9px; height: 9px; border-radius: 9999px; flex-shrink: 0; }
    .vz-aviso { display: flex; align-items: flex-start; gap: 10px; padding: 7px 10px; border-radius: var(--nm-radius-md); background: var(--nm-sunken); border-left: 3px solid; }
    .vz-aviso.crit { border-left-color: #dc2626; } .vz-aviso.warn { border-left-color: #d97706; }
    .vz-filtro { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; border: 1px solid var(--nm-border); color: var(--nm-text-muted); background: var(--nm-surface); }
    .vz-filtro.activo { color: #fff; background: var(--nm-primary); border-color: var(--nm-primary); }
    .vz-campo { font-size: 12px; padding: 5px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); }
    .vz-grafica { position: relative; height: 170px; }

    /* Chasis dibujado: oscuro en los dos temas, como el equipo real. Cada host es un sled vertical. */
    .vz-chasis { display: grid; gap: 6px; padding: 10px; border-radius: 12px; overflow-x: auto;
        background: linear-gradient(180deg, #2b3038 0%, #1c2027 55%, #13161b 100%); border: 1px solid #3b424d;
        box-shadow: inset 0 1px 0 rgb(255 255 255 / .07), inset 0 -2px 6px rgb(0 0 0 / .55); }
    .vz-sled { display: flex; flex-direction: column; align-items: stretch; gap: 7px; padding: 6px 6px 8px; min-width: 84px; border-radius: 6px; cursor: pointer; text-align: left;
        background: linear-gradient(90deg, #262b33 0%, #1b1f25 50%, #232830 100%); border: 1px solid #3a414c;
        box-shadow: inset 0 1px 0 rgb(255 255 255 / .06); transition: transform .12s ease; }
    .vz-sled:hover { border-color: #5b6573; }
    .vz-sled.sel { border-color: #60a5fa; box-shadow: 0 0 0 1.5px #60a5fa, inset 0 1px 0 rgb(255 255 255 / .06); }
    .vz-asa { display: block; height: 7px; margin: 0 10px; border-radius: 3px; background: linear-gradient(180deg, #59616d, #353b44); box-shadow: 0 1px 1px rgb(0 0 0 / .6); }
    .vz-leds { display: flex; align-items: center; justify-content: center; gap: 6px; }
    .vz-leds i, .vz-led-muestra { display: block; width: 7px; height: 7px; border-radius: 50%; background: #3b434e; }
    .vz-bahias { display: flex; flex-direction: column; gap: 3px; }
    .vz-bahia { position: relative; display: block; height: 13px; border-radius: 2px; background: repeating-linear-gradient(90deg, #0b0d11 0 2px, #171b21 2px 4px); border: 1px solid #454d58; }
    .vz-bahia i { position: absolute; right: 3px; top: 3px; width: 5px; height: 5px; border-radius: 50%; background: #3b434e; }
    .vz-bahias-nd { display: block; height: 40px; border: 1px dashed #4b5360; border-radius: 3px; font: 700 9px/40px ui-monospace, Consolas, monospace; color: #6b7480; text-align: center; }
    /* Abajo del sled aunque tenga menos bahías: los medidores de todos quedan alineados */
    .vz-medidores { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 0 4px; margin-top: auto; }
    .vz-medidor { display: flex; flex-direction: column; align-items: center; gap: 2px; }
    .vz-medidor span { position: relative; display: block; width: 10px; height: 52px; border-radius: 3px; background: #0b0d11; box-shadow: inset 0 1px 2px rgb(0 0 0 / .8); overflow: hidden; }
    .vz-medidor span i { position: absolute; left: 0; right: 0; bottom: 0; display: block; }
    .vz-medidor small { font: 700 9px/1 ui-monospace, Consolas, monospace; color: #d6dde6; }
    .vz-medidor em { font: 700 8px/1 ui-monospace, Consolas, monospace; color: #8d97a4; font-style: normal; letter-spacing: .05em; }
    .vz-placa { display: block; text-align: center; font: 800 11px/1.1 ui-monospace, Consolas, monospace; color: #e6ebf1; letter-spacing: .03em; }
    .vz-placa small { display: block; font-size: 9px; font-weight: 700; color: #8d97a4; margin-top: 2px; }

    /* Colores de LED (sled, bahías y VMs) */
    .e-ok, .vz-led-muestra.e-ok { background: #22c55e !important; box-shadow: 0 0 5px #22c55e; }
    .e-warn, .vz-led-muestra.e-warn { background: #f59e0b !important; box-shadow: 0 0 5px #f59e0b; }
    .e-crit, .vz-led-muestra.e-crit { background: #ef4444 !important; box-shadow: 0 0 6px #ef4444; }
    .e-off, .vz-led-muestra.e-off { background: #3b434e !important; }
    .e-nd, .vz-led-muestra.e-nd { background: transparent !important; box-shadow: inset 0 0 0 1px #6b7280; }
    .e-act, .vz-led-muestra.e-act { background: #60a5fa !important; box-shadow: 0 0 5px #60a5fa; }
    .act { animation: vzActividad var(--ritmo, 1.4s) ease-in-out infinite; }
    @keyframes vzActividad { 50% { opacity: .3; box-shadow: none; } }
    @media (prefers-reduced-motion: reduce) { .act { animation: none; } }
    .vz-leyenda { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 11px; color: var(--nm-text-muted); }
    .vz-leyenda span { display: inline-flex; align-items: center; gap: 5px; }

    /* VMs del host: módulos dentro de un panel oscuro */
    .vz-vms { display: grid; grid-template-columns: repeat(auto-fill, minmax(124px, 1fr)); gap: 4px; padding: 6px; border-radius: 10px; max-height: 268px; overflow-y: auto;
        background: linear-gradient(180deg, #23272e, #16191e); border: 1px solid #3b424d; box-shadow: inset 0 1px 3px rgb(0 0 0 / .6); }
    .vz-vm { display: flex; flex-direction: column; gap: 3px; padding: 5px 6px 4px; border-radius: 4px; cursor: pointer; text-align: left; min-width: 0;
        background: rgb(255 255 255 / .03); border: 1px solid #343b45; }
    .vz-vm:hover { background: rgb(255 255 255 / .07); }
    .vz-vm.sel { background: rgb(96 165 250 / .16); border-color: #60a5fa; }
    .vz-vm.s-yellow { border-color: #b45309; } .vz-vm.s-red { border-color: #dc2626; }
    .vz-vm > span { display: flex; align-items: center; gap: 5px; min-width: 0; }
    .vz-vm > span i { display: block; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .vz-vm small { font: 700 10px/1.15 ui-monospace, Consolas, monospace; color: #d6dde6; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vz-vm.apagada small { color: #7d8794; }
    .vz-vm-uso { display: block; height: 2px; border-radius: 2px; background: #2a3039; overflow: hidden; }
    .vz-vm-uso i { display: block; height: 100%; }
    .vz-detalle { margin-top: 8px; padding: 9px 12px; border-radius: var(--nm-radius-md); background: var(--nm-sunken); font-size: 12px; color: var(--nm-text); min-height: 38px; }
    .vz-dato { display: block; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--nm-text-muted); }
</style>
@endsection

@section('content')
@php
    $bytes = fn ($b) => $b === null ? '—' : ($b >= 1024 ** 4 ? number_format($b / 1024 ** 4, 2) . ' TB' : number_format($b / 1024 ** 3, 0) . ' GB');
    $tono = fn ($v, $aviso, $critico) => $v === null ? '' : ($v >= $critico ? 'vz-t-crit' : ($v >= $aviso ? 'vz-t-warn' : ''));
    $colorBarra = fn ($v, $aviso = 80, $critico = 90) => $v >= $critico ? '#dc2626' : ($v >= $aviso ? '#d97706' : '#10b981');
    $corto = fn ($n) => \Illuminate\Support\Str::before($n, '.');
    $ledSensor = fn ($estado) => ['green' => 'e-ok', 'yellow' => 'e-warn', 'red' => 'e-crit'][$estado] ?? 'e-nd';
    // Parpadeo del LED de actividad: más rápido cuanto más CPU se usa (dato real del host o de la VM)
    $ritmo = fn ($pct) => $pct === null ? null : ($pct < 10 ? '2.2s' : ($pct < 30 ? '1.4s' : ($pct < 60 ? '.9s' : '.5s')));
    $nivelHost = function ($f) {
        $a = $f['api'];
        $malos = $a ? collect($a['sensores'])->whereIn('estado', ['yellow', 'red']) : collect();
        if ($f['estado'] === 'offline' || ($a && $a['conexion'] !== 'connected') || $malos->where('estado', 'red')->isNotEmpty() || ($a['salud'] ?? null) === 'red') {
            return 'crit';
        }
        if (!$a || $a['mantenimiento'] || $malos->isNotEmpty() || ($a['salud'] ?? null) === 'yellow'
            || ($a['cpu_pct'] ?? 0) >= 80 || ($a['ram_pct'] ?? 0) >= 85) {
            return 'warn';
        }
        return 'ok';
    };
    $niveles = collect($filas)->mapWithKeys(fn ($f) => [$f['id'] => $nivelHost($f)]);
    // Al abrir la página se muestra el host que más atención requiere
    $hostDefecto = $niveles->search('crit') ?: ($niveles->search('warn') ?: ($filas[0]['id'] ?? null));
    $tipoSensor = ['voltage' => 'Voltaje', 'systemBoard' => 'Placa base', 'temperature' => 'Temperatura', 'memory' => 'Memoria',
        'other' => 'Otros', 'battery' => 'Baterías', 'processor' => 'Procesadores', 'watchdog' => 'Watchdog', 'storage' => 'Discos',
        'fan' => 'Ventiladores', 'power' => 'Fuentes de poder', 'cable' => 'Cables'];
    $series = collect($filas)->mapWithKeys(fn ($f) => [$f['id'] => ['esxi' => $f['historia'], 'ping' => $f['ping_historia']]]);
    $vmsJson = collect($vms)->map(fn ($v) => [
        'k' => $v['host'] . '|' . $v['nombre'],
        'n' => $v['nombre'], 'e' => $v['estado'], 's' => $v['salud'] ?? null, 'h' => $v['host'],
        'c' => $v['vcpu'] ?? null, 'mhz' => $v['cpu_mhz'] ?? null, 'cp' => $v['cpu_pct'] ?? null,
        'ru' => $v['ram_mb_uso'] ?? null, 'rh' => $v['ram_mb_host'] ?? null, 'rm' => $v['ram_mb'] ?? null,
        'ip' => $v['ip'] ?? null, 'ips' => $v['ips'] ?? [], 'so' => $v['so'] ?? null, 't' => $v['tools'] ?? null,
        'up' => $v['uptime_seg'] ?? null, 'l' => $v['latido'] ?? null, 'b' => $v['balloon_mb'] ?? null, 'sw' => $v['swap_mb'] ?? null,
        'd' => $v['discos'] ?? [],
    ])->values();
    $dsPorUrl = collect($datastores)->keyBy(fn ($d) => $d['url'] ?? ($d['host'] . '|' . $d['nombre']));
    $compartidos = collect($datastores)->where('hosts', '>', 1)->values();
    $locales = collect($datastores)->where('hosts', 1)->sortBy('nombre')->values();
    $crit = collect($atencion)->where('nivel', 'crit')->count();
    $vc = $vcenter_fila;
    $modelos = collect($filas)->pluck('api.hardware')->filter()->unique()->implode(' · ');
@endphp
<div class="flex flex-col space-y-4 pb-6">

    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" /><rect width="20" height="8" x="2" y="14" rx="2" /><path d="M6 6h.01" /><path d="M6 18h.01" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Virtualización VMware</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">API de cada host ESXi leída cada minuto · se actualiza cada 15 s</p>
            </div>
        </div>
        <a href="{{ route('settings.index', ['section' => 'general']) }}" class="text-xs font-bold hover:underline" style="color: var(--nm-primary)">Credenciales ESXi / vCenter · Ajustes →</a>
    </div>

    {{-- x-data aquí: el refresco conserva el estado de este elemento (host y VM elegidos, filtros de la tabla) --}}
    <div data-refrescar="virtualizacion" data-refrescar-cada="15" class="flex flex-col space-y-4"
         x-data="{ buscar: '', host: '', estado: 'todas', orden: 'nombre', sel: {{ (int) $hostDefecto }}, vm: '' }"
         x-init="$nextTick(() => window.vzPintar && vzPintar()); $watch('sel', () => $nextTick(() => vzPintar()))">
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

        {{-- ===== Chasis: cada host dibujado como sled, con sus discos físicos y su uso real ===== --}}
        @if($filas)
            <div class="floating-card p-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <div>
                        <h2 class="vz-titulo">Hosts ESXi · {{ count($filas) }}</h2>
                        @if($modelos)<p class="text-[11px] mt-0.5" style="color: var(--nm-text-muted)">{{ $modelos }}</p>@endif
                    </div>
                    <div class="vz-leyenda">
                        <span><i class="vz-led-muestra e-ok"></i> Bien</span>
                        <span><i class="vz-led-muestra e-warn"></i> Aviso</span>
                        <span><i class="vz-led-muestra e-crit"></i> Falla</span>
                        <span><i class="vz-led-muestra e-nd"></i> Sin lectura</span>
                        <span><i class="vz-led-muestra e-act"></i> Actividad (parpadea según la CPU)</span>
                    </div>
                </div>
                <div class="vz-chasis" style="grid-template-columns: repeat({{ count($filas) }}, minmax(84px, 1fr))">
                    @foreach($filas as $f)
                        @php
                            $a = $f['api'];
                            $nivel = $niveles[$f['id']];
                            $cpu = $a['cpu_pct'] ?? null;
                            $ram = $a['ram_pct'] ?? null;
                        @endphp
                        <button type="button" class="vz-sled" :class="sel === {{ $f['id'] }} && 'sel'" @click="if (sel !== {{ $f['id'] }}) { sel = {{ $f['id'] }}; vm = ''; }"
                                title="{{ $f['nombre'] }} · {{ $f['ip'] }}{{ $a ? ' · CPU ' . $cpu . '% · memoria ' . $ram . '%' : '' }}" aria-label="Ver {{ $corto($f['nombre']) }}">
                            <span class="vz-asa"></span>
                            <span class="vz-leds">
                                <i class="{{ $f['estado'] === 'offline' ? 'e-crit' : 'e-ok' }}" title="Encendido: {{ $f['estado'] === 'offline' ? 'sin respuesta a ping' : 'responde' }}"></i>
                                <i class="{{ ['ok' => 'e-ok', 'warn' => 'e-warn', 'crit' => 'e-crit'][$nivel] }}" title="Salud del host"></i>
                                <i class="{{ $a && $f['estado'] !== 'offline' ? 'e-act act' : 'e-off' }}" @if($ritmo($cpu)) style="--ritmo: {{ $ritmo($cpu) }}" @endif title="Actividad (CPU {{ $cpu ?? '—' }}%)"></i>
                            </span>
                            @if($a && $a['discos_fisicos'])
                                <span class="vz-bahias">
                                    @foreach($a['discos_fisicos'] as $dsk)
                                        <span class="vz-bahia" title="{{ $dsk['nombre'] }}: {{ ['green' => 'bien', 'yellow' => 'aviso', 'red' => 'falla'][$dsk['estado']] ?? 'sin dato' }}"><i class="{{ $ledSensor($dsk['estado']) }}"></i></span>
                                    @endforeach
                                </span>
                            @else
                                <span class="vz-bahias-nd">{{ $a ? 'sin discos' : 'sin API' }}</span>
                            @endif
                            <span class="vz-medidores">
                                @foreach([['CPU', $cpu, 80, 90], ['RAM', $ram, 85, 95]] as [$etq, $v, $av, $cr])
                                    <span class="vz-medidor">
                                        <span><i style="height: {{ min(100, $v ?? 0) }}%; background: {{ $v === null ? 'transparent' : $colorBarra($v, $av, $cr) }}"></i></span>
                                        <small>{{ $v === null ? '—' : round($v) . '%' }}</small>
                                        <em>{{ $etq }}</em>
                                    </span>
                                @endforeach
                            </span>
                            <span class="vz-placa">{{ $corto($f['nombre']) }}
                                <small>{{ $a ? collect($a['vms'])->where('estado', 'poweredOn')->count() . ' / ' . count($a['vms']) . ' VMs' : ($f['estado'] === 'offline' ? 'sin respuesta' : 'sin lectura') }}</small>
                            </span>
                        </button>
                    @endforeach
                </div>
                <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)">Las bahías son los discos físicos que reporta cada host (sensores de almacenamiento). Los hosts van ordenados por nombre: la API no informa la ranura física. Clic en un host para ver su detalle.</p>
            </div>
        @endif

        {{-- ===== Panel del host elegido ===== --}}
        @foreach($filas as $f)
            @php
                $a = $f['api'];
                $hostVms = $a ? collect($a['vms'])->sortBy(fn ($v) => [($v['estado'] ?? '') === 'poweredOff' ? 1 : 0, strtolower($v['nombre'] ?? '')])->values() : collect();
                $malos = $a ? collect($a['sensores'])->whereIn('estado', ['yellow', 'red']) : collect();
                $temp = $a['temperaturas'][0] ?? null;
                $topCpu = $hostVms->where('estado', 'poweredOn')->whereNotNull('cpu_mhz')->sortByDesc('cpu_mhz')->take(6);
                $dsHost = $a ? collect($a['datastores'])->map(fn ($d) => ($dsPorUrl[$d['url'] ?? ($f['nombre'] . '|' . $d['nombre'])] ?? $d))->sortByDesc('usado_pct')->values() : collect();
            @endphp
            <div id="vzHost{{ $f['id'] }}" class="floating-card p-5 scroll-mt-4" x-show="sel === {{ $f['id'] }}" x-cloak>
                {{-- Identificación --}}
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('dispositivos.show', $f['id']) }}" class="text-base font-black hover:underline" style="color: var(--nm-text)">{{ $f['nombre'] }}</a>
                            <span class="vz-pill {{ $f['estado'] === 'offline' ? 'vz-crit' : 'vz-ok' }}">{{ $f['estado'] === 'offline' ? 'Sin respuesta' : 'En línea' }}</span>
                            @if($a && $a['conexion'] !== 'connected')<span class="vz-pill vz-crit">{{ $a['conexion'] === 'notResponding' ? 'No responde al vCenter' : 'Desconectado del vCenter' }}</span>@endif
                            @if($a && $a['mantenimiento'])<span class="vz-pill vz-warn">Modo mantenimiento</span>@endif
                            @if($a && in_array($a['salud'], ['yellow', 'red'], true))<span class="vz-pill {{ $a['salud'] === 'red' ? 'vz-crit' : 'vz-warn' }}">Estado VMware: {{ $a['salud'] === 'red' ? 'rojo' : 'amarillo' }}</span>@endif
                            @if($malos->isNotEmpty())<span class="vz-pill {{ $malos->where('estado', 'red')->isNotEmpty() ? 'vz-crit' : 'vz-warn' }}">{{ $malos->count() }} sensor(es) con falla</span>@endif
                            @if($a && $a['alarmas'])<span class="vz-pill vz-warn">{{ count($a['alarmas']) }} alarma(s) VMware</span>@endif
                        </div>
                        <p class="text-xs font-mono mt-1" style="color: var(--nm-text-muted)">
                            {{ $f['ip'] }}{{ ($a['hardware'] ?? null) ? ' · ' . $a['hardware'] : '' }}{{ ($a['version'] ?? null) ? ' · ' . \Illuminate\Support\Str::after($a['version'], 'VMware ') : '' }}{{ ($a['uptime'] ?? null) ? ' · encendido ' . $a['uptime'] : '' }}
                        </p>
                        @if($a && $a['cpu_modelo'])<p class="text-[11px] mt-0.5" style="color: var(--nm-text-muted)">{{ $a['cpu_modelo'] }} · {{ $a['nucleos'] }} núcleos / {{ $a['hilos'] }} hilos</p>@endif
                    </div>
                    <p class="text-[11px]" style="color: var(--nm-text-muted)">
                        Disponibilidad 24 h: <b class="font-mono">{{ $f['disponibilidad_24h'] === null ? '—' : $f['disponibilidad_24h'] . '%' }}</b>
                        · Latencia: <b class="font-mono {{ $f['estado'] === 'offline' ? 'vz-t-crit' : '' }}">{{ $f['estado'] === 'offline' ? 'sin respuesta' : ($f['ping_ms'] === null ? '—' : $f['ping_ms'] . ' ms') }}</b>
                        · Pérdida: <b class="font-mono {{ $tono($f['perdida_pct'], 1, 20) }}">{{ $f['perdida_pct'] === null ? '—' : round($f['perdida_pct']) . '%' }}</b>
                    </p>
                </div>

                @if(!$a)
                    <div class="vz-aviso warn mb-4">
                        <span class="vz-pill vz-warn">Sin API</span>
                        <p class="text-[12.5px]" style="color: var(--nm-text)">
                            @if($f['api_error'])No se pudo leer su API: {{ $f['api_error'] }}{{ $f['api_leido_hace'] ? ' · última lectura correcta ' . $f['api_leido_hace'] : '' }}
                            @elseif($f['api_leido_hace'])Sin lectura reciente de su API (última {{ $f['api_leido_hace'] }}).
                            @else Aún no se lee su API; abajo solo lo que se mide por ping.@endif
                        </p>
                    </div>
                @else
                    {{-- Métricas actuales --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
                        <div class="vz-metrica">
                            <span>CPU</span><b class="{{ $tono($a['cpu_pct'], 80, 90) }}">{{ $a['cpu_pct'] === null ? '—' : $a['cpu_pct'] . '%' }}</b>
                            <div class="vz-barra mt-1.5"><i style="width: {{ min(100, $a['cpu_pct'] ?? 0) }}%; background: {{ $colorBarra($a['cpu_pct'] ?? 0) }}"></i></div>
                            <small class="mt-1">{{ $a['cpu_ghz_usado'] }} de {{ $a['cpu_ghz_total'] }} GHz</small>
                        </div>
                        <div class="vz-metrica">
                            <span>Memoria</span><b class="{{ $tono($a['ram_pct'], 85, 95) }}">{{ $a['ram_pct'] === null ? '—' : $a['ram_pct'] . '%' }}</b>
                            <div class="vz-barra mt-1.5"><i style="width: {{ min(100, $a['ram_pct'] ?? 0) }}%; background: {{ $colorBarra($a['ram_pct'] ?? 0, 85, 95) }}"></i></div>
                            <small class="mt-1">{{ $a['ram_gb_usado'] }} de {{ $a['ram_gb_total'] }} GB</small>
                        </div>
                        <div class="vz-metrica">
                            <span>Máquinas virtuales</span><b>{{ $hostVms->where('estado', 'poweredOn')->count() }}<span class="text-[15px]" style="color: var(--nm-text-muted)"> / {{ $hostVms->count() }}</span></b>
                            <small>encendidas · {{ $hostVms->where('estado', '!=', 'poweredOn')->count() }} apagadas o suspendidas</small>
                        </div>
                        <div class="vz-metrica" title="vCPU de las VMs encendidas contra los hilos físicos del host">
                            <span>vCPU asignadas</span><b>{{ $a['vcpu_encendidas'] }}<span class="text-[15px]" style="color: var(--nm-text-muted)"> / {{ $a['hilos'] ?? '—' }}</span></b>
                            <small>{{ $a['hilos'] ? number_format($a['vcpu_encendidas'] / $a['hilos'], 2) . ' vCPU por hilo físico' : 'hilos del host desconocidos' }}</small>
                        </div>
                        @php $asignadaPct = $a['ram_gb_total'] ? round($a['ram_asignada_gb'] / $a['ram_gb_total'] * 100) : null; @endphp
                        <div class="vz-metrica" title="Memoria configurada en las VMs encendidas contra la memoria física del host">
                            <span>Memoria asignada</span><b class="{{ $tono($asignadaPct, 100, 130) }}">{{ $asignadaPct === null ? '—' : $asignadaPct . '%' }}</b>
                            @if($asignadaPct !== null)<div class="vz-barra mt-1.5"><i style="width: {{ min(100, $asignadaPct) }}%; background: {{ $colorBarra($asignadaPct, 100, 130) }}"></i></div>@endif
                            <small class="mt-1">{{ $a['ram_asignada_gb'] }} GB a VMs de {{ $a['ram_gb_total'] }} GB</small>
                        </div>
                        <div class="vz-metrica">
                            <span>Temperatura</span><b class="{{ $temp && $temp['estado'] === 'red' ? 'vz-t-crit' : ($temp && $temp['estado'] === 'yellow' ? 'vz-t-warn' : '') }}">{{ $temp ? round($temp['valor']) . '°C' : '—' }}</b>
                            <small>{{ $temp ? $temp['nombre'] : 'sin sensores de temperatura' }}</small>
                        </div>
                    </div>
                @endif

                {{-- Gráficas --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-1">
                    @if($a)
                        <div class="lg:col-span-2"><p class="vz-titulo mb-1">CPU y memoria (%)</p><div class="vz-grafica"><canvas data-vz-grafica="cpuram" data-vz-id="{{ $f['id'] }}"></canvas></div></div>
                    @endif
                    <div class="{{ $a ? '' : 'lg:col-span-3' }}"><p class="vz-titulo mb-1">Latencia de ping (ms)</p><div class="vz-grafica"><canvas data-vz-grafica="ping" data-vz-id="{{ $f['id'] }}"></canvas></div></div>
                </div>
                <p class="text-[11px] mb-4" style="color: var(--nm-text-muted)">Últimas 6 h; cada punto es el promedio de 5 min de las lecturas reales y los huecos son periodos sin lectura.</p>

                @if($a)
                    {{-- VMs del host: un módulo por VM; clic para ver su detalle --}}
                    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                        <p class="vz-titulo">Máquinas virtuales de este host · {{ $hostVms->count() }}</p>
                        <div class="vz-leyenda">
                            <span><i class="vz-led-muestra e-ok"></i> Encendida</span>
                            <span><i class="vz-led-muestra e-warn"></i> Suspendida</span>
                            <span><i class="vz-led-muestra e-off"></i> Apagada</span>
                            <span>Barra: CPU de sus vCPU</span>
                        </div>
                    </div>
                    @if($hostVms->isEmpty())
                        <p class="text-xs py-3" style="color: var(--nm-text-muted)">Este host no tiene VMs registradas.</p>
                    @else
                        <div class="vz-vms">
                            @foreach($hostVms as $v)
                                @php
                                    $clave = $f['nombre'] . '|' . $v['nombre'];
                                    $on = ($v['estado'] ?? '') === 'poweredOn';
                                    $led = $on ? 'e-ok' : (($v['estado'] ?? '') === 'suspended' ? 'e-warn' : 'e-off');
                                @endphp
                                <button type="button" class="vz-vm {{ $on ? '' : 'apagada' }} {{ in_array($v['salud'] ?? null, ['yellow', 'red'], true) ? 's-' . $v['salud'] : '' }}"
                                        :class="vm === {{ e(json_encode($clave)) }} && 'sel'" @click="vm = vm === {{ e(json_encode($clave)) }} ? '' : {{ e(json_encode($clave)) }}"
                                        title="{{ $v['nombre'] }}{{ $on && $v['cpu_pct'] !== null ? ' · CPU ' . $v['cpu_pct'] . '%' : '' }}">
                                    <span><i class="{{ $led }} {{ $on && $v['cpu_pct'] !== null ? 'act' : '' }}" @if($on && $ritmo($v['cpu_pct'])) style="--ritmo: {{ $ritmo($v['cpu_pct']) }}" @endif></i><small>{{ $v['nombre'] }}</small></span>
                                    <span class="vz-vm-uso"><i style="width: {{ $on ? min(100, max(2, $v['cpu_pct'] ?? 0)) : 0 }}%; background: {{ $colorBarra($v['cpu_pct'] ?? 0) }}"></i></span>
                                </button>
                            @endforeach
                        </div>
                        <div class="vz-detalle" x-data="{ get v() { return vzVm(vm); } }">
                            <template x-if="v && v.h === {{ e(json_encode($f['nombre'])) }}">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <b class="text-[13px]" x-text="v.n"></b>
                                        <span class="vz-pill" :class="{ poweredOn: 'vz-ok', poweredOff: 'vz-gris', suspended: 'vz-warn' }[v.e] || 'vz-gris'" x-text="{ poweredOn: 'Encendida', poweredOff: 'Apagada', suspended: 'Suspendida' }[v.e] || v.e"></span>
                                        <span x-show="v.s === 'yellow' || v.s === 'red'" class="vz-pill" :class="v.s === 'red' ? 'vz-crit' : 'vz-warn'" x-text="'Estado VMware: ' + (v.s === 'red' ? 'rojo' : 'amarillo')"></span>
                                        <span x-show="v.e === 'poweredOn' && v.l && v.l !== 'gray'" class="vz-pill" :class="v.l === 'green' ? 'vz-ok' : (v.l === 'red' ? 'vz-crit' : 'vz-warn')" x-text="v.l === 'green' ? 'El SO responde' : 'El SO no responde bien'"></span>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-x-4 gap-y-2">
                                        <div><span class="vz-dato">vCPU</span><span class="font-mono" x-text="v.c ?? '—'"></span></div>
                                        <div><span class="vz-dato">CPU</span><span class="font-mono" x-text="v.e === 'poweredOn' && v.mhz !== null ? (v.mhz / 1000).toFixed(2) + ' GHz' + (v.cp !== null ? ' · ' + v.cp + '%' : '') : '—'"></span></div>
                                        <div><span class="vz-dato">Memoria activa</span><span class="font-mono" x-text="v.e === 'poweredOn' && v.ru !== null ? vzGB(v.ru) + ' de ' + vzGB(v.rm) : vzGB(v.rm) + ' asignados'"></span></div>
                                        <div title="Memoria del host que ocupa la VM"><span class="vz-dato">Ocupa en el host</span><span class="font-mono" x-text="v.e === 'poweredOn' && v.rh !== null ? vzGB(v.rh) : '—'"></span></div>
                                        <div class="sm:col-span-2"><span class="vz-dato">IP</span><span class="font-mono text-[11.5px]" x-text="(v.ips && v.ips.length ? v.ips.join(', ') : v.ip) || '—'"></span></div>
                                        <div><span class="vz-dato">Encendida hace</span><span class="font-mono" x-text="v.e === 'poweredOn' && v.up ? vzDur(v.up) : '—'"></span></div>
                                        <div><span class="vz-dato">VMware Tools</span><span x-text="{ guestToolsRunning: 'Activas', guestToolsNotRunning: 'Detenidas', guestToolsExecutingScripts: 'Iniciando' }[v.t] || '—'"></span></div>
                                        <div class="sm:col-span-2"><span class="vz-dato">Sistema operativo</span><span x-text="v.so || '—'"></span></div>
                                        <template x-if="(v.b || 0) > 0 || (v.sw || 0) > 0">
                                            <div class="sm:col-span-2"><span class="vz-dato">Presión de memoria</span><span class="font-mono vz-t-warn" x-text="'balloon ' + vzGB(v.b || 0) + ' · swap ' + vzGB(v.sw || 0)"></span></div>
                                        </template>
                                    </div>
                                    <template x-if="v.d && v.d.length">
                                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-1.5">
                                            <template x-for="d in v.d" :key="d.ruta">
                                                <div>
                                                    <div class="flex justify-between text-[11px]"><span class="font-mono truncate" x-text="d.ruta"></span><span class="font-mono" x-text="vzBytes(d.libre) + ' libres de ' + vzBytes(d.capacidad)"></span></div>
                                                    <div class="vz-barra mt-0.5"><i :style="'width:' + Math.round((1 - d.libre / d.capacidad) * 100) + '%; background:' + vzColor(Math.round((1 - d.libre / d.capacidad) * 100))"></i></div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!(v && v.h === {{ e(json_encode($f['nombre'])) }})" style="color: var(--nm-text-muted)">
                                Elige una VM para ver su detalle (IP, sistema operativo, memoria, discos).
                                @if($topCpu->isNotEmpty())<br><b style="color: var(--nm-text)">Más CPU ahora:</b> {{ $topCpu->take(3)->map(fn ($v) => $v['nombre'] . ' (' . number_format($v['cpu_mhz'] / 1000, 2) . ' GHz)')->implode(' · ') }}@endif
                            </p>
                        </div>
                    @endif

                    {{-- Detalle --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
                        <div>
                            <p class="vz-titulo mb-1">VMs que más CPU usan</p>
                            @if($topCpu->isEmpty())
                                <p class="text-xs py-3" style="color: var(--nm-text-muted)">Sin VMs encendidas con lectura de CPU.</p>
                            @else
                                <table class="vz-tabla">
                                    <tbody>
                                        @foreach($topCpu as $v)
                                            <tr class="cursor-pointer" @click="vm = {{ e(json_encode($f['nombre'] . '|' . $v['nombre'])) }}">
                                                <td class="max-w-[150px] truncate"><b>{{ $v['nombre'] }}</b></td>
                                                <td style="width: 34%"><div class="vz-barra"><i style="width: {{ min(100, max(2, $v['cpu_pct'] ?? 0)) }}%; background: {{ $colorBarra($v['cpu_pct'] ?? 0) }}"></i></div></td>
                                                <td class="num" title="{{ $v['vcpu'] }} vCPU · {{ $v['cpu_pct'] ?? '—' }}% de ellas">{{ number_format($v['cpu_mhz'] / 1000, 2) }} GHz</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                        <div>
                            <p class="vz-titulo mb-1">Datastores que ve este host · {{ $dsHost->count() }}</p>
                            <div class="max-h-[230px] overflow-y-auto">
                                <table class="vz-tabla">
                                    <tbody>
                                        @foreach($dsHost as $ds)
                                            <tr>
                                                <td class="max-w-[130px] truncate" title="{{ $ds['nombre'] }}"><b>{{ $ds['nombre'] }}</b><span class="text-[10.5px]" style="color: var(--nm-text-muted)"> · {{ ($ds['hosts'] ?? 1) > 1 ? 'compartido' : 'local' }}</span></td>
                                                @if(($ds['accesible'] ?? null) === false)
                                                    <td colspan="2"><span class="vz-pill vz-crit">Inaccesible</span></td>
                                                @else
                                                    <td style="width: 30%"><div class="vz-barra"><i style="width: {{ $ds['usado_pct'] ?? 0 }}%; background: {{ $colorBarra($ds['usado_pct'] ?? 0) }}"></i></div></td>
                                                    <td class="num {{ $tono($ds['usado_pct'] ?? null, 80, 90) }}" title="{{ $bytes($ds['libre'] ?? null) }} libres de {{ $bytes($ds['capacidad'] ?? null) }}">{{ $ds['usado_pct'] ?? '—' }}%</td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <p class="vz-titulo mb-1">Sensores del hardware · {{ count($a['sensores']) }}</p>
                            <table class="vz-tabla">
                                <tbody>
                                    @foreach($a['sensores_por_tipo'] as $t)
                                        <tr>
                                            <td>{{ $tipoSensor[$t['tipo']] ?? $t['tipo'] }}</td>
                                            <td class="num" style="color: var(--nm-text-muted)">{{ $t['total'] }}</td>
                                            <td class="text-right">
                                                @if($t['mal'])
                                                    <span class="vz-pill {{ $t['rojos'] ? 'vz-crit' : 'vz-warn' }}" title="{{ $malos->where('tipo', $t['tipo'])->pluck('nombre')->implode(', ') }}">{{ $t['mal'] }} con falla</span>
                                                @else
                                                    <span class="vz-pill vz-ok">OK</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($malos->isNotEmpty())
                                <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)"><b style="color: var(--nm-text)">Con falla:</b> {{ $malos->pluck('nombre')->implode(' · ') }}</p>
                            @endif
                            @if($a['alarmas'])
                                <p class="vz-titulo mt-3 mb-1">Alarmas VMware</p>
                                @foreach($a['alarmas'] as $al)
                                    <p class="text-[12px] flex items-center gap-2"><span class="vz-punto" style="background: {{ ($al['estado'] ?? '') === 'red' ? '#dc2626' : '#d97706' }}"></span>{{ $al['nombre'] }}</p>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            {{-- Datastores --}}
            <div class="floating-card p-5 xl:col-span-2">
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
                                        <td class="text-xs font-mono">{{ $corto($ds['host']) }}</td>
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
            <div class="floating-card p-5">
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
                        <div class="vz-metrica" style="padding: 6px"><span>Latencia</span><b class="text-[15px]">{{ $vc['ping_ms'] === null ? '—' : $vc['ping_ms'] . ' ms' }}</b></div>
                        <div class="vz-metrica" style="padding: 6px"><span>Pérdida</span><b class="text-[15px] {{ $tono($vc['perdida_pct'], 1, 20) }}">{{ $vc['perdida_pct'] === null ? '—' : round($vc['perdida_pct']) . '%' }}</b></div>
                        <div class="vz-metrica" style="padding: 6px"><span>Disp. 24 h</span><b class="text-[15px]">{{ $vc['disponibilidad_24h'] === null ? '—' : $vc['disponibilidad_24h'] . '%' }}</b></div>
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

        {{-- Todas las VMs: filtros arriba, se leen del JSON para poder ordenar sin recargar --}}
        @if($hay_api)
            <div id="vzVms" class="floating-card p-5 scroll-mt-4" x-data="vzTablaVms()">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h2 class="vz-titulo mr-2">Todas las máquinas virtuales · <span x-text="filas.length"></span> de {{ count($vms) }}</h2>
                    <button type="button" class="vz-filtro" :class="estado === 'todas' && 'activo'" @click="estado = 'todas'">Todas</button>
                    <button type="button" class="vz-filtro" :class="estado === 'poweredOn' && 'activo'" @click="estado = 'poweredOn'">Encendidas</button>
                    <button type="button" class="vz-filtro" :class="estado === 'poweredOff' && 'activo'" @click="estado = 'poweredOff'">Apagadas</button>
                    <select x-model="host" class="vz-campo" aria-label="Host">
                        <option value="">Todos los hosts</option>
                        @foreach($filas as $f)<option value="{{ $f['nombre'] }}">{{ $corto($f['nombre']) }}</option>@endforeach
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
                            <tr><th>VM</th><th>Estado</th><th>Host</th><th class="num">vCPU</th><th class="num">CPU</th><th class="num">Memoria activa</th><th>IP</th><th>Sistema operativo</th><th>Tools</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="v in filas" :key="v.k">
                                <tr class="cursor-pointer" @click="sel = vzHostId(v.h); vm = v.k; $nextTick(() => document.getElementById('vzHost' + sel)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" title="Ver en el panel de su host">
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
                <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)">CPU y memoria activa que reporta cada host por su API (lectura cada minuto). Clic en una VM para verla en el panel de su host.</p>
            </div>
        @endif
        <script type="application/json" id="vzHostIds">{!! json_encode(collect($filas)->mapWithKeys(fn ($f) => [$f['nombre'] => $f['id']]), JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Datos de las VMs (los trae la zona que se refresca): se vuelven a leer cuando el JSON cambia
    const vzCache = { el: null, mapa: {} };
    function vzVm(clave) {
        if (!clave) return null;
        const el = document.getElementById('vzVmsDatos');
        if (el !== vzCache.el) {
            vzCache.el = el;
            vzCache.mapa = Object.fromEntries(JSON.parse(el?.textContent || '[]').map(v => [v.k, v]));
        }
        return vzCache.mapa[clave] || null;
    }
    function vzHostId(nombre) { return JSON.parse(document.getElementById('vzHostIds')?.textContent || '{}')[nombre] ?? null; }
    function vzGB(mb) { return mb === null || mb === undefined ? '—' : (mb >= 1024 ? (mb / 1024).toFixed(1) + ' GB' : Math.round(mb) + ' MB'); }
    function vzBytes(b) { return b === null || b === undefined ? '—' : (b >= 1024 ** 4 ? (b / 1024 ** 4).toFixed(2) + ' TB' : (b / 1024 ** 3).toFixed(1) + ' GB'); }
    function vzColor(pct) { return pct >= 90 ? '#dc2626' : (pct >= 80 ? '#d97706' : '#10b981'); }
    function vzDur(seg) {
        const d = Math.floor(seg / 86400), h = Math.floor(seg % 86400 / 3600), m = Math.floor(seg % 3600 / 60);
        return d ? `${d} d ${h} h` : (h ? `${h} h ${m} min` : `${m} min`);
    }

    // Tabla de todas las VMs: los filtros (buscar, host, estado, orden) son del elemento de la zona, así que sobreviven al refresco
    function vzTablaVms() {
        return {
            lista: [],
            init() { this.lista = JSON.parse(document.getElementById('vzVmsDatos')?.textContent || '[]'); },
            get filas() {
                const q = (this.buscar || '').toLowerCase();
                const criterio = { cpu: v => v.e === 'poweredOn' ? (v.mhz ?? -1) : -1, ram: v => v.e === 'poweredOn' ? (v.ru ?? -1) : -1, vcpu: v => v.c ?? -1 }[this.orden];
                const filas = this.lista.filter(v => (this.estado === 'todas' || v.e === this.estado)
                    && (!this.host || v.h === this.host)
                    && (!q || [v.n, v.ip, (v.ips || []).join(' '), v.so, v.h].join(' ').toLowerCase().includes(q)));
                return filas.sort(criterio ? (a, b) => criterio(b) - criterio(a) : (a, b) => a.n.localeCompare(b.n, 'es', { sensitivity: 'base' }));
            },
        };
    }

    // Gráficas del host visible (las de los paneles ocultos no se dibujan: no tienen tamaño)
    (function () {
        const instancias = [];
        const base = (extra = {}) => ({
            responsive: true, maintainAspectRatio: false, animation: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true, labels: { boxWidth: 10, font: { size: 11 } } } },
            scales: { x: { ticks: { maxTicksLimit: 8, font: { size: 10 } }, grid: { display: false } }, y: { beginAtZero: true, ticks: { font: { size: 10 } } } },
            ...extra,
        });
        const linea = (label, data, color, relleno = false) => ({
            label, data, borderColor: color, backgroundColor: relleno ? color + '22' : color, fill: relleno,
            borderWidth: 1.8, pointRadius: data.length === 1 ? 2 : 0, tension: 0.3, spanGaps: false,
        });

        function pintar() {
            instancias.splice(0).forEach(g => g.destroy());
            const fuente = document.getElementById('vzSeries');
            if (!fuente || typeof Chart === 'undefined') return;
            const series = JSON.parse(fuente.textContent || '{}');
            document.querySelectorAll('canvas[data-vz-grafica]').forEach(canvas => {
                if (!canvas.offsetParent) return;
                const s = series[canvas.dataset.vzId] || {};
                let config;
                if (canvas.dataset.vzGrafica === 'cpuram') {
                    const p = s.esxi || [];
                    config = { type: 'line', data: { labels: p.map(x => x.h), datasets: [
                        linea('CPU', p.map(x => x.cpu), '#667d93', true), linea('Memoria', p.map(x => x.ram), '#ff8300'),
                    ] }, options: base({ scales: { x: { ticks: { maxTicksLimit: 8, font: { size: 10 } }, grid: { display: false } }, y: { min: 0, max: 100, ticks: { font: { size: 10 } } } } }) };
                } else {
                    const p = s.ping || [];
                    config = { type: 'line', data: { labels: p.map(x => x.h), datasets: [linea('Latencia (ms)', p.map(x => x.ping), '#00b8c4', true)] },
                               options: base({ plugins: { legend: { display: false } } }) };
                }
                instancias.push(new Chart(canvas, config));
            });
        }

        window.vzPintar = pintar;
        document.addEventListener('DOMContentLoaded', () => setTimeout(pintar, 0));
        document.addEventListener('nm:refrescado', e => { if (e.detail.claves.includes('virtualizacion')) pintar(); });
    })();
</script>
@endsection
