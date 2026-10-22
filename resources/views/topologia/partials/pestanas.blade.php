{{-- Pestañas de Topología: red completa y centro de datos --}}
<nav class="nm-seg" aria-label="Vistas de topología" style="display: inline-flex; gap: 2px; padding: 2px; border-radius: calc(var(--nm-radius-sm) + 2px); background: var(--nm-sunken); border: 1px solid var(--nm-border);">
    @foreach([
        'red' => ['Red completa', route('topologia.index')],
        'centro_datos' => ['Centro de datos', route('topologia.centro_datos')],
    ] as $clave => [$texto, $url])
        <a href="{{ $url }}" @if($activa === $clave) aria-current="page" @endif
           class="inline-flex items-center h-8 px-3 rounded-lg text-[13px] font-semibold whitespace-nowrap transition"
           style="{{ $activa === $clave
                ? 'color:#fff; background: var(--nm-metal-sheen), var(--nm-metal-azure); box-shadow: 0 1px 4px -1px rgb(var(--nm-primary-rgb) / 0.6);'
                : 'color: var(--nm-text-muted);' }}">{{ $texto }}</a>
    @endforeach
</nav>
