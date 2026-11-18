@extends('layouts.app')

@section('title', 'Recaudadoras')

@section('styles')
<style>
    [x-cloak] { display: none !important; }
    .rc-kpi { padding: 12px 14px; border-radius: var(--nm-radius-lg); background: var(--nm-surface); border: 1px solid var(--nm-border); text-align: left; transition: border-color .15s; }
    .rc-kpi:hover { border-color: var(--nm-primary); }
    .rc-kpi.activo { border-color: var(--nm-primary); box-shadow: 0 0 0 1px var(--nm-primary) inset; }
    .rc-kpi span { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .rc-kpi b { display: block; font-size: 22px; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 2px; }
    .rc-titulo { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .rc-campo { font-size: 12.5px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); }
    .rc-btn { font-size: 11.5px; font-weight: 700; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-primary); }
    .rc-btn.sel { background: var(--nm-primary); color: #fff; border-color: var(--nm-primary); }
    .rc-tabla { width: 100%; border-collapse: collapse; }
    .rc-tabla th { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); text-align: left; padding: 7px 9px; border-bottom: 1px solid var(--nm-border); white-space: nowrap; position: sticky; top: 0; background: var(--nm-surface); z-index: 1; }
    .rc-tabla td { padding: 6px 9px; border-bottom: 1px solid var(--nm-border); font-size: 12.5px; color: var(--nm-text); white-space: nowrap; }
    .rc-tabla tbody tr { cursor: pointer; }
    .rc-tabla tbody tr:hover td { background: color-mix(in srgb, var(--nm-primary) 6%, transparent); }
    .rc-punto { width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .rc-mapa { width: 100%; height: auto; display: block; }
    .rc-lista-atencion { max-height: 460px; }
    @media (min-width: 1280px) { .rc-lista-atencion { position: absolute; inset: 0; max-height: none; } }
    .rc-mun { fill: color-mix(in srgb, var(--nm-text-muted) 9%, transparent); stroke: color-mix(in srgb, var(--nm-text-muted) 45%, transparent); stroke-linejoin: round; }
    .rc-mun.con { fill: color-mix(in srgb, var(--nm-primary) 9%, transparent); }
    .rc-pin { cursor: pointer; stroke: var(--nm-surface); }
    .rc-pin:hover { stroke: var(--nm-text); }
    /* Recaudadora con kiosco: recuadro índigo alrededor del punto (no es un color de estado) */
    .rc-kiosco { fill: rgb(79 70 229 / 0.10); stroke: #4f46e5; cursor: pointer; }
    .dark .rc-kiosco { fill: rgb(165 180 252 / 0.12); stroke: #a5b4fc; }
    .rc-muestra-kiosco { width: 12px; height: 12px; border-radius: 3px; border: 2px solid #4f46e5; background: rgb(79 70 229 / 0.10); display: inline-block; }
    .dark .rc-muestra-kiosco { border-color: #a5b4fc; background: rgb(165 180 252 / 0.12); }
    .rc-pulso { animation: rcPulso 1.6s ease-out infinite; transform-box: fill-box; transform-origin: center; }
    @keyframes rcPulso { 0% { opacity: .55; transform: scale(1); } 100% { opacity: 0; transform: scale(3.2); } }
    @media (prefers-reduced-motion: reduce) { .rc-pulso { animation: none; opacity: 0; } }
    .rc-tip { position: absolute; pointer-events: none; z-index: 5; padding: 7px 10px; border-radius: 8px; font-size: 12px; background: var(--nm-text); color: var(--nm-surface); max-width: 260px; box-shadow: 0 6px 18px rgb(0 0 0 / .2); }
</style>
@endsection

@section('content')
@php $estados = \App\Models\Recaudadora::ESTADOS; @endphp
<div class="flex flex-col space-y-4 pb-6" x-data="recaudadoras()">
    <script type="application/json" id="recasDatos">{!! json_encode($lista, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18v-7M14 18v-7M18 18v-7M6 18v-7M3 22h18M2 11h20M12 2 2 7v4h20V7z" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Recaudadoras</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">
                    Comunicación de cada oficina por su IP administrativa y su loopback · se revisa cada minuto, caída tras 3 revisiones sin respuesta ·
                    última revisión <span x-text="ultima"></span>
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
        <form method="GET" action="{{ route('recaudadoras.reporte') }}" target="_blank" class="flex items-center gap-2"
              title="PDF con la disponibilidad del mes: totales, por proveedor, por coordinadora, detalle por sede y cada caída">
            <select name="mes" class="rc-campo" aria-label="Mes del reporte">
                @foreach($mesesReporte as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}{{ $loop->first ? ' (en curso)' : '' }}</option>
                @endforeach
            </select>
            <button type="submit" class="rc-btn flex items-center gap-1.5">
                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M9 15h6M9 18h4" /></svg>
                Reporte PDF
            </button>
        </form>
        @if($esAdmin)
            <a href="{{ route('recaudadoras.crear') }}" class="rc-btn flex items-center gap-1.5" title="Dar de alta una recaudadora u otra oficina en el sistema">
                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                Nueva
            </a>
            <form method="POST" action="{{ route('recaudadoras.importar') }}" enctype="multipart/form-data" class="flex items-center gap-2"
                  x-ref="formImportar">
                @csrf
                <label class="rc-btn cursor-pointer flex items-center gap-1.5" title="Carga inicial desde el Excel «Enlaces Telecomunicaciones». Los datos quedan en la base del sistema y se corrigen desde cada ficha. Si se vuelve a importar, solo agrega las que falten y llena campos vacíos: nunca cambia lo que ya está en el sistema.">
                    <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" /></svg>
                    Importar Excel
                    <input type="file" name="archivo" accept=".xlsx" class="hidden" @change="$refs.formImportar.submit()">
                </label>
            </form>
        @endif
        </div>
    </div>

    @include('recaudadoras.partials.mensajes')

    @if($puedeEditarEquipos)
        {{-- Alta en bloque de kioscos y sus terminales de pago (pegando la lista tal cual) --}}
        <details class="floating-card px-5 py-3" @if($errors->has('lista')) open @endif>
            <summary class="cursor-pointer text-[13px] font-bold flex items-center gap-2" style="color: var(--nm-primary)">
                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" /><path d="M9 18h6M9 6h6v6H9z" /></svg>
                Agregar kioscos y terminales de pago
            </summary>
            <form method="POST" action="{{ route('recaudadoras.kioscos') }}" class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-3">
                @csrf
                <textarea name="lista" rows="7" required maxlength="20000" class="rc-campo font-mono lg:col-span-2" style="font-size: 12px"
                          placeholder="Reca 000 1 10.4.6.9 terminal 10.4.6.61&#10;Reca 000 2 10.4.6.56 terminal 10.4.6.62&#10;Reca 094 10.4.14.20 /Sin ip de terminal">{{ old('lista') }}</textarea>
                <div class="text-xs space-y-2" style="color: var(--nm-text-muted)">
                    <p>Una línea por kiosco: <b style="color: var(--nm-text)">Reca</b>, número de recaudadora, número de kiosco (opcional), IP del kiosco y, si tiene, <b style="color: var(--nm-text)">terminal</b> con la IP de su terminal de pago.</p>
                    <p>Cada uno se revisa cada minuto. Los que nunca han respondido aparecen como «Nunca ha respondido» y no mandan avisos hasta que contesten. Si una IP ya estaba registrada, no se duplica.</p>
                    <button type="submit" class="rc-btn sel">Agregar</button>
                </div>
            </form>
        </details>
    @endif

    {{-- Totales: al pulsar uno, filtra la lista y el mapa --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <button type="button" class="rc-kpi" :class="!filtro.estado && 'activo'" @click="filtro.estado = ''">
            <span>Recaudadoras</span><b x-text="lista.filter(r => r.tipo === 'recaudadora').length"></b>
        </button>
        @foreach(['online', 'offline', 'lan', 'pendiente'] as $e)
            <button type="button" class="rc-kpi" :class="filtro.estado === '{{ $e }}' && 'activo'" @click="filtro.estado = filtro.estado === '{{ $e }}' ? '' : '{{ $e }}'">
                <span>{{ $estados[$e]['etiqueta'] }}</span>
                <b style="color: {{ $e === 'pendiente' ? 'var(--nm-text-muted)' : $estados[$e]['color'] }}" x-text="cuenta('{{ $e }}')"></b>
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        {{-- Mapa --}}
        <div class="floating-card p-4 xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <h2 class="rc-titulo">Mapa de Jalisco</h2>
                <div class="flex flex-wrap gap-1">
                    <button type="button" class="rc-btn flex items-center gap-1.5" :class="filtro.kioscos && 'sel'" @click="filtro.kioscos = !filtro.kioscos"
                            :aria-pressed="filtro.kioscos.toString()" title="Mostrar solo las recaudadoras que tienen kiosco (en el mapa y en la tabla)">
                        <span class="rc-muestra-kiosco" aria-hidden="true"></span>
                        Con kioscos <span class="font-mono" x-text="'(' + lista.filter(r => r.kioscos > 0).length + ')'"></span>
                    </button>
                    <button type="button" class="rc-btn" :class="vista === 'jalisco' && 'sel'" @click="vista = 'jalisco'">Jalisco</button>
                    <button type="button" class="rc-btn" :class="vista === 'zmg' && 'sel'" @click="vista = 'zmg'">Zona metropolitana</button>
                </div>
            </div>
            <div class="relative" x-ref="contenedorMapa" @mouseleave="tip = null">
                <p x-show="!mapa.listo" class="text-xs py-16 text-center" style="color: var(--nm-text-muted)" x-text="mapa.error || 'Cargando mapa…'"></p>
                {{-- El SVG se dibuja desde JavaScript (Alpine no puede repetir <template> dentro de un <svg>) --}}
                <svg x-show="mapa.listo" x-ref="mapa" class="rc-mapa" role="img" aria-label="Mapa de Jalisco con el estado de cada recaudadora"
                     x-effect="dibujarPuntos()" @mousemove="sobreMapa($event)" @click="clicMapa($event)">
                    <g x-ref="municipios"></g>
                    <g x-ref="puntos"></g>
                </svg>
                <div class="rc-tip" x-show="tip" x-cloak :style="tip && `left:${tip.x}px; top:${tip.y}px`">
                    <template x-if="tip">
                        <div>
                            <b x-text="tip.r.titulo"></b>
                            <div x-text="tip.r.municipio || ''" class="opacity-80"></div>
                            <div class="mt-1"><span x-text="etiqueta(tip.r.estado)"></span><span x-show="tip.r.hace" x-text="' · desde hace ' + tip.r.hace"></span></div>
                            <div class="opacity-80 font-mono text-[11px]" x-text="tip.r.ip || tip.r.loopback || ''"></div>
                            <div class="mt-1 flex items-center gap-1.5" x-show="tip.r.kioscos > 0">
                                <span class="rc-muestra-kiosco" aria-hidden="true" style="width: 10px; height: 10px"></span>
                                <span x-text="tip.r.kioscos + (tip.r.kioscos === 1 ? ' kiosco' : ' kioscos') + ' · ' + (tip.r.kioscos_ok ? tip.r.kioscos_ok + ' responde' + (tip.r.kioscos_ok === 1 ? '' : 'n') : 'ninguno responde aún')"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[11px]" style="color: var(--nm-text-muted)">
                @foreach($estados as $e => $info)
                    <span class="flex items-center gap-1.5"><span class="rc-punto" style="background: {{ $info['color'] }}"></span>{{ $info['etiqueta'] }}</span>
                @endforeach
                <span class="flex items-center gap-1.5"><span class="rc-muestra-kiosco" aria-hidden="true" style="width: 11px; height: 11px"></span>Con kiosco</span>
                <span>· Punto chico: otra oficina de Hacienda · Varias en un municipio se agrupan alrededor de su cabecera</span>
            </div>
        </div>

        {{-- Lo que hay que atender ahora --}}
        <div class="floating-card p-4 flex flex-col">
            <h2 class="rc-titulo mb-2">Requieren atención</h2>
            <template x-if="!problemas().length && revisadas()">
                <div class="flex-1 flex flex-col items-center justify-center text-center py-10">
                    <span class="rc-punto mb-2" style="background: #059669; width: 14px; height: 14px"></span>
                    <p class="text-sm font-bold" style="color: var(--nm-text)">Todas con comunicación</p>
                    <p class="text-xs" style="color: var(--nm-text-muted)">Ninguna recaudadora caída en la última revisión.</p>
                </div>
            </template>
            <template x-if="!revisadas()">
                <div class="flex-1 flex flex-col items-center justify-center text-center py-10">
                    <span class="rc-punto mb-2" style="background: #64748b; width: 14px; height: 14px"></span>
                    <p class="text-sm font-bold" style="color: var(--nm-text)">Sin revisión reciente</p>
                    <p class="text-xs max-w-xs" style="color: var(--nm-text-muted)">No hay revisiones de los últimos 5 minutos: el temporizador nm-recaudadoras.timer no está corriendo (en el servidor arranca solo con actualizar.sh).</p>
                </div>
            </template>
            {{-- Junto al mapa la lista toma justo el alto que queda en la tarjeta (no la estira): termina con el mapa --}}
            <div class="relative flex-1 min-h-0" x-show="problemas().length">
            <div class="rc-lista-atencion custom-scrollbar space-y-1.5 overflow-y-auto pr-1.5">
                <template x-for="r in problemas()" :key="r.id">
                    <a :href="url(r)" class="flex items-start gap-2.5 p-2 rounded-lg border hover:opacity-80" style="border-color: var(--nm-border)">
                        <span class="rc-punto mt-1" :style="`background:${color(r.estado)}`"></span>
                        <span class="min-w-0 flex-1">
                            <b class="block text-[13px] truncate" style="color: var(--nm-text)" x-text="r.titulo"></b>
                            <span class="block text-[11.5px]" style="color: var(--nm-text-muted)">
                                <span x-text="etiqueta(r.estado)"></span><span x-show="r.hace" x-text="' · ' + r.hace"></span><span x-show="r.municipio" x-text="' · ' + r.municipio"></span>
                            </span>
                            <span class="block text-[11px] font-mono" style="color: var(--nm-text-muted)" x-text="[r.ip, r.enlace].filter(Boolean).join(' · ')"></span>
                        </span>
                    </a>
                </template>
            </div>
            </div>
        </div>
    </div>

    {{-- Lista --}}
    <div class="floating-card p-4">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h2 class="rc-titulo mr-2">Todas</h2>
            <input type="search" x-model="filtro.texto" placeholder="Buscar número, nombre, municipio o IP…" class="rc-campo w-64">
            <select x-model="filtro.coord" class="rc-campo">
                <option value="">Todas las coordinadoras</option>
                @foreach($coordinadoras as $c)
                    <option value="{{ $c }}">{{ is_numeric($c) ? 'Coordinadora ' . $c : $c }}</option>
                @endforeach
            </select>
            <select x-model="filtro.tipo" class="rc-campo">
                <option value="">Recaudadoras y oficinas</option>
                <option value="recaudadora">Solo recaudadoras</option>
                <option value="oficina">Solo otras oficinas</option>
            </select>
            <select x-model="filtro.estado" class="rc-campo">
                <option value="">Cualquier estado</option>
                @foreach($estados as $e => $info)
                    <option value="{{ $e }}">{{ $info['etiqueta'] }}</option>
                @endforeach
            </select>
            <span class="text-xs ml-auto" style="color: var(--nm-text-muted)" x-text="filtradas().length + ' de ' + lista.length"></span>
        </div>
        <div class="overflow-auto" style="max-height: 640px">
            <table class="rc-tabla">
                <thead>
                    <tr>
                        <th>Estado</th><th>Núm.</th><th>Recaudadora</th><th>Coord.</th><th>Municipio</th><th>Enlace</th>
                        <th>IP administrativa</th><th class="text-right">Latencia</th><th class="text-right">Disp. 24 h</th><th class="text-right">Disp. 30 d</th><th class="text-right">Equipos</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="r in filtradas()" :key="r.id">
                        <tr @click="abrir(r)">
                            <td>
                                <span class="flex items-center gap-1.5">
                                    <span class="rc-punto" :style="`background:${color(r.estado)}`"></span>
                                    <span x-text="etiqueta(r.estado)"></span>
                                    <span x-show="r.hace" class="text-[11px]" style="color: var(--nm-text-muted)" x-text="r.hace"></span>
                                    <span x-show="r.porLoopback" class="text-[10.5px] px-1.5 rounded" style="background: color-mix(in srgb, var(--nm-text-muted) 15%, transparent)" title="Su IP administrativa no responde ping; se monitorea por su loopback">por loopback</span>
                                </span>
                            </td>
                            <td class="font-mono" x-text="r.numero || '—'"></td>
                            <td><b x-text="r.nombre"></b> <span x-show="r.tipo === 'oficina'" class="text-[10.5px]" style="color: var(--nm-text-muted)">oficina</span> <span x-show="!r.activo" class="text-[10.5px]" style="color: #dc2626">sin monitoreo</span></td>
                            <td x-text="r.coordinadora || '—'"></td>
                            <td x-text="r.municipio || '—'"></td>
                            <td class="max-w-[220px] truncate" x-text="r.enlace || '—'" :title="r.enlace"></td>
                            <td class="font-mono" x-text="r.ip || '—'"></td>
                            <td class="text-right font-mono" x-text="r.ms !== null ? r.ms + ' ms' : '—'"></td>
                            <td class="text-right font-mono" :style="dispColor(r.disp24)" x-text="disp(r.disp24)"></td>
                            <td class="text-right font-mono" :style="dispColor(r.disp30)" x-text="disp(r.disp30)"></td>
                            <td class="text-right" x-text="r.equipos || '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function recaudadoras() {
        const ESTADOS = @json($estados);
        const URL_FICHA = @json(route('recaudadoras.show', 0)).replace(/0$/, '');
        const URL_ESTADOS = @json(route('recaudadoras.estados'));
        const URL_MAPA = @json(asset('data/jalisco-municipios.json'));
        const PRIORIDAD = { offline: 0, lan: 1, pendiente: 2, sin_ip: 3, online: 4 };
        const esc = (t) => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

        return {
            lista: JSON.parse(document.getElementById('recasDatos').textContent),
            filtro: { texto: '', estado: '', coord: '', tipo: '', kioscos: false },
            vista: 'jalisco',
            mapa: { listo: false, error: '', cabeceras: {}, cajas: {} },
            tip: null,
            ultima: @json($ultimoChequeo ? \Carbon\Carbon::parse($ultimoChequeo)->format('H:i') : 'nunca'),

            init() {
                this.cargarMapa();
                setInterval(() => this.refrescar(), 30000);
            },

            async cargarMapa() {
                try {
                    const datos = await (await fetch(URL_MAPA)).json();
                    const todos = datos.municipios.flatMap(m => m.anillos.flat());
                    const lat0 = (Math.min(...todos.map(p => p[1])) + Math.max(...todos.map(p => p[1]))) / 2;
                    const k = Math.cos(lat0 * Math.PI / 180);
                    // Proyección simple (equirectangular corregida por latitud): suficiente para un estado
                    const proy = ([lon, lat]) => [lon * k * 100, -lat * 100];
                    const caja = (puntos) => {
                        const xs = puntos.map(p => proy(p)[0]), ys = puntos.map(p => proy(p)[1]);
                        const [x0, x1, y0, y1] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)];
                        const m = Math.max(x1 - x0, y1 - y0) * 0.03;
                        return [x0 - m, y0 - m, x1 - x0 + 2 * m, y1 - y0 + 2 * m];
                    };
                    datos.municipios.forEach(m => { this.mapa.cabeceras[m.cve] = proy([m.cabecera.lon, m.cabecera.lat]); });
                    this.mapa.cajas = {
                        jalisco: caja(todos),
                        zmg: caja(datos.municipios.filter(m => m.zmg).flatMap(m => m.anillos.flat())),
                    };
                    this.agrupar();

                    // Municipios: se dibujan una sola vez (los que tienen recaudadora, con otro tono)
                    const conReca = new Set(this.lista.map(r => r.cve).filter(Boolean));
                    this.$refs.municipios.innerHTML = datos.municipios.map(m =>
                        `<path class="rc-mun${conReca.has(m.cve) ? ' con' : ''}" vector-effect="non-scaling-stroke" stroke-width=".6" d="${
                            m.anillos.map(a => 'M' + a.map(p => proy(p).map(v => v.toFixed(2)).join(',')).join('L') + 'Z').join('')
                        }"><title>${esc(m.nombre)}</title></path>`).join('');
                    this.mapa.listo = true;
                } catch (e) {
                    this.mapa.error = 'No se pudo cargar el mapa de Jalisco.';
                    console.warn('No se pudo cargar el mapa de Jalisco', e);
                }
            },

            // Varias recaudadoras en un municipio: alrededor de la cabecera, en espiral
            agrupar() {
                const grupos = {};
                this.lista.forEach(r => { if (r.cve) (grupos[r.cve] ??= []).push(r); });
                Object.values(grupos).forEach(g => g.forEach((r, i) => {
                    const ang = i * 2.4, dist = i === 0 ? 0 : 1.15 * Math.sqrt(i);
                    r.ox = Math.cos(ang) * dist; r.oy = Math.sin(ang) * dist;
                }));
            },

            caja() { return this.mapa.cajas[this.vista] || [0, 0, 100, 100]; },
            radio() { return this.caja()[2] / (this.vista === 'zmg' ? 95 : 150); },

            // Se vuelve a dibujar solo (x-effect) al cambiar la vista, los filtros o el estado de alguna
            dibujarPuntos() {
                const filas = this.filtradas();   // se lee antes del return: así x-effect sigue los filtros desde el inicio
                const vista = this.vista;
                if (!this.mapa.listo) return;
                const svg = this.$refs.mapa, caja = this.caja(), rad = this.radio();
                svg.setAttribute('viewBox', caja.join(' '));
                svg.dataset.vista = vista;
                // Las caídas al final: quedan encima de las demás
                const puntos = filas.filter(r => r.cve && this.mapa.cabeceras[r.cve])
                    .sort((a, b) => PRIORIDAD[b.estado] - PRIORIDAD[a.estado]);
                this.$refs.puntos.innerHTML = puntos.map(r => {
                    const [bx, by] = this.mapa.cabeceras[r.cve];
                    const x = (bx + (r.ox || 0) * rad * 2.3).toFixed(2), y = (by + (r.oy || 0) * rad * 2.3).toFixed(2);
                    const c = this.color(r.estado), radio = (r.tipo === 'oficina' ? rad * .75 : rad).toFixed(3);
                    // Con kiosco: recuadro alrededor del punto (forma, no color: el color sigue siendo el estado de la sede)
                    const k = r.kioscos > 0 ? rad * 1.75 : 0;
                    return `<g data-id="${r.id}">`
                        + (k ? `<rect class="rc-kiosco" x="${(x - k).toFixed(2)}" y="${(y - k).toFixed(2)}" width="${(k * 2).toFixed(2)}" height="${(k * 2).toFixed(2)}" rx="${(rad * .45).toFixed(2)}" stroke-width="${(rad / 2.6).toFixed(3)}"/>` : '')
                        + (r.estado === 'offline' ? `<circle class="rc-pulso" cx="${x}" cy="${y}" r="${radio}" fill="${c}"/>` : '')
                        + `<circle class="rc-pin" cx="${x}" cy="${y}" r="${radio}" fill="${c}" stroke-width="${(rad / 3).toFixed(3)}"/></g>`;
                }).join('');
            },

            puntoDe(ev) {
                const g = ev.target.closest('[data-id]');
                return g ? this.lista.find(r => r.id === +g.dataset.id) : null;
            },
            sobreMapa(ev) {
                const r = this.puntoDe(ev);
                if (!r) { this.tip = null; return; }
                const caja = this.$refs.contenedorMapa.getBoundingClientRect();
                this.tip = { r, x: Math.max(0, Math.min(ev.clientX - caja.left + 14, caja.width - 270)), y: ev.clientY - caja.top + 14 };
            },
            clicMapa(ev) {
                const r = this.puntoDe(ev);
                if (r) this.abrir(r);
            },

            filtradas() {
                const t = this.filtro.texto.trim().toLowerCase();
                return this.lista.filter(r =>
                    (!this.filtro.estado || r.estado === this.filtro.estado)
                    && (!this.filtro.coord || r.coordinadora === this.filtro.coord)
                    && (!this.filtro.tipo || r.tipo === this.filtro.tipo)
                    && (!this.filtro.kioscos || r.kioscos > 0)
                    && (!t || [r.numero, r.nombre, r.municipio, r.ip, r.loopback, r.enlace].some(v => (v || '').toLowerCase().includes(t))));
            },
            problemas() {
                return this.lista.filter(r => r.activo && (r.estado === 'offline' || r.estado === 'lan'))
                    .sort((a, b) => PRIORIDAD[a.estado] - PRIORIDAD[b.estado] || (a.desde || '').localeCompare(b.desde || ''));
            },
            cuenta(estado) { return this.lista.filter(r => r.estado === estado).length; },
            // ¿Hay revisiones recientes? Si todas están «pendiente», el temporizador no corre
            revisadas() { return this.lista.some(r => ['online', 'offline', 'lan'].includes(r.estado)); },

            color(e) { return (ESTADOS[e] || ESTADOS.pendiente).color; },
            etiqueta(e) { return (ESTADOS[e] || ESTADOS.pendiente).etiqueta; },
            disp(v) { return v === null || v === undefined ? '—' : (v >= 99.995 ? '100' : v.toFixed(2)) + ' %'; },
            dispColor(v) { return v === null || v === undefined ? '' : `color:${v >= 99.5 ? '#059669' : v >= 97 ? '#d97706' : '#dc2626'}`; },
            url(r) { return URL_FICHA + r.id; },
            abrir(r) { location.href = this.url(r); },

            async refrescar() {
                try {
                    const r = await fetch(URL_ESTADOS, { headers: { Accept: 'application/json' } });
                    if (!r.ok) return;
                    const porId = Object.fromEntries((await r.json()).map(x => [x.id, x]));
                    this.lista.forEach(f => {
                        const n = porId[f.id];
                        if (n) Object.assign(f, { estado: n.estado, ms: n.ms, desde: n.desde, hace: n.hace, porLoopback: n.porLoopback, equipos: n.equipos,
                            kioscos: n.kioscos, kioscos_ok: n.kioscos_ok });
                    });
                    this.ultima = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
                } catch (e) { /* sin conexión: se reintenta en 30 s */ }
            },
        };
    }
</script>
@endsection
