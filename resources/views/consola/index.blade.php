@extends('layouts.app')

@section('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <style>
        /* ======================== NOC CONSOLA CLI STYLES ======================== */
        .noc-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .dark .noc-card {
            background: #0d1117;
            border-color: #1e293b;
            box-shadow: none;
        }

        .noc-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #3b5998 0%, #5c8096 100%);
            color: #ffffff !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 900;
            font-size: 1.2rem;
            box-shadow: 0 4px 14px rgba(59, 89, 152, 0.3);
            flex-shrink: 0;
        }

        .btn-primary-hacienda {
            background: linear-gradient(135deg, #3b5998 0%, #4a6b82 100%) !important;
            color: #ffffff !important;
            border-radius: 12px;
            padding: 9px 18px;
            font-size: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(59, 89, 152, 0.3);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            user-select: none;
        }
        .btn-primary-hacienda:hover {
            background: linear-gradient(135deg, #2d4373 0%, #3b5998 100%) !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(59, 89, 152, 0.4);
        }
        .btn-primary-hacienda:active {
            transform: scale(0.97);
        }

        .btn-secondary-noc {
            background: #f1f5f9;
            color: #334155 !important;
            border-radius: 12px;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            user-select: none;
        }
        .btn-secondary-noc:hover {
            background: #e2e8f0;
            color: #0f172a !important;
        }
        .dark .btn-secondary-noc {
            background: #161b22;
            color: #cbd5e1 !important;
            border-color: #30363d;
        }
        .dark .btn-secondary-noc:hover {
            background: #21262d;
            color: #ffffff !important;
            border-color: #484f58;
        }

        /* Quick Command Buttons */
        .noc-cmd-btn {
            background: #f8fafc;
            color: #1e293b !important;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 6px 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
            white-space: nowrap;
            flex-shrink: 0;
            user-select: none;
        }
        .noc-cmd-btn:hover {
            background: #3b5998 !important;
            color: #ffffff !important;
            border-color: #3b5998 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 89, 152, 0.3);
        }
        .noc-cmd-btn:hover svg {
            color: #ffffff !important;
        }
        .dark .noc-cmd-btn {
            background: #161b22;
            color: #e2e8f0 !important;
            border-color: #30363d;
        }
        .dark .noc-cmd-btn:hover {
            background: #3b82f6 !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        /* Custom Terminal Chassis & Scrollbar */
        .terminal-chassis {
            background: #080c14;
            border: 1px solid #1e293b;
            border-radius: 18px;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.7);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .dark .terminal-chassis {
            background: #05070c;
            border-color: #1e2638;
        }
        .terminal-header {
            background: #0d121d;
            border-bottom: 1px solid #1e293b;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dark .terminal-header {
            background: #080c14;
            border-bottom-color: #182030;
        }
        #terminal-container {
            width: 100%;
            height: 520px;
            background-color: #080c14;
            padding: 14px 16px;
        }
        .xterm-viewport {
            background-color: #080c14 !important;
        }
        .xterm-viewport::-webkit-scrollbar {
            width: 8px;
        }
        .xterm-viewport::-webkit-scrollbar-track {
            background: #080c14;
        }
        .xterm-viewport::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }
        .xterm-viewport::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }
    </style>
@endsection

