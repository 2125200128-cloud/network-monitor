{{-- Tarjeta KPI. Parámetros: $titulo, $valor, $unidad?, $sub?, $tono (ok|warn|crit|neutral), $title? --}}
@php
    $tonos = [
        'ok' => ['bar' => 'bg-emerald-500', 'txt' => 'text-emerald-600 dark:text-emerald-400'],
        'warn' => ['bar' => 'bg-amber-500', 'txt' => 'text-amber-600 dark:text-amber-400'],
        'crit' => ['bar' => 'bg-red-500', 'txt' => 'text-red-600 dark:text-red-400'],
        'neutral' => ['bar' => 'bg-[#3b5998]', 'txt' => 'text-gray-900 dark:text-white'],
    ];
    $t = $tonos[$tono ?? 'neutral'] ?? $tonos['neutral'];
@endphp
<div class="floating-card p-4 relative overflow-hidden" @isset($title) title="{{ $title }}" @endisset>
    <span class="absolute left-0 top-0 bottom-0 w-1 {{ $t['bar'] }}"></span>
    <p class="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider font-mono">{{ $titulo }}</p>
    <div class="flex items-baseline gap-1 mt-1">
        <span class="text-2xl font-extrabold font-mono tracking-tight {{ $t['txt'] }}">{{ $valor }}</span>
        @if(!empty($unidad))<span class="text-xs font-mono text-gray-500">{{ $unidad }}</span>@endif
    </div>
    @if(!empty($sub))<p class="text-[11px] text-gray-500 dark:text-slate-400 mt-1 leading-snug">{{ $sub }}</p>@endif
</div>
