{{-- Abre la administración web del equipo en otra pestaña; se entra con el usuario propio (el monitor no guarda ni usa contraseñas para esto) --}}
@props(['url', 'texto' => 'Abrir consola', 'titulo' => null])
<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $titulo ?? $url }}" @click.stop
   {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11.5px] font-bold whitespace-nowrap border transition hover:shadow-sm']) }}
   style="color: var(--nm-primary); border-color: var(--nm-border); background: var(--nm-surface)">
    <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
    {{ $texto }}
</a>
