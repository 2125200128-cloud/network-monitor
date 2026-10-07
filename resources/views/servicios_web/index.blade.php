@extends('layouts.app')

@section('title', 'Monitoreo de Servicios Web | NOC Monitor')

@section('content')
<div class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-6 space-y-5">

    {{-- Header Top --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-[#0d1017] p-5 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-md bg-black dark:bg-zinc-800 text-white flex items-center justify-center shrink-0 shadow-xs">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white tracking-tight">Monitoreo de Servicios Web</h1>
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                        {{ $stats['total'] }} Sitios
                    </span>
                </div>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">
                    Disponibilidad HTTP/HTTPS, latencia y códigos de respuesta.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="reprobarTodosServicios()" id="btnScanAll" class="px-3.5 py-2 rounded-md bg-black hover:bg-zinc-800 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-white text-xs font-semibold transition flex items-center gap-2 shadow-xs group">
                <svg id="iconScanAll" class="w-3.5 h-3.5 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Verificar Todos</span>
            </button>

            <button type="button" onclick="openAddModal()" class="px-3.5 py-2 rounded-md bg-zinc-900 hover:bg-zinc-800 dark:bg-zinc-100 dark:hover:bg-white text-white dark:text-zinc-900 text-xs font-semibold transition flex items-center gap-1.5 shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Agregar Sitio</span>
            </button>
        </div>
    </div>

    {{-- Metrics KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        {{-- Total --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Total Sitios</p>
                <p id="kpiTotal" class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ $stats['total'] }}</p>
            </div>
            <div class="w-8 h-8 rounded-md bg-zinc-100 dark:bg-zinc-800 text-black dark:text-white flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
            </div>
        </div>

        {{-- Online --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">En Línea</p>
                <p id="kpiOnline" class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $stats['online'] }}</p>
            </div>
            <div class="w-8 h-8 rounded-md bg-zinc-100 dark:bg-zinc-800 text-black dark:text-white flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>

        {{-- Warning --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Advertencias</p>
                <p id="kpiWarning" class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-0.5">{{ $stats['warning'] }}</p>
            </div>
            <div class="w-8 h-8 rounded-md bg-zinc-100 dark:bg-zinc-800 text-black dark:text-white flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>

        {{-- Offline --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wider">Caídos</p>
                <p id="kpiOffline" class="text-xl font-bold text-red-600 dark:text-red-400 mt-0.5">{{ $stats['offline'] }}</p>
            </div>
            <div class="w-8 h-8 rounded-md bg-zinc-100 dark:bg-zinc-800 text-black dark:text-white flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
        </div>

        {{-- Avg Latency --}}
        <div class="col-span-2 lg:col-span-1 bg-white dark:bg-[#0d1017] p-4 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Latencia Prom.</p>
                <p id="kpiLatency" class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ $stats['avg_latency'] }} ms</p>
            </div>
            <div class="w-8 h-8 rounded-md bg-zinc-100 dark:bg-zinc-800 text-black dark:text-white flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>
    </div>

    {{-- Filters & Search Bar --}}
    <div class="bg-white dark:bg-[#0d1017] p-3.5 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
        {{-- Category Pills --}}
        <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0 text-xs custom-scrollbar">
            <button type="button" onclick="filterCategory('todas', this)" class="cat-tab-btn px-3 py-1.5 rounded-md font-bold bg-black dark:bg-zinc-100 text-white dark:text-zinc-900 shadow-xs">
                Todas ({{ count($servicios) }})
            </button>
            @foreach($categorias as $cat)
                <button type="button" onclick="filterCategory('{{ $cat }}', this)" class="cat-tab-btn px-3 py-1.5 rounded-md font-medium text-gray-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        {{-- Search Input & Status Select --}}
        <div class="flex items-center gap-2 w-full md:w-auto shrink-0">
            <select id="statusFilter" onchange="applyFilters()" class="text-xs font-medium bg-zinc-50 dark:bg-zinc-900 text-gray-800 dark:text-zinc-200 border border-gray-200 dark:border-zinc-700 rounded-md px-3 py-2 focus:ring-1 focus:ring-black dark:focus:ring-white outline-none">
                <option value="todos">Todos los Estados</option>
                <option value="online">En Línea (200 OK)</option>
                <option value="warning">Advertencias (4xx / Latencia)</option>
                <option value="offline">Caídos (5xx / Timeout)</option>
            </select>

            <div class="relative flex-1 md:w-60">
                <input type="text" id="searchInput" onkeyup="applyFilters()" placeholder="Buscar sitio o URL..." class="w-full text-xs bg-zinc-50 dark:bg-zinc-900 text-gray-800 dark:text-zinc-100 placeholder-gray-400 pl-8 pr-3 py-2 rounded-md border border-gray-200 dark:border-zinc-700 focus:ring-1 focus:ring-black dark:focus:ring-white outline-none">
                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </div>
    </div>

    {{-- Web Services Table --}}
    <div class="bg-white dark:bg-[#0d1017] rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-900/70 border-b border-gray-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 font-bold uppercase text-[10px] tracking-wider">
                        <th class="p-3 pl-5">Sitio Web / URL</th>
                        <th class="p-3">Categoría</th>
                        <th class="p-3">Estado</th>
                        <th class="p-3">Latencia</th>
                        <th class="p-3">Chequeo</th>
                        <th class="p-3 text-right pr-5">Acciones</th>
                    </tr>
                </thead>
                <tbody id="servicesTableBody" class="divide-y divide-gray-200 dark:divide-zinc-800/60">
                    @forelse($servicios as $web)
                        <tr class="web-row transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/30" 
                            data-id="{{ $web->id }}" 
                            data-categoria="{{ $web->categoria }}" 
                            data-estado="{{ $web->estado }}" 
                            data-search="{{ strtolower($web->nombre . ' ' . $web->url) }}">
                            
                            {{-- Name & URL --}}
                            <td class="p-3 pl-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-black dark:text-white bg-zinc-100 dark:bg-zinc-800 shrink-0 border border-zinc-200 dark:border-zinc-700">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 max-w-md">
                                        <h3 class="font-bold text-gray-900 dark:text-white leading-snug truncate">{{ $web->nombre }}</h3>
                                        <a href="{{ $web->url }}" target="_blank" rel="noopener noreferrer" class="text-[11px] font-mono text-zinc-600 dark:text-zinc-400 hover:text-black dark:hover:text-white hover:underline truncate flex items-center gap-1 group mt-0.5" title="{{ $web->url }}">
                                            <span class="truncate">{{ $web->url }}</span>
                                            <svg class="w-2.5 h-2.5 opacity-60 group-hover:opacity-100 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Category --}}
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                    {{ $web->categoria }}
                                </span>
                            </td>

                            {{-- Status Badge --}}
                            <td class="p-3">
                                @if($web->estado === 'online')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ $web->codigo_http ? 'HTTP ' . $web->codigo_http : '200 OK' }}
                                    </span>
                                @elseif($web->estado === 'warning')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60" title="{{ $web->detalles_error }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        {{ $web->codigo_http ? 'HTTP ' . $web->codigo_http : 'Advertencia' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[11px] font-bold bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800/60" title="{{ $web->detalles_error }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        {{ $web->codigo_http ? 'HTTP ' . $web->codigo_http : 'Caído' }}
                                    </span>
                                @endif
                            </td>

                            {{-- Latency --}}
                            <td class="p-3 font-mono font-medium text-xs">
                                @if($web->tiempo_respuesta_ms)
                                    <span class="{{ $web->tiempo_respuesta_ms > 3500 ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-zinc-800 dark:text-zinc-200' }}">
                                        {{ number_format($web->tiempo_respuesta_ms, 1) }} ms
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Last Check --}}
                            <td class="p-3 text-gray-500 dark:text-zinc-400 font-mono text-[11px]">
                                {{ $web->ultimo_chequeo ? $web->ultimo_chequeo->diffForHumans() : 'Pendiente' }}
                            </td>

                            {{-- Actions --}}
                            <td class="p-3 text-right pr-5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" onclick="reprobarSingleService('{{ $web->id }}', this)" class="px-2.5 py-1 rounded bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 text-[11px] font-semibold transition flex items-center gap-1">
                                        <svg class="w-3 h-3 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        <span>Probar</span>
                                    </button>

                                    <form action="{{ route('servicios_web.destroy', $web->id) }}" method="POST" onsubmit="return confirm('¿Eliminar {{ addslashes($web->nombre) }}?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 rounded text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" title="Eliminar">
                                            <svg class="w-3.5 h-3.5 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-400 dark:text-zinc-500">
                                No hay sitios web registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- MODAL AGREGAR SITIO WEB --}}
<div id="addModal" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white dark:bg-[#0d1017] rounded-lg border border-gray-200 dark:border-zinc-800 shadow-xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded bg-black dark:bg-zinc-800 text-white flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Nuevo Sitio Web</h3>
            </div>
            <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-black dark:hover:text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <form action="{{ route('servicios_web.store') }}" method="POST" class="p-4 space-y-3.5">
            @csrf
            <div>
                <label class="block text-[11px] font-bold text-gray-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">Nombre del Servicio</label>
                <input type="text" name="nombre" required placeholder="Ej. Portal de Trámites" class="w-full text-xs bg-zinc-50 dark:bg-zinc-900 text-gray-800 dark:text-zinc-100 px-3 py-2 rounded-md border border-gray-200 dark:border-zinc-700 focus:ring-1 focus:ring-black dark:focus:ring-white outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">URL</label>
                <input type="url" name="url" required placeholder="https://ejemplo.jalisco.gob.mx" class="w-full text-xs font-mono bg-zinc-50 dark:bg-zinc-900 text-gray-800 dark:text-zinc-100 px-3 py-2 rounded-md border border-gray-200 dark:border-zinc-700 focus:ring-1 focus:ring-black dark:focus:ring-white outline-none">
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">Categoría</label>
                    <select name="categoria" required class="w-full text-xs bg-zinc-50 dark:bg-zinc-900 text-gray-800 dark:text-zinc-100 px-3 py-2 rounded-md border border-gray-200 dark:border-zinc-700 focus:ring-1 focus:ring-black dark:focus:ring-white outline-none">
                        <option value="Trámites">Trámites</option>
                        <option value="Sefin">Sefin</option>
                        <option value="Bancos">Bancos</option>
                        <option value="Multipagos">Multipagos</option>
                        <option value="Gobierno">Gobierno</option>
                        <option value="Internos">Internos</option>
                        <option value="General">General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">Método HTTP</label>
                    <select name="metodo" class="w-full text-xs bg-zinc-50 dark:bg-zinc-900 text-gray-800 dark:text-zinc-100 px-3 py-2 rounded-md border border-gray-200 dark:border-zinc-700 focus:ring-1 focus:ring-black dark:focus:ring-white outline-none">
                        <option value="GET">GET</option>
                        <option value="HEAD">HEAD</option>
                    </select>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2 border-t border-gray-100 dark:border-zinc-800 mt-2">
                <button type="button" onclick="closeAddModal()" class="px-3 py-1.5 rounded-md text-xs font-semibold text-gray-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-1.5 rounded-md bg-black hover:bg-zinc-800 dark:bg-zinc-100 dark:hover:bg-white text-white dark:text-zinc-900 text-xs font-semibold transition shadow-xs">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let activeCategory = 'todas';

    function filterCategory(cat, btn) {
        activeCategory = cat;
        document.querySelectorAll('.cat-tab-btn').forEach(b => {
            b.className = 'cat-tab-btn px-3 py-1.5 rounded-md font-medium text-gray-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition';
        });
        if (btn) {
            btn.className = 'cat-tab-btn px-3 py-1.5 rounded-md font-bold bg-black dark:bg-zinc-100 text-white dark:text-zinc-900 shadow-xs';
        }
        applyFilters();
    }

    function applyFilters() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('.web-row');

        rows.forEach(row => {
            const rowCat = row.dataset.categoria;
            const rowStatus = row.dataset.estado;
            const rowSearch = row.dataset.search;

            const matchesCat = (activeCategory === 'todas' || rowCat === activeCategory);
            const matchesStatus = (status === 'todos' || rowStatus === status);
            const matchesSearch = (!query || rowSearch.includes(query));

            if (matchesCat && matchesStatus && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }

    async function reprobarSingleService(id, btn) {
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="w-3 h-3 animate-spin text-black dark:text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Probando...`;

        try {
            const resp = await fetch(`/servicios-web/${id}/reprobar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await resp.json();
            if (data.success) {
                refreshLiveServices();
            }
        } catch (err) {
            console.error('Error al probar servicio:', err);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function reprobarTodosServicios() {
        const btn = document.getElementById('btnScanAll');
        const icon = document.getElementById('iconScanAll');
        btn.disabled = true;
        icon.classList.add('animate-spin');

        try {
            const resp = await fetch('{{ route("servicios_web.reprobar_todos") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await resp.json();
            if (data.success) {
                refreshLiveServices();
            }
        } catch (err) {
            console.error('Error al verificar todos los servicios:', err);
        } finally {
            btn.disabled = false;
            icon.classList.remove('animate-spin');
        }
    }

    async function refreshLiveServices() {
        try {
            const resp = await fetch('{{ route("servicios_web.api_live") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!resp.ok) return;
            const data = await resp.json();

            if (data.success) {
                document.getElementById('kpiTotal').textContent = data.stats.total;
                document.getElementById('kpiOnline').textContent = data.stats.online;
                document.getElementById('kpiWarning').textContent = data.stats.warning;
                document.getElementById('kpiOffline').textContent = data.stats.offline;
                document.getElementById('kpiLatency').textContent = data.stats.avg_latency + ' ms';
            }
        } catch (err) {
            console.error('Error refreshing live web services:', err);
        }
    }

    // Auto-refresh every 20 seconds
    setInterval(refreshLiveServices, 20000);
</script>
@endsection
