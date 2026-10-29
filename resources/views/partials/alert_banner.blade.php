@php
    if (!isset($notificaciones)) {
        try {
            $notificaciones = (new \App\Http\Controllers\DashboardController)->compilarNotificaciones();
            $unreadNotificaciones = count(array_filter($notificaciones, fn($n) => !$n['leida']));
        } catch (\Throwable $e) {
            $notificaciones = [];
            $unreadNotificaciones = 0;
        }
    }

    // Agrupar alertas iguales (mismo tipo y título) para evitar 30 líneas idénticas en la cinta
    $gruposBanner = [];
    foreach (($notificaciones ?? []) as $n) {
        if (!empty($n['leida'])) continue;
        $k = ($n['tipo'] ?? '') . '|' . ($n['titulo'] ?? '');
        $gruposBanner[$k] ??= ['tipo' => $n['tipo'] ?? '', 'titulo' => $n['titulo'] ?? '', 'items' => []];
        $gruposBanner[$k]['items'][] = $n;
    }
    $ordenTipo = ['critica' => 0, 'advertencia' => 1];
    uasort($gruposBanner, fn ($a, $b) => [($ordenTipo[$a['tipo']] ?? 2), -count($a['items'])] <=> [($ordenTipo[$b['tipo']] ?? 2), -count($b['items'])]);
@endphp

