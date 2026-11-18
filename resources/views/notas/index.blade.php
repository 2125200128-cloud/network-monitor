@extends('layouts.app')

@section('title', 'Notas')

@section('styles')
<style>
    .nt-titulo { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .nt-campo { font-size: 13px; padding: 7px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); width: 100%; }
    .nt-campo::placeholder { color: var(--nm-text-muted); opacity: .6; }
    .nt-btn { font-size: 11.5px; font-weight: 700; padding: 5px 11px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-primary); white-space: nowrap; }
    .nt-btn-p { color: #fff; background: var(--nm-primary); border-color: var(--nm-primary); }
    .nt-btn.copiado { color: #fff; background: #059669; border-color: #059669; }
    .nt-bloque { position: relative; border: 1px solid var(--nm-border); border-radius: 10px; background: var(--nm-sunken); }
    .nt-bloque pre { margin: 0; padding: 10px 92px 10px 12px; font-family: ui-monospace, Consolas, monospace; font-size: 12.5px; line-height: 1.5;
        color: var(--nm-text); white-space: pre-wrap; word-break: break-word; max-height: 22rem; overflow: auto; }
    .nt-bloque .nt-btn { position: absolute; top: 7px; right: 7px; }
    .nt-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 9999px; border: 1px solid var(--nm-border); color: var(--nm-text-muted); background: var(--nm-sunken); white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="flex flex-col space-y-4 pb-6">
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" /><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M12 11h4M12 16h4M8 11h.01M8 16h.01" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Notas</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">Pega aquí comandos o texto en una PC y cópialos en otra (p. ej. la de una pantalla) con un botón. Cada nota se borra sola al caducar.</p>
            </div>
        </div>
    </div>

    @include('recaudadoras.partials.mensajes')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        {{-- Nueva nota --}}
        <form method="POST" action="{{ route('notas.guardar') }}" class="floating-card p-4 space-y-3" id="ntForm">
            @csrf
            <h2 class="nt-titulo">Nueva nota</h2>
            <label class="block text-[11px] font-bold" style="color: var(--nm-text-muted)">Título (opcional)
                <input name="titulo" value="{{ old('titulo') }}" maxlength="120" class="nt-campo mt-1" placeholder="Ej. CA del NOC en las pantallas Ubuntu">
            </label>
            <label class="block text-[11px] font-bold" style="color: var(--nm-text-muted)">Texto o comandos
                <textarea name="contenido" rows="12" required maxlength="20000" class="nt-campo mt-1 font-mono" style="font-size: 12.5px"
                          placeholder="sudo apt install -y libnss3-tools&#10;&#10;sudo update-ca-certificates&#10;&#10;(una línea en blanco separa cada bloque: cada uno tendrá su botón Copiar)">{{ old('contenido') }}</textarea>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-[11px] font-bold" style="color: var(--nm-text-muted)">Se borra en
                    <select name="duracion" class="nt-campo mt-1">
                        @foreach($duraciones as $k => [$etiqueta])
                            <option value="{{ $k }}" @selected(old('duracion', '1d') === $k)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-xs self-end pb-2" style="color: var(--nm-text)">
                    <input type="hidden" name="privada" value="0">
                    <input type="checkbox" name="privada" value="1" @checked(old('privada'))>
                    Solo yo (privada)
                </label>
            </div>
            <p class="text-[11px]" style="color: var(--nm-text-muted)">Las notas compartidas las ve todo el equipo del NOC: no pegues contraseñas. Ctrl + Enter para guardar.</p>
            <button type="submit" class="nt-btn nt-btn-p">Guardar nota</button>
        </form>

        {{-- Notas vigentes (se actualizan solas: lo que se guarda en otra PC aparece aquí) --}}
        <div class="xl:col-span-2 space-y-3" data-refrescar="notas" data-refrescar-cada="10">
            @forelse($notas as $n)
                @php $bloques = $n->bloques(); $puedeBorrar = $esAdmin || ($n->user_id === auth()->id() && !$n->esFija()); @endphp
                <article class="floating-card p-4" aria-labelledby="nt-t-{{ $n->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                        <div class="min-w-0">
                            <h3 id="nt-t-{{ $n->id }}" class="text-sm font-bold truncate" style="color: var(--nm-text)">{{ $n->titulo ?: 'Sin título' }}</h3>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1 text-[11px]" style="color: var(--nm-text-muted)">
                                <span>{{ $n->autor?->name ?? ($n->user_id ? 'Usuario eliminado' : 'Sistema NOC') }} · {{ $n->created_at->locale('es')->diffForHumans() }}</span>
                                @if($n->esFija())
                                    <span class="nt-chip" style="color: var(--nm-primary); border-color: var(--nm-primary)" title="Nota fija: no se borra sola (solo un Admin la puede borrar)">Fija</span>
                                @else
                                    <span class="nt-chip" title="Se borra el {{ $n->expira_en->format('d/m/Y H:i') }}">Se borra {{ $n->expira_en->locale('es')->diffForHumans() }}</span>
                                @endif
                                @if($n->privada)<span class="nt-chip">Solo yo</span>@endif
                                @if(count($bloques) > 1)<span class="nt-chip">{{ count($bloques) }} bloques</span>@endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="nt-btn" data-copiar="#nt-todo-{{ $n->id }}">Copiar todo</button>
                            @if($puedeBorrar)
                                <form method="POST" action="{{ route('notas.borrar', $n->id) }}"
                                      onsubmit="event.preventDefault(); nmConfirmar('¿Borrar esta nota? Ya no se podrá copiar en ninguna PC.', { titulo: 'Borrar nota', boton: 'Borrar' }).then(ok => ok && this.submit());">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="nt-btn" style="color: #dc2626">Borrar</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <pre id="nt-todo-{{ $n->id }}" hidden>{{ $n->contenido }}</pre>
                    <div class="space-y-2">
                        @foreach($bloques as $i => $b)
                            <div class="nt-bloque">
                                <pre id="nt-{{ $n->id }}-{{ $i }}">{{ $b }}</pre>
                                <button type="button" class="nt-btn" data-copiar="#nt-{{ $n->id }}-{{ $i }}">Copiar</button>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="floating-card p-8 text-center text-sm" style="color: var(--nm-text-muted)">
                    No hay notas vigentes. Escribe una a la izquierda y ábrela desde la otra PC en esta misma página.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Copiar al portapapeles: API moderna si el navegador la permite (HTTPS) y si no, el método clásico.
    // Delegado en el documento para que funcione también después de cada refresco automático de la lista.
    async function ntCopiar(texto) {
        if (navigator.clipboard && window.isSecureContext) {
            try { await navigator.clipboard.writeText(texto); return true; } catch (e) { /* se intenta el método clásico */ }
        }
        const t = document.createElement('textarea');
        t.value = texto;
        t.setAttribute('readonly', '');
        t.style.position = 'fixed';
        t.style.opacity = '0';
        document.body.appendChild(t);
        t.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        t.remove();
        return ok;
    }

    document.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('[data-copiar]');
        if (!btn) return;
        const origen = document.querySelector(btn.dataset.copiar);
        if (!origen) return;
        const ok = await ntCopiar(origen.textContent);
        const texto = btn.textContent;
        btn.textContent = ok ? 'Copiado' : 'No se pudo';
        btn.classList.toggle('copiado', ok);
        setTimeout(() => { btn.textContent = texto; btn.classList.remove('copiado'); }, 1600);
    });

    // Ctrl + Enter guarda la nota
    document.querySelector('#ntForm textarea')?.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) document.getElementById('ntForm').requestSubmit();
    });
</script>
@endsection
