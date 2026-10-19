{{-- Telemetría FortiGate del equipo (FORTINET-FORTIGATE-MIB, snmp_detail_collector.py cada 5 min) --}}
@php
    $fSis = $chasis?->forti_sistema ?? [];
    $fSdwan = $chasis?->forti_sdwan ?? [];
    $fSensores = $chasis?->forti_sensores ?? [];
    $fSsl = $chasis?->forti_vpn_ssl ?? [];
    $fSeg = $chasis?->forti_seguridad ?? [];
    $fVpn = $chasis?->vpn_tuneles ?? [];
    $fHa = $chasis?->ha_estado ?? [];
    $hayDatos = $fSis || $fSdwan || $fSensores || $fSsl || $fSeg || $fVpn || $fHa;
    $fn = fn ($v, $suf = '') => $v === null ? '—' : (is_numeric($v) ? number_format((float) $v, fmod((float) $v, 1) ? 2 : 0) : $v) . $suf;
    $mbpsF = fn ($v) => $v === null ? '—' : ($v >= 1000 ? number_format($v / 1000, 2) . ' Gbps' : number_format($v, 2) . ' Mbps');
    $th = 'text-left text-[11px] font-bold uppercase tracking-wider text-gray-400 pb-2 pr-3';
    $td = 'py-1.5 pr-3 text-xs text-gray-700 border-t border-gray-100';
