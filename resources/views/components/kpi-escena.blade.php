{{--
    Ilustración animada de fondo para tarjetas KPI (decorativa: aria-hidden, sin datos).
    Líneas finas en el color de la tarjeta, opacidad baja y desvanecida hacia la izquierda (estilos .kpi-escena en
    layouts/app). Props: tipo (servidores|nube|reloj|molecula|reinicio|escudo|trafico|red|ondas), tono (crit|claro|naranja|null).
--}}
@props(['tipo', 'tono' => null, 'centro' => false])
@php
    $color = match ($tono) {
        'crit' => '#ef4444',
        'claro' => '#ffffff',
        'naranja' => '#ff8300',
        default => null,
    };
@endphp
<div class="kpi-escena {{ $tono === 'claro' ? 'kpi-escena-clara' : '' }} {{ $centro ? 'kpi-escena-centro' : '' }}" aria-hidden="true" @if($color) style="--escena-color: {{ $color }}" @endif>
    <svg viewBox="0 0 160 100" preserveAspectRatio="xMaxYMid meet">
        @switch($tipo)
            @case('servidores')
                {{-- Rack: tres servidores con luces que parpadean a destiempo --}}
                @foreach([12, 40, 68] as $i => $y)
                    <rect class="t" x="62" y="{{ $y }}" width="84" height="20" rx="3" />
                    <path class="t" d="M100 {{ $y + 7 }}h36M100 {{ $y + 13 }}h28" />
                    @foreach([0, 1, 2] as $j)
                        <circle class="kpi-led" cx="{{ 71 + $j * 8 }}" cy="{{ $y + 10 }}" r="2.2" style="animation-delay: {{ ($i * 3 + $j) * 0.37 }}s" />
                    @endforeach
                @endforeach
                <path class="t" d="M56 8v84M152 8v84" />
                @break

            @case('nube')
                {{-- Nube conectada a nodos; los datos viajan por las líneas --}}
                <path class="t" d="M74 64h58a15 15 0 0 0 1-30a21 21 0 0 0-40-7a15 15 0 0 0-19 37z" />
                @foreach([[100, 64, 82, 92], [118, 64, 140, 90], [74, 50, 40, 30], [132, 38, 156, 14], [90, 30, 70, 8]] as $i => [$x1, $y1, $x2, $y2])
                    <path class="t kpi-flujo" d="M{{ $x1 }} {{ $y1 }}L{{ $x2 }} {{ $y2 }}" style="animation-delay: -{{ $i * 0.6 }}s" />
                    <circle class="kpi-nodo" cx="{{ $x2 }}" cy="{{ $y2 }}" r="2.6" style="animation-delay: {{ $i * 0.5 }}s" />
                @endforeach
                @break

            @case('reloj')
                {{-- Reloj de arena con arena que cae y un engrane que gira despacio --}}
                <path class="t" d="M72 12h36M72 88h36M76 12c0 22 28 26 28 38s-28 16-28 38M104 12c0 22-28 26-28 38s28 16 28 38" />
                <path class="t" d="M80 80q10-8 20 0" />
                @foreach([0, 1, 2] as $i)
                    <circle class="kpi-arena" cx="90" cy="52" r="1.3" style="animation-delay: {{ $i * 0.7 }}s" />
                @endforeach
                <g class="kpi-gira">
                    <circle class="t" cx="134" cy="66" r="9" />
                    <circle class="t" cx="134" cy="66" r="3.5" />
                    @foreach(range(0, 7) as $k)
                        <path class="t" d="M{{ 134 + 9 * cos($k * M_PI / 4) }} {{ 66 + 9 * sin($k * M_PI / 4) }}L{{ 134 + 13 * cos($k * M_PI / 4) }} {{ 66 + 13 * sin($k * M_PI / 4) }}" />
                    @endforeach
                </g>
                @break

            @case('molecula')
                {{-- Nodo central (el NOC) consultando a sus equipos por SNMP; la red gira muy despacio --}}
                <g class="kpi-gira" style="animation-duration: 60s">
                    @foreach(range(0, 5) as $k)
                        @php $x = 110 + 34 * cos($k * M_PI / 3); $y = 50 + 34 * sin($k * M_PI / 3); @endphp
                        <path class="t kpi-flujo" d="M110 50L{{ $x }} {{ $y }}" style="animation-delay: -{{ $k * 0.5 }}s" />
                        <circle class="t" cx="{{ $x }}" cy="{{ $y }}" r="5" />
                    @endforeach
                    <path class="t" d="M110 38l10.4 6v12L110 62l-10.4-6V44z" />
                </g>
                @break

            @case('reinicio')
                {{-- Flechas de reinicio girando y un pulso que se expande --}}
                <circle class="t kpi-onda" cx="112" cy="50" r="26" />
                <g class="kpi-gira" style="animation-duration: 14s">
                    <path class="t" d="M92 50a20 20 0 0 1 34-14l6 6M132 30v12h-12M132 50a20 20 0 0 1-34 14l-6-6M92 70V58h12" />
                </g>
                @break

            @case('escudo')
                {{-- Escudo con candado y una línea de escaneo que lo recorre --}}
                <path class="t" d="M112 10l30 10v24c0 22-14 36-30 44c-16-8-30-22-30-44V20z" />
                <rect class="t" x="100" y="46" width="24" height="18" rx="3" />
                <path class="t" d="M104 46v-6a8 8 0 0 1 16 0v6" />
                <path class="t kpi-barrido" d="M86 18h52" />
                @break

            @case('trafico')
                {{-- Haces de datos que fluyen (tráfico) --}}
                @foreach([[0, 92, 60, 30, 160, 20, 3.2], [0, 98, 70, 44, 160, 34, 2.6], [10, 100, 80, 58, 160, 50, 3.8], [30, 100, 96, 70, 160, 66, 2.9], [60, 100, 110, 80, 160, 80, 4.4]] as $i => [$x0, $y0, $cx, $cy, $x1, $y1, $dur])
                    <path class="t kpi-flujo" d="M{{ $x0 }} {{ $y0 }}Q{{ $cx }} {{ $cy }} {{ $x1 }} {{ $y1 }}" style="animation-duration: {{ $dur }}s; stroke-dasharray: 2 9" />
                @endforeach
                @break

            @case('red')
                {{-- Constelación de sitios con un "ping" que sale del centro --}}
                @php $puntos = [[96, 22], [120, 16], [142, 30], [150, 56], [132, 78], [108, 86], [86, 70], [80, 44], [114, 48]]; @endphp
                @foreach([[0, 1], [1, 2], [2, 3], [3, 4], [4, 5], [5, 6], [6, 7], [7, 0], [8, 0], [8, 2], [8, 4], [8, 6]] as [$a, $b])
                    <path class="t" style="opacity: .55" d="M{{ $puntos[$a][0] }} {{ $puntos[$a][1] }}L{{ $puntos[$b][0] }} {{ $puntos[$b][1] }}" />
                @endforeach
                @foreach($puntos as $i => [$x, $y])
                    <circle class="kpi-nodo" cx="{{ $x }}" cy="{{ $y }}" r="{{ $i === 8 ? 3 : 2 }}" style="animation-delay: {{ $i * 0.45 }}s" />
                @endforeach
                <circle class="t kpi-onda" cx="114" cy="48" r="20" />
                @break

            @case('ondas')
                {{-- Dos ondas que se desplazan (la señal y su variación) --}}
                <g class="kpi-desliza">
                    <path class="t" d="M0 56c10-18 30-18 40 0s30 18 40 0 30-18 40 0 30 18 40 0 30-18 40 0 30 18 40 0" />
                    <path class="t" style="opacity: .55" d="M0 62c10-10 30-10 40 0s30 10 40 0 30-10 40 0 30 10 40 0 30-10 40 0 30 10 40 0" />
                </g>
                @break
        @endswitch
    </svg>
</div>
