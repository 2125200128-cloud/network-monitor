@extends('layouts.app')

@section('title', 'Monitoreo de Servicios | NOC Monitor')

@section('styles')
<style>
/* From Uiverse.io by milley69 */ 
.loading svg polyline {
  fill: none;
  stroke-width: 3.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.loading svg polyline#back {
  fill: none;
  stroke: #ff4d5033;
}

.loading svg polyline#front {
  fill: none;
  stroke: #ff4d4f;
  stroke-dasharray: 48, 144;
  stroke-dashoffset: 192;
  animation: dash_682 1.4s linear infinite;
}

@keyframes dash_682 {
  72.5% {
    opacity: 0;
  }

  to {
    stroke-dashoffset: 0;
  }
}
</style>
@endsection

@section('content')
<div class="flex-1 overflow-y-auto custom-scrollbar p-3 sm:p-6 space-y-5">
    @include('components.alert-banner')

    {{-- Header Top --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-[#0d1017] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-[#3b5998] text-white flex items-center justify-center shrink-0 shadow-md shadow-blue-900/10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-black text-gray-900 dark:text-white tracking-tight">Monitoreo de Servicios</h1>
                    <!-- From Uiverse.io by milley69 -->
                    <div class="loading inline-flex items-center" title="Monitoreo en vivo activo">
                        <svg width="48px" height="28px" viewBox="0 0 64 48">
                            <polyline points="0.157 23.954, 14 23.954, 21.843 48, 43 0, 50 24, 64 24" id="back"></polyline>
                            <polyline points="0.157 23.954, 14 23.954, 21.843 48, 43 0, 50 24, 64 24" id="front"></polyline>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5 font-medium">
                    Disponibilidad HTTP/HTTPS, latencia de respuesta y estado en tiempo real.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" onclick="reprobarTodosServicios()" id="btnScanAll" class="fancy">
                <span class="top-key"></span>
                <span class="text">
                    <svg id="iconScanAll" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Verificar Todos
                </span>
                <span class="bottom-key-1"></span>
                <span class="bottom-key-2"></span>
            </button>

            <button type="button" onclick="openAddModal()" class="fancy">
                <span class="top-key"></span>
                <span class="text">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Agregar Sitio
                </span>
                <span class="bottom-key-1"></span>
                <span class="bottom-key-2"></span>
            </button>
        </div>
    </div>

    {{-- Metrics KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        {{-- Total --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Sitios</p>
                <p id="kpiTotal" class="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{{ $stats['total'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-[#3b5998] dark:text-blue-400 flex items-center justify-center border border-slate-200/60 dark:border-slate-700/60">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
            </div>
        </div>

        {{-- Online --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">En Línea</p>
                <p id="kpiOnline" class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $stats['online'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-200/60 dark:border-emerald-800/60">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>

        {{-- Warning --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Advertencias</p>
                <p id="kpiWarning" class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5">{{ $stats['warning'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-200/60 dark:border-amber-800/60">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>

        {{-- Offline --}}
        <div class="bg-white dark:bg-[#0d1017] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wider">Caídos</p>
                <p id="kpiOffline" class="text-2xl font-black text-red-600 dark:text-red-400 mt-0.5">{{ $stats['offline'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center border border-red-200/60 dark:border-red-800/60">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
        </div>

        {{-- Avg Latency --}}
        <div class="col-span-2 lg:col-span-1 bg-white dark:bg-[#0d1017] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-[#3b5998] dark:text-blue-400 uppercase tracking-wider">Latencia Prom.</p>
                <p id="kpiLatency" class="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{{ $stats['avg_latency'] }} <span class="text-sm font-semibold text-gray-500">ms</span></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#3b5998] dark:text-blue-400 flex items-center justify-center border border-blue-200/60 dark:border-blue-800/60">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>
    </div>

    {{-- Filters & Search Bar --}}
    <div class="bg-white dark:bg-[#0d1017] p-3.5 sm:p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3.5">
        {{-- Category Pills --}}
        <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0 text-xs custom-scrollbar">
            <button type="button" onclick="filterCategory('todas', this)" class="cat-tab-btn px-3.5 py-1.5 rounded-lg font-bold bg-[#3b5998] text-white shadow-xs transition">
                Todas ({{ count($servicios) }})
            </button>
            @foreach($categorias as $cat)
                <button type="button" onclick="filterCategory('{{ $cat }}', this)" class="cat-tab-btn px-3.5 py-1.5 rounded-lg font-semibold text-gray-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        {{-- Search Input & Status Select --}}
        <div class="flex items-center gap-2.5 w-full md:w-auto shrink-0">
            <select id="statusFilter" onchange="applyFilters()" class="text-xs font-semibold bg-slate-50 dark:bg-slate-800 text-gray-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#3b5998] outline-none">
                <option value="todos">Todos los Estados</option>
                <option value="online">En Línea (200 OK)</option>
                <option value="warning">Advertencias (4xx / Latencia)</option>
                <option value="offline">Caídos (5xx / Timeout)</option>
            </select>

            <div class="relative flex-1 md:w-64">
                <input type="text" id="searchInput" onkeyup="applyFilters()" placeholder="Buscar sitio o URL..." class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 placeholder-gray-400 pl-8 pr-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-[#3b5998] outline-none">
                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </div>
    </div>

    {{-- Web Services Table --}}
    <div class="bg-white dark:bg-[#0d1017] rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-[#090b10] border-b border-slate-200/80 dark:border-slate-800 text-gray-500 dark:text-slate-400 font-extrabold uppercase text-[10px] tracking-wider">
                        <th class="p-3.5 pl-5">Sitio Web / URL</th>
                        <th class="p-3.5">Categoría</th>
                        <th class="p-3.5">Estado HTTP</th>
                        <th class="p-3.5">Latencia</th>
                        <th class="p-3.5">Último Chequeo</th>
                        <th class="p-3.5 text-right pr-5">Acciones</th>
                    </tr>
                </thead>
                <tbody id="servicesTableBody" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    @forelse($servicios as $web)
                        <tr class="web-row transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40" 
                            data-id="{{ $web->id }}" 
                            data-categoria="{{ $web->categoria }}" 
                            data-estado="{{ $web->estado }}" 
                            data-search="{{ strtolower($web->nombre . ' ' . $web->url) }}">
                            
                            {{-- Name & URL --}}
                            <td class="p-3.5 pl-5">
                                <div class="flex items-center gap-3">
                                    <div class="web-icon-box w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 border 
                                        {{ $web->estado === 'online' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60' : ($web->estado === 'warning' ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/60' : 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400 border-red-200/60 dark:border-red-800/60') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 max-w-md">
                                        <h3 class="font-extrabold text-gray-900 dark:text-white leading-snug truncate">{{ $web->nombre }}</h3>
                                        <a href="{{ $web->url }}" target="_blank" rel="noopener noreferrer" class="text-[11px] font-mono text-[#3b5998] dark:text-blue-400 hover:underline truncate flex items-center gap-1 group mt-0.5" title="{{ $web->url }}">
                                            <span class="truncate">{{ $web->url }}</span>
                                            <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform flex-shrink-0 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Category --}}
                            <td class="p-3.5">
                                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700">
                                    {{ $web->categoria }}
                                </span>
                            </td>

                            {{-- Status Badge --}}
                            <td class="p-3.5 cell-status">
                                @if($web->estado === 'online')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/70 dark:border-emerald-800/60">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        {{ $web->codigo_http ? 'HTTP ' . $web->codigo_http : '200 OK' }}
                                    </span>
                                @elseif($web->estado === 'warning')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/70 dark:border-amber-800/60" title="{{ $web->detalles_error }}">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        {{ $web->codigo_http ? 'HTTP ' . $web->codigo_http : 'Advertencia' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border border-red-200/70 dark:border-red-800/60" title="{{ $web->detalles_error }}">
                                        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                                        {{ $web->codigo_http ? 'HTTP ' . $web->codigo_http : 'Caído' }}
                                    </span>
                                @endif
                            </td>

                            {{-- Latency --}}
                            <td class="p-3.5 font-mono text-xs cell-latency">
                                @if($web->tiempo_respuesta_ms)
                                    <span class="font-bold {{ $web->tiempo_respuesta_ms > 3500 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200' }}">
                                        {{ number_format($web->tiempo_respuesta_ms, 1) }} ms
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Last Check --}}
                            <td class="p-3.5 text-gray-500 dark:text-slate-400 font-mono text-[11px] cell-last-check">
                                {{ $web->ultimo_chequeo ? $web->ultimo_chequeo->diffForHumans() : 'Pendiente' }}
                            </td>

                            {{-- Actions --}}
                            <td class="p-3.5 text-right pr-5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" onclick="reprobarSingleService('{{ $web->id }}', this)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-[#3b5998] hover:text-white dark:bg-slate-800 dark:hover:bg-[#3b5998] text-slate-700 dark:text-slate-200 text-[11px] font-bold transition flex items-center gap-1 shadow-2xs">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        <span>Probar</span>
                                    </button>

                                    <form action="{{ route('servicios_web.destroy', $web->id) }}" method="POST" onsubmit="return confirm('¿Eliminar {{ addslashes($web->nombre) }}?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition" title="Eliminar sitio">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-10 text-center text-gray-400 dark:text-slate-500 font-medium">
                                No hay sitios web registrados en el sistema.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- MODAL AGREGAR SITIO WEB --}}
<div id="addModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white dark:bg-[#0d1017] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="p-4 bg-slate-50 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-[#3b5998] text-white flex items-center justify-center font-bold shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Agregar Nuevo Sitio Web</h3>
            </div>
            <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <form action="{{ route('servicios_web.store') }}" method="POST" class="p-5 space-y-3.5">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1">Nombre del Sitio / Servicio</label>
                <input type="text" name="nombre" required placeholder="Ej. Portal de Trámites Jalisco" class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-[#3b5998] outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1">URL (http:// o https://)</label>
                <input type="url" name="url" required placeholder="https://gobiernoenlinea1.jalisco.gob.mx/ejemplo" class="w-full text-xs font-mono bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-[#3b5998] outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1">Categoría</label>
                    <select name="categoria" required class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-[#3b5998] outline-none">
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
                    <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1">Método HTTP</label>
                    <select name="metodo" class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-[#3b5998] outline-none">
                        <option value="GET">GET</option>
                        <option value="HEAD">HEAD</option>
                    </select>
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800 mt-2">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2 rounded-lg text-xs font-bold text-gray-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    Cancelar
                </button>
                <button type="submit" class="fancy fancy-sm">
                    <span class="top-key"></span>
                    <span class="text">Guardar Sitio</span>
                    <span class="bottom-key-1"></span>
                    <span class="bottom-key-2"></span>
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
            b.className = 'cat-tab-btn px-3.5 py-1.5 rounded-lg font-semibold text-gray-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition';
        });
        if (btn) {
            btn.className = 'cat-tab-btn px-3.5 py-1.5 rounded-lg font-bold bg-[#3b5998] text-white shadow-xs transition';
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
        btn.innerHTML = `<svg class="w-3.5 h-3.5 animate-spin text-[#3b5998] dark:text-blue-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> <span>Probando...</span>`;

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

    function updateTableRow(s) {
        const row = document.querySelector(`.web-row[data-id="${s.id}"]`);
        if (!row) return;

        // Update dataset for instant category/status filtering
        row.dataset.estado = s.estado;

        // 1. Icon Box
        const iconBox = row.querySelector('.web-icon-box');
        if (iconBox) {
            iconBox.className = `web-icon-box w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 border ${
                s.estado === 'online' 
                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60'
                    : (s.estado === 'warning'
                        ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/60'
                        : 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400 border-red-200/60 dark:border-red-800/60')
            }`;
        }

        // 2. Status Badge Cell
        const statusCell = row.querySelector('.cell-status');
        if (statusCell) {
            if (s.estado === 'online') {
                statusCell.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/70 dark:border-emerald-800/60">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        ${s.codigo_http ? 'HTTP ' + s.codigo_http : '200 OK'}
                    </span>`;
            } else if (s.estado === 'warning') {
                statusCell.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/70 dark:border-amber-800/60" title="${s.detalles_error || ''}">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        ${s.codigo_http ? 'HTTP ' + s.codigo_http : 'Advertencia'}
                    </span>`;
            } else {
                statusCell.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border border-red-200/70 dark:border-red-800/60" title="${s.detalles_error || ''}">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                        ${s.codigo_http ? 'HTTP ' + s.codigo_http : 'Caído'}
                    </span>`;
            }
        }

        // 3. Latency Cell
        const latencyCell = row.querySelector('.cell-latency');
        if (latencyCell) {
            if (s.tiempo_respuesta_ms !== null && s.tiempo_respuesta_ms !== undefined) {
                const isHigh = s.tiempo_respuesta_ms > 3500;
                latencyCell.innerHTML = `
                    <span class="font-bold ${isHigh ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200'}">
                        ${s.tiempo_respuesta_ms} ms
                    </span>`;
            } else {
                latencyCell.innerHTML = `<span class="text-gray-400">—</span>`;
            }
        }

        // 4. Last Check Cell
        const lastCheckCell = row.querySelector('.cell-last-check');
        if (lastCheckCell) {
            lastCheckCell.textContent = s.ultimo_chequeo_humano || 'Pendiente';
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
                // Update KPI Cards
                if (data.stats) {
                    const elTotal = document.getElementById('kpiTotal');
                    const elOnline = document.getElementById('kpiOnline');
                    const elWarning = document.getElementById('kpiWarning');
                    const elOffline = document.getElementById('kpiOffline');
                    const elLatency = document.getElementById('kpiLatency');

                    if (elTotal) elTotal.textContent = data.stats.total;
                    if (elOnline) elOnline.textContent = data.stats.online;
                    if (elWarning) elWarning.textContent = data.stats.warning;
                    if (elOffline) elOffline.textContent = data.stats.offline;
                    if (elLatency) elLatency.textContent = data.stats.avg_latency + ' ms';
                }

                // Update Individual Table Rows
                if (Array.isArray(data.servicios)) {
                    data.servicios.forEach(updateTableRow);
                    applyFilters();
                }
            }
        } catch (err) {
            console.error('Error refreshing live web services:', err);
        }
    }

    // Auto-refresh dynamically every 8 seconds
    setInterval(refreshLiveServices, 8000);
</script>
@endsection
