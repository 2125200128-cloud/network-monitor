@extends('layouts.app')

@section('title', 'Topología y Conexiones de Red')

@section('styles')
    <style>
        .blueprint-canvas {
            background-color: #f8fafc;
            background-image: 
                linear-gradient(rgba(148, 163, 184, 0.14) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.14) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        .ease-custom {
            transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
        }

        .uiverse-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.45rem 1.4rem;
            border: 2px solid #0f172a;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
            background: #1e293b;
            color: #ffffff;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
            pointer-events: auto !important;
        }

        .uiverse-btn:hover {
            color: #0f172a !important;
            border-radius: 1.25rem;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.2);
            border-color: #0f172a;
        }

        .uiverse-btn.active {
            border-color: #3b82f6 !important;
            background: #0f172a !important;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.4) !important;
        }

        .dark .blueprint-canvas {
            background-color: #07090e;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        }

        .dark .uiverse-btn {
            background: #0f131c;
            border-color: #334155;
            color: #f1f5f9;
        }

        .dark .uiverse-btn:hover {
            color: #090d16 !important;
            border-color: #f8fafc;
        }

        .dark .uiverse-btn.active {
            border-color: #60a5fa !important;
            background: #1e2638 !important;
            box-shadow: 0 0 12px rgba(96, 165, 250, 0.4) !important;
        }

        /* Compact Uiverse Buttons for Filter & Action Bars */
        .uiverse-btn-sm {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.28rem 0.65rem;
            border: 1.5px solid #0f172a;
            border-radius: 0.65rem;
            font-size: 0.68rem;
            font-weight: 700;
            background: #1e293b;
            color: #ffffff;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.12);
            pointer-events: auto !important;
            white-space: nowrap;
        }

        .uiverse-btn-sm:hover,
        .uiverse-btn-sm.active:hover {
            color: #0f172a !important;
            border-radius: 0.95rem;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
            border-color: #0f172a;
        }

        .uiverse-btn-sm.active {
            border-color: #3b82f6 !important;
            background: #0f172a !important;
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.45) !important;
            color: #60a5fa !important;
        }

        .dark .uiverse-btn-sm {
            background: #0f131c;
            border-color: #334155;
            color: #f1f5f9;
        }

        .dark .uiverse-btn-sm:hover,
        .dark .uiverse-btn-sm.active:hover {
            color: #090d16 !important;
            border-color: #f8fafc;
        }

        .dark .uiverse-btn-sm.active {
            border-color: #60a5fa !important;
            background: #1e2638 !important;
            box-shadow: 0 0 10px rgba(96, 165, 250, 0.45) !important;
            color: #93c5fd !important;
        }

        .interactive-bar-island {
            pointer-events: auto !important;
        }

        .filter-pill-btn {
            pointer-events: auto !important;
            cursor: pointer;
        }

        /* Slide drawer */
        #inspectorDrawer {
            transform: translateX(calc(100% + 3rem));
            opacity: 0;
            pointer-events: none;
            visibility: hidden;
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease, visibility 0.28s;
        }
        #inspectorDrawer.drawer-open {
            transform: translateX(0) !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            visibility: visible !important;
        }
    </style>
@endsection

