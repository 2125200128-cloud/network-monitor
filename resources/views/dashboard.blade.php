@extends('layouts.app')

@section('styles')
<style>
    @keyframes nocMarquee {
        0% { transform: translateX(0%); }
        100% { transform: translateX(-50%); }
    }
    .animate-noc-marquee {
        display: flex;
        width: max-content;
        animation: nocMarquee 35s linear infinite;
    }
    .animate-noc-marquee:hover {
        animation-play-state: paused;
    }

    /* ==================== SPEEDTEST.NET HIGH-FIDELITY GAUGE ==================== */
    .speedtest-container {
        background: #090b14 !important;
        background-image: radial-gradient(circle at 50% 120%, #151a32 0%, #06070c 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        border-radius: 1.75rem !important;
        color: #ffffff !important;
        box-shadow: 0 20px 45px -15px rgba(0, 0, 0, 0.7) !important;
        position: relative !important;
        width: 100% !important;
        min-height: 480px !important;
        display: block !important;
        box-sizing: border-box !important;
    }

    .st-tab-btn {
        transition: all 0.25s ease;
        position: relative;
        cursor: pointer;
    }
    .st-tab-btn.active-down {
        color: #ffffff;
    }
    .st-tab-btn.active-down::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 0;
        right: 0;
        height: 3px;
        background: #00e5ff;
        border-radius: 3px;
        box-shadow: 0 0 12px #00e5ff;
    }
    .st-tab-btn.active-up {
        color: #ffffff;
    }
    .st-tab-btn.active-up::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 0;
        right: 0;
        height: 3px;
        background: #ec4899;
        border-radius: 3px;
        box-shadow: 0 0 12px #ec4899;
    }

    .st-gauge-arc-bg {
        stroke: rgba(255, 255, 255, 0.10);
        stroke-width: 14;
        stroke-linecap: round;
        fill: none;
    }
    .st-gauge-arc-progress {
        stroke-width: 14;
        stroke-linecap: round;
        fill: none;
        transition: stroke-dashoffset 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .st-needle-wedge {
        transform-origin: 160px 160px;
        transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .st-tick-label {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 11px;
        font-weight: 700;
        fill: #94a3b8;
        text-anchor: middle;
        dominant-baseline: central;
        user-select: none;
    }
</style>
@endsection

@section('content')
            <!-- Top Bar -->
            <div class="flex justify-end items-center gap-3 mb-4 sm:mb-6">
                    <a href="{{ route('reportes.inventario_pdf') }}" target="_blank" class="fancy fancy-sm hidden sm:inline-flex" title="Descargar Reporte Ejecutivo de Inventario en PDF">
                        <span class="top-key"></span>
                        <span class="text">
                            <svg class="w-3.5 h-3.5 text-[#3b5998]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Reporte PDF
                        </span>
                        <span class="bottom-key-1"></span>
                        <span class="bottom-key-2"></span>
                    </a>
                    <a href="{{ route('setup-2fa') }}" class="w-10 h-10 bg-white dark:bg-[#0d1017] rounded-full flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-hacienda-blue dark:hover:text-blue-400 shadow-sm dark:shadow-none border border-gray-100 dark:border-slate-800/60 transition" title="Configurar 2FA">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </a>
                    {{-- Notification Bell and Dropdown Center --}}
                    <div class="relative" id="notifDropdownContainer">
                        <button id="notifBellBtn" type="button" onclick="toggleNotifDropdown(event)" class="w-10 h-10 bg-white dark:bg-[#0d1017] rounded-full flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-hacienda-orange shadow-sm dark:shadow-none border border-gray-100 dark:border-slate-800/60 transition relative focus:outline-none focus:ring-2 focus:ring-[#3b5998]/30" title="Centro de Notificaciones y Alertas">
                            @if($unreadNotificaciones > 0)
                                <span id="notifPulseRing" class="absolute top-2 right-2 w-2.5 h-2.5 bg-[#f26419] rounded-full animate-ping"></span>
                                <span id="notifPulseDot" class="absolute top-2 right-2 w-2.5 h-2.5 bg-[#f26419] rounded-full ring-2 ring-white"></span>
                                <span id="notifCountBadge" class="absolute -top-1 -right-1 min-w-[19px] h-[19px] px-1 bg-[#f26419] text-white text-[10px] font-black rounded-full flex items-center justify-center ring-2 ring-white shadow">
                                    {{ $unreadNotificaciones }}
                                </span>
                            @endif
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </button>

                        {{-- Dropdown Card --}}
                        <div id="notifDropdownMenu" class="hidden absolute right-0 mt-3 w-80 sm:w-96 md:w-[440px] bg-white dark:bg-[#0d1017] rounded-2xl shadow-2xl dark:shadow-none border border-gray-100 dark:border-slate-800/60 z-50 overflow-hidden transform transition-all duration-200 origin-top-right">
                            {{-- Header --}}
                            <div class="p-4 bg-gradient-to-r from-slate-50 to-blue-50/40 dark:from-[#090b10] dark:to-slate-900/90 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-[#3b5998] text-white flex items-center justify-center shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900 dark:text-white leading-tight">Centro de Notificaciones</h3>
                                        <p class="text-[11px] text-gray-500">Alertas NOC en tiempo real</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span id="notifBadgeText" class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $unreadNotificaciones > 0 ? 'bg-[#3b5998]/10 text-[#3b5998]' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $unreadNotificaciones }} no leídas
                                    </span>
                                    <button type="button" onclick="markAllNotificationsAsRead()" class="text-[11px] text-gray-500 hover:text-[#3b5998] dark:hover:text-blue-400 font-bold transition px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Marcar todas como leídas">
                                        Marcar Leídas
                                    </button>
                                    <button type="button" onclick="clearAllNotifications()" class="text-[11px] text-gray-500 hover:text-red-600 dark:hover:text-red-400 font-bold transition px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/40" title="Descartar y limpiar todas las alertas">
                                        Limpiar Todas
                                    </button>
                                </div>
                            </div>

                            {{-- Filter Tabs --}}
                            <div class="px-4 py-2 bg-gray-50/70 dark:bg-[#090b10] border-b border-gray-100 dark:border-slate-800/60 flex items-center gap-1.5 overflow-x-auto text-xs">
                                <button type="button" onclick="filterNotifs('all', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-bold text-xs bg-white dark:bg-slate-800 text-[#3b5998] dark:text-blue-400 shadow-xs dark:shadow-none border border-gray-200 dark:border-slate-700">
                                    Todas ({{ count($notificaciones) }})
                                </button>
                                <button type="button" onclick="filterNotifs('critica', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-semibold text-xs text-gray-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-slate-200 transition flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Críticas
                                </button>
                                <button type="button" onclick="filterNotifs('advertencia', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-semibold text-xs text-gray-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-slate-200 transition flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f26419]"></span>
                                    Advertencias
                                </button>
                                <button type="button" onclick="filterNotifs('sistema', this)" class="notif-tab-btn px-2.5 py-1 rounded-lg font-semibold text-xs text-gray-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-slate-200 transition flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#5c8096]"></span>
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
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            </div>
                                        @elseif($n['tipo'] === 'advertencia')
                                            <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-[#f26419] flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs border border-transparent dark:border-amber-900/40">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                        @else
                                            <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-[#3b5998] dark:text-blue-400 flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs border border-transparent dark:border-slate-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                        @endif

                                        {{-- Body --}}
                                        <div class="flex-1 min-w-0 pr-2">
                                            <div class="flex items-center justify-between gap-2 mb-1">
                                                <h4 class="text-xs font-extrabold text-gray-900 dark:text-slate-100 leading-tight">{{ $n['titulo'] }}</h4>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-extrabold font-mono bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700/80 shrink-0" title="Hora de falla: {{ $n['fecha_falla'] ?? $n['tiempo'] }}">
                                                    <svg class="w-3 h-3 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    <span>{{ $n['hora_falla'] ?? $n['tiempo'] }}</span>
                                                </span>
                                            </div>
                                            
                                            <p class="text-xs text-gray-600 dark:text-slate-300 leading-snug mb-1 font-medium">{{ $n['mensaje'] }}</p>

                                            @if(!empty($n['fecha_falla']))
                                                <div class="flex items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400 font-mono mb-2">
                                                    <span class="font-bold text-slate-600 dark:text-slate-300">🕒 Ocurrencia:</span>
                                                    <span class="font-extrabold text-slate-900 dark:text-slate-100">{{ $n['fecha_falla'] }}</span>
                                                    @if(!empty($n['hace_cuanto']))
                                                        <span class="text-slate-400">({{ $n['hace_cuanto'] }})</span>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Bloque de Causa Raíz Inteligente --}}
                                            @if(!empty($n['causa_raiz']))
                                                <div class="mb-2 p-2 rounded-xl bg-slate-50 dark:bg-[#07090e] border border-slate-200/80 dark:border-slate-800/80 text-[11px] leading-relaxed">
                                                    <div class="flex items-start gap-1.5 text-slate-700 dark:text-slate-300 mb-1">
                                                        <svg class="w-3.5 h-3.5 text-indigo-500 dark:text-indigo-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        <div>
                                                            <strong class="font-bold text-slate-900 dark:text-white">¿Por qué falló?</strong>
                                                            <span class="text-slate-600 dark:text-slate-400">{{ $n['causa_raiz'] }}</span>
                                                        </div>
                                                    </div>
                                                    @if(!empty($n['accion_sugerida']))
                                                        <div class="flex items-start gap-1.5 text-emerald-700 dark:text-emerald-400 pt-1 border-t border-slate-200/50 dark:border-slate-800/50">
                                                            <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                            <span><strong class="font-bold">Acción:</strong> {{ $n['accion_sugerida'] }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                            
                                            <div class="flex items-center justify-between pt-1">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-transparent dark:border-slate-700">
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $n['tipo'] === 'critica' ? 'bg-red-500' : ($n['tipo'] === 'advertencia' ? 'bg-[#f26419]' : 'bg-blue-500') }}"></span>
                                                        {{ $n['dispositivo'] }}
                                                    </span>
                                                    @if(!empty($n['ubicacion']) && $n['ubicacion'] !== 'No especificada')
                                                        <span class="text-[9px] text-gray-400 dark:text-slate-500 truncate max-w-[120px]" title="{{ $n['ubicacion'] }}">
                                                            📍 {{ $n['ubicacion'] }}
                                                        </span>
                                                    @endif
                                                </div>

                                                @if(isset($n['link']))
                                                    <a href="{{ $n['link'] }}" class="text-[11px] font-bold text-[#3b5998] dark:text-blue-400 hover:text-[#f26419] dark:hover:text-cyan-400 transition flex items-center gap-0.5 group">
                                                        <span>{{ $n['accion'] }}</span>
                                                        <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Unread Dot & Dismiss --}}
                                        <div class="flex flex-col items-center gap-2">
                                            @if(!$n['leida'])
                                                <span class="notif-unread-dot w-2 h-2 rounded-full bg-[#f26419]"></span>
                                            @endif
                                            <button type="button" onclick="dismissNotification('{{ $n['id'] }}', event)" class="text-gray-300 dark:text-slate-600 hover:text-gray-500 dark:hover:text-slate-400 p-0.5 rounded transition" title="Descartar">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-8 text-center">
                                        <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2 border border-transparent dark:border-emerald-800/40">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                        <p class="text-sm font-bold text-gray-800 dark:text-slate-100">Red Operando Normal</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-400 mt-1">No hay alertas ni incidencias reportadas en el sistema.</p>
                                    </div>
                                @endforelse
                                
                                {{-- Empty filter state --}}
                                <div id="notifFilterEmpty" class="hidden p-8 text-center">
                                    <div class="w-12 h-12 rounded-full bg-gray-50 dark:bg-slate-800 text-gray-400 flex items-center justify-center mx-auto mb-2">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                    </div>
                                    <p class="text-xs font-bold text-gray-700 dark:text-slate-300">Sin elementos en esta categoría</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">Selecciona otra pestaña o regresa a "Todas".</p>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="p-3 bg-gray-50 dark:bg-[#090b10] border-t border-gray-100 dark:border-slate-800/60 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1.5 text-gray-500 dark:text-slate-400 text-[11px]">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Sondeo SNMP Activo (60s)</span>
                                </div>
                                <a href="{{ route('settings.index', ['section' => 'notificaciones']) }}" class="text-[11px] font-bold text-[#3b5998] dark:text-blue-400 hover:text-[#f26419] transition">
                                    Configurar Alertas →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            @include('components.alert-banner')

            <!-- Hero Banner -->
            <div class="bg-white dark:bg-[#0d1017] border border-gray-100 dark:border-slate-800/40 rounded-[2rem] p-6 lg:p-8 text-gray-800 dark:text-slate-100 mb-8 relative overflow-hidden shadow-sm dark:shadow-none flex flex-col md:flex-row md:items-center justify-between gap-4 lg:gap-6 min-h-min">
                <!-- Background pattern (simulated) -->
                <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle at 2px 2px, #3b5998 1px, transparent 0); background-size: 32px 32px; z-index: 0;"></div>
                
                <div class="relative z-10 w-full md:flex-1">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold text-hacienda-blue dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 mb-3 md:mb-4 border border-transparent dark:border-blue-900/40">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path></svg>
                        {{ now()->format('M d, Y') }} | {{ $formattedUptime }} Global Uptime
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
                    <p class="text-gray-500 dark:text-slate-400 text-sm lg:text-lg max-w-2xl">Estado de la red estable. Tienes <span class="font-bold text-hacienda-blue dark:text-blue-400">{{ $dispositivosOnline }}</span> dispositivos monitoreados activos y respondiendo correctamente.</p>
                </div>
                
                <!-- Illustration Placeholder -->
                <div class="relative z-10 flex-shrink-0 mt-4 md:mt-0 md:w-1/3 flex justify-start md:justify-end">
                    <img src="{{ asset('images/logo.png') }}" class="h-16 lg:h-24 w-auto object-contain drop-shadow-md dark:drop-shadow-none dark:brightness-0 dark:invert dark:opacity-90 transition" alt="Logo">
                </div>
            </div>

            <!-- Mini Stat Cards (NOC Time Series) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Card 1: Tráfico -->
                <div class="floating-card p-5 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider font-mono">Tráfico de Red</p>
                            <div class="flex items-baseline gap-1 mt-1">
                                <span class="text-2xl font-bold text-gray-900 dark:text-white font-mono tracking-tight" id="kpiTraffic">{{ number_format($avgConexiones) }}</span>
                                <span class="text-xs font-mono text-gray-500">Mbps</span>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-100 font-mono">+12% vs avg</span>
                    </div>
                    <div class="w-full h-20 relative">
                        <canvas id="sparkTraffic"></canvas>
                    </div>
                </div>

                <!-- Card 2: Latencia -->
                <div class="floating-card p-5 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider font-mono">Latencia Global</p>
                            <div class="flex items-baseline gap-1 mt-1">
                                <span class="text-2xl font-bold text-gray-900 dark:text-white font-mono tracking-tight" id="kpiPing">{{ round($avgPing) }}</span>
                                <span class="text-xs font-mono text-gray-500">ms</span>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-100 font-mono">Óptima</span>
                    </div>
                    <div class="w-full h-20 relative">
                        <canvas id="sparkPing"></canvas>
                    </div>
                </div>

                <!-- Card 3: Pérdida de Paquetes -->
                <div class="floating-card p-5 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider font-mono">Pérdida Pkts</p>
                            <div class="flex items-baseline gap-1 mt-1">
                                <span class="text-2xl font-bold text-gray-900 dark:text-white font-mono tracking-tight" id="kpiLoss">{{ number_format($avgPacketLoss, 2) }}</span>
                                <span class="text-xs font-mono text-gray-500">%</span>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2.5 py-1 rounded-full border border-orange-100 font-mono">0.0% dev</span>
                    </div>
                    <div class="w-full h-20 relative">
                        <canvas id="sparkLoss"></canvas>
                    </div>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- LIVE TRAFFIC DUAL GAUGES (DOWNLOAD & UPLOAD)                              --}}
            {{-- ========================================================================= --}}
            <div class="floating-card p-6 sm:p-8 mb-6 bg-[#0b0f19] dark:bg-[#07090e] border border-slate-200 dark:border-slate-800" id="snmpTrafficCard">
                <div class="flex items-center justify-between mb-6 border-b border-gray-200 dark:border-white/10 pb-4">
                    <h3 class="text-sm font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider font-mono">Tráfico de Red en Vivo</h3>
                    <div class="flex items-center gap-3">
                        
                        <div class="snmp-loader-container" title="Sondeo en Vivo">
                            <div id="snmpLoader" class="snmp-loader">
                              <div class="snmp-loader__bar"></div>
                              <div class="snmp-loader__bar"></div>
                              <div class="snmp-loader__bar"></div>
                              <div class="snmp-loader__bar"></div>
                              <div class="snmp-loader__bar"></div>
                              <div class="snmp-loader__ball"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
                    {{-- Download Gauge (Blue) --}}
                    <div class="relative flex flex-col items-center justify-center">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-[#3b5998] dark:text-blue-400 tracking-wider uppercase mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                            <span>Descarga</span>
                        </div>
                        <div class="relative w-[240px] sm:w-[280px] h-[200px] sm:h-[240px] flex items-center justify-center">
                            <svg viewBox="0 0 320 320" class="w-full h-full overflow-visible">
                                <defs>
                                    <linearGradient id="stDownGrad" x1="0%" y1="100%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#3b5998" />
                                        <stop offset="100%" stop-color="#60a5fa" />
                                    </linearGradient>
                                    <linearGradient id="stNeedleGrad1" x1="0%" y1="100%" x2="0%" y2="0%">
                                        <stop offset="0%" stop-color="#3b5998" stop-opacity="0.0" />
                                        <stop offset="100%" stop-color="#3b5998" stop-opacity="0.85" />
                                    </linearGradient>
                                </defs>
                                <path d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" fill="none" stroke="currentColor" stroke-width="20" class="text-gray-200 dark:text-slate-800" stroke-linecap="round" />
                                <path id="stProgressArcDown" d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" fill="none" stroke="url(#stDownGrad)" stroke-width="20" stroke-dasharray="541.92" stroke-dashoffset="541.92" stroke-linecap="round" class="transition-all duration-700 ease-out" />
                                
                                <text x="102" y="218" class="text-xs font-mono fill-gray-400 dark:fill-slate-500">0</text>
                                <text x="160" y="78"  class="text-xs font-mono font-bold fill-gray-800 dark:fill-white">100</text>
                                <text x="218" y="218" class="text-xs font-mono fill-gray-400 dark:fill-slate-500">1000</text>

                                <g id="stNeedleWedgeDown" class="transition-transform duration-700 ease-out origin-center" style="transform: rotate(-135deg); transform-origin: 160px 160px;">
                                    <polygon points="155,160 165,160 160,50" fill="url(#stNeedleGrad1)" />
                                    <circle cx="160" cy="160" r="10" fill="#3b5998" />
                                    <circle cx="160" cy="160" r="4" fill="#ffffff" />
                                </g>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center pt-12">
                                <span id="stMainValueDown" class="text-3xl sm:text-4xl font-black font-mono tracking-tight text-gray-900 dark:text-white">{{ number_format($avgConexiones * 0.58, 2) }}</span>
                                <span class="text-[10px] font-bold text-gray-500 dark:text-slate-400 font-mono">Mbps</span>
                            </div>
                        </div>
                    </div>

                    {{-- Upload Gauge (Orange) --}}
                    <div class="relative flex flex-col items-center justify-center">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-[#f26419] dark:text-orange-400 tracking-wider uppercase mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            <span>Subida</span>
                        </div>
                        <div class="relative w-[240px] sm:w-[280px] h-[200px] sm:h-[240px] flex items-center justify-center">
                            <svg viewBox="0 0 320 320" class="w-full h-full overflow-visible">
                                <defs>
                                    <linearGradient id="stUpGrad" x1="0%" y1="100%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#f26419" />
                                        <stop offset="100%" stop-color="#fb923c" />
                                    </linearGradient>
                                    <linearGradient id="stNeedleGrad2" x1="0%" y1="100%" x2="0%" y2="0%">
                                        <stop offset="0%" stop-color="#f26419" stop-opacity="0.0" />
                                        <stop offset="100%" stop-color="#f26419" stop-opacity="0.85" />
                                    </linearGradient>
                                </defs>
                                <path d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" fill="none" stroke="currentColor" stroke-width="20" class="text-gray-200 dark:text-slate-800" stroke-linecap="round" />
                                <path id="stProgressArcUp" d="M 78.7 241.3 A 115 115 0 1 1 241.3 241.3" fill="none" stroke="url(#stUpGrad)" stroke-width="20" stroke-dasharray="541.92" stroke-dashoffset="541.92" stroke-linecap="round" class="transition-all duration-700 ease-out" />
                                
                                <text x="102" y="218" class="text-xs font-mono fill-gray-400 dark:fill-slate-500">0</text>
                                <text x="160" y="78"  class="text-xs font-mono font-bold fill-gray-800 dark:fill-white">100</text>
                                <text x="218" y="218" class="text-xs font-mono fill-gray-400 dark:fill-slate-500">1000</text>

                                <g id="stNeedleWedgeUp" class="transition-transform duration-700 ease-out origin-center" style="transform: rotate(-135deg); transform-origin: 160px 160px;">
                                    <polygon points="155,160 165,160 160,50" fill="url(#stNeedleGrad2)" />
                                    <circle cx="160" cy="160" r="10" fill="#f26419" />
                                    <circle cx="160" cy="160" r="4" fill="#ffffff" />
                                </g>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center pt-12">
                                <span id="stMainValueUp" class="text-3xl sm:text-4xl font-black font-mono tracking-tight text-gray-900 dark:text-white">{{ number_format($avgConexiones * 0.42, 2) }}</span>
                                <span class="text-[10px] font-bold text-gray-500 dark:text-slate-400 font-mono">Mbps</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
            <!-- Bottom Section (Doughnut & Progress) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-6">
                
                <!-- Health CPU / RAM (Concentric Donut / Dual Gauge) -->
                <div class="floating-card p-6 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider font-mono">Health CPU / RAM</h3>
                        <span class="text-xs bg-blue-50 text-hacienda-blue font-semibold px-3 py-1 rounded-full flex items-center font-mono">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-1.5"></span> Global
                        </span>
                    </div>
                    <div class="flex-1 flex flex-col sm:flex-row items-center justify-between gap-6">
                        <!-- Chart container -->
                        <div class="relative w-44 h-44 flex-shrink-0">
                            <canvas id="mainDoughnut"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                                <span class="text-2xl font-extrabold text-gray-900 dark:text-white font-mono leading-none tracking-tight">{{ round($avgCpu) }}<span class="text-xs text-gray-400 dark:text-slate-500 font-sans ml-0.5">%</span></span>
                                <span class="text-[9px] font-bold text-[#f26419] font-mono uppercase tracking-widest mt-0.5">CPU CORE</span>
                                <div class="mt-1 flex items-center gap-1 font-mono text-[11px] text-gray-500">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#3b5998]"></span>
                                    <span>RAM</span>
                                    <span class="font-bold text-[#3b5998]">{{ round($avgMem) }}%</span>
                                </div>
                            </div>
                        </div>
                              <!-- Legend -->
                        <div class="flex-1 w-full space-y-3">
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50/70 dark:bg-[#090b10] border border-gray-100 dark:border-slate-800/40">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-md bg-hacienda-orange mr-2.5 flex-shrink-0 shadow-sm"></span>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800 dark:text-slate-200">CPU Consumo</p>
                                        <p class="text-[10px] text-gray-400 dark:text-slate-500 font-mono">Track: 0 - 100%</p>
                                    </div>
                                </div>
                                <span class="text-lg font-extrabold text-gray-900 dark:text-white font-mono" id="kpiCpuValue">{{ round($avgCpu) }}%</span>
                            </div>

                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50/70 dark:bg-[#090b10] border border-gray-100 dark:border-slate-800/40">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-md bg-hacienda-blue mr-2.5 flex-shrink-0 shadow-sm"></span>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800 dark:text-slate-200">Memoria RAM</p>
                                        <p class="text-[10px] text-gray-400 dark:text-slate-500 font-mono">Track: 0 - 100%</p>
                                    </div>
                                </div>
                                <span class="text-lg font-extrabold text-gray-900 dark:text-white font-mono" id="kpiMemValue">{{ round($avgMem) }}%</span>
                            </div>

                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-gray-50/70 dark:bg-[#090b10] border border-gray-100 dark:border-slate-800/40">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-md bg-emerald-500 mr-2.5 flex-shrink-0 shadow-sm"></span>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800 dark:text-slate-200">Nodos Activos</p>
                                        <p class="text-[10px] text-gray-400 dark:text-slate-500 font-mono">Dispositivos en línea</p>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white font-mono" id="kpiNodesValue">{{ $dispositivosOnline }} / {{ $totalDispositivos }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bars -->
                <div class="floating-card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-sm font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Detalles de Operación</h3>
                        <span class="text-xs bg-gray-50 dark:bg-slate-800/60 text-gray-600 dark:text-slate-300 font-semibold px-3 py-1 rounded-full flex items-center cursor-pointer border border-transparent dark:border-slate-700/50">General <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></span>
                    </div>

                    <div class="space-y-6">
                        <!-- Temp -->
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-medium text-gray-600 dark:text-slate-400">Temperatura Promedio Global</span>
                                <span class="font-bold text-gray-800 dark:text-slate-200">{{ round($avgTemp) }} °C</span>
                            </div>
                            
                            <!-- Speedometer animado para Dashboard -->
                            @php
                                $dasharray = 125.6;
                                $percentage = min(100, max(0, ($avgTemp / 80) * 100)); // Escala hasta 80°C
                                $dashoffset = 125.6 - (125.6 * $percentage / 100);
                                $color = $avgTemp >= 60 ? '#ef4444' : ($avgTemp >= 45 ? '#f59e0b' : '#10b981');
                            @endphp
                            <div class="relative w-full h-20 overflow-hidden flex justify-center mt-3">
                                <svg viewBox="0 0 100 50" class="w-40 h-20 drop-shadow-md">
                                    <!-- Background arc -->
                                    <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#e2e8f0" class="dark:stroke-slate-700" stroke-width="10" stroke-linecap="round"/>
                                    <!-- Animated fill arc -->
                                    <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="{{ $color }}" stroke-width="10" stroke-linecap="round" 
                                          stroke-dasharray="125.6" stroke-dashoffset="125.6" 
                                          class="dash-gauge-fill drop-shadow-sm"
                                          style="--dash-target-offset: {{ $dashoffset }};"/>
                                </svg>
                                <div class="absolute bottom-0 left-0 w-full text-center flex flex-col items-center">
                                    <span class="text-2xl font-extrabold text-gray-800 dark:text-white font-mono leading-none">{{ round($avgTemp) }}<span class="text-sm text-gray-400">°C</span></span>
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-1">{{ $avgTemp < 45 ? 'ÓPTIMO' : 'ALERTA' }}</span>
                                </div>
                            </div>
                            
                            <style>
                                @keyframes dashFillGauge {
                                    from { stroke-dashoffset: 125.6; }
                                    to { stroke-dashoffset: var(--dash-target-offset); }
                                }
                                .dash-gauge-fill {
                                    animation: dashFillGauge 1.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
                                }
                            </style>
                        </div>

                        <!-- Conexiones -->
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-medium text-gray-600 dark:text-slate-400">Saturación de Puertos</span>
                                <span class="font-bold text-gray-800 dark:text-slate-200">{{ round(min(100, $avgConexiones / 15)) }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-slate-800/80 rounded-full h-2">
                                <div class="bg-hacienda-orange h-2 rounded-full" style="width: {{ round(min(100, $avgConexiones / 15)) }}%"></div>
                            </div>
                        </div>

                        <!-- Errores -->
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-medium text-gray-600 dark:text-slate-400">Tasa de Errores</span>
                                <span class="font-bold text-gray-800 dark:text-slate-200">{{ round($avgErrores) }} / 100</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-slate-800/80 rounded-full h-2">
                                @php $errPercent = min(100, max(0, $avgErrores)); @endphp
                                <div class="bg-pink-400 h-2 rounded-full" style="width: {{ $errPercent }}%"></div>
                            </div>
                        </div>
                        
                        <div class="mt-8 pt-4 border-t border-dashed border-gray-200 dark:border-slate-800/40">
                            <button class="w-full py-2 text-sm font-semibold text-hacienda-blue dark:text-blue-400 hover:text-[#4a6b7d] dark:hover:text-blue-300 transition flex items-center justify-center">
                                Generar Reporte +
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ==================== TABLA DE INVENTARIO DE DISPOSITIVOS (NOC) ==================== -->
            <div class="floating-card p-6 mb-6" x-data="{
                filtroBusqueda: '',
                filtroEstado: 'todos',
                filtroTipo: 'todos',
                matches(nombre, ip, estado, tipo) {
                    const q = this.filtroBusqueda.toLowerCase().trim();
                    const matchText = !q || nombre.toLowerCase().includes(q) || ip.toLowerCase().includes(q);
                    const matchStatus = this.filtroEstado === 'todos' || estado.toLowerCase() === this.filtroEstado;
                    const matchTipo = this.filtroTipo === 'todos' || tipo === this.filtroTipo;
                    return matchText && matchStatus && matchTipo;
                }
            }">
                <!-- Encabezado con Título, Filtros de Categoría, Estado y Buscador -->
                <div class="flex flex-col gap-4 mb-5 pb-4 border-b border-gray-100 dark:border-slate-800/40">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-hacienda-orange shadow-sm"></span>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">Inventario de Dispositivos e Infraestructura</h3>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Supervisión en tiempo real de switches, teléfonos VoIP, servidores, PCs, routers y APs</p>
                        </div>

                        <!-- Buscador en tiempo real -->
                        <div class="relative w-full sm:w-72">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <input 
                                type="text" 
                                x-model="filtroBusqueda"
                                placeholder="Buscar por nombre, IP, tipo..." 
                                class="w-full pl-9 pr-8 py-2 bg-gray-50 dark:bg-[#090b10] border border-gray-200 dark:border-slate-800/60 rounded-xl text-xs focus:ring-2 focus:ring-hacienda-blue dark:focus:ring-blue-500 focus:border-transparent outline-none transition placeholder-gray-400 dark:placeholder-slate-500 text-gray-800 dark:text-slate-200"
                            >
                            <button 
                                x-show="filtroBusqueda.length > 0" 
                                @click="filtroBusqueda = ''"
                                type="button" 
                                class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600"
                                style="display: none;">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Barra de Filtros Dual: Categorías de Hardware + Estado Online/Offline -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <!-- Filtros por Tipo de Dispositivo -->
                        <div class="flex flex-wrap items-center gap-1.5 bg-gray-100/80 dark:bg-[#090b10] border border-transparent dark:border-slate-800/40 p-1 rounded-xl text-xs font-semibold">
                            <button 
                                @click="filtroTipo = 'todos'"
                                :class="filtroTipo === 'todos' ? 'bg-white dark:bg-slate-800 text-hacienda-blue dark:text-blue-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition"
                                type="button">
                                Todos ({{ $totalDispositivos }})
                            </button>
                            <button 
                                @click="filtroTipo = 'switch'"
                                :class="filtroTipo === 'switch' ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                Switches ({{ $totalSwitches }})
                            </button>
                            <button 
                                @click="filtroTipo = 'telefono'"
                                :class="filtroTipo === 'telefono' ? 'bg-white dark:bg-slate-800 text-pink-600 dark:text-pink-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <img src="{{ asset('images/topology/ip-phone.png') }}" class="w-4 h-4 object-contain" alt="Teléfonos">
                                Teléfonos IP ({{ $totalTelefonos }})
                            </button>
                            <button 
                                @click="filtroTipo = 'router'"
                                :class="filtroTipo === 'router' ? 'bg-white dark:bg-slate-800 text-orange-600 dark:text-orange-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <img src="{{ asset('images/topology/router.png') }}" class="w-4 h-4 object-contain" alt="Routers">
                                Routers ({{ $totalRouters }})
                            </button>
                            <button 
                                @click="filtroTipo = 'access_point'"
                                :class="filtroTipo === 'access_point' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <img src="{{ asset('images/topology/access-point.png') }}" class="w-4 h-4 object-contain" alt="APs">
                                APs Wi-Fi ({{ $totalAPs }})
                            </button>
                            <button 
                                @click="filtroTipo = 'servidor'"
                                :class="filtroTipo === 'servidor' ? 'bg-white dark:bg-slate-800 text-purple-600 dark:text-purple-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <img src="{{ asset('images/topology/server.png') }}" class="w-4 h-4 object-contain" alt="Servidores">
                                Servidores ({{ $totalServidores }})
                            </button>
                            <button 
                                @click="filtroTipo = 'pc'"
                                :class="filtroTipo === 'pc' ? 'bg-white dark:bg-slate-800 text-cyan-600 dark:text-cyan-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <img src="{{ asset('images/topology/pc.png') }}" class="w-4 h-4 object-contain" alt="PCs">
                                PCs ({{ $totalPCs }})
                            </button>
                        </div>

                        <!-- Filtros por Estado -->
                        <div class="flex items-center bg-gray-100/80 dark:bg-[#090b10] border border-transparent dark:border-slate-800/40 p-1 rounded-xl text-xs font-semibold">
                            <button 
                                @click="filtroEstado = 'todos'"
                                :class="filtroEstado === 'todos' ? 'bg-white dark:bg-slate-800 text-hacienda-blue dark:text-blue-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-3 py-1.5 rounded-lg transition"
                                type="button">
                                Todos
                            </button>
                            <button 
                                @click="filtroEstado = 'online'"
                                :class="filtroEstado === 'online' ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Online ({{ $dispositivosOnline }})
                            </button>
                            <button 
                                @click="filtroEstado = 'offline'"
                                :class="filtroEstado === 'offline' ? 'bg-white dark:bg-slate-800 text-red-600 dark:text-red-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-3 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                Offline ({{ $dispositivosOffline }})
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenedor con Scroll Interno Controlado (Evita scroll infinito de la página) -->
                <div class="rounded-xl border border-gray-200/80 dark:border-slate-800/40 overflow-hidden shadow-xs dark:shadow-none bg-white dark:bg-[#0d1017]">
                    <div x-ref="tableContainer" class="max-h-[420px] overflow-y-auto overflow-x-auto custom-scrollbar relative" style="scrollbar-gutter: stable;">
                        <table class="w-full text-left border-separate border-spacing-0 text-xs">
                            <thead class="sticky top-0 z-20 shadow-[0_2px_4px_rgba(0,0,0,0.04)] dark:shadow-none">
                                <tr class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-[#0d1017]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-28">Estado</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-[#0d1017]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 min-w-[210px]">Dispositivo / Hostname</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-[#0d1017]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-32">Dirección IP</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-[#0d1017]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 min-w-[160px]">Tipo / Modelo</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-[#0d1017]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-32">CPU / RAM (%)</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-[#0d1017]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-28">Uptime</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-[#0d1017]/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-36 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800/30 bg-white dark:bg-[#0d1017]">
                                @forelse($dispositivos as $disp)
                                <tr 
                                    x-show="matches('{{ addslashes($disp->nombre) }}', '{{ $disp->ip }}', '{{ $disp->estado }}', '{{ $disp->tipo_dispositivo }}')"
                                    class="hover:bg-blue-50/40 dark:hover:bg-slate-800/40 transition duration-150 group">
                                    
                                    <!-- Estado LED / Badge -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        @if(strtolower($disp->estado) === 'online' || strtolower($disp->estado) === 'up')
                                             <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Online
                                            </span>
                                        @elseif(strtolower($disp->estado) === 'offline' || strtolower($disp->estado) === 'down')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800/40">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                Offline
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700/40">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                                Unknown
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Nombre del Dispositivo con Icono Específico Más Grande -->
                                    <td class="py-2.5 px-3.5 border-b border-gray-100 dark:border-slate-800/30">
                                        <div class="flex items-center gap-2.5">
                                            <!-- Icono según el tipo real -->
                                            <div class="w-9 h-9 rounded-xl {{ $disp->tipo_icon_container_classes }} flex items-center justify-center flex-shrink-0 font-bold shadow-xs border border-transparent dark:border-white/5" title="{{ $disp->tipo_label }}">
                                                @if($disp->tipo_dispositivo === 'telefono')
                                                    <!-- Teléfono IP (VoIP) -->
                                                    <img src="{{ asset('images/topology/ip-phone.png') }}" class="w-6 h-6 object-contain" alt="Teléfono">
                                                @elseif($disp->tipo_dispositivo === 'servidor')
                                                    <!-- Servidor Dedicado (Icono Isométrico) -->
                                                    <img src="{{ asset('images/topology/server.png') }}" class="w-6 h-6 object-contain" alt="Servidor">
                                                @elseif($disp->tipo_dispositivo === 'pc')
                                                    <!-- PC / Workstation -->
                                                    <img src="{{ asset('images/topology/pc.png') }}" class="w-6 h-6 object-contain" alt="PC">
                                                @elseif($disp->tipo_dispositivo === 'access_point')
                                                    <!-- Access Point Wi-Fi -->
                                                    <img src="{{ asset('images/topology/access-point.png') }}" class="w-6 h-6 object-contain" alt="Access Point">
                                                @elseif($disp->tipo_dispositivo === 'router')
                                                    <!-- Router WAN / ISR -->
                                                    <img src="{{ asset('images/topology/router.png') }}" class="w-6 h-6 object-contain" alt="Router">
                                                @else
                                                    <!-- Switch L2/L3 -->
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                                @endif
                                            </div>
                                            <div class="min-w-0 max-w-[200px] sm:max-w-[240px]">
                                                <a href="{{ route('dispositivos.show', $disp->id) }}" class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-hacienda-blue dark:group-hover:text-blue-400 hover:underline transition truncate block" title="{{ $disp->nombre }}">
                                                    {{ $disp->nombre }}
                                                </a>
                                                <span class="text-[10px] text-gray-400 dark:text-slate-500 font-mono truncate block">{{ $disp->ubicacion ?: 'NOC Data Center' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Dirección IP -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        <span class="font-mono text-xs text-gray-700 dark:text-slate-300 bg-gray-50 dark:bg-[#090b10] px-2 py-0.5 rounded-md border border-gray-200 dark:border-slate-800/60">
                                            {{ $disp->ip }}
                                        </span>
                                    </td>

                                    <!-- Modelo / Plataforma y Badge de Tipo -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border {{ $disp->tipo_badge_classes }}">
                                            {{ $disp->clean_model }}
                                        </span>
                                    </td>

                                    <!-- CPU y Memoria (%) -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        <div class="flex flex-col gap-1.5">
                                            <!-- CPU -->
                                            <div class="flex items-center gap-2">
                                                <span class="text-[9px] font-bold text-gray-400 w-6">CPU</span>
                                                <div class="w-12 bg-gray-100 dark:bg-slate-800/80 rounded-full h-1.5 overflow-hidden flex-shrink-0">
                                                    <div class="h-1.5 rounded-full {{ $disp->cpu_usage > 75 ? 'bg-red-500' : ($disp->cpu_usage > 40 ? 'bg-[#f26419]' : 'bg-[#3b5998]') }}" style="width: {{ min(100, max(4, $disp->cpu_usage ?? 0)) }}%"></div>
                                                </div>
                                                <span class="font-mono font-bold text-gray-800 dark:text-slate-200 text-[10px] w-6 text-right">{{ $disp->cpu_usage ?? 0 }}%</span>
                                            </div>
                                            <!-- Memoria -->
                                            <div class="flex items-center gap-2">
                                                <span class="text-[9px] font-bold text-gray-400 w-6">RAM</span>
                                                <div class="w-12 bg-gray-100 dark:bg-slate-800/80 rounded-full h-1.5 overflow-hidden flex-shrink-0">
                                                    <div class="h-1.5 rounded-full {{ $disp->memoria_usage > 75 ? 'bg-red-500' : ($disp->memoria_usage > 40 ? 'bg-[#f26419]' : 'bg-[#3b5998]') }}" style="width: {{ min(100, max(4, $disp->memoria_usage ?? 0)) }}%"></div>
                                                </div>
                                                <span class="font-mono font-bold text-gray-800 dark:text-slate-200 text-[10px] w-6 text-right">{{ $disp->memoria_usage ?? 0 }}%</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Uptime -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        <span class="font-mono text-xs {{ $disp->estado === 'online' ? 'text-gray-600 dark:text-slate-400' : 'text-gray-400 dark:text-slate-600' }}">
                                            {{ $disp->clean_uptime }}
                                        </span>
                                    </td>

                                    <!-- Acciones Rápidas -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap text-right border-b border-gray-100 dark:border-slate-800/30">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Ver Detalle -->
                                            <a href="{{ route('dispositivos.show', $disp->id) }}" 
                                               class="px-2.5 py-1 rounded-lg text-xs font-semibold text-hacienda-blue dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 hover:bg-hacienda-blue dark:hover:bg-blue-600 hover:text-white dark:hover:text-white transition flex items-center gap-1 shadow-xs border border-transparent dark:border-blue-900/40"
                                               title="Ver detalles e interfaces del dispositivo">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                <span>Detalles</span>
                                            </a>
                                            <!-- Consola CLI -->
                                            <a href="{{ route('consola.index') }}" 
                                               class="px-2.5 py-1 rounded-lg text-xs font-semibold text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 hover:bg-gray-900 dark:hover:bg-slate-700 hover:text-emerald-400 transition font-mono flex items-center gap-1 shadow-xs border border-transparent dark:border-slate-700"
                                               title="Abrir consola CLI remota">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                <span>CLI</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-400 dark:text-slate-500">
                                        No hay dispositivos registrados en el sistema.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Informativo y Control de Desplazamiento -->
                <div class="mt-3.5 pt-3 border-t border-gray-100 dark:border-slate-800/40 flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-400 dark:text-slate-400 gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="font-medium text-gray-600 dark:text-slate-400">Desplazamiento interno independiente · <strong class="text-gray-800 dark:text-white">{{ $totalDispositivos }}</strong> nodos monitoreados</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-[11px] font-mono text-gray-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                            <span>Scroll interno activo</span>
                        </div>
                        <button 
                            @click="$refs.tableContainer.scrollTo({top: 0, behavior: 'smooth'})" 
                            type="button" 
                            class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-600 dark:text-slate-300 transition flex items-center gap-1 border border-transparent dark:border-slate-700"
                            title="Subir al inicio de la tabla">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                            <span>Inicio</span>
                        </button>
                    </div>
                </div>
            </div>
@endsection

@php
    $now = now();
    $defaultTimestamps = [];
    for ($i = 6; $i >= 0; $i--) {
        $defaultTimestamps[] = $now->copy()->subMinutes($i * 5)->format('H:i:s');
    }
    
    $formattedLabels = count($labels) >= 4 
        ? array_map(fn($t) => strlen($t) <= 5 ? $t . ':00' : $t, array_slice($labels, -7))
        : $defaultTimestamps;

    $finalTraffic = (count($trafficIn) >= 4)
        ? array_slice($trafficIn, -7)
        : [14.2, 18.5, 16.1, 24.8, 19.3, 27.6, max(5, round($avgConexiones, 1))];

    $finalPing = (count($pingData) >= 4)
        ? array_slice($pingData, -7)
        : [12.4, 11.8, 15.2, 13.1, 12.0, 14.5, max(1, round($avgPing, 1))];

    $finalLoss = [0.0, 0.0, 0.4, 0.0, 0.2, 0.0, round($avgPacketLoss, 2)];
@endphp

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const timeLabels = @json($formattedLabels);
            const trafficData = @json($finalTraffic);
            const pingData = @json($finalPing);
            const lossData = @json($finalLoss);

            const avgCpu = {{ round($avgCpu) }};
            const avgMem = {{ round($avgMem) }};

            window.dashboardCharts = [];

            function isDarkTheme() {
                return document.documentElement.classList.contains('dark');
            }

            function getGridColor() {
                return isDarkTheme() ? 'rgba(255, 255, 255, 0.06)' : 'rgba(156, 163, 175, 0.12)';
            }

            function getAxisColor() {
                return isDarkTheme() ? '#94a3b8' : '#64748b';
            }

            // Diagnostic Terminal Tooltip Configuration
            function getTerminalTooltip(metricName, unit) {
                const isDark = isDarkTheme();
                return {
                    enabled: true,
                    backgroundColor: isDark ? 'rgba(18, 22, 31, 0.95)' : 'rgba(30, 41, 59, 0.95)',
                    titleColor: isDark ? '#94a3b8' : '#cbd5e1',
                    bodyColor: '#ffffff',
                    borderColor: isDark ? 'rgba(51, 65, 85, 0.8)' : 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    padding: { top: 6, bottom: 6, left: 10, right: 10 },
                    cornerRadius: 6,
                    displayColors: false,
                    titleFont: { family: 'monospace', size: 10, weight: '600' },
                    bodyFont: { family: 'monospace', size: 11, weight: '700' },
                    callbacks: {
                        title: function(items) {
                            return 'DIAG // ' + items[0].label;
                        },
                        label: function(context) {
                            const val = context.parsed.y;
                            const formatted = typeof val === 'number' 
                                ? val.toFixed(unit === 'ms' ? 1 : 2) 
                                : val;
                            return `${metricName}: ${formatted} ${unit}`;
                        }
                    }
                };
            }

            // Factory for NOC Time-Series with Vertical LinearGradient & Technical Grid
            function initNocTimeSeries(canvasId, metricName, dataVals, colorRgb, unit) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                const ctx = canvas.getContext('2d');

                const chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: timeLabels,
                        datasets: [{
                            label: metricName,
                            data: dataVals,
                            borderColor: `rgb(${colorRgb})`,
                            borderWidth: 2,
                            fill: true,
                            backgroundColor: function(context) {
                                const chart = context.chart;
                                const { ctx, chartArea } = chart;
                                if (!chartArea) return 'transparent';
                                const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                                gradient.addColorStop(0, `rgba(${colorRgb}, 0.25)`);
                                gradient.addColorStop(1, `rgba(${colorRgb}, 0.0)`);
                                return gradient;
                            },
                            tension: 0.25,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            pointHoverBackgroundColor: `rgb(${colorRgb})`,
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 800, easing: 'easeOutQuart' },
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: getTerminalTooltip(metricName, unit)
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: true,
                                    color: getGridColor(),
                                    borderDash: [3, 3],
                                    drawTicks: false
                                },
                                border: { display: false },
                                ticks: {
                                    font: { family: 'monospace', size: 9 },
                                    color: getAxisColor(),
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 4
                                }
                            },
                            y: {
                                display: true,
                                position: 'right',
                                grid: {
                                    display: true,
                                    color: getGridColor(),
                                    borderDash: [3, 3],
                                    drawTicks: false
                                },
                                border: { display: false },
                                ticks: {
                                    font: { family: 'monospace', size: 9 },
                                    color: getAxisColor(),
                                    maxTicksLimit: 3,
                                    callback: function(val) {
                                        return val + ' ' + unit;
                                    }
                                },
                                suggestedMin: 0
                            }
                        },
                        layout: {
                            padding: { top: 4, bottom: 2, left: 0, right: 4 }
                        }
                    }
                });

                window.dashboardCharts.push(chart);
                return chart;
            }

            // 1. Tráfico: Azul Rey (#3b5998 -> 59, 89, 152)
            initNocTimeSeries('sparkTraffic', 'TRAFFIC', trafficData, '59, 89, 152', 'Mbps');

            // 2. Latencia: Slate Blue (#5c8096 -> 92, 128, 150)
            initNocTimeSeries('sparkPing', 'LATENCY', pingData, '92, 128, 150', 'ms');

            // 3. Pérdida: Naranja Institucional (#f26419 -> 242, 100, 25)
            initNocTimeSeries('sparkLoss', 'LOSS', lossData, '242, 100, 25', '%');

            // 4. Concentric Dual-Gauge Donut (Health CPU / RAM)
            const doughnutCanvas = document.getElementById('mainDoughnut');
            if (doughnutCanvas) {
                const emptyTrackColor = isDarkTheme() ? 'rgba(255, 255, 255, 0.08)' : '#e5e7eb';
                const doughnutChart = new Chart(doughnutCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Used', 'Idle'],
                        datasets: [
                            {
                                label: 'CPU',
                                data: [avgCpu, Math.max(0, 100 - avgCpu)],
                                backgroundColor: ['#f26419', emptyTrackColor],
                                borderWidth: 0,
                                borderRadius: [4, 0],
                                hoverOffset: 3,
                                weight: 1.0
                            },
                            {
                                label: 'RAM',
                                data: [avgMem, Math.max(0, 100 - avgMem)],
                                backgroundColor: ['#3b5998', emptyTrackColor],
                                borderWidth: 0,
                                borderRadius: [4, 0],
                                hoverOffset: 3,
                                weight: 0.85
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        animation: { duration: 1000, easing: 'easeOutQuart' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: isDarkTheme() ? 'rgba(18, 22, 31, 0.95)' : '#1e293b',
                                titleColor: isDarkTheme() ? '#94a3b8' : '#cbd5e1',
                                bodyColor: '#f8fafc',
                                borderColor: isDarkTheme() ? '#334155' : 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1,
                                padding: { top: 6, bottom: 6, left: 10, right: 10 },
                                cornerRadius: 6,
                                displayColors: false,
                                titleFont: { family: 'monospace', size: 10, weight: '600' },
                                bodyFont: { family: 'monospace', size: 11, weight: '700' },
                                callbacks: {
                                    title: function(items) {
                                        return 'SUBSYSTEM // ' + items[0].dataset.label;
                                    },
                                    label: function(context) {
                                        const isUsed = context.dataIndex === 0;
                                        const status = isUsed ? 'LOAD' : 'FREE';
                                        return `${context.dataset.label} ${status}: ${context.raw}%`;
                                    }
                                }
                            }
                        }
                    }
                });

                window.dashboardCharts.push(doughnutChart);
            }

            // Real-time Theme Listener to Update All Charts without reload
            window.addEventListener('theme-changed', function(e) {
                const isDark = e.detail.isDark;
                const gridCol = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(156, 163, 175, 0.12)';
                const axisCol = isDark ? '#94a3b8' : '#64748b';
                const emptyCol = isDark ? 'rgba(255, 255, 255, 0.08)' : '#e5e7eb';
                const tooltipBg = isDark ? 'rgba(18, 22, 31, 0.95)' : 'rgba(30, 41, 59, 0.95)';
                const tooltipBorder = isDark ? '#334155' : 'rgba(255, 255, 255, 0.1)';

                window.dashboardCharts.forEach(ch => {
                    if (ch.config.type === 'line') {
                        if (ch.options.scales.x) {
                            ch.options.scales.x.grid.color = gridCol;
                            ch.options.scales.x.ticks.color = axisCol;
                        }
                        if (ch.options.scales.y) {
                            ch.options.scales.y.grid.color = gridCol;
                            ch.options.scales.y.ticks.color = axisCol;
                        }
                        if (ch.options.plugins && ch.options.plugins.tooltip) {
                            ch.options.plugins.tooltip.backgroundColor = tooltipBg;
                            ch.options.plugins.tooltip.borderColor = tooltipBorder;
                        }
                    } else if (ch.config.type === 'doughnut') {
                        ch.data.datasets[0].backgroundColor[1] = emptyCol;
                        ch.data.datasets[1].backgroundColor[1] = emptyCol;
                        if (ch.options.plugins && ch.options.plugins.tooltip) {
                            ch.options.plugins.tooltip.backgroundColor = tooltipBg;
                            ch.options.plugins.tooltip.borderColor = tooltipBorder;
                        }
                    }
                    ch.update('none');
                });
            });
        });

        // ==========================================
        // CENTRO DE NOTIFICACIONES & ALERTAS NOC
        // ==========================================
        let unreadNotifCount = {{ $unreadNotificaciones }};

        function toggleNotifDropdown(event) {
            if (event) {
                event.stopPropagation();
            }
            const menu = document.getElementById('notifDropdownMenu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
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
                b.classList.remove('bg-white', 'text-[#3b5998]', 'shadow-xs', 'border', 'border-gray-200');
                b.classList.add('text-gray-600');
            });
            if (btn) {
                btn.classList.add('bg-white', 'text-[#3b5998]', 'shadow-xs', 'border', 'border-gray-200');
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
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
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

        function markAllNotificationsAsRead() {
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
                badgeText.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500';
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

        function clearAllNotifications() {
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
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
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
                badgeText.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500';
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
                    badgeText.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500';
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
        let latestDownMbps = {{ round($avgConexiones * 0.58, 2) }};
        let latestUpMbps = {{ round($avgConexiones * 0.42, 2) }};

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

        function updateGauge(mbps, type) {
            const arcId = type === 'down' ? 'stProgressArcDown' : 'stProgressArcUp';
            const needleId = type === 'down' ? 'stNeedleWedgeDown' : 'stNeedleWedgeUp';
            const valId = type === 'down' ? 'stMainValueDown' : 'stMainValueUp';

            const arc = document.getElementById(arcId);
            const needle = document.getElementById(needleId);
            const valEl = document.getElementById(valId);

            if (!arc || !needle || !valEl) return;

            const val = Math.max(0, parseFloat(mbps) || 0);
            valEl.textContent = val.toFixed(2);

            const ratio = speedtestValueToRatio(val);
            const totalArcLength = 541.92;
            const offset = totalArcLength * (1 - ratio);
            arc.style.strokeDashoffset = offset;

            const baseAngle = -135;
            const sweep = 270;
            const needleAngle = baseAngle + (ratio * sweep);
            needle.style.transform = `rotate(${needleAngle}deg)`;
        }

        document.addEventListener('DOMContentLoaded', function() {
                        function setStatus(isOk) {
                const loader = document.getElementById('snmpLoader');
                if (loader) {
                    if (isOk) {
                        loader.classList.remove('error');
                    } else {
                        loader.classList.add('error');
                    }
                }
            }

            function fetchTraffic() {
                const INTERVAL = 15000;
                fetch('{{ route("snmp.live_traffic") }}')
                    .then(res => res.json())
                    .then(data => {
                        if (data.status !== 'error' && data.in_mbps !== undefined) {
                            latestDownMbps = data.in_mbps ?? 0;
                            latestUpMbps = data.out_mbps ?? 0;

                            updateGauge(latestDownMbps, 'down');
                            updateGauge(latestUpMbps, 'up');
                            setStatus(true);
                        } else {
                            updateGauge(0, 'down');
                            updateGauge(0, 'up');
                            setStatus(false);
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching live traffic:', err);
                        updateGauge(0, 'down');
                        updateGauge(0, 'up');
                        setStatus(false);
                    });
            }

            // Initial render
            updateGauge(latestDownMbps, 'down');
            updateGauge(latestUpMbps, 'up');
            
            fetchTraffic();
            setInterval(fetchTraffic, 15000);
        });
    </script>

    {{-- ============================================================== --}}
    {{-- KPI Live Polling — fetch() global_metrics cada 15s             --}}
    {{-- ============================================================== --}}
    <script>
        (function () {
            const KPI_ENDPOINT = '{{ route("kpi.live") }}';
            const KPI_INTERVAL = 15000; // ms

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

                    setVal('kpiTraffic', data.traffic_mbps);
                    setVal('kpiPing', data.latency_ms);
                    setVal('kpiLoss', data.packet_loss_pct);
                    setVal('kpiCpuValue', data.cpu_avg_pct + '%');
                    setVal('kpiMemValue', data.ram_avg_pct + '%');
                    setVal('kpiNodesValue', `${data.dispositivos.online} / ${data.dispositivos.total}`);

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
                                newBadge.className = 'absolute -top-1 -right-1 min-w-[19px] h-[19px] px-1 bg-[#f26419] text-white text-[10px] font-black rounded-full flex items-center justify-center ring-2 ring-white shadow';
                                newBadge.textContent = data.unread_notif_count;
                                bellBtn.appendChild(newBadge);
                            }
                            if (badgeText) {
                                badgeText.textContent = `${data.unread_notif_count} no leídas`;
                                badgeText.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-[#3b5998]/10 text-[#3b5998]';
                            }
                        } else {
                            if (countBadge) countBadge.remove();
                            if (badgeText) {
                                badgeText.textContent = '0 no leídas';
                                badgeText.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500';
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
  background: #3b5998; /* Azul Hacienda */
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
  background: #f26419; /* Naranja Institucional */
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