@section('content')
<div class="flex flex-col h-full space-y-4 max-w-7xl mx-auto" x-data="consoleComponent()">

    {{-- ==================== 1. TOP HEADER & DEVICE SELECTOR ==================== --}}
    <div class="noc-card p-5 lg:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 flex-shrink-0 relative z-30">
        {{-- Left Title & Icon --}}
        <div class="flex items-center gap-4">
            <div class="noc-icon-box">
                &gt;_
            </div>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl lg:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Consola Segura CLI & Terminal</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span x-text="sessionActive ? 'SSH Bridge Activo' : 'SSH Bridge Listo'">SSH Bridge Listo</span>
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
                    Terminal interactiva para ejecutar diagnósticos y comandos en tiempo real
                </p>
            </div>
        </div>

        {{-- Right Controls: Searchable Device Dropdown & SSH Credentials Button --}}
        <div class="flex flex-wrap items-center gap-3">
            
            {{-- Searchable Device Dropdown Menu --}}
            <div class="relative min-w-[280px] sm:min-w-[360px]" @click.outside="deviceDropdownOpen = false">
                <input type="hidden" id="dispositivo_id" :value="selectedDeviceId">
                
                {{-- Selector Button Trigger --}}
                <button type="button" @click="deviceDropdownOpen = !deviceDropdownOpen" 
                        class="w-full py-2.5 pl-3.5 pr-10 rounded-xl bg-slate-50 dark:bg-[#161b22] border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-[#3b5998] transition shadow-xs flex items-center justify-between text-left">
                    <div class="flex items-center gap-2.5 min-w-0 pr-2">
                        <template x-if="currentDeviceType === 'firewall' || currentDeviceType === 'fortigate'">
                            <span class="p-1.5 rounded-lg bg-rose-500/10 text-rose-500 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            </span>
                        </template>
                        <template x-if="currentDeviceType === 'router'">
                            <span class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-500 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            </span>
                        </template>
                        <template x-if="currentDeviceType !== 'firewall' && currentDeviceType !== 'fortigate' && currentDeviceType !== 'router'">
                            <span class="p-1.5 rounded-lg bg-[#3b5998]/10 text-[#3b5998] dark:text-blue-400 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            </span>
                        </template>
                        <div class="truncate">
                            <span class="font-extrabold text-slate-900 dark:text-white" x-text="currentDeviceName">Switch</span>
                            <span class="text-slate-400 font-normal font-mono text-[11px]" x-text="' (' + currentDeviceIp + ')'"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="w-2.5 h-2.5 rounded-full" :class="currentDeviceStatus === 'online' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.7)]' : 'bg-slate-400'"></span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{'rotate-180': deviceDropdownOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </button>

                {{-- Searchable Dropdown Popup --}}
                <div x-show="deviceDropdownOpen" 
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-1"
                     class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-[#12161f] rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700/80 overflow-hidden z-50 p-2.5 max-h-[380px] flex flex-col"
                     style="display: none;">
                    
                    {{-- Input de Filtro / Buscador en Vivo --}}
                    <div class="relative mb-2 shrink-0">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" x-model="deviceSearchQuery" 
                               placeholder="Buscar por nombre, IP, modelo..."
                               class="w-full pl-8 pr-7 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-[#090b10] border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3b5998]">
                        <button type="button" x-show="deviceSearchQuery" @click="deviceSearchQuery = ''" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    {{-- Filtros Rápidos de Categoría --}}
                    <div class="flex items-center gap-1 mb-2 pb-2 border-b border-slate-100 dark:border-slate-800 overflow-x-auto shrink-0 custom-scrollbar text-[10px] font-bold">
                        <button type="button" @click="deviceFilterType = 'all'" :class="deviceFilterType === 'all' ? 'bg-[#3b5998] text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Todos</button>
                        <button type="button" @click="deviceFilterType = 'switch'" :class="deviceFilterType === 'switch' ? 'bg-[#3b5998] text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Switches</button>
                        <button type="button" @click="deviceFilterType = 'firewall'" :class="deviceFilterType === 'firewall' ? 'bg-[#3b5998] text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Firewalls</button>
                        <button type="button" @click="deviceFilterType = 'router'" :class="deviceFilterType === 'router' ? 'bg-[#3b5998] text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Routers</button>
                    </div>

                    {{-- Lista de Dispositivos Filtrados --}}
                    <div class="overflow-y-auto custom-scrollbar flex-1 space-y-1">
                        <template x-for="d in filteredDevices" :key="d.id">
                            <div @click="selectDevice(d)" 
                                 class="p-2.5 rounded-xl flex items-center justify-between cursor-pointer transition text-xs"
                                 :class="selectedDeviceId == d.id ? 'bg-blue-50 dark:bg-blue-950/50 border border-[#3b5998]/40' : 'hover:bg-slate-100 dark:hover:bg-slate-800/70 border border-transparent'">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <template x-if="d.tipo && (d.tipo.toLowerCase().includes('firewall') || d.tipo.toLowerCase().includes('forti'))">
                                        <span class="p-1.5 rounded-lg bg-rose-500/10 text-rose-500 shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                        </span>
                                    </template>
                                    <template x-if="d.tipo && d.tipo.toLowerCase().includes('router')">
                                        <span class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-500 shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                        </span>
                                    </template>
                                    <template x-if="!d.tipo || (!d.tipo.toLowerCase().includes('firewall') && !d.tipo.toLowerCase().includes('forti') && !d.tipo.toLowerCase().includes('router'))">
                                        <span class="p-1.5 rounded-lg bg-[#3b5998]/10 text-[#3b5998] dark:text-blue-400 shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                        </span>
                                    </template>
                                    <div class="truncate">
                                        <p class="font-bold text-slate-800 dark:text-slate-100 truncate" x-text="d.nombre"></p>
                                        <p class="text-[10px] text-slate-400 font-mono truncate" x-text="d.ip + ' · ' + (d.tipo ? d.tipo.toUpperCase() : 'SWITCH')"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold"
                                          :class="d.estado === 'online' ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-400'"
                                          x-text="d.estado === 'online' ? 'ONLINE' : 'OFFLINE'"></span>
                                </div>
                            </div>
                        </template>

                        <div x-show="filteredDevices.length === 0" class="p-4 text-center text-xs text-slate-400">
                            No se encontraron dispositivos coincidentes.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Botón Visible: Credenciales SSH --}}
            <button type="button" @click="openLoginModal = true" class="btn-primary-hacienda" title="Configurar credenciales SSH del equipo">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                <span>Credenciales SSH</span>
            </button>

            {{-- Botón Visible: Limpiar Terminal --}}
            <button type="button" onclick="window.clearTerminalScreen()" class="btn-secondary-noc" title="Limpiar Pantalla (Ctrl + L)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                <span class="hidden sm:inline">Limpiar</span>
            </button>
        </div>
    </div>

    {{-- ==================== 2. QUICK COMMANDS BAR ==================== --}}
    <div class="noc-card p-3 sm:p-3.5 flex-shrink-0 flex items-center gap-2 overflow-x-auto custom-scrollbar">
        <span class="text-[11px] font-mono font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider shrink-0 mr-1 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-[#3b5998] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            Comandos Rápidos:
        </span>
        
        <button type="button" onclick="window.runPresetCommand('show version')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>show version</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('show ip interface brief')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span>show ip int brief</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('show running-config')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span>show running-config</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('get system status')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
            <span>get sys status</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('diagnose sys session stat')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span>diagnose sys session</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('show mac address-table')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
            <span>show mac</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('ping 10.4.1.1')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span>ping 10.4.1.1</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('help')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>help / ?</span>
        </button>
    </div>

    {{-- ==================== 3. TERMINAL CHASSIS ==================== --}}
    <div class="terminal-chassis flex-1 flex flex-col relative min-h-[480px]">
        {{-- Terminal Window Header --}}
        <div class="terminal-header">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-[#ef4444] inline-block shadow-sm"></span>
                    <span class="w-3 h-3 rounded-full bg-[#f59e0b] inline-block shadow-sm"></span>
                    <span class="w-3 h-3 rounded-full bg-[#10b981] inline-block shadow-sm"></span>
                </div>
                <div class="h-4 w-px bg-slate-700/60 mx-1"></div>
                <span class="text-xs font-mono text-slate-300 flex items-center gap-2">
                    <span id="termHeaderTarget" class="text-slate-100 font-bold" x-text="currentDeviceName + ' (' + currentDeviceIp + ')'">Terminal CLI</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-slate-400 text-[11px] hidden sm:inline">Paramiko SSH-2.0 / xterm.js · UTF-8</span>
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span id="termStatusBadge" class="inline-flex items-center gap-1.5 text-[11px] font-mono text-emerald-400 bg-emerald-950/80 border border-emerald-800/80 px-2.5 py-0.5 rounded-lg">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span id="termSessionText" x-text="sessionActive ? 'SSH CONECTADO' : 'SSH LISTO'">SSH LISTO</span>
                </span>
            </div>
        </div>

        {{-- xterm.js Canvas Container --}}
        <div class="flex-1 w-full relative">
            <div id="terminal-container"></div>
        </div>
    </div>

    {{-- ==================== 4. AUDIT FOOTER ==================== --}}
    <div class="noc-card p-4 sm:p-5 flex-shrink-0">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-[#3b5998]/10 text-[#3b5998] dark:text-blue-400 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                Auditoría de Comandos Ejecutados
            </span>
            <span class="text-[11px] text-slate-400 font-mono font-medium flex items-center gap-1">
                <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                SOC Compliance Activo
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
            @forelse($historial->take(3) as $h)
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-[#161b22] border border-slate-200/60 dark:border-slate-800 flex items-center justify-between text-xs transition hover:border-slate-300 dark:hover:border-slate-700">
                    <div class="min-w-0 flex items-center gap-2">
                        <div class="p-1 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-500 shrink-0 font-mono text-[10px] font-bold">
                            &gt;_
                        </div>
                        <div class="min-w-0">
                            <p class="font-mono font-bold text-[#3b5998] dark:text-blue-400 truncate">&gt; {{ $h->comando_solicitado }}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $h->dispositivo?->nombre ?? 'N/A' }} · {{ $h->user?->name ?? 'Admin' }}</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400 shrink-0 ml-2 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200/50 dark:border-slate-700/50">{{ $h->fecha_ejecucion ? \Carbon\Carbon::parse($h->fecha_ejecucion)->format('H:i:s') : 'Hoy' }}</span>
                </div>
            @empty
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-[#161b22] border border-slate-200/60 dark:border-slate-800 col-span-3 text-center text-xs text-slate-400">
                    No hay registros recientes de auditoría para este dispositivo.
                </div>
            @endforelse
        </div>
    </div>

    {{-- ==================== MODAL DE AUTENTICACIÓN / LOGIN SSH ==================== --}}
    <div x-show="openLoginModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm"
         style="display: none;">
        
        <div @click.outside="openLoginModal = false"
             class="bg-white dark:bg-[#12161f] border border-slate-200 dark:border-slate-700 rounded-[2rem] p-6 sm:p-7 shadow-2xl max-w-md w-full relative">
            
            {{-- Encabezado Modal --}}
            <div class="flex items-center justify-between mb-5 pb-3.5 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-[#3b5998] text-white flex items-center justify-center shadow-md shadow-[#3b5998]/30">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">Credenciales de Acceso SSH</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium" x-text="currentDeviceName + ' (' + currentDeviceIp + ')'"></p>
                    </div>
                </div>
                <button type="button" @click="openLoginModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            {{-- Formulario de Credenciales con Íconos --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Puerto SSH (TCP):
                    </label>
                    <input type="number" x-model="sshPort" 
                           class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-[#090b10] border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#3b5998] focus:outline-none transition shadow-xs font-bold"
                           placeholder="22">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Usuario SSH (Username):
                    </label>
                    <input type="text" x-model="sshUser" 
                           class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-[#090b10] border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#3b5998] focus:outline-none transition shadow-xs font-bold"
                           placeholder="admin / cisco / root">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Contraseña SSH (Password):
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" x-model="sshPassword" 
                               class="w-full px-3.5 py-2.5 pr-10 text-xs font-mono rounded-xl bg-slate-50 dark:bg-[#090b10] border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#3b5998] focus:outline-none transition shadow-xs font-bold"
                               placeholder="Ingrese la clave SSH">
                        <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 pt-1">
                    <input type="checkbox" id="saveCreds" x-model="saveCredentials" class="w-4 h-4 rounded text-[#3b5998] focus:ring-[#3b5998] bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700">
                    <label for="saveCreds" class="text-xs text-slate-600 dark:text-slate-400 font-medium cursor-pointer">
                        Guardar credenciales cifradas para este equipo
                    </label>
                </div>

                <div x-show="loginStatusMsg" class="p-3 rounded-xl text-xs font-mono" :class="loginSuccess ? 'bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-500 dark:text-rose-400 border border-rose-500/30'">
                    <span x-text="loginStatusMsg"></span>
                </div>
            </div>

            {{-- Botones de Acción --}}
            <div class="flex items-center justify-end gap-2.5 mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="openLoginModal = false" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    Cancelar
                </button>
                <button type="button" @click="testAndConnectSSH()" :disabled="isConnecting" class="btn-primary-hacienda disabled:opacity-50">
                    <svg x-show="isConnecting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-text="isConnecting ? 'Autenticando SSH...' : 'Conectar y Validar SSH'">Conectar y Validar SSH</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
    
    <script>
        window.devicesMap = @json($dispositivosData);

        function consoleComponent() {
            return {
                openLoginModal: false,
                deviceDropdownOpen: false,
                deviceSearchQuery: '',
                deviceFilterType: 'all',
                selectedDeviceId: '{{ $dispositivoSeleccionado?->id ?? '' }}',
                currentDeviceName: '{{ $dispositivoSeleccionado?->nombre ?? 'Switch' }}',
                currentDeviceIp: '{{ $dispositivoSeleccionado?->ip ?? '10.4.1.5' }}',
                currentDeviceType: '{{ strtolower($dispositivoSeleccionado?->tipo ?? 'switch') }}',
                currentDeviceStatus: '{{ $dispositivoSeleccionado?->estado ?? 'online' }}',
                sshUser: 'admin',
                sshPassword: '',
                sshPort: 22,
                saveCredentials: true,
                showPassword: false,
                isConnecting: false,
                loginStatusMsg: '',
                loginSuccess: false,
                sessionActive: false,

                get filteredDevices() {
                    let list = window.devicesMap || [];
                    
                    // Filtro de tipo
                    if (this.deviceFilterType !== 'all') {
                        list = list.filter(d => {
                            const t = (d.tipo || '').toLowerCase();
                            if (this.deviceFilterType === 'firewall') return t.includes('firewall') || t.includes('forti') || t.includes('fw');
                            if (this.deviceFilterType === 'router') return t.includes('router');
                            if (this.deviceFilterType === 'switch') return !t.includes('firewall') && !t.includes('forti') && !t.includes('router');
                            return true;
                        });
                    }

                    // Filtro de búsqueda por texto
                    if (this.deviceSearchQuery.trim()) {
                        const q = this.deviceSearchQuery.toLowerCase().trim();
                        list = list.filter(d => {
                            return (d.nombre && d.nombre.toLowerCase().includes(q)) ||
                                   (d.ip && d.ip.toLowerCase().includes(q)) ||
                                   (d.tipo && d.tipo.toLowerCase().includes(q));
                        });
                    }

                    return list;
                },

                init() {
                    this.onDeviceChange();
                },

                selectDevice(dev) {
                    this.selectedDeviceId = dev.id;
                    this.deviceDropdownOpen = false;
                    this.deviceSearchQuery = '';
                    this.onDeviceChange();
                },

                onDeviceChange() {
                    const dev = (window.devicesMap || []).find(d => d.id == this.selectedDeviceId);
                    if (dev) {
                        this.currentDeviceName = dev.nombre;
                        this.currentDeviceIp = dev.ip;
                        this.currentDeviceType = (dev.tipo || 'switch').toLowerCase();
                        this.currentDeviceStatus = dev.estado || 'online';
                        this.sshUser = dev.ssh_user || 'admin';
                        this.sshPort = dev.ssh_port || 22;
                        this.sshPassword = '';
                        this.loginStatusMsg = '';
                        this.sessionActive = dev.has_password;
                    }
                    if (window.initTerminalSession) {
                        window.initTerminalSession();
                    }
                },

                testAndConnectSSH() {
                    this.isConnecting = true;
                    this.loginStatusMsg = 'Iniciando handshake SSH y autenticando...';
                    this.loginSuccess = false;

                    fetch('{{ route('consola.probar_conexion') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            dispositivo_id: this.selectedDeviceId,
                            ssh_user: this.sshUser,
                            ssh_password: this.sshPassword,
                            ssh_port: this.sshPort,
                            guardar_credenciales: this.saveCredentials
                        })
                    })
                    .then(res => res.json().then(data => ({ ok: res.ok, body: data })))
                    .then(res => {
                        if (res.ok) {
                            this.loginSuccess = true;
                            this.sessionActive = true;
                            this.loginStatusMsg = '✔ ' + (res.body.message || 'Sesión SSH iniciada con éxito.');
                            
                            // Imprimir en la terminal xterm
                            if (window.term) {
                                window.term.write('\r\n\x1b[1;32m[SSH AUTH OK] Conexión establecida con éxito como ' + this.sshUser + '@' + this.currentDeviceIp + ':' + this.sshPort + '\x1b[0m\r\n');
                                if (res.body.output) {
                                    window.term.write(res.body.output.replace(/\n/g, '\r\n') + '\r\n');
                                }
                                window.writePrompt();
                            }

                            setTimeout(() => {
                                this.openLoginModal = false;
                            }, 1200);
                        } else {
                            this.loginSuccess = false;
                            this.loginStatusMsg = '✖ ' + (res.body.message || res.body.error || 'Fallo de autenticación SSH.');
                            if (window.term) {
                                window.term.write('\r\n\x1b[1;31m[SSH AUTH ERROR] ' + (res.body.message || res.body.error || 'Credenciales rechazadas.') + '\x1b[0m\r\n');
                                window.writePrompt();
                            }
                        }
                    })
                    .catch(err => {
                        this.loginSuccess = false;
                        this.loginStatusMsg = '✖ Error de red: no se pudo comunicar con el servidor.';
                    })
                    .finally(() => {
                        this.isConnecting = false;
                    });
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const term = new Terminal({
                cursorBlink: true,
                theme: { 
                    background: '#080c14', 
                    foreground: '#f1f5f9',
                    cursor: '#38bdf8',
                    selection: 'rgba(59, 130, 246, 0.35)',
                    black: '#0f172a',
                    red: '#f87171',
                    green: '#34d399',
                    yellow: '#fbbf24',
                    blue: '#60a5fa',
                    magenta: '#c084fc',
                    cyan: '#38bdf8',
                    white: '#ffffff',
                    brightBlack: '#475569',
                    brightRed: '#fca5a5',
                    brightGreen: '#6ee7b7',
                    brightYellow: '#fde047',
                    brightBlue: '#93c5fd',
                    brightMagenta: '#d8b4fe',
                    brightCyan: '#67e8f9',
                    brightWhite: '#ffffff'
                },
                fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
                fontSize: 13.5,
                lineHeight: 1.35,
                scrollback: 1000
            });

            window.term = term;

            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            
            const container = document.getElementById('terminal-container');
            term.open(container);
            
            setTimeout(() => {
                fitAddon.fit();
            }, 100);

            let currentLine = '';
            let isExecuting = false;
            let currentHostname = 'switch# ';
            let cmdHistory = [];
            let historyIndex = -1;

            function getPromptForDevice(name) {
                const clean = (name || 'switch').split(' ')[0].replace(/[^a-zA-Z0-9_-]/g, '');
                if (clean.toLowerCase().includes('forti') || clean.toLowerCase().startsWith('fg') || clean.toLowerCase().startsWith('fw')) {
                    return clean + ' # ';
                }
                return clean + '# ';
            }

            window.writePrompt = function() {
                term.write('\r\n\x1b[1;32m' + currentHostname + '\x1b[0m');
            };

            window.initTerminalSession = function() {
                const hiddenInput = document.getElementById('dispositivo_id');
                const devId = hiddenInput ? hiddenInput.value : '';
                const dev = (window.devicesMap || []).find(d => d.id == devId);
                
                if (dev) {
                    const devTitle = dev.nombre + ' (' + dev.ip + ') — ' + (dev.tipo ? dev.tipo.toUpperCase() : 'SWITCH');
                    currentHostname = getPromptForDevice(dev.nombre);
                    
                    term.clear();
                    term.write('\x1b[38;2;59;89;152m====================================================================================\x1b[0m\r\n');
                    term.write('\x1b[1;37m  CONSOLA SEGURA CLI & TERMINAL INTERACTIVA NOC (SSH ENGINE)\x1b[0m\r\n');
                    term.write('\x1b[38;2;59;89;152m====================================================================================\x1b[0m\r\n');
                    term.write('\x1b[90mEquipo Destino : \x1b[38;2;96;165;250m' + devTitle + '\x1b[0m\r\n');
                    term.write('\x1b[32m✔ Shell interactiva lista. Para conectar con SSH real haga clic en [Credenciales SSH].\x1b[0m\r\n');
                    term.write('\x1b[90mEscriba un comando directamente o seleccione una de las opciones rápidas arriba.\x1b[0m\r\n');
                    window.writePrompt();
                }
            };

            window.initTerminalSession();

            // Manejo interactivo de teclado xterm
            term.onData(e => {
                const hiddenInput = document.getElementById('dispositivo_id');
                if (isExecuting || !hiddenInput || !hiddenInput.value) return;

                switch (e) {
                    case '\r': // Enter
                        if (currentLine.trim() !== '') {
                            cmdHistory.push(currentLine.trim());
                            historyIndex = cmdHistory.length;
                            window.executeCommand(currentLine.trim());
                        } else {
                            window.writePrompt();
                        }
                        currentLine = '';
                        break;
                    case '\u007F': // Backspace
                        if (currentLine.length > 0) {
                            currentLine = currentLine.substring(0, currentLine.length - 1);
                            term.write('\b \b');
                        }
                        break;
                    case '\u001b[A': // Arrow Up
                        if (cmdHistory.length > 0 && historyIndex > 0) {
                            historyIndex--;
                            while (currentLine.length > 0) {
                                term.write('\b \b');
                                currentLine = currentLine.substring(0, currentLine.length - 1);
                            }
                            currentLine = cmdHistory[historyIndex];
                            term.write(currentLine);
                        }
                        break;
                    case '\u001b[B': // Arrow Down
                        if (cmdHistory.length > 0 && historyIndex < cmdHistory.length - 1) {
                            historyIndex++;
                            while (currentLine.length > 0) {
                                term.write('\b \b');
                                currentLine = currentLine.substring(0, currentLine.length - 1);
                            }
                            currentLine = cmdHistory[historyIndex];
                            term.write(currentLine);
                        } else if (historyIndex >= cmdHistory.length - 1) {
                            historyIndex = cmdHistory.length;
                            while (currentLine.length > 0) {
                                term.write('\b \b');
                                currentLine = currentLine.substring(0, currentLine.length - 1);
                            }
                        }
                        break;
                    case '\u0003': // Ctrl + C
                        term.write('^C');
                        currentLine = '';
                        window.writePrompt();
                        break;
                    case '\u000c': // Ctrl + L
                        window.clearTerminalScreen();
                        break;
                    default:
                        if (e >= String.fromCharCode(0x20) && e <= String.fromCharCode(0x7E) || e >= '\u00a0') {
                            currentLine += e;
                            term.write(e);
                        }
                }
            });

            window.executeCommand = function(comando) {
                if (comando.toLowerCase() === 'clear' || comando.toLowerCase() === 'cls') {
                    window.clearTerminalScreen();
                    return;
                }

                isExecuting = true;
                const hiddenInput = document.getElementById('dispositivo_id');
                const dispositivoId = hiddenInput ? hiddenInput.value : null;
                term.write('\r\n');

                fetch('{{ route('consola.ejecutar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        dispositivo_id: dispositivoId,
                        comando: comando
                    })
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(res => {
                    if (res.status === 200) {
                        const rawOut = res.body.output || '';
                        const outputLines = rawOut.replace(/\r\n/g, '\n').split('\n');
                        outputLines.forEach(line => {
                            term.write(line + '\r\n');
                        });
                    } else {
                        term.write('\x1b[1;31mError (' + res.status + '): ' + (res.body.error || res.body.message || 'Fallo de ejecución') + '\x1b[0m\r\n');
                    }
                })
                .catch(error => {
                    term.write('\x1b[1;31mError de Red: No se pudo comunicar con el endpoint CLI.\x1b[0m\r\n');
                })
                .finally(() => {
                    isExecuting = false;
                    window.writePrompt();
                });
            };

            window.runPresetCommand = function(cmd) {
                const hiddenInput = document.getElementById('dispositivo_id');
                if (isExecuting || !hiddenInput || !hiddenInput.value) return;
                term.write(cmd);
                currentLine = cmd;
                cmdHistory.push(cmd);
                historyIndex = cmdHistory.length;
                window.executeCommand(cmd);
                currentLine = '';
            };

            window.clearTerminalScreen = function() {
                term.clear();
                window.writePrompt();
            };

            window.addEventListener('resize', () => {
                fitAddon.fit();
            });
        });
    </script>
@endsection