{{-- Banner de Notificaciones Carrusel NOC NEWS LIVE (Full-Width Stream) --}}
@if(isset($unreadNotificaciones) && $unreadNotificaciones > 0 && isset($notificaciones) && count($notificaciones) > 0)
    <div id="dashboardAlertBanner" 
         class="nm-cinta w-full mb-5 rounded-2xl p-2.5 sm:p-3 flex items-center justify-between gap-3 overflow-hidden relative transition-all duration-300 shrink-0 text-white">

        {{-- Insignia fija (vidrio sobre el carmesí; el punto y el contador llevan el rojo) --}}
        <div class="flex items-center gap-2 pl-2.5 pr-1.5 py-1.5 rounded-lg bg-white/15 ring-1 ring-white/25 text-white font-bold text-[11px] uppercase tracking-wider shrink-0 z-10 shadow-sm">
            <span class="relative flex w-2 h-2">
                <span class="absolute inset-0 rounded-full bg-red-300 animate-ping"></span>
                <span class="relative w-2 h-2 rounded-full bg-red-400"></span>
            </span>
            <span class="hidden sm:inline">Alertas en vivo</span>
            <span class="sm:hidden">INCIDENCIAS</span>
            <span class="bg-red-600 px-1.5 py-0.5 rounded-md text-[11px] font-mono ring-1 ring-white/20">{{ $unreadNotificaciones }}</span>
        </div>

        {{-- Separador Visual --}}
        <div class="h-6 w-px bg-white/15 hidden sm:block shrink-0 z-10"></div>

        {{-- Contenedor Ancho del Marquee / Cinta de Noticias --}}
        <div class="nm-cinta-pista flex-1 overflow-hidden relative mx-2 sm:mx-3 flex items-center">

            {{-- Sin gap entre pistas: cada una lleva su espaciado final (pr) para que -50% empalme exacto --}}
            <div id="nocMarqueeTrack" class="animate-noc-marquee flex items-center text-xs font-semibold text-slate-200 whitespace-nowrap">
                {{-- Pista 1 --}}
                <div class="noc-marquee-lane flex items-center gap-10 sm:gap-12 pr-10 sm:pr-12">
                    @foreach($gruposBanner as $g)
                        @php
                            $cnt = count($g['items']);
                            $primero = $g['items'][0];
                            $equipos = array_slice(array_map(fn ($i) => $i['dispositivo'] ?? '', $g['items']), 0, 3);
                        @endphp
                        <div class="inline-flex items-center gap-2.5 cursor-pointer hover:text-amber-300 group" onclick="handleBannerNotifClick(event)">
                            @if($g['tipo'] === 'critica')
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-red-950/35 text-red-100 border border-red-300/50">CRÍTICA</span>
                            @elseif($g['tipo'] === 'advertencia')
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-amber-950/30 text-amber-100 border border-amber-300/55">ADVERTENCIA</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-white/10 text-sky-100 border border-sky-200/45">SISTEMA</span>
                            @endif
                            <span class="font-bold text-white text-xs sm:text-sm tracking-tight group-hover:underline decoration-amber-400 underline-offset-4">{{ $g['titulo'] }}</span>
                            @if($cnt > 1)
                                <span class="px-1.5 py-0.5 rounded-md text-[11px] font-black font-mono bg-white/15 text-white">×{{ $cnt }}</span>
                                <span class="text-white/75 font-mono text-[11px] sm:text-xs">({{ implode(', ', $equipos) }}{{ $cnt > 3 ? ' +' . ($cnt - 3) : '' }})</span>
                            @else
                                <span class="text-white/75 font-mono text-[11px] sm:text-xs">({{ $primero['dispositivo'] }} · {{ $primero['hora_falla'] ?? $primero['tiempo'] }})</span>
                            @endif
                            <span class="text-white/30 font-black mx-2 select-none">•</span>
                        </div>
                    @endforeach
                </div>

                {{-- Pista 2 Duplicada para Loop Infinito Continuo --}}
                <div class="flex items-center gap-10 sm:gap-12 pr-10 sm:pr-12" aria-hidden="true">
                    @foreach($gruposBanner as $g)
                        @php
                            $cnt = count($g['items']);
                            $primero = $g['items'][0];
                            $equipos = array_slice(array_map(fn ($i) => $i['dispositivo'] ?? '', $g['items']), 0, 3);
                        @endphp
                        <div class="inline-flex items-center gap-2.5 cursor-pointer hover:text-amber-300 group" onclick="handleBannerNotifClick(event)">
                            @if($g['tipo'] === 'critica')
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-red-950/35 text-red-100 border border-red-300/50">CRÍTICA</span>
                            @elseif($g['tipo'] === 'advertencia')
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-amber-950/30 text-amber-100 border border-amber-300/55">ADVERTENCIA</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-white/10 text-sky-100 border border-sky-200/45">SISTEMA</span>
                            @endif
                            <span class="font-bold text-white text-xs sm:text-sm tracking-tight group-hover:underline decoration-amber-400 underline-offset-4">{{ $g['titulo'] }}</span>
                            @if($cnt > 1)
                                <span class="px-1.5 py-0.5 rounded-md text-[11px] font-black font-mono bg-white/15 text-white">×{{ $cnt }}</span>
                                <span class="text-white/75 font-mono text-[11px] sm:text-xs">({{ implode(', ', $equipos) }}{{ $cnt > 3 ? ' +' . ($cnt - 3) : '' }})</span>
                            @else
                                <span class="text-white/75 font-mono text-[11px] sm:text-xs">({{ $primero['dispositivo'] }} · {{ $primero['hora_falla'] ?? $primero['tiempo'] }})</span>
                            @endif
                            <span class="text-white/30 font-black mx-2 select-none">•</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Botones de Acción Derechos --}}
        <div class="flex items-center gap-2 shrink-0 z-10">
            <button type="button" onclick="handleBannerNotifClick(event)" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-semibold border border-white/20 transition flex items-center gap-1.5 shadow-sm active:scale-95" title="Ver detalle de todas las alertas" aria-label="Ver detalle de todas las alertas">
                <span>Ver Alertas</span>
                <svg class="w-3.5 h-3.5 text-slate-300" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
            </button>
            <button type="button" onclick="document.getElementById('dashboardAlertBanner').remove()" class="p-1.5 text-slate-400 hover:text-white hover:bg-white/10 rounded-lg transition" title="Ocultar cinta de noticias" aria-label="Ocultar cinta de noticias">
                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
            </button>
        </div>
    </div>
    {{-- La velocidad de la cinta y "Ver Alertas" están en layouts/app: siguen funcionando cuando la cinta se refresca --}}
@endif
{{-- Incidencias sin leer (id y severidad) para el aviso sonoro de layouts/app: suena cuando aparece un id nuevo --}}
@php
    $idsAlertas = collect($notificaciones ?? [])->reject(fn ($n) => !empty($n['leida']))
        ->map(fn ($n) => ['id' => (string) ($n['id'] ?? ''), 'tipo' => $n['tipo'] ?? ''])->values();
@endphp
<template id="nocAlertasIds">{!! json_encode($idsAlertas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</template>
