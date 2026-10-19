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
         class="w-full mb-5 rounded-2xl border border-white/10 border-l-4 border-l-red-500 p-2.5 sm:p-3 flex items-center justify-between gap-3 overflow-hidden relative transition-all duration-300 shrink-0 text-white"
         style="background: var(--nm-metal-sheen), linear-gradient(90deg, var(--nm-navy-900) 0%, var(--nm-navy-800) 35%, var(--nm-navy-700) 100%); box-shadow: var(--nm-shadow-lg);">
        
        {{-- Glow de Fondo Sutil --}}
        <div class="absolute inset-0 bg-gradient-to-r from-red-600/20 via-transparent to-transparent pointer-events-none"></div>

        {{-- Badge Fijo Izquierdo --}}
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-red-600 text-white font-bold text-[11px] uppercase tracking-wider shrink-0 z-10">
            <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
            <span class="hidden sm:inline">Alertas en vivo</span>
            <span class="sm:hidden">INCIDENCIAS</span>
            <span class="bg-black/50 px-1.5 py-0.5 rounded-md text-[11px] font-mono border border-white/20">{{ $unreadNotificaciones }}</span>
        </div>

        {{-- Separador Visual --}}
        <div class="h-6 w-px bg-white/15 hidden sm:block shrink-0 z-10"></div>

        {{-- Contenedor Ancho del Marquee / Cinta de Noticias --}}
        <div class="flex-1 overflow-hidden relative mx-2 sm:mx-3 flex items-center">
            {{-- Máscaras de desvanecimiento laterales para un scrolling fluido --}}
            <div class="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-indigo-900 to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-indigo-700 to-transparent z-10 pointer-events-none"></div>

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
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-red-500/30 text-red-300 border border-red-500/60">CRÍTICA</span>
                            @elseif($g['tipo'] === 'advertencia')
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-amber-500/30 text-amber-300 border border-amber-500/60">ADVERTENCIA</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-blue-500/30 text-blue-300 border border-blue-500/60">SISTEMA</span>
                            @endif
                            <span class="font-bold text-white text-xs sm:text-sm tracking-tight group-hover:underline decoration-amber-400 underline-offset-4">{{ $g['titulo'] }}</span>
                            @if($cnt > 1)
                                <span class="px-1.5 py-0.5 rounded-md text-[11px] font-black font-mono bg-white/15 text-white">×{{ $cnt }}</span>
                                <span class="text-slate-300 font-mono text-[11px] sm:text-xs">({{ implode(', ', $equipos) }}{{ $cnt > 3 ? ' +' . ($cnt - 3) : '' }})</span>
                            @else
                                <span class="text-slate-300 font-mono text-[11px] sm:text-xs">({{ $primero['dispositivo'] }} · {{ $primero['hora_falla'] ?? $primero['tiempo'] }})</span>
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
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-red-500/30 text-red-300 border border-red-500/60">CRÍTICA</span>
                            @elseif($g['tipo'] === 'advertencia')
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-amber-500/30 text-amber-300 border border-amber-500/60">ADVERTENCIA</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-blue-500/30 text-blue-300 border border-blue-500/60">SISTEMA</span>
                            @endif
                            <span class="font-bold text-white text-xs sm:text-sm tracking-tight group-hover:underline decoration-amber-400 underline-offset-4">{{ $g['titulo'] }}</span>
                            @if($cnt > 1)
                                <span class="px-1.5 py-0.5 rounded-md text-[11px] font-black font-mono bg-white/15 text-white">×{{ $cnt }}</span>
                                <span class="text-slate-300 font-mono text-[11px] sm:text-xs">({{ implode(', ', $equipos) }}{{ $cnt > 3 ? ' +' . ($cnt - 3) : '' }})</span>
                            @else
                                <span class="text-slate-300 font-mono text-[11px] sm:text-xs">({{ $primero['dispositivo'] }} · {{ $primero['hora_falla'] ?? $primero['tiempo'] }})</span>
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

    <script>
        // Velocidad constante y legible sin importar cuántas alertas haya
        (function () {
            const PX_POR_SEGUNDO = 40;
            const track = document.getElementById('nocMarqueeTrack');
            if (!track) return;
            const ajustar = () => {
                const lane = track.querySelector('.noc-marquee-lane');
                if (!lane) return;
                const segundos = Math.max(20, lane.offsetWidth / PX_POR_SEGUNDO);
                track.style.setProperty('--noc-marquee-duration', segundos.toFixed(1) + 's');
            };
            ajustar();
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(ajustar);
            let t;
            window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(ajustar, 200); });
        })();

        // El centro de alertas vive en el dashboard: ahí se abre; desde otras páginas se va al dashboard y se abre al cargar
        if (typeof window.handleBannerNotifClick !== 'function') {
            window.handleBannerNotifClick = function(e) {
                if (typeof window.toggleNotifDropdown === 'function' && document.getElementById('notifDropdownMenu')) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    window.toggleNotifDropdown(e);
                } else {
                    window.location.href = "{{ route('dashboard') }}#alertas";
                }
            };
        }
    </script>
@endif
