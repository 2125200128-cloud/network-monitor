{{-- Incidentes activos de todos los sectores (los mismos de Alertas y de la campana): lo crítico primero, luego lo más reciente --}}
@php
    $inc = $incidentes;
    $chip = [
        'red' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'firewalls' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300',
        'virtualizacion' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
        'web' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
    ];
@endphp
<div class="floating-card p-5 sm:p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full {{ $inc['criticas'] ? 'bg-red-500 animate-pulse' : ($inc['total'] ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
            <h3 class="text-base font-bold text-gray-900 dark:text-white tracking-tight">Incidentes activos</h3>
            <span class="text-xs text-gray-500 dark:text-slate-400">
                @if($inc['total'])
                    {{ $inc['total'] }} en total · <span class="font-bold text-red-600 dark:text-red-400">{{ $inc['criticas'] }} {{ $inc['criticas'] === 1 ? 'crítico' : 'críticos' }}</span>
                @else
                    Ninguno en este momento
                @endif
            </span>
        </div>
        <a href="{{ route('alertas.index') }}" class="text-xs font-semibold text-hacienda-blue dark:text-blue-400 hover:underline">
            {{ $inc['total'] > count($inc['lista']) ? 'Ver los ' . $inc['total'] . ' en Alertas →' : 'Ir a Alertas →' }}
        </a>
    </div>

    @if($inc['lista'])
        <ul class="grid grid-cols-1 xl:grid-cols-2 gap-2">
            @foreach($inc['lista'] as $i)
                <li>
                    <a href="{{ $i['link'] ?? route('alertas.index') }}"
                       class="flex items-start gap-3 p-3 rounded-xl border transition hover:bg-gray-50 dark:hover:bg-slate-800/40 {{ $i['severidad'] === 'critica' ? 'border-red-100 dark:border-red-900/40' : 'border-amber-100 dark:border-amber-900/40' }}">
                        <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $i['severidad'] === 'critica' ? 'bg-red-500' : 'bg-amber-500' }}" title="{{ $i['severidad'] === 'critica' ? 'Crítica' : 'Advertencia' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $i['titulo'] }}</span>
                            <span class="block text-[11px] text-gray-500 dark:text-slate-400 truncate">
                                {{ $i['equipo'] }}@if($i['ip'] && $i['ip'] !== $i['equipo']) · <span class="font-mono">{{ $i['ip'] }}</span>@endif
                            </span>
                        </span>
                        <span class="flex flex-col items-end gap-1 shrink-0">
                            <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide {{ $chip[$i['sector']] }}">{{ \App\Services\ResumenSectores::SECTORES[$i['sector']]['nombre'] }}</span>
                            @if($i['hace'])<span class="text-[11px] font-mono text-gray-400" title="Tiempo desde que empezó">{{ $i['hace'] }}</span>@endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/20 text-sm text-emerald-700 dark:text-emerald-400">
            <svg class="w-5 h-5 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" /></svg>
            Sin incidentes activos en red, firewalls, virtualización ni sitios web.
        </div>
    @endif
</div>
