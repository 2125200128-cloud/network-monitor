@extends('layouts.app')

@section('title', 'Topología de infraestructura')

@section('styles')
<style>
    .dc-lienzo {
        position: relative;
        background-color: #e6ebf2;
        background-image:
            radial-gradient(ellipse at 50% 30%, rgba(255, 255, 255, 0.55), transparent 70%),
            linear-gradient(rgba(100, 116, 139, 0.12) 1px, transparent 1px),
            linear-gradient(90deg, rgba(100, 116, 139, 0.12) 1px, transparent 1px);
        background-size: 100% 100%, 28px 28px, 28px 28px;
    }
    .dark .dc-lienzo {
        background-color: #0c1424;
        background-image:
            linear-gradient(rgba(148, 163, 184, 0.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
        background-size: 28px 28px, 28px 28px;
    }
    .dc-capa { position: relative; display: flex; align-items: stretch; gap: 1rem; padding: 1.4rem 1rem 1.4rem 0; }
    .dc-capa + .dc-capa { border-top: 1px dashed rgb(100 116 139 / 0.28); }
    .dc-capa-titulo {
        flex: 0 0 8.5rem; display: flex; flex-direction: column; justify-content: center; padding-left: 1rem;
        font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--nm-text-muted);
    }
    .dc-capa-titulo b { font-size: 13px; letter-spacing: 0; text-transform: none; color: var(--nm-text); }
    .dc-capa-nodos { flex: 1; display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem 1.75rem; position: relative; z-index: 2; }

    .dc-nodo {
        width: 13.25rem; text-align: left; background: var(--nm-surface); border: 1px solid var(--nm-border);
        border-radius: var(--nm-radius-lg); box-shadow: var(--nm-shadow-md); overflow: hidden;
        transition: box-shadow var(--nm-dur-fast) ease, border-color var(--nm-dur-fast) ease, transform var(--nm-dur-fast) ease;
    }
    .dc-nodo:hover, .dc-nodo.activo { border-color: rgb(var(--nm-primary-rgb) / 0.55); box-shadow: var(--nm-shadow-lg); transform: translateY(-1px); }
    .dc-nodo.sin-monitoreo { border-style: dashed; border-color: var(--nm-border-strong); background: rgb(var(--nm-surface-rgb) / 0.75); }
    .dc-franja { height: 4px; }
    .dc-arte { height: 2.75rem; display: flex; align-items: center; justify-content: center; padding: 0.4rem 0.6rem; background: var(--nm-sunken); border-bottom: 1px solid var(--nm-border); }
    .dc-arte img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .dc-arte svg { width: 2rem; height: 2rem; color: var(--nm-text-muted); }
    .dc-cuerpo { padding: 0.55rem 0.7rem 0.6rem; }
    .dc-nombre { font-size: 13px; font-weight: 800; color: var(--nm-text); line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dc-sub { font-size: 11px; color: var(--nm-text-muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dc-ip { font-family: ui-monospace, Consolas, monospace; font-size: 12px; font-weight: 700; color: var(--nm-text); }
    .dc-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 9999px; border: 1px solid var(--nm-border); color: var(--nm-text-muted); background: var(--nm-sunken); white-space: nowrap; }
    .dc-punto { width: 7px; height: 7px; border-radius: 9999px; display: inline-block; }

    .dc-sep { align-self: stretch; width: 1px; margin: 0 0.5rem; background: repeating-linear-gradient(to bottom, rgb(100 116 139 / 0.45) 0 5px, transparent 5px 10px); position: relative; }
    .dc-sep span { position: absolute; top: -1.15rem; left: 0.5rem; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--nm-text-muted); white-space: nowrap; }

    #dcEnlaces { position: absolute; inset: 0; z-index: 1; pointer-events: none; overflow: visible; }
    #dcEnlaces path { fill: none; stroke-linecap: round; pointer-events: stroke; cursor: help; transition: stroke-width var(--nm-dur-fast) ease, opacity var(--nm-dur-fast) ease; }
    #dcEnlaces path.trazo-aura { stroke-width: 12; stroke: transparent; }
    #dcEnlaces.resaltando path.linea { opacity: 0.15; }
    #dcEnlaces.resaltando path.linea.resaltada { opacity: 1; stroke-width: 3.5; }

    #dcTooltip { position: fixed; z-index: 70; pointer-events: none; max-width: 22rem; }
    #dcPanel { transform: translateX(calc(100% + 2rem)); transition: transform var(--nm-dur-slow) var(--nm-ease); }
    #dcPanel.abierto { transform: translateX(0); }
    .dc-tabla td { padding: 4px 6px; border-top: 1px solid var(--nm-border); vertical-align: top; }
    .dc-tabla th { padding: 4px 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--nm-text-muted); text-align: left; }
    @media (max-width: 767px) { .dc-capa { flex-direction: column; } .dc-capa-titulo { flex-basis: auto; } }
</style>
@endsection

@section('content')
<div class="flex flex-col h-full space-y-3">

    {{-- Encabezado: pestañas de topología + resumen en vivo --}}
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 floating-card px-4 py-2 flex-shrink-0">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-base font-bold" style="color: var(--nm-text)">Topología</h1>
            @include('topologia.partials.pestanas', ['activa' => 'centro_datos'])
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px]" aria-live="polite">
            <span class="dc-chip" title="Nodos lógicos (pares HA y stacks cuentan como uno) / equipos físicos del inventario">
                <b id="kNodos">{{ $datos['resumen']['nodos'] }}</b> nodos · <b id="kFisicos">{{ $datos['resumen']['equipos_fisicos'] }}</b> equipos
            </span>
            <span class="dc-chip" title="En línea"><i class="dc-punto" style="background: var(--nm-success)"></i><b id="kOnline">{{ $datos['resumen']['online'] }}</b></span>
            <span class="dc-chip" title="Advertencia"><i class="dc-punto" style="background: var(--nm-warning)"></i><b id="kWarning">{{ $datos['resumen']['warning'] }}</b></span>
            <span class="dc-chip" title="Sin respuesta"><i class="dc-punto" style="background: var(--nm-danger)"></i><b id="kOffline">{{ $datos['resumen']['offline'] }}</b></span>
            <span class="dc-chip" title="IP del inventario que no está en el monitoreo"><i class="dc-punto" style="border: 1.5px dashed var(--nm-text-muted)"></i><b id="kSinMon">{{ $datos['resumen']['sin_monitoreo'] }}</b> sin monitoreo</span>
            <span class="dc-chip" title="Enlaces descubiertos por CDP/LLDP entre estos equipos"><b id="kEnlaces">{{ $datos['resumen']['enlaces'] }}</b> enlaces</span>
            <span class="font-mono text-[12px]" style="color: var(--nm-text-muted)" title="Se actualiza cada 30 s">Act. <span id="kHora">{{ $datos['resumen']['actualizado'] }}</span></span>
        </div>
    </div>

    {{-- Lienzo por capas --}}
    <div class="relative flex-1 rounded-2xl overflow-auto border custom-scrollbar dc-lienzo" style="border-color: var(--nm-border); min-height: 600px;">
        <div id="dcContenedor" class="relative min-w-[96rem]">
            <svg id="dcEnlaces" aria-hidden="true"></svg>
            <div id="dcCapas"></div>
        </div>

        {{-- Leyenda --}}
        <div class="sticky bottom-3 left-3 inline-flex flex-wrap items-center gap-3 m-3 px-3 py-2 nm-glass rounded-xl text-[11px] font-semibold z-10" style="color: var(--nm-text-muted)">
            <span class="inline-flex items-center gap-1.5"><svg width="22" height="6"><line x1="1" y1="3" x2="21" y2="3" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/></svg>Enlace activo</span>
            <span class="inline-flex items-center gap-1.5"><svg width="22" height="6"><line x1="1" y1="3" x2="21" y2="3" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"/></svg>Degradado</span>
            <span class="inline-flex items-center gap-1.5"><svg width="22" height="6"><line x1="1" y1="3" x2="21" y2="3" stroke="#ef4444" stroke-width="2.5" stroke-dasharray="5 3"/></svg>Caído</span>
            <span class="inline-flex items-center gap-1.5"><svg width="22" height="6"><line x1="1" y1="3" x2="21" y2="3" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/></svg>Sin dato del puerto</span>
            <span>Solo se dibujan enlaces descubiertos por CDP/LLDP.</span>
        </div>
    </div>
