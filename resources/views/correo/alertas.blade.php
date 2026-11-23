{{-- Correo de alertas del NOC. Tablas y estilos en línea: Outlook y Gmail ignoran las hojas de estilo y el CSS moderno. --}}
@php
    $nuevas = $nuevas ?? []; $recordar = $recordar ?? []; $resueltas = $resueltas ?? []; $secciones = $secciones ?? [];
    $rojo = '#dc2626'; $ambar = '#d97706'; $verde = '#059669'; $teal = '#0f766e'; $gris = '#64748b'; $texto = '#0f172a'; $borde = '#e2e8f0';
    $sev = fn ($f) => $f['critica'] ? ['CRÍTICA', $rojo] : ['ADVERTENCIA', $ambar];
    $equipo = fn ($f) => (string) ($f['equipo_txt'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $asunto }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family: Segoe UI, Arial, Helvetica, sans-serif; color: {{ $texto }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;">
<tr><td align="center" style="padding: 20px 10px;">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%; max-width:640px; background:#ffffff; border:1px solid {{ $borde }}; border-radius:10px;">

    {{-- Encabezado --}}
    <tr><td style="padding: 16px 22px; border-bottom: 1px solid {{ $borde }};">
        <div style="font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: {{ $gris }};">NOC Network Monitor · {{ $grupo }}</div>
        <div style="font-size: 17px; font-weight: 800; margin-top: 4px; line-height: 1.35;">{{ preg_replace('/^\[[^\]]+\]\s*/', '', $asunto) }}</div>
    </td></tr>

    {{-- Alarmas nuevas --}}
    @if($nuevas)
    <tr><td style="padding: 16px 22px 4px;">
        <div style="font-size: 13px; font-weight: 800; color: {{ $rojo }}; text-transform: uppercase; letter-spacing: .05em;">
            {{ $nuevasTotal === 1 ? 'Alarma nueva' : $nuevasTotal . ' alarmas nuevas' }}
        </div>
    </td></tr>
    @if($detalle)
        @foreach($nuevas as $f)
        @php [$etq, $color] = $sev($f); @endphp
        <tr><td style="padding: 8px 22px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid {{ $borde }}; border-left: 4px solid {{ $color }}; border-radius: 6px;">
            <tr><td style="padding: 12px 14px; font-size: 13px; line-height: 1.5;">
                <div>
                    <span style="display:inline-block; font-size: 10.5px; font-weight: 800; color: #fff; background: {{ $color }}; padding: 1px 7px; border-radius: 4px;">{{ $etq }}</span>
                    @if($f['prioritaria'])<span style="display:inline-block; font-size: 10.5px; font-weight: 800; color: {{ $teal }}; border: 1px solid {{ $teal }}; padding: 0 6px; border-radius: 4px; margin-left: 4px;">RED PRIORITARIA</span>@endif
                </div>
                <div style="font-size: 15px; font-weight: 800; margin-top: 6px;">{{ $f['titulo'] }}</div>
                @if($equipo($f))<div><b>Equipo:</b> {{ $equipo($f) }}@if($f['ubicacion']) · {{ $f['ubicacion'] }}@endif</div>@endif
                @if($f['mensaje'])<div style="color: {{ $gris }}; margin-top: 2px;">{{ $f['mensaje'] }}</div>@endif
                @if($f['causa'])<div style="margin-top: 6px;"><b>Causa probable:</b> {{ $f['causa'] }}</div>@endif
                @if($f['impacto'])<div><b>Impacto:</b> {{ $f['impacto'] }}</div>@endif
                @if($f['accion'])<div><b>Acción sugerida:</b> {{ $f['accion'] }}</div>@endif
                <div style="color: {{ $gris }}; font-size: 12px; margin-top: 6px;">
                    Desde {{ $f['inicio'] }}@if($f['enlace']) · <a href="{{ $f['enlace'] }}" style="color: #2563eb;">Ver equipo en el NOC</a>@endif
                </div>
            </td></tr>
            </table>
        </td></tr>
        @endforeach
    @else
        <tr><td style="padding: 8px 22px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 12.5px;">
                <tr style="background: #f8fafc;">
                    <th align="left" style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; color: {{ $gris }};">Severidad</th>
                    <th align="left" style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; color: {{ $gris }};">Alarma</th>
                    <th align="left" style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; color: {{ $gris }};">Desde</th>
                </tr>
                @foreach($nuevas as $f)
                @php [$etq, $color] = $sev($f); @endphp
                <tr>
                    <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; font-weight: 800; color: {{ $color }}; white-space: nowrap;">{{ $etq }}</td>
                    <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }};">
                        @if($f['prioritaria'])<b style="color: {{ $teal }};">[PRIORITARIA]</b> @endif{{ $f['titulo'] }}
                        @if($equipo($f))<div style="color: {{ $gris }};">{{ $equipo($f) }}</div>@endif
                    </td>
                    <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; white-space: nowrap; color: {{ $gris }};">{{ $f['inicio'] }}</td>
                </tr>
                @endforeach
            </table>
            @if($nuevasTotal > count($nuevas))<div style="font-size: 12px; color: {{ $gris }}; margin-top: 6px;">… y {{ $nuevasTotal - count($nuevas) }} más (ver Alertas en el NOC).</div>@endif
        </td></tr>
    @endif
    @endif

    {{-- Recordatorio de la red prioritaria --}}
    @if($recordar)
    <tr><td style="padding: 16px 22px 4px;">
        <div style="font-size: 13px; font-weight: 800; color: {{ $teal }}; text-transform: uppercase; letter-spacing: .05em;">Recordatorio · red prioritaria ({{ \App\Support\RedesPrioritarias::texto() }})</div>
        <div style="font-size: 12.5px; color: {{ $gris }}; margin-top: 2px;">{{ $recordarTotal === 1 ? 'Sigue sin resolverse 1 falla:' : "Siguen sin resolverse {$recordarTotal} fallas:" }}</div>
    </td></tr>
    <tr><td style="padding: 6px 22px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 12.5px;">
            @foreach($recordar as $f)
            @php [$etq, $color] = $sev($f); @endphp
            <tr>
                <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; font-weight: 800; color: {{ $color }}; white-space: nowrap; width: 1%;">{{ $etq }}</td>
                <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }};">
                    {{ $f['titulo'] }}@if($equipo($f))<span style="color: {{ $gris }};"> · {{ $equipo($f) }}</span>@endif
                </td>
                <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; white-space: nowrap;">lleva <b>{{ $f['duracion'] }}</b></td>
            </tr>
            @endforeach
        </table>
        @if($recordarTotal > count($recordar))<div style="font-size: 12px; color: {{ $gris }}; margin-top: 6px;">… y {{ $recordarTotal - count($recordar) }} más.</div>@endif
    </td></tr>
    @endif

    {{-- Resueltas --}}
    @if($resueltas)
    <tr><td style="padding: 16px 22px 4px;">
        <div style="font-size: 13px; font-weight: 800; color: {{ $verde }}; text-transform: uppercase; letter-spacing: .05em;">
            {{ $resueltasTotal === 1 ? 'Alarma resuelta' : $resueltasTotal . ' alarmas resueltas' }}
        </div>
    </td></tr>
    <tr><td style="padding: 6px 22px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 12.5px;">
            @foreach($resueltas as $f)
            <tr>
                <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }};">
                    {{ $f['titulo'] }}@if($equipo($f))<span style="color: {{ $gris }};"> · {{ $equipo($f) }}</span>@endif
                </td>
                <td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; white-space: nowrap; color: {{ $gris }};">@if($f['duracion'])duró <b style="color: {{ $texto }};">{{ $f['duracion'] }}</b>@endif</td>
            </tr>
            @endforeach
        </table>
        @if($resueltasTotal > count($resueltas))<div style="font-size: 12px; color: {{ $gris }}; margin-top: 6px;">… y {{ $resueltasTotal - count($resueltas) }} más.</div>@endif
    </td></tr>
    @endif

    {{-- Secciones libres (recaudadoras, prueba) --}}
    @foreach($secciones as $s)
    <tr><td style="padding: 16px 22px 6px;">
        <div style="font-size: 13px; font-weight: 800; color: {{ $s['color'] }}; text-transform: uppercase; letter-spacing: .05em;">{{ $s['titulo'] }}</div>
        @if(!empty($s['nota']))<div style="font-size: 12.5px; color: {{ $gris }}; margin-top: 2px;">{{ $s['nota'] }}</div>@endif
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 13px; margin-top: 6px;">
            @foreach($s['lineas'] as $l)
            <tr><td style="padding: 6px 8px; border-bottom: 1px solid {{ $borde }}; border-left: 3px solid {{ $s['color'] }}; line-height: 1.45;">{{ $l }}</td></tr>
            @endforeach
        </table>
    </td></tr>
    @endforeach

    {{-- Pie --}}
    <tr><td style="padding: 18px 22px; border-top: 1px solid {{ $borde }}; font-size: 11.5px; color: {{ $gris }}; line-height: 1.5;">
        Enviado {{ now()->format('d/m/Y H:i') }} por el NOC Network Monitor.
        @if(!empty($enlaceModulo))<a href="{{ $enlaceModulo }}" style="color: #2563eb;">Abrir Recaudadoras</a> ·@endif
        @if(!empty($enlaceNoc))<a href="{{ $enlaceNoc }}" style="color: #2563eb;">Ver Alertas en el NOC</a>.@endif
        <br>Para dejar de recibir estos correos, pide que te quiten de la lista en Ajustes → Notificaciones.
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
