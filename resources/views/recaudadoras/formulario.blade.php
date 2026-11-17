@extends('layouts.app')

@section('title', $r->exists ? 'Editar ' . $r->titulo() : 'Nueva recaudadora')

@section('styles')
<style>
    .rc-titulo { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .rc-campo { font-size: 12.5px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); width: 100%; }
    .rc-btn { font-size: 11.5px; font-weight: 700; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-primary); }
    .rc-btn-p { color: #fff; background: var(--nm-primary); border-color: var(--nm-primary); }
    .rc-etq { display: block; font-size: 11px; font-weight: 700; color: var(--nm-text-muted); }
    .rc-etq > :is(input, select, textarea) { margin-top: 4px; }
    .rc-ayuda { font-size: 11px; color: var(--nm-text-muted); margin-top: 3px; font-weight: 400; }
</style>
@endsection

@section('content')
@php
    $v = fn (string $campo) => old($campo, $r->getAttribute($campo));
    $coord = (string) $v('coordinadora');
    $opcionesCoord = array_unique(array_merge(array_map('strval', range(1, 8)),
        preg_match('/^([1-9]\d?|Metropolitano)$/', $coord) ? [$coord] : [], ['Metropolitano']));
    usort($opcionesCoord, 'strnatcmp');
@endphp
<div class="flex flex-col space-y-4 pb-6" x-data="{ tipo: @js($v('tipo') ?: 'recaudadora') }">
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ $r->exists ? route('recaudadoras.show', $r->id) : route('recaudadoras.index') }}" class="rc-btn" title="Volver" aria-label="Volver">←</a>
            <div class="min-w-0">
                <h1 class="text-lg font-black leading-tight truncate" style="color: var(--nm-text)">{{ $r->exists ? 'Editar ' . $r->titulo() : 'Nueva recaudadora u oficina' }}</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">Los datos viven en la base de datos del sistema: lo que guardes aquí es lo que se monitorea y lo que una importación del Excel ya no cambia.</p>
            </div>
        </div>
    </div>

    @include('recaudadoras.partials.mensajes')

    <form method="POST" action="{{ $r->exists ? route('recaudadoras.actualizar', $r->id) : route('recaudadoras.guardar') }}" class="flex flex-col space-y-4">
        @csrf
        @if($r->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Identificación --}}
            <div class="floating-card p-4 space-y-3">
                <h2 class="rc-titulo">Identificación</h2>
                <div class="grid grid-cols-2 gap-3">
                    <label class="rc-etq">Tipo
                        <select name="tipo" class="rc-campo" x-model="tipo">
                            @foreach(\App\Models\Recaudadora::TIPOS as $k => $etq)
                                <option value="{{ $k }}" @selected($v('tipo') === $k)>{{ $etq }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="rc-etq">Número <span x-show="tipo === 'recaudadora'">*</span>
                        <input name="numero" value="{{ $v('numero') }}" maxlength="5" class="rc-campo font-mono" placeholder="Ej. 94 o 002A" :required="tipo === 'recaudadora'">
                    </label>
                </div>
                <label class="rc-etq">Nombre *
                    <input name="nombre" value="{{ $v('nombre') }}" required maxlength="150" class="rc-campo" placeholder="Ej. Tlaquepaque">
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="rc-etq">Coordinadora
                        <select name="coordinadora" class="rc-campo">
                            <option value="">— Sin coordinadora —</option>
                            @foreach($opcionesCoord as $c)
                                <option value="{{ $c }}" @selected($coord === $c)>{{ is_numeric($c) ? 'Coordinadora ' . $c : $c }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="rc-etq">Municipio (lugar en el mapa)
                        <select name="municipio_cve" class="rc-campo">
                            <option value="">{{ $r->exists ? '— Sin municipio —' : '— Detectar por el nombre —' }}</option>
                            @foreach($municipios as $m)
                                <option value="{{ $m['cve'] }}" @selected($v('municipio_cve') === $m['cve'])>{{ $m['nombre'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="flex items-center gap-2 text-xs pt-1" style="color: var(--nm-text)">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" @checked((bool) $v('activo'))>
                    Monitorear (desmárcalo si la oficina cerró o está en obra: no se revisa ni avisa)
                </label>
            </div>

            {{-- Comunicación --}}
            <div class="floating-card p-4 space-y-3">
                <h2 class="rc-titulo">Comunicación</h2>
                <label class="rc-etq">IP administrativa
                    <input name="ip_administrativa" value="{{ $v('ip_administrativa') }}" class="rc-campo font-mono" placeholder="10.4.14.1">
                    <p class="rc-ayuda">La puerta de enlace de su red (la del FortiGate hacia la oficina). Define si tiene servicio.</p>
                </label>
                <label class="rc-etq">IP loopback
                    <input name="ip_loopback" value="{{ $v('ip_loopback') }}" class="rc-campo font-mono" placeholder="10.13.150.10">
                    <p class="rc-ayuda">El equipo por cualquier enlace (principal o backup). Distingue «sin comunicación» de «falla de red interna».</p>
                </label>
                @if($r->exists)
                    <p class="rc-ayuda">Si cambias una IP, lo que se sabía de la anterior se descarta y la oficina se vuelve a revisar desde cero.</p>
                @endif
                <label class="rc-etq">Notas
                    <textarea name="notas" rows="4" maxlength="2000" class="rc-campo" placeholder="Contacto, horario, observaciones…">{{ $v('notas') }}</textarea>
                </label>
            </div>
        </div>

        {{-- Enlaces --}}
        <div class="floating-card p-4">
            <h2 class="rc-titulo mb-3">Enlaces y proveedores</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach(\App\Models\Recaudadora::CAMPOS_ENLACE as $campo => [$etq, $largo])
                    <label class="rc-etq">{{ $etq }}
                        <input name="{{ $campo }}" value="{{ $v($campo) }}" maxlength="{{ $largo }}" class="rc-campo">
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex gap-2">
                <button type="submit" class="rc-btn rc-btn-p">{{ $r->exists ? 'Guardar cambios' : 'Dar de alta' }}</button>
                <a href="{{ $r->exists ? route('recaudadoras.show', $r->id) : route('recaudadoras.index') }}" class="rc-btn">Cancelar</a>
            </div>
        </div>
    </form>

    @if($r->exists)
        <div class="floating-card p-4 flex flex-wrap items-center justify-between gap-3" style="border-color: #dc262640">
            <p class="text-xs" style="color: var(--nm-text-muted)">
                <b style="color: #dc2626">Borrar del sistema:</b> se borran también sus {{ $r->equipos()->count() }} equipos y su historial de caídas.
                Si la oficina solo cerró, mejor desmarca «Monitorear».
            </p>
            <form method="POST" action="{{ route('recaudadoras.borrar', $r->id) }}"
                  onsubmit="event.preventDefault(); nmConfirmar(@js('¿Borrar ' . $r->titulo() . ' del sistema? Se borran sus equipos y su historial. No se puede deshacer.'), { titulo: 'Borrar recaudadora', boton: 'Borrar' }).then(ok => ok && this.submit());">
                @csrf @method('DELETE')
                <button type="submit" class="rc-btn" style="color: #dc2626">Borrar</button>
            </form>
        </div>
    @endif
</div>
@endsection
