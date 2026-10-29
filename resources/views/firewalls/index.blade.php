@extends('layouts.app')

@section('title', 'Firewalls')

@section('styles')
<style>
    .fw-kpi { padding: 12px 14px; border-radius: var(--nm-radius-lg); background: var(--nm-surface); border: 1px solid var(--nm-border); }
    .fw-kpi span { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .fw-kpi b { display: block; font-size: 22px; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 2px; }
    .fw-kpi small { display: block; font-size: 11px; color: var(--nm-text-muted); margin-top: 1px; }
    .fw-metrica { padding: 10px 12px; border-radius: var(--nm-radius-md); background: var(--nm-sunken); }
    .fw-metrica > span { display: block; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .fw-metrica b { display: block; font-size: 24px; line-height: 1.15; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 3px; }
    .fw-metrica small { display: block; font-size: 11px; color: var(--nm-text-muted); }
    .fw-tabla { width: 100%; border-collapse: collapse; }
    .fw-tabla th { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--nm-border); white-space: nowrap; }
    .fw-tabla td { padding: 7px 8px; border-bottom: 1px solid var(--nm-border); font-size: 12.5px; color: var(--nm-text); vertical-align: middle; white-space: nowrap; }
    .fw-tabla .num { text-align: right; font-family: ui-monospace, Consolas, monospace; }
    .fw-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 9999px; white-space: nowrap; }
    .fw-ok { color: #047857; background: #d1fae5; } .dark .fw-ok { color: #6ee7b7; background: rgb(6 78 59 / 0.5); }
    .fw-warn { color: #b45309; background: #fef3c7; } .dark .fw-warn { color: #fcd34d; background: rgb(120 53 15 / 0.45); }
    .fw-crit { color: #b91c1c; background: #fee2e2; } .dark .fw-crit { color: #fca5a5; background: rgb(127 29 29 / 0.45); }
    .fw-gris { color: var(--nm-text-muted); background: var(--nm-sunken); }
    .fw-t-warn { color: #b45309 !important; } .dark .fw-t-warn { color: #fcd34d !important; }
    .fw-t-crit { color: #b91c1c !important; } .dark .fw-t-crit { color: #fca5a5 !important; }
    .fw-titulo { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .fw-barra { position: relative; height: 6px; border-radius: 9999px; background: var(--nm-border); overflow: hidden; margin-top: 6px; }
    .fw-barra i { display: block; height: 100%; border-radius: 9999px; }
    .fw-marca { position: absolute; top: -2px; bottom: -2px; width: 2px; background: #dc2626; opacity: .7; }
    .fw-grafica { position: relative; height: 170px; }
    .fw-mini { position: relative; height: 46px; }

    /* Panel frontal dibujado: el chasis es oscuro en los dos temas, como el equipo real */
    .fw-chasis { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border-radius: 10px; overflow-x: auto; min-height: 74px;
        background: linear-gradient(180deg, #2b3038 0%, #1c2027 55%, #13161b 100%); border: 1px solid #3b424d;
        box-shadow: inset 0 1px 0 rgb(255 255 255 / .07), inset 0 -2px 6px rgb(0 0 0 / .55); }
    .fw-placa { flex-shrink: 0; display: flex; flex-direction: column; gap: 6px; padding-right: 9px; border-right: 1px solid #39404a; }
    .fw-placa span { display: flex; align-items: center; gap: 5px; font: 700 9px/1 ui-monospace, Consolas, monospace; color: #9aa4b1; letter-spacing: .06em; text-transform: uppercase; }
    .fw-placa i { width: 6px; height: 6px; border-radius: 50%; background: #3b434e; }
    .fw-grupo { flex-shrink: 0; display: grid; grid-template-rows: auto auto; grid-auto-flow: column; gap: 2px 3px; padding: 4px 5px; border-radius: 6px;
        background: rgb(0 0 0 / .28); box-shadow: inset 0 1px 2px rgb(0 0 0 / .6); }
    .fw-p { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 3px 2px 2px; border-radius: 4px; background: none; border: 0; cursor: pointer; }
    .fw-p:hover { background: rgb(255 255 255 / .07); }
    .fw-p.sel { background: rgb(96 165 250 / .16); box-shadow: 0 0 0 1.5px #60a5fa; }
    .fw-p small { font: 700 9px/1 ui-monospace, Consolas, monospace; color: #8d97a4; max-width: 44px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fw-p.e-up small { color: #d6dde6; }
    .fw-boca { position: relative; display: block; background: #06080b; box-shadow: inset 0 2px 3px rgb(0 0 0 / .9); }
    /* RJ45: contactos dorados arriba y la muesca del seguro abajo */
    .fw-p-rj45 .fw-boca { width: 22px; height: 17px; border: 1px solid #4a525e; border-radius: 2px; }
    .fw-p-rj45 .fw-boca::before { content: ''; position: absolute; top: 3px; left: 4px; right: 4px; height: 3px; background: repeating-linear-gradient(90deg, #b8932a 0 1px, transparent 1px 2.25px); }
    .fw-p-rj45 .fw-boca::after { content: ''; position: absolute; bottom: -1px; left: 7px; width: 6px; height: 4px; background: #1c2027; border: 1px solid #4a525e; border-bottom: 0; }
    /* SFP: jaula metálica con la ranura del módulo */
    .fw-p-sfp .fw-boca { width: 28px; height: 12px; border: 1.5px solid #8b95a1; border-radius: 1px; }
    .fw-p-sfp .fw-boca::before { content: ''; position: absolute; inset: 3px 4px auto; border-top: 1px solid #2f3640; }
    /* Sin SNMP y sin medio conocido: no se dibuja un conector que no se sabe cuál es */
    .fw-p-generico .fw-boca { width: 22px; height: 15px; border: 1px dashed #6b7280; border-radius: 3px; background: #0b0d11; }
    .fw-led { display: block; width: 10px; height: 3px; border-radius: 2px; background: #2a3039; }
    .e-up .fw-led, .fw-led-muestra.e-up { background: #22c55e; box-shadow: 0 0 5px #22c55e; }
    .e-down .fw-led, .fw-led-muestra.e-down { background: #3b434e; }
    .e-deshabilitado .fw-led, .fw-led-muestra.e-deshabilitado { background: #d97706; }
    .e-nd .fw-led, .fw-led-muestra.e-nd { background: transparent; box-shadow: inset 0 0 0 1px #6b7280; }
    .fw-p.act .fw-led { animation: fwActividad var(--ritmo, 1.4s) ease-in-out infinite; }
    @keyframes fwActividad { 50% { opacity: .3; box-shadow: none; } }
    @media (prefers-reduced-motion: reduce) { .fw-p.act .fw-led { animation: none; } }
    .fw-uso { display: block; width: 22px; height: 2px; border-radius: 2px; background: #2a3039; overflow: hidden; }
    .fw-uso i { display: block; height: 100%; }
    .fw-leyenda { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 11px; color: var(--nm-text-muted); }
    .fw-leyenda span { display: inline-flex; align-items: center; gap: 5px; }
    .fw-led-muestra { display: inline-block; width: 10px; height: 3px; border-radius: 2px; }
    .fw-detalle { margin-top: 8px; padding: 7px 10px; border-radius: var(--nm-radius-md); background: var(--nm-sunken); font-size: 12px; color: var(--nm-text); min-height: 34px; }
</style>
@endsection

@section('content')
@php
    $n = fn ($v) => $v === null ? '—' : number_format($v);
    $mbps = fn ($v) => $v === null ? '—' : ($v >= 1000 ? number_format($v / 1000, 2) . ' Gbps' : number_format($v, 1) . ' Mbps');
    $tono = fn ($v, $aviso, $critico) => $v === null ? '' : ($v >= $critico ? 'fw-t-crit' : ($v >= $aviso ? 'fw-t-warn' : ''));
    $colorBarra = fn ($v, $aviso, $critico) => $v >= $critico ? '#dc2626' : ($v >= $aviso ? '#d97706' : '#10b981');
    $conSnmp = collect($filas)->where('snmp', true);
    $sinSnmp = collect($filas)->where('snmp', false);
    // Series para las gráficas (se leen en el script; viajan dentro de la zona que se refresca)
    $series = collect($filas)->mapWithKeys(fn ($f) => [$f['id'] => $f['historia']]);
@endphp
<div class="flex flex-col space-y-4 pb-6">

    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Firewalls FortiGate</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">Métricas de las últimas {{ \App\Services\FirewallsService::HORAS_HISTORIA }} h · se actualiza cada 15 s</p>
            </div>
        </div>
        <a href="{{ route('settings.index', ['section' => 'general']) }}" class="text-xs font-bold hover:underline" style="color: var(--nm-primary)">
            Usuario SNMPv3: {{ $usuarioSnmp ?: 'sin configurar' }} · Ajustes →
        </a>
    </div>

    <div data-refrescar="firewalls" data-refrescar-cada="15" class="flex flex-col space-y-4">
        <script type="application/json" id="fwSeries">{!! json_encode($series, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

        {{-- Indicadores: solo lo que tiene dato --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="fw-kpi"><span>Disponibilidad 24 h</span><b class="{{ $kpi['disponibilidad_24h'] !== null && $kpi['disponibilidad_24h'] < 99 ? 'fw-t-warn' : '' }}">{{ $kpi['disponibilidad_24h'] === null ? '—' : number_format($kpi['disponibilidad_24h'], 2) . '%' }}</b><small>promedio de los {{ $kpi['total'] }} (ping)</small></div>
            <div class="fw-kpi"><span>Latencia promedio</span><b>{{ $kpi['latencia_prom'] === null ? '—' : number_format($kpi['latencia_prom'], 1) . ' ms' }}</b><small>{{ $kpi['total'] - $kpi['caidos'] }} en línea · {{ $kpi['caidos'] }} caídos</small></div>
            <div class="fw-kpi"><span>Alertas activas</span><b class="{{ $kpi['alarmas_criticas'] ? 'fw-t-crit' : ($kpi['alarmas_advertencia'] ? 'fw-t-warn' : '') }}">{{ $kpi['alarmas_criticas'] + $kpi['alarmas_advertencia'] }}</b><small>{{ $kpi['alarmas_criticas'] }} críticas · {{ $kpi['alarmas_advertencia'] }} advertencias</small></div>
            <div class="fw-kpi"><span>Sesiones</span><b>{{ $n($kpi['sesiones']) }}</b><small>{{ $n($kpi['sesiones_nuevas_seg']) }} nuevas por segundo</small></div>
            @if($kpi['tuneles_total'])
                <div class="fw-kpi"><span>Túneles IPsec</span><b class="{{ $kpi['tuneles_arriba'] < $kpi['tuneles_total'] ? 'fw-t-crit' : '' }}">{{ $kpi['tuneles_arriba'] }} / {{ $kpi['tuneles_total'] }}</b><small>arriba / total</small></div>
            @endif
            @if($kpi['sdwan_total'])
                <div class="fw-kpi"><span>Enlaces SD-WAN</span><b class="{{ $kpi['sdwan_caidos'] ? 'fw-t-crit' : '' }}">{{ $kpi['sdwan_total'] - $kpi['sdwan_caidos'] }} / {{ $kpi['sdwan_total'] }}</b><small>vivos / total</small></div>
            @endif
            <div class="fw-kpi"><span>IPS críticas</span><b class="{{ $kpi['ips_criticas_ciclo'] ? 'fw-t-warn' : '' }}">{{ $n($kpi['ips_criticas_ciclo']) }}</b><small>últimos 5 min</small></div>
            <div class="fw-kpi"><span>Con métricas SNMP</span><b>{{ $kpi['con_snmp'] }} / {{ $kpi['total'] }}</b><small>los demás solo por ping</small></div>
        </div>

        {{-- Alertas activas --}}
        @if($alarmas)
            <div class="floating-card p-5">
                <h2 class="fw-titulo mb-2">Alertas activas</h2>
                @foreach($alarmas as $a)
                    <div class="py-2 flex items-start gap-3 border-t first:border-t-0" style="border-color: var(--nm-border)">
                        <span class="fw-pill {{ ($a['severidad'] ?? '') === 'critica' ? 'fw-crit' : 'fw-warn' }} mt-0.5">{{ ($a['severidad'] ?? '') === 'critica' ? 'Crítica' : 'Advertencia' }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold" style="color: var(--nm-text)">{{ $a['titulo'] }}</p>
                            <p class="text-xs" style="color: var(--nm-text-muted)">{{ $a['dispositivo'] }} · {{ $a['mensaje'] }}</p>
                            @if(!empty($a['accion_sugerida']))<p class="text-xs mt-0.5" style="color: var(--nm-text-muted)"><b>Qué hacer:</b> {{ $a['accion_sugerida'] }}</p>@endif
                        </div>
                        <span class="ml-auto text-[11px] font-mono shrink-0" style="color: var(--nm-text-muted)">{{ $a['hace_cuanto'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ===== Un panel completo por cada FortiGate con métricas SNMP ===== --}}
        @foreach($conSnmp as $f)
            @php $ultimo = collect($f['historia'])->last() ?? []; @endphp
            <div class="floating-card p-5">
                {{-- Identificación --}}
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('dispositivos.show', $f['id']) }}" class="text-base font-black hover:underline" style="color: var(--nm-text)">{{ $f['nombre'] }}</a>
                            <span class="fw-pill {{ $f['estado'] === 'offline' ? 'fw-crit' : 'fw-ok' }}">{{ $f['estado'] === 'offline' ? 'Sin conexión' : 'En línea' }}</span>
                            @if($f['ha'])
                                <span class="fw-pill {{ $f['ha']['sincronizado'] === false ? 'fw-crit' : 'fw-gris' }}">HA {{ $f['ha']['modo'] }}{{ $f['ha']['sincronizado'] === false ? ' · sin sincronizar' : ' · sincronizado' }}</span>
                            @endif
                            @if($f['alarmas'])<span class="fw-pill {{ $f['alarmas_criticas'] ? 'fw-crit' : 'fw-warn' }}">{{ $f['alarmas'] }} alerta(s)</span>@endif
                        </div>
                        <p class="text-xs font-mono mt-1" style="color: var(--nm-text-muted)">
                            {{ $f['ip'] }}{{ $f['serie'] ? ' · S/N ' . $f['serie'] : '' }}{{ $f['version'] ? ' · FortiOS ' . $f['version'] : '' }}{{ $f['uptime'] ? ' · encendido ' . $f['uptime'] : '' }}
                        </p>
                    </div>
                    <p class="text-[11px]" style="color: var(--nm-text-muted)">Disponibilidad 24 h: <b class="font-mono">{{ $f['disponibilidad_24h'] === null ? '—' : $f['disponibilidad_24h'] . '%' }}</b> · Latencia: <b class="font-mono">{{ $f['ping_ms'] === null ? '—' : $f['ping_ms'] . ' ms' }}</b></p>
                </div>

                {{-- Métricas actuales --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
                    <div class="fw-metrica">
                        <span>CPU</span><b class="{{ $tono($f['cpu'], $umbralCpu, 95) }}">{{ $f['cpu'] === null ? '—' : round($f['cpu']) . '%' }}</b>
                        @if($f['cpu'] !== null)<div class="fw-barra"><i style="width: {{ min(100, $f['cpu']) }}%; background: {{ $colorBarra($f['cpu'], $umbralCpu, 95) }}"></i></div>@endif
                    </div>
                    <div class="fw-metrica" title="FortiOS entra en modo conserva al {{ $memConserva }} % (marca roja)">
                        <span>Memoria</span><b class="{{ $tono($f['ram'], $memAviso, $memConserva) }}">{{ $f['ram'] === null ? '—' : round($f['ram']) . '%' }}</b>
                        @if($f['ram'] !== null)<div class="fw-barra"><i style="width: {{ min(100, $f['ram']) }}%; background: {{ $colorBarra($f['ram'], $memAviso, $memConserva) }}"></i><span class="fw-marca" style="left: {{ $memConserva }}%"></span></div>@endif
                    </div>
                    <div class="fw-metrica">
                        <span>Sesiones</span><b>{{ $n($f['sesiones']) }}</b>
                        <small>{{ $f['sesiones_max'] !== null ? 'máx. ' . number_format($f['sesiones_max']) . ' en ' . \App\Services\FirewallsService::HORAS_HISTORIA . ' h' : 'concurrentes' }}</small>
                    </div>
                    <div class="fw-metrica"><span>Sesiones nuevas</span><b>{{ $n($f['sesiones_nuevas_seg']) }}</b><small>por segundo</small></div>
                    <div class="fw-metrica" title="Suma de lo que miden sus puertos físicos (promedio de los últimos 5 min)">
                        <span>Tráfico</span>
                        <b class="text-[17px]">↓ {{ $mbps($ultimo['mbps_in'] ?? null) }}</b>
                        <b class="text-[17px]" style="margin-top: 0">↑ {{ $mbps($ultimo['mbps_out'] ?? null) }}</b>
                    </div>
                    <div class="fw-metrica">
                        <span>Disco · Temp.</span><b class="text-[19px]"><span class="{{ $tono($f['disco_pct'], 80, 90) }}">{{ $f['disco_pct'] === null ? '—' : $f['disco_pct'] . '%' }}</span> · <span class="{{ $tono($f['temp_max'], 70, 85) }}">{{ $f['temp_max'] === null ? '—' : round($f['temp_max']) . '°' }}</span></b>
                        <small>{{ count($f['sensores_lista']) }} sensores · {{ collect($f['sensores_lista'])->where('alarma', true)->count() }} en alarma</small>
                    </div>
                </div>

                {{-- Gráficas --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                    <div><p class="fw-titulo mb-1">CPU y memoria (%) <span class="normal-case font-semibold tracking-normal">· modo conserva al {{ $memConserva }} %</span></p><div class="fw-grafica"><canvas data-fw-grafica="cpuram" data-fw-id="{{ $f['id'] }}"></canvas></div></div>
                    <div><p class="fw-titulo mb-1">Sesiones concurrentes</p><div class="fw-grafica"><canvas data-fw-grafica="sesiones" data-fw-id="{{ $f['id'] }}"></canvas></div></div>
                    <div><p class="fw-titulo mb-1">Tráfico (Mbps)</p><div class="fw-grafica"><canvas data-fw-grafica="trafico" data-fw-id="{{ $f['id'] }}"></canvas></div></div>
                </div>
                <p class="text-[11px] -mt-2 mb-4" style="color: var(--nm-text-muted)">Cada punto es el promedio de 5 min de las mediciones reales; los huecos son periodos sin medición.</p>

                {{-- Detalle --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div>
                        <p class="fw-titulo mb-1">Interfaces con más uso</p>
                        @if(!$f['interfaces'])
                            <p class="text-xs py-3" style="color: var(--nm-text-muted)">Sin medición de uso por interfaz todavía.</p>
                        @else
                            <table class="fw-tabla">
                                <tbody>
                                    @foreach($f['interfaces'] as $i)
                                        <tr>
                                            <td><b>{{ $i['nombre'] }}</b>@if($i['alias'])<span class="text-[11px]" style="color: var(--nm-text-muted)"> · {{ $i['alias'] }}</span>@endif</td>
                                            <td style="width: 38%">
                                                <div class="fw-barra" style="margin-top: 0"><i style="width: {{ min(100, max(2, $i['util_pct'])) }}%; background: {{ $colorBarra($i['util_pct'], 70, 90) }}"></i></div>
                                            </td>
                                            <td class="num {{ $tono($i['util_pct'], 70, 90) }}" title="{{ $i['velocidad_mbps'] ? 'Puerto de ' . $mbps($i['velocidad_mbps']) : '' }}">{{ round($i['util_pct'], 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                    <div>
                        <p class="fw-titulo mb-1">Clúster HA</p>
                        @if(!$f['ha'] || !$f['ha']['miembros'])
                            <p class="text-xs py-3" style="color: var(--nm-text-muted)">{{ $f['ha'] ? 'Trabaja solo (standalone).' : 'Sin datos de alta disponibilidad.' }}</p>
                        @else
                            <table class="fw-tabla">
                                <thead><tr><th>Miembro</th><th>Sync</th><th class="num">CPU</th><th class="num">RAM</th><th class="num">Sesiones</th></tr></thead>
                                <tbody>
                                    @foreach($f['ha']['miembros'] as $m)
                                        <tr>
                                            <td class="font-mono text-xs">{{ $m['nombre'] }}</td>
                                            <td>{!! ($m['sincronizado'] ?? false) ? '<span class="fw-pill fw-ok">✓</span>' : '<span class="fw-pill fw-crit">✗</span>' !!}</td>
                                            <td class="num {{ $tono($m['cpu'] ?? null, $umbralCpu, 95) }}">{{ ($m['cpu'] ?? null) === null ? '—' : $m['cpu'] . '%' }}</td>
                                            <td class="num {{ $tono($m['ram'] ?? null, $memAviso, $memConserva) }}">{{ ($m['ram'] ?? null) === null ? '—' : $m['ram'] . '%' }}</td>
                                            <td class="num">{{ $n($m['sesiones'] ?? null) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                    <div>
                        <p class="fw-titulo mb-1">Seguridad y VPN</p>
                        @php $s = $f['seguridad'] ?? []; @endphp
                        <table class="fw-tabla">
                            <tbody>
                                <tr><td>IPS detectadas (5 min)</td><td class="num">{{ $n($s['ips_detectadas_ciclo'] ?? null) }}</td></tr>
                                <tr><td>IPS críticas (5 min)</td><td class="num {{ ($s['ips_criticas_ciclo'] ?? 0) ? 'fw-t-warn' : '' }}">{{ $n($s['ips_criticas_ciclo'] ?? null) }}</td></tr>
                                <tr><td>Virus detectados (5 min)</td><td class="num {{ ($s['av_detectados_ciclo'] ?? 0) ? 'fw-t-warn' : '' }}">{{ $n($s['av_detectados_ciclo'] ?? null) }}</td></tr>
                                <tr><td>Túneles IPsec arriba</td><td class="num {{ $f['tuneles_total'] && $f['tuneles_arriba'] < $f['tuneles_total'] ? 'fw-t-crit' : '' }}">{{ $f['tuneles_total'] ? $f['tuneles_arriba'] . ' / ' . $f['tuneles_total'] : 'no configurados' }}</td></tr>
                                <tr><td>Enlaces SD-WAN vivos</td><td class="num {{ $f['sdwan_caidos'] ? 'fw-t-crit' : '' }}">{{ $f['sdwan_total'] ? ($f['sdwan_total'] - $f['sdwan_caidos']) . ' / ' . $f['sdwan_total'] : 'no configurado' }}</td></tr>
                                <tr><td>Usuarios VPN SSL</td><td class="num">{{ $n($f['vpn_ssl_usuarios']) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Túneles y SD-WAN de este equipo, solo si los tiene --}}
                @if($f['tuneles_lista'] || $f['sdwan_lista'])
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                        @if($f['tuneles_lista'])
                            <div>
                                <p class="fw-titulo mb-1">Túneles IPsec</p>
                                <table class="fw-tabla">
                                    <thead><tr><th>Túnel</th><th>Remoto</th><th>Estado</th><th class="num">↓</th><th class="num">↑</th></tr></thead>
                                    <tbody>
                                        @foreach(collect($f['tuneles_lista'])->sortBy(fn ($t) => ($t['estado'] ?? '') === 'up' ? 1 : 0)->take(15) as $t)
                                            <tr>
                                                <td class="text-xs">{{ $t['fase1'] }}{{ !empty($t['fase2']) ? ' / ' . $t['fase2'] : '' }}</td>
                                                <td class="font-mono text-xs">{{ $t['remoto'] ?? '—' }}</td>
                                                <td>{!! ($t['estado'] ?? '') === 'up' ? '<span class="fw-pill fw-ok">Arriba</span>' : '<span class="fw-pill fw-crit">Caído</span>' !!}</td>
                                                <td class="num">{{ $mbps($t['mbps_in'] ?? null) }}</td>
                                                <td class="num">{{ $mbps($t['mbps_out'] ?? null) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($f['sdwan_lista'])
                            <div>
                                <p class="fw-titulo mb-1">SLA de SD-WAN</p>
                                <table class="fw-tabla">
                                    <thead><tr><th>Health-check</th><th>Estado</th><th class="num">Latencia</th><th class="num">Jitter</th><th class="num">Pérdida</th></tr></thead>
                                    <tbody>
                                        @foreach($f['sdwan_lista'] as $e)
                                            <tr>
                                                <td class="text-xs">{{ $e['nombre'] }}{{ !empty($e['interfaz']) ? ' · ' . $e['interfaz'] : '' }}</td>
                                                <td>{!! ($e['estado'] ?? '') === 'dead' ? '<span class="fw-pill fw-crit">Caído</span>' : '<span class="fw-pill fw-ok">Vivo</span>' !!}</td>
                                                <td class="num">{{ ($e['latencia_ms'] ?? null) === null ? '—' : $e['latencia_ms'] . ' ms' }}</td>
                                                <td class="num">{{ ($e['jitter_ms'] ?? null) === null ? '—' : $e['jitter_ms'] . ' ms' }}</td>
                                                <td class="num">{{ ($e['perdida_pct'] ?? null) === null ? '—' : $e['perdida_pct'] . '%' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        {{-- ===== Los que no responden SNMP: lo que sí se mide por ping ===== --}}
        @if($sinSnmp->isNotEmpty())
            <div class="floating-card p-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <h2 class="fw-titulo">Solo por ping · {{ $sinSnmp->count() }} FortiGate sin SNMP</h2>
                    <p class="text-[11px]" style="color: var(--nm-text-muted)">Latencia medida de las últimas {{ \App\Services\FirewallsService::HORAS_HISTORIA }} h (promedio cada 5 min).</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                    @foreach($sinSnmp as $f)
                        <a href="{{ route('dispositivos.show', $f['id']) }}" class="block p-3 rounded-xl border hover:shadow-sm transition" style="border-color: var(--nm-border); background: var(--nm-surface)">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold truncate" style="color: var(--nm-text)">{{ $f['nombre'] }}</p>
                                    <p class="text-[11px] font-mono" style="color: var(--nm-text-muted)">{{ $f['ip'] }}</p>
                                </div>
                                <span class="fw-pill {{ $f['estado'] === 'offline' ? 'fw-crit' : 'fw-ok' }}">{{ $f['estado'] === 'offline' ? 'Caído' : 'En línea' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 mt-2">
                                <div><p class="text-[10px] font-bold uppercase" style="color: var(--nm-text-muted)">Latencia</p><p class="text-sm font-mono font-bold" style="color: var(--nm-text)">{{ $f['ping_ms'] === null ? '—' : $f['ping_ms'] . ' ms' }}</p></div>
                                <div><p class="text-[10px] font-bold uppercase" style="color: var(--nm-text-muted)">Pérdida</p><p class="text-sm font-mono font-bold {{ $tono($f['perdida_pct'], 1, 20) }}" style="color: var(--nm-text)">{{ $f['perdida_pct'] === null ? '—' : round($f['perdida_pct']) . '%' }}</p></div>
                                <div><p class="text-[10px] font-bold uppercase" style="color: var(--nm-text-muted)">Disp. 24 h</p><p class="text-sm font-mono font-bold {{ $f['disponibilidad_24h'] !== null && $f['disponibilidad_24h'] < 99 ? 'fw-t-warn' : '' }}" style="color: var(--nm-text)">{{ $f['disponibilidad_24h'] === null ? '—' : $f['disponibilidad_24h'] . '%' }}</p></div>
                            </div>
                            <div class="fw-mini mt-1"><canvas data-fw-grafica="ping" data-fw-id="{{ $f['id'] }}"></canvas></div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @include('firewalls.partials.puertos')

</div>
@endsection

@section('scripts')
<script>
    // Gráficas de cada FortiGate. La zona se reemplaza al refrescarse, así que se vuelven a dibujar con los datos nuevos.
    (function () {
        const instancias = [];
        const base = (extra = {}) => ({
            responsive: true, maintainAspectRatio: false, animation: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true, labels: { boxWidth: 10, font: { size: 11 } } } },
            scales: { x: { ticks: { maxTicksLimit: 7, font: { size: 10 } }, grid: { display: false } }, y: { beginAtZero: true, ticks: { font: { size: 10 } } } },
            ...extra,
        });
        const linea = (label, data, color, relleno = false) => ({
            label, data, borderColor: color, backgroundColor: relleno ? color + '22' : color, fill: relleno,
            borderWidth: 1.8, pointRadius: 0, tension: 0.3, spanGaps: false,
        });

        function pintar() {
            instancias.splice(0).forEach(g => g.destroy());
            const fuente = document.getElementById('fwSeries');
            if (!fuente || typeof Chart === 'undefined') return;
            const series = JSON.parse(fuente.textContent || '{}');

            document.querySelectorAll('canvas[data-fw-grafica]').forEach(canvas => {
                const puntos = series[canvas.dataset.fwId] || [];
                const etiquetas = puntos.map(p => p.h);   // hora local ya formateada por el servidor
                const col = k => puntos.map(p => p[k]);
                let config;
                switch (canvas.dataset.fwGrafica) {
                    case 'cpuram':
                        config = { type: 'line', data: { labels: etiquetas, datasets: [
                            linea('CPU', col('cpu'), '#667d93', true),
                            linea('Memoria', col('ram'), '#ff8300'),
                        ] }, options: base({ scales: { x: { ticks: { maxTicksLimit: 7, font: { size: 10 } }, grid: { display: false } }, y: { min: 0, max: 100, ticks: { font: { size: 10 } } } } }) };
                        break;
                    case 'sesiones':
                        config = { type: 'line', data: { labels: etiquetas, datasets: [linea('Sesiones', col('sesiones'), '#465055', true)] }, options: base({ plugins: { legend: { display: false } } }) };
                        break;
                    case 'trafico':
                        config = { type: 'line', data: { labels: etiquetas, datasets: [
                            linea('↓ Entrada', col('mbps_in'), '#667d93', true), linea('↑ Salida', col('mbps_out'), '#00b8c4', true),
                        ] }, options: base() };
                        break;
                    case 'ping':
                        // Mini gráfica sin ejes: solo la forma de la latencia
                        config = { type: 'line', data: { labels: etiquetas, datasets: [linea('Latencia (ms)', col('ping'), '#667d93', true)] },
                                   options: base({ plugins: { legend: { display: false }, tooltip: { enabled: true } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } } }) };
                        break;
                }
                if (config) instancias.push(new Chart(canvas, config));
            });
        }

        document.addEventListener('DOMContentLoaded', pintar);
        document.addEventListener('nm:refrescado', e => { if (e.detail.claves.includes('firewalls')) pintar(); });
    })();
</script>
@endsection
