@extends('layouts.app')

@section('styles')
    <style>
        /* Paleta Institucional */
        :root {
            --institucional-blue: #3b5998;
            --institucional-slate: #5c8096;
            --institucional-orange: #f26419;
        }

        /* Faceplate 19" Rackmount Switch */
        .faceplate-chassis {
            background: linear-gradient(180deg, #1e2229 0%, #16191e 50%, #0d1013 100%);
            border: 2px solid #2d333b;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.1), 
                        inset 0 -2px 6px rgba(0, 0, 0, 0.8), 
                        0 12px 30px rgba(0, 0, 0, 0.25);
            position: relative;
        }
        .rack-ear {
            width: 14px;
            background: #252b33;
            border: 1px solid #3d4652;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0;
        }
        .rack-screw {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #0f1216;
            border: 1px solid #4a5568;
            box-shadow: inset 0 0 2px rgba(0,0,0,0.9);
        }

        /* Chassis Finish Variants */
        .chassis-nexus {
            background: linear-gradient(180deg, #13171f 0%, #0d1017 45%, #05070a 100%) !important;
            border-color: #2b394b !important;
            box-shadow: inset 0 1px 2px rgba(56, 189, 248, 0.2), inset 0 -2px 6px rgba(0, 0, 0, 0.9), 0 12px 30px rgba(0, 0, 0, 0.35) !important;
        }
        .chassis-catalyst {
            background: linear-gradient(180deg, #181d26 0%, #12151c 50%, #0b0d12 100%) !important;
            border-color: #2e3e52 !important;
            box-shadow: inset 0 1px 2px rgba(59, 89, 152, 0.25), inset 0 -2px 6px rgba(0, 0, 0, 0.9), 0 12px 30px rgba(0, 0, 0, 0.35) !important;
        }
        .chassis-router {
            background: linear-gradient(180deg, #1a2027 0%, #14191f 50%, #0b0e12 100%) !important;
            border-color: #3b4754 !important;
            box-shadow: inset 0 1px 2px rgba(242, 100, 25, 0.2), inset 0 -2px 6px rgba(0, 0, 0, 0.9), 0 12px 30px rgba(0, 0, 0, 0.35) !important;
        }
        .chassis-access {
            background: linear-gradient(180deg, #1e2229 0%, #16191e 50%, #0d1013 100%) !important;
            border-color: #2d333b !important;
        }

        /* RJ45 Port Matrix - Pairs in Columns (Odd Top, Even Bottom) */
        .port-matrix {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .port-pair-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .port-block-separator {
            width: 2px;
            height: 94px;
            background: rgba(255, 255, 255, 0.08);
            margin: 0 4px;
            border-radius: 1px;
        }
        .rj45-socket {
            width: 38px;
            height: 38px;
            background: #0f1115;
            border: 1.5px solid #2f3640;
            border-radius: 5px;
            position: relative;
            cursor: pointer;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .rj45-socket:hover {
            border-color: #3b5998;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 89, 152, 0.35);
            z-index: 10;
        }
        .rj45-socket.active-selected {
            border-color: #f26419 !important;
            box-shadow: 0 0 0 2px rgba(242, 100, 25, 0.4), 0 4px 14px rgba(242, 100, 25, 0.3) !important;
        }
        .rj45-clip-notch {
            width: 16px;
            height: 6px;
            background: #1a1e24;
            border-radius: 1px;
            margin-bottom: 2px;
            border: 1px solid #2a313a;
        }
        .rj45-pins {
            display: flex;
            gap: 1.5px;
            margin-top: 1px;
        }
        .rj45-pin {
            width: 1.5px;
            height: 5px;
            background: #d4af37;
            border-radius: 0.5px;
        }

        /* SFP+ Optical Cage */
        .sfp-cage {
            width: 44px;
            height: 40px;
            background: linear-gradient(135deg, #2c323b 0%, #1c2127 100%);
            border: 2px solid #525f70;
            border-radius: 4px;
            position: relative;
            cursor: pointer;
            transition: all 0.18s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.6);
        }
        .sfp-cage:hover {
            border-color: #3b5998;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(59, 89, 152, 0.4);
            z-index: 10;
        }
        .sfp-cage.active-selected {
            border-color: #f26419 !important;
            box-shadow: 0 0 0 2px rgba(242, 100, 25, 0.4), 0 4px 14px rgba(242, 100, 25, 0.3) !important;
        }
        .sfp-latch {
            width: 28px;
            height: 10px;
            border: 1px solid #718096;
            background: #111418;
            border-radius: 2px;
        }

        /* LEDs */
        .led-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            position: absolute;
            transition: all 0.3s;
        }
        .led-top-left {
            top: 3px;
            left: 3px;
        }
        .led-top-right {
            top: 3px;
            right: 3px;
        }
        .led-link-up {
            background: #10b981;
            box-shadow: 0 0 6px #10b981, 0 0 10px rgba(16, 185, 129, 0.7);
        }
        .led-link-down {
            background: #2d3748;
        }
        .led-link-err {
            background: #ef4444;
            box-shadow: 0 0 7px #ef4444, 0 0 12px rgba(239, 68, 68, 0.8);
            animation: pulseErr 1.4s infinite;
        }
        @keyframes pulseErr {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }
        .led-poe-active {
            background: #f59e0b;
            box-shadow: 0 0 5px #f59e0b;
        }

        /* Port numbering label */
        .port-num-label {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 9px;
            font-weight: 700;
            color: #718096;
            text-align: center;
            user-select: none;
        }

        /* Slide-over Port Drawer */
        .drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            z-index: 60;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .drawer-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }
        .port-drawer {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            box-shadow: -10px 0 35px rgba(0, 0, 0, 0.2);
            z-index: 70;
            transform: translateX(100%);
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }
        .port-drawer.open {
            transform: translateX(0);
        }
        .dark .port-drawer {
            background: #12161f;
            box-shadow: -10px 0 35px rgba(0, 0, 0, 0.7);
            color: #f1f5f9;
        }

        /* Custom Tabs */
        .nav-tab-btn {
            padding: 0.85rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: #64748b;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            border-top: none;
            border-left: none;
            border-right: none;
        }
        .nav-tab-btn:hover {
            color: #3b5998;
            background: rgba(59, 89, 152, 0.04);
        }
        .nav-tab-btn.active {
            color: #3b5998;
            border-bottom-color: #3b5998;
            background: rgba(59, 89, 152, 0.06);
        }
        .dark .nav-tab-btn {
            color: #94a3b8;
        }
        .dark .nav-tab-btn:hover {
            color: #60a5fa;
            background: rgba(96, 165, 250, 0.08);
        }
        .dark .nav-tab-btn.active {
            color: #60a5fa;
            border-bottom-color: #60a5fa;
            background: rgba(96, 165, 250, 0.12);
        }
        .tab-pane {
            display: none;
            animation: fadeInTab 0.2s ease;
        }
        .tab-pane.active {
            display: block;
        }
        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Metric cards */
        .telemetry-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.85rem;
            padding: 1.15rem;
        }
        .dark .telemetry-card {
            background: #161b26;
            border-color: rgba(255, 255, 255, 0.08);
        }
    </style>
@endsection

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-12">

    {{-- ==================== 1. HERO HEADER ==================== --}}
    <div class="bg-white dark:bg-[#12161f] rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-slate-800 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
            {{-- Render Oficial de Hardware según el Modelo Real --}}
            <div class="w-36 sm:w-44 h-24 sm:h-28 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-950 p-2 border border-slate-700/60 shadow-lg flex-shrink-0 flex flex-col items-center justify-between relative overflow-hidden group">
                <div class="absolute inset-0 bg-radial from-blue-500/10 to-transparent pointer-events-none"></div>
                <div class="w-full flex items-center justify-between px-1.5 pt-0.5">
                    <span class="text-[8px] font-mono font-bold text-slate-400 uppercase tracking-widest">{{ $deviceHardware['rol'] }}</span>
                    <span class="text-[8px] font-mono font-bold text-emerald-400 uppercase">ONLINE</span>
                </div>
                <div class="w-full flex-1 flex items-center justify-center px-1">
                    <img src="{{ asset($deviceHardware['image']) }}" alt="{{ $chasis->model_name ?? $dispositivo->nombre }}" class="max-h-16 w-full object-contain drop-shadow-md group-hover:scale-105 transition-transform duration-300">
                </div>
                <div class="w-full text-center pb-0.5">
                    <span class="text-[8px] font-mono text-slate-400 truncate block px-1">{{ $deviceHardware['factor_forma'] }}</span>
                </div>
            </div>

            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 animate-ping"></span>
                    <h1 class="text-2xl lg:text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ $dispositivo->nombre }}</h1>
                    <span class="text-xs font-extrabold uppercase px-2.5 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/60 text-[#3b5998] dark:text-blue-400 border border-blue-200 dark:border-blue-800/40">
                        {{ $chasis->model_name ?? 'Cisco Catalyst 9300' }}
                    </span>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono">
                        {{ $deviceHardware['factor_forma'] }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-slate-400 font-medium">
                    <span><strong>IP:</strong> <code class="text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 px-1.5 py-0.5 rounded font-mono">{{ $dispositivo->ip }}</code></span>
                    <span>•</span>
                    <span><strong>Ubicación:</strong> {{ $dispositivo->ubicacion }}</span>
                    <span>•</span>
                    <span><strong>Uptime:</strong> <span class="text-gray-700 dark:text-slate-300 font-semibold">{{ $chasis->uptime_str ?? '68d 14h 32m' }}</span></span>
                    <span>•</span>
                    <span><strong>OS:</strong> <span class="text-gray-700 dark:text-slate-300 font-semibold">{{ $chasis->os_version ?? 'Cisco IOS-XE 17.9.3a' }}</span></span>
                    <span>•</span>
                    <span><strong>Serial:</strong> <code class="text-gray-700 dark:text-slate-300 font-mono">{{ $chasis->serial_number ?? 'FOC2619L04X' }}</code></span>
                </div>
            </div>
        </div>

        {{-- Hero Quick Counters --}}
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 bg-gray-50 px-3.5 py-2 rounded-xl border border-gray-200">
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm"></div>
                <div class="text-left">
                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Enlaces UP</p>
                    <p class="text-sm font-extrabold text-emerald-600">{{ $upPortsCount }} / {{ $totalPorts }}</p>
                </div>
            </div>

            @if($errDisabledCount > 0)
            <div class="flex items-center gap-2 bg-red-50 px-3.5 py-2 rounded-xl border border-red-200">
                <div class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></div>
                <div class="text-left">
                    <p class="text-[10px] text-red-500 uppercase font-bold tracking-wider">Err-Disabled</p>
                    <p class="text-sm font-extrabold text-red-600">{{ $errDisabledCount }}</p>
                </div>
            </div>
            @endif

            <div class="flex items-center gap-2 bg-amber-50 px-3.5 py-2 rounded-xl border border-amber-200">
                <div class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-sm"></div>
                <div class="text-left">
                    <p class="text-[10px] text-amber-600 uppercase font-bold tracking-wider">PoE Total</p>
                    <p class="text-sm font-extrabold text-amber-700">{{ number_format($totalPoeWatts, 1) }} W</p>
                </div>
            </div>

            <a href="{{ route('dispositivos.pdf', $dispositivo->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#3b5998] hover:bg-[#2d4677] text-white text-xs font-bold transition shadow-sm" title="Descargar Ficha Técnica en PDF">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Reporte PDF
            </a>

            <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Dashboard
            </a>
        </div>
    </div>

    {{-- ==================== 2. PHYSICAL FACEPLATE (19" RACKMOUNT) ==================== --}}
    @php
        $rol = $deviceHardware['rol'];
        $haystack = strtolower($dispositivo->nombre . ' ' . ($chasis->model_name ?? '') . ' ' . ($dispositivo->modelo ?? ''));
        $isRouter = ($rol === 'ROUTER / EDGE') || str_contains($haystack, '1841') || str_contains($haystack, 'router') || str_contains($haystack, 'isr');
        $isNexus = ($rol === 'CORE / MODULAR') && !$isRouter;
        $isCatalyst = ($rol === 'DISTRIBUTION / CORE') && !$isRouter;

        $chassisClass = match(true) {
            $isRouter => 'chassis-router',
            $isNexus => 'chassis-nexus',
            $isCatalyst => 'chassis-catalyst',
            default => 'chassis-access'
        };
        $badgeBg = match(true) {
            $isRouter => 'bg-[#f26419]',
            $isNexus => 'bg-indigo-600',
            $isCatalyst => 'bg-[#3b5998]',
            default => 'bg-slate-700'
        };
    @endphp

    <div x-data="faceplateComponent()" class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
                <div class="px-3 py-1.5 rounded-lg {{ $badgeBg }} flex items-center justify-center text-white font-black text-xs shadow-sm font-mono tracking-wider">
                    {{ $isRouter ? 'ROUTER 1U' : $deviceHardware['factor_forma'] }}
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-gray-900 leading-tight">Panel Frontal Físico (Carrier-Grade Faceplate)</h2>
                    <p class="text-xs text-gray-500 font-medium">
                        {{ $chasis->model_name ?? $dispositivo->nombre }} · <span x-text="copperPorts.length"></span> Puertos de Cobre RJ45 / <span x-text="sfpPorts.length"></span> Puertos SFP Ópticos (<span x-text="interfaces.length"></span> Interfaces Totales Gestionadas) · Haz clic en cualquier puerto para abrir la telemetría.
                    </p>
                </div>
            </div>

            {{-- Faceplate Legend --}}
            <div class="hidden md:flex items-center gap-4 text-xs font-semibold text-gray-500 bg-gray-50 px-3 py-1.5 rounded-xl border border-gray-200">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-sm"></span>
                    <span>Link Up</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-red-500 shadow-sm"></span>
                    <span>Err-Disabled</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                    <span>Link Down</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-500 shadow-sm"></span>
                    <span>PoE Activo</span>
                </div>
            </div>
        </div>

        {{-- Physical Faceplate Chassis Container --}}
        <div class="faceplate-chassis {{ $chassisClass }} flex items-center justify-between gap-2 overflow-x-auto custom-scrollbar">
            {{-- Left Rack Ear --}}
            <div class="rack-ear">
                <div class="rack-screw"></div>
                <div class="rack-screw"></div>
            </div>

            {{-- Brand Logo & Status Panel Customized to Device --}}
            @if($isNexus)
                <div class="flex flex-col justify-between h-[100px] py-1 px-2 border-r border-cyan-900/40 pr-4 flex-shrink-0">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-cyan-400 shadow-[0_0_8px_#22d3ee]"></span>
                            <span class="text-xs font-black text-cyan-300 tracking-wider">CISCO NEXUS</span>
                        </div>
                        <p class="text-[9px] font-mono text-cyan-400/90 uppercase tracking-tight mt-0.5 truncate max-w-[125px]">{{ $chasis->model_name ?? 'NEXUS SWITCH' }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-cyan-400 shadow-sm animate-pulse"></span><span class="text-[8px] font-mono text-slate-400">SUP</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-slate-400">FAB</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-slate-400">FAN</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-slate-400">PWR</span></div>
                    </div>
                    <div class="text-[8px] text-cyan-500 font-mono">NX-OS • MGMT</div>
                </div>
            @elseif($isRouter)
                <div class="flex flex-col justify-between h-[100px] py-1 px-2 border-r border-orange-900/40 pr-4 flex-shrink-0">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-[#f26419] shadow-[0_0_6px_#f26419]"></span>
                            <span class="text-xs font-black text-orange-200 tracking-wider">CISCO ROUTER</span>
                        </div>
                        <p class="text-[9px] font-mono text-orange-300 uppercase tracking-tight mt-0.5 truncate max-w-[125px]">{{ $chasis->model_name ?? 'ISR ROUTER' }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm animate-pulse"></span><span class="text-[8px] font-mono text-gray-400">SYS</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">ACT</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">CF</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">PWR</span></div>
                    </div>
                    <div class="text-[8px] text-orange-400 font-mono">WAN • CONSOLE</div>
                </div>
            @elseif($isCatalyst)
                <div class="flex flex-col justify-between h-[100px] py-1 px-2 border-r border-blue-900/40 pr-4 flex-shrink-0">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-blue-500 shadow-[0_0_6px_#3b82f6]"></span>
                            <span class="text-xs font-black text-blue-200 tracking-wider">CATALYST 9300</span>
                        </div>
                        <p class="text-[9px] font-mono text-blue-300 uppercase tracking-tight mt-0.5 truncate max-w-[125px]">{{ $chasis->model_name ?? 'C9300 ENTERPRISE' }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm animate-pulse"></span><span class="text-[8px] font-mono text-gray-400">SYST</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">STAT</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">MAST</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">POE</span></div>
                    </div>
                    <div class="text-[8px] text-blue-400 font-mono">STACK • USB CON</div>
                </div>
            @else
                <div class="flex flex-col justify-between h-[100px] py-1 px-2 border-r border-gray-800 pr-4 flex-shrink-0">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-[#f26419]"></span>
                            <span class="text-xs font-black text-gray-200 tracking-wider">CISCO</span>
                        </div>
                        <p class="text-[9px] font-mono text-gray-400 uppercase tracking-tight mt-0.5 truncate max-w-[125px]">{{ $chasis->model_name ?? 'SWITCH GESTIONADO' }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm animate-pulse"></span><span class="text-[8px] font-mono text-gray-400">SYST</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">RPS</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">STAT</span></div>
                        <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 shadow-sm"></span><span class="text-[8px] font-mono text-gray-400">POE</span></div>
                    </div>
                    <div class="text-[8px] text-gray-500 font-mono">CON / MGMT</div>
                </div>
            @endif

            {{-- CENTER: DYNAMIC HARDWARE LAYOUT --}}
            <template x-if="isRouter">
                {{-- ==================== MORFOLOGÍA FÍSICA ROUTER (CISCO 1841 / ISR 4400) ==================== --}}
                <div class="flex-1 flex items-center justify-around px-4 gap-4">
                    {{-- Puertos Físicos Dinámicos desde la Base de Datos --}}
                    <div class="bg-[#0a0d13] p-3 rounded-xl border border-slate-700/60 shadow-inner flex flex-col items-center">
                        <span class="text-[8px] font-mono font-bold text-orange-400 uppercase tracking-wider mb-2">PUERTOS DE RED (ROUTER WAN/LAN)</span>
                        <div class="flex items-center gap-3">
                            <template x-for="(interfaz, index) in interfaces" :key="index">
                                <!-- Dibuja el puerto dinámicamente según sea tipo cobre (RJ45) o SFP -->
                                <div class="port-pair-column">
                                    <span class="port-num-label" :class="interfaz.is_sfp ? 'text-blue-300' : 'text-orange-300'" x-text="interfaz.short_name"></span>

                                    <!-- Si es puerto tipo Cobre (RJ45) -->
                                    <template x-if="!interfaz.is_sfp">
                                        <div class="rj45-socket port-element" 
                                             :id="'port-btn-' + interfaz.id"
                                             :class="{ 'active-selected': selectedPortId === interfaz.id }"
                                             @click="select(interfaz.id)" 
                                             :title="interfaz.name + ' • ' + (interfaz.alias || interfaz.name) + ' • ' + (interfaz.oper_status || 'down').toUpperCase()">
                                            <div class="led-indicator led-top-left" :class="{
                                                'led-link-err': interfaz.is_errdisabled,
                                                'led-link-up': !interfaz.is_errdisabled && interfaz.oper_status === 'up',
                                                'led-link-down': !interfaz.is_errdisabled && interfaz.oper_status !== 'up'
                                            }"></div>
                                            <template x-if="interfaz.is_poe">
                                                <div class="led-indicator led-top-right led-poe-active"></div>
                                            </template>
                                            <div class="rj45-clip-notch"></div>
                                            <div class="rj45-pins">
                                                <div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div>
                                                <div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Si es puerto tipo SFP (Fibra) -->
                                    <template x-if="interfaz.is_sfp">
                                        <div class="sfp-cage port-element" 
                                             :id="'port-btn-' + interfaz.id"
                                             :class="{ 'active-selected': selectedPortId === interfaz.id }"
                                             @click="select(interfaz.id)" 
                                             :title="interfaz.name + ' • ' + (interfaz.alias || interfaz.name) + ' • ' + (interfaz.oper_status || 'down').toUpperCase()">
                                            <div class="led-indicator led-top-left" :class="{
                                                'led-link-err': interfaz.is_errdisabled,
                                                'led-link-up': !interfaz.is_errdisabled && interfaz.oper_status === 'up',
                                                'led-link-down': !interfaz.is_errdisabled && interfaz.oper_status !== 'up'
                                            }"></div>
                                            <div class="sfp-latch"></div>
                                        </div>
                                    </template>

                                    <span class="text-[8px] font-mono text-slate-400 mt-1 uppercase" x-text="interfaz.oper_status"></span>
                                </div>
                            </template>

                            <template x-if="interfaces.length === 0">
                                <span class="text-xs text-slate-500 font-mono italic">Sin puertos físicos registrados</span>
                            </template>
                        </div>
                    </div>

                    {{-- Bahía de Gestión: Console RJ45 (Azul Cisco) y AUX --}}
                    <div class="bg-[#0a0d13] p-3 rounded-xl border border-slate-800 shadow-inner hidden md:flex flex-col items-center">
                        <span class="text-[8px] font-mono font-bold text-slate-400 uppercase tracking-wider mb-2">GESTIÓN LOCAL</span>
                        <div class="flex items-center gap-3">
                            <div class="port-pair-column">
                                <span class="port-num-label text-sky-400">CON</span>
                                <div class="w-[38px] h-[38px] bg-[#090c10] border-2 border-sky-500 rounded flex flex-col items-center justify-center cursor-default shadow-[0_0_8px_rgba(2,132,199,0.3)]" title="Puerto de Consola Serial RJ-45 (RS-232 9600-8-N-1)">
                                    <div class="w-3.5 h-1 bg-sky-950 rounded-sm mb-1 border border-sky-800"></div>
                                    <div class="flex gap-0.5">
                                        <div class="w-0.5 h-1.5 bg-amber-400 rounded-xs"></div>
                                        <div class="w-0.5 h-1.5 bg-amber-400 rounded-xs"></div>
                                        <div class="w-0.5 h-1.5 bg-amber-400 rounded-xs"></div>
                                        <div class="w-0.5 h-1.5 bg-amber-400 rounded-xs"></div>
                                    </div>
                                </div>
                                <span class="text-[7px] font-mono text-sky-400 mt-1 uppercase">9600-8N1</span>
                            </div>

                            <div class="port-pair-column">
                                <span class="port-num-label text-slate-400">AUX</span>
                                <div class="w-[38px] h-[38px] bg-[#090c10] border border-slate-700 rounded flex flex-col items-center justify-center cursor-default" title="Puerto Auxiliar RJ-45 para Modem/Dial-up">
                                    <div class="w-3.5 h-1 bg-slate-800 rounded-sm mb-1"></div>
                                    <div class="flex gap-0.5">
                                        <div class="w-0.5 h-1.5 bg-slate-500 rounded-xs"></div>
                                        <div class="w-0.5 h-1.5 bg-slate-500 rounded-xs"></div>
                                        <div class="w-0.5 h-1.5 bg-slate-500 rounded-xs"></div>
                                    </div>
                                </div>
                                <span class="text-[7px] font-mono text-slate-500 mt-1 uppercase">MODEM</span>
                            </div>
                        </div>
                    </div>

                    {{-- Ranuras Modulares WIC / HWIC Reales (Slot 0 y Slot 1) --}}
                    <div class="flex items-center gap-3">
                        {{-- HWIC Slot 0 --}}
                        <div class="w-28 sm:w-32 h-[90px] bg-gradient-to-b from-[#1c232d] to-[#11161d] border border-slate-600 rounded-lg p-2 flex flex-col justify-between items-center shadow-inner">
                            <div class="flex items-center justify-between w-full">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                                <span class="text-[8px] font-mono text-slate-400 uppercase font-bold">SLOT 0: HWIC</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                            </div>
                            <span class="text-[8px] font-mono text-slate-500 tracking-wider uppercase">WAN EXPANSION</span>
                            <div class="flex items-center justify-between w-full">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                                <span class="text-[7px] font-mono text-slate-600">SERIAL/T1</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                            </div>
                        </div>

                        {{-- HWIC Slot 1 --}}
                        <div class="w-28 sm:w-32 h-[90px] bg-gradient-to-b from-[#1c232d] to-[#11161d] border border-slate-600 rounded-lg p-2 flex flex-col justify-between items-center shadow-inner hidden sm:flex">
                            <div class="flex items-center justify-between w-full">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                                <span class="text-[8px] font-mono text-slate-400 uppercase font-bold">SLOT 1: HWIC</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                            </div>
                            <span class="text-[8px] font-mono text-slate-500 tracking-wider uppercase">MODULAR BAY</span>
                            <div class="flex items-center justify-between w-full">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                                <span class="text-[7px] font-mono text-slate-600">VOICE/VPN</span>
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-700 border border-slate-400 shadow-sm"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="!isRouter">
                {{-- ==================== MORFOLOGÍA SWITCH RACK (CATALYST / SG200 / NEXUS) ==================== --}}
                <div class="flex-1 flex items-center justify-center px-2">
                    <template x-if="copperPorts.length > 0">
                        <div class="port-matrix">
                            <template x-for="(col, cIdx) in copperPairs" :key="cIdx">
                                <div class="flex items-center">
                                    <template x-if="cIdx > 0 && cIdx % 4 === 0">
                                        <div class="port-block-separator"></div>
                                    </template>
                                    <div class="port-pair-column">
                                        {{-- Top Port --}}
                                        <template x-if="col.top">
                                            <span class="port-num-label" x-text="col.top.port_num"></span>
                                        </template>
                                        <template x-if="col.top">
                                            <div class="rj45-socket port-element" 
                                                 :id="'port-btn-' + col.top.id"
                                                 :class="{ 'active-selected': selectedPortId === col.top.id }"
                                                 @click="select(col.top.id)" 
                                                 :title="col.top.name + ' • ' + (col.top.alias || ('Puerto ' + col.top.port_num)) + ' • ' + (col.top.oper_status || 'down').toUpperCase()">
                                                <div class="led-indicator led-top-left" :class="{
                                                    'led-link-err': col.top.is_errdisabled,
                                                    'led-link-up': !col.top.is_errdisabled && col.top.oper_status === 'up',
                                                    'led-link-down': !col.top.is_errdisabled && col.top.oper_status !== 'up'
                                                }"></div>
                                                <template x-if="col.top.is_poe">
                                                    <div class="led-indicator led-top-right led-poe-active"></div>
                                                </template>
                                                <div class="rj45-clip-notch"></div>
                                                <div class="rj45-pins">
                                                    <div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div>
                                                    <div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Bottom Port --}}
                                        <template x-if="col.bottom">
                                            <div class="rj45-socket port-element" 
                                                 :id="'port-btn-' + col.bottom.id"
                                                 :class="{ 'active-selected': selectedPortId === col.bottom.id }"
                                                 @click="select(col.bottom.id)" 
                                                 :title="col.bottom.name + ' • ' + (col.bottom.alias || ('Puerto ' + col.bottom.port_num)) + ' • ' + (col.bottom.oper_status || 'down').toUpperCase()">
                                                <div class="led-indicator led-top-left" :class="{
                                                    'led-link-err': col.bottom.is_errdisabled,
                                                    'led-link-up': !col.bottom.is_errdisabled && col.bottom.oper_status === 'up',
                                                    'led-link-down': !col.bottom.is_errdisabled && col.bottom.oper_status !== 'up'
                                                }"></div>
                                                <template x-if="col.bottom.is_poe">
                                                    <div class="led-indicator led-top-right led-poe-active"></div>
                                                </template>
                                                <div class="rj45-clip-notch"></div>
                                                <div class="rj45-pins">
                                                    <div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div>
                                                    <div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div><div class="rj45-pin"></div>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="col.bottom">
                                            <span class="port-num-label" x-text="col.bottom.port_num"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="copperPorts.length === 0 && sfpPorts.length === 0">
                        <div class="py-6 text-gray-500 font-mono text-xs">
                            No se detectaron interfaces físicas asociadas a este equipo
                        </div>
                    </template>
                </div>
            </template>

            {{-- SFP Uplinks for Switch --}}
            <template x-if="!isRouter && sfpPorts.length > 0">
                <div class="flex items-center">
                    {{-- Metallic Bezel Divider --}}
                    <div class="w-[2px] bg-gradient-to-b from-transparent via-gray-600 to-transparent my-1"></div>

                    <div class="flex flex-col justify-center items-center px-3 py-1.5 bg-[#12161b] rounded-lg border border-gray-800 shadow-inner flex-shrink-0 ml-2">
                        <div class="text-[9px] font-extrabold {{ $isCatalyst ? 'text-blue-400' : ($isNexus ? 'text-cyan-400' : 'text-blue-400') }} font-mono tracking-wider text-center mb-1 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isNexus ? 'bg-cyan-400' : 'bg-blue-400' }} animate-pulse"></span>
                            <span>SFP {{ ($totalPorts >= 26 && !$isNexus) ? '1G' : '10G' }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <template x-for="(scol, sIdx) in sfpPairs" :key="sIdx">
                                <div class="port-pair-column">
                                    <template x-if="scol.top">
                                        <span class="port-num-label text-blue-300" x-text="scol.top.port_num"></span>
                                    </template>
                                    <template x-if="scol.top">
                                        <div class="sfp-cage port-element" 
                                             :id="'port-btn-' + scol.top.id"
                                             :class="{ 'active-selected': selectedPortId === scol.top.id }"
                                             @click="select(scol.top.id)" 
                                             :title="scol.top.name + ' • ' + (scol.top.alias || ('Uplink ' + scol.top.port_num)) + ' • ' + (scol.top.oper_status || 'down').toUpperCase()">
                                            <div class="led-indicator led-top-left" :class="{
                                                'led-link-err': scol.top.is_errdisabled,
                                                'led-link-up': !scol.top.is_errdisabled && scol.top.oper_status === 'up',
                                                'led-link-down': !scol.top.is_errdisabled && scol.top.oper_status !== 'up'
                                            }"></div>
                                            <div class="sfp-latch"></div>
                                        </div>
                                    </template>

                                    <template x-if="scol.bottom">
                                        <div class="sfp-cage port-element" 
                                             :id="'port-btn-' + scol.bottom.id"
                                             :class="{ 'active-selected': selectedPortId === scol.bottom.id }"
                                             @click="select(scol.bottom.id)" 
                                             :title="scol.bottom.name + ' • ' + (scol.bottom.alias || ('Uplink ' + scol.bottom.port_num)) + ' • ' + (scol.bottom.oper_status || 'down').toUpperCase()">
                                            <div class="led-indicator led-top-left" :class="{
                                                'led-link-err': scol.bottom.is_errdisabled,
                                                'led-link-up': !scol.bottom.is_errdisabled && scol.bottom.oper_status === 'up',
                                                'led-link-down': !scol.bottom.is_errdisabled && scol.bottom.oper_status !== 'up'
                                            }"></div>
                                            <div class="sfp-latch"></div>
                                        </div>
                                    </template>
                                    <template x-if="scol.bottom">
                                        <span class="port-num-label text-blue-300" x-text="scol.bottom.port_num"></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Right Rack Ear --}}
            <div class="rack-ear">
                <div class="rack-screw"></div>
                <div class="rack-screw"></div>
            </div>
        </div>
    </div>

    {{-- ==================== 3. LOWER TAB SYSTEM ==================== --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        {{-- Tabs Navigation Bar --}}
        <div class="flex items-center border-b border-gray-200 px-4 bg-gray-50/70 overflow-x-auto">
            <button class="nav-tab-btn active" data-tab="tab-hardware">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                Tab 1: Chasis y Hardware
            </button>
            <button class="nav-tab-btn" data-tab="tab-addressing">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                Tab 2: Direccionamiento y Descubrimiento
            </button>
            <button class="nav-tab-btn" data-tab="tab-l2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                Tab 3: L2 / Switching & STP
            </button>
            <button class="nav-tab-btn" data-tab="tab-security">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                Tab 4: Seguridad, QoS y Multicast
            </button>
        </div>

        {{-- ==================== TAB 1: Chasis y Hardware ==================== --}}
        <div class="tab-pane active p-6" id="tab-hardware">

            {{-- Ficha Técnica Oficial del Dispositivo / Hardware Architecture Banner --}}
            <div class="bg-gradient-to-r from-slate-900 via-[#0e131d] to-slate-900 border border-slate-800 rounded-2xl p-5 mb-5 shadow-md relative overflow-hidden">
                <div class="absolute -right-16 -top-16 w-56 h-56 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="flex flex-col gap-4">
                    {{-- Header Row: Model & Architectural Badges --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-2.5 h-2.5 rounded-full bg-[#f26419] shadow-[0_0_8px_#f26419]"></div>
                            <div>
                                <span class="text-[9px] font-mono font-black text-blue-400 uppercase tracking-widest block">ARQUITECTURA DE CHASSIS & CONTROL</span>
                                <h3 class="text-lg font-black text-white leading-tight mt-0.5">{{ $chasis->model_name ?? $dispositivo->nombre }}</h3>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-950/80 text-blue-300 border border-blue-800/50 uppercase font-mono">
                                {{ $deviceHardware['tipo_equipo'] }}
                            </span>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700/60 uppercase font-mono">
                                {{ $deviceHardware['factor_forma'] }}
                            </span>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-800/50 uppercase font-mono flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                {{ $deviceHardware['rol'] }}
                            </span>
                        </div>
                    </div>

                    {{-- Complementary Control & Operational Specifications --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div class="bg-slate-800/40 p-3 rounded-xl border border-slate-700/40 flex flex-col justify-between">
                            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider block">Matriz de Conectividad</span>
                            <div class="mt-1">
                                <span class="font-extrabold text-slate-100 text-sm font-mono block">{{ $copperPorts->count() }} RJ45 / {{ $sfpPorts->count() }} SFP</span>
                                <span class="text-[10px] text-blue-400 font-mono mt-0.5 block">{{ $totalPorts }} Interfaces Totales</span>
                            </div>
                        </div>

                        <div class="bg-slate-800/40 p-3 rounded-xl border border-slate-700/40 flex flex-col justify-between">
                            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider block">Modularidad & Bahías</span>
                            <div class="mt-1">
                                <span class="font-extrabold text-slate-100 text-xs mt-0.5 block">{{ $isRouter ? '2x Ranuras WIC/HWIC' : 'Stacking / Uplink Modular' }}</span>
                                <span class="text-[10px] text-orange-400 font-mono mt-0.5 block">{{ $isRouter ? 'Slot 0 (WAN) · Slot 1 (Opt)' : 'SFP Uplinks Flexibles' }}</span>
                            </div>
                        </div>

                        <div class="bg-slate-800/40 p-3 rounded-xl border border-slate-700/40 flex flex-col justify-between">
                            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider block">Comunidad SNMP (Sondeo)</span>
                            <div class="mt-1">
                                <code class="font-mono font-bold text-amber-300 text-xs block truncate bg-slate-900/60 px-2 py-0.5 rounded border border-amber-500/20 w-fit">{{ $dispositivo->comunidad_snmp ?? 'public' }} (v2c)</code>
                                <span class="text-[10px] text-emerald-400 font-mono mt-0.5 block">Poller & Telemetría OK</span>
                            </div>
                        </div>

                        <div class="bg-slate-800/40 p-3 rounded-xl border border-slate-700/40 flex flex-col justify-between">
                            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider block">Gestión Local & SSH</span>
                            <div class="mt-1">
                                <span class="font-mono font-bold text-sky-300 text-xs block">Puerto SSH: {{ $dispositivo->ssh_port ?? 22 }}</span>
                                <span class="text-[10px] text-slate-400 font-mono mt-0.5 block">Consola RJ-45 (9600-8N1)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {{-- CPU Global & Cores --}}
                @php
                    $cpuVal = $chasis->cpu_utilization ?? $ultimaMetrica->cpu_usage ?? 0;
                    $ramVal = $chasis->ram_utilization ?? $ultimaMetrica->memory_usage ?? 0;
                    $tempVal = $chasis->temperatura_c ?? $ultimaMetrica->temperatura_celsius ?? 34;
                @endphp
                <div class="telemetry-card">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs uppercase font-extrabold text-gray-500 tracking-wider">CPU Global</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-[#3b5998]">{{ round($cpuVal, 1) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-3 overflow-hidden">
                        <div class="bg-[#3b5998] h-2.5 rounded-full" style="width: {{ min(100, max(2, $cpuVal)) }}%"></div>
                    </div>
                    <div class="border-t border-gray-200 pt-2 text-[11px] text-gray-600 font-mono">
                        @if(!empty($chasis->cpu_cores) && is_array($chasis->cpu_cores))
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($chasis->cpu_cores as $core)
                                    <div>{{ $core['core'] ?? 'Core' }}: <span class="font-bold text-gray-800">{{ $core['usage'] ?? round($cpuVal) }}%</span></div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex items-center justify-between text-xs font-sans text-gray-500">
                                <span>Procesador Control Plane:</span>
                                <span class="font-bold font-mono text-gray-800">{{ round($cpuVal, 1) }}%</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- RAM --}}
                <div class="telemetry-card">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs uppercase font-extrabold text-gray-500 tracking-wider">Memoria RAM</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">{{ round($ramVal, 1) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-3 overflow-hidden">
                        <div class="bg-emerald-600 h-2.5 rounded-full" style="width: {{ min(100, max(2, $ramVal)) }}%"></div>
                    </div>
                    <div class="space-y-1 text-xs text-gray-600 border-t border-gray-200 pt-2">
                        <div class="flex justify-between"><span>Total:</span> <span class="font-bold font-mono">{{ $chasis->ram_total_mb ?? ($ramVal > 0 ? 512 : '--') }} MB</span></div>
                        <div class="flex justify-between"><span>Usada:</span> <span class="font-bold font-mono">{{ $chasis->ram_used_mb ?? ($ramVal > 0 ? round(($ramVal/100) * ($chasis->ram_total_mb ?? 512)) : '--') }} MB</span></div>
                    </div>
                </div>

                {{-- PoE Budget --}}
                <div class="telemetry-card">
                    @php
                        $poeBudget = (float)($chasis->poe_total_budget_w ?? 0);
                        $poePct = ($poeBudget > 0) ? round(($totalPoeWatts / $poeBudget) * 100, 1) : 0;
                    @endphp
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs uppercase font-extrabold text-gray-500 tracking-wider">Presupuesto PoE</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $poeBudget > 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $poeBudget > 0 ? number_format($totalPoeWatts, 1) . ' / ' . $poeBudget . 'W' : 'No PoE (N/A)' }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-3 overflow-hidden">
                        <div class="bg-amber-500 h-2.5 rounded-full" style="width: {{ min($poePct, 100) }}%"></div>
                    </div>
                    <div class="space-y-1 text-xs text-gray-600 border-t border-gray-200 pt-2">
                        <div class="flex justify-between"><span>Consumo:</span> <span class="font-bold text-amber-600">{{ $poeBudget > 0 ? $poePct . '%' : 'N/A' }}</span></div>
                        <div class="flex justify-between"><span>Disponible:</span> <span class="font-bold font-mono text-gray-800">{{ $poeBudget > 0 ? number_format(max(0, $poeBudget - $totalPoeWatts), 1) . ' W' : '0.0 W' }}</span></div>
                    </div>
                </div>

                {{-- Environmental & Power Supply Status --}}
                <div class="telemetry-card flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs uppercase font-extrabold text-gray-500 tracking-wider">Salud y Fuentes (PSU)</span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $tempVal < 55 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $tempVal < 55 ? 'Normal' : 'Alerta Térmica' }}</span>
                        </div>
                        <div class="space-y-1 text-xs text-gray-600 mt-2">
                            <div class="flex justify-between">
                                <span>Temp. Térmica:</span> 
                                <span class="font-bold font-mono {{ $tempVal < 55 ? 'text-emerald-600' : 'text-amber-600' }}">{{ round($tempVal, 1) }} °C ({{ $tempVal < 55 ? 'Óptima' : 'Elevada' }})</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Ventilación / Fans:</span> 
                                <span class="font-bold text-gray-700">{{ $chasis->estado_ventiladores[0]['fan'] ?? 'Ventilación Interna' }}: {{ $chasis->estado_ventiladores[0]['status'] ?? 'OK' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 pt-2 mt-2 flex items-center justify-between text-xs font-mono">
                        <span class="text-gray-500">Fuente de Poder:</span>
                        <span class="font-bold text-emerald-600">{{ $chasis->estado_fuentes[0]['psu'] ?? 'Fuente AC' }} Activa</span>
                    </div>
                </div>
            </div>

            {{-- PSUs, Fans & Temperature Grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Power Supplies (PSU 1 / PSU 2) --}}
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-gray-600 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Fuentes de Poder (PSU Redundantes)
                    </h3>
                    <div class="space-y-3">
                        @forelse($chasis->estado_fuentes ?? [] as $psu)
                        <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ str_contains(strtolower($psu['status'] ?? ''), 'active') || str_contains(strtolower($psu['status'] ?? ''), 'ok') ? 'bg-emerald-500 shadow-sm' : 'bg-amber-400' }}"></span>
                                    <span class="font-bold text-sm text-gray-800">{{ $psu['psu'] ?? 'PSU-1' }}</span>
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ $psu['model'] ?? 'PWR-AC' }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 font-mono">Entrada: {{ $psu['input_voltage'] ?? 120 }}V AC · Temp: {{ $psu['temp_c'] ?? round($tempVal) }}°C</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-extrabold text-emerald-600">
                                    {{ ($psu['output_watts'] ?? 0) > 0 ? number_format($psu['output_watts'], 1) . ' W' : 'Operativa' }}
                                </span>
                                <p class="text-[10px] text-gray-400 font-semibold">{{ $psu['status'] ?? 'Active' }}</p>
                            </div>
                        </div>
                        @empty
                        <div class="bg-white p-3 rounded-lg border border-gray-200 text-xs text-gray-500">
                            Fuente de alimentación interna integrada. Suministro eléctrico nominal (AC).
                        </div>
                        @endforelse
                    </div>
                </div>

                {{-- Cooling Fans --}}
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-gray-600 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"></path></svg>
                        Módulos de Ventilación (Fans RPM)
                    </h3>
                    <div class="space-y-3">
                        @forelse($chasis->estado_ventiladores ?? [] as $fan)
                        <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm flex items-center justify-between">
                            <div>
                                <p class="font-bold text-sm text-gray-800">{{ $fan['fan'] ?? 'Fan Tray 1' }}</p>
                                <p class="text-[11px] text-gray-400 font-medium">{{ $fan['direction'] ?? 'Front-to-Back' }}</p>
                            </div>
                            <div class="text-right font-mono">
                                <span class="text-sm font-extrabold text-[#3b5998]">{{ isset($fan['rpm']) ? number_format($fan['rpm']) . ' RPM' : 'Nominal' }}</span>
                                <span class="block text-[10px] font-bold text-emerald-600">Estado: {{ $fan['status'] ?? 'OK' }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="bg-white p-3 rounded-lg border border-gray-200 text-xs text-gray-500">
                            Sistema de ventilación interno activo. Flujo térmico y disipación operando en rango normal.
                        </div>
                        @endforelse
                    </div>
                </div>

                {{-- Temperature Sensors --}}
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-gray-600 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Sensores Térmicos
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach($chasis->sensores_temperatura ?? [] as $sensor)
                        @php
                            $tempC = (int)($sensor['temp_c'] ?? 0);
                            $percentage = min(100, max(0, ($tempC / 80) * 100)); // Asumiendo 80°C como escala max
                            $dasharray = 125.6;
                            $dashoffset = 125.6 - (125.6 * $percentage / 100);
                            $color = $tempC >= 60 ? '#ef4444' : ($tempC >= 45 ? '#f59e0b' : '#10b981');
                            
                            // Formateo legible del nombre del sensor
                            $rawName = $sensor['sensor'] ?? 'Sensor Térmico';
                            $cleanName = str_ireplace([', GREEN', ', RED', ', YELLOW', ' Temp Sensor', ' temperature'], '', $rawName);
                            
                            // Extraer switch o slot si existe
                            $memberBadge = '';
                            if (preg_match('/(Switch \d+|SW#?\d+|Slot \d+|module \d+|VTT \d+)/i', $cleanName, $m)) {
                                $memberBadge = strtoupper(trim($m[1]));
                                $cleanName = trim(str_ireplace($m[0], '', $cleanName));
                                $cleanName = ltrim($cleanName, ' -:');
                            }
                            if (empty($cleanName)) {
                                $cleanName = 'Sensor ' . ($loop->index + 1);
                            }

                            // Clasificación detallada del componente físico
                            $isHotspot = (bool)preg_match('/hotspot|asic|uadp|core/i', $rawName);
                            $isInlet   = (bool)preg_match('/inlet|inlt/i', $rawName);
                            $isOutlet  = (bool)preg_match('/outlet|otlt/i', $rawName);

                            if ($isHotspot) {
                                $zoneType = 'Núcleo / Silicio ASIC';
                                $zoneDesc = 'Mide el procesador de paquetes de alta velocidad. Es el punto de mayor disipación térmica del chasis.';
                                $rangeDesc = '10°C - 68°C (Silicio Seguro)';
                            } elseif ($isInlet) {
                                $zoneType = 'Entrada de Aire (Inlet)';
                                $zoneDesc = 'Monitorea el aire frío ambiente que ingresa desde el pasillo frontal del rack hacia el equipo.';
                                $rangeDesc = '15°C - 35°C (Ambiente Rack)';
                            } elseif ($isOutlet) {
                                $zoneType = 'Salida de Aire (Outlet)';
                                $zoneDesc = 'Mide el calor disipado en el flujo de aire caliente expulsado hacia el pasillo trasero por los fans.';
                                $rangeDesc = '25°C - 45°C (Disipación)';
                            } else {
                                $zoneType = 'Sensor de Chasis';
                                $zoneDesc = 'Sonda térmica de control en la placa madre para prevenir fluctuaciones térmicas internas.';
                                $rangeDesc = '20°C - 50°C (Nominal)';
                            }
                        @endphp
                        <div class="relative bg-slate-900 p-3 rounded-xl shadow-lg border border-slate-800 flex flex-col items-center justify-between group hover:border-slate-600 hover:shadow-xl transition-all duration-300 cursor-pointer">
                            
                            {{-- Popover flotante con información detallada al pasar el cursor --}}
                            <div class="absolute bottom-[108%] left-1/2 -translate-x-1/2 mb-1.5 w-72 sm:w-80 bg-slate-950/95 backdrop-blur-md text-white p-3.5 rounded-xl border border-slate-700 shadow-2xl z-50 pointer-events-none opacity-0 translate-y-2 group-hover:opacity-100 group-hover:translate-y-0 group-hover:pointer-events-auto transition-all duration-200 ease-out text-left">
                                <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full {{ $color == '#ef4444' ? 'bg-red-500 shadow-[0_0_6px_#ef4444]' : ($color == '#f59e0b' ? 'bg-amber-400' : 'bg-emerald-400 shadow-[0_0_6px_#10b981]') }}"></span>
                                        <span class="text-[10px] font-mono font-bold text-slate-200 uppercase">{{ $memberBadge ? $memberBadge . ' · ' : '' }}{{ $zoneType }}</span>
                                    </div>
                                    <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded {{ $sensor['status'] == 'OK' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800/50' : 'bg-red-950 text-red-300 border border-red-800/50' }}">{{ $sensor['status'] }}</span>
                                </div>
                                <div class="space-y-1.5 text-xs">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400 text-[11px]">Temperatura Actual:</span>
                                        <span class="font-extrabold font-mono text-sm" style="color: {{ $color }};">{{ $tempC }} °C</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[11px]">
                                        <span class="text-slate-400">Rango Recomendado:</span>
                                        <span class="font-mono text-slate-200 font-semibold">{{ $rangeDesc }}</span>
                                    </div>
                                    <div class="border-t border-slate-800/80 pt-1.5 mt-1">
                                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Componente / Función:</span>
                                        <p class="text-[11px] text-slate-300 leading-snug">{{ $zoneDesc }}</p>
                                    </div>
                                    <div class="bg-slate-900 p-1.5 rounded border border-slate-800 text-[10px] font-mono text-slate-400 mt-1 break-all">
                                        <span class="text-blue-400 font-bold">SNMP MIB:</span> {{ $rawName }}
                                    </div>
                                </div>
                                <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-slate-950 rotate-45 border-r border-b border-slate-700"></div>
                            </div>

                            {{-- Encabezado de la tarjeta --}}
                            <div class="text-center w-full mb-1">
                                @if($memberBadge)
                                    <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-blue-950/80 text-blue-300 border border-blue-800/40 inline-block mb-1">{{ $memberBadge }}</span>
                                @endif
                                <span class="text-xs font-bold text-slate-200 block truncate w-full">{{ $cleanName }}</span>
                            </div>
                            
                            <!-- Speedometer Gauge -->
                            <div class="relative w-24 h-14 overflow-hidden mt-1">
                                <svg viewBox="0 0 100 50" class="w-full h-full drop-shadow-md">
                                    <!-- Background arc -->
                                    <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#334155" stroke-width="12" stroke-linecap="round"/>
                                    <!-- Animated fill arc -->
                                    <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="{{ $color }}" stroke-width="12" stroke-linecap="round" 
                                          stroke-dasharray="125.6" stroke-dashoffset="125.6" 
                                          class="gauge-fill-animation drop-shadow-lg"
                                          style="--target-offset: {{ $dashoffset }};"/>
                                </svg>
                                <div class="absolute bottom-0 left-0 w-full text-center flex flex-col items-center">
                                    <span class="text-lg font-extrabold text-white font-mono leading-none">{{ $tempC }}<span class="text-xs text-slate-400">°C</span></span>
                                </div>
                            </div>
                            
                            <div class="mt-2 flex items-center justify-between w-full px-1">
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider {{ $sensor['status'] == 'OK' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-red-500/20 text-red-400 border border-red-500/30' }}">{{ $sensor['status'] }}</span>
                                <span class="text-[9px] font-mono text-slate-400 group-hover:text-blue-400 transition-colors">Detalles ℹ️</span>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- CSS para la animación dinámica de cada termómetro -->
                    <style>
                        @keyframes fillGauge {
                            from { stroke-dashoffset: 125.6; }
                            to { stroke-dashoffset: var(--target-offset); }
                        }
                        .gauge-fill-animation {
                            animation: fillGauge 1.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
                        }
                    </style>
                </div>
            </div>
        </div>

        {{-- ==================== TAB 2: Direccionamiento y Descubrimiento ==================== --}}
        <div class="tab-pane p-6" id="tab-addressing">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                
                {{-- CAM Table (Interactive MAC Search) --}}
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 flex flex-col">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-800">Tabla CAM (Direcciones MAC)</h3>
                            <p class="text-xs text-gray-400">Buscador reactivo instantáneo (acepta formato con dos puntos o punto Cisco)</p>
                        </div>
                        <div class="relative w-full sm:w-56">
                            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <input type="text" id="macSearchInput" placeholder="Buscar MAC o VLAN..." class="w-full pl-9 pr-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#3b5998]/30 focus:border-[#3b5998]">
                        </div>
                    </div>

                    <div class="overflow-y-auto max-h-72 border border-gray-200 rounded-lg bg-white custom-scrollbar">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-gray-100 text-gray-600 font-bold sticky top-0 border-b border-gray-200">
                                <tr>
                                    <th class="p-2.5">Dirección MAC</th>
                                    <th class="p-2.5">VLAN</th>
                                    <th class="p-2.5">Puerto</th>
                                    <th class="p-2.5">Tipo</th>
                                    <th class="p-2.5 text-right">Age (s)</th>
                                </tr>
                            </thead>
                            <tbody id="macTableBody" class="divide-y divide-gray-100 font-mono">
                                @forelse($tablas->mac_table ?? [] as $entry)
                                <tr class="hover:bg-blue-50/50 mac-row" data-mac="{{ $entry['mac'] ?? '' }}" data-vlan="{{ $entry['vlan'] ?? 1 }}" data-port="{{ $entry['port'] ?? '' }}">
                                    <td class="p-2.5 text-gray-800">
                                        <div class="flex flex-col">
                                            <span class="font-bold">{{ $entry['mac'] ?? '00:00:00:00:00:00' }}</span>
                                            <span class="text-[10px] text-gray-400 font-sans mt-0.5">{{ \App\Services\MacVendorResolver::resolveVendor($entry['mac'] ?? '') }}</span>
                                        </div>
                                    </td>
                                    <td class="p-2.5"><span class="px-2 py-0.5 rounded bg-blue-50 text-[#3b5998] font-bold">VLAN {{ $entry['vlan'] ?? 1 }}</span></td>
                                    <td class="p-2.5 font-bold text-[#f26419]">{{ $entry['port'] ?? '-' }}</td>
                                    <td class="p-2.5 text-gray-500 font-sans">{{ $entry['type'] ?? 'Dynamic' }}</td>
                                    <td class="p-2.5 text-right text-gray-500">{{ $entry['age_sec'] ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="p-4 text-center text-gray-400">Sin datos CAM disponibles</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Full ARP Table --}}
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-800">Tabla ARP del Dispositivo</h3>
                            <p class="text-xs text-gray-400">Asignación IP a MAC en interfaces virtuales SVI</p>
                        </div>
                        <span class="text-xs font-bold text-[#3b5998] bg-blue-100 px-2.5 py-0.5 rounded-full font-mono">{{ count($tablas->arp_table ?? []) }} entradas</span>
                    </div>

                    <div class="overflow-y-auto max-h-72 border border-gray-200 rounded-lg bg-white custom-scrollbar">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-gray-100 text-gray-600 font-bold sticky top-0 border-b border-gray-200">
                                <tr>
                                    <th class="p-2.5">IP Address</th>
                                    <th class="p-2.5">MAC Address</th>
                                    <th class="p-2.5">Interfaz</th>
                                    <th class="p-2.5 text-right">Age (min)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-mono">
                                @forelse($tablas->arp_table ?? [] as $arp)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2.5 font-bold text-gray-800">{{ $arp['ip'] ?? '' }}</td>
                                    <td class="p-2.5 text-gray-600">
                                        <div class="flex flex-col">
                                            <span class="font-bold">{{ $arp['mac'] ?? '' }}</span>
                                            <span class="text-[10px] text-gray-400 font-sans mt-0.5">{{ \App\Services\MacVendorResolver::resolveVendor($arp['mac'] ?? '') }}</span>
                                        </div>
                                    </td>
                                    <td class="p-2.5 font-bold text-[#3b5998] font-sans">{{ $arp['interface'] ?? 'VLAN 1' }}</td>
                                    <td class="p-2.5 text-right text-gray-500">{{ $arp['age_min'] ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="p-4 text-center text-gray-400">Sin registros ARP</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- CDP / LLDP Neighbor Matrix --}}
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                <h3 class="text-sm font-extrabold text-gray-800 mb-1 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Matriz de Vecinos de Red (CDP / LLDP)
                </h3>
                <p class="text-xs text-gray-400 mb-3">Topología de dispositivos conectados detectados vía protocolos de capa 2</p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @php
                        $vecinos = collect();
                        if (isset($dispositivo)) {
                            foreach($dispositivo->enlacesOrigen as $enlace) {
                                $vecinos->push([
                                    'local_port' => $enlace->interfazOrigen ? $enlace->interfazOrigen->nombre : 'Port',
                                    'remote_chassis' => $enlace->dispositivoDestino ? $enlace->dispositivoDestino->nombre : 'Desconocido',
                                    'remote_ip' => $enlace->dispositivoDestino ? $enlace->dispositivoDestino->ip : 'N/A',
                                    'remote_port' => $enlace->interfazDestino ? $enlace->interfazDestino->nombre : 'Desconocido',
                                    'capabilities' => 'Switch',
                                    'platform' => 'Cisco Systems'
                                ]);
                            }
                            foreach($dispositivo->enlacesDestino as $enlace) {
                                $vecinos->push([
                                    'local_port' => $enlace->interfazDestino ? $enlace->interfazDestino->nombre : 'Port',
                                    'remote_chassis' => $enlace->dispositivoOrigen ? $enlace->dispositivoOrigen->nombre : 'Desconocido',
                                    'remote_ip' => $enlace->dispositivoOrigen ? $enlace->dispositivoOrigen->ip : 'N/A',
                                    'remote_port' => $enlace->interfazOrigen ? $enlace->interfazOrigen->nombre : 'Desconocido',
                                    'capabilities' => 'Switch',
                                    'platform' => 'Cisco Systems'
                                ]);
                            }
                        }
                    @endphp

                    @forelse($vecinos as $neighbor)
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm space-y-2 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                            <span class="text-xs font-bold text-[#3b5998] font-mono">{{ $neighbor['local_port'] }}</span>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-purple-50 text-purple-700">{{ $neighbor['capabilities'] }}</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-semibold text-[10px]">Dispositivo Remoto</p>
                            <p class="text-sm font-extrabold text-gray-900 leading-tight">{{ $neighbor['remote_chassis'] }}</p>
                            <p class="text-[11px] text-gray-500 font-mono mt-0.5">IP: {{ $neighbor['remote_ip'] }}</p>
                        </div>
                        <div class="text-xs text-gray-600 font-mono pt-1">
                            <span>Puerto Remoto:</span> <strong class="text-gray-800">{{ $neighbor['remote_port'] }}</strong>
                        </div>
                        <div class="text-[10px] text-gray-400 truncate">
                            {{ $neighbor['platform'] }}
                        </div>
                    </div>
                    @empty
                    <div class="col-span-4 p-4 text-center text-gray-400 bg-white rounded-xl border border-gray-200">
                        No se detectaron vecinos LLDP/CDP en la topología guardada
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ==================== TAB 3: L2 / Switching & STP ==================== --}}
        <div class="tab-pane p-6" id="tab-l2">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {{-- VLANs Table --}}
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 lg:col-span-1 flex flex-col">
                    <h3 class="text-sm font-extrabold text-gray-800 mb-1">VLANs Activas</h3>
                    <p class="text-xs text-gray-400 mb-3">Segmentación de dominio de broadcast L2</p>

                    <div class="overflow-y-auto max-h-80 border border-gray-200 rounded-lg bg-white custom-scrollbar divide-y divide-gray-100">
                        @forelse($tablas->vlans_table ?? [] as $vlan)
                        <div class="p-3 hover:bg-gray-50 text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-extrabold text-sm text-[#3b5998]">VLAN {{ $vlan['id'] }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">{{ $vlan['status'] }}</span>
                            </div>
                            <p class="font-semibold text-gray-700 text-[11px]">{{ $vlan['name'] }}</p>
                            <div class="text-[10px] text-gray-400 mt-1 font-mono">
                                Acceso: <span class="text-gray-600">{{ count($vlan['ports_access'] ?? []) }} puertos</span> | 
                                Trunk: <span class="text-gray-600">{{ count($vlan['ports_trunk'] ?? []) }} puertos</span>
                            </div>
                        </div>
                        @empty
                        <div class="p-4 text-center text-gray-400 text-xs">Sin VLANs configuradas</div>
                        @endforelse
                    </div>
                </div>

                {{-- Spanning Tree Protocol (STP) & LACP --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- STP Status Card --}}
                    @php $stp = $tablas->spanning_tree ?? []; @endphp
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                            <div>
                                <h3 class="text-sm font-extrabold text-gray-800 flex items-center gap-2">
                                    <span>Spanning Tree Protocol (STP)</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-[#3b5998]">{{ $stp['protocol'] ?? 'Rapid-PVST+' }}</span>
                                </h3>
                                <p class="text-xs text-gray-400">Prevención de bucles y topología de conmutación L2</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs font-mono">
                                <span class="text-gray-500">TCNs: <strong class="text-[#f26419]">{{ $stp['topology_changes'] ?? 0 }}</strong></span>
                                <span class="text-gray-500">Root Port: <strong class="text-emerald-600">{{ $stp['root_port'] ?? 'Root / Local' }}</strong></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4 text-xs font-mono">
                            <div class="bg-white p-2.5 rounded-lg border border-gray-200">
                                <span class="text-gray-400 text-[10px] block font-sans uppercase">Root Bridge ID</span>
                                <span class="font-bold text-gray-800 text-[11px] truncate block" title="{{ $stp['root_bridge_id'] ?? 'N/A' }}">{{ $stp['root_bridge_id'] ?? 'Detectado vía STP' }}</span>
                            </div>
                            <div class="bg-white p-2.5 rounded-lg border border-gray-200">
                                <span class="text-gray-400 text-[10px] block font-sans uppercase">Root Path Cost</span>
                                <span class="font-bold text-gray-800 text-sm">{{ $stp['root_cost'] ?? 0 }}</span>
                            </div>
                            <div class="bg-white p-2.5 rounded-lg border border-gray-200 col-span-2 md:col-span-1">
                                <span class="text-gray-400 text-[10px] block font-sans uppercase">Último Cambio de Topología</span>
                                <span class="font-bold text-gray-700 text-xs font-sans">{{ $stp['last_tcn'] ?? 'Reciente' }}</span>
                            </div>
                        </div>

                        {{-- STP Ports Table --}}
                        @if(!empty($stp['ports']) && is_array($stp['ports']))
                        <div class="overflow-x-auto border border-gray-200 rounded-lg bg-white">
                            <table class="w-full text-left text-xs border-collapse font-mono">
                                <thead class="bg-gray-100 text-gray-600 font-bold border-b border-gray-200">
                                    <tr>
                                        <th class="p-2">Puerto</th>
                                        <th class="p-2">Rol</th>
                                        <th class="p-2">Estado</th>
                                        <th class="p-2">Costo</th>
                                        <th class="p-2">BPDU Guard</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($stp['ports'] as $stpPort)
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-2 font-bold text-gray-800">{{ $stpPort['port'] ?? 'Port' }}</td>
                                        <td class="p-2 text-[#3b5998] font-bold">{{ $stpPort['role'] ?? 'Desg' }}</td>
                                        <td class="p-2">
                                            @if(($stpPort['state'] ?? '') === 'Forwarding')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">Forwarding</span>
                                            @elseif(($stpPort['state'] ?? '') === 'Blocking')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">Blocking</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-700">{{ $stpPort['state'] ?? 'Active' }}</span>
                                            @endif
                                        </td>
                                        <td class="p-2 text-gray-500">{{ $stpPort['cost'] ?? 4 }}</td>
                                        <td class="p-2 font-sans text-gray-600">{{ $stpPort['bpdu_guard'] ?? 'Enabled' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <p class="text-xs text-gray-400 bg-white p-3 rounded-lg border border-gray-200">Topología STP convergida sin puertos bloqueados.</p>
                        @endif
                    </div>

                    {{-- LACP / Port-Channels --}}
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h3 class="text-sm font-extrabold text-gray-800 mb-1">Agregación de Enlaces (LACP / Port-Channels)</h3>
                        <p class="text-xs text-gray-400 mb-3">Canales de agregación de ancho de banda IEEE 802.3ad</p>

                        <div class="space-y-3">
                            @forelse($tablas->lacp_port_channels ?? [] as $pc)
                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-base text-gray-900">{{ $pc['channel'] ?? 'Po1' }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">{{ $pc['status'] ?? 'In-Use' }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1 font-mono">Protocolo: {{ $pc['protocol'] ?? 'LACP' }} · Balanceo: {{ $pc['load_balance'] ?? 'src-dst-ip' }}</p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <span class="text-xs font-bold text-gray-400 block uppercase">Miembros Activos</span>
                                    <div class="flex gap-1 mt-0.5">
                                        @foreach($pc['active_members'] ?? [] as $m)
                                            <span class="px-2 py-0.5 rounded bg-blue-50 text-[#3b5998] font-mono text-xs font-bold border border-blue-200">{{ $m }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @empty
                            <p class="text-xs text-gray-400 bg-white p-3 rounded-lg border border-gray-200">Sin Port-Channels / EtherChannels configurados en este dispositivo.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== TAB 4: Seguridad, QoS y Multicast ==================== --}}
        <div class="tab-pane p-6" id="tab-security">
            @php $sec = $tablas->security_qos_multicast ?? []; @endphp
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                {{-- Err-Disabled & Port-Security --}}
                <div class="space-y-6">
                    {{-- Err-Disabled Ports --}}
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h3 class="text-sm font-extrabold text-red-700 mb-1 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Puertos en Estado Err-Disabled
                        </h3>
                        <p class="text-xs text-gray-400 mb-3">Puertos apagados preventivamente por el motor de seguridad</p>

                        <div class="space-y-2">
                            @forelse($sec['errdisabled_ports'] ?? [] as $ed)
                            <div class="bg-white p-3 rounded-lg border border-red-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <span class="font-mono font-extrabold text-sm text-red-600">{{ $ed['port'] }}</span>
                                    <p class="text-xs text-gray-600 font-medium mt-0.5">Causa: <strong class="text-red-700 font-mono">{{ $ed['reason'] }}</strong></p>
                                    <span class="text-[10px] text-gray-400">{{ $ed['timestamp'] }}</span>
                                </div>
                                <div class="text-right font-mono text-xs">
                                    <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 font-bold">Auto-Recovery</span>
                                    <p class="text-[10px] text-gray-500 mt-1">{{ $ed['recovery_time_remaining_sec'] ?? 180 }}s restantes</p>
                                </div>
                            </div>
                            @empty
                            <div class="bg-emerald-50/70 p-4 rounded-xl border border-emerald-200 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-xs font-extrabold text-emerald-800">Estado de Enlaces Seguro</p>
                                    <p class="text-[11px] text-emerald-700 mt-0.5">0 puertos en estado Err-Disabled. BPDU Guard y protección L2 operan normalmente sin bloqueos.</p>
                                </div>
                            </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Port-Security Status --}}
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h3 class="text-sm font-extrabold text-gray-800 mb-1">Alertas de Port-Security</h3>
                        <p class="text-xs text-gray-400 mb-3">Control de direcciones MAC conectadas por puerto</p>

                        @if(!empty($sec['port_security']) && is_array($sec['port_security']))
                        <div class="overflow-x-auto border border-gray-200 rounded-lg bg-white">
                            <table class="w-full text-left text-xs border-collapse font-mono">
                                <thead class="bg-gray-100 text-gray-600 font-bold border-b border-gray-200">
                                    <tr>
                                        <th class="p-2">Puerto</th>
                                        <th class="p-2">Max/Curr</th>
                                        <th class="p-2">Acción</th>
                                        <th class="p-2">Violaciones</th>
                                        <th class="p-2">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($sec['port_security'] as $ps)
                                    @if(is_array($ps))
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-2 font-bold text-gray-800">{{ $ps['port'] ?? 'Port' }}</td>
                                        <td class="p-2">{{ $ps['curr_mac'] ?? 1 }}/{{ $ps['max_mac'] ?? 1 }}</td>
                                        <td class="p-2 font-sans">{{ $ps['action'] ?? 'Restrict' }}</td>
                                        <td class="p-2 {{ ($ps['violations'] ?? 0) > 0 ? 'text-red-600 font-bold' : 'text-gray-400' }}">{{ $ps['violations'] ?? 0 }}</td>
                                        <td class="p-2 font-sans">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ str_contains($ps['status'] ?? '', 'shutdown') ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                                {{ $ps['status'] ?? 'Active' }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="bg-white p-3 rounded-lg border border-gray-200 text-xs text-gray-400">
                            Sin violaciones de seguridad de puerto (Port-Security) registradas.
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ACL Drops, QoS Queues & IGMP Snooping --}}
                <div class="space-y-6">
                    {{-- QoS Egress Queues --}}
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h3 class="text-sm font-extrabold text-gray-800 mb-1">Colas de QoS (Buffers & Egress Queues)</h3>
                        <p class="text-xs text-gray-400 mb-3">Distribución de ancho de banda y descarte de paquetes por congestión</p>

                        <div class="space-y-3">
                            @forelse($sec['qos_queues'] ?? [] as $queue)
                            @if(is_array($queue))
                            <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-gray-800">{{ $queue['queue'] ?? 'Queue' }}</span>
                                    <span class="text-[11px] font-mono font-bold text-[#3b5998]">{{ $queue['allocated_pct'] ?? 25 }}% BW</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-2 overflow-hidden">
                                    <div class="h-2 rounded-full {{ ($queue['buffer_pct'] ?? 0) > 75 ? 'bg-red-500' : (($queue['buffer_pct'] ?? 0) > 50 ? 'bg-amber-500' : 'bg-[#3b5998]') }}" style="width: {{ $queue['buffer_pct'] ?? 0 }}%"></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-gray-500 font-mono">
                                    <span>Tx: {{ number_format($queue['tx_packets'] ?? 0) }} pkts</span>
                                    <span class="{{ ($queue['drop_packets'] ?? 0) > 0 ? 'text-red-500 font-bold' : 'text-gray-400' }}">Drops: {{ number_format($queue['drop_packets'] ?? 0) }}</span>
                                </div>
                            </div>
                            @endif
                            @empty
                            <div class="bg-white p-3 rounded-lg border border-gray-200 text-xs text-gray-400">
                                Buffers de salida operando en estado óptimo sin descartes por congestión.
                            </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- IGMP Snooping Multicast --}}
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h3 class="text-sm font-extrabold text-gray-800 mb-1">Grupos IGMP Snooping (Multicast)</h3>
                        <p class="text-xs text-gray-400 mb-3">Flujos de video y distribución de audio en tiempo real</p>

                        <div class="space-y-2">
                            @forelse($sec['igmp_snooping'] ?? [] as $igmp)
                            @if(is_array($igmp))
                            <div class="bg-white p-2.5 rounded-lg border border-gray-200 flex items-center justify-between text-xs font-mono">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-[#3b5998]">{{ $igmp['group_ip'] ?? '' }}</span>
                                        <span class="px-1.5 py-0.2 rounded bg-blue-50 text-[10px] text-gray-600 font-sans font-bold">VLAN {{ $igmp['vlan'] ?? 1 }}</span>
                                    </div>
                                    <p class="text-[11px] text-gray-500 font-sans mt-0.5">{{ $igmp['group_name'] ?? '' }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-[11px] text-gray-600 font-bold">{{ count($igmp['members'] ?? []) }} puertos</span>
                                    <span class="block text-[9px] text-gray-400">{{ $igmp['uptime'] ?? '' }}</span>
                                </div>
                            </div>
                            @endif
                            @empty
                            <div class="bg-white p-3 rounded-lg border border-gray-200 text-xs text-gray-400">
                                Sin grupos multicast IGMP Snooping activos en este momento.
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- ==================== 4. PORT DRAWER (SLIDE-OVER LATERAL) ==================== --}}
<div id="drawerOverlay" class="drawer-overlay" onclick="closePortDrawer()"></div>
<div id="portDrawer" class="port-drawer custom-scrollbar">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white">
        <div>
            <div class="flex items-center gap-2">
                <h3 id="drawerPortName" class="text-lg font-black text-gray-900 tracking-tight">Puerto</h3>
                <span id="drawerPortOperBadge" class="text-xs font-extrabold px-2.5 py-0.5 rounded-full uppercase"></span>
            </div>
            <p id="drawerPortAlias" class="text-xs text-gray-500 font-medium mt-0.5"></p>
        </div>
        <button onclick="closePortDrawer()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    {{-- Drawer Scrollable Content --}}
    <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">

        {{-- Section 1: Traffic & Bandwidth Utilization --}}
        <div>
            <h4 class="text-xs uppercase font-extrabold text-gray-400 tracking-wider mb-3">1. Tráfico y Utilización (64-Bit)</h4>
            
            {{-- Bandwidth utilization bar --}}
            <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 mb-3">
                <div class="flex justify-between items-center mb-1.5">
                    <span class="text-xs font-bold text-gray-700">Utilización de Ancho de Banda</span>
                    <span id="drawerUtilPct" class="text-sm font-extrabold font-mono text-gray-900">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                    <div id="drawerUtilBar" class="h-2.5 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
                <div class="flex justify-between text-[10px] text-gray-400 font-mono mt-1">
                    <span>0%</span>
                    <span>70% (Alerta)</span>
                    <span>100%</span>
                </div>
            </div>

            {{-- Bytes In/Out formatted --}}
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="bg-blue-50/60 p-3 rounded-xl border border-blue-100">
                    <span class="text-[10px] uppercase font-bold text-[#3b5998] block">Tráfico Entrante (In)</span>
                    <p id="drawerInOctets" class="text-base font-extrabold text-[#3b5998] font-mono mt-0.5">0 B</p>
                    <span id="drawerInPkts" class="text-[10px] text-gray-500 font-mono block mt-1">0 ucast</span>
                </div>
                <div class="bg-emerald-50/60 p-3 rounded-xl border border-emerald-100">
                    <span class="text-[10px] uppercase font-bold text-emerald-700 block">Tráfico Saliente (Out)</span>
                    <p id="drawerOutOctets" class="text-base font-extrabold text-emerald-700 font-mono mt-0.5">0 B</p>
                    <span id="drawerOutPkts" class="text-[10px] text-gray-500 font-mono block mt-1">0 ucast</span>
                </div>
            </div>

            {{-- Packets breakdown table --}}
            <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 text-xs font-mono space-y-1.5">
                <div class="flex justify-between text-gray-600"><span>Multicast In/Out:</span> <span id="drawerMcast" class="font-bold text-gray-800">0 / 0</span></div>
                <div class="flex justify-between text-gray-600"><span>Broadcast In/Out:</span> <span id="drawerBcast" class="font-bold text-gray-800">0 / 0</span></div>
                <div class="flex justify-between text-gray-600"><span>Descartes (Discards):</span> <span id="drawerDiscards" class="font-bold text-gray-800">0</span></div>
            </div>
        </div>

        {{-- Section 2: Physical Layer --}}
        <div>
            <h4 class="text-xs uppercase font-extrabold text-gray-400 tracking-wider mb-3">2. Capa Física (L1)</h4>
            <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2 text-xs">
                <div class="flex justify-between"><span class="text-gray-500">Velocidad Negociada:</span> <strong id="drawerSpeed" class="font-mono text-gray-800">1000 Mbps</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Dúplex / Autoneg:</span> <strong id="drawerDuplex" class="text-gray-800">Full / Activa</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Modo / VLAN:</span> <strong id="drawerModeVlan" class="text-gray-800">Access (VLAN 10)</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Port-Channel:</span> <strong id="drawerPortChannel" class="font-mono text-gray-800">—</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Dirección MAC:</span> <code id="drawerMac" class="font-mono font-bold text-gray-800">00:00:00:00:00:00</code></div>
                <div class="flex justify-between"><span class="text-gray-500">Tiempo de Estado:</span> <span id="drawerLastChange" class="font-mono text-gray-700 font-medium">N/A</span></div>
            </div>
        </div>

        {{-- Section 3: Specific Error Counters --}}
        <div>
            <h4 class="text-xs uppercase font-extrabold text-gray-400 tracking-wider mb-3">3. Contadores de Error L1/L2</h4>
            <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200 flex justify-between">
                    <span class="text-gray-500 font-sans">CRC Errors:</span>
                    <strong id="drawerCrc" class="text-gray-800">0</strong>
                </div>
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200 flex justify-between">
                    <span class="text-gray-500 font-sans">Colisiones:</span>
                    <strong id="drawerCollisions" class="text-gray-800">0</strong>
                </div>
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200 flex justify-between">
                    <span class="text-gray-500 font-sans">Runts:</span>
                    <strong id="drawerRunts" class="text-gray-800">0</strong>
                </div>
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200 flex justify-between">
                    <span class="text-gray-500 font-sans">Giants:</span>
                    <strong id="drawerGiants" class="text-gray-800">0</strong>
                </div>
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200 flex justify-between">
                    <span class="text-gray-500 font-sans">Jabbers:</span>
                    <strong id="drawerJabbers" class="text-gray-800">0</strong>
                </div>
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-200 flex justify-between">
                    <span class="text-gray-500 font-sans">FCS Errors:</span>
                    <strong id="drawerFcs" class="text-gray-800">0</strong>
                </div>
            </div>
        </div>

        {{-- Section 4: SFP Optical Diagnostics (DOM) --}}
        <div id="drawerOpticsSection">
            <h4 class="text-xs uppercase font-extrabold text-gray-400 tracking-wider mb-3">4. Diagnóstico Óptico SFP+ (DOM)</h4>
            <div id="drawerOpticsContainer" class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-3">
                {{-- Populated via JS if SFP --}}
            </div>
        </div>

        {{-- Section 5: PoE Telemetry --}}
        <div>
            <h4 class="text-xs uppercase font-extrabold text-gray-400 tracking-wider mb-3">5. Telemetría PoE (Power over Ethernet)</h4>
            <div id="drawerPoeContainer" class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2">
                {{-- Populated via JS --}}
            </div>
        </div>

        {{-- Err-Disabled Section (if applicable) --}}
        <div id="drawerErrSection" class="hidden">
            <h4 class="text-xs uppercase font-extrabold text-red-500 tracking-wider mb-3">Alerta Err-Disabled</h4>
            <div class="bg-red-50 p-3.5 rounded-xl border border-red-200 text-xs">
                <p class="font-bold text-red-800">Puerto bloqueado por seguridad</p>
                <p class="text-red-700 mt-1">Causa detectada: <code id="drawerErrReason" class="font-mono font-bold bg-white px-1.5 py-0.5 rounded border border-red-200"></code></p>
            </div>
        </div>

    </div>

    {{-- Footer --}}
    <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
        <span class="text-[11px] text-gray-400 font-mono" id="drawerPortIfIndex">ifIndex: --</span>
        <button onclick="closePortDrawer()" class="px-4 py-1.5 bg-gray-200 hover:bg-gray-300 rounded-lg text-xs font-bold text-gray-700 transition">
            Cerrar Panel
        </button>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Embedded ports telemetry data
    window.__interfaces = @json($interfacesData);

    // Dynamic Alpine.js Component for Faceplate
    window.faceplateComponent = function() {
        return {
            interfaces: @json($interfacesList),
            copperPorts: @json($copperPortsList),
            sfpPorts: @json($sfpPortsList),
            isRouter: {{ $isRouter ? 'true' : 'false' }},
            selectedPortId: null,
            select(id) {
                this.selectedPortId = id;
                window.selectPort(id);
            },
            get copperPairs() {
                let pairs = [];
                for (let i = 0; i < this.copperPorts.length; i += 2) {
                    pairs.push({
                        top: this.copperPorts[i] || null,
                        bottom: this.copperPorts[i + 1] || null,
                        colIndex: Math.floor(i / 2)
                    });
                }
                return pairs;
            },
            get sfpPairs() {
                let pairs = [];
                for (let i = 0; i < this.sfpPorts.length; i += 2) {
                    pairs.push({
                        top: this.sfpPorts[i] || null,
                        bottom: this.sfpPorts[i + 1] || null,
                        colIndex: Math.floor(i / 2)
                    });
                }
                return pairs;
            }
        };
    };

    // Human-readable bytes formatter (B, KB, MB, GB)
    function formatBytes(bytes) {
        if (bytes === 0 || !bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // ==================== PORT SELECTION & DRAWER ====================
    let currentSelectedPortId = null;

    function selectPort(portId) {
        window.selectPort = selectPort;
        const data = window.__interfaces[portId];
        if (!data) return;

        currentSelectedPortId = portId;

        // Visual selection on faceplate
        document.querySelectorAll('.port-element').forEach(el => el.classList.remove('active-selected'));
        const activeBtn = document.getElementById('port-btn-' + portId);
        if (activeBtn) activeBtn.classList.add('active-selected');

        // Populate Drawer
        document.getElementById('drawerPortName').textContent = data.name;
        document.getElementById('drawerPortAlias').textContent = data.alias || 'Sin descripción';
        document.getElementById('drawerPortIfIndex').textContent = `ifIndex: ${data.if_index} • ${data.short_name}`;

        // Status badge
        const badge = document.getElementById('drawerPortOperBadge');
        if (data.is_errdisabled) {
            badge.textContent = 'Err-Disabled';
            badge.className = 'text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-red-100 text-red-700 uppercase';
        } else if (data.oper_status === 'up') {
            badge.textContent = 'Link Up';
            badge.className = 'text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 uppercase';
        } else {
            badge.textContent = 'Link Down';
            badge.className = 'text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-600 uppercase';
        }

        // Bandwidth utilization
        const util = data.bandwidth_util_pct || 0;
        document.getElementById('drawerUtilPct').textContent = util.toFixed(1) + '%';
        const utilBar = document.getElementById('drawerUtilBar');
        utilBar.style.width = Math.min(util, 100) + '%';
        if (util >= 90) {
            utilBar.className = 'h-2.5 rounded-full bg-red-500 transition-all duration-500';
        } else if (util >= 70) {
            utilBar.className = 'h-2.5 rounded-full bg-amber-500 transition-all duration-500';
        } else {
            utilBar.className = 'h-2.5 rounded-full bg-emerald-500 transition-all duration-500';
        }

        // Bytes In / Out
        document.getElementById('drawerInOctets').textContent = formatBytes(data.in_octets);
        document.getElementById('drawerOutOctets').textContent = formatBytes(data.out_octets);
        document.getElementById('drawerInPkts').textContent = Number(data.in_unicast).toLocaleString() + ' ucast';
        document.getElementById('drawerOutPkts').textContent = Number(data.out_unicast).toLocaleString() + ' ucast';
        document.getElementById('drawerMcast').textContent = `${Number(data.in_multicast).toLocaleString()} / ${Number(data.out_multicast).toLocaleString()}`;
        document.getElementById('drawerBcast').textContent = `${Number(data.in_broadcast).toLocaleString()} / ${Number(data.out_broadcast).toLocaleString()}`;
        document.getElementById('drawerDiscards').textContent = `${Number(data.in_discards + data.out_discards).toLocaleString()} pkts`;

        // Physical Layer
        document.getElementById('drawerSpeed').textContent = data.speed ? `${data.speed} Mbps` : 'Auto';
        document.getElementById('drawerDuplex').textContent = `${data.duplex} / ${data.autoneg ? 'Auto-Neg OK' : 'Fija'}`;
        document.getElementById('drawerModeVlan').textContent = `${data.mode.toUpperCase()} (VLAN ${data.vlan_id || '1'})`;
        document.getElementById('drawerPortChannel').textContent = data.port_channel || '—';
        document.getElementById('drawerMac').textContent = data.mac || 'N/A';
        document.getElementById('drawerLastChange').textContent = data.last_change || 'N/A';

        // Error counters
        document.getElementById('drawerCrc').textContent = data.crc_errors;
        document.getElementById('drawerCollisions').textContent = data.collisions;
        document.getElementById('drawerRunts').textContent = data.runts;
        document.getElementById('drawerGiants').textContent = data.giants;
        document.getElementById('drawerJabbers').textContent = data.jabbers;
        document.getElementById('drawerFcs').textContent = data.fcs_errors;

        // SFP Optical DOM Diagnostics
        const optContainer = document.getElementById('drawerOpticsContainer');
        if (data.port_type.includes('sfp') || data.optica_rx !== null) {
            optContainer.innerHTML = `
                <div class="grid grid-cols-2 gap-3 text-xs font-mono">
                    <div class="bg-white p-2.5 rounded-lg border border-gray-200">
                        <span class="text-gray-400 block text-[10px] font-sans">Potencia RX (Recepción)</span>
                        <strong class="text-sm font-extrabold ${data.optica_rx < -10 ? 'text-amber-600' : 'text-emerald-600'}">${data.optica_rx ?? 'N/A'} dBm</strong>
                        <span class="text-[9px] text-gray-400 block mt-0.5">Rango normal: -1 a -12 dBm</span>
                    </div>
                    <div class="bg-white p-2.5 rounded-lg border border-gray-200">
                        <span class="text-gray-400 block text-[10px] font-sans">Potencia TX (Emisión)</span>
                        <strong class="text-sm font-extrabold text-blue-600">${data.optica_tx ?? 'N/A'} dBm</strong>
                        <span class="text-[9px] text-gray-400 block mt-0.5">Rango normal: -1 a -8 dBm</span>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-[11px] font-mono border-t border-gray-200 pt-2">
                    <div><span class="text-gray-400 font-sans block text-[9px]">Temp Láser</span><strong>${data.optica_temp ?? 'N/A'} °C</strong></div>
                    <div><span class="text-gray-400 font-sans block text-[9px]">Voltaje</span><strong>${data.optica_volt ?? 'N/A'} V</strong></div>
                    <div><span class="text-gray-400 font-sans block text-[9px]">Bias Láser</span><strong>${data.optica_bias ?? 'N/A'} mA</strong></div>
                </div>
            `;
        } else {
            optContainer.innerHTML = `
                <p class="text-xs text-gray-400 italic">Transceptor óptico no aplicable (Puerto de Cobre 1000Base-T)</p>
            `;
        }

        // PoE Telemetry
        const poeContainer = document.getElementById('drawerPoeContainer');
        if (data.is_poe) {
            poeContainer.innerHTML = `
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-gray-700">Consumo de Energía:</span>
                    <strong class="font-mono text-amber-600 text-sm">${data.poe_watts.toFixed(1)} W</strong>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-amber-500 h-2 rounded-full" style="width: ${Math.min((data.poe_watts / (data.poe_max || 30)) * 100, 100)}%"></div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-[11px] font-mono border-t border-gray-200 pt-2 mt-2">
                    <div><span class="text-gray-400 font-sans block text-[9px]">Estado</span><strong class="${data.poe_status === 'Delivering' ? 'text-emerald-600' : 'text-gray-600'}">${data.poe_status}</strong></div>
                    <div><span class="text-gray-400 font-sans block text-[9px]">Clase PoE</span><strong>${data.poe_class}</strong></div>
                    <div><span class="text-gray-400 font-sans block text-[9px]">Voltaje</span><strong>${data.poe_volt} V</strong></div>
                </div>
            `;
        } else {
            poeContainer.innerHTML = `
                <p class="text-xs text-gray-400 italic">PoE inactivo o no requerido por el dispositivo conectado</p>
            `;
        }

        // Err-Disabled box
        const errBox = document.getElementById('drawerErrSection');
        if (data.is_errdisabled) {
            errBox.classList.remove('hidden');
            document.getElementById('drawerErrReason').textContent = data.errdisabled_reason || 'bpduguard';
        } else {
            errBox.classList.add('hidden');
        }

        // Open drawer
        document.getElementById('portDrawer').classList.add('open');
        document.getElementById('drawerOverlay').classList.add('open');
    }

    function closePortDrawer() {
        document.getElementById('portDrawer').classList.remove('open');
        document.getElementById('drawerOverlay').classList.remove('open');
        document.querySelectorAll('.port-element').forEach(el => el.classList.remove('active-selected'));
    }

    // ==================== TABS INTERACTION ====================
    document.querySelectorAll('.nav-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.dataset.tab;
            document.querySelectorAll('.nav-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            this.classList.add('active');
            const targetPane = document.getElementById(target);
            if (targetPane) targetPane.classList.add('active');
        });
    });

    // ==================== REACTIVE MAC SEARCH (CAM TABLE) ====================
    // Normalizes colon (00:1A:2B...) and dot notation (001a.2b...) & case-insensitive
    const macInput = document.getElementById('macSearchInput');
    if (macInput) {
        macInput.addEventListener('input', function() {
            const queryRaw = this.value.trim().toLowerCase();
            // Normalized query removing colons, dots, and dashes
            const queryClean = queryRaw.replace(/[:.\-]/g, '');

            const rows = document.querySelectorAll('.mac-row');
            rows.forEach(row => {
                const mac = (row.dataset.mac || '').toLowerCase();
                const macClean = mac.replace(/[:.\-]/g, '');
                const vlan = (row.dataset.vlan || '').toLowerCase();
                const port = (row.dataset.port || '').toLowerCase();

                const match = mac.includes(queryRaw) || 
                              macClean.includes(queryClean) || 
                              vlan.includes(queryRaw) || 
                              port.includes(queryRaw);

                row.style.display = match ? '' : 'none';
            });
        });
    }

    // Close drawer on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePortDrawer();
        }
    });
</script>
@endsection
