@extends('layouts.app')

@section('title', 'Equipos nuevos')

@section('styles')
<style>
    [x-cloak] { display: none !important; }
    .en-kpi { padding: 12px 14px; border-radius: var(--nm-radius-lg); background: var(--nm-surface); border: 1px solid var(--nm-border); text-align: left; }
    .en-kpi span { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); }
    .en-kpi b { display: block; font-size: 22px; font-family: ui-monospace, Consolas, monospace; margin-top: 2px; }
    .en-kpi.activa { border-color: var(--nm-primary); box-shadow: 0 0 0 1.5px var(--nm-primary); }
    .en-campo { font-size: 12.5px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); }
    .en-tabla { width: 100%; border-collapse: collapse; }
    .en-tabla th { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--nm-text-muted); text-align: left; padding: 7px 9px; border-bottom: 1px solid var(--nm-border); white-space: nowrap; position: sticky; top: 0; background: var(--nm-surface); }
    .en-tabla td { padding: 6px 9px; border-bottom: 1px solid var(--nm-border); font-size: 12.5px; color: var(--nm-text); white-space: nowrap; }
    .en-pill { display: inline-flex; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 9999px; }
    .en-nuevo { color: #1d4ed8; background: #dbeafe; } .dark .en-nuevo { color: #93c5fd; background: rgb(30 58 138 / .5); }
    .en-sospechoso { color: #b91c1c; background: #fee2e2; } .dark .en-sospechoso { color: #fca5a5; background: rgb(127 29 29 / .45); }
    .en-conocido { color: #047857; background: #d1fae5; } .dark .en-conocido { color: #6ee7b7; background: rgb(6 78 59 / .5); }
    .en-base { color: var(--nm-text-muted); background: var(--nm-sunken); }
    .en-btn { font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 8px; border: 1px solid var(--nm-border); background: var(--nm-surface); color: var(--nm-text); }
    .en-btn:disabled { opacity: .4; }
</style>
@endsection

@section('content')
<div class="flex flex-col space-y-4 pb-6" x-data="equiposNuevos()">
    <script type="application/json" id="equiposVistos">{!! json_encode($equipos, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    {{-- Encabezado --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-black leading-tight" style="color: var(--nm-text)">Equipos nuevos en la red 10.4</h1>
            <p class="text-xs" style="color: var(--nm-text-muted)">
                Aparatos que aparecen en las tablas ARP de los switches y routers y que nunca se habían visto. Se revisa cada 30 min
                y solo se listan aquí (no se avisan por Telegram).
                · Línea base: {{ $lineaBase ? \Carbon\Carbon::parse($lineaBase)->format('d/m/Y H:i') : '—' }}
                · Última lectura: {{ $ultimaLectura ? \Carbon\Carbon::parse($ultimaLectura)->format('d/m/Y H:i') : '—' }}
            </p>
        </div>
        <a href="{{ route('equipos.index') }}" class="text-xs font-bold hover:underline" style="color: var(--nm-primary)">← Equipos</a>
    </div>

    {{-- Grupos (son también los filtros) --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <template x-for="g in grupos" :key="g.clave">
            <button type="button" class="en-kpi" :class="grupo === g.clave && 'activa'" @click="grupo = g.clave" :title="g.ayuda">
                <span x-text="g.nombre"></span>
                <b :style="'color:' + (g.color || 'var(--nm-text)')" x-text="cuenta(g.clave)"></b>
            </button>
        </template>
    </div>

    <div class="floating-card p-5">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <p class="text-xs mr-auto" style="color: var(--nm-text-muted)" x-text="grupos.find(g => g.clave === grupo).ayuda"></p>
            <input type="search" x-model="q" placeholder="Buscar IP, MAC, fabricante, switch, nota…" class="en-campo w-72">
            @if($puedeRevisar)
                <button type="button" class="en-btn" x-show="(grupo === 'pendientes' || grupo === 'aleatoria') && cuenta(grupo) > 0"
                        @click="aprobarTodos()" :disabled="ocupado" title="Los que reconoces todos de una vez (p. ej. después de instalar equipos nuevos)">
                    Marcar todos como conocidos
                </button>
            @endif
        </div>
        <div class="max-h-[560px] overflow-auto">
            <table class="en-tabla">
                <thead><tr><th>Estado</th><th>IP</th><th>MAC</th><th>Fabricante</th><th>Visto en</th><th>En inventario</th><th>Primera vez</th><th>Última vez</th><th>Nota</th><th></th></tr></thead>
                <tbody>
                    <template x-for="e in filas.slice(0, 500)" :key="e.id">
                        <tr>
                            <td>
                                <span class="en-pill" :class="'en-' + e.estado"
                                      x-text="{ nuevo: 'Nuevo', sospechoso: 'Sospechoso', conocido: 'Conocido', base: 'Línea base' }[e.estado]"></span>
                            </td>
                            <td class="font-mono font-bold" x-text="e.ip"></td>
                            <td class="font-mono text-xs">
                                <span x-text="e.mac"></span>
                                <span x-show="e.aleatoria" class="ml-1 text-[10px] font-bold opacity-70" title="MAC inventada por el propio aparato (celulares, laptops): cambia sola">aleatoria</span>
                            </td>
                            <td class="max-w-[220px] truncate" :title="e.fabricante" x-text="e.fabricante || '—'"></td>
                            <td x-text="e.visto"></td>
                            <td class="text-xs" x-text="e.enInventario || '—'"></td>
                            <td class="font-mono text-xs" x-text="e.primera"></td>
                            <td class="font-mono text-xs" x-text="e.ultima"></td>
                            <td class="text-xs max-w-[200px] truncate" :title="(e.nota || '') + (e.revisado ? ' — ' + e.revisado : '')" x-text="e.nota || (e.revisado ? 'Revisado: ' + e.revisado : '')"></td>
                            <td class="text-right">
                                @if($puedeRevisar)
                                    <span class="inline-flex gap-1">
                                        <button type="button" class="en-btn" x-show="e.estado !== 'conocido' && e.estado !== 'base'" @click="revisar(e, 'conocido')" :disabled="ocupado">Conocido</button>
                                        <button type="button" class="en-btn" x-show="e.estado !== 'sospechoso'" @click="revisar(e, 'sospechoso')" :disabled="ocupado" style="color: #b91c1c">Sospechoso</button>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p x-show="!filas.length" class="text-xs py-6 text-center" style="color: var(--nm-text-muted)" x-text="grupo === 'pendientes' ? 'Nada pendiente de revisar.' : 'Ningún aparato coincide.'"></p>
            <p x-show="filas.length > 500" class="text-xs py-2 text-center" style="color: var(--nm-text-muted)">Se muestran los primeros 500; usa el buscador para encontrar el resto.</p>
        </div>
        <p class="text-[11px] mt-2" style="color: var(--nm-text-muted)">
            «Visto en» es el router o core en cuya tabla ARP apareció (el que enruta su red), no el switch donde está conectado.
        </p>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function equiposNuevos() {
        const pedir = async (metodo, url, cuerpo) => {
            const r = await fetch(url, { method: metodo, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()), 'X-Requested-With': 'XMLHttpRequest' }, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
            const j = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(Object.values(j.errors || {})[0]?.[0] || j.message || `HTTP ${r.status}`);
            return j;
        };
        const deGrupo = {
            pendientes: e => e.estado === 'nuevo' && !e.aleatoria,
            aleatoria: e => e.estado === 'nuevo' && e.aleatoria,
            sospechosos: e => e.estado === 'sospechoso',
            conocidos: e => e.estado === 'conocido' || e.estado === 'base',
            todos: () => true,
        };
        return {
            lista: JSON.parse(document.getElementById('equiposVistos').textContent),
            grupo: 'pendientes', q: '', ocupado: false,
            grupos: [
                { clave: 'pendientes', nombre: 'Pendientes de revisar', color: '#1d4ed8', ayuda: 'Nuevos con MAC de fábrica, pendientes de revisar.' },
                { clave: 'aleatoria', nombre: 'Nuevos con MAC aleatoria', ayuda: 'Celulares y laptops que inventan su MAC: aparecen como nuevos a menudo.' },
                { clave: 'sospechosos', nombre: 'Sospechosos', color: '#dc2626', ayuda: 'Marcados como sospechosos al revisarlos.' },
                { clave: 'conocidos', nombre: 'Conocidos', color: '#059669', ayuda: 'Revisados como conocidos y los de la línea base (los que ya estaban cuando empezó la vigilancia).' },
                { clave: 'todos', nombre: 'Todos', ayuda: 'Todos los aparatos vistos en la red 10.4.' },
            ],
            cuenta(clave) { return this.lista.filter(deGrupo[clave]).length; },
            get filas() {
                const q = this.q.toLowerCase().trim();
                return this.lista.filter(deGrupo[this.grupo])
                    .filter(e => !q || [e.ip, e.mac, e.fabricante, e.visto, e.nota, e.enInventario].join(' ').toLowerCase().includes(q));
            },
            async revisar(e, estado) {
                const nota = prompt(estado === 'sospechoso' ? `¿Por qué es sospechoso ${e.ip}? (opcional)` : `Nota para ${e.ip} (opcional): qué es o de quién`, e.nota || '');
                if (nota === null) return;
                this.ocupado = true;
                try {
                    await pedir('PUT', `{{ url('/api/equipos-vistos') }}/${e.id}`, { estado, nota });
                    Object.assign(e, { estado, nota: nota.trim() || null, revisado: @json(auth()->user()?->name) + ', ahora' });
                } catch (err) { alert('No se pudo guardar: ' + err.message); }
                this.ocupado = false;
            },
            async aprobarTodos() {
                const aleatoria = this.grupo === 'aleatoria';
                if (!confirm(`¿Marcar como conocidos los ${this.cuenta(this.grupo)} ${aleatoria ? 'nuevos con MAC aleatoria' : 'pendientes'}?`)) return;
                this.ocupado = true;
                try {
                    await pedir('POST', @json(route('equipos_vistos.aprobar')), { aleatoria });
                    this.lista.filter(deGrupo[this.grupo]).forEach(e => e.estado = 'conocido');
                } catch (err) { alert('No se pudo: ' + err.message); }
                this.ocupado = false;
            },
        };
    }
</script>
@endsection
