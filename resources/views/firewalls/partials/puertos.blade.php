{{-- Puertos físicos de cada FortiGate dibujados como su panel frontal. Zona propia: el puerto elegido (sel) se conserva al refrescar. --}}
@php
    $textoEstado = ['up' => 'Conectado', 'down' => 'Sin enlace', 'deshabilitado' => 'Deshabilitado'];
    $claseEstado = ['up' => 'e-up', 'down' => 'e-down', 'deshabilitado' => 'e-deshabilitado'];
    // El LED parpadea más rápido con más uso medido; sin uso medido no parpadea
    $ritmo = fn ($u) => $u === null || $u <= 0 ? null : ($u < 1 ? '2.4s' : ($u < 10 ? '1.4s' : ($u < 50 ? '.9s' : '.5s')));
    $equipos = collect($filas)->sortBy(fn ($f) => [($puertos[$f['id']]['fuente'] ?? null) === 'snmp' ? 0 : (($puertos[$f['id']]['total'] ?? 0) ? 1 : 2), -($puertos[$f['id']]['total'] ?? 0)]);
@endphp
<div data-refrescar="firewalls-puertos" data-refrescar-cada="15" x-data="{ sel: {} }" class="floating-card p-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
        <div>
            <h2 class="fw-titulo">Puertos físicos</h2>
            <p class="text-[11px]" style="color: var(--nm-text-muted)">Del que responde SNMP, lo que reporta él mismo. De los demás, los puertos que ven sus vecinos (LLDP/CDP), con el estado medido del otro lado del cable. Clic en un puerto para ver a qué se conecta.</p>
        </div>
        <div class="fw-leyenda">
            <span><i class="fw-led-muestra e-up"></i>Conectado</span>
            <span><i class="fw-led-muestra e-down"></i>Sin enlace</span>
            <span><i class="fw-led-muestra e-deshabilitado"></i>Deshabilitado</span>
            <span><i class="fw-led-muestra e-nd"></i>Sin dato vigente</span>
            <span>El LED parpadea si el puerto tiene tráfico medido</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach($equipos as $f)
            @php $pp = $puertos[$f['id']] ?? ['fuente' => null, 'grupos' => [], 'conectados' => 0, 'total' => 0]; $id = $f['id']; @endphp
            {{-- Los de muchos puertos ocupan dos columnas para que su panel quepa sin desplazarse --}}
            <div class="p-3 rounded-xl border min-w-0 flex flex-col {{ $pp['total'] > 4 ? 'md:col-span-2' : '' }}" style="border-color: var(--nm-border); background: var(--nm-surface)">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <a href="{{ route('dispositivos.show', $id) }}" class="text-sm font-bold truncate hover:underline" style="color: var(--nm-text)">{{ $f['nombre'] }}</a>
                        <span class="text-[11px] font-mono shrink-0" style="color: var(--nm-text-muted)">{{ $f['ip'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($pp['fuente'] === 'snmp')
                            <span class="fw-pill fw-ok">SNMP en vivo</span>
                        @elseif($pp['fuente'] === 'vecinos')
                            <span class="fw-pill fw-gris" title="El FortiGate no responde SNMP: solo se conocen los puertos que reportan sus vecinos">Visto por vecinos</span>
                        @endif
                        @if($pp['total'])
                            <span class="text-[11px] font-mono" style="color: var(--nm-text-muted)"><b style="color: var(--nm-text)">{{ $pp['conectados'] }}</b> de {{ $pp['total'] }} conectados</span>
                        @endif
                    </div>
                </div>

                {{-- Panel frontal --}}
                <div class="fw-chasis">
                    <div class="fw-placa">
                        <span title="Responde ping"><i style="background: {{ $f['estado'] === 'offline' ? '#ef4444' : '#22c55e' }}; box-shadow: 0 0 5px {{ $f['estado'] === 'offline' ? '#ef4444' : '#22c55e' }}"></i>Ping</span>
                        <span title="Responde SNMP"><i style="{{ $f['snmp'] ? 'background: #22c55e; box-shadow: 0 0 5px #22c55e' : '' }}"></i>SNMP</span>
                    </div>
                    @forelse($pp['grupos'] as $grupo)
                        <div class="fw-grupo">
                            @foreach($grupo as $p)
                                @php
                                    $vec = collect($p['vecinos'])->map(fn ($v) => $v['equipo'] . ($v['puerto'] ? ' ' . $v['puerto'] : ''))->implode(', ');
                                    $titulo = $p['nombre'] . ($p['alias'] ? ' (' . $p['alias'] . ')' : '') . ' · ' . ($textoEstado[$p['estado']] ?? 'Sin dato vigente')
                                        . ($p['velocidad'] ? ' · ' . $p['velocidad'] : '') . ($p['util_pct'] !== null ? ' · uso ' . $p['util_pct'] . ' %' : '') . ($vec ? ' → ' . $vec : '');
                                    $vel = $ritmo($p['util_pct']);
                                @endphp
                                <button type="button" class="fw-p fw-p-{{ $p['tipo'] }} {{ $claseEstado[$p['estado']] ?? 'e-nd' }} {{ $vel ? 'act' : '' }}"
                                        @if($vel) style="--ritmo: {{ $vel }}" @endif title="{{ $titulo }}"
                                        :class="sel[{{ $id }}] === {{ $p['clave'] }} && 'sel'"
                                        @click="sel[{{ $id }}] = sel[{{ $id }}] === {{ $p['clave'] }} ? null : {{ $p['clave'] }}">
                                    <i class="fw-led"></i>
                                    <span class="fw-boca"></span>
                                    <small>{{ $p['etiqueta'] }}</small>
                                    @if($p['util_pct'] !== null)<span class="fw-uso"><i style="width: {{ min(100, max(4, $p['util_pct'])) }}%; background: {{ $colorBarra($p['util_pct'], 70, 90) }}"></i></span>@endif
                                </button>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-[11px] font-mono px-2" style="color: #9aa4b1">Sin puertos conocidos: no responde SNMP y ningún vecino lo reporta por LLDP/CDP.</p>
                    @endforelse
                </div>

                {{-- Detalle del puerto elegido --}}
                @if($pp['total'])
                    <div class="fw-detalle flex-1">
                        <p x-show="!sel[{{ $id }}]" style="color: var(--nm-text-muted)">
                            @php $conTrafico = collect($pp['grupos'])->flatten(1)->where('util_pct', '>', 0)->sortByDesc('util_pct')->take(4); @endphp
                            @if($conTrafico->isNotEmpty())
                                Más uso: {{ $conTrafico->map(fn ($p) => $p['nombre'] . ' ' . $p['util_pct'] . ' %')->implode(' · ') }}
                            @else
                                Clic en un puerto para ver a qué se conecta.
                            @endif
                        </p>
                        @foreach(collect($pp['grupos'])->flatten(1) as $p)
                            <div x-show="sel[{{ $id }}] === {{ $p['clave'] }}" x-cloak>
                                <p>
                                    <b>{{ $p['nombre'] }}</b>@if($p['alias'])<span style="color: var(--nm-text-muted)"> · {{ $p['alias'] }}</span>@endif
                                    · <span class="{{ $p['estado'] === 'up' ? '' : ($p['estado'] === null ? '' : 'fw-t-warn') }}">{{ $textoEstado[$p['estado']] ?? 'Sin dato vigente' }}</span>
                                    @if($p['velocidad']) · {{ $p['velocidad'] }}@endif
                                    @if($p['util_pct'] !== null) · uso <b class="font-mono {{ $tono($p['util_pct'], 70, 90) }}">{{ $p['util_pct'] }} %</b>@endif
                                    @if($p['errores'] !== null) · errores acumulados <span class="font-mono {{ $p['errores'] ? 'fw-t-warn' : '' }}">{{ number_format($p['errores']) }}</span>@endif
                                </p>
                                @forelse($p['vecinos'] as $v)
                                    <p class="mt-0.5">
                                        → <a href="{{ route('dispositivos.show', $v['equipo_id']) }}" class="font-bold hover:underline" style="color: var(--nm-primary)">{{ $v['equipo'] }}</a>
                                        @if($v['puerto'])<span class="font-mono">{{ $v['puerto'] }}</span>@endif
                                        <span style="color: var(--nm-text-muted)">
                                            @if($v['visto_hace']) · el vecino lo vio por última vez {{ $v['visto_hace'] }}
                                            @elseif($v['estado']) · su puerto: {{ $textoEstado[$v['estado']] ?? $v['estado'] }}{{ $v['util_pct'] !== null ? ', uso ' . $v['util_pct'] . ' %' : '' }}
                                            @else · sin lectura vigente del puerto del vecino
                                            @endif
                                        </span>
                                    </p>
                                @empty
                                    <p class="mt-0.5" style="color: var(--nm-text-muted)">Ningún vecino lo reporta por LLDP/CDP.</p>
                                @endforelse
                                <p class="mt-0.5 text-[11px]" style="color: var(--nm-text-muted)">
                                    {{ $pp['fuente'] === 'snmp' ? 'Estado reportado por el FortiGate (SNMP).' : 'Estado medido en el puerto del vecino: el FortiGate no responde SNMP.' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
