@extends('layouts.app')

@section('title', 'Cámaras')

@section('styles')
<style>
    [x-cloak] { display: none !important; }
    .cm-kpi { padding: 12px 14px; border-radius: var(--nm-radius-lg); background: var(--nm-surface); border: 1px solid var(--nm-border); }
    .cm-kpi span { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .cm-kpi b { display: block; font-size: 22px; font-family: ui-monospace, Consolas, monospace; color: var(--nm-text); margin-top: 2px; }
    .cm-titulo { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .cm-campo { font-size: 12.5px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); }
    .cm-tabla { width: 100%; border-collapse: collapse; }
    .cm-tabla th { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); text-align: left; padding: 7px 9px; border-bottom: 1px solid var(--nm-border); white-space: nowrap; position: sticky; top: 0; background: var(--nm-surface); }
    .cm-tabla td { padding: 6px 9px; border-bottom: 1px solid var(--nm-border); font-size: 12.5px; color: var(--nm-text); white-space: nowrap; }
    .cm-btn { font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-primary); }
    .cm-btn:disabled { opacity: .4; cursor: not-allowed; }
    .cm-btn-ver { color: #fff; background: var(--nm-primary); border-color: var(--nm-primary); }
    /* Monitor: fondo negro como una pantalla, proporción 16:9 */
    .cm-pantalla { position: relative; aspect-ratio: 16 / 9; background: #05070a; border-radius: 10px; overflow: hidden; border: 1px solid #2a3039; }
    .cm-pantalla video-stream, .cm-pantalla video { width: 100%; height: 100%; display: block; }
    .cm-pantalla video { object-fit: contain; background: #05070a; }
    /* El letrero de estado del reproductor de go2rtc (loading / MSE / error) va abajo: arriba están los botones */
    .cm-pantalla video-stream .info { top: auto; bottom: 46px; right: 8px; font-size: 11px; opacity: .8; pointer-events: none; }
    .cm-barra { position: absolute; left: 0; right: 0; top: 0; display: flex; align-items: center; gap: 6px; padding: 6px 8px;
        background: linear-gradient(180deg, rgb(0 0 0 / .75), transparent); color: #e6ebf1; font-size: 12px; z-index: 2; }
    .cm-barra button { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: rgb(255 255 255 / .14); color: #fff; }
    .cm-barra button:hover { background: rgb(255 255 255 / .28); }
    .cm-vacia { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; padding: 12px; color: #6b7480; font-size: 12.5px; }
    .cm-vivo { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 6px #ef4444; animation: cmVivo 1.4s ease-in-out infinite; }
    @keyframes cmVivo { 50% { opacity: .35; } }
    @media (prefers-reduced-motion: reduce) { .cm-vivo { animation: none; } }
</style>
@endsection

@section('content')
@php
    $marca = fn ($f) => match (true) {
        (bool) preg_match('/ezviz/i', $f) => 'Ezviz (Hikvision)', (bool) preg_match('/hikvision/i', $f) => 'Hikvision',
        (bool) preg_match('/dahua/i', $f) => 'Dahua', (bool) preg_match('/axis/i', $f) => 'Axis',
        (bool) preg_match('/uniview/i', $f) => 'Uniview', (bool) preg_match('/hanwha|samsung techwin/i', $f) => 'Hanwha',
        default => \Illuminate\Support\Str::of($f)->before(',')->limit(24)->toString(),
    };
    $ultima = $ultimaBusqueda ? \Carbon\Carbon::parse($ultimaBusqueda) : null;
    // Datos de la lista para Alpine (sin credenciales: solo si están configuradas)
    $lista = $camaras->map(fn ($c) => [
        'id' => $c->id, 'ip' => $c->ip, 'mac' => $c->mac, 'nombre' => $c->nombre, 'marca' => $marca($c->fabricante),
        'fabricante' => $c->fabricante, 'visto' => \Illuminate\Support\Str::before((string) $c->visto_en, '.'),
        'rtsp' => (bool) $c->rtsp, 'web' => $c->https ? "https://{$c->ip}" : ($c->http ? "http://{$c->ip}" : null),
        'usuario' => $c->usuario, 'tienePassword' => (bool) $c->tiene_password, 'ruta' => $c->ruta_rtsp,
        'marcaConocida' => (bool) \App\Services\VideoCamaras::marca($c->fabricante),
        'lista' => $c->usuario && $c->tiene_password && ($c->ruta_rtsp || \App\Services\VideoCamaras::marca($c->fabricante)),
        'vieja' => $ultima && \Carbon\Carbon::parse($c->ultima_vez)->lt($ultima->copy()->subMinutes(30)),
        'ultima' => \Carbon\Carbon::parse($c->ultima_vez)->format('d/m/Y H:i'),
    ] + \App\Http\Controllers\CamarasController::estado($c))->values();
    $configuradas = $lista->where('lista', true)->count();
@endphp
<div class="flex flex-col space-y-4 pb-6" x-data="camaras()">
    <script type="application/json" id="camarasDatos">{!! json_encode($lista, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center" style="background: var(--nm-metal-sheen), var(--nm-metal-azure)">
                <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16.75 12h3.63a1 1 0 0 1 .96 1.28l-1.17 4.1a1 1 0 0 1-.96.72H17M2 8.55V5a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v6.58a1 1 0 0 1-.4.8l-3.2 2.4a1 1 0 0 1-.6.2H3a1 1 0 0 1-1-1zM7 15v3a2 2 0 0 1-2 2H2" /></svg>
            </div>
            <div>
                <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Cámaras de seguridad</h1>
                <p class="text-xs" style="color: var(--nm-text-muted)">Red 10.4.x.x · encontradas en las tablas ARP de los switches y routers por el fabricante de su tarjeta de red · última búsqueda {{ $ultima ? $ultima->format('d/m/Y H:i') : 'nunca' }} (se repite cada noche)</p>
            </div>
        </div>
        @if($puedeVer)
            <button type="button" @click="buscar()" :disabled="buscando" class="cm-btn flex items-center gap-1.5" style="padding: 7px 12px"
                    title="Busca cámaras en las tablas ARP de los switches y routers (unos 30 s)">
                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
                <span x-text="buscando ? 'Buscando… (unos 30 s)' : 'Buscar cámaras'"></span>
            </button>
        @endif
    </div>

    {{-- Totales (los de estado se recalculan con cada actualización de la lista) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="cm-kpi"><span>Cámaras</span><b>{{ $camaras->count() }}</b></div>
        {{-- El color va en style: la regla .cm-kpi b fija el color del número y le gana a las clases de color --}}
        <div class="cm-kpi"><span>En línea</span><b style="color: #059669" x-text="lista.filter(c => c.vigente && c.estado === 'online').length"></b></div>
        <div class="cm-kpi"><span>Caídas</span><b :style="lista.some(c => c.vigente && c.estado === 'offline') && 'color: #dc2626'" x-text="lista.filter(c => c.vigente && c.estado === 'offline').length"></b></div>
        <div class="cm-kpi"><span>Con acceso configurado</span><b>{{ $configuradas }}</b></div>
    </div>

    {{-- Visor: una cámara en vivo --}}
    @if($puedeVer)
        <div class="floating-card p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <h2 class="cm-titulo">Visor en vivo</h2>
                <p class="text-[11px]" style="color: var(--nm-text-muted)">Pulsa «Ver» en una cámara de la lista. Se muestra en calidad ligera; «HD» la pasa a resolución completa.</p>
            </div>
            <div class="max-w-4xl mx-auto">
                <template x-for="(p, i) in pantallas" :key="i">
                    <div class="cm-pantalla" :id="'pantalla' + i">
                        <template x-if="p">
                            <div class="cm-barra">
                                <span class="cm-vivo"></span>
                                <b class="truncate" x-text="p.nombre"></b>
                                <span class="opacity-70 font-mono" x-text="p.ip"></span>
                                <span class="ml-auto flex gap-1">
                                    <button type="button" @click="calidad(i)" x-text="p.calidad === 'main' ? 'Ligera' : 'HD'"></button>
                                    <button type="button" @click="completa(i)">Pantalla completa</button>
                                    <button type="button" @click="cerrar(i)" aria-label="Cerrar">✕</button>
                                </span>
                            </div>
                        </template>
                        <div class="cm-vacia" x-show="!p || p.error || p.cargando">
                            <span x-text="!p ? 'Elige una cámara de la lista y pulsa «Ver»' : (p.error || 'Conectando con la cámara…')"></span>
                        </div>
                        {{-- Aquí se inserta el reproductor (fuera de Alpine: el reproductor maneja su propio video) --}}
                        <div class="w-full h-full" :data-pantalla="i"></div>
                    </div>
                </template>
            </div>
        </div>
    @endif

    {{-- Lista --}}
    <div class="floating-card p-5">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h2 class="cm-titulo mr-2">Cámaras · <span x-text="filas.length"></span> de {{ $camaras->count() }}</h2>
            <input type="search" x-model="q" placeholder="Buscar nombre, IP, MAC, switch…" class="cm-campo w-64">
            <select x-model="fMarca" class="cm-campo" aria-label="Marca">
                <option value="">Todas las marcas</option>
                @foreach($lista->pluck('marca')->unique()->sort() as $m)<option>{{ $m }}</option>@endforeach
            </select>
            <select x-model="fVisto" class="cm-campo" aria-label="Vista en">
                <option value="">Todos los switches</option>
                @foreach($lista->pluck('visto')->unique()->sort() as $v)<option>{{ $v }}</option>@endforeach
            </select>
            <select x-model="fEstado" class="cm-campo" aria-label="Estado">
                <option value="">Todos los estados</option>
                <option value="online">En línea</option>
                <option value="offline">Caídas</option>
            </select>
            <label class="text-xs flex items-center gap-1.5" style="color: var(--nm-text)"><input type="checkbox" x-model="soloListas"> Solo con acceso configurado</label>
        </div>
        @if($camaras->isEmpty())
            <p class="text-sm py-6 text-center" style="color: var(--nm-text-muted)">Todavía no se han buscado cámaras. Pulsa «Buscar cámaras».</p>
        @else
            <div class="max-h-[520px] overflow-auto">
                <table class="cm-tabla">
                    <thead><tr><th>Estado</th><th>Cámara</th><th>IP</th><th>Marca</th><th>Vista en</th><th>Video</th><th>Acceso</th><th>En tabla ARP</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="c in filas" :key="c.id">
                            <tr>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 font-bold"
                                          :class="!c.vigente || !c.estado ? 'opacity-60' : (c.estado === 'online' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400')"
                                          :title="c.estado && c.desde ? (c.estado === 'online' ? 'En línea' : 'Caída') + ' desde ' + c.desde + ' (' + c.hace + ')' : ''">
                                        <span class="w-2 h-2 rounded-full" :class="!c.vigente || !c.estado ? 'bg-gray-400' : (c.estado === 'online' ? 'bg-emerald-500' : 'bg-red-500 animate-pulse')"></span>
                                        <span x-text="!c.estado ? 'Sin revisar' : (!c.vigente ? 'Sin revisión reciente' : (c.estado === 'online' ? 'En línea' + (c.ms !== null ? ' · ' + c.ms + ' ms' : '') : 'Caída desde ' + c.desde))"></span>
                                    </span>
                                </td>
                                <td class="font-semibold" x-text="c.nombre || '—'"></td>
                                <td class="font-mono" x-text="c.ip"></td>
                                <td :title="c.fabricante" x-text="c.marca"></td>
                                <td x-text="c.visto"></td>
                                <td><span x-show="c.rtsp" class="text-emerald-600 dark:text-emerald-400 font-bold">✓ RTSP</span><span x-show="!c.rtsp" class="opacity-50">—</span></td>
                                <td>
                                    <span x-show="c.lista" class="text-emerald-600 dark:text-emerald-400 font-bold">✓ Configurado</span>
                                    <span x-show="!c.lista" class="opacity-60">Sin configurar</span>
                                </td>
                                <td class="font-mono text-xs" :class="c.vieja && 'text-amber-600 dark:text-amber-400 font-bold'" x-text="c.ultima"
                                    :title="c.vieja ? 'No apareció en la última búsqueda: apagada, desconectada o cambió de IP' : ''"></td>
                                <td class="text-right">
                                    <span class="inline-flex gap-1.5">
                                        @if($puedeVer)
                                            <button type="button" class="cm-btn cm-btn-ver" @click="ver(c)" :disabled="!c.lista || !c.rtsp"
                                                    :title="!c.rtsp ? 'No tiene abierto el puerto de video (554)' : (!c.lista ? 'Configura primero su usuario y contraseña' : 'Ver en vivo')">Ver</button>
                                            <button type="button" class="cm-btn" @click="editar(c)">Acceso</button>
                                        @endif
                                        <a x-show="c.web" :href="c.web" target="_blank" rel="noopener noreferrer" class="cm-btn" title="Página web de la cámara">Web ↗</a>
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!filas.length" class="text-xs py-4 text-center" style="color: var(--nm-text-muted)">Ninguna cámara coincide con los filtros.</p>
            </div>
        @endif
    </div>

    {{-- Acceso de una cámara: nombre, usuario, contraseña (cifrada) y ruta RTSP opcional --}}
    @if($puedeVer)
        <div x-show="edicion" x-cloak style="display: none" @click.self="edicion = null" @keydown.escape.window="edicion = null"
             class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="tituloAcceso">
            <form class="floating-card w-full max-w-md p-6 space-y-3" @submit.prevent="guardar()">
                <h3 id="tituloAcceso" class="text-base font-bold" style="color: var(--nm-text)">Acceso a la cámara <span class="font-mono" x-text="edicion?.ip"></span></h3>
                <p class="text-xs" style="color: var(--nm-text-muted)">La contraseña se guarda cifrada y nunca se vuelve a mostrar. El monitor la usa solo para pedir el video de la cámara.</p>
                <label class="block text-xs font-semibold" style="color: var(--nm-text)">Nombre <span class="font-normal opacity-60">(para reconocerla, p. ej. «Acceso principal»)</span>
                    <input x-model="form.nombre" maxlength="120" class="cm-campo w-full mt-1">
                </label>
                <label class="block text-xs font-semibold" style="color: var(--nm-text)">Usuario
                    <input x-model="form.usuario" maxlength="120" autocomplete="off" class="cm-campo w-full mt-1">
                </label>
                <label class="block text-xs font-semibold" style="color: var(--nm-text)">Contraseña
                    <input x-model="form.password" type="password" maxlength="200" autocomplete="new-password" class="cm-campo w-full mt-1"
                           :placeholder="edicion?.tienePassword ? 'Guardada · déjala vacía para conservarla' : ''">
                </label>
                <label x-show="edicion?.tienePassword" class="text-xs flex items-center gap-1.5" style="color: var(--nm-text)"><input type="checkbox" x-model="form.borrar_password"> Borrar la contraseña guardada</label>
                <label class="block text-xs font-semibold" style="color: var(--nm-text)">Ruta RTSP <span class="font-normal opacity-60" x-text="edicion?.marcaConocida ? '(opcional: se usa la estándar de ' + edicion.marca + ')' : '(necesaria: marca sin ruta estándar)'"></span>
                    <input x-model="form.ruta_rtsp" maxlength="250" class="cm-campo w-full mt-1 font-mono" placeholder="/Streaming/Channels/102">
                </label>
                <p x-show="error" x-text="error" class="text-xs font-semibold text-red-600 dark:text-red-400"></p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="cm-btn" @click="edicion = null">Cancelar</button>
                    <button type="submit" class="cm-btn cm-btn-ver" :disabled="guardando" x-text="guardando ? 'Guardando…' : 'Guardar'"></button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@section('scripts')
@if($puedeVer)
    {{-- Reproductor de go2rtc (componente <video-stream>, video por MSE sobre WebSocket) --}}
    <script type="module" src="{{ $go2rtc }}/video-stream.js"></script>
@endif
<script>
    function camaras() {
        const BASE_VIDEO = @json($go2rtc);
        const pedir = async (metodo, url, cuerpo) => {
            const r = await fetch(url, { method: metodo, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()), 'X-Requested-With': 'XMLHttpRequest' }, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
            const j = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(Object.values(j.errors || {})[0]?.[0] || j.message || `HTTP ${r.status}`);
            return j;
        };
        return {
            lista: JSON.parse(document.getElementById('camarasDatos').textContent),
            q: '', fMarca: '', fVisto: '', fEstado: '', soloListas: false,
            pantallas: [null],   // un solo recuadro: «Ver» en otra cámara la cambia ahí
            init() {
                // El estado de las cámaras se revisa cada minuto en el servidor: aquí se vuelve a pedir cada 30 s
                setInterval(async () => {
                    if (document.hidden) return;
                    try {
                        const estados = await pedir('GET', @json(route('camaras.estados')));
                        const porId = Object.fromEntries(estados.map(e => [e.id, e]));
                        this.lista.forEach(c => porId[c.id] && Object.assign(c, porId[c.id]));
                    } catch (e) { /* sin red o sesión vencida: se reintenta en la siguiente vuelta */ }
                }, 30000);
            },
            edicion: null, form: {}, error: '', guardando: false, buscando: false,
            get filas() {
                const q = this.q.toLowerCase().trim();
                return this.lista.filter(c => (!this.fMarca || c.marca === this.fMarca) && (!this.fVisto || c.visto === this.fVisto)
                    && (!this.fEstado || (c.vigente && c.estado === this.fEstado)) && (!this.soloListas || c.lista)
                    && (!q || [c.nombre, c.ip, c.mac, c.visto, c.fabricante].join(' ').toLowerCase().includes(q)));
            },
            // La cámara se muestra en el recuadro (si ya había otra, la reemplaza)
            ver(c) {
                if (this.pantallas[0]?.id === c.id) return;
                this.abrir(0, c, 'sub');
                document.getElementById('pantalla0')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            },
            async abrir(i, c, calidad) {
                this.detener(i);
                this.pantallas[i] = { id: c.id, ip: c.ip, nombre: c.nombre || c.ip, calidad, cargando: true, error: '' };
                try {
                    const { stream } = await pedir('GET', `{{ url('/api/camaras') }}/${c.id}/video?calidad=${calidad}`);
                    if (this.pantallas[i]?.id !== c.id) return;
                    const lienzo = document.querySelector(`[data-pantalla="${i}"]`);
                    const v = document.createElement('video-stream');
                    v.mode = 'mse';
                    v.background = true;
                    v.src = new URL(`${BASE_VIDEO}/api/ws?src=${encodeURIComponent(stream)}`, location.href).toString();
                    lienzo.replaceChildren(v);
                    this.pantallas[i].cargando = false;
                } catch (e) {
                    if (this.pantallas[i]) { this.pantallas[i].cargando = false; this.pantallas[i].error = 'No se pudo abrir: ' + e.message; }
                }
            },
            calidad(i) {
                const p = this.pantallas[i];
                const c = this.lista.find(x => x.id === p.id);
                this.abrir(i, c, p.calidad === 'main' ? 'sub' : 'main');
            },
            completa(i) { document.getElementById('pantalla' + i)?.requestFullscreen?.(); },
            detener(i) { document.querySelector(`[data-pantalla="${i}"]`)?.replaceChildren(); },
            cerrar(i) { this.detener(i); this.pantallas[i] = null; },
            editar(c) {
                this.edicion = c; this.error = '';
                this.form = { nombre: c.nombre || '', usuario: c.usuario || '', password: '', borrar_password: false, ruta_rtsp: c.ruta || '' };
            },
            async guardar() {
                this.guardando = true; this.error = '';
                try {
                    await pedir('PUT', `{{ url('/api/camaras') }}/${this.edicion.id}/acceso`, this.form);
                    location.reload();
                } catch (e) { this.error = e.message; this.guardando = false; }
            },
            async buscar() {
                this.buscando = true;
                try { await pedir('POST', @json(route('camaras.buscar'))); location.reload(); }
                catch (e) { alert('La búsqueda falló: ' + e.message); this.buscando = false; }
            },
        };
    }
</script>
@endsection