</div>

{{-- Tooltip de enlaces --}}
<div id="dcTooltip" class="hidden nm-glass rounded-lg px-3 py-2 text-xs shadow-lg" style="color: var(--nm-text)"></div>

{{-- Panel de detalle --}}
<aside id="dcPanel" class="fixed top-4 right-4 bottom-4 w-[26rem] max-w-[calc(100vw-2rem)] z-[65] flex flex-col" aria-hidden="true" aria-labelledby="dcPanelTitulo"
       style="background: var(--nm-surface); border: 1px solid var(--nm-border); border-radius: var(--nm-radius-lg); box-shadow: var(--nm-shadow-lg);">
    <div class="flex items-start justify-between gap-3 px-4 py-3 border-b" style="border-color: var(--nm-border)">
        <div class="min-w-0">
            <div id="dcPanelCapa" class="text-[11px] font-extrabold uppercase tracking-wider" style="color: var(--nm-text-muted)"></div>
            <h2 id="dcPanelTitulo" class="text-base font-black truncate" style="color: var(--nm-text)"></h2>
            <div id="dcPanelSub" class="text-xs" style="color: var(--nm-text-muted)"></div>
        </div>
        <button type="button" onclick="dcCerrarPanel()" class="p-1.5 rounded-lg hover:bg-black/5 dark:hover:bg-white/10" aria-label="Cerrar detalle">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div id="dcPanelCuerpo" class="flex-1 overflow-y-auto custom-scrollbar px-4 py-3 space-y-4 text-sm"></div>