@endphp
<div class="mb-6 rounded-2xl border border-rose-100 bg-white shadow-sm overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 border-b border-rose-100" style="background: linear-gradient(90deg, #fff1f2, #ffffff);">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-[#da291c]"></span>
            <h3 class="text-sm font-extrabold text-gray-800">FortiGate · Seguridad perimetral</h3>
        </div>
        <span class="text-[11px] font-mono text-gray-500">
            {{ $chasis?->detalle_medido_en ? 'Medido ' . $chasis->detalle_medido_en->diffForHumans() : 'Sin mediciones' }} · se actualiza cada 5 min
        </span>
    </div>

    @if(!$hayDatos)
        <div class="p-5 text-xs text-gray-600 leading-relaxed">
            <p class="font-bold text-gray-800 mb-1">Este firewall todavía no entrega datos por SNMP.</p>
            <p>Cuando responda, aquí aparecerán: SLA de SD-WAN por enlace, túneles IPsec con su tráfico, usuarios de VPN SSL, estado del clúster HA,
               sensores de hardware, uso de disco, sesiones nuevas por segundo y detecciones de IPS/antivirus.</p>
            <p class="mt-2">Verifica la comunidad con el botón <strong>Probar</strong> de la credencial SNMP (arriba), que la IP del servidor NOC esté en los
               <em>hosts</em> de la comunidad del FortiGate y que la interfaz tenga <code class="font-mono">allowaccess snmp</code>.</p>
        </div>
    @else
        <div class="p-5 space-y-5">
            {{-- Cifras --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach([
                    ['Sesiones nuevas/s', $fn($fSis['sesiones_nuevas_seg'] ?? null)],
                    ['Disco', ($fSis['disco_pct'] ?? null) === null ? '—' : $fSis['disco_pct'] . '%'],
                    ['Usuarios VPN SSL', $fn($fSsl['usuarios'] ?? null) . (!empty($fSsl['max_usuarios']) ? ' / ' . $fSsl['max_usuarios'] : '')],
                    ['IPS último ciclo', $fn($fSeg['ips_detectadas_ciclo'] ?? null) . (($fSeg['ips_criticas_ciclo'] ?? 0) ? ' (' . $fSeg['ips_criticas_ciclo'] . ' críticas)' : '')],
                ] as [$etq, $val])
                    <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $etq }}</p>
                        <p class="text-base font-extrabold font-mono text-gray-900">{{ $val }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                {{-- SD-WAN --}}
                <div>
                    <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider mb-2">SLA de SD-WAN</h4>
                    @if($fSdwan)
                        <table class="w-full"><thead><tr><th class="{{ $th }}">Health-check</th><th class="{{ $th }}">Estado</th><th class="{{ $th }} text-right">Latencia</th><th class="{{ $th }} text-right">Jitter</th><th class="{{ $th }} text-right">Pérdida</th></tr></thead><tbody>
                        @foreach($fSdwan as $e)
                            <tr><td class="{{ $td }} font-semibold">{{ $e['nombre'] ?? '—' }}{{ !empty($e['interfaz']) ? ' · ' . $e['interfaz'] : '' }}</td>
                                <td class="{{ $td }} font-bold {{ ($e['estado'] ?? null) === 'dead' ? 'text-red-600' : 'text-emerald-600' }}">{{ ($e['estado'] ?? null) === 'dead' ? 'CAÍDO' : (($e['estado'] ?? null) === 'alive' ? 'OK' : '—') }}</td>
                                <td class="{{ $td }} text-right font-mono">{{ $fn($e['latencia_ms'] ?? null, ' ms') }}</td>
                                <td class="{{ $td }} text-right font-mono">{{ $fn($e['jitter_ms'] ?? null, ' ms') }}</td>
                                <td class="{{ $td }} text-right font-mono font-bold">{{ $fn($e['perdida_pct'] ?? null, '%') }}</td></tr>
                        @endforeach
                        </tbody></table>
                    @else
                        <p class="text-xs text-gray-400">El equipo no reporta health-checks de SD-WAN.</p>
                    @endif
                </div>

                {{-- Túneles IPsec --}}
                <div>
                    <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider mb-2">Túneles IPsec</h4>
                    @if($fVpn)
                        <table class="w-full"><thead><tr><th class="{{ $th }}">Túnel</th><th class="{{ $th }}">Gateway</th><th class="{{ $th }}">Estado</th><th class="{{ $th }} text-right">↓ Entrada</th><th class="{{ $th }} text-right">↑ Salida</th></tr></thead><tbody>
                        @foreach($fVpn as $t)
                            <tr><td class="{{ $td }} font-semibold">{{ $t['fase1'] ?? '—' }}{{ !empty($t['fase2']) ? ' / ' . $t['fase2'] : '' }}</td>
                                <td class="{{ $td }} font-mono">{{ $t['remoto'] ?? '—' }}</td>
                                <td class="{{ $td }} font-bold {{ ($t['estado'] ?? null) === 'up' ? 'text-emerald-600' : 'text-red-600' }}">{{ strtoupper($t['estado'] ?? '—') }}</td>
                                <td class="{{ $td }} text-right font-mono">{{ $mbpsF($t['mbps_in'] ?? null) }}</td>
                                <td class="{{ $td }} text-right font-mono">{{ $mbpsF($t['mbps_out'] ?? null) }}</td></tr>
                        @endforeach
                        </tbody></table>
                    @else
                        <p class="text-xs text-gray-400">El equipo no reporta túneles IPsec.</p>
                    @endif
                </div>

                {{-- HA --}}
                <div>
                    <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider mb-2">Alta disponibilidad (HA)</h4>
                    @if($fHa)
                        <p class="text-xs text-gray-600 mb-2">Modo: <strong>{{ $fHa['modo'] ?? '—' }}</strong></p>
                        @if(!empty($fHa['miembros']))
                            <table class="w-full"><thead><tr><th class="{{ $th }}">Miembro</th><th class="{{ $th }}">Sincronía</th><th class="{{ $th }} text-right">CPU</th><th class="{{ $th }} text-right">RAM</th><th class="{{ $th }} text-right">Sesiones</th></tr></thead><tbody>
                            @foreach($fHa['miembros'] as $m)
                                <tr><td class="{{ $td }} font-semibold">{{ $m['nombre'] ?? '—' }}</td>
                                    <td class="{{ $td }} font-bold {{ !empty($m['sincronizado']) ? 'text-emerald-600' : 'text-red-600' }}">{{ !empty($m['sincronizado']) ? 'Sincronizado' : 'SIN SINCRONIZAR' }}</td>
                                    <td class="{{ $td }} text-right font-mono">{{ $fn($m['cpu'] ?? null, '%') }}</td>
                                    <td class="{{ $td }} text-right font-mono">{{ $fn($m['ram'] ?? null, '%') }}</td>
                                    <td class="{{ $td }} text-right font-mono">{{ $fn($m['sesiones'] ?? null) }}</td></tr>
                            @endforeach
                            </tbody></table>
                        @endif
                    @else
                        <p class="text-xs text-gray-400">Sin datos de HA.</p>
                    @endif
                </div>

                {{-- Sensores --}}
                <div>
                    <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider mb-2">Sensores de hardware</h4>
                    @if($fSensores)
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach(collect($fSensores)->sortByDesc('alarma') as $s)
                                <div class="rounded-lg px-2.5 py-1.5 border {{ !empty($s['alarma']) ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-100' }}">
                                    <p class="text-[11px] text-gray-500 truncate" title="{{ $s['nombre'] ?? '' }}">{{ $s['nombre'] ?? '—' }}</p>
                                    <p class="text-xs font-bold font-mono {{ !empty($s['alarma']) ? 'text-red-600' : 'text-gray-800' }}">
                                        {{ $fn($s['valor'] ?? null, ['temperatura' => ' °C', 'ventilador' => ' RPM', 'voltaje' => ' V'][$s['tipo'] ?? ''] ?? '') }}{{ !empty($s['alarma']) ? ' · ALARMA' : '' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400">El equipo no reporta sensores de hardware.</p>
                    @endif
                </div>
            </div>

            {{-- IPS / AV acumulados --}}
            @if($fSeg)
                <div class="text-[11px] text-gray-500 border-t border-gray-100 pt-3">
                    <strong class="text-gray-700">Acumulado desde el último reinicio:</strong>
                    IPS {{ $fn($fSeg['ips_detectadas'] ?? null) }} detectadas / {{ $fn($fSeg['ips_bloqueadas'] ?? null) }} bloqueadas
                    ({{ $fn($fSeg['ips_criticas'] ?? null) }} críticas, {{ $fn($fSeg['ips_altas'] ?? null) }} altas) ·
                    Antivirus {{ $fn($fSeg['av_detectados'] ?? null) }} detectados / {{ $fn($fSeg['av_bloqueados'] ?? null) }} bloqueados.
                </div>
            @endif
        </div>
    @endif
</div>
