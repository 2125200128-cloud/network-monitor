@extends('layouts.app')

@section('title', 'Equipos')
{{-- Salud, diagnóstico e inventario cada 15 s --}}
@section('refresco', '15')

@section('styles')
<style>
    /* Listas del diagnóstico de red (clases compartidas para no repetirlas en cada celda) */
    .dash-lista th { padding: 0 0.5rem 0.375rem 0; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; }
    .dash-lista td { padding: 0.375rem 0.5rem 0.375rem 0; color: #374151; border-top: 1px solid #f3f4f6; }
    .dash-lista .dash-disp { font-weight: 600; color: #1f2937; display: inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
    .dash-lista .dash-disp:hover { color: var(--nm-primary); text-decoration: underline; }
    .dark .dash-lista th { color: #64748b; }
    .dark .dash-lista td { color: #cbd5e1; border-top-color: rgba(30, 41, 59, 0.5); }
    .dark .dash-lista .dash-disp { color: #e2e8f0; }
    .dark .dash-lista .dash-disp:hover { color: #667d93; }
</style>
@endsection

@section('content')
<div class="flex flex-col pb-6">

    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h.01M3 12h.01M3 18h.01M8 6h13M8 12h13M8 18h13" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Equipos de red</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">Salud operativa, diagnóstico por pestañas e inventario · se actualiza cada 15 s</p>
            </div>
        </div>
        <a href="{{ route('reportes.inventario_pdf') }}" target="_blank" class="fancy fancy-sm" style="--fancy-fondo: var(--nm-bg)" title="Descargar reporte de inventario en PDF">
            <span class="top-key"></span>
            <span class="text">
                <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /> <path d="M14 2v4a2 2 0 0 0 2 2h4" /> <path d="M12 18v-6" /> <path d="m9 15 3 3 3-3" /></svg>
                Reporte PDF
            </span>
            <span class="bottom-key-1"></span>
            <span class="bottom-key-2"></span>
        </a>
    </div>

    <div data-refrescar="salud">
        @include('dashboard.partials.salud')
    </div>

    @include('dashboard.partials.diagnostico')

            <!-- ==================== TABLA DE INVENTARIO DE DISPOSITIVOS (NOC) ==================== -->
            <div class="floating-card p-6 mb-6" data-refrescar="inventario" x-data="{
                filtroBusqueda: '',
                filtroEstado: 'todos',
                // ?tipo=camaras abre directo el filtro (lo usa el botón Buscar cámaras al recargar)
                filtroTipo: new URLSearchParams(location.search).get('tipo') || 'todos',
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

                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                        @if(in_array(strtolower((string) auth()->user()?->role), ['admin', 'operador'], true))
                            <button type="button" @click="$dispatch('agregar-dispositivo')"
                                    class="px-3 py-2 rounded-xl text-xs font-bold text-white bg-hacienda-blue hover:opacity-90 transition flex items-center justify-center gap-1.5 shadow-xs whitespace-nowrap"
                                    style="background: var(--nm-primary)">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                                Agregar dispositivo
                            </button>
                            <button type="button" @click="$dispatch('buscar-camaras')"
                                    class="px-3 py-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 whitespace-nowrap border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-800"
                                    title="Busca cámaras de seguridad en las tablas ARP de los switches y routers (tarda unos 30 s)">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
                                Buscar cámaras
                            </button>
                        @endif
                        <!-- Buscador en tiempo real -->
                        <div class="relative w-full sm:w-72">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></svg>
                            </div>
                            <input 
                                type="text" 
                                x-model="filtroBusqueda"
                                placeholder="Buscar por nombre, IP, tipo..." 
                                class="w-full pl-9 pr-8 py-2 bg-gray-50 dark:bg-nm-sunken border border-gray-200 dark:border-slate-800/60 rounded-xl text-xs focus:ring-2 focus:ring-hacienda-blue dark:focus:ring-blue-500 focus:border-transparent outline-none transition placeholder-gray-400 dark:placeholder-slate-500 text-gray-800 dark:text-slate-200"
                            >
                            <button 
                                x-show="filtroBusqueda.length > 0" 
                                @click="filtroBusqueda = ''"
                                type="button" 
                                class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600"
                                style="display: none;">
                                <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                            </button>
                        </div>
                        </div>
                    </div>

                    <!-- Barra de Filtros Dual: Categorías de Hardware + Estado Online/Offline -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <!-- Filtros por Tipo de Dispositivo -->
                        <div class="flex flex-wrap items-center gap-1.5 bg-gray-100/80 dark:bg-nm-sunken border border-transparent dark:border-slate-800/40 p-1 rounded-xl text-xs font-semibold">
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
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2" /> <rect width="20" height="8" x="2" y="14" rx="2" ry="2" /> <line x1="6" x2="6.01" y1="6" y2="6" /> <line x1="6" x2="6.01" y1="18" y2="18" /></svg>
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
                            <button
                                @click="filtroTipo = 'camaras'"
                                :class="filtroTipo === 'camaras' ? 'bg-white dark:bg-slate-800 text-rose-600 dark:text-rose-400 shadow-sm dark:shadow-none font-bold' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                                class="px-2.5 py-1.5 rounded-lg transition flex items-center gap-1.5"
                                type="button" title="Cámaras de seguridad encontradas en las tablas ARP (solo inventario, no se monitorean)">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16.75 12h3.63a1 1 0 0 1 .96 1.28l-1.17 4.1a1 1 0 0 1-.96.72H17" /><path d="M2 8.55V5a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v6.58a1 1 0 0 1-.4.8l-3.2 2.4a1 1 0 0 1-.6.2H3a1 1 0 0 1-1-1z" /><path d="M7 15v3a2 2 0 0 1-2 2H2" /></svg>
                                Cámaras ({{ $camaras->count() }})
                            </button>
                        </div>

                        <!-- Filtros por Estado (las cámaras no se monitorean: no tienen estado) -->
                        <div x-show="filtroTipo !== 'camaras'" class="flex items-center bg-gray-100/80 dark:bg-nm-sunken border border-transparent dark:border-slate-800/40 p-1 rounded-xl text-xs font-semibold">
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
                <div x-show="filtroTipo !== 'camaras'" class="rounded-xl border border-gray-200/80 dark:border-slate-800/40 overflow-hidden shadow-xs dark:shadow-none bg-white dark:bg-nm-surface">
                    <div x-ref="tableContainer" class="max-h-[420px] overflow-y-auto overflow-x-auto custom-scrollbar relative" style="scrollbar-gutter: stable;">
                        <table class="w-full text-left border-separate border-spacing-0 text-xs">
                            <thead class="sticky top-0 z-20 shadow-[0_2px_4px_rgba(0,0,0,0.04)] dark:shadow-none">
                                <tr class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-28">Estado</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 min-w-[210px]">Dispositivo / Hostname</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-32">Dirección IP</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 min-w-[160px]">Tipo / Modelo</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-32">CPU / RAM (%)</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-28">Uptime</th>
                                    <th class="sticky top-0 z-20 py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 w-36 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800/30 bg-white dark:bg-nm-surface">
                                @php $esAdmin = strtolower((string) auth()->user()?->role) === 'admin'; @endphp
                                @forelse($dispositivos as $disp)
                                <tr data-disp-id="{{ $disp->id }}"
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
                                                @if($disp->tipo_dispositivo === 'firewall')
                                                    <!-- Firewall -->
                                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /></svg>
                                                @elseif($disp->tipo_dispositivo === 'telefono')
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
                                                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2" /> <rect width="20" height="8" x="2" y="14" rx="2" ry="2" /> <line x1="6" x2="6.01" y1="6" y2="6" /> <line x1="6" x2="6.01" y1="18" y2="18" /></svg>
                                                @endif
                                            </div>
                                            <div class="min-w-0 max-w-[200px] sm:max-w-[240px]">
                                                <a href="{{ route('dispositivos.show', $disp->id) }}" class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-hacienda-blue dark:group-hover:text-blue-400 hover:underline transition truncate block" title="{{ $disp->nombre }}">
                                                    {{ $disp->nombre }}
                                                </a>
                                                <span class="text-[11px] text-gray-400 dark:text-slate-500 font-mono truncate block">{{ $disp->ubicacion ?: 'NOC Data Center' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Dirección IP -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        <span class="font-mono text-xs text-gray-700 dark:text-slate-300 bg-gray-50 dark:bg-nm-sunken px-2 py-0.5 rounded-md border border-gray-200 dark:border-slate-800/60">
                                            {{ $disp->ip }}
                                        </span>
                                    </td>

                                    <!-- Modelo / Plataforma y Badge de Tipo -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border {{ $disp->tipo_badge_classes }}">
                                            {{ $disp->clean_model }}
                                        </span>
                                    </td>

                                    <!-- CPU y Memoria (%) de la última muestra del poller; sin dato = "—" -->
                                    <td class="py-2.5 px-3.5 whitespace-nowrap border-b border-gray-100 dark:border-slate-800/30">
                                        <div class="flex flex-col gap-1.5">
                                            @foreach(['CPU' => $disp->latest_cpu, 'RAM' => $disp->latest_mem] as $etiqueta => $valor)
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[11px] font-bold text-gray-400 w-6">{{ $etiqueta }}</span>
                                                    <div class="w-12 bg-gray-100 dark:bg-slate-800/80 rounded-full h-1.5 overflow-hidden flex-shrink-0">
                                                        @if($valor !== null)
                                                            <div class="h-1.5 rounded-full {{ $valor > 75 ? 'bg-red-500' : ($valor > 40 ? 'bg-[#ff8300]' : 'bg-nm-primary') }}" style="width: {{ min(100, max(4, $valor)) }}%"></div>
                                                        @endif
                                                    </div>
                                                    <span class="font-mono font-bold text-gray-800 dark:text-slate-200 text-[11px] w-6 text-right">{{ $valor !== null ? $valor . '%' : '—' }}</span>
                                                </div>
                                            @endforeach
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
                                                <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /> <circle cx="12" cy="12" r="3" /></svg>
                                                <span>Detalles</span>
                                            </a>
                                            <!-- Consola CLI -->
                                            <a href="{{ route('consola.index') }}" 
                                               class="px-2.5 py-1 rounded-lg text-xs font-semibold text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 hover:bg-gray-900 dark:hover:bg-slate-700 hover:text-emerald-400 transition font-mono flex items-center gap-1 shadow-xs border border-transparent dark:border-slate-700"
                                               title="Abrir consola CLI remota" aria-label="Abrir consola CLI remota">
                                                <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m7 11 2-2-2-2" /> <path d="M11 13h4" /> <rect width="18" height="18" x="3" y="3" rx="2" ry="2" /></svg>
                                                <span>CLI</span>
                                            </a>
                                            @if($esAdmin)
                                                <!-- Eliminar (solo Administrador): la ventana de confirmación está fuera de la zona que se refresca -->
                                                <button type="button"
                                                        @click="$dispatch('eliminar-dispositivo', {{ json_encode(['id' => $disp->id, 'nombre' => $disp->nombre, 'ip' => $disp->ip]) }})"
                                                        class="px-2 py-1 rounded-lg text-xs font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 hover:bg-red-600 hover:text-white dark:hover:bg-red-600 dark:hover:text-white transition flex items-center shadow-xs border border-transparent dark:border-red-900/40"
                                                        title="Eliminar del monitoreo" aria-label="Eliminar {{ $disp->nombre }} del monitoreo">
                                                    <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path d="M10 11v6M14 11v6" /></svg>
                                                </button>
                                            @endif
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

                {{-- Cámaras de seguridad (filtro "Cámaras"): encontradas en las tablas ARP; solo inventario, no se monitorean --}}
                @php
                    $marcaCamara = fn ($f) => match (true) {
                        (bool) preg_match('/ezviz/i', $f) => 'Ezviz (Hikvision)', (bool) preg_match('/hikvision/i', $f) => 'Hikvision',
                        (bool) preg_match('/dahua/i', $f) => 'Dahua', (bool) preg_match('/axis/i', $f) => 'Axis',
                        (bool) preg_match('/uniview/i', $f) => 'Uniview', (bool) preg_match('/hanwha|samsung techwin/i', $f) => 'Hanwha',
                        default => \Illuminate\Support\Str::of($f)->before(',')->limit(24)->toString(),
                    };
                    $ultimaBusqueda = $camarasBuscadas ? \Carbon\Carbon::parse($camarasBuscadas) : null;
                @endphp
                <div x-show="filtroTipo === 'camaras'" x-cloak>
                    <p class="text-[11.5px] mb-2 text-gray-500 dark:text-slate-400">
                        Encontradas en las tablas ARP de los switches y routers por el fabricante de su tarjeta de red (Hikvision, Dahua, Axis…).
                        Solo inventario: no se monitorean ni generan alarmas. Algunas pueden ser grabadoras (NVR/DVR) del mismo fabricante.
                        · Última búsqueda: <b>{{ $ultimaBusqueda ? $ultimaBusqueda->format('d/m/Y H:i') : 'nunca' }}</b> (se repite cada noche)
                    </p>
                    <div class="rounded-xl border border-gray-200/80 dark:border-slate-800/40 overflow-hidden bg-white dark:bg-nm-surface">
                        <div class="max-h-[420px] overflow-y-auto overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-separate border-spacing-0 text-xs">
                                <thead class="sticky top-0 z-20">
                                    <tr class="text-[11px] font-bold uppercase tracking-wider">
                                        @foreach(['IP', 'MAC', 'Fabricante', 'Vista en', 'Video (RTSP)', 'Web', 'Primera vez', 'Última vez'] as $th)
                                            <th class="py-2.5 px-3.5 bg-slate-50/95 dark:bg-nm-surface/95 border-b border-gray-200 dark:border-slate-800/40 text-gray-500 dark:text-slate-400 whitespace-nowrap">{{ $th }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($camaras as $cam)
                                        @php
                                            $busqueda = mb_strtolower("{$cam->ip} {$cam->mac} {$cam->fabricante} {$cam->visto_en}");
                                            $web = $cam->https ? "https://{$cam->ip}" : ($cam->http ? "http://{$cam->ip}" : null);
                                            // No apareció en la última búsqueda: apagada, desconectada o cambió de IP
                                            $vieja = $ultimaBusqueda && \Carbon\Carbon::parse($cam->ultima_vez)->lt($ultimaBusqueda->copy()->subMinutes(30));
                                        @endphp
                                        <tr x-show="!filtroBusqueda || {{ json_encode($busqueda) }}.includes(filtroBusqueda.toLowerCase().trim())"
                                            class="hover:bg-blue-50/40 dark:hover:bg-slate-800/40 {{ $vieja ? 'opacity-60' : '' }}">
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 font-mono font-bold text-gray-800 dark:text-slate-200 whitespace-nowrap">{{ $cam->ip }}</td>
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 font-mono text-gray-500 dark:text-slate-400 whitespace-nowrap">{{ $cam->mac }}</td>
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 font-semibold text-gray-800 dark:text-slate-200 whitespace-nowrap" title="{{ $cam->fabricante }}">{{ $marcaCamara($cam->fabricante) }}</td>
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 text-gray-600 dark:text-slate-300 whitespace-nowrap" title="Equipo de red en cuya tabla ARP apareció ({{ $cam->visto_en_ip }})">{{ \Illuminate\Support\Str::before($cam->visto_en, '.') }}</td>
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 whitespace-nowrap">{!! $cam->rtsp ? '<span class="text-emerald-600 dark:text-emerald-400 font-bold">✓ abierto</span>' : '<span class="text-gray-400">—</span>' !!}</td>
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 whitespace-nowrap">
                                                @if($web)<x-boton-consola :url="$web" texto="Abrir" titulo="Página web de la cámara ({{ $web }})" />@else<span class="text-gray-400">—</span>@endif
                                            </td>
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 font-mono text-gray-500 dark:text-slate-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($cam->primera_vez)->format('d/m/Y') }}</td>
                                            <td class="py-2 px-3.5 border-b border-gray-100 dark:border-slate-800/30 font-mono whitespace-nowrap {{ $vieja ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-gray-500 dark:text-slate-400' }}"
                                                title="{{ $vieja ? 'No apareció en la última búsqueda: apagada, desconectada o cambió de IP' : '' }}">{{ \Carbon\Carbon::parse($cam->ultima_vez)->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="py-8 text-center text-gray-400 dark:text-slate-500">Todavía no se han buscado cámaras. Pulsa «Buscar cámaras».</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer Informativo y Control de Desplazamiento -->
                <div x-show="filtroTipo !== 'camaras'" class="mt-3.5 pt-3 border-t border-gray-100 dark:border-slate-800/40 flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-400 dark:text-slate-400 gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="font-medium text-gray-600 dark:text-slate-400">Desplazamiento interno independiente · <strong class="text-gray-800 dark:text-white">{{ $totalDispositivos }}</strong> nodos monitoreados</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-[11px] font-mono text-gray-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-gray-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21 16-4 4-4-4" /> <path d="M17 20V4" /> <path d="m3 8 4-4 4 4" /> <path d="M7 4v16" /></svg>
                            <span>Scroll interno activo</span>
                        </div>
                        <button 
                            @click="$refs.tableContainer.scrollTo({top: 0, behavior: 'smooth'})" 
                            type="button" 
                            class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-600 dark:text-slate-300 transition flex items-center gap-1 border border-transparent dark:border-slate-700"
                            title="Subir al inicio de la tabla" aria-label="Subir al inicio de la tabla">
                            <svg class="w-3 h-3" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7" /> <path d="M12 19V5" /></svg>
                            <span>Inicio</span>
                        </button>
                    </div>
                </div>
            </div>

    {{-- Confirmación para eliminar un equipo (la abre el botón de la tabla con el evento eliminar-dispositivo) --}}
    @if(strtolower((string) auth()->user()?->role) === 'admin')
        <div x-data="eliminarDispositivo()" @eliminar-dispositivo.window="abrir($event.detail)" @keydown.escape.window="cerrar()"
             x-show="equipo" x-cloak style="display: none" @click.self="cerrar()"
             class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="tituloEliminar">
            {{-- Se cierra con clic en el fondo (no con .outside: el mismo clic que la abre la cerraría) --}}
            <div class="floating-card w-full max-w-md p-6">
                <h3 id="tituloEliminar" class="text-base font-bold text-gray-900 dark:text-white">Eliminar del monitoreo</h3>
                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-slate-200" x-text="equipo && equipo.nombre"></p>
                <p class="text-xs font-mono text-gray-500 dark:text-slate-400" x-text="equipo && equipo.ip"></p>
                <p class="mt-3 text-xs text-gray-600 dark:text-slate-300 leading-relaxed">
                    Se borran también su historial de métricas, interfaces, enlaces de topología, alarmas, notas y registro de comandos.
                    <b>No se puede deshacer.</b> Si el equipo sigue en la red, solo volverá si se corre el descubrimiento de la topología.
                </p>
                <label class="block mt-4 text-xs font-semibold text-gray-700 dark:text-slate-300" for="confirmaIp">Para confirmar, escribe su IP</label>
                <input id="confirmaIp" x-ref="campo" x-model="confirmacion" @keydown.enter.prevent="eliminar()" type="text" autocomplete="off" spellcheck="false"
                       class="mt-1 w-full px-3 py-2 rounded-xl text-sm font-mono bg-gray-50 dark:bg-nm-sunken border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-red-500">
                <p x-show="error" x-text="error" class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400"></p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="cerrar()" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 transition">Cancelar</button>
                    <button type="button" @click="eliminar()" :disabled="borrando || confirmacion.trim() !== equipo?.ip"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            x-text="borrando ? 'Eliminando…' : 'Eliminar'"></button>
                </div>
            </div>
        </div>
    @endif

    {{-- Búsqueda de cámaras: fuera de la zona que se refresca para que el aviso de "buscando" no se pierda --}}
    @if(in_array(strtolower((string) auth()->user()?->role), ['admin', 'operador'], true))
        <div x-data="{ buscando: false, error: '' }" x-show="buscando || error" x-cloak style="display: none"
             @buscar-camaras.window="if (!buscando) { buscando = true; error = '';
                 fetch('{{ route('camaras.buscar') }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' } })
                     .then(r => r.json().then(j => ({ ok: r.ok, j })))
                     .then(({ ok, j }) => { if (ok) { location.href = '{{ route('equipos.index') }}?tipo=camaras'; } else { error = j.message || 'La búsqueda falló.'; buscando = false; } })
                     .catch(() => { error = 'No se pudo completar la búsqueda.'; buscando = false; }); }"
             class="fixed bottom-6 right-6 z-[70] px-4 py-3 rounded-xl shadow-lg text-sm font-semibold"
             :class="error ? 'bg-red-600 text-white' : 'bg-gray-900 text-white dark:bg-slate-700'">
            <span x-show="buscando">Buscando cámaras en las tablas ARP… (unos 30 s)</span>
            <span x-show="error" x-text="error"></span>
            <button x-show="error" type="button" @click="error = ''" class="ml-3 underline">Cerrar</button>
        </div>
    @endif

    {{-- Alta de un equipo por IP: descubrimiento SNMP/CDP/LLDP; si no responde, alta manual. Después, enlaces a mano. --}}
    @if(in_array(strtolower((string) auth()->user()?->role), ['admin', 'operador'], true))
        <datalist id="equiposVecinos">
            @foreach($dispositivos->sortBy(fn ($d) => mb_strtolower($d->nombre)) as $d)<option value="{{ $d->nombre }} — {{ $d->ip }}"></option>@endforeach
        </datalist>
        <script type="application/json" id="equiposLista">{!! json_encode($dispositivos->map(fn ($d) => ['id' => $d->id, 'n' => $d->nombre, 'ip' => $d->ip])->values(), JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        <div x-data="agregarDispositivo()" @agregar-dispositivo.window="abrir()" @keydown.escape.window="cerrar()"
             x-show="abierto" x-cloak style="display: none" @click.self="cerrar()"
             class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="tituloAgregar">
            <div class="floating-card w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3">
                    <h3 id="tituloAgregar" class="text-base font-bold text-gray-900 dark:text-white"
                        x-text="paso === 'enlaces' ? 'Enlaces de ' + dispositivo.nombre : 'Agregar dispositivo'"></h3>
                    <button type="button" @click="cerrar()" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200" aria-label="Cerrar">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Paso 1: IP --}}
                <form x-show="paso === 'ip'" @submit.prevent="descubrir()" class="mt-3 space-y-3">
                    <p class="text-xs text-gray-600 dark:text-slate-300">Se consulta el equipo por SNMP: nombre, modelo, interfaces y vecinos CDP/LLDP (sus enlaces). Tarda unos segundos.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300">IP de gestión
                            <input x-ref="ip" x-model.trim="ip" type="text" inputmode="decimal" autocomplete="off" placeholder="10.4.1.20" required
                                   class="mt-1 w-full px-3 py-2 rounded-xl text-sm font-mono bg-gray-50 dark:bg-nm-sunken border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                        </label>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300">Comunidad SNMP <span class="font-normal text-gray-400">(opcional)</span>
                            <input x-model.trim="comunidad" type="password" autocomplete="off" placeholder="La general de Ajustes"
                                   class="mt-1 w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-nm-sunken border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                        </label>
                    </div>
                    <p x-show="error" class="text-xs font-semibold text-red-600 dark:text-red-400">
                        <span x-text="error"></span>
                        <a x-show="existente" :href="existente?.url" class="underline ml-1">Ver equipo</a>
                    </p>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="ocupado || !ip"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-white disabled:opacity-40 transition" style="background: var(--nm-primary)"
                                x-text="ocupado ? 'Descubriendo…' : 'Descubrir'"></button>
                    </div>
                </form>

                {{-- Paso 2: no respondió SNMP → alta manual --}}
                <form x-show="paso === 'manual'" @submit.prevent="guardarManual()" class="mt-3 space-y-3">
                    <div class="px-3 py-2 rounded-xl text-xs bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300">
                        <b x-text="ip"></b> no se pudo descubrir: <span x-text="aviso"></span><br>
                        Puedes darlo de alta a mano: se monitorea por ping y después le agregas sus enlaces.
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300">Nombre
                            <input x-ref="nombre" x-model.trim="nombre" type="text" maxlength="255" required
                                   class="mt-1 w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-nm-sunken border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                        </label>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300">Tipo
                            <select x-model="tipo" required class="mt-1 w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-nm-sunken border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                                @foreach(\App\Services\TipoDispositivoResolver::CATALOGO as $clave => $t)
                                    @if($clave !== 'desconocido')<option value="{{ $clave }}">{{ $t['label'] }}</option>@endif
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 sm:col-span-2">Ubicación <span class="font-normal text-gray-400">(opcional)</span>
                            <input x-model.trim="ubicacion" type="text" maxlength="255" placeholder="p. ej. Site 3, rack 2"
                                   class="mt-1 w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-nm-sunken border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                        </label>
                    </div>
                    <p x-show="error" x-text="error" class="text-xs font-semibold text-red-600 dark:text-red-400"></p>
                    <div class="flex justify-between gap-2">
                        <button type="button" @click="paso = 'ip'; error = ''" class="px-3 py-2 rounded-xl text-xs font-semibold text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800">← Probar otra vez</button>
                        <button type="submit" :disabled="ocupado || !nombre"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-white disabled:opacity-40 transition" style="background: var(--nm-primary)"
                                x-text="ocupado ? 'Guardando…' : 'Agregar y seguir con enlaces'"></button>
                    </div>
                </form>

                {{-- Paso 3: enlaces (descubiertos y manuales) --}}
                <div x-show="paso === 'enlaces'" class="mt-3 space-y-4">
                    <div class="px-3 py-2 rounded-xl text-xs bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300">
                        <b x-text="dispositivo?.nombre"></b> (<span class="font-mono" x-text="dispositivo?.ip"></span>) quedó agregado<span x-show="modelo" x-text="' · ' + (modelo || '').slice(0, 80)"></span>.
                        <span x-show="vecinosNuevos.length" x-text="'También se agregaron sus vecinos nuevos: ' + vecinosNuevos.join(', ') + '.'"></span>
                        <a :href="dispositivo?.url" class="underline ml-1">Ver ficha</a>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-1">Enlaces · <span x-text="enlaces.length"></span></p>
                        <p x-show="!enlaces.length" class="text-xs text-gray-500 dark:text-slate-400">Sin enlaces: no anunció vecinos por CDP/LLDP. Agrégalos abajo.</p>
                        <table x-show="enlaces.length" class="w-full text-xs">
                            <thead><tr class="text-left text-[10px] uppercase tracking-wider text-gray-400"><th class="py-1">Puerto</th><th>Vecino</th><th>Puerto del vecino</th><th>Velocidad</th><th></th></tr></thead>
                            <tbody>
                                <template x-for="e in enlaces" :key="e.id">
                                    <tr class="border-t border-gray-100 dark:border-slate-800/60 text-gray-700 dark:text-slate-300">
                                        <td class="py-1.5 font-mono" x-text="e.puerto || '—'"></td>
                                        <td><span class="font-semibold" x-text="e.vecino"></span> <span class="font-mono text-gray-400" x-text="e.vecino_ip"></span></td>
                                        <td class="font-mono" x-text="e.puerto_vecino || '—'"></td>
                                        <td class="font-mono" x-text="velocidadTexto(e.velocidad)"></td>
                                        <td class="text-right whitespace-nowrap">
                                            <span x-show="!e.manual" class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">descubierto</span>
                                            <button x-show="e.manual" type="button" @click="quitarEnlace(e)" class="text-[11px] font-bold text-red-600 dark:text-red-400 hover:underline">Quitar</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <form @submit.prevent="agregarEnlace()" class="p-3 rounded-xl bg-gray-50 dark:bg-nm-sunken space-y-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Agregar enlace manual</p>
                        <div class="grid grid-cols-1 sm:grid-cols-6 gap-2">
                            <input x-model.trim="enlace.puertoLocal" type="text" placeholder="Su puerto (Gi1/0/1)" maxlength="64"
                                   class="sm:col-span-1 px-2.5 py-2 rounded-lg text-xs font-mono bg-white dark:bg-nm-surface border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <input x-model="enlace.vecino" list="equiposVecinos" type="text" placeholder="Equipo vecino (nombre o IP)" required
                                   class="sm:col-span-2 px-2.5 py-2 rounded-lg text-xs bg-white dark:bg-nm-surface border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <input x-model.trim="enlace.puertoVecino" type="text" placeholder="Puerto del vecino" maxlength="64"
                                   class="sm:col-span-1 px-2.5 py-2 rounded-lg text-xs font-mono bg-white dark:bg-nm-surface border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                            <select x-model.number="enlace.velocidad" class="sm:col-span-1 px-2 py-2 rounded-lg text-xs bg-white dark:bg-nm-surface border border-gray-200 dark:border-slate-800/60 text-gray-800 dark:text-slate-200">
                                <template x-for="v in [100, 1000, 10000, 25000, 40000, 100000]" :key="v"><option :value="v" x-text="velocidadTexto(v)" :selected="v === enlace.velocidad"></option></template>
                            </select>
                            <button type="submit" :disabled="ocupado" class="sm:col-span-1 px-3 py-2 rounded-lg text-xs font-bold text-white disabled:opacity-40" style="background: var(--nm-primary)">Agregar</button>
                        </div>
                        <p class="text-[11px] text-gray-400">Los puertos son opcionales; se aceptan abreviaturas (Gi, Te, Fa, Po).</p>
                        <p x-show="error" x-text="error" class="text-xs font-semibold text-red-600 dark:text-red-400"></p>
                    </form>

                    <div class="flex justify-between gap-2">
                        <button type="button" @click="reiniciar()" class="px-3 py-2 rounded-xl text-xs font-semibold text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800">Agregar otro</button>
                        <button type="button" @click="cerrar()" class="px-4 py-2 rounded-xl text-xs font-bold text-white" style="background: var(--nm-primary)">Listo</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Las tarjetas de salud ("Ver detalles") abren la pestaña correspondiente del diagnóstico
    function abrirDiagnostico(tab) {
        window.dispatchEvent(new CustomEvent('diag-tab', { detail: tab }));
        const el = document.getElementById('diagnosticoRed');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    // Desde otras páginas: /equipos?diag=puertos abre esa pestaña al cargar
    document.addEventListener('DOMContentLoaded', () => {
        const tab = new URLSearchParams(location.search).get('diag');
        if (tab) setTimeout(() => abrirDiagnostico(tab), 300);
    });

    // Alta por IP: paso 'ip' (descubrir) → 'manual' si no respondió SNMP → 'enlaces'
    function agregarDispositivo() {
        const pedir = async (metodo, url, cuerpo) => {
            const resp = await fetch(url, {
                method: metodo,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
                body: cuerpo ? JSON.stringify(cuerpo) : undefined,
            });
            const datos = await resp.json().catch(() => ({}));
            // 409 (IP ya registrada) y la respuesta "sin SNMP" traen su propio mensaje; los 422 traen errores por campo
            if (!resp.ok && resp.status !== 409) {
                throw new Error(Object.values(datos.errors || {})[0]?.[0] || datos.message || `HTTP ${resp.status}`);
            }
            return datos;
        };
        const base = '{{ url('/api/dispositivos') }}';
        return {
            abierto: false, paso: 'ip', ocupado: false, error: '', aviso: '', cambios: false,
            ip: '', comunidad: '', nombre: '', tipo: 'switch', ubicacion: '',
            dispositivo: null, modelo: null, vecinosNuevos: [], existente: null, enlaces: [],
            enlace: { puertoLocal: '', vecino: '', puertoVecino: '', velocidad: 1000 },
            abrir() { this.reiniciar(); this.abierto = true; },
            reiniciar() {
                Object.assign(this, { paso: 'ip', ocupado: false, error: '', aviso: '', ip: '', comunidad: '', nombre: '', tipo: 'switch',
                    ubicacion: '', dispositivo: null, modelo: null, vecinosNuevos: [], existente: null, enlaces: [] });
                this.enlace = { puertoLocal: '', vecino: '', puertoVecino: '', velocidad: 1000 };
                this.$nextTick(() => this.$refs.ip.focus());
            },
            cerrar() {
                if (this.ocupado) return;
                this.abierto = false;
                // El equipo nuevo aparece en la tabla (y en la lista de vecinos) al recargar
                if (this.cambios) location.reload();
            },
            velocidadTexto: v => v >= 1000 ? (v / 1000) + ' Gbps' : v + ' Mbps',
            async descubrir() {
                this.ocupado = true; this.error = ''; this.existente = null;
                try {
                    const r = await pedir('POST', `${base}/descubrir`, { ip: this.ip, comunidad: this.comunidad });
                    if (r.ok) {
                        this.cambios = true;
                        Object.assign(this, { dispositivo: r.dispositivo, modelo: r.modelo, vecinosNuevos: r.vecinos_nuevos || [], enlaces: r.enlaces || [], paso: 'enlaces' });
                    } else if (r.existente) {
                        this.error = r.mensaje; this.existente = r.existente;
                    } else {
                        this.aviso = r.mensaje; this.paso = 'manual';
                        this.$nextTick(() => this.$refs.nombre.focus());
                    }
                } catch (e) { this.error = e.message; }
                this.ocupado = false;
            },
            async guardarManual() {
                this.ocupado = true; this.error = '';
                try {
                    const r = await pedir('POST', base, { ip: this.ip, nombre: this.nombre, tipo: this.tipo, ubicacion: this.ubicacion, comunidad: this.comunidad });
                    this.cambios = true;
                    Object.assign(this, { dispositivo: r.dispositivo, modelo: null, vecinosNuevos: [], enlaces: [], paso: 'enlaces' });
                } catch (e) { this.error = e.message; }
                this.ocupado = false;
            },
            // El vecino se elige de la lista ("nombre — IP") o escribiendo su IP o nombre exacto
            vecinoId() {
                const texto = this.enlace.vecino.trim().toLowerCase();
                const lista = JSON.parse(document.getElementById('equiposLista')?.textContent || '[]');
                const e = lista.find(d => `${d.n} — ${d.ip}`.toLowerCase() === texto || d.ip === texto || d.n.toLowerCase() === texto);
                return e ? e.id : null;
            },
            async agregarEnlace() {
                const vecino = this.vecinoId();
                if (!vecino) { this.error = 'Elige el equipo vecino de la lista (o escribe su IP exacta).'; return; }
                this.ocupado = true; this.error = '';
                try {
                    const r = await pedir('POST', `${base}/${this.dispositivo.id}/enlaces`,
                        { vecino_id: vecino, puerto_local: this.enlace.puertoLocal, puerto_vecino: this.enlace.puertoVecino, velocidad: this.enlace.velocidad });
                    this.enlaces = r.enlaces;
                    this.enlace = { puertoLocal: '', vecino: '', puertoVecino: '', velocidad: this.enlace.velocidad };
                } catch (e) { this.error = e.message; }
                this.ocupado = false;
            },
            async quitarEnlace(e) {
                this.ocupado = true; this.error = '';
                try { this.enlaces = (await pedir('DELETE', `${base}/${this.dispositivo.id}/enlaces/${e.id}`)).enlaces; }
                catch (err) { this.error = err.message; }
                this.ocupado = false;
            },
        };
    }

    function eliminarDispositivo() {
        return {
            equipo: null, confirmacion: '', error: '', borrando: false,
            abrir(equipo) {
                Object.assign(this, { equipo, confirmacion: '', error: '', borrando: false });
                this.$nextTick(() => this.$refs.campo.focus());
            },
            cerrar() { if (!this.borrando) this.equipo = null; },
            async eliminar() {
                if (this.borrando || this.confirmacion.trim() !== this.equipo?.ip) return;
                this.borrando = true;
                this.error = '';
                try {
                    const resp = await fetch(`{{ url('/api/dispositivos') }}/${this.equipo.id}`, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ confirmacion: this.confirmacion.trim() }),
                    });
                    const datos = await resp.json().catch(() => ({}));
                    if (!resp.ok) throw new Error(datos.errors?.confirmacion?.[0] || datos.message || `HTTP ${resp.status}`);
                    // Fuera de la tabla al instante; el siguiente refresco ya no lo trae
                    document.querySelectorAll(`[data-disp-id="${this.equipo.id}"]`).forEach(fila => fila.remove());
                    this.borrando = false;
                    this.equipo = null;
                } catch (err) {
                    this.borrando = false;
                    this.error = 'No se pudo eliminar: ' + err.message;
                }
            },
        };
    }
</script>
@endsection