{{-- Cifra resumen dentro de una pestaña. Parámetros: $label, $valor, $tono? (ok|warn|crit), $title? --}}
@php
    $color = ['ok' => 'text-emerald-600 dark:text-emerald-400', 'warn' => 'text-amber-600 dark:text-amber-400',
              'crit' => 'text-red-600 dark:text-red-400'][$tono ?? ''] ?? 'text-gray-900 dark:text-white';
@endphp
<div class="rounded-xl bg-white dark:bg-[#0d1017] border border-gray-100 dark:border-slate-800/60 px-3 py-2.5" @isset($title) title="{{ $title }}" @endisset>
    <p class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider leading-tight">{{ $label }}</p>
    <p class="text-lg font-extrabold font-mono {{ $color }}">{{ $valor }}</p>
</div>
