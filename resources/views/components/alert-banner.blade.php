@inject('diagnosisService', 'App\Services\AlarmDiagnosisService')
@php
    $notificaciones = $diagnosisService->diagnosticarAlarmasRed();
    $unreadNotificaciones = count(array_filter($notificaciones, fn($n) => !$n['leida']));
@endphp

@if($unreadNotificaciones > 0)
    <style>
        @keyframes nocMarquee {
            0% { transform: translateX(0%); }
            100% { transform: translateX(-50%); }
        }
        .animate-noc-marquee {
            display: flex;
            width: max-content;
            animation: nocMarquee 300s linear infinite;
        }
        .animate-noc-marquee:hover {
            animation-play-state: paused;
        }
        
        /* Modal transitions */
        .global-notif-modal {
            transition: all 0.2s ease-in-out;
        }
        .global-notif-modal.hidden {
            opacity: 0;
            pointer-events: none;
            transform: translateY(-10px);
            display: block; /* keep block to allow transition, hide via opacity/pointer */
        }
    </style>

    {{-- BARRA DE ALERTAS --}}
    <div id="dashboardAlertBanner" class="mb-6 rounded-2xl bg-gradient-to-r from-red-950 via-slate-900 to-slate-950 border border-red-500/40 p-4 sm:p-5 flex items-center justify-between gap-4 shadow-xl overflow-hidden relative backdrop-blur-md z-40">
        {{-- Badge Fijo Izquierdo --}}
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-red-600/90 text-white font-black text-xs uppercase tracking-wider shrink-0 shadow-md animate-pulse z-10">
            <span class="w-2.5 h-2.5 rounded-full bg-white animate-ping"></span>
            <span class="hidden sm:inline">NOC NEWS LIVE</span>
            <span class="sm:hidden">INCIDENCIAS</span>
            <span class="bg-black/40 px-1.5 py-0.5 rounded-md text-[11px] font-mono">{{ $unreadNotificaciones }}</span>
        </div>

        {{-- Contenedor del Marquee / Cinta de Noticias --}}
        <div class="flex-1 overflow-hidden relative mx-2 flex items-center">
            <div class="animate-noc-marquee flex items-center gap-8 text-sm font-bold text-slate-200 whitespace-nowrap">
                {{-- Pista 1 --}}
                <div class="flex items-center gap-8">
                    @foreach($notificaciones as $n)
                        @if(!$n['leida'])
                            <div class="inline-flex items-center gap-2 cursor-pointer hover:text-amber-300 transition" onclick="toggleGlobalNotifModal(event)">
                                @if($n['tipo'] === 'critica')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-red-500/30 text-red-300 border border-red-500/40">🔴 CRÍTICA</span>
                                @elseif($n['tipo'] === 'advertencia')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500/30 text-amber-300 border border-amber-500/40">🟡 ADVERTENCIA</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-blue-500/30 text-blue-300 border border-blue-500/40">⚡ SISTEMA</span>
                                @endif
                                <span class="font-extrabold text-white">{{ $n['titulo'] }}</span>
                                <span class="text-slate-300 font-mono text-[11px]">({{ $n['dispositivo'] }} · {{ $n['hora_falla'] ?? $n['tiempo'] }})</span>
                                <span class="text-red-500 font-extrabold mx-2">///</span>
                            </div>
                        @endif
                    @endforeach
                </div>
                {{-- Pista 2 --}}
                <div class="flex items-center gap-8">
                    @foreach($notificaciones as $n)
                        @if(!$n['leida'])
                            <div class="inline-flex items-center gap-2 cursor-pointer hover:text-amber-300 transition" onclick="toggleGlobalNotifModal(event)">
                                @if($n['tipo'] === 'critica')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-red-500/30 text-red-300 border border-red-500/40">🔴 CRÍTICA</span>
                                @elseif($n['tipo'] === 'advertencia')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500/30 text-amber-300 border border-amber-500/40">🟡 ADVERTENCIA</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-blue-500/30 text-blue-300 border border-blue-500/40">⚡ SISTEMA</span>
                                @endif
                                <span class="font-extrabold text-white">{{ $n['titulo'] }}</span>
                                <span class="text-slate-300 font-mono text-[11px]">({{ $n['dispositivo'] }} · {{ $n['hora_falla'] ?? $n['tiempo'] }})</span>
                                <span class="text-red-500 font-extrabold mx-2">///</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Botones de Acción --}}
        <div class="flex items-center gap-2 shrink-0 z-10">
            <button type="button" onclick="toggleGlobalNotifModal(event)" class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/10 transition flex items-center gap-1.5">
                <span>Ver Alertas</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <button type="button" onclick="document.getElementById('dashboardAlertBanner').remove()" class="p-1.5 text-slate-400 hover:text-white rounded-lg transition" title="Ocultar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>

    {{-- MODAL FLOTANTE GLOBAL DE NOTIFICACIONES --}}
    <div id="globalNotifModal" class="global-notif-modal hidden fixed top-20 right-4 sm:right-8 w-80 sm:w-96 md:w-[440px] bg-white dark:bg-[#0d1017] border border-gray-100 dark:border-slate-800 rounded-2xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.5)] z-[9999] overflow-hidden flex flex-col">
        {{-- Header --}}
        <div class="p-4 bg-gray-50/80 dark:bg-slate-900/40 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-[#3b5998] text-white flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white leading-tight">Centro de Notificaciones</h3>
                    <p class="text-[11px] text-gray-500">Alertas NOC en tiempo real</p>
                </div>
            </div>
            <button onclick="toggleGlobalNotifModal(event)" class="text-gray-400 hover:text-gray-600 transition p-1 rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- Tabs --}}
        <div class="px-4 py-2 bg-gray-50/70 dark:bg-[#090b10] border-b border-gray-100 dark:border-slate-800/60 flex gap-2 overflow-x-auto">
            <button onclick="filterGlobalNotifs('all')" class="text-xs font-bold text-[#3b5998] bg-white dark:bg-slate-800 px-2 py-1 rounded shadow-sm border border-gray-200 dark:border-slate-700">Todas</button>
            <button onclick="filterGlobalNotifs('critica')" class="text-xs font-semibold text-gray-600 dark:text-slate-400 hover:bg-slate-100 px-2 py-1 rounded flex items-center gap-1"><span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span> Críticas</button>
        </div>

        {{-- Lista --}}
        <div class="max-h-[380px] overflow-y-auto divide-y divide-gray-100 dark:divide-slate-800/50 p-1 custom-scrollbar">
            @forelse($notificaciones as $n)
                <div class="global-notif-item p-3 hover:bg-slate-50 dark:hover:bg-slate-800/40 flex gap-3 items-start" data-tipo="{{ $n['tipo'] }}">
                    @if($n['tipo'] === 'critica')
                        <div class="w-8 h-8 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 flex items-center justify-center shrink-0">🔴</div>
                    @elseif($n['tipo'] === 'advertencia')
                        <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 flex items-center justify-center shrink-0">🟡</div>
                    @else
                        <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-blue-600 flex items-center justify-center shrink-0">⚡</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <h4 class="text-xs font-extrabold text-gray-900 dark:text-slate-100">{{ $n['titulo'] }}</h4>
                        <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">{{ $n['mensaje'] ?? $n['dispositivo'] }}</p>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-gray-500 text-xs">No hay alertas.</div>
            @endforelse
        </div>
    </div>

    <script>
        function toggleGlobalNotifModal(event) {
            if(event) event.stopPropagation();
            const modal = document.getElementById('globalNotifModal');
            if(modal) {
                if(modal.classList.contains('hidden')) {
                    modal.classList.remove('hidden');
                    modal.style.opacity = '1';
                    modal.style.pointerEvents = 'auto';
                } else {
                    modal.classList.add('hidden');
                }
            }
        }

        function filterGlobalNotifs(tipo) {
            const items = document.querySelectorAll('.global-notif-item');
            items.forEach(item => {
                if (tipo === 'all' || item.dataset.tipo === tipo) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Close on outside click
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('globalNotifModal');
            const banner = document.getElementById('dashboardAlertBanner');
            if (modal && !modal.classList.contains('hidden')) {
                if (!modal.contains(e.target) && (!banner || !banner.contains(e.target))) {
                    modal.classList.add('hidden');
                }
            }
        });
    </script>
@endif
