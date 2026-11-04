@extends('layouts.app')

@section('title', 'Avisos de Telegram')
@section('refresco', '30')

@section('styles')
<style>
    /* Burbujas como en Telegram: fondo del chat, globo blanco (oscuro en tema oscuro), hora y palomitas abajo a la derecha */
    .tg-chat { padding: 14px; border-radius: var(--nm-radius-lg); background: #dfe7ee; display: flex; flex-direction: column; gap: 10px; }
    .dark .tg-chat { background: #0e1621; }
    .tg-globo { position: relative; max-width: 560px; border-radius: 14px 14px 14px 4px; background: #fff; color: #0f1419; box-shadow: 0 1px 1px rgb(0 0 0 / .12); overflow: hidden; }
    .dark .tg-globo { background: #182533; color: #e9eef3; }
    .tg-globo img { display: block; width: 100%; max-height: 300px; object-fit: contain; background: #fff; }
    .tg-texto { padding: 8px 12px 4px; font-size: 14px; line-height: 1.42; word-wrap: break-word; }
    .tg-texto b { font-weight: 700; }
    .tg-texto code { font-family: ui-monospace, Consolas, monospace; font-size: 13px; color: #c2185b; }
    .dark .tg-texto code { color: #f48fb1; }
    .tg-texto a { color: #168acd; } .dark .tg-texto a { color: #6ab3f3; }
    .tg-pie { display: flex; align-items: center; justify-content: flex-end; gap: 6px; padding: 0 10px 6px; font-size: 11.5px; color: #8a9aa9; }
    .tg-bot { padding: 6px 12px 0; font-size: 13px; font-weight: 700; color: #2f8fd8; } .dark .tg-bot { color: #6ab3f3; }
    .tg-error { padding: 6px 12px; font-size: 12px; font-weight: 600; color: #b91c1c; background: #fee2e2; } .dark .tg-error { color: #fca5a5; background: rgb(127 29 29 / .45); }
    .tg-grupo { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; padding: 1px 7px; border-radius: 9999px; }
    .tg-g-redes { color: #1d4ed8; background: #dbeafe; } .dark .tg-g-redes { color: #93c5fd; background: rgb(30 58 138 / .5); }
    .tg-g-servidores { color: #7e22ce; background: #f3e8ff; } .dark .tg-g-servidores { color: #d8b4fe; background: rgb(88 28 135 / .5); }
    .tg-g-todo, .tg-g-otro { color: var(--nm-text-muted); background: var(--nm-sunken); }
    .tg-filtro { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; border: 1px solid var(--nm-border); color: var(--nm-text-muted); background: var(--nm-surface); }
    .tg-filtro.activo { color: #fff; background: var(--nm-primary); border-color: var(--nm-primary); }
</style>
@endsection

@section('content')
@php
    // Solo las etiquetas que arma el propio NOC (Telegram usa este subconjunto de HTML); los saltos de línea se respetan
    $html = fn ($t) => nl2br(str_replace('<a ', '<a target="_blank" rel="noopener" ', strip_tags($t, '<b><i><code><a>')), false);
    $nombreGrupo = ['redes' => 'Grupo de redes', 'servidores' => 'Grupo de servidores', 'todo' => 'Grupo único', 'otro' => 'Chat anterior'];
    $configurado = $cfg && $cfg->telegram_chat_id;
    $dosGrupos = $configurado && trim((string) $cfg->telegram_chat_id_servidores) !== '' && $cfg->telegram_chat_id_servidores !== $cfg->telegram_chat_id;
@endphp
<div class="flex flex-col space-y-4 pb-6">

    {{-- Encabezado y pestañas (las mismas de Alertas) --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-lg font-black" style="color: var(--nm-text)">Alertas</h1>
            <nav class="inline-flex gap-[2px] p-[2px] rounded-[10px]" style="background: var(--nm-sunken); border: 1px solid var(--nm-border)" aria-label="Vistas de alertas">
                @foreach(['activas' => 'Activas', 'historial' => 'Historial'] as $clave => $texto)
                    <a href="{{ route('alertas.index', ['pestana' => $clave]) }}" class="inline-flex items-center h-8 px-4 rounded-lg text-[13px] font-semibold transition" style="color: var(--nm-text-muted);">{{ $texto }}</a>
                @endforeach
                <a href="{{ route('alertas.telegram') }}" aria-current="page" class="inline-flex items-center h-8 px-4 rounded-lg text-[13px] font-semibold transition"
                   style="color:#fff; background: var(--nm-metal-sheen), var(--nm-metal-azure);">Telegram</a>
            </nav>
        </div>
        <p class="text-xs" style="color: var(--nm-text-muted)">Avisos que el NOC envía a los grupos de Telegram.</p>
    </div>

    {{-- Estado de la configuración --}}
    <div class="floating-card px-5 py-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px]" style="color: var(--nm-text)">
        @if(!$configurado)
            <span><b class="text-amber-600">Telegram no está configurado:</b> no se envían avisos.</span>
        @else
            <span>Envío: <b class="{{ $cfg->telegram_activo ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600' }}">{{ $cfg->telegram_activo ? 'activado' : 'desactivado' }}</b></span>
            <span>{{ $dosGrupos ? 'Dos grupos: redes y servidores' : 'Un solo grupo para todo' }}</span>
            <span>Se envían: <b>{{ $cfg->telegram_severidad_min === 'advertencia' ? 'críticas y advertencias' : 'solo críticas' }}</b>{{ $cfg->telegram_resueltas ? ' · y cuando se resuelven' : '' }}{{ $cfg->telegram_imagenes ? ' · con tarjeta resumen' : '' }}</span>
        @endif
        <span>Últimas 24 h: <b>{{ $enviados24h }}</b> enviados{!! $fallidos24h ? ' · <b class="text-red-600">' . $fallidos24h . ' fallidos</b>' : '' !!}</span>
        @if(strtolower((string) auth()->user()?->role) === 'admin')
            <a href="{{ route('settings.index', ['section' => 'general']) }}" class="ml-auto text-xs font-bold hover:underline" style="color: var(--nm-primary)">Configurar en Ajustes →</a>
        @endif
    </div>

    {{-- Por grupo: sus alarmas activas (cuáles se envían y por qué no las otras) y cómo se ve el aviso --}}
    <div class="floating-card p-5">
        @php $filtro = ($cfg?->telegram_severidad_min ?? 'critica') === 'advertencia' ? 'críticas y advertencias' : 'solo críticas'; @endphp
        <h2 class="text-[11.5px] font-extrabold uppercase tracking-wider mb-1" style="color: var(--nm-text-muted)">Alarmas activas por grupo</h2>
        <p class="text-xs mb-3" style="color: var(--nm-text-muted)">Qué alarmas activas se avisan en cada grupo según el filtro de severidad («{{ $filtro }}») y el formato del aviso.</p>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($vistas as $sector => $v)
                <div>
                    <p class="mb-1.5 flex items-center gap-2">
                        <span class="tg-grupo tg-g-{{ $dosGrupos ? $sector : 'todo' }}">{{ $dosGrupos ? $nombreGrupo[$sector] : 'Grupo único' }}</span>
                        <span class="text-[11px]" style="color: var(--nm-text-muted)">{{ $sector === 'redes' ? 'switches, firewalls, routers, APs, teléfonos' : 'servidores, ESXi, VMs, datastores, sitios web' }}</span>
                    </p>
                    @if($v['activas']->isEmpty())
                        <p class="text-sm py-2" style="color: var(--nm-text-muted)">Sin alarmas activas.</p>
                    @else
                        <div class="max-h-64 overflow-y-auto mb-3 rounded-xl border" style="border-color: var(--nm-border)">
                            @foreach($v['activas'] as $x)
                                @php $a = $x['alarma']; @endphp
                                <div class="flex items-start gap-2 px-3 py-1.5 border-b last:border-b-0 text-[12.5px]" style="border-color: var(--nm-border); color: var(--nm-text)">
                                    <span class="mt-0.5 shrink-0">{{ $a->severidad === 'critica' ? '🔴' : '🟠' }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="font-semibold">{{ $a->titulo }}</span>
                                        <span class="block text-[11px]" style="color: var(--nm-text-muted)">
                                            @if($a->notificada_telegram_at)
                                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ Enviada {{ \Carbon\Carbon::parse($a->notificada_telegram_at)->format('d/m H:i') }}</span>
                                            @elseif($x['se_envia'] && $a->created_at?->gt(now()->subMinutes(20)))
                                                {{-- Recién registrada: se avisa en el siguiente ciclo (o a los 15 min si es intermitente) --}}
                                                <span class="font-semibold" style="color: var(--nm-primary)">Se enviará en el siguiente ciclo</span>
                                            @elseif($x['se_envia'])
                                                {{-- Telegram solo avisa las alarmas al empezar: esta empezó cuando no estaba activo o falló el envío --}}
                                                <span class="font-semibold" style="color: var(--nm-text-muted)">No enviada: ya estaba activa antes de activar Telegram o falló el envío</span>
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400 font-semibold">No se envía: advertencia (filtro: {{ $filtro }})</span>
                                            @endif
                                            · desde {{ $a->fecha_inicio?->format('d/m H:i') }}
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if($v['alarma'])
                        <p class="text-[11px] font-bold uppercase tracking-wider mb-1" style="color: var(--nm-text-muted)">Formato del aviso</p>
                        <div class="tg-chat">
                            <div class="tg-globo">
                                @if($cfg?->telegram_imagenes)
                                    <img src="{{ route('alertas.telegram.grafica', $v['alarma']->id) }}" alt="Tarjeta resumen de la alarma" loading="lazy" onerror="this.remove()">
                                @endif
                                <div class="tg-bot">{{ $botNombre }}</div>
                                <div class="tg-texto">{!! $html($v['texto']) !!}</div>
                                <div class="tg-pie">{{ now()->format('H:i') }}</div>
                            </div>
                        </div>
                    @elseif($v['activas']->isNotEmpty())
                        <p class="text-xs" style="color: var(--nm-text-muted)">Ninguna alarma activa de este grupo cumple el filtro «{{ $filtro }}».</p>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="text-[11px] mt-3" style="color: var(--nm-text-muted)">
            Las alarmas se revisan cada minuto. La CPU alta de una VM se registra como advertencia y pasa a crítica después de 60 minutos.
        </p>
    </div>

    {{-- Avisos enviados --}}
    <div class="floating-card p-5" data-refrescar="telegram-enviados">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h2 class="text-[11.5px] font-extrabold uppercase tracking-wider mr-2" style="color: var(--nm-text-muted)">Avisos enviados · {{ $mensajes->count() }}</h2>
            @foreach([null => 'Todos', 'redes' => 'Redes', 'servidores' => 'Servidores'] as $clave => $texto)
                <a href="{{ route('alertas.telegram', array_filter(['grupo' => $clave])) }}" class="tg-filtro {{ $grupo === ($clave ?: null) ? 'activo' : '' }}">{{ $texto }}</a>
            @endforeach
            <span class="ml-auto text-[11px]" style="color: var(--nm-text-muted)">Últimos 30 días</span>
        </div>
        @if($mensajes->isEmpty())
            <p class="text-sm py-2" style="color: var(--nm-text-muted)">Sin avisos enviados{{ $grupo ? ' a este grupo' : '' }} en los últimos 30 días.</p>
        @else
            <div class="tg-chat max-h-[900px] overflow-y-auto">
                @foreach($mensajes as $m)
                    @php $fecha = \Carbon\Carbon::parse($m->created_at); @endphp
                    <div>
                        <p class="mb-1 flex items-center gap-2 text-[11px]" style="color: #6b7a88">
                            <span class="tg-grupo tg-g-{{ $m->grupo }}">{{ $nombreGrupo[$m->grupo] }}</span>
                            {{ $fecha->format('d/m/Y') }}
                        </p>
                        <div class="tg-globo">
                            @if($m->con_imagen)
                                <img src="{{ route('alertas.telegram.imagen', $m->id) }}" alt="Imagen enviada con el aviso" loading="lazy" onerror="this.remove()">
                            @endif
                            <div class="tg-bot">{{ $botNombre }}</div>
                            <div class="tg-texto">{!! $html($m->texto) !!}</div>
                            @if(!$m->ok)<div class="tg-error">No llegó: {{ $m->error }}</div>@endif
                            <div class="tg-pie">{{ $fecha->format('H:i') }} <span title="{{ $m->ok ? 'Enviado' : 'Falló' }}">{{ $m->ok ? '✓✓' : '✗' }}</span></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
