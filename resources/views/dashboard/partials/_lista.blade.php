{{--
    Lista compacta. Parámetros:
      $titulo, $items (array de arrays), $cols: [['key' => ..., 'label' => ..., 'align' => 'text-right', 'fmt' => fn($item)]],
      $vacio? (texto sin elementos), $badge?, $badgeClass?, $nota?
    La columna con key "dispositivo" enlaza al detalle si el item trae disp_id.
    Las filas se emiten sin indentación: con cientos de celdas, el espacio en blanco de Blade pesaba más que el contenido.
--}}
@php $items = $items ?? []; @endphp
<div class="rounded-xl border border-gray-100 dark:border-slate-800/60 bg-gray-50/60 dark:bg-nm-sunken p-4 min-w-0">
    <div class="flex items-center justify-between gap-2 mb-2.5">
        <h4 class="text-[11px] font-bold text-gray-700 dark:text-slate-200 uppercase tracking-wider">{{ $titulo }}</h4>
        @isset($badge)
            <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full shrink-0 {{ $badgeClass ?? 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">{{ $badge }}</span>
        @endisset
    </div>
    @if(empty($items))
        <p class="text-xs text-gray-400 dark:text-slate-500 py-3 text-center">{{ $vacio ?? 'Sin datos' }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="dash-lista w-full text-xs">
                <thead><tr>@foreach($cols as $c)<th class="{{ $c['align'] ?? 'text-left' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
                <tbody>
@foreach($items as $it)
<tr>@foreach($cols as $c)@php $v = isset($c['fmt']) ? $c['fmt']($it) : ($it[$c['key']] ?? null); @endphp<td class="{{ $c['align'] ?? '' }} {{ $c['class'] ?? '' }}">@if(($c['key'] ?? null) === 'dispositivo' && !empty($it['disp_id']))<a href="{{ route('dispositivos.show', $it['disp_id']) }}" class="dash-disp" title="{{ $v }}" aria-label="{{ $v }}">{{ $v }}</a>@else{{ $v ?? '—' }}@endif</td>@endforeach</tr>
@endforeach
                </tbody>
            </table>
        </div>
    @endif
    @isset($nota)
        <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-2 leading-snug">{{ $nota }}</p>
    @endisset
</div>