@section('content')
<div class="flex flex-col h-full space-y-4">
    
    {{-- ==================== 1. TOP HEADER & METRICS ==================== --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-[#12161f] rounded-[2rem] p-5 lg:p-6 shadow-sm border border-gray-100 dark:border-slate-800 flex-shrink-0">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#3b5998] to-[#5c8096] flex items-center justify-center text-white shadow-md shadow-blue-900/10 flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <circle cx="6" cy="6" r="2.5"></circle>
                    <circle cx="18" cy="6" r="2.5"></circle>
                    <circle cx="12" cy="18" r="2.5"></circle>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.2 7.2l2.6 8.6m2.4 0l2.6-8.6M8.5 6h7"></path>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl lg:text-2xl font-black text-gray-900 dark:text-white leading-tight">Topología y Conexiones de Red</h1>
                    <div id="liveStatusContainer" class="flex items-center justify-center transition-all duration-300 ml-1">
                        <dotlottie-player id="liveWifiAnim" src="https://lottie.host/8b5830cd-4218-4247-87fb-5bd887d203d8/PomLBEIdAK.lottie" background="transparent" speed="1" style="width: 85px; height: 85px; margin-top: -12px; margin-bottom: -12px;" loop autoplay></dotlottie-player>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 font-medium mt-0.5">Mapa cartográfico de interconexión espacial L2/L3 · CDP & LLDP Telemetry</p>
            </div>
        </div>

        {{-- Metrics Badges Only (Header limpio y sin duplicidad de botones) --}}
        <div class="flex flex-wrap items-center gap-2.5">
            <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-[#3b5998]"></span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Nodos:</span>
                <span id="badgeNodos" class="text-xs font-black text-slate-800 dark:text-slate-100 font-mono">{{ $totalNodos }}</span>
            </div>

            <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/60 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-[#5c8096]"></span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Enlaces:</span>
                <span id="badgeEnlaces" class="text-xs font-black text-slate-800 dark:text-slate-100 font-mono">{{ $totalEnlaces }}</span>
            </div>

            <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/40 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-xs text-emerald-700 dark:text-emerald-400 font-semibold">Activos (UP):</span>
                <span id="badgeEnlacesUp" class="text-xs font-black text-emerald-800 dark:text-emerald-300 font-mono">{{ $enlacesActivos }}</span>
            </div>

            <div id="badgeAlertaContainer" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200/80 dark:border-red-800/40 shadow-xs {{ $enlacesAlerta > 0 ? '' : 'hidden' }}">
                <div class="w-6 h-6 flex items-center justify-center shrink-0">
                    <dotlottie-player 
                        src="{{ asset('animations/alert-down.lottie') }}?v={{ filemtime(public_path('animations/alert-down.lottie')) }}" 
                        background="transparent" 
                        speed="1" 
                        style="width: 24px; height: 24px;" 
                        loop 
                        autoplay>
                    </dotlottie-player>
                </div>
                <span class="text-xs text-red-700 dark:text-red-400 font-bold tracking-tight">Alerta / Down:</span>
                <span id="badgeEnlacesDown" class="text-xs font-black text-red-800 dark:text-red-300 font-mono">{{ $enlacesAlerta }}</span>
            </div>

            <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200/80 dark:border-blue-800/40 shadow-xs">
                <span class="text-xs text-[#3b5998] dark:text-blue-400 font-semibold">Capacidad:</span>
                <span id="badgeCapacidad" class="text-xs font-black text-[#3b5998] dark:text-blue-300 font-mono">{{ $anchoBandaTotalGbps }} Gbps</span>
            </div>
        </div>
    </div>

    {{-- ==================== 2. CANVAS & INTERACTIVE WORKSPACE ==================== --}}
    <div class="relative flex-1 rounded-[2rem] overflow-hidden shadow-sm border border-slate-200 dark:border-slate-800 blueprint-canvas flex flex-col min-h-[600px]">
        
        {{-- Unified Floating Top Bar (Organizada en dos grupos lógicos con separadores) --}}
        <div class="absolute z-10 flex flex-wrap items-center justify-between gap-3 pointer-events-none" style="top: 1rem; left: 1rem; right: 1rem;">
            
            {{-- Left Bar: Toolbar Groups (Uiverse Buttons) --}}
            <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-white/95 dark:bg-[#0d1017]/95 backdrop-blur-md border border-slate-200/90 dark:border-slate-800/70 shadow-lg dark:shadow-none interactive-bar-island" style="pointer-events: auto;">
                
                {{-- Grupo 1: Vista / Disposición --}}
                <div class="flex items-center gap-1.5">
                    {{-- 1. Centrar Todo --}}
                    <button type="button" onclick="resetZoom()" class="uiverse-btn group" title="Centrar y encuadrar vista completa">
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-3 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom -translate-x-2 group-hover:translate-x-2 whitespace-nowrap">Ver Todo</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[220px] group-hover:h-[220px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom right-3 group-hover:-right-1/4 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                    </button>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- 2. Física Pausada / Activa --}}
                    <button type="button" id="btnTogglePhysics" onclick="togglePhysics()" class="uiverse-btn group" title="Alternar simulación de física y fuerzas gravitatorias">
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-amber-400 z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-3 group-hover:fill-amber-600" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" fill="currentColor"></path>
                        </svg>
                        <span id="physicsStatusLabel" class="relative z-[1] transition-all duration-500 ease-custom -translate-x-2 group-hover:translate-x-2 whitespace-nowrap">Física Pausada</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[220px] group-hover:h-[220px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-amber-400 z-[9] transition-all duration-500 ease-custom right-3 group-hover:-right-1/4 group-hover:fill-amber-600" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" fill="currentColor"></path>
                        </svg>
                    </button>

                    {{-- 7. Jerárquico --}}
                    <button type="button" id="btnLayoutTree" onclick="setLayout('tree')" class="uiverse-btn group" title="Organizar en niveles jerárquicos (Core ➔ Acceso)">
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-3 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom -translate-x-2 group-hover:translate-x-2 whitespace-nowrap">Jerárquico</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[220px] group-hover:h-[220px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom right-3 group-hover:-right-1/4 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                    </button>

                    {{-- 8. Libre --}}
                    <button type="button" id="btnLayoutFree" onclick="setLayout('free')" class="uiverse-btn group active" title="Disposición orgánica libre">
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-3 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom -translate-x-2 group-hover:translate-x-2 whitespace-nowrap">Libre</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[220px] group-hover:h-[220px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom right-3 group-hover:-right-1/4 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                    </button>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- 9. Haces de Tráfico Animados (MagicUI Animated Beams) --}}
                    <button type="button" id="btnToggleBeams" onclick="toggleTrafficBeams()" class="uiverse-btn group active" title="Alternar simulación de tráfico de red en vivo (MagicUI Animated Beams)">
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-cyan-400 z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-3 group-hover:fill-cyan-600" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z" fill="currentColor"></path>
                        </svg>
                        <span id="beamsStatusLabel" class="relative z-[1] transition-all duration-500 ease-custom -translate-x-2 group-hover:translate-x-2 whitespace-nowrap">Haces (Beams)</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[240px] group-hover:h-[240px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-cyan-400 z-[9] transition-all duration-500 ease-custom right-3 group-hover:-right-1/4 group-hover:fill-cyan-600" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z" fill="currentColor"></path>
                        </svg>
                    </button>
                </div>

                {{-- Separador principal entre grupos --}}
                <div class="h-5 w-px bg-slate-300 dark:bg-slate-700 mx-1 hidden sm:block"></div>

                {{-- Grupo 2: Acciones --}}
                <div class="flex items-center gap-1.5" x-data>
                    {{-- 5. Auto-Descubrimiento SNMP --}}
                    <button type="button" onclick="openDiscoveryModal()" class="uiverse-btn group" title="Auto-Descubrimiento SNMP (CDP/LLDP)">
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-cyan-400 z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-3 group-hover:fill-cyan-600" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom -translate-x-2 group-hover:translate-x-2 whitespace-nowrap">Auto-Descubrimiento SNMP</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[260px] group-hover:h-[260px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-cyan-400 z-[9] transition-all duration-500 ease-custom right-3 group-hover:-right-1/4 group-hover:fill-cyan-600" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                    </button>

                    {{-- 6. Exportar PNG --}}
                    <button type="button" onclick="exportTopologyImage()" class="uiverse-btn group" title="Exportar topología de red como imagen PNG en alta definición">
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-3 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom -translate-x-2 group-hover:translate-x-2 whitespace-nowrap">Exportar PNG</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[220px] group-hover:h-[220px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg viewBox="0 0 24 24" class="absolute w-4 h-4 fill-white z-[9] transition-all duration-500 ease-custom right-3 group-hover:-right-1/4 group-hover:fill-slate-900" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                        </svg>
                    </button>
                </div>

            </div>

            {{-- Right Island: Search & Category / Edge Filter Pills (Colapsable y estilo Uiverse Compacto) --}}
            <div id="rightFilterBarContainer" class="flex items-center transition-all duration-300 pointer-events-auto" style="margin-left: auto;">
                
                {{-- Collapsed State: Botón flotante compacto para expandir --}}
                <div id="rightFilterBarCollapsed" class="hidden">
                    <button type="button" onclick="toggleRightFilterBar()" class="uiverse-btn-sm group" title="Mostrar barra de búsqueda y filtros de red">
                        <svg class="w-3.5 h-3.5 fill-cyan-400 z-[9] transition-all duration-500 ease-custom -left-1/4 group-hover:left-2" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom -translate-x-1 group-hover:translate-x-1 flex items-center gap-1.5 font-bold">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            Filtros & Búsqueda
                        </span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[180px] group-hover:h-[180px] group-hover:opacity-100 pointer-events-none"></span>
                        <svg class="w-3.5 h-3.5 fill-cyan-400 z-[9] transition-all duration-500 ease-custom right-2 group-hover:-right-1/4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>

                {{-- Expanded State: Barra Completa con Botones Uiverse Compactos --}}
                <div id="rightFilterBarExpanded" class="flex flex-wrap items-center gap-1.5 bg-white/95 dark:bg-[#0d1017]/95 p-1.5 rounded-2xl border border-slate-200/90 dark:border-slate-800/70 backdrop-blur-md shadow-lg dark:shadow-none interactive-bar-island">
                    
                    {{-- Quick Device Search Input --}}
                    <div class="relative flex items-center">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" id="topoSearchInput" oninput="searchTopologyDevice(this.value)" placeholder="Buscar IP, router, switch..." class="w-32 sm:w-40 pl-8 pr-2 py-1 text-[11px] bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700/60 rounded-xl text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition font-medium">
                    </div>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- Device Category Filter Buttons (Estilo Uiverse Pequeño) --}}
                    <button type="button" onclick="filterDeviceCategory('all', this)" class="uiverse-btn-sm group device-filter-btn active" title="Mostrar todos los nodos">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Todos</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('router', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver y centrar en Routers WAN">
                        <img src="{{ asset('images/topology/router.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="Routers">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Routers</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('switch', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver y centrar en Switches">
                        <svg class="w-3.5 h-3.5 relative z-[1]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Switches</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('servidor', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver Servidores Enterprise">
                        <img src="{{ asset('images/topology/server.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="Servidores">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Servidores</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('access_point', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver y centrar en Access Points">
                        <img src="{{ asset('images/topology/access-point.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="APs">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">APs</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('phone', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver Teléfonos IP">
                        <img src="{{ asset('images/topology/ip-phone.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="VoIP">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">VoIP</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- Enlaces Filter Buttons (Estilo Uiverse Pequeño) --}}
                    <button type="button" onclick="filterEdges('all', this)" class="uiverse-btn-sm group filter-pill-btn active" title="Mostrar todos los enlaces">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Enlaces ({{ $totalEnlaces }})</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[160px] group-hover:h-[160px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterEdges('up', this)" class="uiverse-btn-sm group filter-pill-btn" title="Filtrar enlaces UP">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 relative z-[1]"></span>
                        <span class="relative z-[1] transition-all duration-500 ease-custom">UP</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[120px] group-hover:h-[120px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterEdges('down', this)" class="uiverse-btn-sm group filter-pill-btn" title="Filtrar enlaces en Alerta">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 relative z-[1]"></span>
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Alertas</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[120px] group-hover:h-[120px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- Collapse Toggle Button (Ocultar Barra) --}}
                    <button type="button" onclick="toggleRightFilterBar()" class="uiverse-btn-sm group px-2 text-slate-400 hover:text-slate-900 dark:hover:text-slate-100" title="Ocultar barra de filtros">
                        <svg class="w-3.5 h-3.5 relative z-[1] transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[100px] group-hover:h-[100px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>
                </div>

            </div>
        </div>

        {{-- Main Vis.js Graph Canvas --}}
        <div id="networkTopologyCanvas" class="w-full h-full flex-1 cursor-grab active:cursor-grabbing"></div>

        {{-- ==================== 3. INSPECTOR DRAWER (SIDE PANEL) ==================== --}}
        <div id="inspectorDrawer" class="absolute w-96 max-w-[calc(100%-2rem)] bg-white/95 dark:bg-[#12161f]/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-800 p-5 z-20 pointer-events-none" style="top: 4.5rem; right: 1rem; max-height: calc(100% - 5.5rem); overflow-y: auto;">
            
            {{-- Drawer Header with Close Button --}}
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-slate-800 mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#3b5998]"></span>
                    <span class="text-[10px] font-black uppercase text-gray-400 dark:text-slate-400 tracking-wider">Telemetría de Red</span>
                </div>
                <button type="button" onclick="closeDrawer()" class="w-7 h-7 rounded-full bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white flex items-center justify-center transition" title="Cerrar inspector">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            {{-- Device Inspector Container --}}
            <div id="drawerDeviceContent" class="space-y-4 hidden">
                <div class="flex items-start gap-3 pr-6">
                    <div id="drawerDeviceIcon" class="w-11 h-11 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-md flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span id="drawerDeviceRol" class="px-2 py-0.5 rounded-full text-[9px] font-black tracking-wider bg-blue-100 text-[#3b5998]">CORE</span>
                            <span id="drawerDeviceStatusBadge" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700">ONLINE</span>
                        </div>
                        <h3 id="drawerDeviceNombre" class="text-sm font-extrabold text-gray-900 dark:text-white mt-1 leading-tight truncate">Switch Core Catalyst 9300</h3>
                        <p id="drawerDeviceIp" class="text-xs text-gray-500 dark:text-slate-400 font-mono mt-0.5">192.168.1.1</p>
                    </div>
                </div>

                {{-- Ficha Técnica Preview del Hardware --}}
                <div class="rounded-xl bg-gradient-to-br from-slate-900 via-[#0d1017] to-slate-950 p-2.5 border border-slate-800 shadow-inner flex flex-col items-center justify-center relative overflow-hidden group">
                    <div class="w-full flex items-center justify-between mb-1 px-1">
                        <span class="text-[9px] font-mono font-bold text-slate-400 uppercase tracking-wider">CHASIS FÍSICO</span>
                        <span id="drawerDeviceTipoEquipo" class="text-[9px] font-mono font-bold text-blue-400">Switch Gestionado</span>
                    </div>
                    <div class="w-full h-24 flex items-center justify-center p-2 bg-black/40 rounded-lg border border-slate-800/60">
                        <img id="drawerDeviceChassisImage" src="{{ asset('images/topology/switch-core.svg') }}" alt="Chasis" class="max-h-20 max-w-full object-contain drop-shadow-md transition-transform duration-300 group-hover:scale-105">
                    </div>
                    <div class="w-full flex items-center justify-between mt-1.5 px-1 text-[10px] text-slate-400 font-mono">
                        <span id="drawerDeviceFactorForma">1U Rackmount</span>
                        <span id="drawerDeviceVendor" class="text-slate-300 font-bold">Cisco Systems</span>
                    </div>
                </div>

                {{-- Gauges --}}
                <div class="grid grid-cols-3 gap-2 bg-gray-50 dark:bg-slate-900/80 p-3 rounded-xl border border-gray-100 dark:border-slate-800 text-center">
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">CPU</p>
                        <p id="drawerDeviceCpu" class="text-sm font-black text-gray-800 dark:text-slate-100 font-mono">15%</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Memoria</p>
                        <p id="drawerDeviceMem" class="text-sm font-black text-gray-800 dark:text-slate-100 font-mono">32%</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Ping</p>
                        <p id="drawerDevicePing" class="text-sm font-black text-emerald-600 dark:text-emerald-400 font-mono">1.2 ms</p>
                    </div>
                </div>

                {{-- Hardware Metadata --}}
                <div class="space-y-2 text-xs border-t border-gray-100 dark:border-slate-800 pt-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Modelo:</span>
                        <span id="drawerDeviceModelo" class="font-semibold text-gray-700 dark:text-slate-300">Catalyst 9300 24T</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Número Serial:</span>
                        <span id="drawerDeviceSerial" class="font-mono text-gray-700 dark:text-slate-300 font-bold">FOC2438L8PQ</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Uptime:</span>
                        <span id="drawerDeviceUptime" class="font-semibold text-gray-700 dark:text-slate-300">14d 8h 22m</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Ubicación:</span>
                        <span id="drawerDeviceUbicacion" class="font-semibold text-gray-700 dark:text-slate-300">Site Principal - Rack 1</span>
                    </div>
                </div>

                {{-- Conexiones Activas de Cableado / Puertos --}}
                <div class="border-t border-gray-100 dark:border-slate-800 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Cables y Puertos Conectados</p>
                        <span id="drawerDeviceConnCount" class="text-[10px] font-mono font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded border border-transparent dark:border-slate-700">0 cables</span>
                    </div>
                    <div id="drawerDeviceConnectionsList" class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>

                {{-- Sección Especial: Dispositivo Conectado al Puerto PC (Pass-Through) para Teléfonos IP --}}
                <div id="drawerPhonePcSection" class="hidden border-t border-gray-100 dark:border-slate-800 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                            <p class="text-[10px] font-black text-indigo-900 dark:text-indigo-400 uppercase tracking-wider">Puerto PC (Pass-Through)</p>
                        </div>
                        <span id="drawerPhonePcStatusBadge" class="text-[9px] font-black px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">CONECTADA</span>
                    </div>

                    <div class="bg-gradient-to-br from-indigo-50/70 to-slate-50 dark:from-slate-900/90 dark:to-indigo-950/30 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/40 space-y-2 text-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="truncate">
                                <h5 id="drawerPhonePcHostname" class="font-extrabold text-slate-800 dark:text-slate-100 leading-tight truncate">PC-DELL-OPTIPLEX</h5>
                                <p id="drawerPhonePcVendor" class="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold truncate">Dell Inc. / Workstation</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1 border-t border-indigo-100/60 dark:border-slate-800 font-mono text-[11px]">
                            <div>
                                <span class="text-[10px] text-slate-400 block">IP de Datos:</span>
                                <span id="drawerPhonePcIp" class="font-bold text-blue-600 dark:text-blue-400">10.204.1.159</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 block">MAC (Switch CAM):</span>
                                <span id="drawerPhonePcMac" class="font-bold text-slate-800 dark:text-slate-200">24:7E:12:5A:21:18</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 block">Velocidad Enlace:</span>
                                <span id="drawerPhonePcSpeed" class="font-bold text-emerald-600 dark:text-emerald-400">1.0 Gbps (Full)</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 block">VLAN Tráfico:</span>
                                <span id="drawerPhonePcVlan" class="font-bold text-slate-700 dark:text-slate-300">VLAN Datos (1)</span>
                            </div>
                        </div>

                        <div class="pt-1 text-[9px] text-slate-400 font-mono flex items-center justify-between border-t border-indigo-100/40 dark:border-slate-800">
                            <span>Switch / Puerto:</span>
                            <span id="drawerPhonePcSwitchPort" class="text-slate-600 dark:text-slate-400 font-bold truncate max-w-[160px]">HMAG_IDF2A_B (Gi1/0/23)</span>
                        </div>
                    </div>
                </div>

                {{-- Action Links --}}
                <div class="pt-2 flex flex-col gap-2">
                    <a id="drawerDeviceLink" href="#" class="w-full py-2.5 px-3 rounded-xl bg-[#3b5998] hover:bg-[#2d4677] text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm">
                        <span>Ver Ficha Técnica y Puertos</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                    <a href="{{ route('consola.index') }}" class="w-full py-2 px-3 rounded-xl bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-bold transition flex items-center justify-center gap-1.5 border border-transparent dark:border-slate-700">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Abrir Consola CLI</span>
                    </a>
                </div>
            </div>

            {{-- Link / Cable Inspector Container --}}
            <div id="drawerLinkContent" class="space-y-4 hidden">
                <div class="flex items-start gap-3 pr-6">
                    <div class="w-11 h-11 rounded-xl bg-[#3b5998] text-white flex items-center justify-center shadow-md flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span id="drawerLinkTipo" class="px-2 py-0.5 rounded-full text-[9px] font-black tracking-wider bg-blue-100 text-[#3b5998]">FIBRA 10G SFP+</span>
                            <span id="drawerLinkStatusBadge" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700">UP</span>
                        </div>
                        <h3 class="text-sm font-extrabold text-gray-900 mt-1 leading-tight">Enlace de Interconexión</h3>
                        <p id="drawerLinkVelocidad" class="text-xs text-gray-500 font-mono mt-0.5">10000 Mbps Negociados</p>
                    </div>
                </div>

                {{-- Endpoints Visual Box --}}
                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100 space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span class="font-bold text-gray-700 truncate max-w-[140px]" id="drawerLinkOrigenDisp">Switch Core</span>
                        </div>
                        <span class="font-mono text-[11px] font-bold bg-white px-2 py-0.5 rounded border border-gray-200 text-gray-800" id="drawerLinkOrigenPuerto">Te1/0/24</span>
                    </div>

                    <div class="flex items-center justify-center text-gray-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            <span class="font-bold text-gray-700 truncate max-w-[140px]" id="drawerLinkDestinoDisp">Router Edge</span>
                        </div>
                        <span class="font-mono text-[11px] font-bold bg-white px-2 py-0.5 rounded border border-gray-200 text-gray-800" id="drawerLinkDestinoPuerto">Gi0/0/0</span>
                    </div>
                </div>

                {{-- Traffic Telemetry Meters --}}
                <div class="space-y-2 border-t border-gray-100 pt-3 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Tráfico Entrada (In):</span>
                        <span id="drawerLinkTraficoIn" class="font-mono font-bold text-emerald-600">420.5 Mbps</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Tráfico Salida (Out):</span>
                        <span id="drawerLinkTraficoOut" class="font-mono font-bold text-blue-600">185.2 Mbps</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Saturación de Enlace:</span>
                        <span id="drawerLinkSaturacion" class="font-mono font-black text-gray-800">4.2%</span>
                    </div>
                </div>

                {{-- Allowed VLANs --}}
                <div class="border-t border-gray-100 pt-3">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">VLANs Transportadas (Trunk)</p>
                    <div id="drawerLinkVlans" class="flex flex-wrap gap-1">
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-[10px] font-bold rounded">VLAN 1</span>
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-[10px] font-bold rounded">VLAN 10</span>
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-[10px] font-bold rounded">VLAN 20</span>
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-[10px] font-bold rounded">VLAN 99</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- ==================== OLED TOOLTIP ==================== --}}
    <div id="oledTooltip" class="absolute z-50 pointer-events-none opacity-0 transition-opacity duration-200 bg-[#0a0c10] border border-cyan-900/50 rounded-xl p-3 shadow-2xl shadow-cyan-900/20 w-64" style="left: -9999px; top: -9999px;">
        <div class="flex items-center justify-between mb-1">
            <span id="ttNombre" class="text-xs font-black text-white truncate w-3/4">Nombre</span>
            <span id="ttEstadoBadge" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        </div>
        <p id="ttIp" class="text-[10px] text-cyan-400 font-mono mb-2">192.168.x.x</p>
        <div class="space-y-1 text-[10px]">
            <div class="flex justify-between">
                <span class="text-slate-500">Modelo:</span>
                <span id="ttModelo" class="text-slate-300 font-semibold truncate max-w-[100px]">...</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Ubicación:</span>
                <span id="ttUbicacion" class="text-slate-300 font-semibold truncate max-w-[100px]">...</span>
            </div>
        </div>
    </div>

    {{-- ==================== MODAL: RASTREO PROFUNDO ==================== --}}
    <div x-data="{ openScanner: false, isScanning: false }" 
         x-show="openScanner" 
         @open-scanner.window="openScanner = true"
         style="display: none;"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm transition-opacity">
        
        <div class="bg-[#12161f] border border-[#1e293b] rounded-2xl w-full max-w-md shadow-2xl shadow-cyan-900/20 overflow-hidden"
             @click.away="if(!isScanning) openScanner = false">
            
            <div class="p-5 border-b border-[#1e293b] flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-cyan-900/30 border border-cyan-800/50 flex items-center justify-center text-cyan-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-white">Rastreo Profundo L2/L3</h3>
                        <p class="text-[10px] text-slate-400">Motor Crawler Python Asíncrono</p>
                    </div>
                </div>
                <button type="button" @click="if(!isScanning) openScanner = false" class="text-slate-500 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-5" x-show="!isScanning">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Subredes (CIDR)</label>
                    <input type="text" value="10.4.254.0/24" class="w-full bg-[#0a0c10] border border-[#1e293b] rounded-xl px-3 py-2 text-xs font-mono text-cyan-400 focus:outline-none focus:border-cyan-700" placeholder="Ej: 192.168.1.0/24">
                </div>
                <button type="button" @click="isScanning = true; setTimeout(() => { isScanning = false; openScanner = false; location.reload(); }, 3000)" class="w-full py-2.5 rounded-xl text-xs font-bold text-white bg-cyan-700 hover:bg-cyan-600 transition shadow-lg shadow-cyan-900/20">
                    Iniciar Escaneo
                </button>
            </div>

            <div class="p-8 text-center" x-show="isScanning">
                <div class="relative w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full border-2 border-t-cyan-400 border-cyan-900/30 animate-spin"></div>
                    <svg class="w-6 h-6 text-cyan-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h4 class="text-sm font-bold text-white">Escaneando red...</h4>
                <p class="text-xs text-slate-400 mt-1">Ejecutando worker Python en background</p>
            </div>
        </div>
    </div>

    {{-- ==================== 3. MODAL: AUTO-DESCUBRIMIENTO DE RED ==================== --}}
    <div id="discoveryModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-all duration-300 opacity-0 pointer-events-none">
        <div id="discoveryModalCard" class="bg-white rounded-[2rem] max-w-xl w-full p-6 sm:p-7 shadow-2xl border border-slate-100 transition-all duration-300 transform scale-95 flex flex-col space-y-5 max-h-[90vh] overflow-y-auto custom-scrollbar">
            
            {{-- Modal Header --}}
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 leading-tight">Auto-Descubrimiento de Red</h3>
                        <p class="text-xs text-slate-500 font-medium">Crawler SNMPv2c · Mapeo autónomo CDP / LLDP y Barrido CIDR</p>
                    </div>
                </div>
                <button type="button" onclick="closeDiscoveryModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            {{-- FORM STATE --}}
            <div id="discoveryFormState" class="space-y-4">
                {{-- Method selector tabs --}}
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Método de Descubrimiento</label>
                    <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1 rounded-2xl border border-slate-200">
                        <button type="button" id="btnMethodCdp" onclick="setDiscoveryMethod('cdp_lldp')" class="py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-white text-indigo-900 shadow-sm border border-slate-200">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"></path></svg>
                            <span>Vecinos CDP / LLDP</span>
                        </button>
                        <button type="button" id="btnMethodCidr" onclick="setDiscoveryMethod('cidr_sweep')" class="py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Barrido Subred CIDR</span>
                        </button>
                    </div>
                </div>

                {{-- CDP/LLDP Seed IP Input --}}
                <div id="fieldSeedIpGroup">
                    <label for="discSeedIp" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">IP Semilla / Equipo Raíz (Seed IP)</label>
                    <div class="relative">
                        <input type="text" id="discSeedIp" value="{{ $config->ip_switch_core ?? '192.168.1.254' }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none" placeholder="10.4.254.3">
                        <span class="absolute right-3 top-2.5 text-[10px] font-bold text-slate-400 uppercase">Core Switch</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">El crawler conectará a este switch y consultará recursivamente sus tablas de vecinos.</p>
                </div>

                {{-- CIDR Input (Hidden by default) --}}
                <div id="fieldCidrGroup" class="hidden">
                    <label for="discCidr" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Segmento de Red (CIDR)</label>
                    <input type="text" id="discCidr" value="10.4.254.0/24" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none" placeholder="10.4.254.0/24">
                    <p class="text-[11px] text-slate-500 mt-1">Se realizará un ping sweep paralelo en todo el segmento buscando switches gestionables.</p>
                </div>

                {{-- Common Parameters: Community & Max Depth --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="discCommunity" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Comunidad SNMP</label>
                        <input type="text" id="discCommunity" value="{{ $config->comunidad_snmp_default ?? 'public' }}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none" placeholder="public">
                    </div>
                    <div>
                        <label for="discMaxDepth" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Profundidad Máxima (Saltos)</label>
                        <select id="discMaxDepth" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition outline-none">
                            <option value="1">1 salto (Solo vecinos directos)</option>
                            <option value="2">2 saltos (Distribución)</option>
                            <option value="3" selected>3 saltos (Recomendado)</option>
                            <option value="4">4 saltos (Toda la infraestructura)</option>
                            <option value="5">5 saltos (Campus / WAN)</option>
                        </select>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closeDiscoveryModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-800 hover:bg-slate-100 transition">
                        Cancelar
                    </button>
                    <button type="button" id="btnSubmitDiscovery" onclick="startAutoDiscovery()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-md shadow-indigo-500/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Iniciar Auto-Descubrimiento</span>
                    </button>
                </div>
            </div>

            {{-- SCANNING / IN-FLIGHT STATE --}}
            <div id="discoveryScanningState" class="hidden space-y-4 py-3">
                <div class="flex flex-col items-center justify-center text-center space-y-3">
                    <div class="relative w-40 h-40 flex items-center justify-center -my-4">
                        <dotlottie-player 
                            src="https://lottie.host/a753aa47-08da-4e30-844d-0149a474c3a2/lGtqv6zxYe.lottie" 
                            background="transparent" 
                            speed="1" 
                            style="width: 100%; height: 100%;" 
                            loop 
                            autoplay>
                        </dotlottie-player>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-800">Rastreando Topología en Tiempo Real...</h4>
                        <p id="discoveryStatusText" class="text-xs text-slate-500 font-medium mt-0.5">Consultando tablas de adyacencia CDP y LLDP...</p>
                    </div>
                    <div class="px-3 py-1 rounded-full bg-slate-100 text-[11px] font-mono font-bold text-slate-600" id="discoveryTimer">
                        Tiempo: 00:00s
                    </div>
                </div>

                {{-- Live Log Output Terminal --}}
                <div class="bg-slate-950 rounded-2xl p-3.5 font-mono text-[11px] text-slate-300 max-h-48 overflow-y-auto custom-scrollbar border border-slate-800 space-y-1" id="discoveryLogBox">
                    <p class="text-slate-500">// Consola de rastreo activa...</p>
                </div>
            </div>

            {{-- SUCCESS / RESULT STATE --}}
            <div id="discoveryResultState" class="hidden space-y-4 py-2">
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-emerald-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-emerald-900" id="discoveryResultTitle">¡Mapeo de Topología Finalizado!</h4>
                        <p class="text-xs text-emerald-700 font-medium" id="discoveryResultMessage">Se ha sincronizado la red con éxito.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dispositivos Detectados</span>
                        <span class="text-xl font-black text-slate-800 font-mono" id="resTotalDevices">0</span>
                        <span class="text-[10px] font-semibold text-indigo-600 block mt-0.5" id="resNewDevices">(0 nuevos)</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Enlaces Físicos</span>
                        <span class="text-xl font-black text-slate-800 font-mono" id="resTotalLinks">0</span>
                        <span class="text-[10px] font-semibold text-emerald-600 block mt-0.5" id="resNewLinks">(0 nuevos)</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeDiscoveryModal()" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 transition shadow-md">
                        Cerrar y Ver Lienzo
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ==================== 5. MODAL: ESCANEO POR CABLE RJ-45 ==================== --}}
<div id="localRj45Modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-lg w-full overflow-hidden transform transition-all scale-100">
        {{-- Modal Header --}}
        <div class="p-6 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 text-white relative">
            <button type="button" onclick="closeLocalRj45Modal()" class="absolute top-5 right-5 text-slate-400 hover:text-white transition p-1.5 rounded-full hover:bg-white/10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400 shadow-inner">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-black tracking-tight">Rastreo de Red Local por Cable (RJ-45)</h3>
                    <p class="text-xs text-slate-300 font-medium">Detección de NIC física, barrido /24 y sondeo CDP/LLDP</p>
                </div>
            </div>
        </div>

        {{-- Modal Body --}}
        <div class="p-6 space-y-5">
            
            {{-- CONFIGURATION / FORM STATE --}}
            <div id="rj45FormState" class="space-y-4 py-1">
                <div>
                    <label for="rj45SubnetInput" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Subred o IP a Escanear
                    </label>
                    <div class="relative">
                        <input type="text" id="rj45SubnetInput" value="10.4.155.0/24" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition outline-none" placeholder="ej. 10.4.155.0/24, 192.168.1.0/24, 10.4.155.2 o cualquier IP">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Escribe <strong>cualquier IP o segmento /24</strong> de tu red.</p>
                </div>

                {{-- Fast selector pills --}}
                <div class="space-y-1.5">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Accesos Rápidos a Redes Locales:</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="setRj45Target('10.4.155.0/24')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 transition">
                            📶 Red Wi-Fi (10.4.155.0/24)
                        </button>
                        <button type="button" onclick="setRj45Target('192.168.1.0/24')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition">
                            🔌 Cable RJ-45 (192.168.1.0/24)
                        </button>
                        <button type="button" onclick="setRj45Target('')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition">
                            ⚡ Autodetectar Interfaz
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label for="rj45CommunityInput" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Comunidad SNMP</label>
                        <input type="text" id="rj45CommunityInput" value="public" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition outline-none" placeholder="public">
                    </div>
                    <div>
                        <label for="rj45ThreadsInput" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Hilos Paralelos</label>
                        <select id="rj45ThreadsInput" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-800 focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition outline-none">
                            <option value="50" selected>50 hilos (Ultra Rápido)</option>
                            <option value="25">25 hilos (Moderado)</option>
                            <option value="10">10 hilos (Bajo tráfico)</option>
                        </select>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeLocalRj45Modal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-800 hover:bg-slate-100 transition">
                        Cancelar
                    </button>
                    <button type="button" onclick="executeLocalScan()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-md shadow-emerald-500/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>Iniciar Rastreo</span>
                    </button>
                </div>
            </div>

            {{-- SCANNING / PROGRESS STATE --}}
            <div id="rj45ScanningState" class="hidden space-y-4 py-2">
                <div class="flex flex-col items-center justify-center text-center space-y-3">
                    <div class="relative w-16 h-16 flex items-center justify-center">
                        <div class="absolute inset-0 rounded-full bg-emerald-500/20 animate-ping"></div>
                        <div class="w-12 h-12 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-600/30">
                            <svg class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-800" id="rj45StatusTitle">Identificando Tarjeta de Red Física...</h4>
                        <p id="rj45StatusSubtitle" class="text-xs text-slate-500 font-medium mt-0.5">Analizando adaptadores de red y Gateway predeterminado...</p>
                    </div>
                    <div class="px-3 py-1 rounded-full bg-slate-100 text-[11px] font-mono font-bold text-slate-600" id="rj45Timer">
                        Tiempo transcurrido: 00:00s
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden border border-slate-200">
                    <div id="rj45ProgressBar" class="bg-gradient-to-r from-emerald-500 via-teal-500 to-indigo-600 h-2.5 rounded-full transition-all duration-300" style="width: 15%;"></div>
                </div>

                {{-- Telemetry details box --}}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3.5 text-xs text-slate-600 space-y-1.5 font-mono">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">NIC Detectada:</span>
                        <span id="rj45NicName" class="font-bold text-slate-800">Detectando...</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">IP / Subred:</span>
                        <span id="rj45NicIp" class="font-bold text-slate-800">Calculando...</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Gateway:</span>
                        <span id="rj45NicGw" class="font-bold text-slate-800">Determinando...</span>
                    </div>
                </div>

                {{-- Live Log Box --}}
                <div class="bg-slate-950 rounded-2xl p-3 font-mono text-[11px] text-slate-300 max-h-36 overflow-y-auto custom-scrollbar border border-slate-800 space-y-1" id="rj45LogBox">
                    <p class="text-slate-500">// Iniciando worker/local_interface_scanner.py...</p>
                </div>
            </div>

            {{-- SUCCESS / RESULT STATE --}}
            <div id="rj45ResultState" class="hidden space-y-4 py-2">
                <div id="rj45BannerBox" class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 transition-colors duration-200">
                    <div id="rj45BannerIcon" class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-emerald-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-emerald-900" id="rj45ResultTitle">¡Subred RJ-45 Mapeada con Éxito!</h4>
                        <p class="text-xs text-emerald-700 font-medium" id="rj45ResultMessage">La topología física se ha sincronizado.</p>
                    </div>
                </div>

                {{-- Aviso de diagnóstico si no se detectaron equipos --}}
                <div id="rj45ZeroDevicesNotice" class="hidden bg-amber-50/80 border border-amber-200/80 rounded-2xl p-3.5 text-xs text-amber-800 space-y-1.5">
                    <div class="flex items-center gap-1.5 font-bold text-amber-900">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Diagnóstico del enlace físico:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-[11px] text-amber-700 pl-1">
                        <li>El barrido ICMP/SNMP en los 254 hosts concluyó sin respuestas de red.</li>
                        <li>Verifica que el switch esté encendido y que el puerto RJ-45 muestre el LED en color verde.</li>
                        <li>Si el switch recién encendió, el protocolo Spanning Tree (STP) tarda entre 30 y 50 segundos en negociar el enlace.</li>
                        <li>Verifica si el switch tiene una IP configurada en el rango 192.168.1.x o en otra VLAN.</li>
                    </ul>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hosts con Respuesta</span>
                        <span class="text-xl font-black text-slate-800 font-mono" id="rj45ResHosts">0</span>
                        <span class="text-[10px] font-semibold text-teal-600 block mt-0.5">ICMP Ping /24</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Equipos SNMP</span>
                        <span class="text-xl font-black text-slate-800 font-mono" id="rj45ResDevices">0</span>
                        <span class="text-[10px] font-semibold text-indigo-600 block mt-0.5" id="rj45ResNewDevices">(0 nuevos)</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 col-span-2">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Enlaces Físicos CDP / LLDP</span>
                        <span class="text-xl font-black text-emerald-700 font-mono" id="rj45ResLinks">0</span>
                        <span class="text-[10px] font-semibold text-emerald-600 block mt-0.5">Conexiones puerto a puerto registradas</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeLocalRj45Modal()" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 transition shadow-md hover:scale-[1.02] active:scale-[0.98]">
                        Ver Lienzo de Topología
                    </button>
                </div>
            </div>

            {{-- ERROR STATE --}}
            <div id="rj45ErrorState" class="hidden space-y-4 py-2">
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-rose-50 border border-rose-200">
                    <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-rose-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-rose-900">Error durante el Escaneo</h4>
                        <p class="text-xs text-rose-700 font-medium" id="rj45ErrorMessage">Ocurrió un error inesperado al rastrear la subred.</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeLocalRj45Modal()" class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                        Cerrar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
    {{-- Vis-Network Library --}}
    <script src="{{ asset('vendor/vis-network/vis-network.min.js') }}"></script>
    <script>
        let initialGrafoData = @json($grafoData);

        let network = null;
        let nodesDataSet = null;
        let edgesDataSet = null;
        let physicsEnabled = true;
        let currentFilter = 'all';

        // MagicUI Animated Beams & Live Simulation State
        let trafficSimulationEnabled = true;
        let trafficAnimTime = 0;
        let trafficSpeedMultiplier = 1.0;
        let animLoopRunning = false;

        document.addEventListener('DOMContentLoaded', function() {
            if (initialGrafoData && initialGrafoData.nodes && initialGrafoData.nodes.length > 0) {
                initNetworkTopology();
            } else {
                fetchTopologyData();
            }
            if (localStorage.getItem('topo_right_bar_collapsed') === '1') {
                const exp = document.getElementById('rightFilterBarExpanded');
                const col = document.getElementById('rightFilterBarCollapsed');
                if (exp && col) {
                    exp.classList.add('hidden');
                    col.classList.remove('hidden');
                }
            }
        });

        function saveNodePositionsToDatabase(positions = null) {
            if (!network) return;
            const currentPositions = positions || network.getPositions();
            if (!currentPositions || Object.keys(currentPositions).length === 0) return;

            localStorage.setItem('vis_positions_user', JSON.stringify(currentPositions));

            fetch("{{ route('topologia.guardar_posiciones') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ posiciones: currentPositions })
            }).then(res => res.json())
              .then(data => {
                  console.log('Posiciones de topología guardadas en cuenta:', data);
              }).catch(err => {
                  console.error('Error al guardar posiciones:', err);
              });
        }

        async function fetchTopologyData() {
            try {
                const response = await fetch("{{ route('topologia.datos') }}");
                if (!response.ok) throw new Error('Error de red al cargar la topología');
                const data = await response.json();
                
                initialGrafoData = data;
                if (!network) {
                    initNetworkTopology();
                } else if (nodesDataSet && edgesDataSet) {
                    // Actualización en caliente - NO sobrescribir x e y para no desacomodar el lienzo
                    const mappedNodes = (data.nodes || []).map(node => {
                        const cData = node.customData || {};
                        const nombre = cData.nombre || node.label || '';
                        const imgInfo = getDeviceImageAndRole(cData.modelo, nombre, cData.sysDescr);
                        const imagenRuta = cData.image || imgInfo.image || null;
                        const esInfra = imagenRuta ? true : false;
                        
                        // Extraer x e y para omitirlos en la actualización en caliente y preservar el lienzo
                        const { x, y, ...nodeWithoutPos } = node;

                        return {
                            ...nodeWithoutPos,
                            id: node.id,
                            label: node.label || nombre || 'Desconocido',
                            shape: esInfra ? 'image' : 'dot',
                            image: esInfra ? (imagenRuta || '/img/renders/switch_default.png') : undefined,
                            size: node.size || imgInfo.size || (esInfra ? 145 : 120),
                            customData: {
                                ...cData,
                                rol: esInfra ? (cData.rol || imgInfo.rol) : 'ENDPOINT',
                                tipo_equipo: esInfra ? (cData.tipo_equipo || imgInfo.tipo_equipo) : 'Dispositivo Final'
                            }
                        };
                    });
                    nodesDataSet.update(mappedNodes);
                }
            } catch (error) {
                console.error("Error haciendo fetch de la topología:", error);
            }
        }

        /**
         * Mapeo dinámico de hardware fotorrealista según el modelo exacto del equipo.
         */
        function getDeviceImageAndRole(modelo, nombre, sysDescr) {
            const haystack = ((nombre || '') + ' ' + (modelo || '') + ' ' + (sysDescr || '')).toLowerCase();
            const nomLower = (nombre || '').toLowerCase();

            // 1. Teléfonos IP / VoIP
            if (nomLower.startsWith('sep') || haystack.includes('phone') || haystack.includes('telefono') || haystack.includes('teléfono') || haystack.includes('cp-') || haystack.includes('sip') || haystack.includes('voip') || haystack.includes('7841') || haystack.includes('8841') || haystack.includes('7960')) {
                return {
                    image: '{{ asset("images/topology/ip-phone.svg") }}',
                    rol: 'VOIP / TELEFONIA',
                    tipo_equipo: 'Teléfono IP / VoIP Endpoint',
                    factor_forma: 'Desktop VoIP Appliance',
                    size: 120
                };
            }

            // 2. Puntos de Acceso Inalámbrico (Access Points / WLC / Wi-Fi)
            if (
                haystack.includes('wlc') || haystack.includes('c9800') || haystack.includes('air-ct') ||
                haystack.includes('-ap0') || haystack.includes('-ap1') || haystack.includes('-ap2') || haystack.includes('-ap3') || haystack.includes('-ap4') ||
                haystack.includes('-ap') || nomLower.startsWith('ap-') || nomLower.startsWith('wap-') ||
                (
                    (haystack.includes('c9115') || haystack.includes('air-ap') || haystack.includes('air-cap') || haystack.includes('ap software')) &&
                    !haystack.includes('switch') && !nomLower.startsWith('sw')
                )
            ) {
                return {
                    image: '{{ asset("images/topology/access-point.svg") }}',
                    rol: 'WIFI / ACCESS POINT',
                    tipo_equipo: 'Punto de Acceso Wi-Fi Enterprise / WLC',
                    factor_forma: 'Ceiling / Wall Mount',
                    size: 125
                };
            }

            // 3. Routers de borde WAN (ISR / Router / 1841 / 4400 / 4451 / 4331 / CUBE / GW / SAT)
            if (
                (
                    haystack.includes('isr') || haystack.includes('router') || haystack.includes('1841') || haystack.includes('4400') || haystack.includes('4451') || haystack.includes('4331') || haystack.includes('cube') || haystack.includes('gw-') || haystack.includes('-gw') || haystack.includes('gw_') || haystack.includes('rtr') || haystack.includes('edge') || haystack.includes('sat.') || haystack.includes('-sat')
                ) &&
                !haystack.includes('catalyst') && !haystack.includes('cat9k') && !haystack.includes('ws-c') && !nomLower.startsWith('sw') && !nomLower.includes('swc') && !nomLower.includes('swe') && !haystack.includes('switch') && !nomLower.startsWith('nx') && !haystack.includes('nexus')
            ) {
                return {
                    image: '{{ asset("images/topology/router.svg") }}',
                    rol: 'ROUTER / EDGE',
                    tipo_equipo: 'Router de Borde WAN / Voice Gateway',
                    factor_forma: 'Router Cisco ISR',
                    size: 140
                };
            }

            // 4. Chasis modular Nexus / Datacenter / ACI / 7000 / 9000 / 3000
            if (
                haystack.includes('nexus') || haystack.includes('nx-os') || haystack.includes('nxos') ||
                haystack.includes('7000') || haystack.includes('n7000') || haystack.includes('n7k') || haystack.includes('nx7k') ||
                haystack.includes('n9k') || haystack.includes('nx9k') || haystack.includes('n3k') || haystack.includes('nx3k') ||
                haystack.includes('leaf') || haystack.includes('spine') || haystack.includes('aci-') ||
                haystack.includes('93180') || haystack.includes('93240') || haystack.includes('9372')
            ) {
                return {
                    image: '{{ asset("images/topology/switch-nexus.svg") }}',
                    rol: 'NEXUS / DATACENTER',
                    tipo_equipo: 'Switch Cisco Nexus Datacenter / ACI',
                    factor_forma: 'Modular Multi-Slot Chassis',
                    size: 170
                };
            }

            // 5. Switches Core / Distribución / Multicapa (Catalyst 9300 / 9600 / 6500 / Core / MDF)
            if (
                haystack.includes('9300') || haystack.includes('catalyst 93') || haystack.includes('cat9k') || haystack.includes('c93') || haystack.includes('c9606') || haystack.includes('6500') || haystack.includes('core01') || haystack.includes('core-') || haystack.includes('core_') || haystack.includes('mdf')
            ) {
                return {
                    image: '{{ asset("images/topology/switch-core.svg") }}',
                    rol: 'DISTRIBUTION / CORE',
                    tipo_equipo: 'Switch Multicapa L3 Enterprise Core',
                    factor_forma: '1U/Modular Enterprise Chassis',
                    size: 155
                };
            }

            // 6. Servidores Enterprise Físicos Dedicados (IBM / Dell / HPE / Cisco UCS / ESXi)
            if (
                (
                    haystack.includes('ibm') || haystack.includes('system x') || haystack.includes('bladecenter') || haystack.includes('imm') ||
                    haystack.includes('thinksystem') || haystack.includes('poweredge') || haystack.includes('proliant') || haystack.includes('ucs-b') ||
                    haystack.includes('esxi') || haystack.includes('hyper-v') || haystack.includes('windows server')
                ) &&
                !nomLower.startsWith('sw') && !nomLower.startsWith('nx') && !haystack.includes('switch') && !haystack.includes('catalyst') && !haystack.includes('nexus')
            ) {
                return {
                    image: '{{ asset("images/topology/server.png") }}',
                    rol: 'SERVIDOR / DATA CENTER',
                    tipo_equipo: 'Servidor Enterprise Dedicado',
                    factor_forma: '2U Rackmount Enterprise',
                    size: 210
                };
            }

            // 7. PCs / Workstations / Estaciones de Trabajo
            if (
                (nomLower.startsWith('pc-') || nomLower.startsWith('desktop-') || nomLower.startsWith('laptop-') || nomLower.startsWith('host-') || haystack.includes('workstation')) &&
                !haystack.includes('cisco') && !haystack.includes('switch') && !nomLower.startsWith('sw') && !haystack.includes('ws-c') && !haystack.includes('ios')
            ) {
                return {
                    image: '{{ asset("images/topology/pc.svg") }}',
                    rol: 'ENDPOINT / PC',
                    tipo_equipo: 'Estación de Trabajo / PC',
                    factor_forma: 'Desktop Tower / SFF',
                    size: 120
                };
            }

            // 8. Switches de acceso por defecto (WS-C, Catalyst 2960, 3750, 9200, SG200, etc.)
            return {
                image: '{{ asset("images/topology/switch-access.svg") }}',
                rol: 'ACCESS / SWITCH',
                tipo_equipo: 'Switch de Acceso Gigabit Managed',
                factor_forma: '1U Rackmount Fixed',
                size: 145
            };
        }

        function initNetworkTopology() {
            const container = document.getElementById('networkTopologyCanvas');
            if (!container) return;

            const isDark = document.documentElement.classList.contains('dark');
            const userSavedPositions = initialGrafoData.userPosiciones || {};
            const localSavedPositions = JSON.parse(localStorage.getItem('vis_positions_user'))
                || JSON.parse(localStorage.getItem('vis_positions_v6'))
                || JSON.parse(localStorage.getItem('vis_positions_v5'))
                || {};
            const savedPositions = Object.keys(userSavedPositions).length > 0 ? userSavedPositions : localSavedPositions;
            const hasSavedPositions = initialGrafoData.hasSavedPositions || Object.keys(savedPositions).length > 0;

            // Enriquecer nodos con render frontal según el modelo real
            const mappedNodes = (initialGrafoData.nodes || []).map(node => {
                const cData = node.customData || {};
                const nombre = cData.nombre || node.label || '';
                const imgInfo = getDeviceImageAndRole(cData.modelo, nombre, cData.sysDescr);
                const imagenRuta = cData.image || imgInfo.image || null;
                
                // Evaluación robusta: Es infraestructura si tiene imagen asignada o su nombre lo delata
                let esInfra = imagenRuta ? true : false; 
                if (!esInfra && nombre) {
                    let nom = nombre.toLowerCase();
                    if (nom.includes('switch') || nom.includes('router') || nom.includes('nexus') || nom.includes('cisco')) {
                        esInfra = true;
                    }
                }
                
                return {
                    ...node,
                    id: node.id,
                    label: nombre || 'Desconocido',
                    shape: esInfra ? 'image' : 'dot',
                    image: esInfra ? (imagenRuta || '/img/renders/switch_default.png') : undefined,
                    size: node.size || imgInfo.size || (esInfra ? 145 : 120),
                    color: esInfra ? undefined : { background: '#0ea5e9', border: '#0284c7' },
                    font: { 
                        color: isDark ? '#f8fafc' : '#0f172a', 
                        face: 'Inter, system-ui, sans-serif', 
                        size: 16,
                        vadjust: 38,
                        strokeWidth: 3.5,
                        strokeColor: isDark ? '#090d16' : '#ffffff'
                    },
                    customData: {
                        ...cData,
                        rol: esInfra ? (cData.rol || imgInfo.rol) : 'ENDPOINT',
                        tipo_equipo: esInfra ? (cData.tipo_equipo || imgInfo.tipo_equipo) : 'Dispositivo Final'
                    },
                    title: undefined,
                    x: savedPositions[node.id] ? savedPositions[node.id].x : (node.x !== undefined ? node.x : undefined),
                    y: savedPositions[node.id] ? savedPositions[node.id].y : (node.y !== undefined ? node.y : undefined)
                };
            });

            // Instanciar datasets de Vis.js
            nodesDataSet = new vis.DataSet(mappedNodes);
            
            // Diccionario rápido para roles de nodos
            const nodeRoles = {};
            mappedNodes.forEach(n => {
                nodeRoles[n.id] = n.customData.rol;
            });
            
            // Sanitizar etiquetas de enlaces para no saturar el lienzo visual (solo velocidad compacta)
            const sanitizedEdges = (initialGrafoData.edges || []).map(edge => {
                let lbl = edge.label || '';
                if (lbl.includes('\n')) {
                    const parts = lbl.split('\n');
                    lbl = parts[1] ? parts[1].replace(/\s*\([^\)]*\)/, '') : parts[0];
                }
                
                const isEndpoint = (nodeRoles[edge.to] === 'ENDPOINT' || nodeRoles[edge.from] === 'ENDPOINT');
                
                return {
                    ...edge,
                    label: lbl,
                    to_is_endpoint: isEndpoint,
                    length: isEndpoint ? 260 : 480
                };
            });

            edgesDataSet = new vis.DataSet(sanitizedEdges);

            const data = {
                nodes: nodesDataSet,
                edges: edgesDataSet
            };

            const options = {
                layout: {
                    improvedLayout: true,
                    hierarchical: false
                },
                nodes: {
                    borderWidth: 0,
                    borderWidthSelected: 0,
                    font: {
                        size: 16,
                        color: isDark ? '#f8fafc' : '#0f172a',
                        face: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        vadjust: 38,
                        strokeWidth: 3.5,
                        strokeColor: isDark ? '#090d16' : '#ffffff',
                        align: 'center'
                    },
                    shadow: {
                        enabled: true,
                        color: 'rgba(15, 23, 42, 0.25)',
                        size: 16,
                        x: 0,
                        y: 6
                    }
                },
                edges: {
                    font: {
                        size: 11,
                        color: isDark ? '#94a3b8' : '#475569',
                        background: isDark ? 'rgba(13, 16, 23, 0.88)' : 'rgba(255, 255, 255, 0.95)',
                        strokeWidth: 0,
                        align: 'horizontal'
                    },
                    smooth: { type: 'continuous' },
                    color: { color: '#10b981', opacity: 0.5 },
                    width: 2.5
                },
                physics: {
                    enabled: !hasSavedPositions,
                    solver: 'barnesHut',
                    barnesHut: {
                        gravitationalConstant: -55000,
                        centralGravity: 0.05,
                        springLength: 520,
                        springConstant: 0.04,
                        damping: 0.09,
                        avoidOverlap: 1
                    },
                    stabilization: {
                        enabled: true,
                        iterations: 150
                    }
                },
                interaction: {
                    hover: true,
                    dragNodes: true,
                    dragView: true,
                    zoomView: true,
                    navigationButtons: false,
                    keyboard: false
                }
            };

            network = new vis.Network(container, data, options);

            // Iniciar ciclo de animación a 60 FPS para Haces de Red (MagicUI Animated Beams)
            startTrafficAnimationLoop();

            // Vista general completa sin auto-zoom a nodos específicos
            function frameInitialView() {
                if (!network) return;
                network.fit({
                    animation: { duration: 600, easingFunction: 'easeInOutQuad' },
                    padding: 80
                });
            }

            if (hasSavedPositions) {
                physicsEnabled = false;
                const btnPhys = document.getElementById('btnTogglePhysics');
                const labelPhys = document.getElementById('physicsStatusLabel');
                if (btnPhys) btnPhys.classList.remove('active');
                if (labelPhys) labelPhys.textContent = 'Física Pausada';
                setTimeout(frameInitialView, 150);
            }

            // Encuadre automático y CONGELACIÓN de física al estabilizar
            network.once('stabilizationIterationsDone', function() {
                network.storePositions();
                network.setOptions({ physics: { enabled: false } });
                physicsEnabled = false;
                
                const currentPositions = network.getPositions();
                saveNodePositionsToDatabase(currentPositions);

                const btnPhys = document.getElementById('btnTogglePhysics');
                const labelPhys = document.getElementById('physicsStatusLabel');
                if (btnPhys) btnPhys.classList.remove('active');
                if (labelPhys) labelPhys.textContent = 'Física Pausada';

                setTimeout(frameInitialView, 150);
            });

            // ==================== TOOLTIP OLED INTELIGENTE ====================
            
            // Guardar posiciones al terminar de arrastrar nodos
            network.on('dragEnd', function (params) {
                if (params.nodes && params.nodes.length > 0) {
                    saveNodePositionsToDatabase();
                }
            });

            network.on('hoverNode', function(params) {
                const nodeId = params.node;
                const node = nodesDataSet.get(nodeId);
                if (!node) return;
                
                const cData = node.customData || {};
                document.getElementById('ttNombre').textContent = node.label || cData.nombre || 'Desconocido';
                document.getElementById('ttIp').textContent = cData.ip || 'Sin IP';
                document.getElementById('ttModelo').textContent = cData.modelo || cData.tipo_equipo || '-';
                document.getElementById('ttUbicacion').textContent = cData.ubicacion || '-';
                
                const estadoBadge = document.getElementById('ttEstadoBadge');
                if ((cData.estado || 'online') === 'online') {
                    estadoBadge.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
                } else {
                    estadoBadge.className = 'w-2 h-2 rounded-full bg-red-500 animate-ping';
                }
                
                const tt = document.getElementById('oledTooltip');
                const canvasPos = network.canvasToDOM({x: params.pointer.canvas.x, y: params.pointer.canvas.y});
                const rect = container.getBoundingClientRect();
                tt.style.left = (rect.left + canvasPos.x + 15) + 'px';
                tt.style.top = (rect.top + canvasPos.y + 15) + 'px';
                tt.classList.remove('opacity-0');
                tt.classList.add('opacity-100');
            });

            network.on('blurNode', function(params) {
                const tt = document.getElementById('oledTooltip');
                tt.classList.remove('opacity-100');
                tt.classList.add('opacity-0');
            });

            // ==================== AFTERDRAWING: HACES DE RED CURVADOS (MAGICUI BEAMS) ====================
            network.on('afterDrawing', function(ctx) {
                if (!nodesDataSet) return;
                const nodeIds = nodesDataSet.getIds();
                const nodePositions = network.getPositions(nodeIds);

                // ----------------------------------------------------
                // CAPA 1: HACES DE LUZ ANIMADOS PEGADOS AL CABLE
                // ----------------------------------------------------
                if (edgesDataSet) {
                    const edges = edgesDataSet.get();

                    edges.forEach(edge => {
                        const fromPos = nodePositions[edge.from];
                        const toPos = nodePositions[edge.to];
                        if (!fromPos || !toPos) return;

                        // Obtener el objeto interno de Vis.js para calcular la curva exacta del cable
                        const visEdge = network.body && network.body.edges ? network.body.edges[edge.id] : null;

                        const isDown = edge.customData?.estado === 'DOWN';
                        const isSaturated = (parseFloat(edge.customData?.saturacion_pct) || 0) > 75;

                        // Si la simulación está activa y el enlace está UP, renderizar los pulsos que siguen la curva
                        if (trafficSimulationEnabled && !isDown) {
                            const seed = ((edge.from * 23 + edge.to * 47) % 100) / 100;
                            const speed = 0.35;
                            const beamSpan = 0.14; // Porcentaje del cable ocupado por la estela del haz

                            // 1. Haz Principal (Forward: Origen ➔ Destino)
                            for (let k = 0; k < 2; k++) {
                                const rawPhase = (trafficAnimTime * speed + seed + (k * 0.5)) % 1;
                                // Rango activo entre 0.08 y 0.92 para no tapar los chasis
                                const tHead = 0.08 + rawPhase * 0.84;
                                const tTail = Math.max(0.04, tHead - beamSpan);

                                const headPt = getPointOnVisEdge(visEdge, fromPos, toPos, tHead);
                                const tailPt = getPointOnVisEdge(visEdge, fromPos, toPos, tTail);

                                ctx.save();
                                const grad = ctx.createLinearGradient(tailPt.x, tailPt.y, headPt.x, headPt.y);
                                if (isSaturated) {
                                    grad.addColorStop(0, 'rgba(239, 68, 68, 0)');
                                    grad.addColorStop(0.4, 'rgba(245, 158, 11, 0.7)');
                                    grad.addColorStop(0.9, 'rgba(251, 191, 36, 1)');
                                    grad.addColorStop(1, '#ffffff');
                                    ctx.shadowColor = '#f59e0b';
                                } else {
                                    // Cyber Neon: Cyan -> Esmeralda -> Núcleo Blanco
                                    grad.addColorStop(0, 'rgba(6, 182, 212, 0)');
                                    grad.addColorStop(0.35, 'rgba(16, 185, 129, 0.75)');
                                    grad.addColorStop(0.85, 'rgba(52, 211, 153, 1)');
                                    grad.addColorStop(1, '#ffffff');
                                    ctx.shadowColor = '#10b981';
                                }
                                ctx.strokeStyle = grad;
                                ctx.lineWidth = 3.5;
                                ctx.lineCap = 'round';
                                ctx.shadowBlur = 8;

                                // Dibujar el segmento curvo muestreando puntos exactos sobre la spline del cable
                                ctx.beginPath();
                                const samples = 8;
                                for (let s = 0; s <= samples; s++) {
                                    const st = tTail + (tHead - tTail) * (s / samples);
                                    const pt = getPointOnVisEdge(visEdge, fromPos, toPos, st);
                                    if (s === 0) ctx.moveTo(pt.x, pt.y);
                                    else ctx.lineTo(pt.x, pt.y);
                                }
                                ctx.stroke();

                                // Fotón/Paquete Líder brillante que viaja sobre el cable
                                ctx.beginPath();
                                ctx.arc(headPt.x, headPt.y, 3.2, 0, Math.PI * 2);
                                ctx.fillStyle = '#ffffff';
                                ctx.shadowBlur = 10;
                                ctx.fill();
                                ctx.restore();
                            }

                            // 2. Haz Secundario / ACK (Retorno Full-Duplex: Destino ➔ Origen)
                            const revRawPhase = (trafficAnimTime * (speed * 1.15) + seed * 1.7) % 1;
                            const tRevHead = 0.92 - revRawPhase * 0.84;
                            const tRevTail = Math.min(0.96, tRevHead + (beamSpan * 0.75));

                            const revHeadPt = getPointOnVisEdge(visEdge, fromPos, toPos, tRevHead);
                            const revTailPt = getPointOnVisEdge(visEdge, fromPos, toPos, tRevTail);

                            ctx.save();
                            const revGrad = ctx.createLinearGradient(revTailPt.x, revTailPt.y, revHeadPt.x, revHeadPt.y);
                            revGrad.addColorStop(0, 'rgba(56, 189, 248, 0)');
                            revGrad.addColorStop(0.6, 'rgba(99, 102, 241, 0.8)');
                            revGrad.addColorStop(1, '#ffffff');
                            ctx.strokeStyle = revGrad;
                            ctx.lineWidth = 2.4;
                            ctx.lineCap = 'round';
                            ctx.shadowColor = '#6366f1';
                            ctx.shadowBlur = 6;

                            ctx.beginPath();
                            const revSamples = 6;
                            for (let s = 0; s <= revSamples; s++) {
                                const st = tRevTail + (tRevHead - tRevTail) * (s / revSamples);
                                const pt = getPointOnVisEdge(visEdge, fromPos, toPos, st);
                                if (s === 0) ctx.moveTo(pt.x, pt.y);
                                else ctx.lineTo(pt.x, pt.y);
                            }
                            ctx.stroke();

                            ctx.beginPath();
                            ctx.arc(revHeadPt.x, revHeadPt.y, 2.4, 0, Math.PI * 2);
                            ctx.fillStyle = '#ffffff';
                            ctx.shadowBlur = 8;
                            ctx.fill();
                            ctx.restore();
                        }

                        // Badges de puertos colocados exactamente en los extremos de la curva del cable
                        const p1Text = edge.customData?.origen_puerto_abrev || 'P1';
                        const p2Text = edge.customData?.destino_puerto_abrev || 'P2';
                        const badgePos1 = getPointOnVisEdge(visEdge, fromPos, toPos, 0.12);
                        const badgePos2 = getPointOnVisEdge(visEdge, fromPos, toPos, 0.88);

                        drawPortBadge(ctx, badgePos1.x, badgePos1.y, p1Text, isDown);
                        drawPortBadge(ctx, badgePos2.x, badgePos2.y, p2Text, isDown);
                    });
                }

                // ----------------------------------------------------
                // CAPA 2: MICRO-LEDS DE ESTADO EN HARDWARE
                // ----------------------------------------------------
                nodeIds.forEach(id => {
                    const pos = nodePositions[id];
                    if (!pos) return;

                    const node = nodesDataSet.get(id);
                    if (!node || !node.customData) return;

                    const estado = node.customData.estado || 'online';
                    const nodeSize = node.size || 120;

                    const ledX = pos.x + (nodeSize * 0.92);
                    const ledY = pos.y - (nodeSize * 0.55);
                    const ledRadius = 6;

                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(ledX, ledY, ledRadius, 0, 2 * Math.PI, false);

                    if (estado === 'online') {
                        ctx.fillStyle = '#10b981';
                        ctx.shadowColor = 'rgba(16, 185, 129, 0.8)';
                        ctx.shadowBlur = 8;
                        ctx.fill();

                        ctx.strokeStyle = '#ffffff';
                        ctx.lineWidth = 1.5;
                        ctx.stroke();
                    } else {
                        ctx.fillStyle = '#ef4444';
                        ctx.shadowColor = 'rgba(239, 68, 68, 0.9)';
                        ctx.shadowBlur = 8;
                        ctx.fill();

                        ctx.strokeStyle = '#ffffff';
                        ctx.lineWidth = 1.5;
                        ctx.stroke();
                    }
                    ctx.restore();
                });
            });

            /**
             * Función de alta precisión para interpolar coordenadas a lo largo de la curva exacta de un enlace de Vis.js.
             */
            function getPointOnVisEdge(visEdge, fromPos, toPos, t) {
                // 1. Intentar obtener el punto directamente desde el motor de cálculo spline de Vis.js
                if (visEdge && visEdge.edgeType && typeof visEdge.edgeType.getPoint === 'function') {
                    try {
                        const p = visEdge.edgeType.getPoint(t);
                        if (p && typeof p.x === 'number' && typeof p.y === 'number' && !isNaN(p.x) && !isNaN(p.y)) {
                            return p;
                        }
                    } catch (e) {
                        // Fallback a curva cuadrática
                    }
                }

                // 2. Si Vis.js calculó un punto de control Bezier (via), interpolar la curva cuadrática
                const via = visEdge?.edgeType?.via || visEdge?.via;
                if (via && typeof via.x === 'number' && typeof via.y === 'number') {
                    const inv = 1 - t;
                    return {
                        x: inv * inv * fromPos.x + 2 * inv * t * via.x + t * t * toPos.x,
                        y: inv * inv * fromPos.y + 2 * inv * t * via.y + t * t * toPos.y
                    };
                }

                // 3. Fallback a interpolación lineal si el cable es recto
                return {
                    x: fromPos.x + t * (toPos.x - fromPos.x),
                    y: fromPos.y + t * (toPos.y - fromPos.y)
                };
            }

            // Función auxiliar para dibujar pastillas de puertos en los extremos de los cables
            function drawPortBadge(ctx, x, y, text, isAlert = false) {
                ctx.save();
                ctx.font = 'bold 9px ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';
                const textWidth = ctx.measureText(text).width;
                const padX = 4;
                const h = 13;
                const w = textWidth + padX * 2;
                const rx = x - w / 2;
                const ry = y - h / 2;

                ctx.fillStyle = isAlert ? 'rgba(254, 242, 242, 0.98)' : 'rgba(255, 255, 255, 0.98)';
                ctx.strokeStyle = isAlert ? '#ef4444' : '#94a3b8';
                ctx.lineWidth = 1;

                ctx.beginPath();
                if (ctx.roundRect) {
                    ctx.roundRect(rx, ry, w, h, 2.5);
                } else {
                    ctx.rect(rx, ry, w, h);
                }
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = isAlert ? '#b91c1c' : '#0f172a';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, x, y + 0.5);
                ctx.restore();
            }

            // Evento: Clic en nodo o cable
            network.on('click', function(params) {
                if (params.nodes.length > 0) {
                    const nodeId = params.nodes[0];
                    showDeviceDetails(nodeId);
                } else if (params.edges.length > 0) {
                    const edgeId = params.edges[0];
                    showLinkDetails(edgeId);
                } else {
                    closeDrawer();
                }
            });

            // Cambiar cursor al pasar sobre nodos
            network.on('hoverNode', function() {
                container.style.cursor = 'pointer';
            });
            network.on('blurNode', function() {
                container.style.cursor = 'default';
            });
        }

        // ==================== DRAWER INSPECTOR ====================
        function showDeviceDetails(nodeId) {
            const node = nodesDataSet.get(nodeId);
            if (!node || !node.customData) return;

            const d = node.customData;
            document.getElementById('drawerLinkContent').classList.add('hidden');
            document.getElementById('drawerDeviceContent').classList.remove('hidden');

            document.getElementById('drawerDeviceNombre').textContent = d.nombre;
            document.getElementById('drawerDeviceIp').textContent = d.ip;
            document.getElementById('drawerDeviceRol').textContent = d.rol;
            document.getElementById('drawerDeviceCpu').textContent = d.cpu + '%';
            document.getElementById('drawerDeviceMem').textContent = d.memoria + '%';
            document.getElementById('drawerDevicePing').textContent = d.ping + ' ms';
            document.getElementById('drawerDeviceModelo').textContent = d.modelo;
            document.getElementById('drawerDeviceSerial').textContent = d.serial;
            document.getElementById('drawerDeviceUptime').textContent = d.uptime;
            document.getElementById('drawerDeviceUbicacion').textContent = d.ubicacion;
            document.getElementById('drawerDeviceLink').href = d.url;

            const badge = document.getElementById('drawerDeviceStatusBadge');
            badge.textContent = d.estado.toUpperCase();
            if (d.estado === 'online') {
                badge.className = 'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700';
            } else if (d.estado === 'warning') {
                badge.className = 'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-[#f26419]';
            } else {
                badge.className = 'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-red-100 text-red-700';
            }

            if (d.image) {
                document.getElementById('drawerDeviceIcon').innerHTML = `<img src="${d.image}" class="w-9 h-9 object-contain drop-shadow" alt="${d.nombre}">`;
                const chassisImg = document.getElementById('drawerDeviceChassisImage');
                if (chassisImg) {
                    chassisImg.src = d.image;
                    chassisImg.alt = d.modelo || d.nombre;
                }
            }

            if (document.getElementById('drawerDeviceTipoEquipo')) {
                document.getElementById('drawerDeviceTipoEquipo').textContent = d.tipo_equipo || d.rol;
            }
            if (document.getElementById('drawerDeviceFactorForma')) {
                document.getElementById('drawerDeviceFactorForma').textContent = d.factor_forma || '1U Rackmount';
            }

            // Renderizar lista de conexiones activas / cables del equipo
            const connList = document.getElementById('drawerDeviceConnectionsList');
            const connCount = document.getElementById('drawerDeviceConnCount');
            if (connList && connCount) {
                connList.innerHTML = '';
                const conexiones = d.conexiones || [];
                connCount.textContent = conexiones.length + (conexiones.length === 1 ? ' cable' : ' cables');

                if (conexiones.length === 0) {
                    connList.innerHTML = '<p class="text-xs text-gray-400 italic py-1">Sin enlaces físicos registrados.</p>';
                } else {
                    conexiones.forEach(c => {
                        const item = document.createElement('div');
                        item.className = 'p-2 rounded-lg border border-gray-100 bg-gray-50/80 hover:bg-slate-100 transition cursor-pointer flex items-center justify-between text-xs';
                        item.title = 'Hacer clic para inspeccionar este enlace';
                        item.onclick = function() {
                            showLinkDetails(c.enlace_id);
                            if (network) {
                                network.selectEdges([c.enlace_id]);
                            }
                        };

                        item.innerHTML = `
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${c.color};"></span>
                                <span class="font-mono font-bold text-slate-800 bg-white px-1.5 py-0.5 rounded border border-gray-200 text-[10px] shadow-2xs">${c.puerto_local}</span>
                                <span class="text-gray-400 text-[10px]">➔</span>
                                <div class="truncate max-w-[130px]">
                                    <p class="font-semibold text-slate-800 leading-tight truncate">${c.remoto_nombre}</p>
                                    <p class="text-[10px] text-gray-500 font-mono">${c.remoto_ip} • ${c.puerto_remoto}</p>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="text-[9px] font-black px-1.5 py-0.5 rounded ${c.estado === 'UP' ? 'bg-emerald-100 text-emerald-700' : (c.estado === 'DOWN' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')}">${c.estado}</span>
                                <p class="text-[9px] text-gray-400 font-mono mt-0.5">${c.velocidad}</p>
                            </div>
                        `;
                        connList.appendChild(item);
                    });
                }
            }
            // Sección Especial: Dispositivo en Puerto PC (Pass-Through)
            const pcSection = document.getElementById('drawerPhonePcSection');
            if (pcSection) {
                if (d.pc_conectada && d.pc_conectada.has_attached_pc) {
                    const pc = d.pc_conectada;
                    pcSection.classList.remove('hidden');
                    document.getElementById('drawerPhonePcHostname').textContent = pc.hostname;
                    document.getElementById('drawerPhonePcVendor').textContent = pc.vendor;
                    document.getElementById('drawerPhonePcIp').textContent = pc.ip;
                    document.getElementById('drawerPhonePcMac').textContent = pc.mac;
                    document.getElementById('drawerPhonePcSpeed').textContent = pc.speed;
                    document.getElementById('drawerPhonePcVlan').textContent = pc.vlan;
                    document.getElementById('drawerPhonePcSwitchPort').textContent = `${pc.switch_name} (${pc.switch_port})`;
                    
                    const pcBadge = document.getElementById('drawerPhonePcStatusBadge');
                    if (pcBadge) {
                        pcBadge.className = 'text-[9px] font-black px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300';
                        pcBadge.textContent = 'CONECTADA';
                    }
                } else if (d.pc_conectada && d.pc_conectada.status === 'standby') {
                    const pc = d.pc_conectada;
                    pcSection.classList.remove('hidden');
                    document.getElementById('drawerPhonePcHostname').textContent = 'Puerto PC Disponible';
                    document.getElementById('drawerPhonePcVendor').textContent = 'Sin computadora transmitiendo';
                    document.getElementById('drawerPhonePcIp').textContent = 'Standby';
                    document.getElementById('drawerPhonePcMac').textContent = 'Puerto 10/100/1000';
                    document.getElementById('drawerPhonePcSpeed').textContent = '1.0 Gbps Capacidad';
                    document.getElementById('drawerPhonePcVlan').textContent = 'VLAN Datos';
                    document.getElementById('drawerPhonePcSwitchPort').textContent = `${pc.switch_name} (${pc.switch_port})`;
                    
                    const pcBadge = document.getElementById('drawerPhonePcStatusBadge');
                    if (pcBadge) {
                        pcBadge.className = 'text-[9px] font-black px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300';
                        pcBadge.textContent = 'STANDBY';
                    }
                } else {
                    pcSection.classList.add('hidden');
                }
            }

            openDrawer();
        }

        function showLinkDetails(edgeId) {
            const edge = edgesDataSet.get(edgeId);
            if (!edge || !edge.customData) return;

            const l = edge.customData;
            document.getElementById('drawerDeviceContent').classList.add('hidden');
            document.getElementById('drawerLinkContent').classList.remove('hidden');

            document.getElementById('drawerLinkTipo').textContent = l.tipo_medio;
            document.getElementById('drawerLinkVelocidad').textContent = (l.velocidad_mbps >= 1000 ? (l.velocidad_mbps/1000) + ' Gbps' : l.velocidad_mbps + ' Mbps') + ' Negociados';
            document.getElementById('drawerLinkOrigenDisp').textContent = l.origen_disp;
            document.getElementById('drawerLinkOrigenPuerto').textContent = l.origen_puerto;
            document.getElementById('drawerLinkDestinoDisp').textContent = l.destino_disp;
            document.getElementById('drawerLinkDestinoPuerto').textContent = l.destino_puerto;
            document.getElementById('drawerLinkTraficoIn').textContent = l.trafico_in + ' Mbps';
            document.getElementById('drawerLinkTraficoOut').textContent = l.trafico_out + ' Mbps';
            document.getElementById('drawerLinkSaturacion').textContent = l.saturacion_pct + '%';

            const badge = document.getElementById('drawerLinkStatusBadge');
            badge.textContent = l.estado;
            if (l.estado === 'UP') {
                badge.className = 'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700';
            } else if (l.estado === 'DEGRADED') {
                badge.className = 'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-[#f26419]';
            } else {
                badge.className = 'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-red-100 text-red-700';
            }

            // Vlans tags
            const vlansContainer = document.getElementById('drawerLinkVlans');
            vlansContainer.innerHTML = '';
            const vlans = l.vlans.split(',');
            vlans.forEach(v => {
                const span = document.createElement('span');
                span.className = 'px-2 py-0.5 bg-gray-100 text-gray-700 text-[10px] font-bold rounded';
                span.textContent = 'VLAN ' + v.trim();
                vlansContainer.appendChild(span);
            });

            openDrawer();
        }

        function openDrawer() {
            const drawer = document.getElementById('inspectorDrawer');
            if (drawer) drawer.classList.add('drawer-open');
        }

        function closeDrawer() {
            const drawer = document.getElementById('inspectorDrawer');
            if (drawer) drawer.classList.remove('drawer-open');
        }

        // ==================== CONTROLES DE LIENZO ====================
        function toggleTrafficBeams() {
            trafficSimulationEnabled = !trafficSimulationEnabled;
            const btn = document.getElementById('btnToggleBeams');
            const label = document.getElementById('beamsStatusLabel');
            if (trafficSimulationEnabled) {
                if (btn) btn.classList.add('active');
                if (label) label.textContent = 'Haces Activos (Beams)';
            } else {
                if (btn) btn.classList.remove('active');
                if (label) label.textContent = 'Haces Pausados';
                if (network) network.redraw();
            }
        }

        function startTrafficAnimationLoop() {
            if (animLoopRunning) return;
            animLoopRunning = true;
            let lastTimestamp = performance.now();

            function frame(now) {
                const delta = (now - lastTimestamp) / 1000;
                lastTimestamp = now;

                if (trafficSimulationEnabled && network) {
                    trafficAnimTime += delta * trafficSpeedMultiplier;
                    network.redraw();
                }
                requestAnimationFrame(frame);
            }
            requestAnimationFrame(frame);
        }

        function resetZoom() {
            if (network) {
                network.fit({
                    animation: {
                        duration: 800,
                        easingFunction: 'easeInOutQuad'
                    },
                    padding: 80
                });
            }
        }



        function togglePhysics() {
            physicsEnabled = !physicsEnabled;
            if (network) {
                network.setOptions({ physics: { enabled: physicsEnabled } });
            }
            const btn = document.getElementById('btnTogglePhysics');
            const label = document.getElementById('physicsStatusLabel');
            if (physicsEnabled) {
                if (btn) btn.classList.add('active');
                if (label) label.textContent = 'Física Activa';
            } else {
                if (btn) btn.classList.remove('active');
                if (label) label.textContent = 'Física Pausada';
            }
        }

        function setLayout(type) {
            if (!network) return;

            const btnTree = document.getElementById('btnLayoutTree');
            const btnFree = document.getElementById('btnLayoutFree');

            if (type === 'tree') {
                if (btnTree) btnTree.classList.add('active');
                if (btnFree) btnFree.classList.remove('active');

                network.setOptions({
                    layout: {
                        hierarchical: {
                            enabled: true,
                            direction: 'UD',
                            sortMethod: 'directed',
                            nodeSpacing: 450,
                            levelSeparation: 480
                        }
                    },
                    physics: { enabled: false }
                });
                
                // Synchronous processing
                network.storePositions();
                network.setOptions({ layout: { hierarchical: false } });
                
                const currentPositions = network.getPositions();
                saveNodePositionsToDatabase(currentPositions);

                physicsEnabled = false;
                const btnPhys = document.getElementById('btnTogglePhysics');
                const labelPhys = document.getElementById('physicsStatusLabel');
                if (btnPhys) btnPhys.classList.remove('active');
                if (labelPhys) labelPhys.textContent = 'Física Inactiva';

                setTimeout(() => network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' }, padding: 80 }), 250);
            } else {
                if (btnFree) btnFree.classList.add('active');
                if (btnTree) btnTree.classList.remove('active');

                network.setOptions({
                    layout: { hierarchical: false },
                    physics: {
                        enabled: true,
                        solver: 'barnesHut',
                        barnesHut: {
                            gravitationalConstant: -55000,
                            centralGravity: 0.05,
                            springLength: 520,
                            springConstant: 0.04,
                            damping: 0.09,
                            avoidOverlap: 1
                        },
                        stabilization: {
                            enabled: true,
                            iterations: 150
                        }
                    }
                });
                physicsEnabled = true;
                
                network.once('stabilizationIterationsDone', function() {
                    network.setOptions({ physics: { enabled: false } });
                    physicsEnabled = false;
                    const btnPhys = document.getElementById('btnTogglePhysics');
                    const labelPhys = document.getElementById('physicsStatusLabel');
                    if (btnPhys) btnPhys.classList.remove('active');
                    if (labelPhys) labelPhys.textContent = 'Física Pausada';
                    
                    const currentPositions = network.getPositions();
                    saveNodePositionsToDatabase(currentPositions);
                    
                    network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' }, padding: 80 });
                });
                
                const btnPhys = document.getElementById('btnTogglePhysics');
                const labelPhys = document.getElementById('physicsStatusLabel');
                if (btnPhys) btnPhys.classList.add('active');
                if (labelPhys) labelPhys.textContent = 'Física Activa';
            }
        }

        // ==================== TOGGLE BARRA DE BÚSQUEDA Y FILTROS ====================
        function toggleRightFilterBar() {
            const exp = document.getElementById('rightFilterBarExpanded');
            const col = document.getElementById('rightFilterBarCollapsed');
            if (!exp || !col) return;
            const isHidden = exp.classList.contains('hidden');
            if (isHidden) {
                exp.classList.remove('hidden');
                col.classList.add('hidden');
                localStorage.setItem('topo_right_bar_collapsed', '0');
            } else {
                exp.classList.add('hidden');
                col.classList.remove('hidden');
                localStorage.setItem('topo_right_bar_collapsed', '1');
            }
        }

        // ==================== FILTRADO DE DISPOSITIVOS Y BUSCADOR ====================
        function filterDeviceCategory(category, btn) {
            document.querySelectorAll('.device-filter-btn').forEach(b => {
                b.classList.remove('active');
            });
            if (btn) {
                btn.classList.add('active');
            }

            if (!nodesDataSet || !network) return;

            const allNodes = nodesDataSet.get();
            if (category === 'all') {
                allNodes.forEach(node => {
                    nodesDataSet.update({ id: node.id, opacity: 1 });
                });
                network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' }, padding: 80 });
                return;
            }

            const matchedNodeIds = [];
            allNodes.forEach(node => {
                const rol = (node.customData?.rol || '').toLowerCase();
                const nombre = (node.label || node.customData?.nombre || '').toLowerCase();
                const tipo = (node.customData?.tipo_equipo || '').toLowerCase();
                const modelo = (node.customData?.modelo || '').toLowerCase();
                const hay = rol + ' ' + nombre + ' ' + tipo + ' ' + modelo;

                let match = false;
                const nomLower = nombre.toLowerCase();
                if (category === 'router') {
                    match = (hay.includes('router') || hay.includes('isr') || hay.includes('1841') || hay.includes('4400') || hay.includes('4451') || hay.includes('4331') || hay.includes('cube') || hay.includes('gw-') || hay.includes('-gw') || hay.includes('gw_') || hay.includes('sat.') || hay.includes('-sat')) && !hay.includes('catalyst') && !nomLower.startsWith('sw') && !nomLower.includes('swc') && !nomLower.includes('swe') && !nomLower.startsWith('nx') && !hay.includes('nexus');
                } else if (category === 'switch') {
                    match = (hay.includes('switch') || hay.includes('nexus') || hay.includes('catalyst') || hay.includes('core') || hay.includes('access') || hay.includes('ws-c') || nomLower.startsWith('sw') || nomLower.startsWith('nx') || nomLower.includes('idf') || nomLower.includes('mdf')) && !nomLower.startsWith('sep');
                } else if (category === 'access_point') {
                    match = (hay.includes('ap') || hay.includes('wifi') || hay.includes('access point') || hay.includes('wireless') || hay.includes('c9115') || hay.includes('wlc') || hay.includes('c9800')) && !nomLower.startsWith('sw');
                } else if (category === 'phone') {
                    match = nomLower.startsWith('sep') || hay.includes('phone') || hay.includes('telefono') || hay.includes('teléfono') || hay.includes('voip');
                } else if (category === 'servidor') {
                    match = (hay.includes('ibm') || hay.includes('system x') || hay.includes('bladecenter') || hay.includes('imm') || hay.includes('thinksystem') || hay.includes('poweredge') || hay.includes('proliant') || hay.includes('ucs-b') || hay.includes('esxi') || hay.includes('hyper-v') || hay.includes('windows server')) && !nomLower.startsWith('sw') && !nomLower.startsWith('nx') && !hay.includes('switch') && !hay.includes('catalyst');
                }

                if (match) {
                    matchedNodeIds.push(node.id);
                    nodesDataSet.update({ id: node.id, opacity: 1 });
                } else {
                    nodesDataSet.update({ id: node.id, opacity: 0.18 });
                }
            });

            if (matchedNodeIds.length > 0) {
                network.fit({
                    nodes: matchedNodeIds,
                    animation: { duration: 800, easingFunction: 'easeInOutQuad' },
                    padding: 100
                });
            }
        }

        function searchTopologyDevice(query) {
            query = (query || '').trim().toLowerCase();
            if (!nodesDataSet || !network) return;

            const allNodes = nodesDataSet.get();
            if (!query) {
                allNodes.forEach(node => {
                    nodesDataSet.update({ id: node.id, opacity: 1 });
                });
                return;
            }

            const matchedNodeIds = [];
            allNodes.forEach(node => {
                const nombre = (node.label || node.customData?.nombre || '').toLowerCase();
                const ip = (node.customData?.ip || '').toLowerCase();
                const modelo = (node.customData?.modelo || '').toLowerCase();
                const rol = (node.customData?.rol || '').toLowerCase();

                if (nombre.includes(query) || ip.includes(query) || modelo.includes(query) || rol.includes(query)) {
                    matchedNodeIds.push(node.id);
                    nodesDataSet.update({ id: node.id, opacity: 1 });
                } else {
                    nodesDataSet.update({ id: node.id, opacity: 0.12 });
                }
            });

            if (matchedNodeIds.length === 1) {
                network.focus(matchedNodeIds[0], {
                    scale: 1.15,
                    animation: { duration: 600, easingFunction: 'easeInOutQuad' }
                });
                network.selectNodes([matchedNodeIds[0]]);
                showDeviceDetails(matchedNodeIds[0]);
            } else if (matchedNodeIds.length > 1) {
                network.fit({
                    nodes: matchedNodeIds,
                    animation: { duration: 600, easingFunction: 'easeInOutQuad' },
                    padding: 100
                });
            }
        }

        function filterEdges(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-pill-btn').forEach(b => {
                b.classList.remove('active');
            });
            if (btn) {
                btn.classList.add('active');
            }

            if (!edgesDataSet) return;

            const allEdges = initialGrafoData.edges;
            if (filter === 'all') {
                edgesDataSet.clear();
                edgesDataSet.add(allEdges);
            } else if (filter === 'up') {
                edgesDataSet.clear();
                edgesDataSet.add(allEdges.filter(e => e.customData.estado === 'UP'));
            } else if (filter === 'down') {
                edgesDataSet.clear();
                edgesDataSet.add(allEdges.filter(e => e.customData.estado !== 'UP'));
            }
        }

        function exportTopologyImage() {
            const canvas = document.querySelector('#networkTopologyCanvas canvas');
            if (!canvas) return;

            const link = document.createElement('a');
            link.download = 'Topologia_Red_NOC_' + new Date().toISOString().slice(0,10) + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        // ==================== AUTO-DISCOVERY CRAWLER LOGIC ====================
        let discoveryMethod = 'cdp_lldp';
        let discoveryTimerInterval = null;
        let discoverySeconds = 0;

        function openDiscoveryModal() {
            const modal = document.getElementById('discoveryModal');
            const card = document.getElementById('discoveryModalCard');
            if (!modal || !card) return;

            // Reset views
            document.getElementById('discoveryFormState').classList.remove('hidden');
            document.getElementById('discoveryScanningState').classList.add('hidden');
            document.getElementById('discoveryResultState').classList.add('hidden');
            document.getElementById('btnSubmitDiscovery').disabled = false;

            // Open animation
            modal.classList.remove('opacity-0', 'pointer-events-none');
            card.classList.remove('scale-95');
        }

        function closeDiscoveryModal() {
            const modal = document.getElementById('discoveryModal');
            const card = document.getElementById('discoveryModalCard');
            if (!modal || !card) return;

            if (discoveryTimerInterval) {
                clearInterval(discoveryTimerInterval);
                discoveryTimerInterval = null;
            }

            modal.classList.add('opacity-0', 'pointer-events-none');
            card.classList.add('scale-95');
        }

        function setDiscoveryMethod(method) {
            discoveryMethod = method;
            const btnCdp = document.getElementById('btnMethodCdp');
            const btnCidr = document.getElementById('btnMethodCidr');
            const seedGroup = document.getElementById('fieldSeedIpGroup');
            const cidrGroup = document.getElementById('fieldCidrGroup');

            if (method === 'cdp_lldp') {
                btnCdp.className = 'py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-white text-indigo-900 shadow-sm border border-slate-200';
                btnCidr.className = 'py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
                seedGroup.classList.remove('hidden');
                cidrGroup.classList.add('hidden');
            } else {
                btnCidr.className = 'py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-white text-indigo-900 shadow-sm border border-slate-200';
                btnCdp.className = 'py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
                seedGroup.classList.add('hidden');
                cidrGroup.classList.remove('hidden');
            }
        }

        async function startAutoDiscovery() {
            const seedIp = document.getElementById('discSeedIp').value.trim();
            const cidr = document.getElementById('discCidr').value.trim();
            const community = document.getElementById('discCommunity').value.trim() || 'public';
            const maxDepth = parseInt(document.getElementById('discMaxDepth').value, 10) || 3;

            if (discoveryMethod === 'cdp_lldp' && !seedIp) {
                alert('Por favor introduce una dirección IP semilla válida.');
                return;
            }
            if (discoveryMethod === 'cidr_sweep' && !cidr) {
                alert('Por favor introduce un prefijo CIDR válido (ej. 192.168.1.0/24).');
                return;
            }

            // Cambiar a vista de escaneo
            document.getElementById('discoveryFormState').classList.add('hidden');
            document.getElementById('discoveryScanningState').classList.remove('hidden');
            document.getElementById('discoveryResultState').classList.add('hidden');

            const logBox = document.getElementById('discoveryLogBox');
            logBox.innerHTML = `<p class="text-indigo-400 font-bold">// Iniciando motor de auto-descubrimiento (${discoveryMethod.toUpperCase()})...</p>`;
            if (discoveryMethod === 'cdp_lldp') {
                logBox.innerHTML += `<p class="text-slate-300">Conectando a IP semilla: ${seedIp} (Comunidad: ${community})...</p>`;
                logBox.innerHTML += `<p class="text-slate-400">Rastreando tablas OID CDP y LLDP en profundidad...</p>`;
            } else {
                logBox.innerHTML += `<p class="text-slate-300">Iniciando barrido paralelo en bloque ${cidr}...</p>`;
            }

            // Iniciar cronómetro
            discoverySeconds = 0;
            const timerEl = document.getElementById('discoveryTimer');
            timerEl.textContent = 'Tiempo: 00:00s';
            if (discoveryTimerInterval) clearInterval(discoveryTimerInterval);
            discoveryTimerInterval = setInterval(() => {
                discoverySeconds++;
                const mins = String(Math.floor(discoverySeconds / 60)).padStart(2, '0');
                const secs = String(discoverySeconds % 60).padStart(2, '0');
                timerEl.textContent = `Tiempo: ${mins}:${secs}s`;
            }, 1000);

            try {
                const response = await fetch("{{ route('topologia.descubrir') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        method: discoveryMethod,
                        seed_ip: seedIp,
                        cidr: cidr,
                        community: community,
                        max_depth: maxDepth
                    })
                });

                const result = await response.json();
                clearInterval(discoveryTimerInterval);

                if (!result.success) {
                    throw new Error(result.message || 'Error durante el descubrimiento');
                }

                // Renderizar logs devueltos por el crawler en la consola
                const data = result.data || {};
                const logs = data.logs || [];
                logs.forEach(l => {
                    const p = document.createElement('p');
                    p.className = l.includes('ERROR') || l.includes('No responde') ? 'text-amber-400' : 'text-slate-300';
                    p.textContent = l;
                    logBox.appendChild(p);
                });
                logBox.scrollTop = logBox.scrollHeight;

                // Llenar estadísticas de resultado
                document.getElementById('resTotalDevices').textContent = data.total_devices ?? 0;
                document.getElementById('resNewDevices').textContent = `(${data.new_devices ?? 0} nuevos)`;
                document.getElementById('resTotalLinks').textContent = data.total_links ?? 0;
                document.getElementById('resNewLinks').textContent = `(${data.new_links ?? 0} nuevos)`;
                document.getElementById('discoveryResultMessage').textContent = result.message;

                // Transición a vista de éxito
                document.getElementById('discoveryScanningState').classList.add('hidden');
                document.getElementById('discoveryResultState').classList.remove('hidden');

                // Recargar el mapa de topología automáticamente con la nueva topología descubierta
                await reloadTopologyData();

            } catch (error) {
                clearInterval(discoveryTimerInterval);
                const p = document.createElement('p');
                p.className = 'text-red-400 font-bold';
                p.textContent = `[ERROR] ${error.message}`;
                logBox.appendChild(p);
                alert(`Error en el auto-descubrimiento: ${error.message}`);
                // Revertir a formulario
                document.getElementById('discoveryScanningState').classList.add('hidden');
                document.getElementById('discoveryFormState').classList.remove('hidden');
            }
        }

        async function reloadTopologyData() {
            try {
                const res = await fetch("{{ route('topologia.datos') }}");
                if (!res.ok) throw new Error('No se pudieron recargar los datos del grafo');
                const data = await res.json();

                // Actualizar datasets de Vis.js
                if (nodesDataSet && edgesDataSet) {
                    nodesDataSet.clear();
                    edgesDataSet.clear();
                    nodesDataSet.add(data.nodes);
                    edgesDataSet.add(data.edges);

                    // Actualizar referencia para filtros
                    initialGrafoData.nodes = data.nodes;
                    initialGrafoData.edges = data.edges;

                    // Reajustar vista y física
                    if (network) {
                        network.fit({
                            animation: {
                                duration: 1000,
                                easingFunction: 'easeInOutQuad'
                            }
                        });
                    }
                }

                // Actualizar badges del encabezado
                const totalNodos = data.nodes.length;
                const totalEnlaces = data.edges.length;
                const enlacesUp = data.edges.filter(e => (e.customData?.estado || '').toUpperCase() === 'UP').length;
                const enlacesDown = totalEnlaces - enlacesUp;
                let capGbps = 0;
                data.edges.forEach(e => {
                    capGbps += (e.customData?.velocidad_mbps || 1000) / 1000;
                });

                const badgeN = document.getElementById('badgeNodos');
                const badgeE = document.getElementById('badgeEnlaces');
                const badgeUp = document.getElementById('badgeEnlacesUp');
                const badgeDown = document.getElementById('badgeEnlacesDown');
                const badgeCap = document.getElementById('badgeCapacidad');
                const alertContainer = document.getElementById('badgeAlertaContainer');

                if (badgeN) badgeN.textContent = totalNodos;
                if (badgeE) badgeE.textContent = totalEnlaces;
                if (badgeUp) badgeUp.textContent = enlacesUp;
                if (badgeDown) badgeDown.textContent = enlacesDown;
                if (badgeCap) badgeCap.textContent = `${capGbps.toFixed(1)} Gbps`;
                if (alertContainer) {
                    if (enlacesDown > 0) alertContainer.classList.remove('hidden');
                    else alertContainer.classList.add('hidden');
                }

            } catch (err) {
                console.error('Error al actualizar el lienzo de topología:', err);
            }
        }

        // ==================== ESCANEO DE SUBRED LOCAL / CUALQUIER IP ====================
        let rj45TimerInterval = null;
        let rj45StartTime = null;

        function closeLocalRj45Modal() {
            const modal = document.getElementById('localRj45Modal');
            if (modal) modal.classList.add('hidden');
            if (rj45TimerInterval) {
                clearInterval(rj45TimerInterval);
                rj45TimerInterval = null;
            }
        }

        function openLocalRj45Modal() {
            const modal = document.getElementById('localRj45Modal');
            if (!modal) return;
            modal.classList.remove('hidden');
            document.getElementById('rj45FormState').classList.remove('hidden');
            document.getElementById('rj45ScanningState').classList.add('hidden');
            document.getElementById('rj45ResultState').classList.add('hidden');
            document.getElementById('rj45ErrorState').classList.add('hidden');
        }

        function setRj45Target(val) {
            const input = document.getElementById('rj45SubnetInput');
            if (input) {
                input.value = val;
                input.focus();
            }
        }

        async function executeLocalScan() {
            const targetSubnet = document.getElementById('rj45SubnetInput')?.value?.trim() || '';
            const community = document.getElementById('rj45CommunityInput')?.value?.trim() || 'public';
            const threads = parseInt(document.getElementById('rj45ThreadsInput')?.value || '50', 10);

            const modal = document.getElementById('localRj45Modal');
            const formState = document.getElementById('rj45FormState');
            const scanState = document.getElementById('rj45ScanningState');
            const resState = document.getElementById('rj45ResultState');
            const errState = document.getElementById('rj45ErrorState');
            const progressBar = document.getElementById('rj45ProgressBar');
            const logBox = document.getElementById('rj45LogBox');
            const timerEl = document.getElementById('rj45Timer');

            if (!modal) return;

            // Transición a estado de escaneo
            formState.classList.add('hidden');
            scanState.classList.remove('hidden');
            resState.classList.add('hidden');
            errState.classList.add('hidden');

            const displayTarget = targetSubnet || 'Autodetección de red';
            document.getElementById('rj45StatusTitle').textContent = `Iniciando escaneo: ${displayTarget}...`;
            document.getElementById('rj45StatusSubtitle').textContent = "Preparando barrido concurrente y consultas SNMP...";
            document.getElementById('rj45NicName').textContent = targetSubnet ? 'Objetivo Personalizado' : 'Detectando...';
            document.getElementById('rj45NicIp').textContent = targetSubnet || 'Calculando /24...';
            document.getElementById('rj45NicGw').textContent = "Determinando Gateway...";

            if (progressBar) progressBar.style.width = '15%';

            if (logBox) {
                logBox.innerHTML = `
                    <p class="text-emerald-400 font-bold">[1/4] Iniciando escáner sobre objetivo: ${displayTarget}...</p>
                `;
            }

            // Iniciar cronómetro
            if (rj45TimerInterval) clearInterval(rj45TimerInterval);
            rj45StartTime = Date.now();
            rj45TimerInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - rj45StartTime) / 1000);
                const mm = String(Math.floor(elapsed / 60)).padStart(2, '0');
                const ss = String(elapsed % 60).padStart(2, '0');
                if (timerEl) timerEl.textContent = `Tiempo transcurrido: ${mm}:${ss}s`;
            }, 500);

            // Simular progresión fluida
            const p1 = setTimeout(() => {
                if (progressBar) progressBar.style.width = '45%';
                document.getElementById('rj45StatusTitle').textContent = `Barrido ICMP (${threads} hilos)...`;
                document.getElementById('rj45StatusSubtitle').textContent = `Enviando pings ultra-rápidos a los hosts de ${displayTarget}...`;
                if (logBox) {
                    logBox.innerHTML += `<p class="text-cyan-300">[2/4] Ejecutando barrido ICMP en paralelo (${threads} hilos)...</p>`;
                    logBox.scrollTop = logBox.scrollHeight;
                }
            }, 700);

            const p2 = setTimeout(() => {
                if (progressBar) progressBar.style.width = '75%';
                document.getElementById('rj45StatusTitle').textContent = "Consultando SNMPv2c & Vecinos CDP/LLDP...";
                document.getElementById('rj45StatusSubtitle').textContent = "Extrayendo sysName, sysDescr y tablas de adyacencia física...";
                if (logBox) {
                    logBox.innerHTML += `<p class="text-indigo-300">[3/4] Enviando consultas SNMPv2c (comunidad '${community}')...</p>`;
                    logBox.scrollTop = logBox.scrollHeight;
                }
            }, 1800);

            try {
                const response = await fetch("{{ route('topologia.escanear_local') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        subnet: targetSubnet,
                        community: community,
                        threads: threads
                    })
                });

                clearTimeout(p1);
                clearTimeout(p2);
                if (rj45TimerInterval) {
                    clearInterval(rj45TimerInterval);
                    rj45TimerInterval = null;
                }

                let result = null;
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    result = await response.json();
                } else {
                    const htmlText = await response.text();
                    console.error('Respuesta HTTP no JSON:', htmlText);
                    throw new Error(`El servidor devolvió un error (HTTP ${response.status}). Por favor recarga la página o verifica tu sesión.`);
                }

                if (!response.ok || !result.success) {
                    if (result && result.is_warning) {
                        const adapter = result.data?.adapter || {};
                        document.getElementById('rj45NicName').textContent = adapter.name || 'Ethernet Desconectada';
                        document.getElementById('rj45NicIp').textContent = adapter.ip ? `${adapter.ip} (${adapter.cidr || '/24'})` : 'Sin Dirección IP';
                        document.getElementById('rj45NicGw').textContent = adapter.gateway || 'Sin Gateway';

                        document.getElementById('rj45ResHosts').textContent = '0';
                        document.getElementById('rj45ResDevices').textContent = '0';
                        document.getElementById('rj45ResNewDevices').textContent = '(0 nuevos)';
                        document.getElementById('rj45ResLinks').textContent = '0 enlaces';

                        document.getElementById('rj45ResultTitle').textContent = 'Cable RJ-45 Desconectado';
                        document.getElementById('rj45ResultMessage').textContent = result.message || 'Por favor conecta el cable de red RJ-45 al switch y enciende el equipo.';

                        setTimeout(() => {
                            scanState.classList.add('hidden');
                            resState.classList.remove('hidden');
                        }, 400);
                        return;
                    }
                    throw new Error(result.message || result.error || 'Error al ejecutar escaneo local.');
                }

                if (progressBar) progressBar.style.width = '100%';
                if (logBox) {
                    logBox.innerHTML += `<p class="text-emerald-300 font-bold">[4/4] Sincronización en base de datos completada exitosamente.</p>`;
                    logBox.scrollTop = logBox.scrollHeight;
                }

                const data = result.data || {};
                const adapter = data.adapter || {};
                const aliveHosts = data.ping_alive_hosts || [];
                const totalDevices = data.total_devices || 0;

                document.getElementById('rj45NicName').textContent = adapter.name || 'Ethernet';
                document.getElementById('rj45NicIp').textContent = `${adapter.ip || '127.0.0.1'} (${adapter.cidr || '/24'})`;
                document.getElementById('rj45NicGw').textContent = adapter.gateway || 'Sin Gateway';

                document.getElementById('rj45ResHosts').textContent = aliveHosts.length;
                document.getElementById('rj45ResDevices').textContent = totalDevices;
                document.getElementById('rj45ResNewDevices').textContent = `(${data.new_devices || 0} nuevos)`;
                document.getElementById('rj45ResLinks').textContent = `${data.total_links || 0} enlaces`;

                const bannerBox = document.getElementById('rj45BannerBox');
                const bannerIcon = document.getElementById('rj45BannerIcon');
                const resTitle = document.getElementById('rj45ResultTitle');
                const resMsg = document.getElementById('rj45ResultMessage');
                const zeroNotice = document.getElementById('rj45ZeroDevicesNotice');

                if (totalDevices > 0) {
                    if (bannerBox) bannerBox.className = "flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 transition-colors duration-200";
                    if (bannerIcon) {
                        bannerIcon.className = "w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-emerald-500/20";
                        bannerIcon.innerHTML = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>`;
                    }
                    if (resTitle) {
                        resTitle.className = "text-sm font-black text-emerald-900";
                        resTitle.textContent = "¡Subred RJ-45 Mapeada con Éxito!";
                    }
                    if (resMsg) {
                        resMsg.className = "text-xs text-emerald-700 font-medium";
                        resMsg.textContent = `${totalDevices} equipo(s) gestionable(s) y ${data.total_links || 0} enlace(s) físico(s) sincronizados.`;
                    }
                    if (zeroNotice) zeroNotice.classList.add('hidden');
                } else {
                    if (bannerBox) bannerBox.className = "flex items-center gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200 transition-colors duration-200";
                    if (bannerIcon) {
                        bannerIcon.className = "w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-amber-500/20";
                        bannerIcon.innerHTML = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
                    }
                    if (resTitle) {
                        resTitle.className = "text-sm font-black text-amber-900";
                        resTitle.textContent = "Sin Dispositivos Detectados en el Segmento";
                    }
                    if (resMsg) {
                        resMsg.className = "text-xs text-amber-700 font-medium";
                        resMsg.textContent = `Escaneo finalizado (${data.scan_time_sec || '6.6'}s). No se detectaron equipos respondiendo a ICMP o SNMP en ${adapter.ip || '192.168.1.50'}/24.`;
                    }
                    if (zeroNotice) zeroNotice.classList.remove('hidden');
                }

                setTimeout(() => {
                    scanState.classList.add('hidden');
                    resState.classList.remove('hidden');
                }, 400);

                // Recargar el lienzo de topología automáticamente
                await reloadTopologyData();

            } catch (err) {
                clearTimeout(p1);
                clearTimeout(p2);
                if (rj45TimerInterval) {
                    clearInterval(rj45TimerInterval);
                    rj45TimerInterval = null;
                }

                scanState.classList.add('hidden');
                errState.classList.remove('hidden');
                document.getElementById('rj45ErrorMessage').textContent = err.message || 'Error de conexión con el servidor.';
                console.error('Error en escaneo local RJ-45:', err);
            }
        }
        // ==========================================
        // Live Status Badge Toggle (Online/Offline)
        // ==========================================
        // Enlaces a las animaciones Lottie
        const animOnline = 'https://lottie.host/8b5830cd-4218-4247-87fb-5bd887d203d8/PomLBEIdAK.lottie';
        const animOffline = 'https://lottie.host/5bb3a0e0-68ed-412a-9881-f8bedba1bf0f/6IVaKzhSJr.lottie';
        
        let snmpIsWorking = true;

        async function checkSNMPHealth() {
            if (!navigator.onLine) return; // Si no hay red local, no checamos
            try {
                // Hacemos ping rápido para ver si hay nodos activos
                const res = await fetch("{{ route('kpi.live') }}", { 
                    cache: "no-store",
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                
                if (res.ok) {
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        // Evaluamos SNMP basándonos en dispositivos que SÍ devolvieron métricas de CPU/RAM reales
                        if (data.snmp_online !== undefined) {
                            snmpIsWorking = (data.snmp_online > 0);
                        } else if (data.dispositivos && typeof data.dispositivos.online !== 'undefined') {
                            snmpIsWorking = (data.dispositivos.online > 0);
                        } else if (data.total !== undefined) {
                            snmpIsWorking = (data.total > 0);
                        }
                    } catch (parseErr) {
                        // Si recibimos HTML (ej. redirección al login), ignoramos el chequeo
                        console.warn("Monitor SNMP: Respuesta no JSON (posible timeout de sesión)");
                    }
                }
            } catch (e) {
                // Si la red falla intermitentemente, no ponemos el icono rojo de inmediato
                console.warn("Monitor SNMP: Fallo de red temporal", e);
            }
            updateLiveBadgeStatus();
        }

        function updateLiveBadgeStatus() {
            const anim = document.getElementById('liveWifiAnim');
            if (!anim) return;

            if (!navigator.onLine) {
                // 1. Estado OFFLINE LOCAL (Página sin conexión) -> Robot Caído (404)
                if (anim.getAttribute('src') !== animOffline) {
                    anim.setAttribute('src', animOffline);
                }
                anim.style.filter = 'none'; // El robot tiene sus propios colores
            } else {
                // 2. Estado ONLINE LOCAL -> Señal Wi-Fi
                if (anim.getAttribute('src') !== animOnline) {
                    anim.setAttribute('src', animOnline);
                }
                
                if (snmpIsWorking) {
                    // 2a. SNMP Funcionando (Azul original)
                    anim.style.filter = 'none';
                } else {
                    // 2b. SNMP Fallando / Sin señal (Filtro Rojo)
                    anim.style.filter = 'grayscale(1) sepia(1) hue-rotate(320deg) saturate(5)';
                }
            }
        }

        window.addEventListener('online', () => { checkSNMPHealth(); updateLiveBadgeStatus(); });
        window.addEventListener('offline', updateLiveBadgeStatus);
        
        // Inicializar estado y arrancar monitor en segundo plano cada 10 segundos
        document.addEventListener('DOMContentLoaded', () => {
            updateLiveBadgeStatus();
            checkSNMPHealth();
            setInterval(checkSNMPHealth, 10000);
        });
    </script>
@endsection
