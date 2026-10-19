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

    {{-- Mensajes de resultado (alta / edición / eliminación) y errores de validación --}}
    @if(session('status'))
        <div class="flex items-center gap-3 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
            <svg class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="m9 12 2 2 4-4" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="flex items-start gap-3 p-3.5 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 text-red-700 dark:text-red-300 text-xs font-semibold">
            <svg class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <line x1="12" x2="12" y1="8" y2="12" /> <line x1="12" x2="12.01" y1="16" y2="16" /></svg>
            <ul class="space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header Top --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-nm-surface p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl text-white flex items-center justify-center shrink-0 shadow-md" style="background: linear-gradient(135deg, var(--nm-primary), var(--nm-primary-2));">
                <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /> <path d="M2 12h20" /></svg>
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
                    <svg id="iconScanAll" class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /> <path d="M21 3v5h-5" /> <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /> <path d="M8 16H3v5" /></svg>
                    Verificar Todos
                </span>
                <span class="bottom-key-1"></span>
                <span class="bottom-key-2"></span>
            </button>

            <button type="button" onclick="openAddModal()" class="fancy">
                <span class="top-key"></span>
                <span class="text">
                    <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14" /> <path d="M12 5v14" /></svg>
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
        <div class="bg-white dark:bg-nm-surface p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Sitios</p>
                <p id="kpiTotal" class="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{{ $stats['total'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800/80 text-nm-primary dark:text-blue-400 flex items-center justify-center border border-slate-200/60 dark:border-slate-700/60">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /> <path d="M2 12h20" /></svg>
            </div>
        </div>

        {{-- Online --}}
        <div class="bg-white dark:bg-nm-surface p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">En Línea</p>
                <p id="kpiOnline" class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $stats['online'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-200/60 dark:border-emerald-800/60">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
            </div>
        </div>

        {{-- Warning --}}
        <div class="bg-white dark:bg-nm-surface p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Advertencias</p>
                <p id="kpiWarning" class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5">{{ $stats['warning'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-200/60 dark:border-amber-800/60">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /> <path d="M12 9v4" /> <path d="M12 17h.01" /></svg>
            </div>
        </div>

        {{-- Offline --}}
        <div class="bg-white dark:bg-nm-surface p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wider">Caídos</p>
                <p id="kpiOffline" class="text-2xl font-black text-red-600 dark:text-red-400 mt-0.5">{{ $stats['offline'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center border border-red-200/60 dark:border-red-800/60">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
            </div>
        </div>

        {{-- Avg Latency --}}
        <div class="col-span-2 lg:col-span-1 bg-white dark:bg-nm-surface p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-nm-primary dark:text-blue-400 uppercase tracking-wider">Latencia Prom.</p>
                <p id="kpiLatency" class="text-2xl font-black text-gray-900 dark:text-white mt-0.5">{{ $stats['avg_latency'] }} <span class="text-sm font-semibold text-gray-500">ms</span></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-nm-primary dark:text-blue-400 flex items-center justify-center border border-blue-200/60 dark:border-blue-800/60">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
            </div>
        </div>
    </div>

    {{-- Filters & Search Bar --}}
    <div class="bg-white dark:bg-nm-surface p-3.5 sm:p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3.5">
        {{-- Category Pills --}}
        <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0 text-xs custom-scrollbar">
            <button type="button" onclick="filterCategory('todas', this)" class="cat-tab-btn px-3.5 py-1.5 rounded-lg font-bold bg-nm-primary text-white shadow-xs transition">
                Todas ({{ count($servicios) }})
            </button>
            @foreach($categorias as $cat)
                <button type="button" onclick="filterCategory('{{ $cat }}', this)" class="cat-tab-btn px-3.5 py-1.5 rounded-lg font-semibold text-gray-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white transition">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        {{-- Search Input & Status Select --}}
        <div class="flex items-center gap-2.5 w-full md:w-auto shrink-0">
            <select id="statusFilter" onchange="applyFilters()" class="text-xs font-semibold bg-slate-50 dark:bg-slate-800 text-gray-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 focus:ring-2 focus:ring-nm-primary outline-none">
                <option value="todos">Todos los Estados</option>
                <option value="online">En Línea (200 OK)</option>
                <option value="warning">Advertencias (4xx / Latencia)</option>
                <option value="offline">Caídos (5xx / Timeout)</option>
            </select>

            <div class="relative flex-1 md:w-64">
                <input type="text" id="searchInput" onkeyup="applyFilters()" placeholder="Buscar sitio o URL..." class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 placeholder-gray-400 pl-8 pr-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-nm-primary outline-none">
                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></svg>
            </div>
        </div>
    </div>

    {{-- Web Services Table --}}
    <div class="bg-white dark:bg-nm-surface rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-nm-sunken border-b border-slate-200/80 dark:border-slate-800 text-gray-500 dark:text-slate-400 font-extrabold uppercase text-[11px] tracking-wider">
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
                                        {{ $web->estado === 'online' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60' : ($web->estado === 'warning' ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/60' : ($web->estado === 'offline' ? 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400 border-red-200/60 dark:border-red-800/60' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 border-slate-200/60 dark:border-slate-700/60')) }}">
                                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /> <path d="M2 12h20" /></svg>
                                    </div>
                                    <div class="min-w-0 max-w-md">
                                        <h3 class="font-extrabold text-gray-900 dark:text-white leading-snug truncate">{{ $web->nombre }}</h3>
                                        <a href="{{ $web->url }}" target="_blank" rel="noopener noreferrer" class="text-[11px] font-mono text-nm-primary dark:text-blue-400 hover:underline truncate flex items-center gap-1 group mt-0.5" title="{{ $web->url }}">
                                            <span class="truncate">{{ $web->url }}</span>
                                            <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform flex-shrink-0 opacity-70 group-hover:opacity-100" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6" /> <path d="M10 14 21 3" /> <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /></svg>
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Category --}}
                            <td class="p-3.5">
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700">
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
                                @elseif($web->estado === 'offline')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border border-red-200/70 dark:border-red-800/60" title="{{ $web->detalles_error }}">
                                        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                                        {{ $web->codigo_http ? 'HTTP ' . $web->codigo_http : 'Caído' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/70 dark:border-slate-700" title="Aún no se ha comprobado">
                                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                        Pendiente
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
                                    <button type="button" onclick="reprobarSingleService('{{ $web->id }}', this)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-nm-primary hover:text-white dark:bg-slate-800 dark:hover:bg-nm-primary text-slate-700 dark:text-slate-200 text-[11px] font-bold transition flex items-center gap-1 shadow-2xs">
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /> <path d="M21 3v5h-5" /> <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /> <path d="M8 16H3v5" /></svg>
                                        <span>Probar</span>
                                    </button>

                                    <form action="{{ route('servicios_web.destroy', $web->id) }}" method="POST" onsubmit="event.preventDefault(); nmConfirmar('¿Eliminar el servicio {{ addslashes($web->nombre) }}?', { titulo: 'Eliminar servicio', boton: 'Eliminar' }).then(ok => ok && this.submit());" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition" title="Eliminar sitio" aria-label="Eliminar sitio">
                                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /> <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /> <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" /> <line x1="10" x2="10" y1="11" y2="17" /> <line x1="14" x2="14" y1="11" y2="17" /></svg>
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
    <div class="nm-glass bg-white dark:bg-nm-surface rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="p-4 bg-slate-50 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-nm-primary text-white flex items-center justify-center font-bold shadow-xs">
                    <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14" /> <path d="M12 5v14" /></svg>
                </div>
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Agregar Nuevo Sitio Web</h3>
            </div>
            <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
            </button>
        </div>

        <form action="{{ route('servicios_web.store') }}" method="POST" class="p-5 space-y-3.5">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1">Nombre del Sitio / Servicio</label>
                <input type="text" name="nombre" required value="{{ old('nombre') }}" placeholder="Ej. Portal de Trámites Jalisco" class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-nm-primary outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1">URL (http:// o https://)</label>
                <input type="url" name="url" required value="{{ old('url') }}" placeholder="https://gobiernoenlinea1.jalisco.gob.mx/ejemplo" class="w-full text-xs font-mono bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-nm-primary outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-1">Categoría</label>
                    <select name="categoria" required class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-nm-primary outline-none">
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
                    <select name="metodo" class="w-full text-xs bg-slate-50 dark:bg-slate-800 text-gray-800 dark:text-slate-100 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-nm-primary outline-none">
                        <option value="GET">GET</option>
                        <option value="HEAD">HEAD</option>
                    </select>
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800 mt-2">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2 rounded-lg text-xs font-bold text-gray-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white transition">
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

    // Escapa texto antes de insertarlo en HTML (los mensajes de error incluyen URLs y texto del servidor)
    function escHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    @if($errors->any())
        document.addEventListener('DOMContentLoaded', () => openAddModal());
    @endif

    function filterCategory(cat, btn) {
        activeCategory = cat;
        document.querySelectorAll('.cat-tab-btn').forEach(b => {
            b.className = 'cat-tab-btn px-3.5 py-1.5 rounded-lg font-semibold text-gray-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition';
        });
        if (btn) {
            btn.className = 'cat-tab-btn px-3.5 py-1.5 rounded-lg font-bold bg-nm-primary text-white shadow-xs transition';
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
        btn.innerHTML = `<svg class="w-3.5 h-3.5 animate-spin text-nm-primary dark:text-blue-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> <span>Probando...</span>`;

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
            const iconColors = {
                online: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60',
                warning: 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/60',
                offline: 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400 border-red-200/60 dark:border-red-800/60',
            };
            iconBox.className = `web-icon-box w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 border ${
                iconColors[s.estado] || 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 border-slate-200/60 dark:border-slate-700/60'
            }`;
        }

        // 2. Status Badge Cell
        const statusCell = row.querySelector('.cell-status');
        if (statusCell) {
            const err = escHtml(s.detalles_error);
            const code = s.codigo_http ? 'HTTP ' + escHtml(s.codigo_http) : null;
            if (s.estado === 'online') {
                statusCell.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/70 dark:border-emerald-800/60">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        ${code || '200 OK'}
                    </span>`;
            } else if (s.estado === 'warning') {
                statusCell.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/70 dark:border-amber-800/60" title="${err}">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        ${code || 'Advertencia'}
                    </span>`;
            } else if (s.estado === 'offline') {
                statusCell.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border border-red-200/70 dark:border-red-800/60" title="${err}">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                        ${code || 'Caído'}
                    </span>`;
            } else {
                statusCell.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/70 dark:border-slate-700" title="Aún no se ha comprobado">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        Pendiente
                    </span>`;
            }
        }

        // 3. Latency Cell
        const latencyCell = row.querySelector('.cell-latency');
        if (latencyCell) {
            if (s.tiempo_respuesta_ms !== null && s.tiempo_respuesta_ms !== undefined) {
                const ms = Number(s.tiempo_respuesta_ms);
                const isHigh = ms > 3500;
                latencyCell.innerHTML = `
                    <span class="font-bold ${isHigh ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200'}">
                        ${ms.toFixed(1)} ms
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
                    if (elLatency) elLatency.innerHTML = `${escHtml(data.stats.avg_latency)} <span class="text-sm font-semibold text-gray-500">ms</span>`;
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