</aside>

<script>
(function () {
    const URL_DATOS = @json(route('topologia.centro_datos.datos'));
    const REFRESCO_MS = 10000;   // el sondeo actualiza cada equipo cada ~8 s
    const COLOR_ENLACE = { up: '#10b981', degraded: '#f59e0b', down: '#ef4444', unknown: '#94a3b8' };
    const ESTADO = {
        online: ['En línea', 'var(--nm-success)'], warning: ['Advertencia', 'var(--nm-warning)'],
        offline: ['Sin respuesta', 'var(--nm-danger)'], null: ['Sin monitoreo', 'var(--nm-border-strong)'],
    };
    const ICONO_GENERICO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="6" rx="1.5"/><rect x="2" y="14" width="20" height="6" rx="1.5"/><path d="M6 7h.01M6 17h.01M10 7h8M10 17h8"/></svg>';

    let datos = @json($datos);
    let seleccionado = null;
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const nodoPorId = () => Object.fromEntries(datos.nodos.map((n) => [n.id, n]));
    const velocidad = (mbps) => !mbps ? 'velocidad sin dato' : (mbps >= 1000 ? (mbps / 1000).toLocaleString('es-MX') + ' Gbps' : mbps + ' Mbps');

    // ---------- Tarjetas ----------
    function tarjeta(n) {
        const [txtEstado, colEstado] = ESTADO[n.estado] || ESTADO.null;
        const grupo = n.tipo === 'ha' ? '<span class="dc-chip" title="Par de alta disponibilidad">HA ×' + n.miembros.length + '</span>'
            : n.tipo === 'stack' ? '<span class="dc-chip" title="Stack: varios switches administrados como uno">Stack ×' + n.miembros.length + '</span>' : '';
        const aviso = n.avisos.length ? '<span class="dc-chip" style="color: var(--nm-warning); border-color: var(--nm-warning)" title="' + esc(n.avisos.join(' ')) + '">⚠ ' + n.avisos.length + '</span>' : '';
        const carga = n.dispositivo_id && (n.cpu !== null || n.ram !== null)
            ? '<span class="text-[11px] font-mono" style="color: var(--nm-text-muted)">' + (n.cpu !== null ? 'CPU ' + Math.round(n.cpu) + '%' : '') + (n.ram !== null ? ' · RAM ' + Math.round(n.ram) + '%' : '') + '</span>' : '';
        return `
            <button type="button" class="dc-nodo ${n.dispositivo_id ? '' : 'sin-monitoreo'} ${seleccionado === n.id ? 'activo' : ''}" data-nodo="${esc(n.id)}"
                    aria-label="${esc(n.titulo)}, ${esc(txtEstado)}, ${esc(n.ip || 'sin IP')}">
                <div class="dc-franja" style="background: ${colEstado}"></div>
                <div class="dc-arte">${n.arte ? `<img src="${esc(n.arte)}" alt="" loading="lazy">` : ICONO_GENERICO}</div>
                <div class="dc-cuerpo">
                    <div class="dc-nombre" title="${esc(n.titulo)}">${esc(n.titulo)}</div>
                    <div class="dc-sub" title="${esc(n.marca + ' ' + n.modelo)}">${esc(n.marca)} ${esc(n.modelo)}</div>
                    <div class="flex items-center justify-between gap-2 mt-1.5">
                        <span class="dc-ip">${esc(n.ip || '—')}</span>
                        <span class="dc-chip"><i class="dc-punto" style="background: ${colEstado}"></i>${esc(txtEstado)}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1.5 min-h-[18px]">${grupo}${aviso}${carga}</div>
                </div>
            </button>`;
    }

    function pintarCapas() {
        const ids = nodoPorId();
        document.getElementById('dcCapas').innerHTML = datos.capas.map((c, i) => {
            // Dentro de una franja, un separador rotulado marca dónde empieza cada capa (p. ej. firewall | balanceo)
            let previa = null;
            const contenido = c.nodos.map((id) => {
                const n = ids[id];
                const sep = previa !== null && previa !== n.capa
                    ? `<div class="dc-sep" aria-hidden="true"><span>${esc(n.capa_titulo)}</span></div>` : '';
                previa = n.capa;
                return sep + tarjeta(n);
            }).join('');
            return `<section class="dc-capa" data-banda="${i}" aria-label="${esc(c.titulo)}">
                <div class="dc-capa-titulo"><span>Capa</span><b>${esc(c.titulo)}</b></div>
                <div class="dc-capa-nodos">${contenido}</div>
            </section>`;
        }).join('');
        document.querySelectorAll('.dc-nodo').forEach((b) => {
            b.addEventListener('click', () => dcAbrirPanel(b.dataset.nodo));
            b.addEventListener('mouseenter', () => resaltar(b.dataset.nodo));
            b.addEventListener('mouseleave', () => resaltar(null));
        });
        // Las imágenes cambian la altura de las tarjetas al cargar: se vuelven a trazar los enlaces
        document.querySelectorAll('.dc-arte img').forEach((img) => {
            if (!img.complete) img.addEventListener('load', () => requestAnimationFrame(pintarEnlaces), { once: true });
        });
        requestAnimationFrame(pintarEnlaces);
    }

    // ---------- Enlaces (SVG sobre las tarjetas) ----------
    function pintarEnlaces() {
        const svg = document.getElementById('dcEnlaces');
        const cont = document.getElementById('dcContenedor');
        const base = cont.getBoundingClientRect();
        svg.setAttribute('width', cont.scrollWidth);
        svg.setAttribute('height', cont.scrollHeight);
        const rel = (r) => ({ x: r.left - base.left, y: r.top - base.top, w: r.width, h: r.height });
        const caja = (id) => {
            const el = cont.querySelector(`.dc-nodo[data-nodo="${CSS.escape(id)}"]`);
            if (!el) return null;
            return { ...rel(el.getBoundingClientRect()), banda: +el.closest('.dc-capa').dataset.banda };
        };

        // Huecos horizontales de cada franja (entre tarjetas y a los lados) por donde pasan los enlaces que la cruzan
        const franjas = [...cont.querySelectorAll('.dc-capa')].map((sec) => {
            const zona = rel(sec.querySelector('.dc-capa-nodos').getBoundingClientRect());
            const tarjetas = [...sec.querySelectorAll('.dc-nodo')].map((el) => rel(el.getBoundingClientRect())).sort((p, q) => p.x - q.x);
            const huecos = [];
            let izq = zona.x;
            for (const t of tarjetas) {
                if (t.x - izq > 6) huecos.push([izq, t.x]);
                izq = t.x + t.w;
            }
            huecos.push([izq, zona.x + zona.w]);
            const yCentro = tarjetas.length ? tarjetas[0].y + tarjetas[0].h / 2 : zona.y + zona.h / 2;
            return { huecos, yCentro, usos: {} };
        });
        // Punto de paso por la franja intermedia: el hueco más cercano a la recta, con desplazamiento si ya lo usa otra línea
        const paso = (f, xIdeal) => {
            let mejor = 0, dist = Infinity;
            f.huecos.forEach(([a, b], i) => {
                const x = Math.min(Math.max(xIdeal, a + 4), b - 4);
                const d = Math.abs(x - xIdeal) + (b - a < 14 ? 40 : 0);
                if (d < dist) { dist = d; mejor = i; }
            });
            const [a, b] = f.huecos[mejor];
            const n = (f.usos[mejor] = (f.usos[mejor] || 0) + 1) - 1;
            const centro = Math.min(Math.max(xIdeal, a + 6), b - 6);
            const x = Math.min(Math.max(centro + (n % 2 ? 1 : -1) * Math.ceil(n / 2) * 5, a + 3), b - 3);
            return [x, f.yCentro];
        };

        // Reparte los puntos de salida de cada lado de la tarjeta para que no se encimen las líneas
        const salidas = {};
        const tramos = datos.enlaces.map((e) => {
            const a = caja(e.desde), b = caja(e.hasta);
            if (!a || !b) return null;
            const mismaFila = Math.abs(a.y - b.y) < 10;
            const [arriba, abajo] = a.y <= b.y ? [a, b] : [b, a];
            const ladoA = mismaFila ? (a.x < b.x ? 'der' : 'izq') : (a === arriba ? 'abajo' : 'arriba');
            const ladoB = mismaFila ? (a.x < b.x ? 'izq' : 'der') : (b === arriba ? 'abajo' : 'arriba');
            const t = { e, a, b, ladoA, ladoB, mismaFila };
            (salidas[e.desde + ladoA] ??= []).push([t, 'A', b.x + b.w / 2]);
            (salidas[e.hasta + ladoB] ??= []).push([t, 'B', a.x + a.w / 2]);
            return t;
        }).filter(Boolean);
        Object.values(salidas).forEach((lista) => {
            lista.sort((p, q) => p[2] - q[2]);
            lista.forEach(([t, extremo], i) => { t['frac' + extremo] = (i + 1) / (lista.length + 1); });
        });

        const punto = (c, lado, frac) => lado === 'abajo' ? [c.x + c.w * frac, c.y + c.h]
            : lado === 'arriba' ? [c.x + c.w * frac, c.y]
            : lado === 'der' ? [c.x + c.w, c.y + c.h * frac] : [c.x, c.y + c.h * frac];

        // Curva vertical suave por una lista de puntos (tangente vertical en cada uno)
        const curva = (pts) => pts.slice(1).reduce((d, [x, y], i) => {
            const [px, py] = pts[i];
            const my = (py + y) / 2;
            return d + ` C${px},${my} ${x},${my} ${x},${y}`;
        }, `M${pts[0][0]},${pts[0][1]}`);

        svg.innerHTML = tramos.map((t) => {
            const [x1, y1] = punto(t.a, t.ladoA, t.fracA);
            const [x2, y2] = punto(t.b, t.ladoB, t.fracB);
            let d;
            if (t.mismaFila) {
                d = `M${x1},${y1} C${(x1 + x2) / 2},${y1} ${(x1 + x2) / 2},${y2} ${x2},${y2}`;
            } else {
                // Si el enlace salta franjas, pasa por el hueco de cada franja intermedia en vez de por detrás de las tarjetas
                const [p1, p2, b1, b2] = y1 <= y2 ? [[x1, y1], [x2, y2], t.a.banda, t.b.banda] : [[x2, y2], [x1, y1], t.b.banda, t.a.banda];
                const intermedios = [];
                for (let k = Math.min(b1, b2) + 1; k < Math.max(b1, b2); k++) {
                    const f = franjas[k];
                    const xIdeal = p1[0] + (p2[0] - p1[0]) * ((f.yCentro - p1[1]) / ((p2[1] - p1[1]) || 1));
                    intermedios.push(paso(f, xIdeal));
                }
                d = curva([p1, ...intermedios, p2]);
            }
            const color = COLOR_ENLACE[t.e.estado] || COLOR_ENLACE.unknown;
            const guion = t.e.estado === 'down' ? 'stroke-dasharray="7 5"' : '';
            const ancho = t.e.cantidad > 1 ? 3 : 2.25;
            const datosE = esc(JSON.stringify({ desde: t.e.desde, hasta: t.e.hasta }));
            return `<path class="trazo-aura" d="${d}" data-enlace="${datosE}"></path>
                    <path class="linea" d="${d}" stroke="${color}" stroke-width="${ancho}" ${guion} data-a="${esc(t.e.desde)}" data-b="${esc(t.e.hasta)}"></path>`;
        }).join('');

        svg.querySelectorAll('path.trazo-aura').forEach((p) => {
            p.addEventListener('mousemove', (ev) => mostrarTooltip(ev, JSON.parse(p.dataset.enlace)));
            p.addEventListener('mouseleave', ocultarTooltip);
        });
        resaltar(seleccionado);
    }

    function resaltar(id) {
        const svg = document.getElementById('dcEnlaces');
        svg.classList.toggle('resaltando', !!id);
        svg.querySelectorAll('path.linea').forEach((p) => p.classList.toggle('resaltada', !!id && (p.dataset.a === id || p.dataset.b === id)));
        if (!id && seleccionado) resaltar(seleccionado);
    }

    function mostrarTooltip(ev, ref) {
        const e = datos.enlaces.find((x) => x.desde === ref.desde && x.hasta === ref.hasta);
        if (!e) return;
        const ids = nodoPorId();
        const estados = { up: 'Activo', degraded: 'Degradado', down: 'Caído', unknown: 'Sin dato del puerto' };
        const puertos = e.puertos.map(([p1, p2]) => esc(p1 || '¿?') + ' ↔ ' + esc(p2 || '¿?')).join('<br>');
        const tip = document.getElementById('dcTooltip');
        tip.innerHTML = `<div class="font-bold">${esc(ids[e.desde].titulo)} ↔ ${esc(ids[e.hasta].titulo)}</div>
            <div style="color: ${COLOR_ENLACE[e.estado]}" class="font-semibold">${estados[e.estado] || e.estado}${e.cantidad > 1 ? ' · ' + e.cantidad + ' enlaces' : ''}</div>
            <div style="color: var(--nm-text-muted)">${velocidad(e.velocidad_mbps)}${e.trafico_mbps ? ' · tráfico ' + e.trafico_mbps + ' Mbps' : ''}</div>
            <div class="font-mono mt-1" style="color: var(--nm-text-muted)">${puertos}</div>`;
        tip.classList.remove('hidden');
        tip.style.left = Math.min(ev.clientX + 14, innerWidth - tip.offsetWidth - 8) + 'px';
        tip.style.top = Math.min(ev.clientY + 14, innerHeight - tip.offsetHeight - 8) + 'px';
    }
    function ocultarTooltip() { document.getElementById('dcTooltip').classList.add('hidden'); }

    // ---------- Panel de detalle ----------
    window.dcAbrirPanel = function (id) {
        const n = nodoPorId()[id];
        if (!n) return;
        seleccionado = id;
        document.querySelectorAll('.dc-nodo').forEach((b) => b.classList.toggle('activo', b.dataset.nodo === id));
        resaltar(id);
        const [txtEstado, colEstado] = ESTADO[n.estado] || ESTADO.null;
        document.getElementById('dcPanelCapa').textContent = n.capa_titulo;
        document.getElementById('dcPanelTitulo').textContent = n.titulo;
        document.getElementById('dcPanelSub').textContent = `${n.marca} ${n.modelo} · ${n.ip || 'sin IP'}`;

        const ids = nodoPorId();
        const enlaces = datos.enlaces.filter((e) => e.desde === id || e.hasta === id);
        const filasMiembros = n.miembros.map((m) => `<tr>
                <td>${esc({ primario: 'Primario', secundario: 'Secundario (HA)', miembro: 'Miembro', unico: '—' }[m.rol] || m.rol)}</td>
                <td>${esc(m.modelo)}</td><td class="font-mono">${esc(m.serie || '—')}</td><td>${esc(m.hostname || '—')}</td></tr>`).join('');
        const monitoreo = n.dispositivo_id ? `
            <dl class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[13px]">
                <dt style="color: var(--nm-text-muted)">Estado</dt><dd class="font-bold"><i class="dc-punto" style="background:${colEstado}"></i> ${esc(txtEstado)}</dd>
                <dt style="color: var(--nm-text-muted)">Nombre en el monitoreo</dt><dd>${esc(n.nombre_monitoreo)}</dd>
                <dt style="color: var(--nm-text-muted)">Clasificación</dt><dd>${esc(n.tipo_detectado || 'Sin dato')}</dd>
                <dt style="color: var(--nm-text-muted)">CPU / RAM</dt><dd class="font-mono">${n.cpu !== null ? Math.round(n.cpu) + ' %' : 'Sin dato'} / ${n.ram !== null ? Math.round(n.ram) + ' %' : 'Sin dato'}</dd>
                <dt style="color: var(--nm-text-muted)">Encendido</dt><dd>${esc(n.uptime || 'Sin dato')}</dd>
                <dt style="color: var(--nm-text-muted)">Versión reportada</dt><dd class="font-mono">${esc(n.version_snmp || 'Sin dato')}</dd>
                <dt style="color: var(--nm-text-muted)">Último contacto</dt><dd>${esc(n.ultimo_contacto || 'Sin dato')}</dd>
            </dl>
            <a href="${esc(n.ficha)}" class="inline-flex mt-3 px-3 py-2 rounded-lg text-xs font-bold nm-metal-azure">Abrir ficha técnica</a>`
            : `<p class="text-[13px]" style="color: var(--nm-text-muted)">La IP <b class="font-mono">${esc(n.ip || '—')}</b> no está registrada en el monitoreo, así que no hay estado ni enlaces descubiertos. Aparecerá aquí en cuanto el descubrimiento de red lo agregue.</p>`;
        const avisos = n.avisos.length ? `<section><h3 class="text-[11px] font-extrabold uppercase tracking-wider mb-1.5" style="color: var(--nm-warning)">Diferencias inventario / monitoreo</h3>
            <ul class="list-disc pl-5 space-y-1 text-[13px]">${n.avisos.map((a) => '<li>' + esc(a) + '</li>').join('')}</ul></section>` : '';
        const listaEnlaces = enlaces.length ? enlaces.map((e) => {
            const otro = ids[e.desde === id ? e.hasta : e.desde];
            const puertos = e.puertos.map(([p1, p2]) => e.desde === id ? [p1, p2] : [p2, p1]).map(([p1, p2]) => esc(p1 || '¿?') + ' → ' + esc(p2 || '¿?')).join(', ');
            return `<li class="flex items-start gap-2"><i class="dc-punto mt-1.5" style="background:${COLOR_ENLACE[e.estado]}"></i>
                <span><b>${esc(otro.titulo)}</b><br><span class="font-mono text-[12px]" style="color: var(--nm-text-muted)">${puertos} · ${velocidad(e.velocidad_mbps)}</span></span></li>`;
        }).join('') : '<li style="color: var(--nm-text-muted)">Sin enlaces descubiertos con otros equipos de esta vista.</li>';

        document.getElementById('dcPanelCuerpo').innerHTML = `
            ${avisos}
            <section><h3 class="text-[11px] font-extrabold uppercase tracking-wider mb-1.5" style="color: var(--nm-text-muted)">Monitoreo en vivo</h3>${monitoreo}</section>
            <section><h3 class="text-[11px] font-extrabold uppercase tracking-wider mb-1.5" style="color: var(--nm-text-muted)">Inventario físico${n.descripcion ? ' · ' + esc(n.descripcion) : ''}</h3>
                <div class="overflow-x-auto"><table class="dc-tabla w-full text-[12px]"><thead><tr><th>Rol</th><th>Modelo</th><th>Serie</th><th>Hostname</th></tr></thead><tbody>${filasMiembros}</tbody></table></div>
                ${n.miembros.find((m) => m.version) ? '<p class="text-[12px] mt-1.5" style="color: var(--nm-text-muted)">Versión en inventario: <b class="font-mono">' + esc(n.miembros.find((m) => m.version).version) + '</b></p>' : ''}
            </section>
            <section><h3 class="text-[11px] font-extrabold uppercase tracking-wider mb-1.5" style="color: var(--nm-text-muted)">Enlaces en esta vista (${enlaces.length})</h3>
                <ul class="space-y-2 text-[13px]">${listaEnlaces}</ul>
                ${n.enlaces_externos ? '<p class="text-[12px] mt-2" style="color: var(--nm-text-muted)">Además tiene ' + n.enlaces_externos + ' enlace(s) con equipos fuera del centro de datos (ver Red completa).</p>' : ''}
            </section>`;
        const panel = document.getElementById('dcPanel');
        panel.classList.add('abierto');
        panel.setAttribute('aria-hidden', 'false');
    };

    window.dcCerrarPanel = function () {
        seleccionado = null;
        document.getElementById('dcPanel').classList.remove('abierto');
        document.getElementById('dcPanel').setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.dc-nodo.activo').forEach((b) => b.classList.remove('activo'));
        resaltar(null);
    };
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') dcCerrarPanel(); });

    // ---------- Actualización en vivo ----------
    function pintarResumen() {
        const r = datos.resumen;
        for (const [id, v] of Object.entries({ kNodos: r.nodos, kFisicos: r.equipos_fisicos, kOnline: r.online, kWarning: r.warning, kOffline: r.offline, kSinMon: r.sin_monitoreo, kEnlaces: r.enlaces, kHora: r.actualizado })) {
            document.getElementById(id).textContent = v;
        }
    }
    async function refrescar() {
        try {
            const r = await fetch(URL_DATOS, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) return;
            datos = await r.json();
            pintarCapas();
            pintarResumen();
            if (seleccionado) dcAbrirPanel(seleccionado);
        } catch (e) { /* se reintenta en el siguiente ciclo */ }
    }

    pintarCapas();
    new ResizeObserver(() => requestAnimationFrame(pintarEnlaces)).observe(document.getElementById('dcContenedor'));
    setInterval(refrescar, REFRESCO_MS);
})();
</script>
@endsection
