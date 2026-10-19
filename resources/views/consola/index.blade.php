@extends('layouts.app')

@section('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <style>
        /* ======================== NOC CONSOLA CLI STYLES ======================== */
        .noc-card {
            background: var(--nm-surface);
            border: 1px solid var(--nm-border);
            border-radius: var(--nm-card-radius);
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
            border-radius: var(--nm-radius-md);
            background: linear-gradient(135deg, var(--nm-primary) 0%, var(--nm-primary-2) 100%);
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
            background: linear-gradient(135deg, var(--nm-primary) 0%, var(--nm-primary-2) 100%) !important;
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
            background: linear-gradient(135deg, #0a1d4a 0%, var(--nm-primary) 100%) !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(59, 89, 152, 0.4);
        }
        .btn-primary-hacienda:active {
            transform: scale(0.97);
        }

        .btn-secondary-noc {
            background: var(--nm-sunken);
            color: var(--nm-text) !important;
            border-radius: 12px;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--nm-border-strong);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            user-select: none;
        }
        .btn-secondary-noc:hover {
            background: #e2e8f0;
            color: var(--nm-text) !important;
        }
        .dark .btn-secondary-noc {
            background: var(--nm-surface);
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
            background: var(--nm-sunken);
            color: var(--nm-text) !important;
            border: 1px solid var(--nm-border-strong);
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
            background: var(--nm-primary) !important;
            color: #ffffff !important;
            border-color: var(--nm-primary) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 89, 152, 0.3);
        }
        .noc-cmd-btn:hover svg {
            color: #ffffff !important;
        }
        .dark .noc-cmd-btn {
            background: var(--nm-surface);
            color: #e2e8f0 !important;
            border-color: #30363d;
        }
        .dark .noc-cmd-btn:hover {
            background: #1a7afb !important;
            color: #ffffff !important;
            border-color: #1a7afb !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        /* Custom Terminal Chassis & Scrollbar */
        .terminal-chassis {
            background: var(--nm-ink);
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
            background: var(--nm-sunken);
            border-bottom-color: #182030;
        }
        #terminal-container {
            width: 100%;
            height: 520px;
            background-color: var(--nm-ink);
            padding: 14px 16px;
        }
        .xterm-viewport {
            background-color: var(--nm-ink) !important;
        }
        .xterm-viewport::-webkit-scrollbar {
            width: 8px;
        }
        .xterm-viewport::-webkit-scrollbar-track {
            background: var(--nm-ink);
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
                <svg class="w-6 h-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m7 11 2-2-2-2" /> <path d="M11 13h4" /> <rect width="18" height="18" x="3" y="3" rx="2" ry="2" /></svg>
            </div>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl lg:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Consola Segura CLI & Terminal</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
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
                        class="w-full py-2.5 pl-3.5 pr-10 rounded-xl bg-slate-50 dark:bg-nm-surface border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-nm-primary transition shadow-xs flex items-center justify-between text-left">
                    <div class="flex items-center gap-2.5 min-w-0 pr-2">
                        <template x-if="currentDeviceType === 'firewall' || currentDeviceType === 'fortigate'">
                            <span class="p-1.5 rounded-lg bg-rose-500/10 text-rose-500 shrink-0">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                            </span>
                        </template>
                        <template x-if="currentDeviceType === 'router'">
                            <span class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-500 shrink-0">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3 4 7l4 4" /> <path d="M4 7h16" /> <path d="m16 21 4-4-4-4" /> <path d="M20 17H4" /></svg>
                            </span>
                        </template>
                        <template x-if="currentDeviceType !== 'firewall' && currentDeviceType !== 'fortigate' && currentDeviceType !== 'router'">
                            <span class="p-1.5 rounded-lg bg-nm-primary/10 text-nm-primary dark:text-blue-400 shrink-0">
                                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /> <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /> <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" /></svg>
                            </span>
                        </template>
                        <div class="truncate">
                            <span class="font-extrabold text-slate-900 dark:text-white" x-text="currentDeviceName">Switch</span>
                            <span class="text-slate-400 font-normal font-mono text-[11px]" x-text="' (' + currentDeviceIp + ')'"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="w-2.5 h-2.5 rounded-full" :class="currentDeviceStatus === 'online' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.7)]' : 'bg-slate-400'"></span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{'rotate-180': deviceDropdownOpen}" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
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
                     class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-nm-surface rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700/80 overflow-hidden z-50 p-2.5 max-h-[380px] flex flex-col"
                     style="display: none;">
                    
                    {{-- Input de Filtro / Buscador en Vivo --}}
                    <div class="relative mb-2 shrink-0">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" /></svg>
                        </div>
                        <input type="text" x-model="deviceSearchQuery" 
                               placeholder="Buscar por nombre, IP, modelo..."
                               class="w-full pl-8 pr-7 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-nm-primary">
                        <button type="button" x-show="deviceSearchQuery" @click="deviceSearchQuery = ''" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                        </button>
                    </div>

                    {{-- Filtros Rápidos de Categoría --}}
                    <div class="flex items-center gap-1 mb-2 pb-2 border-b border-slate-100 dark:border-slate-800 overflow-x-auto shrink-0 custom-scrollbar text-[11px] font-bold">
                        <button type="button" @click="deviceFilterType = 'all'" :class="deviceFilterType === 'all' ? 'bg-nm-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Todos</button>
                        <button type="button" @click="deviceFilterType = 'switch'" :class="deviceFilterType === 'switch' ? 'bg-nm-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Switches</button>
                        <button type="button" @click="deviceFilterType = 'firewall'" :class="deviceFilterType === 'firewall' ? 'bg-nm-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Firewalls</button>
                        <button type="button" @click="deviceFilterType = 'router'" :class="deviceFilterType === 'router' ? 'bg-nm-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'" class="px-2.5 py-1 rounded-lg transition shrink-0">Routers</button>
                    </div>

                    {{-- Lista de Dispositivos Filtrados --}}
                    <div class="overflow-y-auto custom-scrollbar flex-1 space-y-1">
                        <template x-for="d in filteredDevices" :key="d.id">
                            <div @click="selectDevice(d)" 
                                 class="p-2.5 rounded-xl flex items-center justify-between cursor-pointer transition text-xs"
                                 :class="selectedDeviceId == d.id ? 'bg-blue-50 dark:bg-blue-950/50 border border-nm-primary/40' : 'hover:bg-slate-100 dark:hover:bg-slate-800/70 border border-transparent'">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <template x-if="d.tipo && (d.tipo.toLowerCase().includes('firewall') || d.tipo.toLowerCase().includes('forti'))">
                                        <span class="p-1.5 rounded-lg bg-rose-500/10 text-rose-500 shrink-0">
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                                        </span>
                                    </template>
                                    <template x-if="d.tipo && d.tipo.toLowerCase().includes('router')">
                                        <span class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-500 shrink-0">
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3 4 7l4 4" /> <path d="M4 7h16" /> <path d="m16 21 4-4-4-4" /> <path d="M20 17H4" /></svg>
                                        </span>
                                    </template>
                                    <template x-if="!d.tipo || (!d.tipo.toLowerCase().includes('firewall') && !d.tipo.toLowerCase().includes('forti') && !d.tipo.toLowerCase().includes('router'))">
                                        <span class="p-1.5 rounded-lg bg-nm-primary/10 text-nm-primary dark:text-blue-400 shrink-0">
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /> <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /> <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" /></svg>
                                        </span>
                                    </template>
                                    <div class="truncate">
                                        <p class="font-bold text-slate-800 dark:text-slate-100 truncate" x-text="d.nombre"></p>
                                        <p class="text-[11px] text-slate-400 font-mono truncate" x-text="d.ip + ' · ' + (d.tipo ? d.tipo.toUpperCase() : 'SWITCH')"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold"
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
            <button type="button" @click="openLoginModal = true" class="btn-primary-hacienda" title="Configurar credenciales SSH del equipo" aria-label="Configurar credenciales SSH del equipo">
                <svg class="w-4 h-4 text-white" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z" /> <circle cx="16.5" cy="7.5" r=".5" fill="currentColor" /></svg>
                <span>Credenciales SSH</span>
            </button>

            {{-- Botón Visible: Limpiar Terminal --}}
            <button type="button" onclick="window.clearTerminalScreen()" class="btn-secondary-noc" title="Limpiar Pantalla (Ctrl + L)" aria-label="Limpiar Pantalla (Ctrl + L)">
                <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /> <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /> <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" /> <line x1="10" x2="10" y1="11" y2="17" /> <line x1="14" x2="14" y1="11" y2="17" /></svg>
                <span class="hidden sm:inline">Limpiar</span>
            </button>
        </div>
    </div>

    {{-- ==================== 2. QUICK COMMANDS BAR ==================== --}}
    <div class="noc-card p-3 sm:p-3.5 flex-shrink-0 flex items-center gap-2 overflow-x-auto custom-scrollbar">
        <span class="text-[11px] font-mono font-bold text-slate-400 dark:text-slate-400 uppercase tracking-wider shrink-0 mr-1 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-nm-primary dark:text-blue-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
            Comandos Rápidos:
        </span>
        
        <button type="button" onclick="window.runPresetCommand('show version')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-blue-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" /></svg>
            <span>show version</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('show ip interface brief')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m7 11 2-2-2-2" /> <path d="M11 13h4" /> <rect width="18" height="18" x="3" y="3" rx="2" ry="2" /></svg>
            <span>show ip int brief</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('show running-config')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-purple-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /> <path d="M14 2v4a2 2 0 0 0 2 2h4" /> <path d="M10 9H8" /> <path d="M16 13H8" /> <path d="M16 17H8" /></svg>
            <span>show running-config</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('get system status')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-rose-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="4" rx="2" /> <rect width="6" height="6" x="9" y="9" rx="1" /> <path d="M15 2v2" /> <path d="M15 20v2" /> <path d="M2 15h2" /> <path d="M2 9h2" /> <path d="M20 15h2" /> <path d="M20 9h2" /> <path d="M9 2v2" /> <path d="M9 20v2" /></svg>
            <span>get sys status</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('diagnose sys session stat')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-amber-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
            <span>diagnose sys session</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('show mac address-table')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-cyan-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z" /> <circle cx="7.5" cy="7.5" r=".5" fill="currentColor" /></svg>
            <span>show mac</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('ping 10.4.1.1')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-indigo-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" /></svg>
            <span>ping 10.4.1.1</span>
        </button>

        <button type="button" onclick="window.runPresetCommand('help')" class="noc-cmd-btn">
            <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /> <path d="M12 17h.01" /></svg>
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
                <div class="w-6 h-6 rounded-lg bg-nm-primary/10 text-nm-primary dark:text-blue-400 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" /></svg>
                </div>
                Auditoría de Comandos Ejecutados
            </span>
            <span class="text-[11px] text-slate-400 font-mono font-medium flex items-center gap-1">
                <svg class="w-3 h-3 text-emerald-500" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                SOC Compliance Activo
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
            @forelse($historial->take(3) as $h)
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-nm-surface border border-slate-200/60 dark:border-slate-800 flex items-center justify-between text-xs transition hover:border-slate-300 dark:hover:border-slate-700">
                    <div class="min-w-0 flex items-center gap-2">
                        <div class="p-1 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-500 shrink-0 font-mono text-[11px] font-bold">
                            &gt;_
                        </div>
                        <div class="min-w-0">
                            <p class="font-mono font-bold text-nm-primary dark:text-blue-400 truncate">&gt; {{ $h->comando_solicitado }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $h->dispositivo?->nombre ?? 'N/A' }} · {{ $h->user?->name ?? 'Admin' }}</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400 shrink-0 ml-2 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200/50 dark:border-slate-700/50">{{ $h->fecha_ejecucion ? \Carbon\Carbon::parse($h->fecha_ejecucion)->format('H:i:s') : 'Hoy' }}</span>
                </div>
            @empty
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-nm-surface border border-slate-200/60 dark:border-slate-800 col-span-3 text-center text-xs text-slate-400">
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
             class="bg-white dark:bg-nm-surface border border-slate-200 dark:border-slate-700 rounded-2xl p-6 sm:p-7 shadow-2xl max-w-md w-full relative">
            
            {{-- Encabezado Modal --}}
            <div class="flex items-center justify-between mb-5 pb-3.5 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-nm-primary text-white flex items-center justify-center shadow-md shadow-nm-primary/30">
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">Credenciales de Acceso SSH</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium" x-text="currentDeviceName + ' (' + currentDeviceIp + ')'"></p>
                    </div>
                </div>
                <button type="button" @click="openLoginModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /> <path d="m6 6 12 12" /></svg>
                </button>
            </div>

            {{-- Formulario de Credenciales con Íconos --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" /></svg>
                        Puerto SSH (TCP):
                    </label>
                    <input type="number" x-model="sshPort" 
                           class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-nm-primary focus:outline-none transition shadow-xs font-bold"
                           placeholder="22">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /> <circle cx="12" cy="7" r="4" /></svg>
                        Usuario SSH (Username):
                    </label>
                    <input type="text" x-model="sshUser" 
                           class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-nm-primary focus:outline-none transition shadow-xs font-bold"
                           placeholder="admin / cisco / root">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                        Contraseña SSH (Password):
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" x-model="sshPassword" 
                               class="w-full px-3.5 py-2.5 pr-10 text-xs font-mono rounded-xl bg-slate-50 dark:bg-nm-sunken border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-nm-primary focus:outline-none transition shadow-xs font-bold"
                               placeholder="Ingrese la clave SSH">
                        <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-white transition">
                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /> <circle cx="12" cy="12" r="3" /></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 pt-1">
                    <input type="checkbox" id="saveCreds" x-model="saveCredentials" class="w-4 h-4 rounded text-nm-primary focus:ring-nm-primary bg-slate-50 dark:bg-slate-800 border-slate-300 dark:border-slate-700">
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
                <button type="button" @click="openLoginModal = false" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition">
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
                    background: '#090e18', 
                    foreground: '#f1f5f9',
                    cursor: '#38bdf8',
                    selection: 'rgba(59, 130, 246, 0.35)',
                    black: '#0f172a',
                    red: '#f87171',
                    green: '#34d399',
                    yellow: '#fbbf24',
                    blue: '#4d94fd',
                    magenta: '#c084fc',
                    cyan: '#38bdf8',
                    white: '#ffffff',
                    brightBlack: '#475569',
                    brightRed: '#fca5a5',
                    brightGreen: '#6ee7b7',
                    brightYellow: '#fde047',
                    brightBlue: '#80b3ff',
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
