@extends('layouts.app')

{{-- Tarjetas, salud e inventario cada 15 s (la página completa es la consulta más pesada: no bajar de aquí) --}}
@section('refresco', '15')

@section('styles')
<style>
    /* ==================== NOC DYNAMIC DUAL TRAFFIC GAUGES ==================== */
    .traffic-gauge-card {
        border-radius: 1.75rem;
        transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    /* Modo Claro (Colores Institucionales: Azul Hacienda & Naranja Hacienda) */
    html:not(.dark) .traffic-gauge-card {
        background: var(--nm-surface);
        border: 1px solid var(--nm-border);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        color: var(--nm-text);
    }
    html:not(.dark) .traffic-gauge-box {
        background: var(--nm-sunken);
        border: 1px solid var(--nm-border);
    }
    html:not(.dark) .st-gauge-arc-bg {
        stroke: #cbd5e1;
        stroke-width: 14;
        stroke-linecap: round;
        fill: none;
    }
    html:not(.dark) .st-tick-label {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 11px;
        font-weight: 700;
        fill: #64748b;
        text-anchor: middle;
        dominant-baseline: central;
        user-select: none;
    }
    html:not(.dark) .st-needle-pointer {
        fill: #0f172a;
    }
    html:not(.dark) .st-needle-cap-bg {
        fill: #ffffff;
        stroke: #0f172a;
    }

    /* Modo Oscuro (NOC Neón: Cian & Magenta) */
    .dark .traffic-gauge-card {
        background: #090b14;
        background-image: radial-gradient(circle at 50% 120%, #151a32 0%, #06070c 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 20px 45px -15px rgba(0, 0, 0, 0.7);
        color: #ffffff;
    }
    .dark .traffic-gauge-box {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    .dark .st-gauge-arc-bg {
        stroke: rgba(255, 255, 255, 0.10);
        stroke-width: 14;
        stroke-linecap: round;
        fill: none;
    }
    .dark .st-tick-label {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 11px;
        font-weight: 700;
        fill: #94a3b8;
        text-anchor: middle;
        dominant-baseline: central;
        user-select: none;
    }
    .dark .st-needle-pointer {
        fill: #ffffff;
    }
    .dark .st-needle-cap-bg {
        fill: #0b0c16;
        stroke: rgba(255, 255, 255, 0.3);
    }

    .st-gauge-arc-progress {
        stroke-width: 14;
        stroke-linecap: round;
        fill: none;
        transition: stroke-dashoffset 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    /* La aguja la mueve el bucle de animación del script (sin transición CSS) */
    .st-needle-wedge {
        transform-origin: 160px 160px;
        will-change: transform;
    }

    /* Listas del diagnóstico de red (clases compartidas para no repetirlas en cada celda) */
    .dash-lista th { padding: 0 0.5rem 0.375rem 0; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; }
    .dash-lista td { padding: 0.375rem 0.5rem 0.375rem 0; color: #374151; border-top: 1px solid #f3f4f6; }
    .dash-lista .dash-disp { font-weight: 600; color: #1f2937; display: inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
    .dash-lista .dash-disp:hover { color: var(--nm-primary); text-decoration: underline; }
    .dark .dash-lista th { color: #64748b; }
    .dark .dash-lista td { color: #cbd5e1; border-top-color: rgba(30, 41, 59, 0.5); }
    .dark .dash-lista .dash-disp { color: #e2e8f0; }
    .dark .dash-lista .dash-disp:hover { color: #667d93; }

    /* Selector de rango/métrica de la gráfica de tendencias */
    .trend-btn { color: var(--nm-text-muted); }
    .trend-btn:hover { color: #1f2937; }
    .trend-btn-active { background: var(--nm-surface); color: var(--nm-primary); font-weight: 700; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
    .dark .trend-btn { color: #94a3b8; }
    .dark .trend-btn:hover { color: #e2e8f0; }
    .dark .trend-btn-active { background: #1e293b; color: #667d93; box-shadow: none; }

    /* Último punto real de una gráfica: late para indicar que la serie está en vivo (nmVivo.puntoVivo) */
    .nm-punto-vivo {
        position: absolute; width: 8px; height: 8px; margin: -4px 0 0 -4px; border-radius: 9999px;
        background: var(--c, #667d93); box-shadow: 0 0 0 2px rgb(255 255 255 / 0.85); pointer-events: none; z-index: 2;
    }
    .nm-metal-navy .nm-punto-vivo, .nm-metal-azure .nm-punto-vivo { box-shadow: 0 0 0 2px rgb(255 255 255 / 0.35); }
    .nm-punto-vivo::after {
        content: ''; position: absolute; inset: -7px; border-radius: 9999px; border: 2px solid var(--c, #667d93);
        animation: nmLatido 2.2s ease-out infinite;
    }
    @keyframes nmLatido { 0% { transform: scale(.35); opacity: .9; } 100% { transform: scale(1.5); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { .nm-punto-vivo::after { animation: none; opacity: 0; } }
</style>
@endsection

@section('content')
            <!-- Top Bar -->
            <div class="flex justify-end items-center gap-3 mb-4 sm:mb-6">
                    <a href="{{ route('reportes.inventario_pdf') }}" target="_blank" class="fancy fancy-sm hidden sm:inline-flex" style="--fancy-fondo: var(--nm-bg)" title="Descargar Reporte Ejecutivo de Inventario en PDF" aria-label="Descargar Reporte Ejecutivo de Inventario en PDF">
                        <span class="top-key"></span>
                        <span class="text">
                            <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /> <path d="M14 2v4a2 2 0 0 0 2 2h4" /> <path d="M12 18v-6" /> <path d="m9 15 3 3 3-3" /></svg>
                            Reporte PDF
                        </span>
                        <span class="bottom-key-1"></span>
                        <span class="bottom-key-2"></span>
                    </a>
                    {{-- Notification Bell and Dropdown Center --}}
                    <div class="relative" id="notifDropdownContainer" data-refrescar="notificaciones" data-refrescar-pausa="#notifDropdownMenu:not(.hidden)">
                        <button id="notifBellBtn" type="button" onclick="toggleNotifDropdown(event)" class="w-10 h-10 bg-white dark:bg-nm-surface rounded-full flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-hacienda-orange shadow-sm dark:shadow-none border border-gray-100 dark:border-slate-800/60 transition relative focus:outline-none focus:ring-2 focus:ring-nm-primary/30" title="Centro de Notificaciones y Alertas" aria-label="Centro de Notificaciones y Alertas">
                            @if($unreadNotificaciones > 0)
                                <span id="notifPulseRing" class="absolute top-2 right-2 w-2.5 h-2.5 bg-[#ff8300] rounded-full animate-ping"></span>
                                <span id="notifPulseDot" class="absolute top-2 right-2 w-2.5 h-2.5 bg-[#ff8300] rounded-full ring-2 ring-white"></span>
                                <span id="notifCountBadge" class="absolute -top-1 -right-1 min-w-[19px] h-[19px] px-1 bg-[#ff8300] text-white text-[11px] font-black rounded-full flex items-center justify-center ring-2 ring-white shadow">
                                    {{ $unreadNotificaciones }}
                                </span>
                            @endif
                            <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /> <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
                        </button>

                        {{-- Dropdown Card --}}
                        <div id="notifDropdownMenu" class="hidden absolute right-0 mt-3 w-80 sm:w-96 md:w-[440px] nm-glass bg-white dark:bg-nm-surface rounded-2xl shadow-2xl dark:shadow-none border border-gray-100 dark:border-slate-800/60 z-50 overflow-hidden transform transition-all duration-200 origin-top-right">
                            {{-- Header --}}
                            <div class="p-4 bg-gradient-to-r from-slate-50 to-blue-50/40 dark:from-nm-sunken dark:to-slate-900/90 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-nm-primary text-white flex items-center justify-center shadow-sm">
                                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /> <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900 dark:text-white leading-tight">Centro de Notificaciones</h3>
                                        <p class="text-[11px] text-gray-500 dark:text-slate-400">Alertas NOC en tiempo real</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span id="notifBadgeText" class="px-2 py-0.5 rounded-full text-[11px] font-black {{ $unreadNotificaciones > 0 ? 'bg-nm-primary/10 text-nm-primary dark:bg-blue-950/60 dark:text-blue-400' : 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $unreadNotificaciones }} no leídas
                                    </span>
                                    <button type="button" onclick="markAllNotificationsAsRead()" class="text-[11px] text-gray-500 dark:text-slate-400 hover:text-nm-primary dark:hover:text-blue-400 font-bold transition px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Marcar todas como leídas" aria-label="Marcar todas como leídas">
                                        Marcar Leídas
                                    </button>
                                    <button type="button" onclick="clearAllNotifications()" class="text-[11px] text-gray-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 font-bold transition px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/40" title="Ocultar todas las alertas actuales: reaparecen si la falla vuelve a ocurrir o sigue activa después de 24 h" aria-label="Ocultar todas las alertas actuales">
                                        Limpiar Todas
                                    </button>
                                </div>
                            </div>

                            {{-- Filter Tabs --}}
                            <div class="px-4 py-2 bg-gray-50/70 dark:bg-nm-sunken border-b border-gray-100 dark:border-slate-800/60 flex items-center gap-1.5 overflow-x-auto text-xs">
                                <button type="button" onclick="filterNotifs('all', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-bold text-xs bg-white dark:bg-slate-800 text-nm-primary dark:text-blue-400 shadow-xs dark:shadow-none border border-gray-200 dark:border-slate-700">
                                    Todas ({{ count($notificaciones) }})
                                </button>
                                <button type="button" onclick="filterNotifs('critica', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-semibold text-xs text-gray-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-slate-100 transition flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Críticas
                                </button>
                                <button type="button" onclick="filterNotifs('advertencia', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-semibold text-xs text-gray-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-slate-100 transition flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#ff8300]"></span>
                                    Advertencias
                                </button>
                                <button type="button" onclick="filterNotifs('sistema', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-semibold text-xs text-gray-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-slate-100 transition flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--nm-primary-2)]"></span>
                                    Sistema
                                </button>
                            </div>

                            {{-- Notifications List --}}
                            <div id="notifListContainer" class="max-h-[380px] overflow-y-auto divide-y divide-gray-100 dark:divide-slate-800/50 custom-scrollbar">
                                @forelse($notificaciones as $n)
                                    <div class="notif-item p-3.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/40 flex gap-3 items-start relative {{ !$n['leida'] ? 'bg-blue-50/20 dark:bg-blue-950/20' : '' }}" 
                                         data-id="{{ $n['id'] }}" 
                                         data-tipo="{{ $n['tipo'] }}" 
                                         data-leida="{{ $n['leida'] ? '1' : '0' }}">
                                        
                                        {{-- Icon by Type --}}
                                        @if($n['tipo'] === 'critica')
                                            <div class="w-8 h-8 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs border border-transparent dark:border-red-900/40">
                                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /> <path d="M12 9v4" /> <path d="M12 17h.01" /></svg>
                                            </div>
                                        @elseif($n['tipo'] === 'advertencia')
                                            <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-[#ff8300] flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs border border-transparent dark:border-amber-900/40">
                                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" /></svg>
                                            </div>
                                        @else
                                            <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-nm-primary dark:text-blue-400 flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs border border-transparent dark:border-slate-700">
                                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="m9 12 2 2 4-4" /></svg>
                                            </div>
                                        @endif

                                        {{-- Body --}}
                                        <div class="flex-1 min-w-0 pr-2">
                                            <div class="flex items-center justify-between gap-2 mb-1">
                                                <h4 class="text-xs font-extrabold text-gray-900 dark:text-slate-100 leading-tight">{{ $n['titulo'] }}</h4>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-extrabold font-mono bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700/80 shrink-0" title="Hora de falla: {{ $n['fecha_falla'] ?? $n['tiempo'] }}">
                                                    <svg class="w-3 h-3 text-red-500 flex-shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <polyline points="12 6 12 12 16 14" /></svg>
                                                    <span>{{ $n['hora_falla'] ?? $n['tiempo'] }}</span>
                                                </span>
                                            </div>
                                            
                                            <p class="text-xs text-gray-600 dark:text-slate-300 leading-snug mb-1 font-medium">{{ $n['mensaje'] }}</p>

                                            @if(!empty($n['fecha_falla']))
                                                <div class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 font-mono mb-2">
                                                    <span class="font-bold text-slate-600 dark:text-slate-300"><svg class="w-3.5 h-3.5 inline-block align-[-2px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <polyline points="12 6 12 12 16 14" /></svg> Ocurrencia:</span>
                                                    <span class="font-extrabold text-slate-900 dark:text-slate-100">{{ $n['fecha_falla'] }}</span>
                                                    @if(!empty($n['hace_cuanto']))
                                                        <span class="text-slate-400">({{ $n['hace_cuanto'] }})</span>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Bloque de Causa Raíz Inteligente --}}
                                            @if(!empty($n['causa_raiz']))
                                                <div class="mb-2 p-2 rounded-xl bg-slate-50 dark:bg-nm-sunken border border-slate-200/80 dark:border-slate-800/80 text-[11px] leading-relaxed">
                                                    <div class="flex items-start gap-1.5 text-slate-700 dark:text-slate-300 mb-1">
                                                        <svg class="w-3.5 h-3.5 text-indigo-500 dark:text-indigo-400 flex-shrink-0 mt-0.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" /></svg>
                                                        <div>
                                                            <strong class="font-bold text-slate-900 dark:text-white">¿Por qué falló?</strong>
                                                            <span class="text-slate-600 dark:text-slate-400">{{ $n['causa_raiz'] }}</span>
                                                        </div>
                                                    </div>
                                                    @if(!empty($n['accion_sugerida']))
                                                        <div class="flex items-start gap-1.5 text-emerald-700 dark:text-emerald-400 pt-1 border-t border-slate-200/50 dark:border-slate-800/50">
                                                            <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="m9 12 2 2 4-4" /></svg>
                                                            <span><strong class="font-bold">Acción:</strong> {{ $n['accion_sugerida'] }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                            
                                            <div class="flex items-center justify-between pt-1">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-transparent dark:border-slate-700">
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $n['tipo'] === 'critica' ? 'bg-red-500' : ($n['tipo'] === 'advertencia' ? 'bg-[#ff8300]' : 'bg-blue-500') }}"></span>
                                                        {{ $n['dispositivo'] }}
                                                    </span>
                                                    @if(!empty($n['ubicacion']) && $n['ubicacion'] !== 'No especificada')
                                                        <span class="text-[11px] text-gray-400 dark:text-slate-500 truncate max-w-[120px]" title="{{ $n['ubicacion'] }}">
                                                            <svg class="w-3.5 h-3.5 inline-block align-[-2px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" /> <circle cx="12" cy="10" r="3" /></svg> {{ $n['ubicacion'] }}
                                                        </span>
                                                    @endif
                                                </div>

                                                @if(isset($n['link']))
                                                    <a href="{{ $n['link'] }}" class="text-[11px] font-bold text-nm-primary dark:text-blue-400 hover:text-[#ff8300] dark:hover:text-cyan-400 transition flex items-center gap-0.5 group">
                                                        <span>{{ $n['accion'] }}</span>
                                                        <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Unread Dot & Dismiss --}}
                                        <div class="flex flex-col items-center gap-2">
                                            @if(!$n['leida'])
                                                <span class="notif-unread-dot w-2 h-2 rounded-full bg-[#ff8300]"></span>
                                            @endif
                                            <button type="button" onclick="dismissNotification('{{ $n['id'] }}', event)" class="text-gray-300 dark:text-slate-600 hover:text-gray-500 dark:hover:text-slate-400 p-0.5 rounded transition" title="Ocultar esta alerta (reaparece si vuelve a ocurrir o sigue activa después de 24 h)" aria-label="Ocultar esta alerta">
                                                <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-8 text-center">
                                        <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2 border border-transparent dark:border-emerald-800/40">
                                            <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                        </div>
                                        <p class="text-sm font-bold text-gray-800 dark:text-slate-100">Red Operando Normal</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-400 mt-1">No hay alertas ni incidencias reportadas en el sistema.</p>
                                    </div>
                                @endforelse
                                
                                {{-- Empty filter state --}}
                                <div id="notifFilterEmpty" class="hidden p-8 text-center">
                                    <div class="w-12 h-12 rounded-full bg-gray-50 dark:bg-slate-800 text-gray-400 flex items-center justify-center mx-auto mb-2">
                                        <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12" /> <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" /></svg>
                                    </div>
                                    <p class="text-xs font-bold text-gray-700 dark:text-slate-300">Sin elementos en esta categoría</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">Selecciona otra pestaña o regresa a "Todas".</p>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="p-3 bg-gray-50 dark:bg-nm-sunken border-t border-gray-100 dark:border-slate-800/60 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1.5 text-gray-500 dark:text-slate-400 text-[11px]">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Sondeo SNMP/ICMP cada ~8 s</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('settings.index', ['section' => 'notificaciones']) }}" class="text-[11px] font-bold text-gray-500 dark:text-slate-400 hover:text-[#ff8300] transition">
                                        Configurar
                                    </a>
                                    <a href="{{ route('alertas.index') }}" class="text-[11px] font-bold text-nm-primary dark:text-blue-400 hover:text-[#ff8300] transition">
                                        Ver todas →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            <!-- Hero Banner -->
            {{-- shrink-0: el contenedor principal es flex en columna; con overflow-hidden se encogería a 0 px --}}
            <div class="bg-white dark:bg-nm-surface border border-gray-100 dark:border-slate-800/40 rounded-2xl p-6 lg:p-8 text-gray-800 dark:text-slate-100 mb-8 relative overflow-hidden shrink-0 shadow-sm dark:shadow-none flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-6 min-h-min">
                <!-- Background pattern (simulated) -->
                <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle at 2px 2px, var(--nm-primary) 1px, transparent 0); background-size: 32px 32px; z-index: 0;"></div>

                <div class="relative z-10 w-full md:flex-1">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold text-hacienda-blue dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 mb-3 md:mb-4 border border-transparent dark:border-blue-900/40">
                        <svg class="w-4 h-4 mr-1" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4" /> <path d="M16 2v4" /> <rect width="18" height="18" x="3" y="4" rx="2" /> <path d="M3 10h18" /></svg>
                        <span title="Menor sysUpTime entre los equipos en línea (el equipo reiniciado más recientemente)">{{ now()->format('d/m/Y') }} | Uptime mínimo: {{ $formattedUptime }}</span>
                    </span>
                    @php
                        $hora = now()->hour;
                        if ($hora < 12) {
                            $saludo = '¡Buen Día';
                        } elseif ($hora < 19) {
                            $saludo = '¡Buena Tarde';
                        } else {
                            $saludo = '¡Buena Noche';
                        }
                        $prefijo = auth()->user()->prefijo ?? '';
                        $nombreUsuario = auth()->user()->name ?? 'Administrador';
                        $nombreCompleto = $prefijo ? $prefijo . ' ' . $nombreUsuario : $nombreUsuario;
                    @endphp
                    <h2 class="text-2xl lg:text-4xl font-bold mb-2 text-gray-900 dark:text-white leading-tight">{{ $saludo }}, {{ $nombreCompleto }}!</h2>
                    @php
                        $incRes = $resumen['incidentes'];
                        $nombres = fn ($claves) => collect($claves)->map(fn ($k) => \App\Services\ResumenSectores::SECTORES[$k]['nombre'])->join(', ', ' y ');
                        $conAlarma = collect($resumen['sectores'])->filter(fn ($s) => in_array($s['tono'], ['crit', 'warn'], true))->keys();
                        $sinNovedad = collect($resumen['sectores'])->filter(fn ($s) => $s['tono'] === 'ok')->keys();
                        $workers = $paneles['cobertura']['workers'];
                        $workersOk = collect($workers)->where('ok', true)->count();
                    @endphp
                    <div data-refrescar="resumen">
                        <p class="text-gray-500 dark:text-slate-400 text-sm lg:text-lg max-w-2xl">
                            @if($incRes['total'] === 0)
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Todo en orden:</span> sin incidentes activos en ningún sector.
                            @else
                                <span class="font-bold {{ $incRes['criticas'] ? 'text-red-600' : 'text-amber-600' }}">{{ $incRes['total'] }} {{ $incRes['total'] === 1 ? 'incidente activo' : 'incidentes activos' }}</span>@if($incRes['criticas']) ({{ $incRes['criticas'] }} {{ $incRes['criticas'] === 1 ? 'crítico' : 'críticos' }})@endif
                                en {{ $nombres($conAlarma) }}.
                                @if($sinNovedad->isNotEmpty()) Sin novedad en {{ $nombres($sinNovedad) }}.@endif
                            @endif
                        </p>
                        <p class="mt-2 inline-flex items-center gap-2 text-xs font-semibold {{ $workersOk === count($workers) ? 'text-gray-400 dark:text-slate-500' : 'text-red-600 dark:text-red-400' }}"
                           title="{{ collect($workers)->map(fn ($w) => $w['nombre'] . ': ' . $w['edad'])->join(' · ') }}">
                            <span class="w-2 h-2 rounded-full {{ $workersOk === count($workers) ? 'bg-emerald-500' : 'bg-red-500 animate-pulse' }}"></span>
                            Recolección de datos: {{ $workersOk }} de {{ count($workers) }} procesos al día
                        </p>
                    </div>
                </div>
                
                <!-- Illustration Placeholder -->
                <div class="relative z-10 flex-shrink-0 mt-4 md:mt-0 md:w-1/3 flex justify-start md:justify-end">
                    {{-- Logo DTIF a su tamaño original (320 × 85); en pantallas angostas se reduce proporcionalmente --}}
                    <img src="{{ asset('images/logo_dtif.png') }}?v=320x85" width="320" height="85" class="w-[320px] max-w-full h-auto object-contain dark:brightness-0 dark:invert dark:opacity-90 transition" alt="Dirección de Tecnología de la Información Financiera">
                </div>
            </div>

            {{-- Estado de cada sector --}}
            <div data-refrescar="sectores">
                @include('dashboard.partials.sectores', ['sectores' => $resumen['sectores']])
            </div>

            {{-- Incidentes activos de todos los sectores --}}
            <div data-refrescar="incidentes">
                @include('dashboard.partials.incidentes', ['incidentes' => $resumen['incidentes']])
            </div>

            {{-- Tráfico de la red en vivo | lo que más preocupa --}}
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 min-w-0">
            <div class="traffic-gauge-card p-6 sm:p-8 h-full" id="snmpTrafficCard">
                {{-- Header: Título Institucional + Status SNMP --}}
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pb-5 border-b border-slate-200 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-nm-primary/10 dark:bg-white/10 text-nm-primary dark:text-blue-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white tracking-tight">Monitoreo de Tráfico de Red en Tiempo Real</h3>
                            <p class="text-xs text-gray-500 dark:text-slate-400" id="snmpTrafficSource">Fuente: switch core configurado en Ajustes (o suma de todos los equipos)</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-xs font-mono">
                        <span id="snmpStatusDot" class="w-2.5 h-2.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                        <span id="snmpStatusText" class="text-gray-700 dark:text-slate-300 font-medium">Sondeo SNMP Activo</span>
                    </div>
                </div>

                {{-- Centerpiece: Dual Speedometer Arc Gauges (Descarga & Subida Side-by-Side) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
                    {{-- Gauge 1: DESCARGA (DOWN) --}}
                    <div class="traffic-gauge-box relative flex flex-col items-center justify-center p-5 sm:p-6 rounded-2xl transition">
                        <div class="flex items-center gap-2 text-xs font-black text-nm-primary dark:text-[#8a9cad] tracking-widest uppercase mb-3">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14" /> <path d="m19 12-7 7-7-7" /></svg>
                            <span>TRÁFICO DE DESCARGA (DOWN)</span>
                        </div>
                        <div class="relative w-[260px] sm:w-[300px] h-[220px] sm:h-[250px] flex items-center justify-center">
                            <svg viewBox="0 0 320 320" class="w-full h-full overflow-visible">
                                <defs>
                                    <linearGradient id="stDownGradDark" x1="0%" y1="100%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#667d93" />
                                        <stop offset="50%" stop-color="#8a9cad" />
                                        <stop offset="100%" stop-color="#00b8c4" />
                                    </linearGradient>
                                    <linearGradient id="stDownGradLight" x1="0%" y1="100%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#3d4b59" />
                                        <stop offset="50%" stop-color="#667d93" />
                                        <stop offset="100%" stop-color="#8a9cad" />
                                    </linearGradient>
                                    <filter id="stDownGlow" x="-20%" y="-20%" width="140%" height="140%">
                                        <feGaussianBlur stdDeviation="5" result="blur" />
                                        <feMerge>
                                            <feMergeNode in="blur" />
                                            <feMergeNode in="SourceGraphic" />
                                        </feMerge>
                                    </filter>
                                </defs>

                                <path d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" class="st-gauge-arc-bg" />
                                <path id="stDownProgressArc" d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" class="st-gauge-arc-progress" stroke="url(#stDownGradLight)" stroke-dasharray="541.92" stroke-dashoffset="541.92" filter="url(#stDownGlow)" />

                                <text x="102" y="218" class="st-tick-label">0</text>
                                <text x="84"  y="191" class="st-tick-label">5</text>
                                <text x="78"  y="153" class="st-tick-label">10</text>
                                <text x="99"  y="105" class="st-tick-label">50</text>
                                <text x="160" y="78"  class="st-tick-label font-black text-gray-900 dark:text-white" style="font-size: 12px;">100</text>
                                <text x="221" y="105" class="st-tick-label">250</text>
                                <text x="242" y="153" class="st-tick-label">500</text>
                                <text x="236" y="191" class="st-tick-label">750</text>
                                <text x="218" y="218" class="st-tick-label">1000</text>

                                {{-- Prominent Visible Needle Pointer --}}
                                <g id="stDownNeedleWedge" class="st-needle-wedge" style="transform: rotate(-135deg);">
                                    <polygon points="156,160 164,160 161.5,42 158.5,42" class="st-needle-pointer drop-shadow-md" />
                                    <circle cx="160" cy="160" r="13" class="st-needle-cap-bg" stroke-width="2.5" />
                                    <circle cx="160" cy="160" r="5" fill="#667d93" class="dark:fill-[#8a9cad]" />
                                </g>
                            </svg>

                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center pt-14">
                                <span id="stDownMainValue" class="text-3xl sm:text-4xl font-black font-mono tracking-tight text-gray-900 dark:text-white drop-shadow-xs">{{ $trafficInNow !== null ? number_format($trafficInNow, 2) : '—' }}</span>
                                <div class="flex items-center gap-1 text-xs font-bold text-nm-primary dark:text-[#8a9cad] tracking-widest uppercase mt-0.5">
                                    <span id="stDownUnit">↓ Mbps</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Gauge 2: SUBIDA (UP) --}}
                    <div class="traffic-gauge-box relative flex flex-col items-center justify-center p-5 sm:p-6 rounded-2xl transition">
                        <div class="flex items-center gap-2 text-xs font-black text-[#ff8300] dark:text-pink-400 tracking-widest uppercase mb-3">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7" /> <path d="M12 19V5" /></svg>
                            <span>TRÁFICO DE SUBIDA (UP)</span>
                        </div>
                        <div class="relative w-[260px] sm:w-[300px] h-[220px] sm:h-[250px] flex items-center justify-center">
                            <svg viewBox="0 0 320 320" class="w-full h-full overflow-visible">
                                <defs>
                                    <linearGradient id="stUpGradDark" x1="0%" y1="100%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#8b5cf6" />
                                        <stop offset="50%" stop-color="#d946ef" />
                                        <stop offset="100%" stop-color="#00b8c4" />
                                    </linearGradient>
                                    <linearGradient id="stUpGradLight" x1="0%" y1="100%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#ff8300" />
                                        <stop offset="50%" stop-color="#ea580c" />
                                        <stop offset="100%" stop-color="#c2410c" />
                                    </linearGradient>
                                    <filter id="stUpGlow" x="-20%" y="-20%" width="140%" height="140%">
                                        <feGaussianBlur stdDeviation="5" result="blur" />
                                        <feMerge>
                                            <feMergeNode in="blur" />
                                            <feMergeNode in="SourceGraphic" />
                                        </feMerge>
                                    </filter>
                                </defs>

                                <path d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" class="st-gauge-arc-bg" />
                                <path id="stUpProgressArc" d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" class="st-gauge-arc-progress" stroke="url(#stUpGradLight)" stroke-dasharray="541.92" stroke-dashoffset="541.92" filter="url(#stUpGlow)" />

                                <text x="102" y="218" class="st-tick-label">0</text>
                                <text x="84"  y="191" class="st-tick-label">5</text>
                                <text x="78"  y="153" class="st-tick-label">10</text>
                                <text x="99"  y="105" class="st-tick-label">50</text>
                                <text x="160" y="78"  class="st-tick-label font-black text-gray-900 dark:text-white" style="font-size: 12px;">100</text>
                                <text x="221" y="105" class="st-tick-label">250</text>
                                <text x="242" y="153" class="st-tick-label">500</text>
                                <text x="236" y="191" class="st-tick-label">750</text>
                                <text x="218" y="218" class="st-tick-label">1000</text>

                                {{-- Prominent Visible Needle Pointer --}}
                                <g id="stUpNeedleWedge" class="st-needle-wedge" style="transform: rotate(-135deg);">
                                    <polygon points="156,160 164,160 161.5,42 158.5,42" class="st-needle-pointer drop-shadow-md" />
                                    <circle cx="160" cy="160" r="13" class="st-needle-cap-bg" stroke-width="2.5" />
                                    <circle cx="160" cy="160" r="5" fill="#ff8300" class="dark:fill-[#00b8c4]" />
                                </g>
                            </svg>

                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center pt-14">
                                <span id="stUpMainValue" class="text-3xl sm:text-4xl font-black font-mono tracking-tight text-gray-900 dark:text-white drop-shadow-xs">{{ $trafficOutNow !== null ? number_format($trafficOutNow, 2) : '—' }}</span>
                                <div class="flex items-center gap-1 text-xs font-bold text-[#ff8300] dark:text-pink-400 tracking-widest uppercase mt-0.5">
                                    <span id="stUpUnit">↑ Mbps</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer: Metadata Institucional --}}
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-200 dark:border-white/10 text-xs text-gray-600 dark:text-slate-300">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-nm-primary/10 dark:bg-white/10 flex items-center justify-center text-nm-primary dark:text-slate-300 shrink-0">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z" /> <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" /> <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2" /> <path d="M10 6h4" /> <path d="M10 10h4" /> <path d="M10 14h4" /> <path d="M10 18h4" /></svg>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 dark:text-white text-xs leading-tight">Secretaría de la Hacienda Pública</p>
                            <p class="text-[11px] text-gray-500 dark:text-slate-400 font-mono mt-0.5">Red Estatal de Comunicaciones · Jalisco</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-[#ff8300]/10 dark:bg-white/10 flex items-center justify-center text-[#ff8300] dark:text-slate-300 shrink-0">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2" /> <rect width="20" height="8" x="2" y="14" rx="2" ry="2" /> <line x1="6" x2="6.01" y1="6" y2="6" /> <line x1="6" x2="6.01" y1="18" y2="18" /></svg>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 dark:text-white text-xs leading-tight" id="snmpTrafficDevice">—</p>
                            <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5" id="snmpTrafficMeasured">Esperando medición</p>
                        </div>
                    </div>
                </div>
            </div>
                </div>
                <div data-refrescar="atencion" class="min-w-0">
                    @include('dashboard.partials.atencion', ['atencion' => $resumen['atencion']])
                </div>
            </div>

            @include('dashboard.partials.tendencias')
@endsection

@section('scripts')
    <script>
        /*
         * Movimiento de las gráficas, siempre con datos reales:
         *  - Al crearse, la línea se dibuja de izquierda a derecha.
         *  - Cuando llega una muestra nueva del sondeo, la ventana de tiempo avanza: los puntos se deslizan a la
         *    izquierda y el nuevo crece desde el anterior. No se interpola ni se inventa ningún punto.
         *  - El último punto real late (en vivo).
         * Con "reducir movimiento" del sistema no hay animaciones.
         */
        window.nmVivo = (function () {
            const reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const opt = { hour12: false };
            const hhmmss = t => new Date(t).toLocaleTimeString('es-MX', { ...opt, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const hhmm = t => new Date(t).toLocaleTimeString('es-MX', { ...opt, hour: '2-digit', minute: '2-digit' });
            const ddhhmm = t => new Date(t).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit' }) + ' ' + hhmm(t);

            function animaciones(totalMs = 1100) {
                if (reducido) return { animation: false };
                const vistos = new WeakSet();
                const nuevo = ctx => ctx.type === 'data' && ctx.element && !vistos.has(ctx.element);
                const paso = ctx => totalMs / Math.max(1, ctx.chart.data.datasets[0]?.data.length || 1);
                const yPrevio = ctx => {
                    const meta = ctx.chart.getDatasetMeta(ctx.datasetIndex);
                    const ant = meta.data[ctx.index - 1];
                    const escala = ctx.chart.scales[meta.yAxisID || 'y'];
                    return ant ? ant.getProps(['y'], true).y : escala.getPixelForValue(escala.min);
                };
                const propiedad = (desde) => ({
                    type: 'number',
                    from: ctx => nuevo(ctx) ? desde(ctx) : undefined,
                    duration: ctx => nuevo(ctx) ? (ctx.chart.$dibujado ? 700 : paso(ctx)) : 900,
                    easing: ctx => nuevo(ctx) && !ctx.chart.$dibujado ? 'linear' : 'easeInOutCubic',
                    delay: ctx => nuevo(ctx) && !ctx.chart.$dibujado ? ctx.index * paso(ctx) : 0,
                });
                return {
                    animation: {
                        onComplete: ({ chart }) => {
                            chart.$dibujado = true;
                            chart.data.datasets.forEach((_, i) => chart.getDatasetMeta(i).data.forEach(e => vistos.add(e)));
                        },
                    },
                    animations: { x: propiedad(() => NaN), y: propiedad(yPrevio) },
                };
            }

            // Punto que late en la última muestra real de cada serie (sigue a la línea mientras se desliza)
            function puntoVivo(series = [0]) {
                return {
                    id: 'nmPuntoVivo',
                    afterDraw(chart) {
                        const cont = chart.canvas.parentElement;
                        if (!cont) return;
                        chart.$puntos = chart.$puntos || [];
                        series.forEach((di, k) => {
                            const meta = chart.getDatasetMeta(di);
                            const ds = chart.data.datasets[di];
                            if (!meta || !ds) return;
                            let el = chart.$puntos[k];
                            if (!el) {
                                el = document.createElement('span');
                                el.className = 'nm-punto-vivo';
                                cont.appendChild(el);
                                chart.$puntos[k] = el;
                            }
                            let ult = null;
                            for (let i = meta.data.length - 1; i >= 0; i--) {
                                const p = meta.data[i], d = ds.data[i];
                                if (p && !p.skip && Number.isFinite(p.x) && Number.isFinite(p.y) && d && d.y !== null && d.y !== undefined) { ult = p; break; }
                            }
                            const a = chart.chartArea;
                            const visible = ult && !meta.hidden && a && ult.x >= a.left - 1 && ult.x <= a.right + 1;
                            el.style.display = visible ? '' : 'none';
                            if (visible) {
                                el.style.left = ult.x + 'px';
                                el.style.top = ult.y + 'px';
                                el.style.setProperty('--c', ds.borderColor);
                            }
                        });
                    },
                    beforeDestroy(chart) { (chart.$puntos || []).forEach(el => el.remove()); },
                };
            }

            // Agrega muestras reales al final y avanza la ventana; quita solo lo que ya salió de la vista
            function avanzar(chart, nuevos, ventanaMs) {
                let ultimo = -Infinity;
                chart.data.datasets.forEach((ds, i) => {
                    const pts = ds.data;
                    (nuevos[i] || []).forEach(p => {
                        const igual = pts.length && pts[pts.length - 1].x === p.x ? pts[pts.length - 1] : null;
                        if (igual) igual.y = p.y;                       // el bloque en curso cambió su promedio
                        else if (!pts.length || p.x > pts[pts.length - 1].x) pts.push({ x: p.x, y: p.y });
                    });
                    if (pts.length) ultimo = Math.max(ultimo, pts[pts.length - 1].x);
                });
                if (!Number.isFinite(ultimo)) return;
                const minActual = chart.options.scales.x.min;
                chart.data.datasets.forEach(ds => { while (ds.data.length > 2 && ds.data[1].x < minActual) ds.data.shift(); });
                chart.options.scales.x.min = ultimo - ventanaMs;
                chart.options.scales.x.max = ultimo;
                chart.update();
            }

            return { animaciones, puntoVivo, avanzar, hhmmss, hhmm, ddhhmm, reducido };
        })();
    </script>
    <script>

        // ==========================================
        // CENTRO DE NOTIFICACIONES & ALERTAS NOC
        // ==========================================
        let unreadNotifCount = {{ $unreadNotificaciones }};
        // Tras el refresco automático de la campana, el contador se toma de lo que llegó
        document.addEventListener('nm:refrescado', e => {
            if (e.detail.claves.includes('notificaciones')) unreadNotifCount = document.querySelectorAll('.notif-item[data-leida="0"]').length;
        });

        function toggleNotifDropdown(event) {
            if (event) {
                event.stopPropagation();
            }
            const menu = document.getElementById('notifDropdownMenu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        // Llegada desde "Ver Alertas" de la cinta en otra página: abrir el centro de alertas
        if (location.hash === '#alertas') {
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('notifDropdownMenu')?.classList.remove('hidden');
                history.replaceState(null, '', location.pathname + location.search);
            });
        }

        // Close dropdown on click outside
        document.addEventListener('click', function(e) {
            const container = document.getElementById('notifDropdownContainer');
            const menu = document.getElementById('notifDropdownMenu');
            if (container && menu && !container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // Close dropdown on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const menu = document.getElementById('notifDropdownMenu');
                if (menu) menu.classList.add('hidden');
            }
        });

        function filterNotifs(tipo, btn) {
            // Update tab styles
            document.querySelectorAll('.notif-tab-btn').forEach(b => {
                b.classList.remove('bg-white', 'text-nm-primary', 'shadow-xs', 'border', 'border-gray-200');
                b.classList.add('text-gray-600');
            });
            if (btn) {
                btn.classList.add('bg-white', 'text-nm-primary', 'shadow-xs', 'border', 'border-gray-200');
                btn.classList.remove('text-gray-600');
            }

            // Filter items
            const items = document.querySelectorAll('.notif-item');
            let visibleCount = 0;
            items.forEach(item => {
                if (tipo === 'all' || item.dataset.tipo === tipo) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            const emptyFilter = document.getElementById('notifFilterEmpty');
            if (emptyFilter) {
                emptyFilter.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function markSingleNotificationAsRead(id) {
            const item = document.querySelector(`.notif-item[data-id="${id}"]`);
            if (!item || item.dataset.leida === '1') return;

            item.dataset.leida = '1';
            item.classList.remove('bg-blue-50/20', 'dark:bg-blue-950/20');
            const dot = item.querySelector('.notif-unread-dot');
            if (dot) dot.remove();

            decrementUnreadBadge();

            fetch('{{ route("notificaciones.marcar_leida") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: id })
            }).catch(err => console.error('Error al marcar notificación como leída:', err));
        }

        function dismissNotification(id, event) {
            if (event) event.stopPropagation();
            const item = document.querySelector(`.notif-item[data-id="${id}"]`);
            if (!item) return;

            // If it was unread, decrement counter
            if (item.dataset.leida === '0') {
                item.dataset.leida = '1';
                decrementUnreadBadge();
            }

            item.style.transition = 'all 0.2s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateX(15px)';
            setTimeout(() => {
                item.remove();
                const remaining = document.querySelectorAll('.notif-item');
                if (remaining.length === 0) {
                    const list = document.getElementById('notifListContainer');
                    if (list) {
                        list.innerHTML = `
                            <div class="p-8 text-center">
                                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2 border border-transparent dark:border-emerald-800/40">
                                    <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </div>
                                <p class="text-sm font-bold text-gray-800 dark:text-slate-100">Todo en orden</p>
                                <p class="text-xs text-gray-400 dark:text-slate-400 mt-1">Has atendido todas las alertas del sistema.</p>
                            </div>
                        `;
                    }
                }
            }, 200);

            // Persistir descarte en BD
            fetch('{{ route("notificaciones.descartar") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: id })
            }).catch(err => console.error('Error al descartar notificación:', err));
        }

        async function markAllNotificationsAsRead() {
            const total = document.querySelectorAll('.notif-item[data-leida="0"]').length;
            if (!total) return;
            if (typeof nmConfirmar === 'function' && !(await nmConfirmar(
                `Se marcarán ${total} alertas como leídas y saldrán de la cinta de alertas. Siguen en esta lista y reaparecen si la falla vuelve a ocurrir.`,
                { titulo: 'Marcar todas como leídas', boton: 'Marcar leídas', peligro: false }))) return;
            unreadNotifCount = 0;
            
            // Remove unread dots and highlights
            document.querySelectorAll('.notif-item').forEach(item => {
                item.dataset.leida = '1';
                item.classList.remove('bg-blue-50/20', 'dark:bg-blue-950/20');
                const dot = item.querySelector('.notif-unread-dot');
                if (dot) dot.remove();
            });

            // Update badge text in dropdown header
            const badgeText = document.getElementById('notifBadgeText');
            if (badgeText) {
                badgeText.textContent = '0 no leídas';
                badgeText.className = 'px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500';
            }

            // Remove badge and pulse from bell button
            const badge = document.getElementById('notifCountBadge');
            if (badge) badge.remove();
            const ring = document.getElementById('notifPulseRing');
            if (ring) ring.remove();
            const dot = document.getElementById('notifPulseDot');
            if (dot) dot.remove();

            // Smoothly hide top alert banner
            const banner = document.getElementById('dashboardAlertBanner');
            if (banner) {
                banner.style.transition = 'all 0.3s ease';
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 300);
            }

            // Persistir en BD que todas fueron leídas
            fetch('{{ route("notificaciones.marcar_leida") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ all: true })
            }).catch(err => console.error('Error al marcar notificaciones leídas:', err));
        }

        async function clearAllNotifications() {
            const total = document.querySelectorAll('.notif-item').length;
            if (typeof nmConfirmar === 'function' && !(await nmConfirmar(
                `Se ocultarán ${total} alertas para tu cuenta. Cada una reaparece si la falla vuelve a ocurrir o si sigue activa después de 24 h.`,
                { titulo: 'Ocultar todas las alertas', boton: 'Ocultar todas' }))) return;
            unreadNotifCount = 0;

            const items = document.querySelectorAll('.notif-item');
            items.forEach(item => {
                item.style.transition = 'all 0.2s ease';
                item.style.opacity = '0';
                item.style.transform = 'translateX(15px)';
            });

            setTimeout(() => {
                items.forEach(item => item.remove());
                const list = document.getElementById('notifListContainer');
                if (list) {
                    list.innerHTML = `
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2 border border-transparent dark:border-emerald-800/40">
                                <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                            </div>
                            <p class="text-sm font-bold text-gray-800 dark:text-slate-100">Todo en orden</p>
                            <p class="text-xs text-gray-400 dark:text-slate-400 mt-1">Has atendido y limpiado todas las alertas del sistema.</p>
                        </div>
                    `;
                }
            }, 200);

            // Update badge text
            const badgeText = document.getElementById('notifBadgeText');
            if (badgeText) {
                badgeText.textContent = '0 no leídas';
                badgeText.className = 'px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500';
            }

            // Remove badge and pulse from bell button
            const badge = document.getElementById('notifCountBadge');
            if (badge) badge.remove();
            const ring = document.getElementById('notifPulseRing');
            if (ring) ring.remove();
            const dot = document.getElementById('notifPulseDot');
            if (dot) dot.remove();

            // Hide top banner
            const banner = document.getElementById('dashboardAlertBanner');
            if (banner) {
                banner.style.transition = 'all 0.3s ease';
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 300);
            }

            // Persistir descarte de todas en BD
            fetch('{{ route("notificaciones.descartar") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ all: true })
            }).catch(err => console.error('Error al descartar todas las notificaciones:', err));
        }

        function decrementUnreadBadge() {
            unreadNotifCount = Math.max(0, unreadNotifCount - 1);
            
            const countBadge = document.getElementById('notifCountBadge');
            if (countBadge) {
                if (unreadNotifCount > 0) {
                    countBadge.textContent = unreadNotifCount;
                } else {
                    countBadge.remove();
                    const ring = document.getElementById('notifPulseRing');
                    if (ring) ring.remove();
                    const dot = document.getElementById('notifPulseDot');
                    if (dot) dot.remove();
                }
            }

            const badgeText = document.getElementById('notifBadgeText');
            if (badgeText) {
                badgeText.textContent = `${unreadNotifCount} no leídas`;
                if (unreadNotifCount === 0) {
                    badgeText.className = 'px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500';
                }
            }

            if (unreadNotifCount === 0) {
                const banner = document.getElementById('dashboardAlertBanner');
                if (banner) {
                    banner.style.transition = 'all 0.3s ease';
                    banner.style.opacity = '0';
                    setTimeout(() => banner.remove(), 300);
                }
            }
        }
    </script>

    {{-- ============================================================== --}}
    {{-- DUAL GAUGE LOGIC & LIVE SNMP POLLING                           --}}
    {{-- ============================================================== --}}
    <script>
        let latestDownMbps = {{ $trafficInNow !== null ? round($trafficInNow, 2) : 0 }};
        let latestUpMbps = {{ $trafficOutNow !== null ? round($trafficOutNow, 2) : 0 }};

        function speedtestValueToRatio(mbps) {
            const val = Math.max(0, parseFloat(mbps) || 0);
            const scale = [
                { limit: 0,    ratio: 0.000 },
                { limit: 5,    ratio: 0.125 },
                { limit: 10,   ratio: 0.250 },
                { limit: 50,   ratio: 0.375 },
                { limit: 100,  ratio: 0.500 },
                { limit: 250,  ratio: 0.625 },
                { limit: 500,  ratio: 0.750 },
                { limit: 750,  ratio: 0.875 },
                { limit: 1000, ratio: 1.000 }
            ];
            for (let i = 0; i < scale.length - 1; i++) {
                let current = scale[i];
                let next = scale[i+1];
                if (val >= current.limit && val <= next.limit) {
                    let rangeVal = val - current.limit;
                    let rangeMax = next.limit - current.limit;
                    let rangeRatio = rangeVal / rangeMax;
                    let ratioDiff = next.ratio - current.ratio;
                    return current.ratio + (rangeRatio * ratioDiff);
                }
            }
            return 1.0;
        }

        function updateDualSpeedtestGauges(downMbps, upMbps) {
            const downVal = parseFloat(downMbps) || 0;
            const upVal   = parseFloat(upMbps) || 0;
            const isDark  = document.documentElement.classList.contains('dark');

            // Down Gauge
            const downRatio = speedtestValueToRatio(downVal);
            const downArc = document.getElementById('stDownProgressArc');
            const downText = document.getElementById('stDownMainValue');
            const topDownText = document.getElementById('stTopDownVal');

            // ≥ 1000 Mbps se muestra en Gbps para que el número no se encime en la aguja
            const fmtGauge = (v, unitId, arrow) => {
                const unit = document.getElementById(unitId);
                if (unit) unit.textContent = `${arrow} ${v >= 1000 ? 'Gbps' : 'Mbps'}`;
                return v >= 1000 ? (v / 1000).toFixed(2) : v.toFixed(2);
            };
            if (downText) downText.textContent = fmtGauge(downVal, 'stDownUnit', '↓');
            if (topDownText) topDownText.textContent = downVal.toFixed(2);
            if (downArc) {
                downArc.style.strokeDashoffset = (541.92 * (1 - downRatio)).toFixed(2);
                downArc.setAttribute('stroke', isDark ? 'url(#stDownGradDark)' : 'url(#stDownGradLight)');
            }
            stAgujas.down.objetivo = downRatio;

            // Up Gauge
            const upRatio = speedtestValueToRatio(upVal);
            const upArc = document.getElementById('stUpProgressArc');
            const upText = document.getElementById('stUpMainValue');
            const topUpText = document.getElementById('stTopUpVal');

            if (upText) upText.textContent = fmtGauge(upVal, 'stUpUnit', '↑');
            if (topUpText) topUpText.textContent = upVal.toFixed(2);
            if (upArc) {
                upArc.style.strokeDashoffset = (541.92 * (1 - upRatio)).toFixed(2);
                upArc.setAttribute('stroke', isDark ? 'url(#stUpGradDark)' : 'url(#stUpGradLight)');
            }
            stAgujas.up.objetivo = upRatio;
        }

        // Agujas "vivas": un resorte las lleva hacia el valor real medido y encima
        // oscilan un poco, como un medidor analógico. Solo se mueve la aguja; el
        // número y el arco siempre muestran el dato real.
        const stAgujas = (function () {
            const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const crear = (id, fase) => ({ el: document.getElementById(id), objetivo: 0, pos: 0, vel: 0, fase, vivo: true });
            const agujas = {
                down: crear('stDownNeedleWedge', 0),
                up:   crear('stUpNeedleWedge', 2.1),
            };
            const RIGIDEZ = 60, AMORTIGUACION = 11;
            let previo = null;

            function cuadro(ahora) {
                // En pestaña oculta el navegador detiene el bucle; al volver no se "salta"
                const dt = previo === null ? 0 : Math.min((ahora - previo) / 1000, 0.05);
                previo = ahora;
                const t = ahora / 1000;

                for (const a of Object.values(agujas)) {
                    if (!a.el) continue;
                    if (reducido) {
                        a.pos = a.objetivo;
                    } else {
                        a.vel += ((a.objetivo - a.pos) * RIGIDEZ - a.vel * AMORTIGUACION) * dt;
                        a.pos += a.vel * dt;
                    }

                    // Temblor: suma de senos de periodos distintos (no se ve repetitivo),
                    // ~±1.5° en reposo y algo más con tráfico alto
                    let temblor = 0;
                    if (a.vivo && !reducido) {
                        const amp = 0.004 + a.objetivo * 0.006;
                        temblor = amp * (Math.sin(t * 1.7 + a.fase) + 0.6 * Math.sin(t * 4.3 + a.fase * 2) + 0.3 * Math.sin(t * 9.1 + a.fase * 3));
                    }
                    const ratio = Math.min(1.01, Math.max(0, a.pos + temblor));
                    a.el.style.transform = `rotate(${(-135 + ratio * 270).toFixed(2)}deg)`;
                }
                requestAnimationFrame(cuadro);
            }

            requestAnimationFrame(cuadro);

            return {
                ...agujas,
                // Sin datos la aguja se queda quieta en cero: no simular actividad
                vivas(si) { agujas.down.vivo = agujas.up.vivo = si; },
            };
        })();

        window.addEventListener('theme-changed', function() {
            updateDualSpeedtestGauges(latestDownMbps, latestUpMbps);
        });

        (function () {
            const ENDPOINT  = '{{ route("snmp.live_traffic") }}';
            const INTERVAL  = 3000; // ms
            const elTopDown = document.getElementById('stTopDownVal');
            const elTopUp   = document.getElementById('stTopUpVal');
            const elDot     = document.getElementById('snmpStatusDot');
            const elStatus  = document.getElementById('snmpStatusText');

            function setStatus(ok, msg) {
                if (!elDot || !elStatus) return;
                elDot.className   = `w-2.5 h-2.5 rounded-full ${ok ? 'bg-emerald-400 animate-pulse' : 'bg-red-400'}`;
                elStatus.textContent = msg;
            }

            async function fetchTraffic() {
                try {
                    const resp = await fetch(ENDPOINT, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

                    const data = await resp.json();

                    const elDevice = document.getElementById('snmpTrafficDevice');
                    const elMeasured = document.getElementById('snmpTrafficMeasured');
                    if (data.status === 'success') {
                        latestDownMbps = parseFloat(data.in_mbps);
                        latestUpMbps   = parseFloat(data.out_mbps);

                        updateDualSpeedtestGauges(latestDownMbps, latestUpMbps);
                        stAgujas.vivas(true);
                        setStatus(true, data.fuente === 'core' ? 'Switch core · SNMP' : 'Suma global · SNMP');
                        if (elDevice) elDevice.textContent = data.ip ? `${data.dispositivo} (${data.ip})` : data.dispositivo;
                        if (elMeasured) elMeasured.textContent = `Medido: ${data.medido_en}`;
                    } else {
                        updateDualSpeedtestGauges(0, 0);
                        stAgujas.vivas(false);
                        ['stDownMainValue', 'stUpMainValue', 'stTopDownVal', 'stTopUpVal'].forEach(id => {
                            const el = document.getElementById(id);
                            if (el) el.textContent = '—';
                        });
                        setStatus(false, 'Sin datos');
                        if (elDevice) elDevice.textContent = '—';
                        if (elMeasured) elMeasured.textContent = data.message ?? 'Sin mediciones recientes';
                    }
                } catch (err) {
                    setStatus(false, `Sin respuesta (${err.message})`);
                }
            }

            // Initial render, fetch & polling
            updateDualSpeedtestGauges(latestDownMbps, latestUpMbps);
            fetchTraffic();
            setInterval(fetchTraffic, INTERVAL);
        })();
    </script>

    {{-- ============================================================== --}}
    {{-- KPI Live Polling — fetch() global_metrics cada 15s             --}}
    {{-- ============================================================== --}}
    <script>
        (function () {
            const KPI_ENDPOINT = '{{ route("kpi.live") }}';
            const KPI_INTERVAL = 8000; // ms (al ritmo del sondeo)

            async function fetchKPIs() {
                try {
                    const resp = await fetch(KPI_ENDPOINT, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (!resp.ok) return;

                    const data = await resp.json();

                    // Update UI Elements safely
                    const setVal = (id, val) => {
                        const el = document.getElementById(id);
                        if(el && el.textContent != val) el.textContent = val;
                    };

                    const orNd = (v, suffix = '') => v === null || v === undefined ? 'Sin dato' : v + suffix;
                    setVal('kpiTraffic', orNd(data.traffic_mbps));
                    setVal('kpiPing', orNd(data.latency_ms));
                    setVal('kpiLoss', orNd(data.packet_loss_pct));
                    setVal('kpiCpuValue', orNd(data.cpu_avg_pct, '%'));
                    setVal('kpiMemValue', orNd(data.ram_avg_pct, '%'));
                    setVal('kpiNodesValue', `${data.dispositivos.online} / ${data.dispositivos.total}`);

                    // Muestra nueva del sondeo: entra por la derecha de las mini gráficas (solo si es más reciente)
                    const s = window.nmSparks;
                    if (s && data.t && (!s.ultimoT || data.t > s.ultimoT)) {
                        s.ultimoT = data.t;
                        [[s.traffic, data.traffic_mbps], [s.ping, data.latency_ms], [s.loss, data.packet_loss_pct]].forEach(([chart, v]) => {
                            if (chart) nmVivo.avanzar(chart, [[{ x: data.t, y: v ?? null }]], s.ventana);
                        });
                    }

                    // Actualizar Alertas y Notificaciones en Vivo
                    if (data.unread_notif_count !== undefined) {
                        const countBadge = document.getElementById('notifCountBadge');
                        const badgeText  = document.getElementById('notifBadgeText');
                        const bellBtn    = document.getElementById('notifBellBtn');

                        if (data.unread_notif_count > 0) {
                            if (countBadge) {
                                countBadge.textContent = data.unread_notif_count;
                            } else if (bellBtn) {
                                const newBadge = document.createElement('span');
                                newBadge.id = 'notifCountBadge';
                                newBadge.className = 'absolute -top-1 -right-1 min-w-[19px] h-[19px] px-1 bg-[#ff8300] text-white text-[11px] font-black rounded-full flex items-center justify-center ring-2 ring-white shadow';
                                newBadge.textContent = data.unread_notif_count;
                                bellBtn.appendChild(newBadge);
                            }
                            if (badgeText) {
                                badgeText.textContent = `${data.unread_notif_count} no leídas`;
                                badgeText.className = 'px-2 py-0.5 rounded-full text-[11px] font-black bg-nm-primary/10 text-nm-primary';
                            }
                        } else {
                            if (countBadge) countBadge.remove();
                            if (badgeText) {
                                badgeText.textContent = '0 no leídas';
                                badgeText.className = 'px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500';
                            }
                        }
                    }

                } catch (err) {
                    console.error('Error fetching live KPIs:', err);
                }
            }

            setInterval(fetchKPIs, KPI_INTERVAL);
        })();
    </script>

    {{-- ============================================================== --}}
    {{-- Tendencias (1 h / 24 h / 7 d) y navegación al diagnóstico      --}}
    {{-- ============================================================== --}}
    <script>
        function abrirDiagnostico(tab) {
            window.dispatchEvent(new CustomEvent('diag-tab', { detail: tab }));
            const el = document.getElementById('diagnosticoRed');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        (function () {
            const ENDPOINT = '{{ route("dashboard.series") }}';
            const lienzos = document.querySelectorAll('[data-trend-chart]');
            if (!lienzos.length) return;
            let rango = '24h', datos = null;
            const charts = {};

            const isDark = () => document.documentElement.classList.contains('dark');
            const gridColor = () => isDark() ? 'rgba(255, 255, 255, 0.06)' : 'rgba(156, 163, 175, 0.15)';
            const axisColor = () => isDark() ? '#94a3b8' : '#64748b';
            const fmtMbps = v => v >= 1000 ? (v / 1000).toFixed(1) + ' Gbps' : Math.round(v) + ' Mbps';
            // Ancho de la ventana visible, cada cuánto se piden muestras nuevas y formato de la hora por rango
            const VENTANA = { '1h': 3600e3, '24h': 86400e3, '7d': 7 * 86400e3 };
            const CADA = { '1h': 15000, '24h': 60000, '7d': 60000 };
            const fmtX = (t, largo = false) => rango === '7d' ? nmVivo.ddhhmm(t) : (rango === '1h' || largo ? nmVivo.hhmmss(t) : nmVivo.hhmm(t));

            const GRUPOS = {
                trafico: { fmtY: fmtMbps, series: [
                    { key: 'trafico_in', label: 'Entrada', color: '59, 89, 152', axis: 'y', fill: true },
                    { key: 'trafico_out', label: 'Salida', color: '242, 100, 25', axis: 'y', fill: true },
                ] },
                latencia: { fmtY: v => v + ' ms', fmtY1: v => v + ' %', series: [
                    { key: 'latencia', label: 'Latencia (ms)', color: '92, 128, 150', axis: 'y' },
                    { key: 'perdida', label: 'Pérdida (%)', color: '239, 68, 68', axis: 'y1' },
                ] },
                recursos: { fmtY: v => v + ' %', series: [
                    { key: 'cpu', label: 'CPU promedio', color: '242, 100, 25', axis: 'y' },
                    { key: 'ram', label: 'RAM promedio', color: '59, 89, 152', axis: 'y' },
                ] },
                // Los otros sectores traen su propio eje de tiempo (t: nombre del arreglo de momentos)
                firewalls: { fmtY: v => Math.round(v).toLocaleString('es-MX'), series: [
                    { key: 'fw_sesiones', t: 'fw_t', label: 'Sesiones (todos los FortiGate)', color: '242, 100, 25', axis: 'y', fill: true },
                ] },
                virtualizacion: { fmtY: v => v + ' %', series: [
                    { key: 'virt_cpu', t: 'virt_t', label: 'CPU hosts ESXi', color: '242, 100, 25', axis: 'y' },
                    { key: 'virt_ram', t: 'virt_t', label: 'Memoria hosts ESXi', color: '59, 89, 152', axis: 'y' },
                ] },
                web: { fmtY: v => Math.round(v) + ' ms', series: [
                    { key: 'web_ms', t: 'web_t', label: 'Respuesta promedio de los sitios', color: '92, 128, 150', axis: 'y', fill: true },
                ] },
            };
            const momentos = (d, s) => d[s.t || 't'] || [];

            function render() {
                if (!datos) return;
                lienzos.forEach(canvas => dibujar(canvas.dataset.trendChart, canvas));
            }

            function dibujar(metrica, canvas) {
                const g = GRUPOS[metrica];
                const hayDatos = g.series.some(s => (datos[s.key] || []).some(v => v !== null));
                document.querySelector(`[data-trend-empty="${metrica}"]`).style.display = hayDatos ? 'none' : 'flex';

                const ticks = { color: axisColor(), font: { family: 'monospace', size: 10 } };
                const scales = {
                    x: { grid: { color: gridColor() }, border: { display: false }, ticks: { ...ticks, maxTicksLimit: 5, maxRotation: 0, autoSkip: true } },
                    y: { position: 'left', beginAtZero: true, grid: { color: gridColor() }, border: { display: false }, ticks: { ...ticks, callback: g.fmtY } },
                };
                if (g.fmtY1) {
                    scales.y1 = { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, border: { display: false }, ticks: { ...ticks, callback: g.fmtY1 } };
                }
                // Eje de tiempo real: al llegar muestras nuevas la ventana avanza y la línea se desliza
                const finales = g.series.map(s => momentos(datos, s).slice(-1)[0]).filter(Boolean);
                const fin = finales.length ? Math.max(...finales) : Date.now();
                scales.x = { ...scales.x, type: 'linear', min: fin - VENTANA[rango], max: fin,
                             ticks: { ...scales.x.ticks, callback: v => fmtX(v) } };

                if (charts[metrica]) charts[metrica].destroy();
                charts[metrica] = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    plugins: [nmVivo.puntoVivo(g.series.map((_, i) => i))],
                    data: {
                        datasets: g.series.map(s => ({
                            label: s.label,
                            data: momentos(datos, s).map((t, i) => ({ x: t, y: datos[s.key][i] })),
                            yAxisID: s.axis,
                            borderColor: `rgb(${s.color})`,
                            backgroundColor: `rgba(${s.color}, 0.10)`,
                            fill: !!s.fill,
                            borderWidth: 2,
                            tension: 0.25,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            spanGaps: false,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        ...nmVivo.animaciones(1400),
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            // Con una sola serie el título de la tarjeta ya dice qué es
                            legend: { display: g.series.length > 1, align: 'end', labels: { color: axisColor(), boxWidth: 8, boxHeight: 8, font: { size: 10 } } },
                            tooltip: {
                                backgroundColor: isDark() ? 'rgba(18, 22, 31, 0.95)' : 'rgba(30, 41, 59, 0.95)',
                                titleFont: { family: 'monospace', size: 10 },
                                bodyFont: { family: 'monospace', size: 11, weight: '700' },
                                callbacks: {
                                    title: items => items.length ? fmtX(items[0].parsed.x, true) : '',
                                    label: ctx => {
                                        const v = ctx.parsed.y;
                                        if (v === null || v === undefined) return `${ctx.dataset.label}: sin dato`;
                                        const fmt = ctx.dataset.yAxisID === 'y1' ? g.fmtY1 : g.fmtY;
                                        return `${ctx.dataset.label}: ${fmt(v)}`;
                                    },
                                },
                            },
                        },
                        scales,
                    },
                });
            }

            async function pedir() {
                const resp = await fetch(`${ENDPOINT}?rango=${rango}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                return resp.json();
            }

            async function cargar() {
                try {
                    datos = await pedir();
                    render();
                } catch (err) {
                    console.error('Error cargando tendencias:', err);
                }
            }

            // Actualización periódica: solo agrega las muestras reales nuevas (o el promedio del bloque en curso)
            // y la ventana avanza; la gráfica no se vuelve a crear
            async function actualizar() {
                if (!datos || document.hidden) return;
                const pedido = rango;
                try {
                    const nuevos = await pedir();
                    if (pedido !== rango) return;
                    datos = nuevos;
                    Object.entries(charts).forEach(([metrica, chart]) => {
                        const g = GRUPOS[metrica];
                        if (!g.series.some(s => momentos(nuevos, s).length)) return;
                        const serie = (s, i) => {
                            const desde = chart.data.datasets[i]?.data.slice(-1)[0]?.x ?? -Infinity;
                            return momentos(nuevos, s).map((t, j) => ({ x: t, y: nuevos[s.key][j] })).filter(p => p.x >= desde);
                        };
                        nmVivo.avanzar(chart, g.series.map(serie), VENTANA[rango]);
                    });
                } catch (err) {
                    console.error('Error actualizando tendencias:', err);
                }
            }
            let ultimaActualizacion = 0;
            setInterval(() => {
                if (Date.now() - ultimaActualizacion < CADA[rango] - 500) return;
                ultimaActualizacion = Date.now();
                actualizar();
            }, 5000);

            function activar(selector, btn) {
                document.querySelectorAll(selector).forEach(b => b.classList.toggle('trend-btn-active', b === btn));
            }
            document.querySelectorAll('[data-trend-range]').forEach(btn => btn.addEventListener('click', () => {
                rango = btn.dataset.trendRange;
                activar('[data-trend-range]', btn);
                cargar();
            }));

            window.addEventListener('theme-changed', render);
            cargar();
            // El rango de 1 h se mantiene en vivo; 24 h y 7 d cambian poco
            setInterval(() => { if (rango === '1h') cargar(); }, 30000);
            setInterval(() => { if (rango !== '1h') cargar(); }, 300000);
        })();
    </script>
@endsection


<style>
/* From Uiverse.io by Nawsome */ 
.snmp-loader-container {
    transform: scale(0.35);
    transform-origin: right center;
    width: 30px;
    height: 35px;
    margin-right: 15px;
}
.snmp-loader {
  position: relative;
  width: 75px;
  height: 100px;
}
.snmp-loader__bar {
  position: absolute;
  bottom: 0;
  width: 10px;
  height: 50%;
  background: var(--nm-primary);
  transform-origin: center bottom;
  box-shadow: 1px 1px 0 rgba(0, 0, 0, 0.2);
  transition: background 0.3s ease;
}
.snmp-loader.error .snmp-loader__bar {
  background: #ef4444; /* Rojo Alerta */
}
.snmp-loader__bar:nth-child(1) {
  left: 0px;
  transform: scale(1, 0.2);
  animation: barUp1 4s infinite;
}
.snmp-loader__bar:nth-child(2) {
  left: 15px;
  transform: scale(1, 0.4);
  animation: barUp2 4s infinite;
}
.snmp-loader__bar:nth-child(3) {
  left: 30px;
  transform: scale(1, 0.6);
  animation: barUp3 4s infinite;
}
.snmp-loader__bar:nth-child(4) {
  left: 45px;
  transform: scale(1, 0.8);
  animation: barUp4 4s infinite;
}
.snmp-loader__bar:nth-child(5) {
  left: 60px;
  transform: scale(1, 1);
  animation: barUp5 4s infinite;
}
.snmp-loader__ball {
  position: absolute;
  bottom: 10px;
  left: 0;
  width: 10px;
  height: 10px;
  background: #ff8300; /* Naranja Institucional */
  border-radius: 50%;
  animation: ball624 4s infinite;
  transition: background 0.3s ease;
}
.snmp-loader.error .snmp-loader__ball {
  background: #991b1b;
  animation-play-state: paused;
}
.snmp-loader.error .snmp-loader__bar {
  animation-play-state: paused;
}

@keyframes ball624 {
  0% { transform: translate(0, 0); }
  5% { transform: translate(8px, -14px); }
  10% { transform: translate(15px, -10px); }
  17% { transform: translate(23px, -24px); }
  20% { transform: translate(30px, -20px); }
  27% { transform: translate(38px, -34px); }
  30% { transform: translate(45px, -30px); }
  37% { transform: translate(53px, -44px); }
  40% { transform: translate(60px, -40px); }
  50% { transform: translate(60px, 0); }
  57% { transform: translate(53px, -14px); }
  60% { transform: translate(45px, -10px); }
  67% { transform: translate(37px, -24px); }
  70% { transform: translate(30px, -20px); }
  77% { transform: translate(22px, -34px); }
  80% { transform: translate(15px, -30px); }
  87% { transform: translate(7px, -44px); }
  90% { transform: translate(0, -40px); }
  100% { transform: translate(0, 0); }
}
@keyframes barUp1 { 0%, 40%, 100% { transform: scale(1, 0.2); } 50%, 90% { transform: scale(1, 1); } }
@keyframes barUp2 { 0%, 40%, 100% { transform: scale(1, 0.4); } 50%, 90% { transform: scale(1, 0.8); } }
@keyframes barUp3 { 0%, 100% { transform: scale(1, 0.6); } }
@keyframes barUp4 { 0%, 40%, 100% { transform: scale(1, 0.8); } 50%, 90% { transform: scale(1, 0.4); } }
@keyframes barUp5 { 0%, 40%, 100% { transform: scale(1, 1); } 50%, 90% { transform: scale(1, 0.2); } }
</style>
