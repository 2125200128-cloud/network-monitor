@extends('layouts.app')

@section('title', 'Topología y Conexiones de Red')

@section('styles')
    <style>
        /* Fondo de tono medio: contrasta con los chasis negros (Catalyst, Nexus, FortiGate) y con los
           grises claros (Huawei, ISR) a la vez; con el blanco o el negro casi puro uno de los dos se perdía. */
        .blueprint-canvas {
            background-color: #e6ebf2;
            background-image:
                radial-gradient(ellipse at 50% 35%, rgba(255, 255, 255, 0.55), transparent 70%),
                linear-gradient(rgba(100, 116, 139, 0.13) 1px, transparent 1px),
                linear-gradient(90deg, rgba(100, 116, 139, 0.13) 1px, transparent 1px);
            background-size: 100% 100%, 28px 28px, 28px 28px;
        }

        .ease-custom {
            transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Botones de la barra de herramientas (sistema de diseño): superficie, borde fino y activo metálico.
           Se ocultan los adornos animados heredados (círculo expansivo y flechas deslizantes). */
        .uiverse-btn, .uiverse-btn-sm {
            position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            padding: 0.45rem 0.85rem; font-size: 0.78rem; font-weight: 600; white-space: nowrap; user-select: none;
            color: var(--nm-text); background: var(--nm-surface); border: 1px solid var(--nm-border-strong);
            border-radius: var(--nm-btn-radius); box-shadow: var(--nm-shadow-sm); overflow: hidden;
            transition: background-color var(--nm-dur-fast) ease, border-color var(--nm-dur-fast) ease, color var(--nm-dur-fast) ease, box-shadow var(--nm-dur-fast) ease;
            pointer-events: auto !important;
        }
        .uiverse-btn-sm { padding: 0.3rem 0.65rem; font-size: 0.74rem; }
        .uiverse-btn > span.absolute, .uiverse-btn-sm > span.absolute,
        .uiverse-btn > svg:first-child, .uiverse-btn-sm > svg:first-child { display: none !important; }
        .uiverse-btn > svg, .uiverse-btn-sm > svg {
            position: static !important; order: 2; width: 0.95rem; height: 0.95rem; fill: none !important; stroke: currentColor; opacity: 0.75;
        }
        .uiverse-btn > span, .uiverse-btn-sm > span { transform: none !important; }
        .uiverse-btn:hover, .uiverse-btn-sm:hover {
            color: var(--nm-azure-700); background: var(--nm-azure-50); border-color: var(--nm-azure-300);
        }
        .uiverse-btn.active, .uiverse-btn-sm.active {
            color: #fff !important; background: var(--nm-metal-sheen), var(--nm-metal-azure) !important;
            border-color: transparent !important; box-shadow: 0 2px 8px -2px rgb(var(--nm-primary-rgb) / 0.5) !important;
        }
        .uiverse-btn.active > svg, .uiverse-btn-sm.active > svg { opacity: 1; }
        .dark .uiverse-btn:hover, .dark .uiverse-btn-sm:hover {
            color: var(--nm-azure-200); background: rgb(var(--nm-primary-rgb) / 0.14); border-color: rgb(var(--nm-primary-rgb) / 0.45);
        }

        /* Barra de herramientas del mapa (sistema de diseño) */
        .nm-toolbar { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem; border-radius: var(--nm-radius-lg); box-shadow: var(--nm-shadow-md); max-width: 100%; overflow-x: auto; scrollbar-width: none; }
        .nm-toolbar::-webkit-scrollbar { display: none; }
        .nm-toolbar-sep { width: 1px; height: 1.5rem; background: var(--nm-border-strong); flex-shrink: 0; }
        .nm-tool {
            position: relative; display: inline-flex; align-items: center; gap: 0.4rem; height: 2.25rem; padding: 0 0.7rem;
            font-size: 0.8rem; font-weight: 600; white-space: nowrap; color: var(--nm-text-muted);
            background: transparent; border: 1px solid transparent; border-radius: var(--nm-radius-sm);
            transition: background-color var(--nm-dur-fast) ease, color var(--nm-dur-fast) ease, border-color var(--nm-dur-fast) ease;
        }
        .nm-tool:hover { color: var(--nm-text); background: rgb(var(--nm-primary-rgb) / 0.08); }
        /* Segmentado: una sola opción activa, rellena */
        .nm-seg { display: inline-flex; gap: 2px; padding: 2px; border-radius: calc(var(--nm-radius-sm) + 2px); background: var(--nm-sunken); border: 1px solid var(--nm-border); }
        .nm-seg .nm-tool { height: 2rem; }
        .nm-seg .nm-tool.active { color: #fff; background: var(--nm-metal-sheen), var(--nm-metal-azure); box-shadow: 0 1px 4px -1px rgb(var(--nm-primary-rgb) / 0.6); }
        /* Interruptor: encendido = tinte azul + punto indicador */
        .nm-toggle.active { color: var(--nm-azure-700); background: rgb(var(--nm-primary-rgb) / 0.1); border-color: rgb(var(--nm-primary-rgb) / 0.3); }
        .dark .nm-toggle.active { color: var(--nm-azure-200); background: rgb(var(--nm-primary-rgb) / 0.18); }
        .nm-toggle::after { content: ''; width: 6px; height: 6px; border-radius: 9999px; background: var(--nm-border-strong); margin-left: 0.1rem; transition: background-color var(--nm-dur-fast) ease; }
        .nm-toggle.active::after { background: var(--nm-success); box-shadow: 0 0 0 3px rgb(16 185 129 / 0.18); }
        /* En pantallas angostas solo iconos: el texto se oculta visualmente pero sigue siendo el nombre accesible */
        @media (max-width: 1280px) { .nm-toolbar .nm-tool > span { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; } .nm-toolbar .nm-tool { padding: 0 0.55rem; } }

        /* El selector Jalisco/ZMG no tiene icono: su texto nunca se oculta */
        @media (max-width: 1280px) { #segVistaMapa .nm-tool > span { position: static; width: auto; height: auto; overflow: visible; clip: auto; } #segVistaMapa .nm-tool { padding: 0 0.6rem; } }
        /* En pantallas angostas el recuadro de la ZMG taparía el mapa: se queda solo el fondo */
        @media (max-width: 767px) { #recuadroMapa, #panelSitios { display: none !important; } }

        .interactive-bar-island {
            pointer-events: auto !important;
        }

        .filter-pill-btn {
            pointer-events: auto !important;
            cursor: pointer;
        }

        /* KPIs del encabezado */
        .noc-kpi { display: flex; flex-direction: column; gap: 2px; padding: 6px 12px; border-radius: var(--nm-radius-md); background: var(--nm-sunken); border: 1px solid var(--nm-border); min-width: 96px; }
        
        .noc-kpi-title { font-size: 11px; line-height: 1; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--nm-text-muted); }
        .noc-kpi-row { display: flex; align-items: center; gap: 10px; }
        .noc-kpi-item { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; color: var(--nm-text); }
        .noc-kpi-item b { font-family: ui-monospace, Consolas, monospace; font-weight: 800; }
        .noc-dot { display: inline-block; width: 8px; height: 8px; border-radius: 9999px; }
        .noc-stale { color: #dc2626 !important; }

        /* Barra de edición de zonas */
        #zonaEditBar { display: none; }
        body.zonas-editando #zonaEditBar { display: flex; }
        body.zonas-editando #networkTopologyCanvas { cursor: crosshair; }

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
<div class="flex flex-col h-full space-y-3">
    


    {{-- ==================== 1. ENCABEZADO COMPACTO: título + resumen en una fila ==================== --}}
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 bg-white dark:bg-nm-surface rounded-xl px-4 py-2 shadow-sm border border-gray-100 dark:border-slate-800 flex-shrink-0">
        <div class="flex items-center gap-2.5">
            <h1 class="text-base font-bold text-gray-900 dark:text-white">Topología</h1>
            @include('topologia.partials.pestanas', ['activa' => 'red'])
            <span id="liveStatusDot" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400" title="Estado del monitoreo en vivo">
                <i class="w-2 h-2 rounded-full bg-slate-400"></i><span>En vivo</span>
            </span>
        </div>

        {{-- Resumen real: equipos y enlaces por estado + frescura del dato --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px]">
            <div class="flex items-center gap-2">
                <span class="noc-kpi-title">Equipos</span>
                <span class="noc-kpi-item" title="En línea"><i class="noc-dot" style="background:#10b981"></i><b id="kpiDispOnline">{{ $resumen['disp_online'] }}</b></span>
                <span class="noc-kpi-item" title="Advertencia (umbrales de CPU / pérdida)"><i class="noc-dot" style="background:#f59e0b"></i><b id="kpiDispWarning">{{ $resumen['disp_warning'] }}</b></span>
                <span class="noc-kpi-item" title="Sin respuesta (ICMP y SNMP)"><i class="noc-dot" style="background:#ef4444"></i><b id="kpiDispOffline">{{ $resumen['disp_offline'] }}</b></span>
            </div>
            <span class="w-px h-4 bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="noc-kpi-title">Enlaces</span>
                <span class="noc-kpi-item" title="UP (puerto operativo)"><i class="noc-dot" style="background:#10b981"></i><b id="kpiEnlUp">{{ $resumen['enl_up'] }}</b></span>
                <span class="noc-kpi-item" title="Degradado / utilización ≥ 80 %"><i class="noc-dot" style="background:#f59e0b"></i><b id="kpiEnlDegraded">{{ $resumen['enl_degraded'] }}</b></span>
                <span class="noc-kpi-item" title="DOWN"><i class="noc-dot" style="background:#ef4444"></i><b id="kpiEnlDown">{{ $resumen['enl_down'] }}</b></span>
                <span class="noc-kpi-item" title="Sin dato de los puertos"><i class="noc-dot" style="background:#94a3b8"></i><b id="kpiEnlUnknown">{{ $resumen['enl_unknown'] }}</b></span>
            </div>
            <span class="w-px h-4 bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
            <span id="kpiFrescura" class="noc-kpi-item font-mono text-slate-500 dark:text-slate-400" title="Último sondeo" data-ts="{{ $resumen['ultimo_sondeo'] }}">—</span>
        </div>
    </div>

    {{-- ==================== 2. CANVAS & INTERACTIVE WORKSPACE ==================== --}}
    <div class="relative flex-1 rounded-2xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-800 blueprint-canvas flex flex-col min-h-[600px]">
        
        {{-- Unified Floating Top Bar (Organizada en dos grupos lógicos con separadores) --}}
        <div class="absolute z-10 flex flex-wrap items-center justify-between gap-3 pointer-events-none" style="top: 1rem; left: 1rem; right: 1rem;">
            
            {{-- Barra de herramientas: disposición (segmentado), capas (interruptores) y acciones --}}
            <div class="nm-toolbar nm-glass interactive-bar-island" role="toolbar" aria-label="Herramientas del mapa" style="pointer-events: auto;">

                <div class="nm-seg" role="group" aria-label="Disposición del mapa">
                    <button type="button" id="btnLayoutTree" onclick="setLayout('tree')" class="nm-tool" title="Ordenar en árbol desde el núcleo, sin encimar equipos (se guarda para todos)">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="16" y="16" width="6" height="6" rx="1" /> <rect x="2" y="16" width="6" height="6" rx="1" /> <rect x="9" y="2" width="6" height="6" rx="1" /> <path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3" /> <path d="M12 12V8" /></svg><span>Jerárquico</span>
                    </button>
                    <button type="button" id="btnLayoutFree" onclick="setLayout('free')" class="nm-tool active" title="Disposición orgánica libre">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4.5" r="2.5" /> <path d="m10.2 6.3-3.9 3.9" /> <circle cx="4.5" cy="12" r="2.5" /> <path d="M7 12h10" /> <circle cx="19.5" cy="12" r="2.5" /> <path d="m13.8 17.7 3.9-3.9" /> <circle cx="12" cy="19.5" r="2.5" /></svg><span>Libre</span>
                    </button>
                </div>

                <div class="nm-toolbar-sep" aria-hidden="true"></div>

                <div class="flex items-center gap-1" role="group" aria-label="Capas del mapa">
                    <button type="button" id="btnTogglePhysics" onclick="togglePhysics()" class="nm-tool nm-toggle" title="Activa o pausa la simulación de fuerzas que acomoda los nodos">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1" /> <path d="M20.2 20.2c2.04-2.03.02-7.36-4.5-11.9-4.54-4.52-9.87-6.54-11.9-4.5-2.04 2.03-.02 7.36 4.5 11.9 4.54 4.52 9.87 6.54 11.9 4.5Z" /> <path d="M15.7 15.7c4.52-4.54 6.54-9.87 4.5-11.9-2.03-2.04-7.36-.02-11.9 4.5-4.52 4.54-6.54 9.87-4.5 11.9 2.03 2.04 7.36.02 11.9-4.5Z" /></svg><span id="physicsStatusLabel">Física</span>
                    </button>
                    <button type="button" id="btnToggleBeams" onclick="toggleTrafficBeams()" class="nm-tool nm-toggle active" title="Muestra el tráfico en vivo como haces animados sobre los enlaces">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" /></svg><span id="beamsStatusLabel">Tráfico animado</span>
                    </button>
                    <button type="button" id="btnToggleEndpoints" onclick="toggleEndpoints()" class="nm-tool nm-toggle active" title="Agrupa teléfonos y PCs dentro de su switch de acceso">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5c0-1.1.9-2 2-2h2" /> <path d="M17 3h2c1.1 0 2 .9 2 2v2" /> <path d="M21 17v2c0 1.1-.9 2-2 2h-2" /> <path d="M7 21H5c-1.1 0-2-.9-2-2v-2" /> <rect width="7" height="5" x="7" y="7" rx="1" /> <rect width="7" height="5" x="10" y="12" rx="1" /></svg><span id="endpointsStatusLabel">Agrupar finales</span>
                    </button>
                    <button type="button" id="btnMapaJalisco" onclick="toggleMapaJalisco()" class="nm-tool nm-toggle active" title="Muestra de fondo el mapa municipal de Jalisco con sus cabeceras y la Zona Metropolitana de Guadalajara">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z" /> <path d="M15 5.764v15" /> <path d="M9 3.236v15" /></svg><span>Mapa de Jalisco</span>
                    </button>
                    <div id="segVistaMapa" class="nm-seg" role="group" aria-label="Mapa en grande">
                        <button type="button" data-vista="jalisco" onclick="cambiarVistaMapa('jalisco')" class="nm-tool" title="Ver en grande el estado de Jalisco"><span>Jalisco</span></button>
                        <button type="button" data-vista="zmg" onclick="cambiarVistaMapa('zmg')" class="nm-tool" title="Ver en grande la Zona Metropolitana de Guadalajara"><span>ZMG</span></button>
                    </div>
                    @if($puedeEditar)
                    <button type="button" id="btnZonas" onclick="toggleZonaEdicion()" class="nm-tool nm-toggle" title="Crear, mover y redimensionar zonas (edificios, datacenters, sitios)">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3a2 2 0 0 0-2 2" /> <path d="M19 3a2 2 0 0 1 2 2" /> <path d="M21 19a2 2 0 0 1-2 2" /> <path d="M5 21a2 2 0 0 1-2-2" /> <path d="M9 3h1" /> <path d="M9 21h1" /> <path d="M14 3h1" /> <path d="M14 21h1" /> <path d="M3 9v1" /> <path d="M21 9v1" /> <path d="M3 14v1" /> <path d="M21 14v1" /></svg><span>Zonas</span>
                    </button>
                    @endif
                </div>

                <div class="nm-toolbar-sep" aria-hidden="true"></div>

                <div class="flex items-center gap-1" role="group" aria-label="Acciones">
                    <button type="button" onclick="resetZoom()" class="nm-tool" title="Centrar y encuadrar la vista completa">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2" /> <path d="M17 3h2a2 2 0 0 1 2 2v2" /> <path d="M21 17v2a2 2 0 0 1-2 2h-2" /> <path d="M7 21H5a2 2 0 0 1-2-2v-2" /></svg><span>Ver todo</span>
                    </button>
                    <button type="button" onclick="vistaLegible()" class="nm-tool" title="Acercar al switch core de Configuración con los equipos a tamaño legible">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /><path d="M11 8v6M8 11h6" /></svg><span>Núcleo</span>
                    </button>
                    <button type="button" onclick="openDiscoveryModal()" class="nm-tool" title="Auto-descubrimiento por SNMP (CDP/LLDP)">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19.07 4.93A10 10 0 0 0 6.99 3.34" /> <path d="M4 6h.01" /> <path d="M2.29 9.62A10 10 0 1 0 21.31 8.35" /> <path d="M16.24 7.76A6 6 0 1 0 8.23 16.67" /> <path d="M12 18h.01" /> <path d="M17.99 11.66A6 6 0 0 1 15.77 16.67" /> <circle cx="12" cy="12" r="2" /> <path d="m13.41 10.59 5.66-5.66" /></svg><span>Descubrir</span>
                    </button>
                    <button type="button" onclick="exportTopologyImage()" class="nm-tool" title="Exportar la topología como imagen PNG en alta definición">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10l-3.1-3.1a2 2 0 0 0-2.814.014L6 21" /> <path d="m14 19 3 3v-5.5" /> <path d="m17 22 3-3" /> <circle cx="9" cy="9" r="2" /></svg><span>Exportar PNG</span>
                    </button>
                </div>
            </div>

            <script>
                // aria-pressed refleja el estado "active" que manejan las funciones del mapa
                (function () {
                    const botones = document.querySelectorAll('.nm-toolbar .nm-toggle, .nm-toolbar .nm-seg .nm-tool');
                    const sincronizar = (b) => b.setAttribute('aria-pressed', b.classList.contains('active') ? 'true' : 'false');
                    const obs = new MutationObserver((cambios) => cambios.forEach((c) => sincronizar(c.target)));
                    botones.forEach((b) => { sincronizar(b); obs.observe(b, { attributes: true, attributeFilter: ['class'] }); });
                })();
            </script>

            {{-- Right Island: Search & Category / Edge Filter Pills (Colapsable y estilo Uiverse Compacto) --}}
            <div id="rightFilterBarContainer" class="flex items-center transition-all duration-300 pointer-events-auto" style="margin-left: auto;">
                
                {{-- Collapsed State: Botón flotante compacto para expandir --}}
                <div id="rightFilterBarCollapsed" class="hidden">
                    <button type="button" onclick="toggleRightFilterBar()" class="nm-tool nm-glass shadow-md" title="Mostrar búsqueda y filtros de red" aria-expanded="false">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="14" y1="4" y2="4" /> <line x1="10" x2="3" y1="4" y2="4" /> <line x1="21" x2="12" y1="12" y2="12" /> <line x1="8" x2="3" y1="12" y2="12" /> <line x1="21" x2="16" y1="20" y2="20" /> <line x1="12" x2="3" y1="20" y2="20" /> <line x1="14" x2="14" y1="2" y2="6" /> <line x1="8" x2="8" y1="10" y2="14" /> <line x1="16" x2="16" y1="18" y2="22" /></svg><span>Filtros y búsqueda</span>
                    </button>
                </div>

                {{-- Expanded State: Barra Completa con Botones Uiverse Compactos --}}
                <div id="rightFilterBarExpanded" class="flex flex-wrap items-center gap-1.5 nm-glass p-1.5 rounded-xl shadow-md interactive-bar-island">
                    
                    {{-- Quick Device Search Input --}}
                    <div class="relative flex items-center">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 pointer-events-none" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></svg>
                        <input type="text" id="topoSearchInput" oninput="searchTopologyDevice(this.value)" placeholder="Buscar IP, router, switch..." class="w-32 sm:w-40 pl-8 pr-2 py-1 text-[11px] bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700/60 rounded-xl text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition font-medium">
                    </div>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- Device Category Filter Buttons (Estilo Uiverse Pequeño) --}}
                    <button type="button" onclick="filterDeviceCategory('all', this)" class="uiverse-btn-sm group device-filter-btn active" title="Mostrar todos los nodos" aria-label="Mostrar todos los nodos">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Todos</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('router', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver y centrar en Routers WAN" aria-label="Ver y centrar en Routers WAN">
                        <img src="{{ asset('images/topology/router.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="Routers">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Routers</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('switch', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver y centrar en Switches" aria-label="Ver y centrar en Switches">
                        <svg class="w-3.5 h-3.5 relative z-[1]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2" /> <rect width="20" height="8" x="2" y="14" rx="2" ry="2" /> <line x1="6" x2="6.01" y1="6" y2="6" /> <line x1="6" x2="6.01" y1="18" y2="18" /></svg>
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Switches</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('servidor', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver Servidores Enterprise" aria-label="Ver Servidores Enterprise">
                        <img src="{{ asset('images/topology/server.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="Servidores">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Servidores</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('access_point', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver y centrar en Access Points" aria-label="Ver y centrar en Access Points">
                        <img src="{{ asset('images/topology/access-point.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="APs">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">APs</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterDeviceCategory('phone', this)" class="uiverse-btn-sm group device-filter-btn" title="Ver Teléfonos IP" aria-label="Ver Teléfonos IP">
                        <img src="{{ asset('images/topology/ip-phone.png') }}" class="w-3.5 h-3.5 object-contain relative z-[1]" alt="VoIP">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">VoIP</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[140px] group-hover:h-[140px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- Enlaces Filter Buttons (Estilo Uiverse Pequeño) --}}
                    <button type="button" onclick="filterEdges('all', this)" class="uiverse-btn-sm group filter-pill-btn active" title="Mostrar todos los enlaces" aria-label="Mostrar todos los enlaces">
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Enlaces ({{ $totalEnlaces }})</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[160px] group-hover:h-[160px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterEdges('up', this)" class="uiverse-btn-sm group filter-pill-btn" title="Filtrar enlaces UP" aria-label="Filtrar enlaces UP">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 relative z-[1]"></span>
                        <span class="relative z-[1] transition-all duration-500 ease-custom">UP</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[120px] group-hover:h-[120px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <button type="button" onclick="filterEdges('down', this)" class="uiverse-btn-sm group filter-pill-btn" title="Filtrar enlaces en Alerta" aria-label="Filtrar enlaces en Alerta">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 relative z-[1]"></span>
                        <span class="relative z-[1] transition-all duration-500 ease-custom">Alertas</span>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[120px] group-hover:h-[120px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700/60 mx-0.5"></div>

                    {{-- Collapse Toggle Button (Ocultar Barra) --}}
                    <button type="button" onclick="toggleRightFilterBar()" class="uiverse-btn-sm group px-2 text-slate-400 hover:text-slate-900 dark:hover:text-slate-100" title="Ocultar barra de filtros" aria-label="Ocultar barra de filtros">
                        <svg class="w-3.5 h-3.5 relative z-[1] transition-transform duration-300" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 transition-all duration-500 ease-custom group-hover:w-[100px] group-hover:h-[100px] group-hover:opacity-100 pointer-events-none"></span>
                    </button>
                </div>

            </div>
        </div>

        {{-- Main Vis.js Graph Canvas --}}
        <div id="networkTopologyCanvas" class="w-full h-full flex-1 cursor-grab active:cursor-grabbing"></div>

        {{-- Sitios de la ZMG: filtros por categoría (solo con la ZMG en grande) --}}
        <div id="panelSitios" class="hidden absolute z-10 right-4 bottom-4 w-[230px] nm-glass rounded-xl shadow-lg p-3 pointer-events-auto text-xs">
            <div class="flex items-center justify-between mb-2">
                <p class="font-bold text-slate-800 dark:text-slate-100">Sitios en la ZMG</p>
                <span id="panelSitiosTotal" class="text-[11px] text-slate-500 dark:text-slate-400"></span>
            </div>
            <div id="panelSitiosLista" class="flex flex-col gap-1"></div>
            <p class="mt-2 pt-2 border-t border-[var(--nm-border)] text-[11px] leading-snug text-slate-500 dark:text-slate-400">
                Ubicaciones: © colaboradores de <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline hover:text-slate-800 dark:hover:text-white">OpenStreetMap</a> (ODbL). Son candidatos: verifica antes de asignar.
            </p>
        </div>

        {{-- Ficha de un sitio: datos y equipos asignados --}}
        <div id="fichaSitio" class="hidden absolute z-20 w-[320px] nm-glass rounded-xl shadow-2xl p-3.5 pointer-events-auto text-xs" role="dialog" aria-labelledby="fichaSitioNombre">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <span id="fichaSitioCategoria" class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide"></span>
                    <p id="fichaSitioNombre" class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white leading-snug"></p>
                    <p id="fichaSitioDireccion" class="mt-0.5 text-slate-500 dark:text-slate-400"></p>
                </div>
                <button type="button" onclick="cerrarFichaSitio()" class="w-7 h-7 -mr-1 -mt-1 shrink-0 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-slate-500/10 text-lg leading-none" aria-label="Cerrar ficha del sitio">&times;</button>
            </div>
            <p class="mt-3 mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Equipos asignados</p>
            <ul id="fichaSitioEquipos" class="flex flex-col gap-1 max-h-40 overflow-y-auto custom-scrollbar"></ul>
            @if($puedeEditar)
            <form id="fichaSitioForm" class="mt-2.5 flex gap-1.5" onsubmit="event.preventDefault(); asignarEquipoASitio();">
                <label for="fichaSitioBuscar" class="sr-only">Equipo a asignar</label>
                <input id="fichaSitioBuscar" list="listaEquiposSitio" autocomplete="off" placeholder="Buscar equipo por nombre o IP…"
                       class="flex-1 min-w-0 px-2.5 py-1.5 rounded-lg border border-[var(--nm-border-strong)] bg-[var(--nm-surface)] text-slate-800 dark:text-slate-100">
                <datalist id="listaEquiposSitio"></datalist>
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold">Asignar</button>
            </form>
            <p id="fichaSitioAviso" class="hidden mt-1.5 text-[11px]"></p>
            @endif
            <p class="mt-2.5 text-[11px] text-slate-400" id="fichaSitioFuente"></p>
        </div>

        {{-- Recuadro del mapa: muestra la vista que no está en grande; al hacer clic se intercambian --}}
        <div id="recuadroMapa" class="hidden absolute z-10 left-4 bottom-4 w-[300px] nm-glass rounded-xl shadow-lg p-3 pointer-events-auto">
            <div class="flex items-start justify-between gap-2 mb-1.5">
                <div>
                    <p id="recuadroMapaTitulo" class="text-xs font-bold text-slate-800 dark:text-slate-100 leading-tight">Zona Metropolitana de Guadalajara</p>
                    <p id="recuadroMapaDetalle" class="text-[11px] text-slate-500 dark:text-slate-400">10 municipios · clic para verla en grande</p>
                </div>
                <button type="button" onclick="cerrarRecuadroMapa()" class="w-7 h-7 -mr-1 -mt-1 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-slate-500/10 text-lg leading-none" aria-label="Ocultar recuadro del mapa">&times;</button>
            </div>
            <button type="button" id="recuadroMapaSvg" onclick="cambiarVistaMapa(mapaJalisco.vista === 'zmg' ? 'jalisco' : 'zmg')" class="block w-full rounded-lg overflow-hidden bg-[var(--nm-sunken)] border border-[var(--nm-border)] hover:border-[#ff8300] transition-colors" aria-label="Ver en grande el mapa de la ZMG"></button>
            <div class="flex items-center justify-between mt-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#ff8300]"></span>Cabecera municipal</span>
                <span>Fuente: INEGI</span>
            </div>
        </div>
        </div>

        {{-- Barra del modo edición de zonas --}}
        <div id="zonaEditBar" class="absolute z-10 left-4 right-4 bottom-4 flex-col gap-2 px-4 py-3 rounded-2xl bg-slate-900/95 text-white text-xs shadow-xl border border-blue-500/60">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-blue-300 mr-1">Modo zonas</span>
                <button type="button" onclick="acomodarPorIps()" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 font-bold" title="Coloca dentro de cada zona, en cuadrícula, los equipos cuya IP coincide con sus rangos" aria-label="Coloca dentro de cada zona, en cuadrícula, los equipos cuya IP coincide con sus rangos">Acomodar por IPs</button>
                <button type="button" onclick="crearZonasPorSubred()" class="px-3 py-1 rounded-lg bg-slate-700 hover:bg-slate-600 font-bold" title="Crea una zona por cada subred /24 con equipos y los acomoda" aria-label="Crea una zona por cada subred /24 con equipos y los acomoda">Zonas por subred /24</button>
                <span class="text-slate-400 hidden lg:inline ml-2">Arrastra en vacío para dibujar · arrastra el título para mover · esquina para redimensionar · ✎ editar · 🗑 borrar</span>
                <button type="button" onclick="toggleZonaEdicion(false)" class="ml-auto px-3 py-1 rounded-lg bg-blue-600 hover:bg-blue-500 font-bold">Listo</button>
            </div>
            <div id="zonaLista" class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto"></div>
        </div>

        {{-- Qué hacer con las zonas al cambiar el acomodo automático --}}
        <div id="layoutZonasDialog" class="hidden absolute inset-0 z-40 flex items-center justify-center bg-slate-900/40">
            <div class="w-[26rem] max-w-[calc(100%-2rem)] p-5 rounded-2xl bg-white dark:bg-nm-surface shadow-2xl border border-slate-200 dark:border-slate-700 text-xs">
                <p class="text-sm font-black text-slate-800 dark:text-slate-100 mb-1">Hay <span id="layoutZonasCount"></span> zonas en el mapa</p>
                <p class="text-slate-500 dark:text-slate-400 mb-4">El acomodo <b id="layoutZonasNombre"></b> mueve todos los equipos. ¿Qué hacemos con las zonas?</p>
                <div class="space-y-2">
                    <button type="button" data-op="dentro" class="w-full text-left px-3 py-2.5 rounded-xl border-2 border-blue-500 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40">
                        <b class="text-slate-800 dark:text-slate-100">Respetar las zonas (recomendado)</b>
                        <span class="block text-slate-500 dark:text-slate-400">Los equipos de cada zona se reacomodan dentro de ella; el resto se acomoda debajo.</span>
                    </button>
                    <button type="button" data-op="seguir" class="w-full text-left px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
                        <b class="text-slate-800 dark:text-slate-100">Mover las zonas con sus equipos</b>
                        <span class="block text-slate-500 dark:text-slate-400">Se aplica el acomodo y cada zona se ajusta para rodear a sus equipos (pueden quedar grandes o encimadas).</span>
                    </button>
                    <button type="button" data-op="quitar" class="w-full text-left px-3 py-2.5 rounded-xl border border-red-300 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-950/40">
                        <b class="text-red-600">Quitar todas las zonas</b>
                        <span class="block text-slate-500 dark:text-slate-400">Se borran las zonas y se aplica el acomodo. Los equipos no se borran.</span>
                    </button>
                </div>
                <div class="flex justify-end mt-4">
                    <button type="button" data-op="" class="px-3 py-1.5 rounded-lg font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Cancelar</button>
                </div>
            </div>
        </div>

        {{-- Editor de zona (nombre / color / eliminar) --}}
        <div id="zonaEditor" class="hidden absolute z-30 w-72 p-4 rounded-2xl nm-glass bg-white dark:bg-nm-surface shadow-2xl border border-slate-200 dark:border-slate-700 text-xs" style="left: 50%; top: 30%; transform: translateX(-50%);">
            <p class="font-black text-slate-800 dark:text-slate-100 mb-2" id="zonaEditorTitulo">Zona</p>
            <label class="block text-slate-500 mb-1">Nombre</label>
            <input id="zonaEditorNombre" maxlength="80" class="w-full mb-3 px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100" placeholder="EDIFICIO 1">
            <label class="block text-slate-500 mb-1">Rangos de IP <span class="text-slate-400">(opcional, separados por coma)</span></label>
            <input id="zonaEditorRangos" maxlength="500" class="w-full mb-1 px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 font-mono" placeholder="10.4.155.0/24, 10.4.20.*, 10.4.1.10-10.4.1.50">
            <p id="zonaEditorRangosInfo" class="text-[11px] text-slate-400 mb-3"></p>
            <label class="block text-slate-500 mb-1">Color</label>
            <div id="zonaEditorColores" class="flex flex-wrap gap-1.5 mb-4"></div>
            <div class="flex items-center justify-between">
                <button type="button" id="zonaEditorEliminar" class="px-3 py-1.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 font-bold">Eliminar</button>
                <div class="flex gap-2">
                    <button type="button" onclick="cerrarZonaEditor()" class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold">Cancelar</button>
                    <button type="button" id="zonaEditorGuardar" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold">Guardar</button>
                </div>
            </div>
        </div>

        {{-- ==================== 3. INSPECTOR DRAWER (SIDE PANEL) ==================== --}}
        <div id="inspectorDrawer" class="absolute w-96 max-w-[calc(100%-2rem)] bg-white/95 dark:bg-nm-surface/95 rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-800 p-5 z-20 pointer-events-none" style="top: 4.5rem; right: 1rem; max-height: calc(100% - 5.5rem); overflow-y: auto;">
            
            {{-- Drawer Header with Close Button --}}
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-slate-800 mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-nm-primary"></span>
                    <span class="text-[11px] font-black uppercase text-gray-400 dark:text-slate-400 tracking-wider">Telemetría de Red</span>
                </div>
                <button type="button" onclick="closeDrawer()" class="w-7 h-7 rounded-full bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white flex items-center justify-center transition" title="Cerrar inspector" aria-label="Cerrar inspector">
                    <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                </button>
            </div>

            {{-- Device Inspector Container --}}
            <div id="drawerDeviceContent" class="space-y-4 hidden">
                <div class="flex items-start gap-3 pr-6">
                    <div id="drawerDeviceIcon" class="w-11 h-11 rounded-xl nm-metal-azure text-white flex items-center justify-center shadow-md flex-shrink-0">
                        <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2" /> <rect width="20" height="8" x="2" y="14" rx="2" ry="2" /> <line x1="6" x2="6.01" y1="6" y2="6" /> <line x1="6" x2="6.01" y1="18" y2="18" /></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span id="drawerDeviceRol" class="px-2 py-0.5 rounded-full text-[11px] font-black tracking-wider bg-blue-100 text-nm-primary">CORE</span>
                            <span id="drawerDeviceStatusBadge" class="px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700">ONLINE</span>
                        </div>
                        <h3 id="drawerDeviceNombre" class="text-sm font-extrabold text-gray-900 dark:text-white mt-1 leading-tight truncate">Switch Core Catalyst 9300</h3>
                        <p id="drawerDeviceIp" class="text-xs text-gray-500 dark:text-slate-400 font-mono mt-0.5">192.168.1.1</p>
                    </div>
                </div>

                {{-- Ficha Técnica Preview del Hardware --}}
                <div class="rounded-xl bg-gradient-to-br from-slate-900 via-nm-ink to-slate-950 p-2.5 border border-slate-800 shadow-inner flex flex-col items-center justify-center relative overflow-hidden group">
                    <div class="w-full flex items-center justify-between mb-1 px-1">
                        <span class="text-[11px] font-mono font-bold text-slate-400 uppercase tracking-wider">CHASIS FÍSICO</span>
                        <span id="drawerDeviceTipoEquipo" class="text-[11px] font-mono font-bold text-blue-400">Switch Gestionado</span>
                    </div>
                    <div class="w-full h-24 flex items-center justify-center p-2 bg-black/40 rounded-lg border border-slate-800/60">
                        <img id="drawerDeviceChassisImage" src="{{ asset('images/topology/switch-core.svg') }}" alt="Chasis" class="max-h-20 max-w-full object-contain drop-shadow-md transition-transform duration-300 group-hover:scale-105">
                    </div>
                    <div class="w-full flex items-center justify-between mt-1.5 px-1 text-[11px] text-slate-400 font-mono">
                        <span id="drawerDeviceFactorForma">1U Rackmount</span>
                        <span id="drawerDeviceVendor" class="text-slate-300 font-bold">Cisco Systems</span>
                    </div>
                </div>

                {{-- Gauges --}}
                <div class="grid grid-cols-3 gap-2 bg-gray-50 dark:bg-slate-900/80 p-3 rounded-xl border border-gray-100 dark:border-slate-800 text-center">
                    <div>
                        <p class="text-[11px] text-gray-400 font-bold uppercase">CPU</p>
                        <p id="drawerDeviceCpu" class="text-sm font-black text-gray-800 dark:text-slate-100 font-mono">15%</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-400 font-bold uppercase">Memoria</p>
                        <p id="drawerDeviceMem" class="text-sm font-black text-gray-800 dark:text-slate-100 font-mono">32%</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-400 font-bold uppercase">Ping</p>
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
                        <p class="text-[11px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Cables y Puertos Conectados</p>
                        <span id="drawerDeviceConnCount" class="text-[11px] font-mono font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded border border-transparent dark:border-slate-700">0 cables</span>
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
                            <p class="text-[11px] font-black text-indigo-900 dark:text-indigo-400 uppercase tracking-wider">Puerto PC (Pass-Through)</p>
                        </div>
                        <span id="drawerPhonePcStatusBadge" class="text-[11px] font-black px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">CONECTADA</span>
                    </div>

                    <div class="bg-gradient-to-br from-indigo-50/70 to-slate-50 dark:from-slate-900/90 dark:to-indigo-950/30 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/40 space-y-2 text-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2" /> <line x1="8" x2="16" y1="21" y2="21" /> <line x1="12" x2="12" y1="17" y2="21" /></svg>
                            </div>
                            <div class="truncate">
                                <h5 id="drawerPhonePcHostname" class="font-extrabold text-slate-800 dark:text-slate-100 leading-tight truncate">PC-DELL-OPTIPLEX</h5>
                                <p id="drawerPhonePcVendor" class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold truncate">Dell Inc. / Workstation</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1 border-t border-indigo-100/60 dark:border-slate-800 font-mono text-[11px]">
                            <div>
                                <span class="text-[11px] text-slate-400 block">IP de Datos:</span>
                                <span id="drawerPhonePcIp" class="font-bold text-blue-600 dark:text-blue-400">10.204.1.159</span>
                            </div>
                            <div>
                                <span class="text-[11px] text-slate-400 block">MAC (Switch CAM):</span>
                                <span id="drawerPhonePcMac" class="font-bold text-slate-800 dark:text-slate-200">24:7E:12:5A:21:18</span>
                            </div>
                            <div>
                                <span class="text-[11px] text-slate-400 block">Velocidad Enlace:</span>
                                <span id="drawerPhonePcSpeed" class="font-bold text-emerald-600 dark:text-emerald-400">1.0 Gbps (Full)</span>
                            </div>
                            <div>
                                <span class="text-[11px] text-slate-400 block">VLAN Tráfico:</span>
                                <span id="drawerPhonePcVlan" class="font-bold text-slate-700 dark:text-slate-300">VLAN Datos (1)</span>
                            </div>
                        </div>

                        <div class="pt-1 text-[11px] text-slate-400 font-mono flex items-center justify-between border-t border-indigo-100/40 dark:border-slate-800">
                            <span>Switch / Puerto:</span>
                            <span id="drawerPhonePcSwitchPort" class="text-slate-600 dark:text-slate-400 font-bold truncate max-w-[160px]">HMAG_IDF2A_B (Gi1/0/23)</span>
                        </div>
                    </div>
                </div>

                {{-- Tipo de equipo: de dónde sale y qué tan seguro es --}}
                <div class="border-t border-gray-100 dark:border-slate-800 pt-3">
                    <div class="flex items-center justify-between mb-1.5">
                        <p class="text-[11px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Tipo de equipo</p>
                        <span id="drawerTipoConfianza" class="text-[11px] font-black px-1.5 py-0.5 rounded"></span>
                    </div>
                    <ul id="drawerTipoEvidencia" class="text-[11px] text-slate-600 dark:text-slate-300 space-y-0.5 list-disc pl-4"></ul>
                    @if($puedeEditar)
                    <div class="flex items-center gap-2 mt-2">
                        <label for="drawerTipoManual" class="text-[11px] text-slate-500">Corregir:</label>
                        <select id="drawerTipoManual" onchange="corregirTipo(this.value)" class="flex-1 text-[11px] px-2 py-1 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100">
                            <option value="">Automático (por evidencia)</option>
                            @foreach(\App\Services\TipoDispositivoResolver::CATALOGO as $clave => $info)
                                <option value="{{ $clave }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>

                {{-- Equipos finales agrupados en este switch --}}
                <div id="drawerEndpointsSection" class="hidden border-t border-gray-100 dark:border-slate-800 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Equipos finales conectados</p>
                        <span id="drawerEndpointsCount" class="text-[11px] font-mono font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded"></span>
                    </div>
                    <div id="drawerEndpointsList" class="space-y-1 max-h-40 overflow-y-auto pr-1"></div>
                </div>

                {{-- Notas del equipo --}}
                <div class="border-t border-gray-100 dark:border-slate-800 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider">Notas del equipo</p>
                        <button type="button" id="btnNuevaNota" onclick="abrirFormNota()" class="hidden text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline">+ Agregar</button>
                    </div>
                    <div id="notaForm" class="hidden mb-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 space-y-2">
                        <input id="notaTitulo" maxlength="120" placeholder="Título (ej. Acceso local, Ubicación del rack)" class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100">
                        <textarea id="notaContenido" rows="3" maxlength="5000" placeholder="Contenido" class="w-full px-2 py-1.5 text-xs font-mono rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100"></textarea>
                        <label class="flex items-center gap-1.5 text-[11px] text-slate-600 dark:text-slate-300">
                            <input type="checkbox" id="notaSecreto"> Es secreto (contraseña): se oculta y solo Admin/Operador puede revelarla
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="cerrarFormNota()" class="px-2.5 py-1 text-[11px] font-bold rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800">Cancelar</button>
                            <button type="button" onclick="guardarNota()" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-blue-600 hover:bg-blue-500 text-white">Guardar</button>
                        </div>
                    </div>
                    <div id="notasLista" class="space-y-1.5"></div>
                </div>

                {{-- Action Links --}}
                <div class="pt-2 flex flex-col gap-2">
                    <a id="drawerDeviceLink" href="#" class="w-full py-2.5 px-3 rounded-xl bg-nm-primary hover:bg-[#505b61] text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm">
                        <span>Ver Ficha Técnica y Puertos</span>
                        <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14" /> <path d="m12 5 7 7-7 7" /></svg>
                    </a>
                    <a href="{{ route('consola.index') }}" class="w-full py-2 px-3 rounded-xl bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 text-xs font-bold transition flex items-center justify-center gap-1.5 border border-transparent dark:border-slate-700">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m7 11 2-2-2-2" /> <path d="M11 13h4" /> <rect width="18" height="18" x="3" y="3" rx="2" ry="2" /></svg>
                        <span>Abrir Consola CLI</span>
                    </a>
                </div>
            </div>

            {{-- Link / Cable Inspector Container --}}
            <div id="drawerLinkContent" class="space-y-4 hidden">
                <div class="flex items-start gap-3 pr-6">
                    <div class="w-11 h-11 rounded-xl bg-nm-primary text-white flex items-center justify-center shadow-md flex-shrink-0">
                        <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span id="drawerLinkTipo" class="px-2 py-0.5 rounded-full text-[11px] font-black tracking-wider bg-blue-100 dark:bg-blue-950/60 text-nm-primary dark:text-blue-400">FIBRA 10G SFP+</span>
                            <span id="drawerLinkStatusBadge" class="px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400">UP</span>
                        </div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mt-1 leading-tight">Enlace de Interconexión</h3>
                        <p id="drawerLinkVelocidad" class="text-xs text-gray-500 dark:text-slate-400 font-mono mt-0.5">10000 Mbps Negociados</p>
                    </div>
                </div>

                {{-- Endpoints Visual Box --}}
                <div class="bg-gray-50 dark:bg-nm-sunken p-3 rounded-xl border border-gray-100 dark:border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span class="font-bold text-gray-700 dark:text-slate-200 truncate max-w-[140px]" id="drawerLinkOrigenDisp">Switch Core</span>
                        </div>
                        <span class="font-mono text-[11px] font-bold bg-white dark:bg-slate-800 px-2 py-0.5 rounded border border-gray-200 dark:border-slate-700 text-gray-800 dark:text-slate-200" id="drawerLinkOrigenPuerto">Te1/0/24</span>
                    </div>

                    <div class="flex items-center justify-center text-gray-300 dark:text-slate-600">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14" /> <path d="m19 12-7 7-7-7" /></svg>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            <span class="font-bold text-gray-700 dark:text-slate-200 truncate max-w-[140px]" id="drawerLinkDestinoDisp">Router Edge</span>
                        </div>
                        <span class="font-mono text-[11px] font-bold bg-white dark:bg-slate-800 px-2 py-0.5 rounded border border-gray-200 dark:border-slate-700 text-gray-800 dark:text-slate-200" id="drawerLinkDestinoPuerto">Gi0/0/0</span>
                    </div>
                </div>

                {{-- Traffic Telemetry Meters --}}
                <div class="space-y-2 border-t border-gray-100 dark:border-slate-800 pt-3 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-slate-400">Tráfico Entrada (In):</span>
                        <span id="drawerLinkTraficoIn" class="font-mono font-bold text-emerald-600 dark:text-emerald-400">420.5 Mbps</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-slate-400">Tráfico Salida (Out):</span>
                        <span id="drawerLinkTraficoOut" class="font-mono font-bold text-blue-600 dark:text-blue-400">185.2 Mbps</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-slate-400">Saturación de Enlace:</span>
                        <span id="drawerLinkSaturacion" class="font-mono font-black text-gray-800 dark:text-slate-100">4.2%</span>
                    </div>
                </div>

                {{-- Allowed VLANs --}}
                <div class="border-t border-gray-100 dark:border-slate-800 pt-3">
                    <p class="text-[11px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wider mb-1.5">VLANs Transportadas (Trunk)</p>
                    <div id="drawerLinkVlans" class="flex flex-wrap gap-1">
                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 text-[11px] font-bold rounded">VLAN 1</span>
                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 text-[11px] font-bold rounded">VLAN 10</span>
                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 text-[11px] font-bold rounded">VLAN 20</span>
                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 text-[11px] font-bold rounded">VLAN 99</span>
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
        <p id="ttIp" class="text-[11px] text-cyan-400 font-mono mb-2">192.168.x.x</p>
        <div class="space-y-1 text-[11px]">
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
        
        <div class="bg-nm-ink border border-[#1e293b] rounded-2xl w-full max-w-md shadow-2xl shadow-cyan-900/20 overflow-hidden"
             @click.away="if(!isScanning) openScanner = false">
            
            <div class="p-5 border-b border-[#1e293b] flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-cyan-900/30 border border-cyan-800/50 flex items-center justify-center text-cyan-400">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /> <path d="M2 12h20" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-white">Rastreo Profundo L2/L3</h3>
                        <p class="text-[11px] text-slate-400">Motor Crawler Python Asíncrono</p>
                    </div>
                </div>
                <button type="button" @click="if(!isScanning) openScanner = false" class="text-slate-500 hover:text-white transition">
                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
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
        <div id="discoveryModalCard" class="nm-glass bg-white dark:bg-nm-surface rounded-2xl max-w-xl w-full p-6 sm:p-7 shadow-2xl border border-slate-100 dark:border-slate-800 transition-all duration-300 transform scale-95 flex flex-col space-y-5 max-h-[90vh] overflow-y-auto custom-scrollbar">
            
            {{-- Modal Header --}}
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                        <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white leading-tight">Auto-Descubrimiento de Red</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Crawler SNMPv2c · Mapeo autónomo CDP / LLDP y Barrido CIDR</p>
                    </div>
                </div>
                <button type="button" onclick="closeDiscoveryModal()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition">
                    <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                </button>
            </div>

            {{-- FORM STATE --}}
            <div id="discoveryFormState" class="space-y-4">
                {{-- Method selector tabs --}}
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Método de Descubrimiento</label>
                    <div class="grid grid-cols-2 gap-2 bg-slate-100 dark:bg-nm-sunken p-1 rounded-2xl border border-slate-200 dark:border-slate-800">
                        <button type="button" id="btnMethodCdp" onclick="setDiscoveryMethod('cdp_lldp')" class="py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-white dark:bg-slate-800 text-indigo-900 dark:text-indigo-300 shadow-sm border border-slate-200 dark:border-slate-700">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" /> <circle cx="12" cy="12" r="3" /></svg>
                            <span>Vecinos CDP / LLDP</span>
                        </button>
                        <button type="button" id="btnMethodCidr" onclick="setDiscoveryMethod('cidr_sweep')" class="py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.54 15H17a2 2 0 0 0-2 2v4.54" /> <path d="M7 3.34V5a3 3 0 0 0 3 3a2 2 0 0 1 2 2c0 1.1.9 2 2 2a2 2 0 0 0 2-2c0-1.1.9-2 2-2h3.17" /> <path d="M11 21.95V18a2 2 0 0 0-2-2a2 2 0 0 1-2-2v-1a2 2 0 0 0-2-2H2.05" /> <circle cx="12" cy="12" r="10" /></svg>
                            <span>Barrido Subred CIDR</span>
                        </button>
                    </div>
                </div>

                {{-- CDP/LLDP Seed IP Input --}}
                <div id="fieldSeedIpGroup">
                    <label for="discSeedIp" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">IP Semilla / Equipo Raíz (Seed IP)</label>
                    <div class="relative">
                        <input type="text" id="discSeedIp" value="{{ $config->ip_switch_core ?? '192.168.1.254' }}" class="w-full bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 dark:text-white focus:bg-white dark:focus:bg-nm-surface focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900/50 transition outline-none" placeholder="10.4.254.3">
                        <span class="absolute right-3 top-2.5 text-[11px] font-bold text-slate-400 uppercase">Core Switch</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">El crawler conectará a este switch y consultará recursivamente sus tablas de vecinos.</p>
                </div>

                {{-- CIDR Input (Hidden by default) --}}
                <div id="fieldCidrGroup" class="hidden">
                    <label for="discCidr" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Segmento de Red (CIDR)</label>
                    <input type="text" id="discCidr" value="10.4.254.0/24" class="w-full bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 dark:text-white focus:bg-white dark:focus:bg-nm-surface focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900/50 transition outline-none" placeholder="10.4.254.0/24">
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Se realizará un ping sweep paralelo en todo el segmento buscando switches gestionables.</p>
                </div>

                {{-- Common Parameters: Community & Max Depth --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="discCommunity" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Comunidad SNMP</label>
                        <input type="text" id="discCommunity" value="{{ $config->comunidad_snmp_default ?? 'public' }}" class="w-full bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 dark:text-white focus:bg-white dark:focus:bg-nm-surface focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900/50 transition outline-none" placeholder="public">
                    </div>
                    <div>
                        <label for="discMaxDepth" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Profundidad Máxima (Saltos)</label>
                        <select id="discMaxDepth" class="w-full bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-800 dark:text-white focus:bg-white dark:focus:bg-nm-surface focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900/50 transition outline-none">
                            <option value="1">1 salto (Solo vecinos directos)</option>
                            <option value="2">2 saltos (Distribución)</option>
                            <option value="3" selected>3 saltos (Recomendado)</option>
                            <option value="4">4 saltos (Toda la infraestructura)</option>
                            <option value="5">5 saltos (Campus / WAN)</option>
                        </select>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="closeDiscoveryModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Cancelar
                    </button>
                    <button type="button" id="btnSubmitDiscovery" onclick="startAutoDiscovery()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-md shadow-indigo-500/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3" /></svg>
                        <span>Iniciar Auto-Descubrimiento</span>
                    </button>
                </div>
            </div>

            {{-- SCANNING / IN-FLIGHT STATE --}}
            <div id="discoveryScanningState" class="hidden space-y-4 py-3">
                <div class="flex flex-col items-center justify-center text-center space-y-3">
                    <div class="relative w-40 h-40 flex items-center justify-center -my-4">
                        <script src="{{ asset('vendor/dotlottie-player/dotlottie-player.mjs') }}" type="module"></script>
                        <dotlottie-player
                            src="{{ asset('animaciones/topologia-carga.lottie') }}"
                            background="transparent" 
                            speed="1" 
                            style="width: 100%; height: 100%;" 
                            loop 
                            autoplay>
                        </dotlottie-player>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-800 dark:text-white">Rastreando Topología en Tiempo Real...</h4>
                        <p id="discoveryStatusText" class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Consultando tablas de adyacencia CDP y LLDP...</p>
                    </div>
                    <div class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-[11px] font-mono font-bold text-slate-600 dark:text-slate-300 border border-transparent dark:border-slate-700" id="discoveryTimer">
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
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-emerald-500/20">
                        <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-emerald-900 dark:text-emerald-300" id="discoveryResultTitle">¡Mapeo de Topología Finalizado!</h4>
                        <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium" id="discoveryResultMessage">Se ha sincronizado la red con éxito.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 rounded-2xl p-3">
                        <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Dispositivos Detectados</span>
                        <span class="text-xl font-black text-slate-800 dark:text-white font-mono" id="resTotalDevices">0</span>
                        <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 block mt-0.5" id="resNewDevices">(0 nuevos)</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 rounded-2xl p-3">
                        <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Enlaces Físicos</span>
                        <span class="text-xl font-black text-slate-800 dark:text-white font-mono" id="resTotalLinks">0</span>
                        <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 block mt-0.5" id="resNewLinks">(0 nuevos)</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeDiscoveryModal()" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 border border-transparent dark:border-slate-700 transition shadow-md">
                        Cerrar y Ver Lienzo
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ==================== 5. MODAL: ESCANEO POR CABLE RJ-45 ==================== --}}
<div id="localRj45Modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="nm-glass bg-white dark:bg-nm-surface rounded-3xl shadow-2xl border border-slate-100 dark:border-slate-800 max-w-lg w-full overflow-hidden transform transition-all scale-100">
        {{-- Modal Header --}}
        <div class="p-6 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 text-white relative">
            <button type="button" onclick="closeLocalRj45Modal()" class="absolute top-5 right-5 text-slate-400 hover:text-white transition p-1.5 rounded-full hover:bg-white/10">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
            </button>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400 shadow-inner">
                    <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
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
                    <label for="rj45SubnetInput" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                        Subred o IP a Escanear
                    </label>
                    <div class="relative">
                        <input type="text" id="rj45SubnetInput" value="10.4.155.0/24" class="w-full bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 dark:text-white focus:bg-white dark:focus:bg-nm-surface focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:focus:ring-emerald-900/50 transition outline-none" placeholder="ej. 10.4.155.0/24, 192.168.1.0/24, 10.4.155.2 o cualquier IP">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Escribe <strong>cualquier IP o segmento /24</strong> de tu red.</p>
                </div>

                {{-- Fast selector pills --}}
                <div class="space-y-1.5">
                    <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Accesos Rápidos a Redes Locales:</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="setRj45Target('10.4.155.0/24')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 transition">
                            <svg class="w-3.5 h-3.5 inline-block align-[-2px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h.01" /> <path d="M2 8.82a15 15 0 0 1 20 0" /> <path d="M5 12.859a10 10 0 0 1 14 0" /> <path d="M8.5 16.429a5 5 0 0 1 7 0" /></svg> Red Wi-Fi (10.4.155.0/24)
                        </button>
                        <button type="button" onclick="setRj45Target('192.168.1.0/24')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition">
                            <svg class="w-3.5 h-3.5 inline-block align-[-2px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a1 1 0 0 1-1-1v-1a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1a1 1 0 0 1-1 1" /> <path d="M19 15V6.5a1 1 0 0 0-7 0v11a1 1 0 0 1-7 0V9" /> <path d="M21 21v-2h-4" /> <path d="M3 5h4V3" /> <path d="M7 5a1 1 0 0 1 1 1v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a1 1 0 0 1 1-1V3" /></svg> Cable RJ-45 (192.168.1.0/24)
                        </button>
                        <button type="button" onclick="setRj45Target('')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 transition">
                            <svg class="w-3.5 h-3.5 inline-block align-[-2px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg> Autodetectar interfaz
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label for="rj45CommunityInput" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Comunidad SNMP</label>
                        <input type="text" id="rj45CommunityInput" value="public" class="w-full bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 dark:text-white focus:bg-white dark:focus:bg-nm-surface focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:focus:ring-emerald-900/50 transition outline-none" placeholder="public">
                    </div>
                    <div>
                        <label for="rj45ThreadsInput" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Hilos Paralelos</label>
                        <select id="rj45ThreadsInput" class="w-full bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-800 dark:text-white focus:bg-white dark:focus:bg-nm-surface focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:focus:ring-emerald-900/50 transition outline-none">
                            <option value="50" selected>50 hilos (Ultra Rápido)</option>
                            <option value="25">25 hilos (Moderado)</option>
                            <option value="10">10 hilos (Bajo tráfico)</option>
                        </select>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="closeLocalRj45Modal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Cancelar
                    </button>
                    <button type="button" onclick="executeLocalScan()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-md shadow-emerald-500/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
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
                        <h4 class="text-sm font-black text-slate-800 dark:text-white" id="rj45StatusTitle">Identificando Tarjeta de Red Física...</h4>
                        <p id="rj45StatusSubtitle" class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Analizando adaptadores de red y Gateway predeterminado...</p>
                    </div>
                    <div class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-[11px] font-mono font-bold text-slate-600 dark:text-slate-300 border border-transparent dark:border-slate-700" id="rj45Timer">
                        Tiempo transcurrido: 00:00s
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden border border-slate-200 dark:border-slate-700">
                    <div id="rj45ProgressBar" class="bg-gradient-to-r from-emerald-500 via-teal-500 to-indigo-600 h-2.5 rounded-full transition-all duration-300" style="width: 15%;"></div>
                </div>

                {{-- Telemetry details box --}}
                <div class="bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 text-xs text-slate-600 dark:text-slate-400 space-y-1.5 font-mono">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">NIC Detectada:</span>
                        <span id="rj45NicName" class="font-bold text-slate-800 dark:text-white">Detectando...</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">IP / Subred:</span>
                        <span id="rj45NicIp" class="font-bold text-slate-800 dark:text-white">Calculando...</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Gateway:</span>
                        <span id="rj45NicGw" class="font-bold text-slate-800 dark:text-white">Determinando...</span>
                    </div>
                </div>

                {{-- Live Log Box --}}
                <div class="bg-slate-950 rounded-2xl p-3 font-mono text-[11px] text-slate-300 max-h-36 overflow-y-auto custom-scrollbar border border-slate-800 space-y-1" id="rj45LogBox">
                    <p class="text-slate-500">// Iniciando worker/local_interface_scanner.py...</p>
                </div>
            </div>

            {{-- SUCCESS / RESULT STATE --}}
            <div id="rj45ResultState" class="hidden space-y-4 py-2">
                <div id="rj45BannerBox" class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 transition-colors duration-200">
                    <div id="rj45BannerIcon" class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-emerald-500/20">
                        <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-emerald-900 dark:text-emerald-300" id="rj45ResultTitle">¡Subred RJ-45 Mapeada con Éxito!</h4>
                        <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium" id="rj45ResultMessage">La topología física se ha sincronizado.</p>
                    </div>
                </div>

                {{-- Aviso de diagnóstico si no se detectaron equipos --}}
                <div id="rj45ZeroDevicesNotice" class="hidden bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/60 rounded-2xl p-3.5 text-xs text-amber-800 dark:text-amber-300 space-y-1.5">
                    <div class="flex items-center gap-1.5 font-bold text-amber-900 dark:text-amber-200">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" /></svg>
                        <span>Diagnóstico del enlace físico:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-[11px] text-amber-700 dark:text-amber-400 pl-1">
                        <li>El barrido ICMP/SNMP en los 254 hosts concluyó sin respuestas de red.</li>
                        <li>Verifica que el switch esté encendido y que el puerto RJ-45 muestre el LED en color verde.</li>
                        <li>Si el switch recién encendió, el protocolo Spanning Tree (STP) tarda entre 30 y 50 segundos en negociar el enlace.</li>
                        <li>Verifica si el switch tiene una IP configurada en el rango 192.168.1.x o en otra VLAN.</li>
                    </ul>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 rounded-2xl p-3">
                        <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Hosts con Respuesta</span>
                        <span class="text-xl font-black text-slate-800 dark:text-white font-mono" id="rj45ResHosts">0</span>
                        <span class="text-[11px] font-semibold text-teal-600 dark:text-teal-400 block mt-0.5">ICMP Ping /24</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 rounded-2xl p-3">
                        <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Equipos SNMP</span>
                        <span class="text-xl font-black text-slate-800 dark:text-white font-mono" id="rj45ResDevices">0</span>
                        <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 block mt-0.5" id="rj45ResNewDevices">(0 nuevos)</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-800 rounded-2xl p-3 col-span-2">
                        <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Enlaces Físicos CDP / LLDP</span>
                        <span class="text-xl font-black text-emerald-700 dark:text-emerald-400 font-mono" id="rj45ResLinks">0</span>
                        <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 block mt-0.5">Conexiones puerto a puerto registradas</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeLocalRj45Modal()" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 border border-transparent dark:border-slate-700 transition shadow-md hover:scale-[1.02] active:scale-[0.98]">
                        Ver Lienzo de Topología
                    </button>
                </div>
            </div>

            {{-- ERROR STATE --}}
            <div id="rj45ErrorState" class="hidden space-y-4 py-2">
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60">
                    <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-rose-500/20">
                        <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-rose-900 dark:text-rose-300">Error durante el Escaneo</h4>
                        <p class="text-xs text-rose-700 dark:text-rose-400 font-medium" id="rj45ErrorMessage">Ocurrió un error inesperado al rastrear la subred.</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeLocalRj45Modal()" class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-transparent dark:border-slate-700 transition">
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
        const PUEDE_EDITAR = @json($puedeEditar);
        const CSRF = '{{ csrf_token() }}';
        // Acomodo en zonas (unidades del lienzo): márgenes, espacio para el título y separación entre equipos
        const MARGEN = 160, TITULO = 380, SEP_X = 180, SEP_Y = 160;

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
            if (!network || !PUEDE_EDITAR) return; // el plano es compartido: solo Admin/Operador lo modifica
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

            // 0. Firewalls Fortinet / FortiGate (FortiGate, Fortinet, FG-, FW, FortiOS)
            if (
                haystack.includes('forti') || haystack.includes('fortigate') || haystack.includes('fortinet') ||
                haystack.includes('fortios') || nomLower.startsWith('fg-') || nomLower.startsWith('fw-') ||
                nomLower.startsWith('fg_') || nomLower.startsWith('fw_') || haystack.includes('firewall')
            ) {
                return {
                    image: '{{ asset("images/topology/firewall-fortinet.svg") }}',
                    rol: 'FIREWALL / FORTINET',
                    tipo_equipo: 'Fortinet FortiGate Next-Gen Firewall (NGFW)',
                    factor_forma: 'FortiGate Security Appliance',
                    size: 160
                };
            }

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
                    color: esInfra ? undefined : { background: '#667d93', border: '#667d93' },
                    font: fuenteEtiqueta(isDark),
                    customData: {
                        ...cData,
                        rol: esInfra ? (cData.rol || imgInfo.rol) : 'ENDPOINT',
                        tipo_equipo: esInfra ? (cData.tipo_equipo || imgInfo.tipo_equipo) : 'Dispositivo Final'
                    },
                    title: undefined,
                    x: savedPositions[node.id] ? savedPositions[node.id].x : (node.x ?? undefined),
                    y: savedPositions[node.id] ? savedPositions[node.id].y : (node.y ?? undefined)
                };
            });
            // Equipos sin posición (recién descubiertos): se colocan junto a sus vecinos al terminar de crear la red
            const sinPosicion = mappedNodes.filter(n => n.x === undefined || n.y === undefined).map(n => n.id);

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
                    font: { ...fuenteEtiqueta(isDark), align: 'center' },
                    // Sombras desactivadas: se recalculan por nodo en cada cuadro y frenan el lienzo
                    shadow: { enabled: false }
                },
                edges: {
                    font: {
                        size: 11,
                        color: isDark ? '#94a3b8' : '#475569',
                        background: isDark ? 'rgba(13, 16, 23, 0.88)' : 'rgba(255, 255, 255, 0.95)',
                        strokeWidth: 0,
                        align: 'horizontal'
                    },
                    // Curva vertical (organigrama): con el acomodo en árbol baja de cada equipo a sus hijos sin arcos cruzados
                    smooth: { type: 'cubicBezier', forceDirection: 'vertical', roundness: 0.45 },
                    color: { color: '#10b981', opacity: 0.5 },
                    width: 2.5
                },
                // Sin física al cargar: el plano guardado se respeta tal cual y lo que falta lo coloca acomodarFaltantes()
                physics: {
                    enabled: false,
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
                    dragNodes: PUEDE_EDITAR,
                    dragView: true,
                    zoomView: true,
                    navigationButtons: false,
                    keyboard: false
                }
            };

            network = new vis.Network(container, data, options);
            ['zoom', 'stabilized', 'animationFinished', 'resize'].forEach(ev => network.on(ev, ajustarGrosorCables));
            window.addEventListener('theme-changed', ajustarGrosorCables);
            ajustarGrosorCables();

            // Equipos finales dentro de su switch, zonas y selección (para los haces)
            aplicarAgrupacionEndpoints();
            network.on('beforeDrawing', ctx => { dibujarMapaJalisco(ctx); dibujarSitios(ctx); dibujarZonas(ctx); });
            initZonasInteraccion(container);
            network.on('selectNode', p => { nodoSeleccionado = p.nodes[0] ?? null; });
            network.on('deselectNode', () => { nodoSeleccionado = null; });

            // Ciclo de animación de los haces (limitado y solo si hay haces que dibujar)
            startTrafficAnimationLoop();

            // Vista general completa sin auto-zoom a nodos específicos
            // Al abrir: la red completa (acercarse al núcleo es opcional, con el botón "Núcleo")
            function frameInitialView() {
                resetZoom();
            }
            network.on('zoom', ajustarEtiquetas);
            network.on('animationFinished', ajustarEtiquetas);

            physicsEnabled = false;
            const btnPhysIni = document.getElementById('btnTogglePhysics');
            const labelPhysIni = document.getElementById('physicsStatusLabel');
            if (btnPhysIni) btnPhysIni.classList.remove('active');
            if (labelPhysIni) labelPhysIni.textContent = 'Física';

            // Las imágenes deben estar medidas antes de acomodar (el ancho real de cada panel evita encimarlos)
            network.once('afterDrawing', () => setTimeout(() => {
                if (!hasSavedPositions) {
                    ordenarTopologia();                 // primera vez: árbol completo desde el núcleo
                } else if (sinPosicion.length) {
                    acomodarFaltantes(sinPosicion);     // solo los recién descubiertos, junto a sus vecinos
                    frameInitialView();
                } else {
                    frameInitialView();
                }
                // Llegada desde una alerta (?foco=id): centrar y seleccionar ese equipo
                const foco = Number(new URLSearchParams(location.search).get('foco'));
                if (foco && nodesDataSet.get(foco)) {
                    setTimeout(() => {
                        network.selectNodes([foco]);
                        network.focus(foco, { scale: Math.max(network.getScale(), 180 / medirNodo(foco).w), animation: { duration: 700, easingFunction: 'easeInOutQuad' } });
                        nodoSeleccionado = foco;
                        if (typeof showDeviceDetails === 'function') showDeviceDetails(foco);
                    }, 700);
                }
            }, 120));

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
            // Caché de datasets: DataSet.get() copia todos los objetos, no se debe llamar en cada cuadro
            let cachedEdges = null;
            let cachedNodes = null;
            edgesDataSet.on('*', () => { cachedEdges = null; });
            nodesDataSet.on('*', () => { cachedNodes = null; });

            network.on('afterDrawing', function(ctx) {
                if (!nodesDataSet) return;
                if (!cachedNodes) {
                    cachedNodes = nodesDataSet.get();
                    estadoNodo = {};
                    cachedNodes.forEach(n => { estadoNodo[n.id] = n.customData?.estado; });
                }
                const nodePositions = network.getPositions();
                let hacesDibujados = 0;

                // ----------------------------------------------------
                // CAPA 1: HACES DE LUZ ANIMADOS PEGADOS AL CABLE
                // ----------------------------------------------------
                if (edgesDataSet) {
                    if (!cachedEdges) cachedEdges = edgesDataSet.get();
                    const edges = cachedEdges;

                    edges.forEach(edge => {
                        if (edge.hidden) return;
                        const fromPos = nodePositions[edge.from];
                        const toPos = nodePositions[edge.to];
                        if (!fromPos || !toPos) return;

                        // Obtener el objeto interno de Vis.js para calcular la curva exacta del cable
                        const visEdge = network.body && network.body.edges ? network.body.edges[edge.id] : null;

                        const estadoEnlace = edge.customData?.estado;
                        const isDown = estadoEnlace === 'DOWN';
                        const isSaturated = (parseFloat(edge.customData?.saturacion_pct) || 0) > 75;

                        // Se animan todos los enlaces activos; el uso define velocidad y cantidad de pulsos.
                        // Con un equipo seleccionado, solo los suyos (para seguir su tráfico).
                        const util = parseFloat(edge.customData?.saturacion_pct) || 0;
                        const delSeleccionado = nodoSeleccionado === null || edge.from === nodoSeleccionado || edge.to === nodoSeleccionado;
                        const animar = trafficSimulationEnabled
                            && (estadoEnlace === 'UP' || estadoEnlace === 'DEGRADED')
                            && delSeleccionado;
                        if (animar) {
                            hacesDibujados++;
                            const seed = ((edge.from * 23 + edge.to * 47) % 100) / 100;
                            const speed = 0.22 + Math.min(util, 100) / 100 * 0.55;
                            // Con la red completa en pantalla (cientos de enlaces) un pulso delgado por enlace y sin
                            // retorno; al acercarse, todos los pulsos a tamaño completo
                            const lejos = network.getScale() < BEAMS_ESCALA_DETALLE;
                            const pulsos = !lejos && util >= BEAMS_MIN_UTIL ? 2 : 1;
                            const beamSpan = lejos ? 0.1 : 0.14; // Porcentaje del cable ocupado por la estela del haz
                            // Grosor en píxeles de pantalla: se ve igual al alejar o acercar el zoom
                            const u = px(1) * (lejos ? 0.6 : 1);

                            // 1. Haz Principal (Forward: Origen ➔ Destino)
                            for (let k = 0; k < pulsos; k++) {
                                const rawPhase = (trafficAnimTime * speed + seed + (k / pulsos)) % 1;
                                // Rango activo entre 0.08 y 0.92 para no tapar los chasis
                                const tHead = 0.08 + rawPhase * 0.84;
                                const tTail = Math.max(0.04, tHead - beamSpan);

                                const headPt = getPointOnVisEdge(visEdge, fromPos, toPos, tHead);
                                const tailPt = getPointOnVisEdge(visEdge, fromPos, toPos, tTail);

                                ctx.save();
                                // Saturado: ámbar; normal: cian eléctrico (contrasta con el cable verde y con el fondo claro u oscuro)
                                const [r, g, b] = isSaturated ? [245, 158, 11] : [102, 125, 147];
                                const grad = ctx.createLinearGradient(tailPt.x, tailPt.y, headPt.x, headPt.y);
                                grad.addColorStop(0, `rgba(${r}, ${g}, ${b}, 0)`);
                                grad.addColorStop(0.5, `rgba(${r}, ${g}, ${b}, 0.6)`);
                                grad.addColorStop(1, `rgba(${r}, ${g}, ${b}, 0.9)`);
                                ctx.lineCap = 'round';
                                ctx.lineJoin = 'round';

                                // Segmento curvo muestreando puntos exactos sobre la curva del cable
                                const trazo = new Path2D();
                                const samples = 10;
                                for (let s = 0; s <= samples; s++) {
                                    const st = tTail + (tHead - tTail) * (s / samples);
                                    const pt = getPointOnVisEdge(visEdge, fromPos, toPos, st);
                                    if (s === 0) trazo.moveTo(pt.x, pt.y);
                                    else trazo.lineTo(pt.x, pt.y);
                                }
                                // 1) Brillo suave  2) Haz de color
                                ctx.strokeStyle = `rgba(${r}, ${g}, ${b}, 0.14)`;
                                ctx.lineWidth = 6 * u;
                                ctx.stroke(trazo);
                                ctx.strokeStyle = grad;
                                ctx.lineWidth = 2.6 * u;
                                ctx.stroke(trazo);

                                // Paquete líder: punto blanco con borde de color
                                ctx.beginPath();
                                ctx.arc(headPt.x, headPt.y, 2.8 * u, 0, Math.PI * 2);
                                ctx.fillStyle = '#ffffff';
                                ctx.fill();
                                ctx.lineWidth = 1.2 * u;
                                ctx.strokeStyle = `rgba(${r}, ${g}, ${b}, 0.9)`;
                                ctx.stroke();
                                ctx.restore();
                            }

                            // 2. Haz Secundario / ACK (Retorno Full-Duplex: Destino ➔ Origen), solo de cerca
                            if (!lejos) {
                            const revRawPhase = (trafficAnimTime * (speed * 1.15) + seed * 1.7) % 1;
                            const tRevHead = 0.92 - revRawPhase * 0.84;
                            const tRevTail = Math.min(0.96, tRevHead + (beamSpan * 0.75));

                            const revHeadPt = getPointOnVisEdge(visEdge, fromPos, toPos, tRevHead);
                            const revTailPt = getPointOnVisEdge(visEdge, fromPos, toPos, tRevTail);

                            ctx.save();
                            // Retorno: violeta, un poco más delgado que el de ida
                            const revGrad = ctx.createLinearGradient(revTailPt.x, revTailPt.y, revHeadPt.x, revHeadPt.y);
                            revGrad.addColorStop(0, 'rgba(139, 92, 246, 0)');
                            revGrad.addColorStop(0.5, 'rgba(139, 92, 246, 0.45)');
                            revGrad.addColorStop(1, 'rgba(139, 92, 246, 0.75)');
                            ctx.lineCap = 'round';
                            ctx.lineJoin = 'round';

                            const revTrazo = new Path2D();
                            const revSamples = 8;
                            for (let s = 0; s <= revSamples; s++) {
                                const st = tRevTail + (tRevHead - tRevTail) * (s / revSamples);
                                const pt = getPointOnVisEdge(visEdge, fromPos, toPos, st);
                                if (s === 0) revTrazo.moveTo(pt.x, pt.y);
                                else revTrazo.lineTo(pt.x, pt.y);
                            }
                            ctx.strokeStyle = revGrad;
                            ctx.lineWidth = 2 * u;
                            ctx.stroke(revTrazo);

                            ctx.beginPath();
                            ctx.arc(revHeadPt.x, revHeadPt.y, 2 * u, 0, Math.PI * 2);
                            ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
                            ctx.fill();
                            ctx.restore();
                            }
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
                hacesActivos = hacesDibujados;

                cachedNodes.forEach(node => {
                    const pos = nodePositions[node.id];
                    if (!pos || !node.customData || node.hidden) return;

                    dibujarInsigniasNodo(ctx, node, pos);

                    const estado = node.customData.estado || 'online';
                    const nodeSize = node.size || 120;

                    const ledX = pos.x + (nodeSize * 0.92);
                    const ledY = pos.y - (nodeSize * 0.55);
                    const ledRadius = 6;
                    const online = estado === 'online';

                    ctx.save();
                    // Halo translúcido en lugar de shadowBlur
                    ctx.beginPath();
                    ctx.arc(ledX, ledY, ledRadius + 4, 0, 2 * Math.PI, false);
                    ctx.fillStyle = online ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.35)';
                    ctx.fill();

                    ctx.beginPath();
                    ctx.arc(ledX, ledY, ledRadius, 0, 2 * Math.PI, false);
                    ctx.fillStyle = online ? '#10b981' : '#ef4444';
                    ctx.fill();
                    ctx.strokeStyle = '#ffffff';
                    ctx.lineWidth = 1.5;
                    ctx.stroke();
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
                    const sitio = sitioEn(params.pointer.canvas);
                    if (sitio) { abrirFichaSitio(sitio, params.pointer.DOM); return; }
                    cerrarFichaSitio();
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
            const sinDato = (v, suffix = '') => (v === null || v === undefined || v === '') ? 'Sin dato' : v + suffix;
            document.getElementById('drawerDeviceCpu').textContent = sinDato(d.cpu, '%');
            document.getElementById('drawerDeviceMem').textContent = sinDato(d.memoria, '%');
            document.getElementById('drawerDevicePing').textContent = sinDato(d.ping, ' ms');
            document.getElementById('drawerDeviceModelo').textContent = sinDato(d.modelo);
            document.getElementById('drawerDeviceSerial').textContent = sinDato(d.serial);
            document.getElementById('drawerDeviceUptime').textContent = sinDato(d.uptime);
            document.getElementById('drawerDeviceUbicacion').textContent = d.ubicacion;
            document.getElementById('drawerDeviceLink').href = d.url;

            const badge = document.getElementById('drawerDeviceStatusBadge');
            badge.textContent = d.estado.toUpperCase();
            if (d.estado === 'online') {
                badge.className = 'px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700';
            } else if (d.estado === 'warning') {
                badge.className = 'px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-amber-100 text-[#ff8300]';
            } else {
                badge.className = 'px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-red-100 text-red-700';
            }

            if (d.image) {
                const icon = document.createElement('img');
                icon.src = d.image;
                icon.className = 'w-9 h-9 object-contain drop-shadow';
                icon.alt = d.nombre;
                document.getElementById('drawerDeviceIcon').replaceChildren(icon);
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
                document.getElementById('drawerDeviceFactorForma').textContent = d.factor_forma || 'Modelo no reportado';
            }
            if (document.getElementById('drawerDeviceVendor')) {
                document.getElementById('drawerDeviceVendor').textContent = d.vendor || 'Sin dato';
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

                        // Los nombres de puertos/vecinos llegan por CDP/LLDP desde la red: se escapan siempre.
                        const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
                        item.innerHTML = `
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${esc(c.color)};"></span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded border border-gray-200 dark:border-slate-700 text-[11px] shadow-2xs">${esc(c.puerto_local)}</span>
                                <span class="text-gray-400 dark:text-slate-500 text-[11px]">➔</span>
                                <div class="truncate max-w-[130px]">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100 leading-tight truncate">${esc(c.remoto_nombre)}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-slate-400 font-mono">${esc(c.remoto_ip)} • ${esc(c.puerto_remoto)}</p>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="text-[11px] font-black px-1.5 py-0.5 rounded ${c.estado === 'UP' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : (c.estado === 'DOWN' ? 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300')}">${esc(c.estado === 'UNKNOWN' ? 'SIN DATO' : c.estado)}</span>
                                <p class="text-[11px] text-gray-400 dark:text-slate-500 font-mono mt-0.5">${esc(c.velocidad)}</p>
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
                        pcBadge.className = 'text-[11px] font-black px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300';
                        pcBadge.textContent = 'CONECTADA';
                    }
                } else if (d.pc_conectada && d.pc_conectada.status === 'standby') {
                    const pc = d.pc_conectada;
                    pcSection.classList.remove('hidden');
                    document.getElementById('drawerPhonePcHostname').textContent = 'Puerto PC Disponible';
                    document.getElementById('drawerPhonePcVendor').textContent = 'Sin computadora transmitiendo';
                    document.getElementById('drawerPhonePcIp').textContent = 'Standby';
                    document.getElementById('drawerPhonePcMac').textContent = 'Sin dato';
                    document.getElementById('drawerPhonePcSpeed').textContent = 'Sin dato';
                    document.getElementById('drawerPhonePcVlan').textContent = 'Sin dato';
                    document.getElementById('drawerPhonePcSwitchPort').textContent = `${pc.switch_name} (${pc.switch_port})`;
                    
                    const pcBadge = document.getElementById('drawerPhonePcStatusBadge');
                    if (pcBadge) {
                        pcBadge.className = 'text-[11px] font-black px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300';
                        pcBadge.textContent = 'STANDBY';
                    }
                } else {
                    pcSection.classList.add('hidden');
                }
            }

            renderTipoDrawer(d);
            renderEndpointsDrawer(nodeId);
            cargarNotas(nodeId);
            openDrawer();
        }

        const CONF_ESTILO = {
            alta: 'bg-emerald-100 text-emerald-700', media: 'bg-amber-100 text-amber-700',
            baja: 'bg-red-100 text-red-700', ninguna: 'bg-red-100 text-red-700', manual: 'bg-sky-100 text-sky-700'
        };
        const FUENTE_TXT = { snmp: 'SNMP del equipo', vecinos: 'CDP/LLDP de vecinos', nombre: 'solo por nombre', manual: 'corrección manual', ninguna: 'sin evidencia' };

        function renderTipoDrawer(d) {
            const conf = d.tipo_confianza || 'baja';
            const badge = document.getElementById('drawerTipoConfianza');
            badge.className = 'text-[11px] font-black px-1.5 py-0.5 rounded ' + (CONF_ESTILO[conf] || CONF_ESTILO.baja);
            badge.textContent = `${(d.tipo_equipo || '').toUpperCase()} · ${conf.toUpperCase()} · ${FUENTE_TXT[d.tipo_fuente] || d.tipo_fuente || ''}`;
            const ul = document.getElementById('drawerTipoEvidencia');
            ul.innerHTML = (d.tipo_evidencia || []).map(e => `<li>${escHtml(e)}</li>`).join('') || '<li>Sin evidencia registrada</li>';
            const sel = document.getElementById('drawerTipoManual');
            if (sel) sel.value = d.tipo_manual || '';
        }

        async function corregirTipo(tipo) {
            const id = notasDispositivoId;
            if (!id) return;
            try {
                const r = await apiJson(`{{ url('api/dispositivos') }}/${id}/tipo`, 'POST', { tipo: tipo || null });
                const t = r.tipo;
                const node = nodesDataSet.get(id);
                if (node) {
                    const cd = { ...node.customData, tipo: t.tipo, rol: t.rol, tipo_equipo: t.label, tipo_confianza: t.confianza,
                                 tipo_fuente: t.fuente, tipo_evidencia: t.evidencia, tipo_manual: tipo || null };
                    nodesDataSet.update({ id, customData: cd, image: '{{ url('/') }}/' + t.image, size: t.size });
                    renderTipoDrawer(cd);
                    aplicarAgrupacionEndpoints();
                }
            } catch (e) {
                nmAviso('No se pudo corregir el tipo: ' + e.message, 'error');
            }
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
            document.getElementById('drawerLinkTraficoIn').textContent = l.trafico_in === null ? 'Sin dato' : l.trafico_in + ' Mbps';
            document.getElementById('drawerLinkTraficoOut').textContent = l.trafico_out === null ? 'Sin dato' : l.trafico_out + ' Mbps';
            document.getElementById('drawerLinkSaturacion').textContent = l.saturacion_pct === null ? 'Sin dato' : l.saturacion_pct + '%';

            const badge = document.getElementById('drawerLinkStatusBadge');
            badge.textContent = l.estado === 'UNKNOWN' ? 'SIN DATO' : l.estado;
            if (l.estado === 'UP') {
                badge.className = 'px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700';
            } else if (l.estado === 'DEGRADED') {
                badge.className = 'px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-amber-100 text-[#ff8300]';
            } else if (l.estado === 'UNKNOWN') {
                badge.className = 'px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-gray-100 text-gray-600';
            } else {
                badge.className = 'px-2 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-red-100 text-red-700';
            }

            // Vlans tags
            const vlansContainer = document.getElementById('drawerLinkVlans');
            vlansContainer.innerHTML = '';
            const vlans = l.vlans ? l.vlans.split(',') : [];
            if (vlans.length === 0) {
                vlansContainer.textContent = 'Sin dato';
            }
            vlans.forEach(v => {
                const span = document.createElement('span');
                span.className = 'px-2 py-0.5 bg-gray-100 text-gray-700 text-[11px] font-bold rounded';
                span.textContent = v.trim().toUpperCase().startsWith('VLAN') ? v.trim() : 'VLAN ' + v.trim();
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
                if (label) label.textContent = 'Tráfico animado';
            } else {
                if (btn) btn.classList.remove('active');
                if (label) label.textContent = 'Tráfico animado';
                if (network) network.redraw();
            }
        }

        // Redibujar el lienzo completo es caro: se limita a ~30 FPS, se pausa con la pestaña oculta
        // y cuando el zoom es tan lejano que los haces no se distinguen.
        const BEAMS_FRAME_MS = 1000 / 30;
        const BEAMS_MIN_SCALE = 0.01;  // los haces se dibujan en píxeles de pantalla: se ven también con la red completa
        const BEAMS_MIN_UTIL = 60;     // % de utilización a partir del cual el enlace lleva un pulso más
        const BEAMS_ESCALA_DETALLE = 0.07; // por debajo (red completa): haces discretos; por encima: completos
        let hacesActivos = 0;
        let nodoSeleccionado = null;

        function startTrafficAnimationLoop() {
            if (animLoopRunning) return;
            animLoopRunning = true;
            let lastTimestamp = performance.now();
            let lastDraw = 0;

            function frame(now) {
                requestAnimationFrame(frame);
                const delta = Math.min(0.1, (now - lastTimestamp) / 1000);
                lastTimestamp = now;

                if (!trafficSimulationEnabled || !network || document.hidden) return;
                trafficAnimTime += delta * trafficSpeedMultiplier;

                if (now - lastDraw < BEAMS_FRAME_MS) return;
                if (hacesActivos === 0) return; // nada que animar: vis redibuja solo al cambiar algo
                if (network.getScale() < BEAMS_MIN_SCALE) return;
                lastDraw = now;
                network.redraw();
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
                setTimeout(ajustarEtiquetas, 850);
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
                if (label) label.textContent = 'Física';
            } else {
                if (btn) btn.classList.remove('active');
                if (label) label.textContent = 'Física';
            }
        }

        // ==================== ACOMODO AUTOMÁTICO + ZONAS ====================
        function preguntarZonas(nombreLayout) {
            return new Promise(resolve => {
                const dlg = document.getElementById('layoutZonasDialog');
                document.getElementById('layoutZonasCount').textContent = zonas.length;
                document.getElementById('layoutZonasNombre').textContent = nombreLayout;
                dlg.classList.remove('hidden');
                dlg.querySelectorAll('button[data-op]').forEach(b => b.onclick = () => {
                    dlg.classList.add('hidden');
                    resolve(b.dataset.op || null);
                });
            });
        }

        // Equipos de cada zona: por sus rangos de IP o, si no tiene, por los que están dentro del recuadro
        function miembrosDeZonas() {
            const pos = network.getPositions();
            const asignados = new Set();
            return zonas.map(z => {
                let ids;
                if (parseRangos(z.rangos_ip).rangos.length) {
                    ids = equiposEnRangos(z.rangos_ip).map(n => n.id);
                } else {
                    ids = Object.entries(pos)
                        .filter(([, q]) => q.x >= z.x && q.x <= z.x + z.ancho && q.y >= z.y && q.y <= z.y + z.alto)
                        .map(([id]) => isNaN(id) ? id : Number(id));
                }
                ids = ids.filter(id => !asignados.has(id));
                ids.forEach(id => asignados.add(id));
                return { zona: z, ids };
            });
        }

        async function guardarZonasSilencioso(lista) {
            if (!PUEDE_EDITAR) return;
            for (const z of lista) {
                try { await guardarZonaServidor(z); } catch (e) { console.warn('Zona no guardada', z.nombre, e); }
            }
        }

        // Cada zona se ajusta al recuadro que rodea a sus equipos visibles
        async function zonasSiguenEquipos(miembros) {
            const pos = network.getPositions();
            const cambiadas = [];
            miembros.forEach(({ zona, ids }) => {
                const pts = ids.filter(id => !(endpointsAgrupados && padreDeHijo[id] !== undefined)).map(id => pos[id]).filter(Boolean);
                if (!pts.length) return;
                const xs = pts.map(p => p.x), ys = pts.map(p => p.y);
                zona.x = Math.min(...xs) - MARGEN;
                zona.y = Math.min(...ys) - TITULO - 60;
                zona.ancho = Math.max(400, Math.max(...xs) - Math.min(...xs) + 2 * MARGEN);
                zona.alto = Math.max(300, Math.max(...ys) - Math.min(...ys) + TITULO + 60 + MARGEN);
                cambiadas.push(zona);
            });
            await guardarZonasSilencioso(cambiadas);
        }

        // Los equipos de cada zona se reacomodan dentro de ella; los demás (ya acomodados
        // por el layout) se desplazan debajo de todas las zonas para no encimarse.
        async function respetarZonas(miembros) {
            const posiciones = {};
            const enZona = new Set();
            miembros.forEach(({ zona, ids }) => {
                ids.forEach(id => enZona.add(id));
                cuadriculaEnZona(zona, nodesDataSet.get(ids), posiciones);
            });
            const conZona = miembros.map(m => m.zona);
            if (conZona.length) {
                const fondo = Math.max(...conZona.map(z => z.y + z.alto));
                const izquierda = Math.min(...conZona.map(z => z.x));
                const actuales = network.getPositions();
                const fuera = Object.keys(actuales).map(id => isNaN(id) ? id : Number(id)).filter(id => !enZona.has(id));
                const esAgrupado = id => endpointsAgrupados && padreDeHijo[id] !== undefined;
                const resto = fuera.filter(id => !esAgrupado(id));
                if (resto.length) {
                    const minY = Math.min(...resto.map(id => actuales[id].y));
                    const minX = Math.min(...resto.map(id => actuales[id].x));
                    resto.forEach(id => {
                        posiciones[id] = { x: actuales[id].x - minX + izquierda, y: actuales[id].y - minY + fondo + 500 };
                    });
                }
                // Equipos finales agrupados: alrededor de su switch, esté dentro o fuera de una zona
                rodearPadres(nodesDataSet.get(fuera.filter(esAgrupado)), posiciones);
            }
            Object.entries(posiciones).forEach(([id, p]) => network.moveNode(isNaN(id) ? id : Number(id), p.x, p.y));
            await guardarZonasSilencioso(conZona);
            saveNodePositionsToDatabase(network.getPositions());
        }

        async function setLayout(type) {
            if (!network) return;
            if (!zonas.length) return aplicarLayout(type);

            const op = await preguntarZonas(type === 'tree' ? 'Jerárquico' : 'Libre');
            if (!op) return;
            if (op === 'quitar') {
                if (!(await nmConfirmar(`¿Borrar las ${zonas.length} zonas?`, { titulo: 'Borrar zonas', boton: 'Borrar zonas' }))) return;
                try {
                    await apiJson("{{ route('topologia.zonas.destroy_all') }}", 'DELETE'); // una sola petición: borra también zonas que el mapa no tenga cargadas
                    zonas = [];
                    renderZonaLista();
                } catch (e) {
                    nmAviso('No se pudieron borrar todas las zonas: ' + e.message, 'error');
                }
                return aplicarLayout(type);
            }
            const miembros = miembrosDeZonas();
            aplicarLayout(type, async () => {
                if (op === 'dentro') await respetarZonas(miembros);
                else await zonasSiguenEquipos(miembros);
                renderZonaLista();
                network.redraw();
                network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' }, padding: 80 });
            });
        }

        function aplicarLayout(type, alTerminar = null) {
            if (!network) return;

            const btnTree = document.getElementById('btnLayoutTree');
            const btnFree = document.getElementById('btnLayoutFree');

            if (type === 'tree') {
                if (btnTree) btnTree.classList.add('active');
                if (btnFree) btnFree.classList.remove('active');

                // Árbol propio desde el núcleo con el ancho real de cada panel (el jerárquico de vis-network
                // separa 450 unidades fijas y los paneles miden hasta 1450: se encimaban)
                network.setOptions({ physics: { enabled: false } });
                ordenarTopologia({ encuadrar: false });

                physicsEnabled = false;
                const btnPhys = document.getElementById('btnTogglePhysics');
                const labelPhys = document.getElementById('physicsStatusLabel');
                if (btnPhys) btnPhys.classList.remove('active');
                if (labelPhys) labelPhys.textContent = 'Física Inactiva';

                if (alTerminar) alTerminar();
                else setTimeout(() => network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' }, padding: 80 }), 250);
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
                    if (labelPhys) labelPhys.textContent = 'Física';
                    
                    const currentPositions = network.getPositions();
                    saveNodePositionsToDatabase(currentPositions);

                    if (alTerminar) alTerminar();
                    else network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' }, padding: 80 });
                });

                const btnPhys = document.getElementById('btnTogglePhysics');
                const labelPhys = document.getElementById('physicsStatusLabel');
                if (btnPhys) btnPhys.classList.add('active');
                if (labelPhys) labelPhys.textContent = 'Física';
                // Sin stabilize() explícito el evento 'stabilizationIterationsDone' no se emite
                network.stabilize(150);
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
                const tipoReal = node.customData?.tipo;
                const porTipo = { router: ['router', 'firewall'], switch: ['switch'], access_point: ['access_point', 'wlc'], phone: ['telefono'], servidor: ['servidor'] };
                if (tipoReal && porTipo[category]) {
                    match = porTipo[category].includes(tipoReal);
                } else if (category === 'router') {
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

            // Un equipo final agrupado no está en el lienzo: se enfoca su switch
            const visible = id => (endpointsAgrupados && padreDeHijo[id] !== undefined) ? padreDeHijo[id] : id;
            if (matchedNodeIds.length === 1) {
                const destino = visible(matchedNodeIds[0]);
                network.focus(destino, {
                    scale: 1.15,
                    animation: { duration: 600, easingFunction: 'easeInOutQuad' }
                });
                network.selectNodes([destino]);
                showDeviceDetails(matchedNodeIds[0]);
            } else if (matchedNodeIds.length > 1) {
                network.fit({
                    nodes: [...new Set(matchedNodeIds.map(visible))],
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
                edgesDataSet.add(allEdges.filter(e => e.customData.estado === 'DOWN' || e.customData.estado === 'DEGRADED'));
            }
            aplicarAgrupacionEndpoints();
        }

        function exportTopologyImage() {
            const canvas = document.querySelector('#networkTopologyCanvas canvas');
            if (!canvas) return;

            const link = document.createElement('a');
            link.download = 'Topologia_Red_NOC_' + new Date().toISOString().slice(0,10) + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        const escHtml = s => String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));

        async function apiJson(url, method = 'GET', body = null) {
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: body ? JSON.stringify(body) : null
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || ('Error ' + res.status));
            return data;
        }

        // ==================== EQUIPOS FINALES AGRUPADOS EN SU SWITCH ====================
        // Teléfonos y PCs con un solo vecino de infraestructura se ocultan y se muestran
        // como insignia sobre ese vecino. Se ven en el inspector del switch.
        let endpointsAgrupados = localStorage.getItem('topo_endpoints_visibles') !== '1';
        let estadoNodo = {};      // id -> estado (cacheado por cuadro de dibujo)
        let hijosPorPadre = {};   // padreId -> [hijoId]
        let padreDeHijo = {};     // hijoId  -> padreId

        function esEquipoFinal(node) {
            const tipo = node.customData?.tipo;
            if (tipo) return ['telefono', 'pc', 'impresora'].includes(tipo);
            const rol = (node.customData?.rol || '').toUpperCase();
            return /VOIP|ENDPOINT|PC \/|\/ PC/.test(rol);
        }

        function calcularJerarquiaEndpoints() {
            hijosPorPadre = {};
            padreDeHijo = {};
            const nodos = {};
            nodesDataSet.get().forEach(n => { nodos[n.id] = n; });
            const vecinos = {};
            (initialGrafoData.edges || []).forEach(e => {
                (vecinos[e.from] = vecinos[e.from] || new Set()).add(e.to);
                (vecinos[e.to] = vecinos[e.to] || new Set()).add(e.from);
            });
            Object.values(nodos).forEach(n => {
                if (!esEquipoFinal(n)) return;
                const v = [...(vecinos[n.id] || [])].filter(id => nodos[id] && !esEquipoFinal(nodos[id]));
                if (v.length !== 1) return;
                padreDeHijo[n.id] = v[0];
                (hijosPorPadre[v[0]] = hijosPorPadre[v[0]] || []).push(n.id);
            });
        }

        function aplicarAgrupacionEndpoints() {
            if (!nodesDataSet || !edgesDataSet) return;
            calcularJerarquiaEndpoints();
            const ocultar = endpointsAgrupados;
            nodesDataSet.update(nodesDataSet.getIds().map(id => ({ id, hidden: ocultar && padreDeHijo[id] !== undefined })));
            edgesDataSet.update(edgesDataSet.get().map(e => ({
                id: e.id,
                hidden: ocultar && (padreDeHijo[e.from] === e.to || padreDeHijo[e.to] === e.from)
            })));
            const btn = document.getElementById('btnToggleEndpoints');
            const lbl = document.getElementById('endpointsStatusLabel');
            if (btn) btn.classList.toggle('active', ocultar);
            if (lbl) lbl.textContent = 'Agrupar finales';
        }

        function toggleEndpoints() {
            endpointsAgrupados = !endpointsAgrupados;
            try { localStorage.setItem('topo_endpoints_visibles', endpointsAgrupados ? '0' : '1'); } catch (e) {}
            aplicarAgrupacionEndpoints();
        }

        // Insignias dibujadas sobre el nodo: equipos finales agrupados y notas
        function dibujarInsigniasNodo(ctx, node, pos) {
            const size = node.size || 120;
            const hijos = endpointsAgrupados ? (hijosPorPadre[node.id] || []) : [];
            if (hijos.length) {
                let caidos = 0;
                hijos.forEach(id => { if (estadoNodo[id] === 'offline') caidos++; });
                const texto = `☎ ${hijos.length}` + (caidos ? `  ▼${caidos}` : '');
                ctx.save();
                ctx.font = 'bold 22px Inter, system-ui, sans-serif';
                const w = ctx.measureText(texto).width + 20;
                const x = pos.x - w / 2, y = pos.y + size * 0.55 + 46;
                ctx.fillStyle = caidos ? '#fee2e2' : '#e0f2fe';
                ctx.strokeStyle = caidos ? '#ef4444' : '#667d93';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.roundRect ? ctx.roundRect(x, y, w, 32, 16) : ctx.rect(x, y, w, 32);
                ctx.fill(); ctx.stroke();
                ctx.fillStyle = caidos ? '#b91c1c' : '#075985';
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(texto, pos.x, y + 17);
                ctx.restore();
            }
            if ((node.customData?.notas || 0) > 0) {
                const x = pos.x - size * 0.92, y = pos.y - size * 0.55;
                ctx.save();
                ctx.fillStyle = '#facc15';
                ctx.strokeStyle = '#a16207';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                ctx.rect(x - 9, y - 11, 18, 22);
                ctx.fill(); ctx.stroke();
                ctx.fillStyle = '#713f12';
                for (let i = 0; i < 3; i++) ctx.fillRect(x - 5, y - 6 + i * 5, 10, 1.5);
                ctx.restore();
            }
        }

        function renderEndpointsDrawer(nodeId) {
            const sec = document.getElementById('drawerEndpointsSection');
            const list = document.getElementById('drawerEndpointsList');
            const hijos = hijosPorPadre[nodeId] || [];
            if (!sec || !list) return;
            if (!hijos.length) { sec.classList.add('hidden'); return; }
            sec.classList.remove('hidden');
            const nodos = nodesDataSet.get(hijos).sort((a, b) =>
                (a.customData?.estado === 'offline' ? 0 : 1) - (b.customData?.estado === 'offline' ? 0 : 1));
            const caidos = nodos.filter(n => n.customData?.estado === 'offline').length;
            document.getElementById('drawerEndpointsCount').textContent = `${nodos.length} · ${caidos} sin respuesta`;
            list.innerHTML = nodos.map(n => {
                const d = n.customData || {};
                const color = d.estado === 'online' ? '#10b981' : (d.estado === 'warning' ? '#f59e0b' : '#ef4444');
                return `<div class="flex items-center justify-between gap-2 px-2 py-1 rounded-lg bg-slate-50 dark:bg-slate-900/70 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer text-xs" onclick="showDeviceDetails(${Number(n.id)})">
                    <span class="flex items-center gap-1.5 truncate"><i class="noc-dot" style="background:${color}"></i><span class="truncate font-semibold text-slate-700 dark:text-slate-200">${escHtml(d.nombre)}</span></span>
                    <span class="font-mono text-[11px] text-slate-500">${escHtml(d.ip)}</span>
                </div>`;
            }).join('');
        }

        // ==================== ZONAS (recuadros tipo Packet Tracer) ====================
        const ZONA_COLORES = ['#facc15', '#22d3ee', '#22c55e', '#e879f9', '#ff8300', '#667d93', '#ef4444', '#64748b'];
        let zonas = (initialGrafoData.zonas || []).map(z => ({ ...z }));
        let zonaEditando = false;
        let zonaArrastre = null;

        function hexToRgba(hex, a) {
            const n = parseInt(hex.slice(1), 16);
            return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
        }

        // Medidas en pantalla constantes, convertidas a unidades del lienzo
        function px(n) { return n / (network ? network.getScale() : 1); }

        /**
         * Grosor de cable constante en pantalla: los equipos miden ~1450 unidades del lienzo, así que un ancho
         * fijo de 2-5 unidades desaparecía en la vista general. El backend manda ancho y guiones en píxeles
         * (customData.ancho_px / dashes_px) y aquí se dividen entre el zoom actual.
         */
        let ajusteCablesPendiente = false;
        function ajustarGrosorCables() {
            if (!network || !edgesDataSet || ajusteCablesPendiente) return;
            ajusteCablesPendiente = true;
            requestAnimationFrame(() => {
                ajusteCablesPendiente = false;
                const escala = Math.max(network.getScale(), 0.01);
                const oscuro = document.documentElement.classList.contains('dark');
                edgesDataSet.update(edgesDataSet.get().map(e => {
                    const cd = e.customData || {};
                    const anchoPx = cd.ancho_px ?? 1.4;
                    const guiones = Array.isArray(cd.dashes_px) ? cd.dashes_px.map(v => v / escala) : (cd.dashes_px ?? e.dashes ?? false);
                    const cambio = { id: e.id, width: anchoPx / escala, dashes: guiones, hoverWidth: w => w * 2, selectionWidth: w => w * 2.5 };
                    // "Sin dato": gris más claro sobre el fondo oscuro para que no se pierda
                    if (cd.estado === 'UNKNOWN') {
                        cambio.color = { ...(e.color || {}), color: oscuro ? '#94a3b8' : '#64748b' };
                    }
                    return cambio;
                }));
            });
        }

        // ==================== MAPA DE FONDO: JALISCO / ZMG ====================
        // Contornos municipales del INEGI (Marco Geoestadístico) proyectados al lienzo. Es solo referencia
        // visual: los equipos no están georreferenciados. Hay dos vistas en grande (estado completo o ZMG) y el
        // recuadro muestra la otra; las rutas se precalculan como Path2D porque el lienzo se redibuja ~30 veces/s.
        const leerPref = (clave, defecto) => { try { return localStorage.getItem(clave) ?? defecto; } catch (e) { return defecto; } };
        const guardarPref = (clave, valor) => { try { localStorage.setItem(clave, valor); } catch (e) {} };
        const mapaJalisco = {
            visible: leerPref('topo_mapa_jalisco', '1') !== '0',
            vista: leerPref('topo_mapa_vista', 'jalisco') === 'zmg' ? 'zmg' : 'jalisco',
            datos: null, preparadoPara: null, municipios: [], zmgCaja: null, pxPorGrado: 0,
        };

        function extensionGrados(lista) {
            let minLon = 180, maxLon = -180, minLat = 90, maxLat = -90;
            lista.forEach(m => m.anillos.forEach(a => a.forEach(([lon, lat]) => {
                minLon = Math.min(minLon, lon); maxLon = Math.max(maxLon, lon); minLat = Math.min(minLat, lat); maxLat = Math.max(maxLat, lat);
            })));
            return { minLon, maxLon, minLat, maxLat, k: Math.cos(((minLat + maxLat) / 2) * Math.PI / 180) };
        }

        function prepararMapaJalisco() {
            if (!mapaJalisco.datos || !network || mapaJalisco.preparadoPara === mapaJalisco.vista) return;
            const pos = Object.values(network.getPositions());
            if (!pos.length) return;
            // El mapa elegido cubre la extensión del acomodo actual, centrado detrás de los equipos
            const xs = pos.map(p => p.x), ys = pos.map(p => p.y);
            const cx = (Math.min(...xs) + Math.max(...xs)) / 2, cy = (Math.min(...ys) + Math.max(...ys)) / 2;
            const anchoNodos = Math.max(...xs) - Math.min(...xs) + 3000, altoNodos = Math.max(...ys) - Math.min(...ys) + 3000;

            const lista = mapaJalisco.datos.municipios.filter(m => mapaJalisco.vista === 'jalisco' || m.zmg);
            const e = extensionGrados(lista);
            const escala = Math.max(anchoNodos / ((e.maxLon - e.minLon) * e.k), altoNodos / (e.maxLat - e.minLat)) * 1.05;
            const lon0 = (e.minLon + e.maxLon) / 2, lat0 = (e.minLat + e.maxLat) / 2;
            const proyectar = (lon, lat) => [cx + (lon - lon0) * escala * e.k, cy - (lat - lat0) * escala];

            const caja = { x1: Infinity, y1: Infinity, x2: -Infinity, y2: -Infinity };
            mapaJalisco.municipios = lista.map(m => {
                const ruta = new Path2D();
                m.anillos.forEach(anillo => anillo.forEach(([lon, lat], i) => {
                    const [x, y] = proyectar(lon, lat);
                    i ? ruta.lineTo(x, y) : ruta.moveTo(x, y);
                    if (m.zmg) {
                        caja.x1 = Math.min(caja.x1, x); caja.y1 = Math.min(caja.y1, y);
                        caja.x2 = Math.max(caja.x2, x); caja.y2 = Math.max(caja.y2, y);
                    }
                }));
                const [cabX, cabY] = proyectar(m.cabecera.lon, m.cabecera.lat);
                return { nombre: m.nombre, zmg: m.zmg, ruta, cabX, cabY };
            });
            mapaJalisco.zmgCaja = caja;
            mapaJalisco.proyectar = proyectar;
            mapaJalisco.pxPorGrado = escala;
            mapaJalisco.preparadoPara = mapaJalisco.vista;
        }

        // Colores del mapa: centros en azul claro y contornos marcados en azul cálido (mismos en lienzo y recuadro SVG)
        const MAPA_COLORES = {
            claro:  { relleno: '#d4e2f6', contorno: '#3f72c0', cabecera: '#3f72c0', borde: '#ffffff',
                      rellenoZmg: 'rgba(255,131,0,0.10)', contornoZmg: '#ff8300' },
            oscuro: { relleno: 'rgba(122,162,224,0.16)', contorno: '#7aa2e0', cabecera: '#7aa2e0', borde: '#0d1320',
                      rellenoZmg: 'rgba(255,131,0,0.14)', contornoZmg: '#ff8300' },
        };

        function dibujarMapaJalisco(ctx) {
            if (!mapaJalisco.visible || !mapaJalisco.datos) return;
            prepararMapaJalisco();
            if (!mapaJalisco.municipios.length) return;
            const oscuro = document.documentElement.classList.contains('dark');
            const esZmg = mapaJalisco.vista === 'zmg';
            ctx.save();
            ctx.lineJoin = 'round';

            const col = MAPA_COLORES[oscuro ? 'oscuro' : 'claro'];
            const pintar = (lista, naranja) => lista.forEach(m => {
                ctx.fillStyle = naranja ? col.rellenoZmg : col.relleno;
                ctx.strokeStyle = naranja ? col.contornoZmg : col.contorno;
                ctx.lineWidth = px(esZmg ? 2.2 : 1.6);
                ctx.fill(m.ruta);
                ctx.stroke(m.ruta);
            });
            // La ZMG va al final para que su contorno naranja quede encima del azul de los vecinos
            pintar(mapaJalisco.municipios.filter(m => !(m.zmg && !esZmg)), false);
            pintar(mapaJalisco.municipios.filter(m => m.zmg && !esZmg), true);

            if (!esZmg) {
                // Marco de la ZMG sobre el mapa estatal
                const z = mapaJalisco.zmgCaja, margen = px(10);
                ctx.setLineDash([px(6), px(4)]);
                ctx.strokeStyle = 'rgba(255,131,0,0.85)';
                ctx.lineWidth = px(1.5);
                ctx.strokeRect(z.x1 - margen, z.y1 - margen, z.x2 - z.x1 + margen * 2, z.y2 - z.y1 + margen * 2);
                ctx.setLineDash([]);
                ctx.font = `700 ${px(11)}px Inter, system-ui, sans-serif`;
                ctx.fillStyle = 'rgba(255,131,0,0.95)';
                ctx.textBaseline = 'bottom';
                ctx.fillText('ZMG', z.x1 - margen, z.y1 - margen - px(3));
            }

            // Cabeceras municipales; en el estado completo el nombre aparece solo con zoom suficiente
            const conNombre = esZmg || network.getScale() * mapaJalisco.pxPorGrado > 900;
            ctx.textBaseline = 'middle';
            ctx.font = `${esZmg ? 700 : 600} ${px(esZmg ? 13 : 10.5)}px Inter, system-ui, sans-serif`;
            mapaJalisco.municipios.forEach(m => {
                ctx.beginPath();
                ctx.arc(m.cabX, m.cabY, px(esZmg ? 4.5 : (m.zmg ? 3.5 : 3)), 0, Math.PI * 2);
                ctx.fillStyle = m.zmg ? '#ff8300' : MAPA_COLORES[oscuro ? 'oscuro' : 'claro'].cabecera;
                ctx.fill();
                ctx.lineWidth = px(1.2);
                ctx.strokeStyle = MAPA_COLORES[oscuro ? 'oscuro' : 'claro'].borde;
                ctx.stroke();
                if (conNombre) {
                    ctx.lineWidth = px(3);
                    ctx.strokeStyle = oscuro ? 'rgba(13,19,32,0.85)' : 'rgba(255,255,255,0.85)';
                    ctx.strokeText(m.nombre, m.cabX + px(7), m.cabY);
                    ctx.fillStyle = oscuro ? 'rgba(232,237,245,0.85)' : 'rgba(28,39,60,0.78)';
                    ctx.fillText(m.nombre, m.cabX + px(7), m.cabY);
                }
            });
            ctx.restore();
        }

        // SVG del recuadro: siempre muestra la vista que NO está en grande
        function construirRecuadroMapa() {
            const esZmgGrande = mapaJalisco.vista === 'zmg';
            const lista = mapaJalisco.datos.municipios.filter(m => esZmgGrande || m.zmg);
            const e = extensionGrados(lista);
            const W = 276, ancho = (e.maxLon - e.minLon) * e.k, alto = e.maxLat - e.minLat;
            const s = (W - 16) / ancho, H = Math.round(alto * s) + 16;
            const p = (lon, lat) => [8 + (lon - e.minLon) * e.k * s, 8 + (e.maxLat - lat) * s];
            const esc = t => t.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const ruta = m => m.anillos.map(a => 'M' + a.map(([lon, lat]) => p(lon, lat).map(v => v.toFixed(1)).join(',')).join('L') + 'Z').join('');

            const col = MAPA_COLORES[document.documentElement.classList.contains('dark') ? 'oscuro' : 'claro'];
            let svg = `<svg viewBox="0 0 ${W} ${H}" class="w-full h-auto" role="img" aria-label="${esZmgGrande ? 'Mapa del estado de Jalisco' : 'Mapa de la Zona Metropolitana de Guadalajara'}">`;
            // En el recuadro del estado la ZMG resalta en naranja (se dibuja al final para que su contorno quede encima)
            const esNaranja = m => esZmgGrande && m.zmg;
            [...lista.filter(m => !esNaranja(m)), ...lista.filter(esNaranja)].forEach(m => {
                const naranja = esNaranja(m);
                svg += `<path d="${ruta(m)}" fill="${naranja ? col.rellenoZmg : col.relleno}" stroke="${naranja ? col.contornoZmg : col.contorno}" stroke-width="${esZmgGrande ? 0.7 : 1.3}" stroke-linejoin="round"><title>${esc(m.nombre)}</title></path>`;
            });
            if (!esZmgGrande) {
                // Las cabeceras del centro están muy juntas: cada etiqueta tiene su lado para no encimarse
                const LADO = { 'Guadalajara': [6, -7, 'start'], 'San Pedro Tlaquepaque': [-5, 9, 'end'], 'Tonalá': [5, -3, 'start'],
                               'El Salto': [-5, 8, 'end'], 'Juanacatlán': [5, 9, 'start'], 'Zapopan': [-6, 0, 'end'] };
                lista.forEach(m => {
                    const [x, y] = p(m.cabecera.lon, m.cabecera.lat);
                    const [dx, dy, ancla] = LADO[m.nombre] || [5, 3, 'start'];
                    const nombre = m.nombre.replace('San Pedro ', '').replace(' de los Membrillos', '').replace(' de Zúñiga', '').replace(' de Juárez', '');
                    svg += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="3" fill="#ff8300" stroke="#fff" stroke-width="1"/>`;
                    svg += `<text x="${(x + dx).toFixed(1)}" y="${(y + dy).toFixed(1)}" text-anchor="${ancla}" font-size="8.5" font-weight="600" fill="currentColor" paint-order="stroke" stroke="var(--nm-sunken)" stroke-width="2.5" class="text-slate-700 dark:text-slate-200">${esc(nombre)}</text>`;
                });
            }
            document.getElementById('recuadroMapaSvg').innerHTML = svg + '</svg>';
            document.getElementById('recuadroMapaTitulo').textContent = esZmgGrande ? 'Estado de Jalisco' : 'Zona Metropolitana de Guadalajara';
            document.getElementById('recuadroMapaDetalle').textContent = esZmgGrande ? '125 municipios · clic para verlo en grande' : '10 municipios · clic para verla en grande';
            document.getElementById('recuadroMapaSvg').setAttribute('aria-label', esZmgGrande ? 'Ver en grande el mapa de Jalisco' : 'Ver en grande el mapa de la ZMG');
        }

        function aplicarVisibilidadMapa() {
            const listo = mapaJalisco.visible && mapaJalisco.datos;
            document.getElementById('btnMapaJalisco')?.classList.toggle('active', mapaJalisco.visible);
            document.getElementById('segVistaMapa')?.classList.toggle('hidden', !mapaJalisco.visible);
            document.querySelectorAll('#segVistaMapa [data-vista]').forEach(b => b.classList.toggle('active', b.dataset.vista === mapaJalisco.vista));
            document.getElementById('recuadroMapa')?.classList.toggle('hidden', !listo || mapaJalisco.recuadroCerrado === true);
            if (typeof pintarPanelSitios === 'function' && document.getElementById('panelSitios')) pintarPanelSitios();
            if (network) network.redraw();
        }

        function toggleMapaJalisco() {
            mapaJalisco.visible = !mapaJalisco.visible;
            guardarPref('topo_mapa_jalisco', mapaJalisco.visible ? '1' : '0');
            aplicarVisibilidadMapa();
        }

        function cambiarVistaMapa(vista) {
            mapaJalisco.vista = vista === 'zmg' ? 'zmg' : 'jalisco';
            guardarPref('topo_mapa_vista', mapaJalisco.vista);
            mapaJalisco.preparadoPara = null;
            if (mapaJalisco.datos) construirRecuadroMapa();
            aplicarVisibilidadMapa();
        }

        function cerrarRecuadroMapa() {
            mapaJalisco.recuadroCerrado = true;
            aplicarVisibilidadMapa();
        }

        fetch(@json(asset('data/jalisco-municipios.json')))
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
            .then(datos => { mapaJalisco.datos = datos; construirRecuadroMapa(); aplicarVisibilidadMapa(); })
            .catch(e => console.warn('No se pudo cargar el mapa de Jalisco:', e));

        // ==================== SITIOS DE LA ZMG (dependencias, recaudadoras, escuelas…) ====================
        // Candidatos de OpenStreetMap guardados en la base; la asignación de equipos la hace el NOC a mano.
        const CATEGORIAS_SITIO = {
            recaudadora: { etiqueta: 'Recaudadoras', color: '#ff8300' },
            gobierno: { etiqueta: 'Oficinas de gobierno', color: '#667d93' },
            ayuntamiento: { etiqueta: 'Ayuntamientos', color: '#465055' },
            justicia: { etiqueta: 'Justicia', color: '#7c3aed' },
            seguridad: { etiqueta: 'Seguridad', color: '#e11d48' },
            salud: { etiqueta: 'Salud', color: '#10b981' },
            educacion: { etiqueta: 'Escuelas', color: '#0891b2' },
        };
        const sitiosRed = {
            lista: [], proyectadosPara: null, seleccionado: null,
            visibles: new Set(JSON.parse(leerPref('topo_sitios_categorias', 'null')) || Object.keys(CATEGORIAS_SITIO).filter(c => c !== 'educacion')),
        };

        function proyectarSitios() {
            if (sitiosRed.proyectadosPara === mapaJalisco.preparadoPara || !mapaJalisco.proyectar) return;
            sitiosRed.lista.forEach(s => { [s.x, s.y] = mapaJalisco.proyectar(s.lon, s.lat); });
            sitiosRed.proyectadosPara = mapaJalisco.preparadoPara;
        }

        const sitiosActivos = () => mapaJalisco.visible && mapaJalisco.vista === 'zmg' && sitiosRed.lista.length > 0;

        function dibujarSitios(ctx) {
            if (!sitiosActivos() || !mapaJalisco.proyectar) return;
            proyectarSitios();
            const oscuro = document.documentElement.classList.contains('dark');
            const conNombre = network.getScale() * mapaJalisco.pxPorGrado > 9000;
            ctx.save();
            ctx.textBaseline = 'middle';
            ctx.font = `600 ${px(10.5)}px Inter, system-ui, sans-serif`;
            sitiosRed.lista.forEach(s => {
                if (!sitiosRed.visibles.has(s.c)) return;
                const color = CATEGORIAS_SITIO[s.c].color;
                const r = px(s.c === 'recaudadora' ? 5.5 : 3.6);
                ctx.beginPath();
                if (s.c === 'recaudadora') { ctx.moveTo(s.x, s.y - r); ctx.lineTo(s.x + r, s.y); ctx.lineTo(s.x, s.y + r); ctx.lineTo(s.x - r, s.y); ctx.closePath(); }
                else ctx.arc(s.x, s.y, r, 0, Math.PI * 2);
                ctx.fillStyle = color;
                ctx.fill();
                ctx.lineWidth = px(1.2);
                ctx.strokeStyle = oscuro ? '#0d1320' : '#ffffff';
                ctx.stroke();
                if (s.eq.length) {
                    // Anillo y número de equipos asignados
                    ctx.beginPath();
                    ctx.arc(s.x, s.y, r + px(3.5), 0, Math.PI * 2);
                    ctx.lineWidth = px(2);
                    ctx.strokeStyle = color;
                    ctx.stroke();
                    ctx.fillStyle = color;
                    ctx.font = `800 ${px(10)}px Inter, system-ui, sans-serif`;
                    ctx.fillText(String(s.eq.length), s.x + r + px(5), s.y - r - px(2));
                    ctx.font = `600 ${px(10.5)}px Inter, system-ui, sans-serif`;
                }
                if (conNombre && (s.c !== 'educacion' || s.eq.length)) {
                    ctx.lineWidth = px(3);
                    ctx.strokeStyle = oscuro ? 'rgba(13,19,32,0.85)' : 'rgba(255,255,255,0.9)';
                    ctx.strokeText(s.n, s.x + r + px(4), s.y + px(1));
                    ctx.fillStyle = oscuro ? 'rgba(232,237,245,0.9)' : 'rgba(28,39,60,0.85)';
                    ctx.fillText(s.n, s.x + r + px(4), s.y + px(1));
                }
            });

            // Sitio seleccionado: realce y líneas hacia sus equipos
            const sel = sitiosRed.seleccionado;
            if (sel && sitiosRed.visibles.has(sel.c)) {
                const pos = network.getPositions(sel.eq);
                ctx.setLineDash([px(6), px(5)]);
                ctx.lineWidth = px(1.6);
                ctx.strokeStyle = CATEGORIAS_SITIO[sel.c].color;
                Object.values(pos).forEach(p => { ctx.beginPath(); ctx.moveTo(sel.x, sel.y); ctx.lineTo(p.x, p.y); ctx.stroke(); });
                ctx.setLineDash([]);
                ctx.beginPath();
                ctx.arc(sel.x, sel.y, px(11), 0, Math.PI * 2);
                ctx.lineWidth = px(2.5);
                ctx.stroke();
            }
            ctx.restore();
        }

        function sitioEn(punto) {
            if (!sitiosActivos()) return null;
            let mejor = null, distMin = px(10);
            sitiosRed.lista.forEach(s => {
                if (!sitiosRed.visibles.has(s.c) || s.x === undefined) return;
                const d = Math.hypot(s.x - punto.x, s.y - punto.y);
                if (d < distMin) { distMin = d; mejor = s; }
            });
            return mejor;
        }

        function nombreEquipo(id) {
            const n = nodesDataSet?.get(id);
            return n?.customData ? `${n.customData.nombre} (${n.customData.ip})` : `Equipo #${id}`;
        }

        function abrirFichaSitio(sitio, dom) {
            sitiosRed.seleccionado = sitio;
            const ficha = document.getElementById('fichaSitio');
            const cat = CATEGORIAS_SITIO[sitio.c];
            const chip = document.getElementById('fichaSitioCategoria');
            chip.replaceChildren(Object.assign(document.createElement('i'), { className: 'w-2 h-2 rounded-full', style: `background:${cat.color}` }), document.createTextNode(cat.etiqueta.replace(/s$/, '')));
            chip.style.color = cat.color;
            document.getElementById('fichaSitioNombre').textContent = sitio.n;
            document.getElementById('fichaSitioDireccion').textContent = [sitio.d || 'Sin dirección registrada', sitio.m, sitio.op].filter(Boolean).join(' · ');
            document.getElementById('fichaSitioFuente').textContent = sitio.o === 'osm' ? 'Fuente: OpenStreetMap' : 'Capturado en el NOC';
            pintarEquiposSitio();

            const lista = document.getElementById('listaEquiposSitio');
            if (lista && !lista.childElementCount) {
                (nodesDataSet?.get({ filter: n => !!n.customData?.nombre }) || [])
                    .sort((a, b) => a.customData.nombre.localeCompare(b.customData.nombre))
                    .forEach(n => lista.append(Object.assign(document.createElement('option'), { value: `${n.customData.nombre} (${n.customData.ip})` })));
            }

            // Se abre junto al punto, sin salirse del lienzo
            const lienzo = document.getElementById('networkTopologyCanvas');
            ficha.classList.remove('hidden');
            const x = Math.min(dom.x + 16, lienzo.clientWidth - ficha.offsetWidth - 12);
            const y = Math.min(Math.max(dom.y - 20, 70), lienzo.clientHeight - ficha.offsetHeight - 12);
            ficha.style.left = Math.max(12, x) + 'px';
            ficha.style.top = y + 'px';
            network.redraw();
        }

        function pintarEquiposSitio() {
            const sitio = sitiosRed.seleccionado;
            const ul = document.getElementById('fichaSitioEquipos');
            ul.replaceChildren();
            if (!sitio.eq.length) {
                ul.append(Object.assign(document.createElement('li'), { className: 'text-slate-500 dark:text-slate-400', textContent: 'Ninguno todavía.' }));
                return;
            }
            sitio.eq.forEach(id => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-2 px-2 py-1 rounded-lg bg-[var(--nm-sunken)]';
                const btnEquipo = Object.assign(document.createElement('button'), { type: 'button', className: 'truncate text-left font-medium text-slate-800 dark:text-slate-100 hover:text-blue-600', textContent: nombreEquipo(id) });
                btnEquipo.onclick = () => { network.focus(id, { scale: Math.max(network.getScale(), 0.12), animation: { duration: 500 } }); network.selectNodes([id]); };
                li.append(btnEquipo);
                if (PUEDE_EDITAR) {
                    const quitar = Object.assign(document.createElement('button'), { type: 'button', className: 'shrink-0 w-6 h-6 rounded-md text-slate-500 hover:text-red-600 hover:bg-red-500/10', textContent: '×' });
                    quitar.setAttribute('aria-label', 'Quitar ' + nombreEquipo(id) + ' de este sitio');
                    quitar.onclick = () => guardarSitioDeEquipo(id, null);
                    li.append(quitar);
                }
                ul.append(li);
            });
        }

        function avisoFicha(texto, error) {
            const p = document.getElementById('fichaSitioAviso');
            if (!p) return;
            p.textContent = texto;
            p.className = 'mt-1.5 text-[11px] ' + (error ? 'text-red-600' : 'text-emerald-600');
            p.classList.toggle('hidden', !texto);
        }

        async function guardarSitioDeEquipo(dispositivoId, sitioId) {
            try {
                const r = await fetch(@json(url('/api/topologia/dispositivos')) + '/' + dispositivoId + '/sitio', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ sitio_id: sitioId }),
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                // Un equipo pertenece a un solo sitio
                sitiosRed.lista.forEach(s => { s.eq = s.eq.filter(id => id !== dispositivoId); });
                if (sitioId) sitiosRed.lista.find(s => s.id === sitioId)?.eq.push(dispositivoId);
                pintarEquiposSitio();
                network.redraw();
                return true;
            } catch (e) {
                avisoFicha('No se pudo guardar: ' + e.message, true);
                return false;
            }
        }

        async function asignarEquipoASitio() {
            const input = document.getElementById('fichaSitioBuscar');
            const texto = input.value.trim().toLowerCase();
            const nodo = (nodesDataSet?.get({ filter: n => !!n.customData?.nombre }) || []).find(n =>
                `${n.customData.nombre} (${n.customData.ip})`.toLowerCase() === texto || n.customData.ip === texto || n.customData.nombre.toLowerCase() === texto);
            if (!nodo) { avisoFicha('Elige un equipo de la lista.', true); return; }
            const anterior = sitiosRed.lista.find(s => s.eq.includes(nodo.id));
            if (await guardarSitioDeEquipo(nodo.id, sitiosRed.seleccionado.id)) {
                input.value = '';
                avisoFicha(anterior && anterior !== sitiosRed.seleccionado ? `Asignado; antes estaba en "${anterior.n}".` : 'Asignado.', false);
            }
        }

        function cerrarFichaSitio() {
            document.getElementById('fichaSitio').classList.add('hidden');
            sitiosRed.seleccionado = null;
            avisoFicha('');
            network?.redraw();
        }

        function pintarPanelSitios() {
            const panel = document.getElementById('panelSitios');
            panel.classList.toggle('hidden', !sitiosActivos());
            if (!sitiosActivos()) { cerrarFichaSitio(); return; }
            const conteo = {};
            sitiosRed.lista.forEach(s => { conteo[s.c] = (conteo[s.c] || 0) + 1; });
            const lista = document.getElementById('panelSitiosLista');
            lista.replaceChildren(...Object.entries(CATEGORIAS_SITIO).filter(([c]) => conteo[c]).map(([c, cat]) => {
                const label = document.createElement('label');
                label.className = 'flex items-center gap-2 px-1.5 py-1 rounded-md hover:bg-slate-500/10 cursor-pointer';
                const cb = Object.assign(document.createElement('input'), { type: 'checkbox', checked: sitiosRed.visibles.has(c), className: 'rounded' });
                cb.style.accentColor = cat.color;
                cb.onchange = () => {
                    cb.checked ? sitiosRed.visibles.add(c) : sitiosRed.visibles.delete(c);
                    guardarPref('topo_sitios_categorias', JSON.stringify([...sitiosRed.visibles]));
                    if (sitiosRed.seleccionado && !sitiosRed.visibles.has(sitiosRed.seleccionado.c)) cerrarFichaSitio();
                    network.redraw();
                };
                const punto = Object.assign(document.createElement('i'), { className: 'w-2.5 h-2.5 rounded-full shrink-0' });
                punto.style.background = cat.color;
                const nombre = Object.assign(document.createElement('span'), { className: 'flex-1 text-slate-700 dark:text-slate-200', textContent: cat.etiqueta });
                const n = Object.assign(document.createElement('span'), { className: 'font-mono text-slate-500 dark:text-slate-400', textContent: conteo[c].toLocaleString('es-MX') });
                label.append(cb, punto, nombre, n);
                return label;
            }));
            document.getElementById('panelSitiosTotal').textContent = sitiosRed.lista.length.toLocaleString('es-MX') + ' sitios';
        }

        fetch(@json(route('topologia.sitios')), { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
            .then(j => { sitiosRed.lista = j.sitios; pintarPanelSitios(); network?.redraw(); })
            .catch(e => console.warn('No se pudieron cargar los sitios:', e));

        function dibujarZonas(ctx) {
            const isDark = document.documentElement.classList.contains('dark');
            const todas = [...zonas];
            if (zonaArrastre && zonaArrastre.tipo === 'nueva') todas.push(zonaArrastre.zona);
            if (zonaEnEditor && zonaEnEditor.esNueva) todas.push(zonaEnEditor.zona);
            todas.forEach(z => {
                ctx.save();
                ctx.fillStyle = hexToRgba(z.color, isDark ? 0.12 : 0.2);
                ctx.strokeStyle = hexToRgba(z.color, 0.85);
                ctx.lineWidth = px(2);
                if (zonaEditando) ctx.setLineDash([px(8), px(5)]);
                ctx.beginPath();
                ctx.rect(z.x, z.y, z.ancho, z.alto);
                ctx.fill(); ctx.stroke();
                ctx.setLineDash([]);

                // Franja superior delgada con el color de la zona
                ctx.fillStyle = hexToRgba(z.color, isDark ? 0.7 : 0.8);
                ctx.fillRect(z.x, z.y, z.ancho, Math.min(px(5), z.alto));

                // Etiqueta tipo pastilla: tamaño de PANTALLA constante (legible a cualquier zoom) y solo tan
                // ancha como el texto, en la esquina superior izquierda; no cubre la fila de equipos.
                // Nunca más ancha que la zona: así las etiquetas de zonas vecinas no se enciman.
                const anchoEnPantalla = z.ancho * network.getScale();
                if (anchoEnPantalla >= 56) {
                    const padX = px(9), padY = px(5), punto = px(8);
                    const disponible = z.ancho - px(16) - padX * 2 - punto - px(6);
                    let fs = px(14);
                    let texto = (z.nombre || '').toUpperCase();
                    ctx.font = `800 ${fs}px Inter, system-ui, sans-serif`;
                    if (ctx.measureText(texto).width > disponible) {
                        fs = px(11);
                        ctx.font = `800 ${fs}px Inter, system-ui, sans-serif`;
                    }
                    while (texto.length > 1 && ctx.measureText(texto).width > disponible) {
                        texto = texto.slice(0, -2) + '…';
                    }
                    ctx.textBaseline = 'middle';
                    const w = ctx.measureText(texto).width + padX * 2 + punto + px(6);
                    const h = fs + padY * 2;
                    const lx = z.x + px(8), ly = z.y + px(10);
                    ctx.beginPath();
                    if (ctx.roundRect) ctx.roundRect(lx, ly, w, h, h / 2); else ctx.rect(lx, ly, w, h);
                    ctx.fillStyle = isDark ? 'rgba(15, 23, 42, 0.92)' : 'rgba(255, 255, 255, 0.95)';
                    ctx.fill();
                    ctx.lineWidth = px(2);
                    ctx.strokeStyle = z.color;
                    ctx.stroke();
                    ctx.beginPath();
                    ctx.arc(lx + padX + punto / 2, ly + h / 2, punto / 2, 0, Math.PI * 2);
                    ctx.fillStyle = z.color;
                    ctx.fill();
                    ctx.fillStyle = isDark ? '#f8fafc' : '#0f172a';
                    ctx.fillText(texto, lx + padX + punto + px(6), ly + h / 2 + px(0.5));
                }

                ctx.beginPath();
                ctx.rect(z.x, z.y, z.ancho, z.alto);
                ctx.clip();

                if (zonaEditando) {
                    const h = px(14);
                    ctx.fillStyle = z.color;
                    ctx.fillRect(z.x + z.ancho - h, z.y + z.alto - h, h, h);

                    // Botones ✎ y 🗑 en el extremo derecho del título (solo si caben y la zona ya existe)
                    if (z.id && z.ancho > px(110)) {
                        const b = botonesZona(z);
                        [[b.editar, '✎', '#667d93'], [b.borrar, '🗑', '#dc2626']].forEach(([r, txt, col]) => {
                            ctx.fillStyle = '#ffffff';
                            ctx.strokeStyle = col;
                            ctx.lineWidth = px(1.5);
                            ctx.beginPath();
                            ctx.rect(r.x, r.y, r.s, r.s);
                            ctx.fill(); ctx.stroke();
                            ctx.fillStyle = col;
                            ctx.font = `700 ${px(13)}px Inter, system-ui, sans-serif`;
                            ctx.textAlign = 'center';
                            ctx.fillText(txt, r.x + r.s / 2, r.y + r.s / 2 + px(1));
                            ctx.textAlign = 'start';
                        });
                    }
                }
                ctx.restore();
            });
        }

        // Posición (lienzo) de los botones del título de una zona
        function botonesZona(z) {
            const s = px(22), m = px(4);
            return {
                borrar: { x: z.x + z.ancho - s - m, y: z.y + m, s },
                editar: { x: z.x + z.ancho - 2 * s - 2 * m, y: z.y + m, s },
            };
        }

        function zonaEnPunto(p) {
            for (let i = zonas.length - 1; i >= 0; i--) {
                const z = zonas[i];
                if (p.x < z.x || p.y < z.y || p.x > z.x + z.ancho || p.y > z.y + z.alto) continue;
                if (z.id && z.ancho > px(110)) {
                    const b = botonesZona(z);
                    const dentro = r => p.x >= r.x && p.x <= r.x + r.s && p.y >= r.y && p.y <= r.y + r.s;
                    if (dentro(b.borrar)) return { zona: z, parte: 'borrar' };
                    if (dentro(b.editar)) return { zona: z, parte: 'editar' };
                }
                const h = px(18);
                if (p.x >= z.x + z.ancho - h && p.y >= z.y + z.alto - h) return { zona: z, parte: 'resize' };
                if (p.y <= z.y + px(30)) return { zona: z, parte: 'titulo' };
                return { zona: z, parte: 'cuerpo' };
            }
            return null;
        }

        function toggleZonaEdicion(forzar) {
            zonaEditando = typeof forzar === 'boolean' ? forzar : !zonaEditando;
            document.body.classList.toggle('zonas-editando', zonaEditando);
            document.getElementById('btnZonas')?.classList.toggle('active', zonaEditando);
            if (zonaEditando) renderZonaLista();
            else cerrarZonaEditor();
            network?.redraw();
        }

        function initZonasInteraccion(container) {
            const domPos = ev => {
                const r = container.getBoundingClientRect();
                return { x: ev.clientX - r.left, y: ev.clientY - r.top };
            };

            // Captura antes que vis-network para que el arrastre de zonas no mueva la vista
            container.addEventListener('pointerdown', ev => {
                if (!zonaEditando || ev.button !== 0) return;
                const dom = domPos(ev);
                if (network.getNodeAt(dom) !== undefined) return; // sobre un equipo: lo maneja vis
                const p = network.DOMtoCanvas(dom);
                const hit = zonaEnPunto(p);
                ev.stopPropagation();
                ev.preventDefault();

                if (hit && hit.parte === 'borrar') { eliminarZona(hit.zona); return; }
                if (hit && hit.parte === 'editar') { abrirZonaEditor(hit.zona, false); return; }

                if (hit && hit.parte === 'resize') {
                    zonaArrastre = { tipo: 'resize', zona: hit.zona, inicio: p, orig: { ...hit.zona } };
                } else if (hit && hit.parte === 'titulo') {
                    // Mover la zona junto con los equipos que contiene
                    const z = hit.zona;
                    const posiciones = network.getPositions();
                    const dentro = Object.entries(posiciones)
                        .filter(([, q]) => q.x >= z.x && q.x <= z.x + z.ancho && q.y >= z.y && q.y <= z.y + z.alto)
                        .map(([id, q]) => ({ id: isNaN(id) ? id : Number(id), x: q.x, y: q.y }));
                    zonaArrastre = { tipo: 'mover', zona: z, inicio: p, orig: { ...z }, nodos: dentro };
                } else {
                    zonaArrastre = { tipo: 'nueva', inicio: p, zona: { id: null, nombre: '', color: ZONA_COLORES[zonas.length % ZONA_COLORES.length], x: p.x, y: p.y, ancho: 0, alto: 0 } };
                }
                container.setPointerCapture?.(ev.pointerId);
            }, true);

            container.addEventListener('pointermove', ev => {
                if (!zonaArrastre) return;
                ev.stopPropagation();
                const p = network.DOMtoCanvas(domPos(ev));
                const dx = p.x - zonaArrastre.inicio.x, dy = p.y - zonaArrastre.inicio.y;
                const z = zonaArrastre.zona, o = zonaArrastre.orig;
                if (zonaArrastre.tipo === 'resize') {
                    z.ancho = Math.max(80, o.ancho + dx);
                    z.alto = Math.max(80, o.alto + dy);
                } else if (zonaArrastre.tipo === 'mover') {
                    z.x = o.x + dx; z.y = o.y + dy;
                    zonaArrastre.nodos.forEach(n => network.moveNode(n.id, n.x + dx, n.y + dy));
                } else {
                    z.x = Math.min(p.x, zonaArrastre.inicio.x);
                    z.y = Math.min(p.y, zonaArrastre.inicio.y);
                    z.ancho = Math.abs(dx); z.alto = Math.abs(dy);
                }
                network.redraw();
            }, true);

            const terminar = async ev => {
                if (!zonaArrastre) return;
                ev.stopPropagation();
                const a = zonaArrastre;
                zonaArrastre = null;
                try {
                    if (a.tipo === 'nueva') {
                        if (a.zona.ancho >= 80 && a.zona.alto >= 80) abrirZonaEditor(a.zona, true);
                    } else {
                        await guardarZonaServidor(a.zona);
                        if (a.tipo === 'mover' && a.nodos.length) {
                            const pos = network.getPositions(a.nodos.map(n => n.id));
                            await apiJson("{{ route('topologia.guardar_posiciones') }}", 'POST', { posiciones: pos });
                        }
                    }
                } catch (e) {
                    nmAviso('No se pudo guardar la zona: ' + e.message, 'error');
                }
                network.redraw();
            };
            container.addEventListener('pointerup', terminar, true);
            container.addEventListener('pointercancel', terminar, true);

            container.addEventListener('dblclick', ev => {
                if (!zonaEditando) return;
                const dom = domPos(ev);
                if (network.getNodeAt(dom) !== undefined) return;
                const hit = zonaEnPunto(network.DOMtoCanvas(dom));
                if (hit) { ev.stopPropagation(); abrirZonaEditor(hit.zona, false); }
            }, true);
        }

        async function guardarZonaServidor(z) {
            const body = { nombre: z.nombre, color: z.color, rangos_ip: z.rangos_ip || null, x: z.x, y: z.y, ancho: z.ancho, alto: z.alto };
            if (z.id) {
                const r = await apiJson(`{{ url('api/topologia/zonas') }}/${z.id}`, 'PUT', body);
                z.id = r.zona.id; // cambia si el servidor tuvo que recrearla
            } else {
                const r = await apiJson("{{ route('topologia.zonas.store') }}", 'POST', body);
                z.id = r.zona.id;
            }
        }

        let zonaEnEditor = null;
        function abrirZonaEditor(z, esNueva) {
            zonaEnEditor = { zona: z, esNueva, color: z.color };
            document.getElementById('zonaEditorTitulo').textContent = esNueva ? 'Nueva zona' : 'Editar zona';
            const inp = document.getElementById('zonaEditorNombre');
            inp.value = z.nombre || '';
            const rng = document.getElementById('zonaEditorRangos');
            rng.value = z.rangos_ip || '';
            rng.oninput = () => infoRangos(rng.value);
            infoRangos(rng.value);
            const cont = document.getElementById('zonaEditorColores');
            cont.innerHTML = ZONA_COLORES.map(c =>
                `<button type="button" data-c="${c}" class="w-6 h-6 rounded-md border-2" style="background:${c}; border-color:${c === z.color ? '#0f172a' : 'transparent'}"></button>`).join('');
            cont.querySelectorAll('button').forEach(b => b.onclick = () => {
                zonaEnEditor.color = b.dataset.c;
                cont.querySelectorAll('button').forEach(x => x.style.borderColor = x.dataset.c === b.dataset.c ? '#0f172a' : 'transparent');
            });
            document.getElementById('zonaEditorEliminar').classList.toggle('hidden', esNueva);
            document.getElementById('zonaEditor').classList.remove('hidden');
            inp.focus();
        }

        function cerrarZonaEditor() {
            document.getElementById('zonaEditor')?.classList.add('hidden');
            zonaEnEditor = null;
            network?.redraw();
        }

        document.getElementById('zonaEditorGuardar')?.addEventListener('click', async () => {
            if (!zonaEnEditor) return;
            const nombre = document.getElementById('zonaEditorNombre').value.trim();
            if (!nombre) { document.getElementById('zonaEditorNombre').focus(); return; }
            const rangos = document.getElementById('zonaEditorRangos').value.trim();
            const invalidos = parseRangos(rangos).invalidos;
            if (invalidos.length) { nmAviso('Rangos no válidos: ' + invalidos.join(', '), 'error'); return; }
            const z = zonaEnEditor.zona;
            const previo = { nombre: z.nombre, color: z.color, rangos_ip: z.rangos_ip };
            z.nombre = nombre;
            z.color = zonaEnEditor.color;
            z.rangos_ip = rangos || null;
            try {
                await guardarZonaServidor(z);
                if (zonaEnEditor.esNueva) zonas.push(z);
                cerrarZonaEditor();
                renderZonaLista();
            } catch (e) {
                Object.assign(z, previo);
                nmAviso('No se pudo guardar la zona: ' + e.message, 'error');
            }
        });

        document.getElementById('zonaEditorEliminar')?.addEventListener('click', () => {
            if (zonaEnEditor && zonaEnEditor.zona.id) eliminarZona(zonaEnEditor.zona);
        });

        async function eliminarZona(z) {
            if (!(await nmConfirmar(`¿Eliminar la zona "${z.nombre}"? Los equipos no se borran.`, { titulo: 'Eliminar zona', boton: 'Eliminar' }))) return;
            try {
                await apiJson(`{{ url('api/topologia/zonas') }}/${z.id}`, 'DELETE');
                zonas = zonas.filter(x => x !== z);
                if (zonaEnEditor && zonaEnEditor.zona === z) cerrarZonaEditor();
                renderZonaLista();
                network.redraw();
            } catch (e) {
                nmAviso('No se pudo eliminar: ' + e.message, 'error');
            }
        }

        // Lista de zonas en la barra del modo edición: clic = encuadrar, ✎ = editar, 🗑 = borrar
        function renderZonaLista() {
            const cont = document.getElementById('zonaLista');
            if (!cont) return;
            if (!zonas.length) {
                cont.innerHTML = '<span class="text-slate-400">Sin zonas. Dibuja una arrastrando en un espacio vacío o usa "Zonas por subred".</span>';
                return;
            }
            cont.innerHTML = zonas.map((z, i) => `
                <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-lg bg-slate-800 border border-slate-700">
                    <i class="noc-dot" style="background:${escHtml(z.color)}"></i>
                    <button type="button" class="font-bold hover:underline" onclick="encuadrarZona(${i})" title="${escHtml(z.rangos_ip || 'Sin rangos de IP')}" aria-label="${escHtml(z.rangos_ip || 'Sin rangos de IP')}">${escHtml(z.nombre)}</button>
                    <span class="text-slate-400 font-mono">${equiposEnRangos(z.rangos_ip).length || ''}</span>
                    <button type="button" class="px-1 hover:text-blue-300" onclick="abrirZonaEditor(zonas[${i}], false)" title="Editar" aria-label="Editar zona"><svg class="w-3.5 h-3.5 inline-block align-[-2px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /> <path d="m15 5 4 4" /></svg></button>
                    <button type="button" class="px-1 hover:text-red-400" onclick="eliminarZona(zonas[${i}])" title="Borrar" aria-label="Borrar zona"><svg class="w-3.5 h-3.5 inline-block align-[-2px]" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /> <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /> <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" /> <line x1="10" x2="10" y1="11" y2="17" /> <line x1="14" x2="14" y1="11" y2="17" /></svg></button>
                </span>`).join('');
        }

        function encuadrarZona(i) {
            const z = zonas[i];
            if (!z || !network) return;
            const cont = document.getElementById('networkTopologyCanvas');
            const escala = Math.min(cont.clientWidth / (z.ancho * 1.15), cont.clientHeight / (z.alto * 1.25));
            network.moveTo({ position: { x: z.x + z.ancho / 2, y: z.y + z.alto / 2 }, scale: escala, animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
        }

        // ==================== RANGOS DE IP Y ACOMODO AUTOMÁTICO ====================
        function ipAEntero(ip) {
            const p = String(ip || '').trim().split('.');
            if (p.length !== 4 || p.some(x => !/^\d{1,3}$/.test(x) || +x > 255)) return null;
            return ((+p[0] << 24) >>> 0) + (+p[1] << 16) + (+p[2] << 8) + (+p[3]);
        }

        // "10.4.155.0/24, 10.4.20.*, 10.4.1.10-10.4.1.50, 10.4.9.7" -> lista de [desde, hasta]
        function parseRangos(texto) {
            const rangos = [], invalidos = [];
            String(texto || '').split(',').map(t => t.trim()).filter(Boolean).forEach(t => {
                let m;
                if ((m = t.match(/^([\d.]+)\/(\d{1,2})$/))) {
                    const base = ipAEntero(m[1]), bits = +m[2];
                    if (base === null || bits > 32) return invalidos.push(t);
                    const mask = bits === 0 ? 0 : (0xFFFFFFFF << (32 - bits)) >>> 0;
                    const ini = (base & mask) >>> 0;
                    rangos.push([ini, (ini | (~mask >>> 0)) >>> 0]);
                } else if (t.includes('*')) {
                    const partes = t.split('.');
                    if (partes.length !== 4) return invalidos.push(t);
                    const ini = ipAEntero(partes.map(x => x === '*' ? '0' : x).join('.'));
                    const fin = ipAEntero(partes.map(x => x === '*' ? '255' : x).join('.'));
                    if (ini === null || fin === null) return invalidos.push(t);
                    rangos.push([ini, fin]);
                } else if ((m = t.match(/^([\d.]+)\s*-\s*([\d.]+)$/))) {
                    const a = ipAEntero(m[1]), b = ipAEntero(m[2]);
                    if (a === null || b === null) return invalidos.push(t);
                    rangos.push([Math.min(a, b), Math.max(a, b)]);
                } else {
                    const a = ipAEntero(t);
                    if (a === null) return invalidos.push(t);
                    rangos.push([a, a]);
                }
            });
            return { rangos, invalidos };
        }

        function equiposEnRangos(texto) {
            const { rangos } = parseRangos(texto);
            if (!rangos.length || !nodesDataSet) return [];
            return nodesDataSet.get().filter(n => {
                const v = ipAEntero(n.customData?.ip);
                return v !== null && rangos.some(([a, b]) => v >= a && v <= b);
            });
        }

        function infoRangos(texto) {
            const el = document.getElementById('zonaEditorRangosInfo');
            if (!el) return;
            const { invalidos } = parseRangos(texto);
            if (invalidos.length) { el.textContent = 'No válido: ' + invalidos.join(', '); el.className = 'text-[11px] text-red-500 mb-3'; return; }
            const n = texto.trim() ? equiposEnRangos(texto).length : 0;
            el.textContent = texto.trim() ? `${n} equipos coinciden` : 'Sin rangos: la zona no se acomoda automáticamente';
            el.className = 'text-[11px] text-slate-400 mb-3';
        }

        // Coloca los equipos dentro de la zona en filas, según el tamaño REAL de cada uno (un switch de rack
        // mide ~1450 unidades de ancho y un teléfono ~220), y agranda la zona si no caben.
        // Los equipos finales agrupados se ponen alrededor de su switch.
        // Ancho y alto del equipo en el lienzo: tamaño de su IMAGEN más espacio fijo para la etiqueta.
        // (getBoundingBox incluye la etiqueta, cuyo tamaño depende del zoom y la inflaba hasta decenas de miles)
        function medirNodo(id) {
            const forma = network?.body?.nodes?.[id]?.shape;
            if (forma && forma.width > 0 && forma.height > 0) {
                return { w: Math.max(260, forma.width), h: forma.height + 140 };
            }
            const size = nodesDataSet?.get(id)?.size || 120;
            return { w: Math.max(260, size * 2), h: size * 2 + 140 };
        }

        function cuadriculaEnZona(z, nodos, posiciones) {
            const visibles = nodos.filter(n => !(endpointsAgrupados && padreDeHijo[n.id] !== undefined));
            const ocultos = nodos.filter(n => endpointsAgrupados && padreDeHijo[n.id] !== undefined);
            visibles.sort((a, b) => (ipAEntero(a.customData?.ip) || 0) - (ipAEntero(b.customData?.ip) || 0));
            if (visibles.length) {
                const medidas = visibles.map(n => medirNodo(n.id));
                // Ancho objetivo: zona aproximadamente cuadrada, nunca más angosta que el equipo más ancho
                const area = medidas.reduce((s, m) => s + (m.w + SEP_X) * (m.h + SEP_Y), 0);
                const anchoMax = Math.max(...medidas.map(m => m.w));
                const anchoObjetivo = Math.max(z.ancho - 2 * MARGEN, anchoMax, Math.sqrt(area * 1.4));

                let fx = 0, fy = 0, altoFila = 0, anchoUsado = 0;
                visibles.forEach((n, i) => {
                    const m = medidas[i];
                    if (fx > 0 && fx + m.w > anchoObjetivo) { fy += altoFila + SEP_Y; fx = 0; altoFila = 0; }
                    posiciones[n.id] = { x: z.x + MARGEN + fx + m.w / 2, y: z.y + TITULO + fy + m.h / 2 };
                    fx += m.w + SEP_X;
                    anchoUsado = Math.max(anchoUsado, fx - SEP_X);
                    altoFila = Math.max(altoFila, m.h);
                });
                z.ancho = Math.max(z.ancho, anchoUsado + 2 * MARGEN);
                z.alto = Math.max(z.alto, TITULO + fy + altoFila + MARGEN);
            }
            rodearPadres(ocultos, posiciones);
        }

        // Equipos finales agrupados: en círculo alrededor de su switch (para cuando se muestren),
        // por fuera del dibujo del switch
        function rodearPadres(hijos, posiciones) {
            const porPadre = {};
            hijos.forEach(n => (porPadre[padreDeHijo[n.id]] = porPadre[padreDeHijo[n.id]] || []).push(n));
            Object.entries(porPadre).forEach(([pid, lista]) => {
                const padreId = isNaN(pid) ? pid : Number(pid);
                const padre = posiciones[padreId] || network.getPositions([padreId])[padreId];
                if (!padre) return;
                const radio = Math.max(260, medirNodo(padreId).w / 2 + 220);
                lista.forEach((n, i) => {
                    const ang = (i / lista.length) * Math.PI * 2;
                    posiciones[n.id] = { x: padre.x + Math.cos(ang) * radio, y: padre.y + Math.sin(ang) * radio * 0.6 };
                });
            });
        }

        // ==================== ACOMODO AUTOMÁTICO: árbol desde el núcleo ====================
        // Raíz: el switch core de Configuración (o el equipo con más enlaces) y su pareja si la tiene (Core
        // primario/secundario). Cada equipo cuelga del vecino del nivel anterior con menos hijos, los hijos se
        // ordenan por IP y, si son muchos, se reparten en varias filas. Se usa el ancho real de cada panel.
        const IP_CORE = @json($config->ip_switch_core ?? null);
        const ARBOL_SEP_X = 140, ARBOL_SEP_NIVEL = 240, ARBOL_SEP_GRUPO = 480;

        // Botón "Núcleo": centra el switch core con el equipo típico a ~180 px de ancho (solo cuando se pide;
        // al abrir se muestra la red completa)
        function vistaLegible(animar = true) {
            if (!network || !nodesDataSet) return;
            const ids = grafoVisible().ids;
            if (!ids.length) return;
            network.fit({ animation: false, padding: 80 });
            const escalaTodo = network.getScale();
            const anchos = ids.map(id => medirNodo(id).w).sort((a, b) => a - b);
            const escala = Math.min(1, Math.max(escalaTodo, 180 / anchos[anchos.length >> 1]));
            if (escala <= escalaTodo * 1.05) return ajustarEtiquetas();   // todo cabe a buen tamaño
            const core = ids.find(id => nodesDataSet.get(id)?.customData?.ip === IP_CORE);
            const pos = network.getPositions(ids);
            const centro = core !== undefined ? pos[core]
                : ids.map(id => pos[id]).reduce((a, p) => ({ x: Math.min(a.x, p.x), y: Math.min(a.y, p.y) }), { x: Infinity, y: Infinity });
            const lienzo = document.getElementById('networkTopologyCanvas');
            // El núcleo queda arriba al centro: debajo está su árbol
            network.moveTo({
                position: { x: centro.x, y: centro.y + (lienzo.clientHeight * 0.32) / escala },
                scale: escala,
                animation: animar ? { duration: 600, easingFunction: 'easeInOutQuad' } : false,
            });
            if (!animar) ajustarEtiquetas();
        }

        // Estilo de los nombres de equipo: azul profundo con un halo blanco grueso, para que resalten
        // sobre el mapa claro y las líneas de enlace sin tapar nada (el halo crece con la letra en ajustarEtiquetas)
        function fuenteEtiqueta(oscuro) {
            return {
                color: oscuro ? '#ffffff' : '#0f2f63',
                strokeColor: oscuro ? '#0b1d3a' : '#ffffff',
                strokeWidth: 5,
                // Sin "mod: bold": vis-network cambiaría al estilo bold propio (gris #343434) e ignoraría este color
                face: 'Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                size: 16,
                vadjust: 38,
            };
        }

        // Nombres legibles a cualquier zoom: ~14 px en pantalla, sin pasar del ancho de su equipo (no se enciman)
        let escalaEtiquetas = null, ajusteEtiquetasPendiente = false;
        function ajustarEtiquetas() {
            if (!network || !nodesDataSet || ajusteEtiquetasPendiente) return;
            ajusteEtiquetasPendiente = true;
            requestAnimationFrame(() => {
                ajusteEtiquetasPendiente = false;
                const escala = Math.max(network.getScale(), 0.005);
                if (escalaEtiquetas && Math.abs(escala / escalaEtiquetas - 1) < 0.08) return;
                escalaEtiquetas = escala;
                const cambios = [];
                nodesDataSet.get().forEach(n => {
                    if (!n.font) return;
                    const texto = String(n.label || '');
                    const largo = Math.max(...texto.split('\n').map(l => l.length), 4);
                    const tope = (medirNodo(n.id).w * 1.05) / (largo * 0.56);
                    const tam = Math.round(Math.max(16, Math.min(14 / escala, tope)));
                    if (n.font.size !== tam) cambios.push({ id: n.id, font: { ...n.font, size: tam, strokeWidth: Math.max(5, tam * 0.3) } });
                });
                if (cambios.length) nodesDataSet.update(cambios);
            });
        }

        function grafoVisible() {
            const oculto = id => endpointsAgrupados && padreDeHijo[id] !== undefined;
            const ids = nodesDataSet.getIds().filter(id => !oculto(id));
            const enVista = new Set(ids);
            const ady = new Map(ids.map(id => [id, new Set()]));
            edgesDataSet.get().forEach(e => {
                if (e.from !== e.to && enVista.has(e.from) && enVista.has(e.to)) {
                    ady.get(e.from).add(e.to);
                    ady.get(e.to).add(e.from);
                }
            });
            return { ids, ady, ocultos: nodesDataSet.getIds().filter(oculto) };
        }

        const ipDe = id => ipAEntero(nodesDataSet.get(id)?.customData?.ip) || 0;

        function calcularArbol(ids, ady) {
            const grado = id => ady.get(id).size;
            const hijos = new Map(ids.map(id => [id, []]));
            const visitados = new Set();
            const grupos = [];
            const orden = [...ids].sort((a, b) => grado(b) - grado(a));
            const core = ids.find(id => nodesDataSet.get(id)?.customData?.ip === IP_CORE);
            if (core !== undefined) {
                orden.splice(orden.indexOf(core), 1);
                orden.unshift(core);
            }

            for (const inicio of orden) {
                if (visitados.has(inicio) || grado(inicio) === 0) continue;
                // Pareja de núcleo: vecinos con al menos la mitad de enlaces que la raíz
                const raices = [inicio, ...[...ady.get(inicio)].filter(v => !visitados.has(v) && grado(v) >= 4 && grado(v) >= grado(inicio) * 0.5)]
                    .sort((a, b) => ipDe(a) - ipDe(b));
                raices.forEach(r => visitados.add(r));
                let nivel = raices;
                while (nivel.length) {
                    const padresPosibles = new Map();
                    nivel.forEach(p => ady.get(p).forEach(v => {
                        if (!visitados.has(v)) (padresPosibles.get(v) || padresPosibles.set(v, []).get(v)).push(p);
                    }));
                    const siguiente = [];
                    // Primero los que solo tienen un padre posible; los demás van al padre con menos hijos (reparte entre cores)
                    [...padresPosibles.entries()]
                        .sort((a, b) => a[1].length - b[1].length || ipDe(a[0]) - ipDe(b[0]))
                        .forEach(([v, padres]) => {
                            const p = padres.reduce((m, q) => hijos.get(q).length < hijos.get(m).length ? q : m, padres[0]);
                            hijos.get(p).push(v);
                            visitados.add(v);
                            siguiente.push(v);
                        });
                    nivel = siguiente;
                }
                grupos.push(raices);
            }
            return { hijos, grupos, aislados: ids.filter(id => !visitados.has(id)).sort((a, b) => ipDe(a) - ipDe(b)) };
        }

        // Filas de una lista de cajas: se llena cada fila hasta un ancho objetivo que deja el conjunto
        // aproximadamente cuadrado (nunca más angosto que la caja más ancha)
        function enFilas(lista, cajas) {
            const area = lista.reduce((s, h) => s + (cajas[h].w + ARBOL_SEP_X) * (cajas[h].h + ARBOL_SEP_NIVEL), 0);
            const objetivo = Math.max(...lista.map(h => cajas[h].w), Math.sqrt(area) * 1.6);
            const filas = [];
            let fila = null;
            lista.forEach(h => {
                if (!fila || fila.w + ARBOL_SEP_X + cajas[h].w > objetivo) {
                    fila = { ids: [], w: -ARBOL_SEP_X, h: 0 };
                    filas.push(fila);
                }
                fila.ids.push(h);
                fila.w += ARBOL_SEP_X + cajas[h].w;
                fila.h = Math.max(fila.h, cajas[h].h);
            });
            return filas;
        }

        function cajaArbol(id, hijos, cajas) {
            const m = medirNodo(id);
            const hs = (hijos.get(id) || []).sort((a, b) => ipDe(a) - ipDe(b));
            hs.forEach(h => cajaArbol(h, hijos, cajas));
            const filas = hs.length ? enFilas(hs, cajas) : [];
            const anchoFilas = Math.max(0, ...filas.map(f => f.w));
            const altoFilas = filas.reduce((s, f) => s + ARBOL_SEP_NIVEL + f.h, 0);
            return cajas[id] = { w: Math.max(m.w, anchoFilas), h: m.h + altoFilas, m, filas };
        }

        function colocarArbol(id, x0, y0, cajas, pos) {
            const c = cajas[id];
            pos[id] = { x: x0 + c.w / 2, y: y0 + c.m.h / 2 };
            let y = y0 + c.m.h;
            c.filas.forEach(f => {
                y += ARBOL_SEP_NIVEL;
                let x = x0 + (c.w - f.w) / 2;
                f.ids.forEach(h => { colocarArbol(h, x, y, cajas, pos); x += cajas[h].w + ARBOL_SEP_X; });
                y += f.h;
            });
        }

        function calcularAcomodo() {
            const { ids, ady, ocultos } = grafoVisible();
            const { hijos, grupos, aislados } = calcularArbol(ids, ady);
            const cajas = {};
            // Cada grupo conectado es un bloque: sus raíces lado a lado y su árbol debajo
            const bloques = grupos.map(raices => {
                raices.forEach(r => cajaArbol(r, hijos, cajas));
                return { raices, w: raices.reduce((s, r) => s + cajas[r].w, 0) + ARBOL_SEP_X * (raices.length - 1), h: Math.max(...raices.map(r => cajas[r].h)) };
            }).sort((a, b) => b.w * b.h - a.w * a.h);
            // Equipos sin enlaces: una cuadrícula al final
            if (aislados.length) {
                aislados.forEach(id => cajas[id] = { w: medirNodo(id).w, h: medirNodo(id).h, m: medirNodo(id), filas: [] });
                const filas = enFilas(aislados, cajas);
                bloques.push({ aislados: filas, w: Math.max(...filas.map(f => f.w)), h: filas.reduce((s, f) => s + f.h + ARBOL_SEP_NIVEL, 0) });
            }

            // Bloques en filas: el más grande arriba a la izquierda
            const areaTotal = bloques.reduce((s, b) => s + (b.w + ARBOL_SEP_GRUPO) * (b.h + ARBOL_SEP_GRUPO), 0);
            const anchoMax = Math.max(...bloques.map(b => b.w), Math.sqrt(areaTotal * 1.6));
            const pos = {};
            let x = 0, y = 0, altoFila = 0;
            bloques.forEach(b => {
                if (x > 0 && x + b.w > anchoMax) { x = 0; y += altoFila + ARBOL_SEP_GRUPO; altoFila = 0; }
                if (b.aislados) {
                    let yy = y;
                    b.aislados.forEach(f => {
                        let xx = x;
                        f.ids.forEach(id => { pos[id] = { x: xx + cajas[id].w / 2, y: yy + cajas[id].m.h / 2 }; xx += cajas[id].w + ARBOL_SEP_X; });
                        yy += f.h + ARBOL_SEP_NIVEL;
                    });
                } else {
                    let xr = x;
                    b.raices.forEach(r => { colocarArbol(r, xr, y, cajas, pos); xr += cajas[r].w + ARBOL_SEP_X; });
                }
                x += b.w + ARBOL_SEP_GRUPO;
                altoFila = Math.max(altoFila, b.h);
            });
            // Equipos finales agrupados: alrededor de su switch
            rodearPadres(nodesDataSet.get(ocultos), pos);
            return pos;
        }

        function ordenarTopologia({ encuadrar = true } = {}) {
            if (!network || !nodesDataSet) return;
            const pos = calcularAcomodo();
            Object.entries(pos).forEach(([id, p]) => network.moveNode(isNaN(id) ? id : Number(id), p.x, p.y));
            network.storePositions();
            saveNodePositionsToDatabase(network.getPositions());
            if (encuadrar) resetZoom();
        }

        // Equipos nuevos (sin posición guardada): debajo de un vecino ya colocado, sin mover a los demás.
        // Los que no tienen vecinos colocados van en una fila debajo de todo el plano.
        function acomodarFaltantes(ids) {
            if (!network || !ids.length) return;
            const faltan = new Set(ids);
            const pos = network.getPositions();
            const vecinos = id => network.getConnectedNodes(id);
            const usados = {};   // vecinoId -> cuántos nuevos ya cuelgan de él
            const colocar = (id, x, y) => { network.moveNode(id, x, y); pos[id] = { x, y }; faltan.delete(id); };
            let avance = true;
            while (faltan.size && avance) {
                avance = false;
                for (const id of [...faltan]) {
                    const ancla = vecinos(id).find(v => !faltan.has(v) && pos[v]);
                    if (ancla === undefined) continue;
                    const n = usados[ancla] = (usados[ancla] || 0) + 1;
                    const ma = medirNodo(ancla), mi = medirNodo(id);
                    // Abanico debajo del vecino: 0, +1, -1, +2, -2…
                    const paso = (n % 2 ? 1 : -1) * Math.floor(n / 2);
                    colocar(id, pos[ancla].x + paso * (mi.w + ARBOL_SEP_X), pos[ancla].y + ma.h / 2 + ARBOL_SEP_NIVEL + mi.h / 2 + Math.floor((n - 1) / 7) * (mi.h + 120));
                    avance = true;
                }
            }
            if (faltan.size) {
                const ys = Object.entries(pos).filter(([k]) => !faltan.has(isNaN(k) ? k : Number(k))).map(([, p]) => p.y);
                let x = 0;
                const y = (ys.length ? Math.max(...ys) : 0) + 900;
                [...faltan].forEach(id => { const m = medirNodo(id); colocar(id, x + m.w / 2, y); x += m.w + ARBOL_SEP_X; });
            }
            network.storePositions();
            saveNodePositionsToDatabase(network.getPositions());
        }

        async function aplicarPosiciones(posiciones) {
            Object.entries(posiciones).forEach(([id, p]) => network.moveNode(isNaN(id) ? id : Number(id), p.x, p.y));
            await apiJson("{{ route('topologia.guardar_posiciones') }}", 'POST', { posiciones });
        }

        async function acomodarPorIps() {
            const conRangos = zonas.filter(z => z.id && parseRangos(z.rangos_ip).rangos.length);
            if (!conRangos.length) {
                nmAviso('Ninguna zona tiene rangos de IP. Edita una zona (✎) y escribe por ejemplo: 10.4.155.0/24', 'advertencia');
                return;
            }
            const asignados = new Set();
            const posiciones = {};
            const resumen = [];
            for (const z of conRangos) {
                // Un equipo va a la primera zona cuyo rango lo incluya
                const nodos = equiposEnRangos(z.rangos_ip).filter(n => !asignados.has(n.id));
                nodos.forEach(n => asignados.add(n.id));
                cuadriculaEnZona(z, nodos, posiciones);
                resumen.push(`${z.nombre}: ${nodos.length}`);
            }
            try {
                await aplicarPosiciones(posiciones);
                for (const z of conRangos) await guardarZonaServidor(z);
                renderZonaLista();
                network.redraw();
                nmAviso('Equipos acomodados:\n' + resumen.join('\n'), 'exito');
            } catch (e) {
                nmAviso('No se pudo guardar el acomodo: ' + e.message, 'error');
            }
        }

        async function crearZonasPorSubred() {
            if (!nodesDataSet) return;
            // Agrupar por /24 (los equipos finales agrupados viajan con su switch)
            const porSubred = {};
            const agrupados = [];
            nodesDataSet.get().forEach(n => {
                if (endpointsAgrupados && padreDeHijo[n.id] !== undefined) { agrupados.push(n); return; }
                const v = ipAEntero(n.customData?.ip);
                if (v === null) return;
                const red = `${(v >>> 24) & 255}.${(v >>> 16) & 255}.${(v >>> 8) & 255}.0/24`;
                (porSubred[red] = porSubred[red] || []).push(n);
            });
            const redes = Object.keys(porSubred).sort((a, b) => ipAEntero(a.split('/')[0]) - ipAEntero(b.split('/')[0]));
            if (!redes.length) return;
            const msg = zonas.length
                ? `Se crearán ${redes.length} zonas (una por subred /24) y se BORRARÁN las ${zonas.length} zonas actuales. ¿Continuar?`
                : `Se crearán ${redes.length} zonas (una por subred /24) y se acomodarán sus equipos. ¿Continuar?`;
            if (!(await nmConfirmar(msg, { titulo: 'Crear zonas por subred', boton: 'Crear zonas', peligro: false }))) return;

            try {
                await apiJson("{{ route('topologia.zonas.destroy_all') }}", 'DELETE'); // una sola petición: borra también zonas que el mapa no tenga cargadas
                zonas = [];

                // 1) Tamaño de cada zona según sus equipos (calculado en el origen)
                const SEP_ZONAS = 320;
                const calculadas = redes.map((red, i) => {
                    const z = { id: null, nombre: red, color: ZONA_COLORES[i % ZONA_COLORES.length], rangos_ip: red, x: 0, y: 0, ancho: 600, alto: 500 };
                    const locales = {};
                    cuadriculaEnZona(z, porSubred[red], locales);
                    return { z, locales };
                });

                // 2) Zonas en filas, ordenadas por subred; el ancho de fila se ajusta al tamaño total del mapa
                const areaTotal = calculadas.reduce((s, c) => s + (c.z.ancho + SEP_ZONAS) * (c.z.alto + SEP_ZONAS), 0);
                const anchoFila = Math.max(6000, Math.max(...calculadas.map(c => c.z.ancho)), Math.sqrt(areaTotal * 1.6));
                const posiciones = {};
                let x = 0, y = 0, altoFila = 0;
                for (const { z, locales } of calculadas) {
                    if (x > 0 && x + z.ancho > anchoFila) { x = 0; y += altoFila + SEP_ZONAS; altoFila = 0; }
                    z.x = x; z.y = y;
                    Object.entries(locales).forEach(([id, p]) => { posiciones[id] = { x: p.x + x, y: p.y + y }; });
                    await guardarZonaServidor(z);
                    zonas.push(z);
                    x += z.ancho + SEP_ZONAS;
                    altoFila = Math.max(altoFila, z.alto);
                }
                rodearPadres(agrupados, posiciones);
                await aplicarPosiciones(posiciones);
                renderZonaLista();
                network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' }, padding: 60 });
            } catch (e) {
                nmAviso('No se pudieron crear las zonas: ' + e.message, 'error');
                renderZonaLista();
            }
        }

        // ==================== NOTAS DEL EQUIPO ====================
        let notasDispositivoId = null;
        let notaEditandoId = null;
        let notaEditandoSecreta = false;

        async function cargarNotas(dispId) {
            notasDispositivoId = dispId;
            cerrarFormNota();
            const lista = document.getElementById('notasLista');
            lista.innerHTML = '<p class="text-[11px] text-slate-400">Cargando…</p>';
            try {
                const data = await apiJson(`{{ url('api/dispositivos') }}/${dispId}/notas`);
                if (notasDispositivoId !== dispId) return;
                document.getElementById('btnNuevaNota').classList.toggle('hidden', !data.puede_editar);
                renderNotas(data.notas, data.puede_editar);
                // Mantener al día el icono de nota del nodo
                const node = nodesDataSet.get(dispId);
                if (node && (node.customData?.notas || 0) !== data.notas.length) {
                    nodesDataSet.update({ id: dispId, customData: { ...node.customData, notas: data.notas.length } });
                }
            } catch (e) {
                lista.innerHTML = `<p class="text-[11px] text-red-500">No se pudieron cargar las notas: ${escHtml(e.message)}</p>`;
            }
        }

        let notasCache = [];
        function renderNotas(notas, puedeEditar) {
            notasCache = notas;
            const lista = document.getElementById('notasLista');
            if (!notas.length) {
                lista.innerHTML = '<p class="text-[11px] text-slate-400 italic">Sin notas.</p>';
                return;
            }
            lista.innerHTML = notas.map(n => `
                <div class="p-2 rounded-lg bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200/70 dark:border-amber-900/40 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-bold text-slate-800 dark:text-slate-100 truncate">${n.es_secreto ? '<svg class="w-3.5 h-3.5 inline-block align-[-2px] mr-1" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>' : ''}${escHtml(n.titulo)}</span>
                        ${puedeEditar ? `<span class="flex gap-2 shrink-0 text-[11px] font-bold">
                            <button type="button" class="text-blue-600 dark:text-blue-400 hover:underline" onclick="abrirFormNota(${n.id})">Editar</button>
                            <button type="button" class="text-red-600 hover:underline" onclick="eliminarNota(${n.id})">Borrar</button>
                        </span>` : ''}
                    </div>
                    <div class="mt-1 font-mono text-[11px] text-slate-700 dark:text-slate-300 whitespace-pre-wrap break-words" id="notaContenido_${n.id}">${
                        n.es_secreto ? '••••••••' : escHtml(n.contenido)
                    }</div>
                    ${n.es_secreto && puedeEditar ? `<div class="mt-1 flex gap-2 text-[11px] font-bold">
                        <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" onclick="revelarNota(${n.id}, false)">Mostrar</button>
                        <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" onclick="revelarNota(${n.id}, true)">Copiar</button>
                    </div>` : ''}
                    <p class="mt-1 text-[11px] text-slate-400">${escHtml(n.autor || '')} · ${escHtml(n.actualizado || '')}</p>
                </div>`).join('');
        }

        async function revelarNota(id, copiar) {
            try {
                const r = await apiJson(`{{ url('api/dispositivos') }}/${notasDispositivoId}/notas/${id}/revelar`, 'POST');
                if (copiar) {
                    await navigator.clipboard.writeText(r.contenido);
                    return;
                }
                const el = document.getElementById('notaContenido_' + id);
                if (!el) return;
                el.textContent = r.contenido;
                setTimeout(() => { if (el.isConnected) el.textContent = '••••••••'; }, 30000); // se vuelve a ocultar
            } catch (e) {
                nmAviso('No se pudo revelar: ' + e.message, 'error');
            }
        }

        function abrirFormNota(id = null) {
            const n = id ? notasCache.find(x => x.id === id) : null;
            notaEditandoId = id;
            notaEditandoSecreta = !!(n && n.es_secreto);
            document.getElementById('notaTitulo').value = n ? n.titulo : '';
            document.getElementById('notaContenido').value = n && !n.es_secreto ? (n.contenido || '') : '';
            document.getElementById('notaContenido').placeholder = notaEditandoSecreta ? 'Dejar vacío para no cambiar el secreto' : 'Contenido';
            document.getElementById('notaSecreto').checked = n ? n.es_secreto : false;
            document.getElementById('notaForm').classList.remove('hidden');
            document.getElementById('notaTitulo').focus();
        }

        function cerrarFormNota() {
            document.getElementById('notaForm')?.classList.add('hidden');
            notaEditandoId = null;
        }

        async function guardarNota() {
            const body = {
                titulo: document.getElementById('notaTitulo').value.trim(),
                contenido: document.getElementById('notaContenido').value,
                es_secreto: document.getElementById('notaSecreto').checked
            };
            if (!body.titulo) { document.getElementById('notaTitulo').focus(); return; }
            try {
                const base = `{{ url('api/dispositivos') }}/${notasDispositivoId}/notas`;
                if (notaEditandoId) await apiJson(`${base}/${notaEditandoId}`, 'PUT', body);
                else await apiJson(base, 'POST', body);
                cargarNotas(notasDispositivoId);
            } catch (e) {
                nmAviso('No se pudo guardar la nota: ' + e.message, 'error');
            }
        }

        async function eliminarNota(id) {
            if (!(await nmConfirmar('¿Borrar esta nota?', { titulo: 'Borrar nota', boton: 'Borrar' }))) return;
            try {
                await apiJson(`{{ url('api/dispositivos') }}/${notasDispositivoId}/notas/${id}`, 'DELETE');
                cargarNotas(notasDispositivoId);
            } catch (e) {
                nmAviso('No se pudo borrar: ' + e.message, 'error');
            }
        }

        // ==================== ESTADO EN VIVO (cada 30 s, sin mover el lienzo) ====================
        function actualizarResumen(r) {
            if (!r) return;
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            set('kpiDispOnline', r.disp_online);
            set('kpiDispWarning', r.disp_warning);
            set('kpiDispOffline', r.disp_offline);
            set('kpiEnlUp', r.enl_up);
            set('kpiEnlDegraded', r.enl_degraded);
            set('kpiEnlDown', r.enl_down);
            set('kpiEnlUnknown', r.enl_unknown);
            const f = document.getElementById('kpiFrescura');
            if (f) f.dataset.ts = r.ultimo_sondeo || '';
            pintarFrescura();
        }

        function pintarFrescura() {
            const f = document.getElementById('kpiFrescura');
            if (!f) return;
            if (!f.dataset.ts) { f.textContent = 'Sin dato'; return; }
            const s = Math.max(0, Math.round((Date.now() - new Date(f.dataset.ts).getTime()) / 1000));
            f.textContent = s < 60 ? `hace ${s} s` : (s < 3600 ? `hace ${Math.floor(s / 60)} min` : `hace ${Math.floor(s / 3600)} h`);
            f.classList.toggle('noc-stale', s > 180); // el poller dejó de reportar
            f.title = new Date(f.dataset.ts).toLocaleString();
        }

        async function refrescarEstadoVivo() {
            if (document.hidden || !nodesDataSet || !edgesDataSet || zonaArrastre) return;
            try {
                const res = await fetch("{{ route('topologia.datos') }}", { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                actualizarResumen(data.resumen);

                // Equipos o enlaces nuevos (descubrimiento) o retirados: se recarga el grafo conservando el acomodo
                const mismos = (a, b) => a.length === b.length && a.every(id => b.includes(id));
                if (!mismos((data.nodes || []).map(n => n.id), nodesDataSet.getIds())
                    || !mismos((data.edges || []).map(e => e.id), edgesDataSet.getIds())) {
                    if (!zonaArrastre) await reloadTopologyData();
                    return;
                }

                const campos = ['estado', 'statusColor', 'cpu', 'memoria', 'ping', 'uptime', 'conexiones', 'notas'];
                const nodosUpd = [];
                (data.nodes || []).forEach(n => {
                    const cur = nodesDataSet.get(n.id);
                    if (!cur) return;
                    const cd = { ...cur.customData };
                    campos.forEach(k => { cd[k] = n.customData?.[k]; });
                    nodosUpd.push({ id: n.id, customData: cd });
                });
                nodesDataSet.update(nodosUpd);

                const enlUpd = [];
                (data.edges || []).forEach(e => {
                    if (!edgesDataSet.get(e.id)) return;
                    enlUpd.push({ id: e.id, color: e.color, width: e.width, dashes: e.dashes, customData: e.customData, label: e.label });
                });
                edgesDataSet.update(enlUpd);
                ajustarGrosorCables();
                initialGrafoData.edges = data.edges;
            } catch (e) {
                console.warn('Refresco de topología falló:', e);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            pintarFrescura();
            setInterval(pintarFrescura, 1000);
            setInterval(refrescarEstadoVivo, 10000);   // el sondeo actualiza cada equipo cada ~8 s
        });

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
                nmAviso('Por favor introduce una dirección IP semilla válida.', 'advertencia');
                return;
            }
            if (discoveryMethod === 'cidr_sweep' && !cidr) {
                nmAviso('Por favor introduce un prefijo CIDR válido (ej. 192.168.1.0/24).', 'advertencia');
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
                nmAviso(`Error en el auto-descubrimiento: ${error.message}`, 'error');
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

                // Actualizar datasets de Vis.js conservando el acomodo: cada equipo sigue donde está en el lienzo,
                // con su imagen y rol; los nuevos se colocan junto a sus vecinos
                if (nodesDataSet && edgesDataSet && network) {
                    const actuales = network.getPositions();
                    const oscuro = document.documentElement.classList.contains('dark');
                    const nuevos = [];
                    const nodos = (data.nodes || []).map(node => {
                        const cData = node.customData || {};
                        const nombre = cData.nombre || node.label || '';
                        const imgInfo = getDeviceImageAndRole(cData.modelo, nombre, cData.sysDescr);
                        const imagenRuta = cData.image || imgInfo.image || null;
                        const esInfra = !!imagenRuta;
                        const p = actuales[node.id] || (cData.posicion_guardada ? { x: node.x, y: node.y } : null);
                        if (!p) nuevos.push(node.id);
                        return {
                            ...node,
                            label: nombre || 'Desconocido',
                            title: undefined,
                            shape: esInfra ? 'image' : 'dot',
                            image: esInfra ? imagenRuta : undefined,
                            size: node.size || imgInfo.size || (esInfra ? 145 : 120),
                            color: esInfra ? undefined : { background: '#667d93', border: '#667d93' },
                            font: fuenteEtiqueta(oscuro),
                            customData: { ...cData, rol: esInfra ? (cData.rol || imgInfo.rol) : 'ENDPOINT', tipo_equipo: esInfra ? (cData.tipo_equipo || imgInfo.tipo_equipo) : 'Dispositivo Final' },
                            x: p ? p.x : undefined,
                            y: p ? p.y : undefined,
                        };
                    });
                    const roles = Object.fromEntries(nodos.map(n => [n.id, n.customData.rol]));
                    const enlaces = (data.edges || []).map(edge => {
                        let lbl = edge.label || '';
                        if (lbl.includes('\n')) {
                            const partes = lbl.split('\n');
                            lbl = partes[1] ? partes[1].replace(/\s*\([^\)]*\)/, '') : partes[0];
                        }
                        const esFinal = roles[edge.to] === 'ENDPOINT' || roles[edge.from] === 'ENDPOINT';
                        return { ...edge, label: lbl, to_is_endpoint: esFinal, length: esFinal ? 260 : 480 };
                    });

                    nodesDataSet.clear();
                    edgesDataSet.clear();
                    nodesDataSet.add(nodos);
                    edgesDataSet.add(enlaces);

                    // Actualizar referencia para filtros
                    initialGrafoData.nodes = data.nodes;
                    initialGrafoData.edges = data.edges;

                    zonas = (data.zonas || []).map(z => ({ ...z }));
                    aplicarAgrupacionEndpoints();
                    network.once('afterDrawing', () => setTimeout(() => {
                        acomodarFaltantes(nuevos);
                        network.fit({ animation: { duration: 800, easingFunction: 'easeInOutQuad' }, padding: 80 });
                    }, 120));
                    network.redraw();
                }

                actualizarResumen(data.resumen);

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
                        bannerIcon.innerHTML = `<svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>`;
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
                        bannerIcon.innerHTML = `<svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /> <path d="M12 9v4" /> <path d="M12 17h.01" /></svg>`;
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
        // Indicador "En vivo" del encabezado: conexión local y salud del sondeo
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
            const badge = document.getElementById('liveStatusDot');
            if (!badge) return;
            const [punto, texto] = [badge.querySelector('i'), badge.querySelector('span')];
            const estado = !navigator.onLine ? ['bg-slate-400', 'Sin conexión', 'Este equipo perdió la conexión de red']
                : snmpIsWorking ? ['bg-emerald-500', 'En vivo', 'El sondeo de la red responde']
                : ['bg-red-500', 'Sondeo con fallas', 'El servidor de monitoreo no está respondiendo'];
            punto.className = 'w-2 h-2 rounded-full ' + estado[0];
            texto.textContent = estado[1];
            badge.title = estado[2];
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
