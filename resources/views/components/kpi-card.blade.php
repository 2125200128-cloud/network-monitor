{{--
    Tarjeta KPI: título, valor grande, comparación con insignia, mini gráfica (slot) y pie "Ver detalles".
    Props: titulo, valor, unidad?, comparacion?, badge?, badgeTono (ok|warn|crit|neutral), valorTono?, detalle? (pestaña del diagnóstico),
           escena? (ilustración animada de fondo, ver components/kpi-escena), escenaTono?
--}}
@props(['titulo', 'valor', 'unidad' => null, 'comparacion' => null, 'badge' => null, 'badgeTono' => 'neutral', 'valorTono' => null, 'detalle' => null, 'ayuda' => null, 'escena' => null, 'escenaTono' => null])
@php
    $badgeClases = [
        'ok' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
        'warn' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400',
        'crit' => 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400',
        'neutral' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    ][$badgeTono] ?? '';
    $valorClases = [
        'ok' => 'text-emerald-600 dark:text-emerald-400',
        'warn' => 'text-amber-600 dark:text-amber-400',
        'crit' => 'text-red-600 dark:text-red-400',
    ][$valorTono] ?? 'text-gray-900 dark:text-white';
@endphp
<div class="rounded-2xl bg-gray-50 dark:bg-nm-sunken border border-gray-100 dark:border-slate-800/60 flex flex-col overflow-hidden" @if($ayuda) title="{{ $ayuda }}" @endif>
    <div class="relative overflow-hidden bg-white dark:bg-nm-surface rounded-2xl border-b border-gray-100 dark:border-slate-800/60 p-4 flex-1">
        @if($escena)<x-kpi-escena :tipo="$escena" :tono="$escenaTono" centro />@endif
        <p class="relative text-[13px] font-semibold text-gray-800 dark:text-slate-200">{{ $titulo }}</p>
        <div class="relative flex items-end justify-between gap-3 mt-2">
            <div class="min-w-0">
                <p class="flex items-baseline gap-1">
                    <span class="text-3xl font-extrabold tracking-tight leading-none {{ $valorClases }}">{{ $valor }}</span>
                    @if($unidad)<span class="text-sm font-semibold text-gray-400">{{ $unidad }}</span>@endif
                </p>
                <p class="flex flex-wrap items-center gap-1.5 mt-2 text-[11px] text-gray-500 dark:text-slate-400">
                    @if($comparacion)<span>{{ $comparacion }}</span>@endif
                    @if($badge)<span class="px-1.5 py-0.5 rounded-md text-[11px] font-bold {{ $badgeClases }}">{{ $badge }}</span>@endif
                </p>
            </div>
            <div class="shrink-0 w-24 h-12 flex items-end justify-end">{{ $slot }}</div>
        </div>
    </div>
    @if($detalle)
        <button type="button" onclick="abrirDiagnostico('{{ $detalle }}')"
                class="py-2 text-xs font-semibold text-gray-600 dark:text-slate-400 hover:text-hacienda-blue dark:hover:text-blue-400 transition flex items-center justify-center gap-1">
            Ver detalles
            <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14" /> <path d="m12 5 7 7-7 7" /></svg>
        </button>
    @endif
</div>
