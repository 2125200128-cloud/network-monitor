{{-- Lo más cargado de cada sector, aunque todavía no sea alarma: barra y color por umbral. Solo lecturas vigentes. --}}
@php
    $barra = ['ok' => 'bg-emerald-500', 'warn' => 'bg-amber-500', 'crit' => 'bg-red-500'];
    $texto = ['ok' => 'text-gray-800 dark:text-slate-200', 'warn' => 'text-amber-600 dark:text-amber-400', 'crit' => 'text-red-600 dark:text-red-400'];
@endphp
<div class="floating-card p-5 sm:p-6 h-full flex flex-col">
    <div class="mb-4">
        <h3 class="text-base font-bold text-gray-900 dark:text-white tracking-tight">Lo que más preocupa ahora</h3>
        <p class="text-xs text-gray-500 dark:text-slate-400">Las lecturas más altas de cada sector; ámbar y rojo, cerca o encima de su umbral</p>
    </div>
    <div class="flex-1 space-y-5">
        @foreach($atencion as $g)
            <section>
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500 mb-2">
                    {{ $g['titulo'] }} <span class="font-semibold normal-case tracking-normal">· {{ \App\Services\ResumenSectores::SECTORES[$g['sector']]['nombre'] }}</span>
                </h4>
                @forelse($g['items'] as $i)
                    <a href="{{ $i['link'] }}" class="block py-1.5 group" @if($i['detalle']) title="{{ $i['detalle'] }}" @endif>
                        <span class="flex items-baseline justify-between gap-3 text-xs">
                            <span class="min-w-0 truncate text-gray-700 dark:text-slate-300 group-hover:text-hacienda-blue dark:group-hover:text-blue-400">
                                {{ $i['nombre'] }}@if($i['detalle'])<span class="text-gray-400 dark:text-slate-500"> · {{ $i['detalle'] }}</span>@endif
                            </span>
                            <span class="shrink-0 font-bold font-mono {{ $texto[$i['tono']] ?? $texto['ok'] }}">{{ $i['texto'] }}</span>
                        </span>
                        <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-slate-800 overflow-hidden">
                            <span class="block h-full rounded-full {{ $barra[$i['tono']] ?? $barra['ok'] }}" style="width: {{ max(2, min(100, $i['barra'])) }}%"></span>
                        </span>
                    </a>
                @empty
                    <p class="text-xs text-gray-400 dark:text-slate-500 py-1">{{ $g['vacio'] }}</p>
                @endforelse
            </section>
        @endforeach
    </div>
</div>
