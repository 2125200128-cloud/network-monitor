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
@endphp

{{-- Banner de Notificaciones Carrusel NOC NEWS LIVE (Full-Width Stream) --}}
@if(isset($unreadNotificaciones) && $unreadNotificaciones > 0 && isset($notificaciones) && count($notificaciones) > 0)
    <div id="dashboardAlertBanner" 
         class="w-full mb-5 rounded-2xl border border-red-500/60 p-2.5 sm:p-3 flex items-center justify-between gap-3 shadow-2xl overflow-hidden relative transition-all duration-300 shrink-0"
         style="background: linear-gradient(90deg, #1c0b10 0%, #0f172a 20%, #0b1120 50%, #0f172a 80%, #1c0b10 100%); box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.5);">
        
        {{-- Glow de Fondo Sutil --}}
        <div class="absolute inset-0 bg-gradient-to-r from-red-600/15 via-transparent to-red-600/15 pointer-events-none"></div>

        {{-- Badge Fijo Izquierdo --}}
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 text-white font-black text-[11px] uppercase tracking-wider shrink-0 shadow-lg shadow-red-600/40 animate-pulse z-10 border border-red-400/40">
            <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
            <span class="hidden sm:inline">NOC NEWS LIVE</span>
            <span class="sm:hidden">INCIDENCIAS</span>
            <span class="bg-black/50 px-1.5 py-0.5 rounded-md text-[10px] font-mono border border-white/20">{{ $unreadNotificaciones }}</span>
        </div>

        {{-- Separador Visual --}}
        <div class="h-6 w-px bg-red-500/40 hidden sm:block shrink-0 z-10"></div>

        {{-- Contenedor Ancho del Marquee / Cinta de Noticias --}}
        <div class="flex-1 overflow-hidden relative mx-2 sm:mx-3 flex items-center">
            {{-- Máscaras de desvanecimiento laterales para un scrolling fluido --}}
            <div class="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-[#0f172a] to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-[#0f172a] to-transparent z-10 pointer-events-none"></div>

            <div class="animate-noc-marquee flex items-center gap-10 sm:gap-12 text-xs font-semibold text-slate-200 whitespace-nowrap">
                {{-- Pista 1 --}}
                <div class="flex items-center gap-10 sm:gap-12">
                    @foreach($notificaciones as $n)
                        @if(!$n['leida'])
                            <div class="inline-flex items-center gap-2.5 cursor-pointer hover:text-amber-300 transition group" onclick="handleBannerNotifClick(event)">
                                @if($n['tipo'] === 'critica')
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-red-500/30 text-red-300 border border-red-500/60 group-hover:bg-red-500/50 shadow-sm">🔴 CRÍTICA</span>
                                @elseif($n['tipo'] === 'advertencia')
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-amber-500/30 text-amber-300 border border-amber-500/60 group-hover:bg-amber-500/50 shadow-sm">🟡 ADVERTENCIA</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-blue-500/30 text-blue-300 border border-blue-500/60 group-hover:bg-blue-500/50 shadow-sm">⚡ SISTEMA</span>
                                @endif
                                <span class="font-bold text-white text-xs sm:text-sm tracking-tight group-hover:underline decoration-amber-400 underline-offset-4">{{ $n['titulo'] }}</span>
                                <span class="text-slate-300 font-mono text-[11px] sm:text-xs">({{ $n['dispositivo'] }} · {{ $n['hora_falla'] ?? $n['tiempo'] }})</span>
                                <span class="text-rose-500 font-black mx-2 select-none">///</span>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Pista 2 Duplicada para Loop Infinito Continuo --}}
                <div class="flex items-center gap-10 sm:gap-12">
                    @foreach($notificaciones as $n)
                        @if(!$n['leida'])
                            <div class="inline-flex items-center gap-2.5 cursor-pointer hover:text-amber-300 transition group" onclick="handleBannerNotifClick(event)">
                                @if($n['tipo'] === 'critica')
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-red-500/30 text-red-300 border border-red-500/60 group-hover:bg-red-500/50 shadow-sm">🔴 CRÍTICA</span>
                                @elseif($n['tipo'] === 'advertencia')
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-amber-500/30 text-amber-300 border border-amber-500/60 group-hover:bg-amber-500/50 shadow-sm">🟡 ADVERTENCIA</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black bg-blue-500/30 text-blue-300 border border-blue-500/60 group-hover:bg-blue-500/50 shadow-sm">⚡ SISTEMA</span>
                                @endif
                                <span class="font-bold text-white text-xs sm:text-sm tracking-tight group-hover:underline decoration-amber-400 underline-offset-4">{{ $n['titulo'] }}</span>
                                <span class="text-slate-300 font-mono text-[11px] sm:text-xs">({{ $n['dispositivo'] }} · {{ $n['hora_falla'] ?? $n['tiempo'] }})</span>
                                <span class="text-rose-500 font-black mx-2 select-none">///</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Botones de Acción Derechos --}}
        <div class="flex items-center gap-2 shrink-0 z-10">
            <button type="button" onclick="handleBannerNotifClick(event)" class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/20 transition flex items-center gap-1.5 shadow-sm active:scale-95" title="Ver detalle de todas las alertas">
                <span>Ver Alertas</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <button type="button" onclick="document.getElementById('dashboardAlertBanner').remove()" class="p-1.5 text-slate-400 hover:text-white hover:bg-white/10 rounded-lg transition" title="Ocultar cinta de noticias">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>

    <script>
        if (typeof window.handleBannerNotifClick !== 'function') {
            window.handleBannerNotifClick = function(e) {
                if (typeof window.toggleNotifDropdown === 'function') {
                    window.toggleNotifDropdown(e);
                } else {
                    window.location.href = "{{ route('settings.index', ['section' => 'notificaciones']) }}";
                }
            };
        }
    </script>
@endif
