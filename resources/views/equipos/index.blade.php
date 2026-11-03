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
                        </div>

                        <!-- Filtros por Estado -->
                        <div class="flex items-center bg-gray-100/80 dark:bg-nm-sunken border border-transparent dark:border-slate-800/40 p-1 rounded-xl text-xs font-semibold">
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
                <div class="rounded-xl border border-gray-200/80 dark:border-slate-800/40 overflow-hidden shadow-xs dark:shadow-none bg-white dark:bg-nm-surface">
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
</script>
@endsection