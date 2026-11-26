@extends('layouts.app')

@section('title', $r->titulo())

@section('styles')
<style>
    .rc-titulo { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .rc-campo { font-size: 12.5px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); }
    .rc-btn { font-size: 11.5px; font-weight: 700; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-primary); }
    .rc-btn-p { color: #fff; background: var(--nm-primary); border-color: var(--nm-primary); }
    .rc-dato { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid var(--nm-border); font-size: 12.5px; }
    .rc-dato:last-child { border-bottom: 0; }
    .rc-dato dt { color: var(--nm-text-muted); white-space: nowrap; }
    .rc-dato dd { color: var(--nm-text); text-align: right; font-weight: 600; word-break: break-word; }
    .rc-tabla { width: 100%; border-collapse: collapse; }
    .rc-tabla th { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); text-align: left; padding: 7px 9px; border-bottom: 1px solid var(--nm-border); white-space: nowrap; }
    .rc-tabla td { padding: 6px 9px; border-bottom: 1px solid var(--nm-border); font-size: 12.5px; color: var(--nm-text); white-space: nowrap; }
    .rc-punto { width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .rc-dia { flex: 1; height: 34px; border-radius: 3px; min-width: 4px; }
</style>
@endsection

@section('content')
@php
    $estados = \App\Models\Recaudadora::ESTADOS;
    $estado = $r->estadoVisible();
    $info = $estados[$estado];
    $porLoopback = $estado === 'online' && !$r->admin_ok && $r->loopback_ok;
    $ok = fn ($v) => $v === null ? ['—', 'var(--nm-text-muted)'] : ($v ? ['Responde', '#059669'] : ['No responde', '#dc2626']);
    $dispColor = fn ($v) => $v === null ? 'var(--nm-text-muted)' : ($v >= 99.5 ? '#059669' : ($v >= 97 ? '#d97706' : '#dc2626'));
    $fmtDisp = fn ($v) => $v === null ? '—' : ($v >= 99.995 ? '100' : number_format($v, 2)) . ' %';
    $enlaces = array_filter([
        'Tipo de enlace' => $r->tipo_enlace,
        'Uninet (Telmex)' => $r->uninet,
        'Referencia Uninet' => $r->referencia,
        'ID sitio Red Jalisco' => $r->id_sitio_red_jalisco,
        'Velocidad Red Jalisco' => $r->velocidad_red_jalisco,
        'Bestel' => $r->bestel,
        'Referencia Bestel' => $r->bestel_referencia,
        'Global King' => $r->global_king,
        'Enlace backup' => $r->enlace_backup,
        'Gobierno del Estado' => $r->gobierno_estado,
        'HEMAC' => $r->hemac,
        'Número de referencia' => $r->numero_referencia,
    ], fn ($v) => $v !== null && $v !== '');
    $duracion = fn (int $s) => \App\Console\Commands\RevisarRecaudadorasCommand::duracion($s);
@endphp
<div class="flex flex-col space-y-4 pb-6">
    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('recaudadoras.index') }}" class="rc-btn" title="Volver al tablero" aria-label="Volver al tablero">←</a>
            <div class="min-w-0">
                <h1 class="text-lg font-black leading-tight truncate" style="color: var(--nm-text)">{{ $r->titulo() }}</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">
                    {{ $r->tipo === 'oficina' ? 'Otra oficina de Hacienda' : 'Recaudadora' }}
                    @if($r->coordinadora) · Coordinadora {{ $r->coordinadora }} @endif
                    @if($r->municipio) · {{ $r->municipio }} @endif
                    @unless($r->activo) · <b style="color: #dc2626">sin monitoreo</b> @endunless
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl" style="background: {{ $info['color'] }}1a; color: {{ $info['color'] }}">
            <span class="rc-punto" style="background: {{ $info['color'] }}; width: 11px; height: 11px"></span>
            <b class="text-sm">{{ $info['etiqueta'] }}</b>
            @if($r->estado_desde && in_array($estado, ['online', 'offline', 'lan'], true))
                <span class="text-xs opacity-80">desde {{ $r->estado_desde->format($r->estado_desde->isToday() ? 'H:i' : 'd/m/Y H:i') }}
                    @if($estado !== 'online') ({{ $duracion((int) $r->estado_desde->diffInSeconds(now(), true)) }}) @endif</span>
            @endif
        </div>
    </div>

    @include('recaudadoras.partials.mensajes')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Comunicación --}}
        <div class="floating-card p-4">
            <h2 class="rc-titulo mb-2">Comunicación</h2>
            <dl>
                <div class="rc-dato"><dt>IP administrativa</dt><dd class="font-mono">{{ $r->ip_administrativa ?: '—' }}
                    @if($r->ip_administrativa) <span class="ml-1" style="color: {{ $ok($r->admin_ok)[1] }}">{{ $ok($r->admin_ok)[0] }}</span> @endif</dd></div>
                <div class="rc-dato"><dt>IP loopback</dt><dd class="font-mono">{{ $r->ip_loopback ?: '—' }}
                    @if($r->ip_loopback) <span class="ml-1" style="color: {{ $ok($r->loopback_ok)[1] }}">{{ $ok($r->loopback_ok)[0] }}</span> @endif</dd></div>
                <div class="rc-dato"><dt>Latencia</dt><dd class="font-mono">{{ $r->latencia_ms !== null ? round($r->latencia_ms, 1) . ' ms' : '—' }}</dd></div>
                <div class="rc-dato"><dt>Última revisión</dt><dd>{{ $r->ultimo_chequeo?->format('d/m/Y H:i:s') ?? 'nunca' }}</dd></div>
            </dl>
            <p class="text-[11.5px] mt-3 leading-relaxed" style="color: var(--nm-text-muted)">
                @if($porLoopback)
                    Su IP administrativa nunca ha respondido ping (lo bloquea esa interfaz), así que se monitorea por su loopback.
                @elseif($estado === 'lan')
                    El equipo de la recaudadora responde por su loopback, pero su red interna (la IP administrativa) dejó de responder: revisar switch, cableado o la interfaz LAN.
                @elseif($estado === 'offline')
                    No responde ni su IP administrativa ni su loopback: falla del enlace del proveedor o equipo apagado. Para el reporte con el proveedor usa la referencia del enlace.
                @else
                    La IP administrativa es la puerta de enlace de su red; la loopback, el equipo por cualquier enlace (principal o backup).
                @endif
            </p>
        </div>

        {{-- Disponibilidad --}}
        <div class="floating-card p-4">
            <h2 class="rc-titulo mb-2">Disponibilidad de comunicación</h2>
            <div class="grid grid-cols-3 gap-2 mb-4">
                @foreach($disponibilidad as $ventana => $v)
                    <div class="text-center p-2 rounded-lg" style="border: 1px solid var(--nm-border)">
                        <div class="text-[10.5px] font-bold uppercase tracking-wider" style="color: var(--nm-text-muted)">{{ $ventana }}</div>
                        <div class="text-lg font-black font-mono" style="color: {{ $dispColor($v) }}">{{ $fmtDisp($v) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="text-[10.5px] font-bold uppercase tracking-wider mb-1" style="color: var(--nm-text-muted)">Últimos 30 días</div>
            <div class="flex gap-[2px]">
                @foreach($porDia as $dia => $v)
                    <div class="rc-dia" style="background: {{ $v === null ? 'color-mix(in srgb, var(--nm-text-muted) 18%, transparent)' : $dispColor($v) }}"
                         title="{{ \Carbon\Carbon::parse($dia)->format('d/m/Y') }}: {{ $v === null ? 'sin datos' : $fmtDisp($v) }}"></div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10.5px] mt-1" style="color: var(--nm-text-muted)">
                <span>{{ \Carbon\Carbon::parse(array_key_first($porDia))->format('d/m') }}</span><span>hoy</span>
            </div>
            <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)">Solo cuenta «sin comunicación»; la falla de red interna no. Gris: aún no se monitoreaba.</p>
        </div>

        {{-- Enlaces --}}
        <div class="floating-card p-4">
            <h2 class="rc-titulo mb-2">Enlaces</h2>
            @if($enlaces)
                <dl>
                    @foreach($enlaces as $etiqueta => $valor)
                        <div class="rc-dato"><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>
                    @endforeach
                </dl>
            @else
                <p class="text-xs" style="color: var(--nm-text-muted)">Sin datos de enlace registrados.@if($esAdmin) Se capturan en «Editar datos».@endif</p>
            @endif
        </div>
    </div>

    {{-- Historial de ping: disponibilidad y tiempo de respuesta (como las gráficas de Zabbix) --}}
    <div class="floating-card p-4" x-data="historialPing()" x-init="cargar()">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div>
                <h2 class="rc-titulo">Historial de ping</h2>
                <p class="text-[11px]" style="color: var(--nm-text-muted)">Una revisión por minuto a la IP administrativa y a la loopback.</p>
            </div>
            <div class="flex gap-1" role="group" aria-label="Periodo de las gráficas">
                @foreach(['6h' => '6 h', '24h' => '24 h', '7d' => '7 días', '30d' => '30 días', '90d' => '90 días'] as $clave => $texto)
                    <button type="button" class="rc-btn" :class="rango === '{{ $clave }}' && 'rc-btn-p'" :aria-pressed="(rango === '{{ $clave }}').toString()"
                            @click="rango = '{{ $clave }}'; cargar()">{{ $texto }}</button>
                @endforeach
            </div>
        </div>

        <p x-show="error" x-cloak class="text-xs py-6 text-center" style="color: #dc2626" x-text="error"></p>
        <p x-show="!error && datos && !datos.resumen.muestras" x-cloak class="text-xs py-6 text-center" style="color: var(--nm-text-muted)">
            Todavía no hay mediciones en este periodo. El historial se guarda desde que se actualizó el sistema: las gráficas se llenan con cada revisión (una por minuto).
        </p>

        <div x-show="datos && datos.resumen.muestras" x-cloak>
            <div class="flex items-baseline justify-between gap-2">
                <h3 class="text-[11px] font-bold uppercase tracking-wider" style="color: var(--nm-text-muted)">Responde al ping</h3>
                <span class="text-xs" style="color: var(--nm-text-muted)">Disponibilidad del periodo:
                    <b class="font-mono" :style="`color: ${colorDisp(datos?.resumen.disponibilidad)}`" x-text="fmtPct(datos?.resumen.disponibilidad)"></b></span>
            </div>
            <div style="height: 110px" class="mt-1"><canvas x-ref="disp" aria-label="Disponibilidad por intervalo" role="img"></canvas></div>

            <h3 class="text-[11px] font-bold uppercase tracking-wider mt-4" style="color: var(--nm-text-muted)">Tiempo de respuesta</h3>
            <div style="height: 200px" class="mt-1"><canvas x-ref="lat" aria-label="Tiempo de respuesta del ping" role="img"></canvas></div>

            {{-- Resumen como el de Zabbix: último, mínimo, media y máximo --}}
            <table class="rc-tabla mt-3">
                <thead><tr><th></th><th style="text-align: right">Último</th><th style="text-align: right">Mínimo</th><th style="text-align: right">Media</th><th style="text-align: right">Máximo</th></tr></thead>
                <tbody>
                    <template x-for="fila in filasResumen()" :key="fila.nombre">
                        <tr>
                            <td><span class="flex items-center gap-1.5"><span class="rc-punto" :style="`background: ${fila.color}`"></span><span x-text="fila.nombre"></span></span></td>
                            <td class="text-right font-mono" x-text="fmtMs(fila.d.ultimo)"></td>
                            <td class="text-right font-mono" x-text="fmtMs(fila.d.min)"></td>
                            <td class="text-right font-mono" x-text="fmtMs(fila.d.avg)"></td>
                            <td class="text-right font-mono" x-text="fmtMs(fila.d.max)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            {{-- Pérdida de paquetes: 5 paquetes ICMP por IP en cada revisión --}}
            <template x-if="datos?.resumen.perdida">
                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-wider mt-5" style="color: var(--nm-text-muted)">Pérdida de paquetes</h3>
                    <div style="height: 150px" class="mt-1"><canvas x-ref="per" aria-label="Pérdida de paquetes del ping" role="img"></canvas></div>
                    <table class="rc-tabla mt-3">
                        <thead><tr><th></th><th style="text-align: right">Último</th><th style="text-align: right">Media</th><th style="text-align: right">Máximo</th></tr></thead>
                        <tbody>
                            <template x-for="fila in filasPerdida()" :key="fila.nombre">
                                <tr>
                                    <td><span class="flex items-center gap-1.5"><span class="rc-punto" :style="`background: ${fila.color}`"></span><span x-text="fila.nombre"></span></span></td>
                                    <td class="text-right font-mono" x-text="fmtPerdida(fila.d.ultimo)"></td>
                                    <td class="text-right font-mono" x-text="fmtPerdida(fila.d.avg)"></td>
                                    <td class="text-right font-mono" x-text="fmtPerdida(fila.d.max)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)" x-text="nota()"></p>
        </div>
    </div>

    {{-- Equipos --}}
    <div class="floating-card p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
            <h2 class="rc-titulo">Equipos de la recaudadora</h2>
            <p class="text-[11px]" style="color: var(--nm-text-muted)">Se revisan cada minuto por ping (o, si lo bloquean, por los puertos 443, 80, 22 o 3389).</p>
        </div>
        @if($r->equipos->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="rc-tabla">
                    <thead><tr><th>Estado</th><th>Tipo</th><th>Nombre</th><th>IP</th><th class="text-right">Latencia</th><th>Desde</th><th>Notas</th>@if($puedeEditarEquipos)<th></th>@endif</tr></thead>
                    <tbody>
                        @foreach($r->equipos as $e)
                            @php
                                $ev = $e->estadoVisible();
                                [$evTexto, $evColor, $evAyuda] = match ($ev) {
                                    'online' => ['Responde', $estados['online']['color'], null],
                                    'offline' => ['Sin respuesta', $estados['offline']['color'], null],
                                    'nunca' => ['Nunca ha respondido', '#94a3b8', 'No ha contestado ni una vez: puede ser IP equivocada, que esté apagado o que su firewall bloquee el ping. No cuenta como caída ni manda avisos hasta que responda.'],
                                    default => [$estados['pendiente']['etiqueta'], $estados['pendiente']['color'], null],
                                };
                            @endphp
                            <tr>
                                <td><span class="flex items-center gap-1.5" @if($evAyuda) title="{{ $evAyuda }}" @endif><span class="rc-punto" style="background: {{ $evColor }}"></span>{{ $evTexto }}</span></td>
                                <td>{{ \App\Models\RecaudadoraEquipo::TIPOS[$e->tipo] ?? $e->tipo }}</td>
                                <td><b>{{ $e->nombre }}</b></td>
                                <td class="font-mono">{{ $e->ip }}</td>
                                <td class="text-right font-mono">{{ $e->latencia_ms !== null ? round($e->latencia_ms, 1) . ' ms' : '—' }}</td>
                                <td>{{ $e->estado_desde?->format('d/m H:i') ?? '—' }}</td>
                                <td class="max-w-[260px] truncate" title="{{ $e->notas }}">{{ $e->notas ?: '—' }}</td>
                                @if($puedeEditarEquipos)
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('recaudadoras.equipos.borrar', [$r->id, $e->id]) }}"
                                              onsubmit="event.preventDefault(); nmConfirmar('¿Quitar {{ addslashes($e->nombre) }} de la recaudadora? Se borra también su historial de caídas.', { titulo: 'Quitar equipo', boton: 'Quitar' }).then(ok => ok && this.submit());">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rc-btn" style="color: #dc2626" aria-label="Quitar {{ $e->nombre }}">Quitar</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs mb-2" style="color: var(--nm-text-muted)">Aún no hay equipos registrados (kioscos, switch, antena, PCs de caja, módem del proveedor…).</p>
        @endif

        @if($puedeEditarEquipos)
            <form method="POST" action="{{ route('recaudadoras.equipos.guardar', $r->id) }}" class="grid grid-cols-1 sm:grid-cols-5 gap-2 mt-3 items-end">
                @csrf
                <label class="text-[11px] font-bold" style="color: var(--nm-text-muted)">Tipo
                    <select name="tipo" class="rc-campo w-full mt-1" required>
                        @foreach(\App\Models\RecaudadoraEquipo::TIPOS as $k => $v)
                            <option value="{{ $k }}" @selected(old('tipo') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[11px] font-bold" style="color: var(--nm-text-muted)">Nombre
                    <input name="nombre" value="{{ old('nombre') }}" required maxlength="120" placeholder="Kiosco 1" class="rc-campo w-full mt-1">
                </label>
                <label class="text-[11px] font-bold" style="color: var(--nm-text-muted)">IP
                    <input name="ip" value="{{ old('ip') }}" required placeholder="10.4.105.200" class="rc-campo w-full mt-1 font-mono">
                </label>
                <label class="text-[11px] font-bold" style="color: var(--nm-text-muted)">Notas (opcional)
                    <input name="notas" value="{{ old('notas') }}" maxlength="250" placeholder="Marca, serie, ubicación…" class="rc-campo w-full mt-1">
                </label>
                <button type="submit" class="rc-btn rc-btn-p">Agregar equipo</button>
            </form>

            {{-- Buscar equipos en la red interna (ping al segmento y unos puertos a los que contestan) --}}
            <div class="mt-4 pt-3" style="border-top: 1px solid var(--nm-border)" x-data="escanerRed()">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="rc-titulo">Buscar equipos en su red</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--nm-text-muted)">
                            @if($segmento)
                                Hace ping a las IPs de <b class="font-mono">{{ $segmento }}</b> y, a las que contestan, prueba unos puertos para adivinar qué son.
                                No aparecen los que bloquean el ping (algunas PCs con firewall).
                            @else
                                No tiene una IP administrativa válida: no se sabe cuál es su red.
                            @endif
                        </p>
                    </div>
                    @if($segmento)
                        <button type="button" class="rc-btn" @click="buscar()" :disabled="cargando"
                                x-text="cargando ? 'Buscando… (hasta 30 s)' : (hecho ? 'Buscar otra vez' : 'Buscar equipos')">Buscar equipos</button>
                    @endif
                </div>
                <p x-show="error" x-cloak x-text="error" class="text-xs mt-2 font-semibold" style="color: #dc2626"></p>
                <div x-show="hecho && !error" x-cloak class="mt-2">
                    <p class="text-xs" style="color: var(--nm-text)" x-text="resumen()"></p>
                    <div class="overflow-x-auto" x-show="equipos.length">
                        <table class="rc-tabla mt-1">
                            <thead><tr><th>IP</th><th>Nombre en la red</th><th>Puertos abiertos</th><th>Tipo</th><th>Nombre para el equipo</th><th></th></tr></thead>
                            <tbody>
                                <template x-for="e in equipos" :key="e.ip">
                                    <tr>
                                        <td class="font-mono" x-text="e.ip"></td>
                                        <td class="max-w-[220px] truncate" :title="e.nombre" x-text="e.nombre || '—'"></td>
                                        <td x-text="puertos(e.puertos)"></td>
                                        <td>
                                            <select class="rc-campo" x-model="e.tipo" :disabled="!!e.registrado" aria-label="Tipo de equipo">
                                                @foreach(\App\Models\RecaudadoraEquipo::TIPOS as $k => $v)
                                                    <option value="{{ $k }}">{{ $v }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input class="rc-campo w-44" x-model="e.alias" maxlength="120" :disabled="!!e.registrado" aria-label="Nombre para el equipo"></td>
                                        <td class="text-right">
                                            <span x-show="e.registrado" class="text-xs" style="color: var(--nm-text-muted)">Ya registrado</span>
                                            <button type="button" x-show="!e.registrado" class="rc-btn rc-btn-p" @click="agregar(e)" :disabled="!e.alias.trim()">Agregar</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- Alta con el mismo formulario de siempre (misma validación y mensaje) --}}
                <form method="POST" action="{{ route('recaudadoras.equipos.guardar', $r->id) }}" x-ref="formAlta" class="hidden">
                    @csrf
                    <input type="hidden" name="tipo" x-ref="altaTipo">
                    <input type="hidden" name="nombre" x-ref="altaNombre">
                    <input type="hidden" name="ip" x-ref="altaIp">
                    <input type="hidden" name="notas" x-ref="altaNotas">
                </form>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Historial --}}
        <div class="floating-card p-4 lg:col-span-2">
            <h2 class="rc-titulo mb-2">Historial de caídas</h2>
            @if($eventos->isNotEmpty())
                <div class="overflow-auto" style="max-height: 420px">
                    <table class="rc-tabla">
                        <thead><tr><th>Qué</th><th>Inicio</th><th>Fin</th><th class="text-right">Duración</th></tr></thead>
                        <tbody>
                            @foreach($eventos as $ev)
                                <tr>
                                    <td>
                                        <span class="flex items-center gap-1.5">
                                            <span class="rc-punto" style="background: {{ $ev->tipo === 'lan' ? '#d97706' : '#dc2626' }}"></span>
                                            {{ $ev->tipo === 'equipo' ? ($ev->equipo?->nombre ?? 'Equipo') . ' sin respuesta' : \App\Models\RecaudadoraEvento::TIPOS[$ev->tipo] }}
                                        </span>
                                    </td>
                                    <td>{{ $ev->inicio->format('d/m/Y H:i') }}</td>
                                    <td>{!! $ev->fin ? e($ev->fin->format('d/m/Y H:i')) : '<b style="color:#dc2626">sigue</b>' !!}</td>
                                    <td class="text-right font-mono">{{ $duracion($ev->duracionSegundos()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs" style="color: var(--nm-text-muted)">Sin caídas registradas desde que se monitorea.</p>
            @endif
        </div>

        {{-- Datos de la oficina (se editan en su propia página: todo vive en la base de datos) --}}
        <div class="floating-card p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="rc-titulo">Datos de la oficina</h2>
                @if($esAdmin)
                    <a href="{{ route('recaudadoras.editar', $r->id) }}" class="rc-btn rc-btn-p">Editar datos</a>
                @endif
            </div>
            <dl>
                <div class="rc-dato"><dt>Tipo</dt><dd>{{ \App\Models\Recaudadora::TIPOS[$r->tipo] ?? $r->tipo }}</dd></div>
                @if($r->numero !== null && $r->numero !== '')<div class="rc-dato"><dt>Número</dt><dd class="font-mono">{{ $r->numero }}</dd></div>@endif
                <div class="rc-dato"><dt>Coordinadora</dt><dd>{{ $r->coordinadora ? (is_numeric($r->coordinadora) ? 'Coordinadora ' . $r->coordinadora : $r->coordinadora) : '—' }}</dd></div>
                <div class="rc-dato"><dt>Municipio</dt><dd>{{ $r->municipio ?: '—' }}</dd></div>
                <div class="rc-dato"><dt>Monitoreo</dt><dd style="color: {{ $r->activo ? '#059669' : '#dc2626' }}">{{ $r->activo ? 'Activo' : 'Sin monitoreo' }}</dd></div>
            </dl>
            @if($r->notas)<p class="text-xs mt-2 whitespace-pre-line" style="color: var(--nm-text)">{{ $r->notas }}</p>@endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Gráficas del historial de ping. Los objetos Chart viven fuera del estado de Alpine (no deben envolverse en Proxy).
    function historialPing() {
        const URL = @json(route('recaudadoras.historial', $r->id));
        const VERDE = '#059669', ROJO = '#dc2626', AMBAR = '#d97706', AZUL = '#2563eb', GRIS = '#64748b';
        let graficaDisp = null, graficaLat = null, graficaPer = null, temporizador = null;
        const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
        const dosDig = (n) => String(n).padStart(2, '0');
        const fecha = (t) => new Date(t);   // 'YYYY-MM-DDTHH:mm' en hora local

        return {
            rango: '24h', datos: null, error: '',

            async cargar() {
                clearTimeout(temporizador);
                try {
                    const res = await fetch(`${URL}?rango=${this.rango}`, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error(`No se pudo cargar el historial (HTTP ${res.status}).`);
                    this.datos = await res.json();
                    this.error = '';
                    this.$nextTick(() => this.dibujar());
                } catch (e) {
                    this.error = e.message;
                }
                // Los periodos cortos se actualizan solos cada minuto
                if (['6h', '24h'].includes(this.rango)) temporizador = setTimeout(() => this.cargar(), 60000);
            },

            etiqueta(t) {
                const d = fecha(t), hm = `${dosDig(d.getHours())}:${dosDig(d.getMinutes())}`;
                return ['6h', '24h'].includes(this.rango) ? hm : `${dosDig(d.getDate())}/${dosDig(d.getMonth() + 1)}` + (this.rango === '90d' ? '' : ` ${hm}`);
            },

            dibujar() {
                if (!this.datos || !this.datos.resumen.muestras || typeof Chart === 'undefined') return;
                const p = this.datos.puntos, etiquetas = p.map(x => this.etiqueta(x.t));
                const texto = css('--nm-text-muted') || '#64748b', rejilla = css('--nm-border') || '#e2e8f0';
                const titulo = (items) => { const d = fecha(p[items[0].dataIndex].t);
                    return `${dosDig(d.getDate())}/${dosDig(d.getMonth() + 1)}/${d.getFullYear()} ${dosDig(d.getHours())}:${dosDig(d.getMinutes())}`; };
                const ejeX = { ticks: { color: texto, maxTicksLimit: 10, maxRotation: 0, autoSkip: true, font: { size: 10.5 } }, grid: { color: rejilla, drawTicks: false } };
                const comun = { responsive: true, maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } }, elements: { point: { radius: 0, hitRadius: 6, hoverRadius: 3 } } };

                // Disponibilidad: verde con respuesta completa, ámbar parcial, rojo sin respuesta; hueco = sin revisiones
                const colorDe = (v) => v === null || v === undefined ? GRIS : v >= 100 ? VERDE : v > 0 ? AMBAR : ROJO;
                graficaDisp?.destroy();
                graficaDisp = new Chart(this.$refs.disp, {
                    type: 'line',
                    data: { labels: etiquetas, datasets: [{ label: 'Respondió', data: p.map(x => x.disp), stepped: 'middle', borderWidth: 2, fill: 'origin',
                        segment: { borderColor: c => colorDe(Math.min(c.p0.parsed.y, c.p1.parsed.y)), backgroundColor: c => colorDe(Math.min(c.p0.parsed.y, c.p1.parsed.y)) + '33' },
                        borderColor: VERDE, backgroundColor: VERDE + '33', spanGaps: false }] },
                    options: { ...comun, scales: { x: ejeX,
                        y: { min: 0, max: 100, ticks: { stepSize: 50, color: texto, font: { size: 10.5 }, callback: v => v + ' %' }, grid: { color: rejilla } } },
                        plugins: { ...comun.plugins, tooltip: { callbacks: { title: titulo,
                            label: c => c.parsed.y === null ? 'Sin revisiones' : (c.parsed.y >= 100 ? 'Respondió todas las revisiones' : `Respondió ${c.parsed.y} % de las revisiones`) } } } },
                });

                // Tiempo de respuesta: promedio de cada IP y, de la administrativa, la franja entre el mínimo y el máximo
                const conLoop = !!this.datos.resumen.loopback, conAdmin = !!this.datos.resumen.admin;
                const serie = (ip, k) => p.map(x => x[ip] ? x[ip][k] : null);
                const ds = [];
                if (conAdmin) {
                    ds.push({ label: 'máx', data: serie('admin', 'max'), borderWidth: 0, pointRadius: 0, fill: false, spanGaps: false });
                    ds.push({ label: 'mín', data: serie('admin', 'min'), borderWidth: 0, pointRadius: 0, fill: '-1', backgroundColor: AZUL + '24', spanGaps: false });
                    ds.push({ label: 'IP administrativa', data: serie('admin', 'avg'), borderColor: AZUL, backgroundColor: AZUL, borderWidth: 2, spanGaps: false });
                }
                if (conLoop) ds.push({ label: 'Loopback', data: serie('loopback', 'avg'), borderColor: GRIS, backgroundColor: GRIS, borderWidth: 1.5, borderDash: [5, 3], spanGaps: false });
                graficaLat?.destroy();
                graficaLat = new Chart(this.$refs.lat, {
                    type: 'line', data: { labels: etiquetas, datasets: ds },
                    options: { ...comun, scales: { x: ejeX, y: { beginAtZero: true, ticks: { color: texto, font: { size: 10.5 }, callback: v => v + ' ms' }, grid: { color: rejilla } } },
                        plugins: { ...comun.plugins,
                            legend: { display: true, position: 'bottom', labels: { color: texto, boxWidth: 14, boxHeight: 2, font: { size: 11 }, filter: i => !['máx', 'mín'].includes(i.text) } },
                            tooltip: { filter: i => i.dataset.label !== 'máx', callbacks: { title: titulo, label: c => {
                                if (c.parsed.y === null) return null;
                                if (c.dataset.label === 'mín') { const x = p[c.dataIndex].admin; return x && x.max !== x.min ? `  rango ${x.min} – ${x.max} ms` : null; }
                                return `${c.dataset.label}: ${c.parsed.y} ms`;
                            } } } } },
                });

                // Pérdida de paquetes: promedio de cada intervalo (0 % = llegaron todos)
                graficaPer?.destroy();
                graficaPer = null;
                const rp = this.datos.resumen.perdida;
                if (rp && this.$refs.per) {
                    const per = (ip) => p.map(x => x.perdida && x.perdida[ip] ? x.perdida[ip].avg : null);
                    const dsP = [];
                    if (rp.admin) dsP.push({ label: 'IP administrativa', ip: 'admin', data: per('admin'), borderColor: AZUL, backgroundColor: AZUL + '1f', fill: 'origin', borderWidth: 2, spanGaps: false });
                    if (rp.loopback) dsP.push({ label: 'Loopback', ip: 'loopback', data: per('loopback'), borderColor: GRIS, backgroundColor: GRIS, borderWidth: 1.5, borderDash: [5, 3], spanGaps: false });
                    graficaPer = new Chart(this.$refs.per, {
                        type: 'line', data: { labels: etiquetas, datasets: dsP },
                        options: { ...comun, scales: { x: ejeX,
                            y: { min: 0, suggestedMax: 20, ticks: { color: texto, font: { size: 10.5 }, callback: v => v + ' %' }, grid: { color: rejilla } } },
                            plugins: { ...comun.plugins,
                                legend: { display: true, position: 'bottom', labels: { color: texto, boxWidth: 14, boxHeight: 2, font: { size: 11 } } },
                                tooltip: { callbacks: { title: titulo, label: c => {
                                    if (c.parsed.y === null) return null;
                                    const x = p[c.dataIndex].perdida[c.dataset.ip];
                                    return `${c.dataset.label}: ${c.parsed.y} %` + (x && x.max > x.avg ? ` (máx. ${x.max} %)` : '');
                                } } } } },
                    });
                }
            },

            filasPerdida() {
                const r = this.datos?.resumen.perdida || {}, f = [];
                if (r.admin) f.push({ nombre: 'IP administrativa', color: AZUL, d: r.admin });
                if (r.loopback) f.push({ nombre: 'Loopback', color: GRIS, d: r.loopback });
                return f;
            },
            fmtPerdida: (v) => v === null || v === undefined ? '—' : `${v} %`,

            filasResumen() {
                const r = this.datos?.resumen || {}, f = [];
                if (r.admin) f.push({ nombre: 'IP administrativa', color: AZUL, d: r.admin });
                if (r.loopback) f.push({ nombre: 'Loopback', color: GRIS, d: r.loopback });
                return f;
            },
            nota() {
                const r = this.datos?.resumen; if (!r) return '';
                const paso = this.datos.intervalo_s >= 3600 ? `${this.datos.intervalo_s / 3600} h` : `${this.datos.intervalo_s / 60} min`;
                return `Cada punto resume ${paso} · ${r.muestras.toLocaleString('es-MX')} revisiones en el periodo. Un hueco en la gráfica es tiempo sin revisiones (monitoreo detenido).`;
            },
            fmtMs: (v) => v === null || v === undefined ? '—' : `${v} ms`,
            fmtPct: (v) => v === null || v === undefined ? '—' : (v >= 99.995 ? '100' : v.toFixed(2)) + ' %',
            colorDisp: (v) => v === null || v === undefined ? GRIS : v >= 99.5 ? VERDE : v >= 97 ? AMBAR : ROJO,
        };
    }
</script>
@if($puedeEditarEquipos)
<script>
    function escanerRed() {
        const TIPOS = @json(\App\Models\RecaudadoraEquipo::TIPOS);
        const PUERTOS = { 22: 'SSH', 23: 'Telnet', 80: 'Web', 135: 'Windows', 443: 'Web segura', 445: 'Archivos Windows',
            554: 'Video', 631: 'Impresión', 3389: 'Escritorio remoto', 8080: 'Web alterna', 9100: 'Impresión' };
        return {
            cargando: false, hecho: false, error: '', red: '', segundos: null, equipos: [],
            async buscar() {
                this.cargando = true;
                this.error = '';
                try {
                    const res = await fetch(@json(route('recaudadoras.escanear', $r->id)), { method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()), 'X-Requested-With': 'XMLHttpRequest' } });
                    const d = await res.json().catch(() => ({}));
                    if (res.status === 429) throw new Error('Se buscó varias veces seguidas: espera un minuto e inténtalo otra vez.');
                    if (!res.ok) throw new Error(d.message || `La búsqueda falló (HTTP ${res.status}).`);
                    this.red = d.red;
                    this.segundos = d.segundos;
                    this.equipos = d.equipos.map(e => ({ ...e, alias: e.registrado || this.sugerirNombre(e) }));
                    this.hecho = true;
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.cargando = false;
                }
            },
            resumen() {
                const nuevos = this.equipos.filter(e => !e.registrado).length;
                if (!this.equipos.length) return `Ningún equipo contestó el ping en ${this.red}.`;
                return `${this.equipos.length} equipo(s) contestaron en ${this.red}` + (this.segundos ? ` (${this.segundos} s)` : '')
                    + (nuevos ? ` · ${nuevos} sin registrar` : ' · todos ya están registrados') + '.';
            },
            puertos(lista) {
                return lista.length ? [...new Set(lista.map(p => PUERTOS[p] || p))].join(', ') : 'Solo ping';
            },
            // Nombre del DNS sin el dominio (RF26CAJA1-720B.secfin.jal.mx → RF26CAJA1-720B) o «Tipo .último octeto»
            sugerirNombre(e) {
                return e.nombre ? e.nombre.split('.')[0].toUpperCase() : `${TIPOS[e.tipo] || 'Equipo'} .${e.ip.split('.').pop()}`;
            },
            agregar(e) {
                this.$refs.altaTipo.value = e.tipo;
                this.$refs.altaNombre.value = e.alias.trim();
                this.$refs.altaIp.value = e.ip;
                this.$refs.altaNotas.value = ('Encontrado al buscar en su red' + (e.nombre ? ` · DNS ${e.nombre}` : '')
                    + (e.puertos.length ? ` · puertos ${e.puertos.join(', ')}` : '')).slice(0, 250);
                this.$refs.formAlta.submit();
            },
        };
    }
</script>
@endif
@endsection
