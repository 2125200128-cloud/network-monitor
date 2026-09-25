<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Network Dashboard')</title>
    <!-- Tailwind CSS (via Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Chart.js (Optional, but included globally as before) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Anti-FOUC Theme Initializer -->
    <script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('theme') || 'system';
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (savedTheme === 'dark' || (savedTheme === 'system' && systemDark)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {
                console.error('Error initializing theme:', e);
            }
        })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        
        /* Custom Colors */
        .text-hacienda-blue { color: #3b5998; }
        .text-hacienda-orange { color: #f26419; }
        .bg-hacienda-blue { background-color: #3b5998 !important; }
        .bg-hacienda-orange { background-color: #f26419 !important; }

        /* Smooth scrollbar for lists & tables */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .custom-scrollbar { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f8fafc; }

        /* Floating Cards */
        .floating-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        }
        .floating-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Dark Mode High-End NOC Overrides */
        .dark body { background-color: #07090e; color: #f1f5f9; }
        .dark .floating-card {
            background: #0d1017;
            border: 1px solid rgba(51, 65, 85, 0.4);
            box-shadow: none !important;
        }
        .dark .floating-card:hover {
            box-shadow: none !important;
            border-color: rgba(51, 65, 85, 0.65);
        }
        .dark .custom-scrollbar::-webkit-scrollbar-track { background: #07090e; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
        .dark .custom-scrollbar { scrollbar-color: #334155 #07090e; }
        
        @yield('styles')
    </style>
</head>
<body class="text-gray-800 dark:text-slate-100 antialiased min-h-screen selection:bg-[#f26419] selection:text-white overflow-hidden bg-[#e8eef3] dark:bg-[#07090e] transition-colors duration-200">
    
    <div class="flex h-screen bg-[#e8eef3] dark:bg-[#07090e] p-4 lg:p-6 gap-6 transition-colors duration-200">
        
        <!-- ==================== SIDEBAR ==================== -->
        <aside class="w-24 bg-hacienda-blue dark:bg-[#090b10] dark:border-r dark:border-slate-800/50 dark:shadow-none rounded-[2rem] flex flex-col items-center py-8 shadow-2xl flex-shrink-0 z-30 relative hidden md:flex transition-colors duration-200">
            <!-- Logo/Brand -->
            <div class="mb-12">
                <div class="w-14 h-14 flex items-center justify-center p-2 overflow-hidden">
                    <img src="{{ asset('images/logo_swoop.png') }}" alt="Logo" class="w-full h-full object-contain dark:brightness-0 dark:invert dark:opacity-90 transition" onerror="this.src='https://ui-avatars.com/api/?name=NM&background=fff&color=3b5998'">
                </div>
            </div>

            <!-- Nav Icons -->
            <nav class="flex-1 flex flex-col gap-6 items-center w-full">
                <!-- Home -->
                <a href="{{ url('/') }}" class="w-12 h-12 {{ request()->is('/') ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-400 shadow-md dark:shadow-none transform scale-110 border border-transparent dark:border-slate-700/50' : 'text-blue-100 dark:text-slate-400 hover:bg-white/20 dark:hover:bg-slate-800/50' }} rounded-xl flex items-center justify-center transition" title="Dashboard">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                </a>
                
                <!-- Submenú Desplegable: Gestión de Red L2/L3 -->
                <div class="relative w-full flex justify-center" x-data="{ open: false }" @click.outside="open = false">
                    @php
                        $isNetworkMgmtActive = request()->is('consola*') || request()->is('vlans*') || request()->is('configuraciones*');
                    @endphp
                    <!-- Botón Padre -->
                    <button 
                        @click="open = !open" 
                        type="button"
                        class="w-12 h-12 {{ $isNetworkMgmtActive ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-400 shadow-md dark:shadow-none transform scale-110 border border-transparent dark:border-slate-700/50' : 'text-blue-100 dark:text-slate-400 hover:bg-white/20 dark:hover:bg-slate-800/50' }} rounded-xl flex items-center justify-center transition relative group"
                        title="Gestión de Red L2/L3 (Consola, VLANs, NCM)">
                        <!-- Ícono L2/L3 / Switches & Servidor unificado -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                        </svg>
                        
                        <!-- Indicador Activo -->
                        @if($isNetworkMgmtActive)
                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-hacienda-orange rounded-full border-2 border-hacienda-blue dark:border-[#090b10]"></span>
                        @endif
                    </button>

                    <!-- Panel Desplegable (Flyout hacia la derecha) -->
                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-2"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 translate-x-2"
                        class="absolute left-16 top-0 w-64 bg-white dark:bg-[#0d1017] rounded-2xl shadow-2xl dark:shadow-none border border-gray-100 dark:border-slate-800/50 p-3 z-50 flex flex-col gap-1.5 text-gray-800 dark:text-slate-200"
                        style="display: none;">
                        
                        <div class="px-3 py-2 border-b border-gray-100 dark:border-slate-800/50 mb-1 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-extrabold text-hacienda-blue dark:text-blue-400 uppercase tracking-wider">Gestión de Red</p>
                                <p class="text-[10px] text-gray-400 dark:text-slate-400">Módulos L2 / L3</p>
                            </div>
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        </div>

                        <!-- Opción 1: Consola Segura CLI -->
                        <a href="{{ route('consola.index') }}" 
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->is('consola*') ? 'bg-blue-50 dark:bg-slate-800/80 text-hacienda-blue dark:text-blue-400 font-bold border border-blue-100 dark:border-slate-700/60' : 'hover:bg-gray-50 dark:hover:bg-slate-800/50 text-gray-700 dark:text-slate-300' }}"
                           title="Consola CLI - Ejecución remota de comandos de diagnóstico y configuración">
                            <div class="w-8 h-8 rounded-lg bg-gray-900 text-emerald-400 flex items-center justify-center flex-shrink-0 font-mono text-xs shadow-sm border border-slate-700">
                                &gt;_
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="leading-tight truncate">Consola Segura CLI</p>
                                <p class="text-[10px] text-gray-400 dark:text-slate-400 font-normal truncate">Diagnóstico y comandos</p>
                            </div>
                        </a>

                        <!-- Opción 2: Configuración de VLANs -->
                        <a href="{{ route('vlans.index') }}" 
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->is('vlans*') ? 'bg-blue-50 dark:bg-slate-800/80 text-hacienda-blue dark:text-blue-400 font-bold border border-blue-100 dark:border-slate-700/60' : 'hover:bg-gray-50 dark:hover:bg-slate-800/50 text-gray-700 dark:text-slate-300' }}"
                           title="Configuración de VLANs - Segmentación y asignación de puertos">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 border border-indigo-200/40 dark:border-indigo-800/40">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="leading-tight truncate">Configuración VLANs</p>
                                <p class="text-[10px] text-gray-400 dark:text-slate-400 font-normal truncate">Segmentación de puertos</p>
                            </div>
                        </a>

                        <!-- Opción 3: Respaldos NCM -->
                        <a href="{{ route('configuraciones.index') }}" 
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->is('configuraciones*') ? 'bg-blue-50 dark:bg-slate-800/80 text-hacienda-blue dark:text-blue-400 font-bold border border-blue-100 dark:border-slate-700/60' : 'hover:bg-gray-50 dark:hover:bg-slate-800/50 text-gray-700 dark:text-slate-300' }}"
                           title="Respaldos NCM - Gestión de configuraciones running-config y diff">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-hacienda-orange flex items-center justify-center flex-shrink-0 border border-amber-200/40 dark:border-amber-800/40">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="leading-tight truncate">Respaldos NCM</p>
                                <p class="text-[10px] text-gray-400 dark:text-slate-400 font-normal truncate">Running-config y Diff</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Mapa de Topología -->
                <a href="{{ route('topologia.index') }}" class="w-12 h-12 {{ request()->is('topologia*') ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-400 shadow-md dark:shadow-none transform scale-110 border border-transparent dark:border-slate-700/50' : 'text-blue-100 dark:text-slate-400 hover:bg-white/20 dark:hover:bg-slate-800/50' }} rounded-xl flex items-center justify-center transition" title="Mapa de Topología">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><circle cx="6" cy="6" r="2.5"></circle><circle cx="18" cy="6" r="2.5"></circle><circle cx="12" cy="18" r="2.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M8.2 7.2l2.6 8.6m2.4 0l2.6-8.6M8.5 6h7"></path></svg>
                </a>

                <!-- Configuraciones / Ajustes -->
                <a href="{{ route('settings.index') }}" class="w-12 h-12 {{ request()->is('settings*') ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-400 shadow-md dark:shadow-none transform scale-110 border border-transparent dark:border-slate-700/50' : 'text-blue-100 dark:text-slate-400 hover:bg-white/20 dark:hover:bg-slate-800/50' }} rounded-xl flex items-center justify-center transition" title="Configuraciones Generales">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </a>

                <!-- Agregar Switch -->
                <a href="{{ route('dispositivos.create') }}" class="w-12 h-12 {{ request()->is('dispositivos/create') ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-400 shadow-md dark:shadow-none transform scale-110 border border-transparent dark:border-slate-700/50' : 'text-blue-100 dark:text-slate-400 hover:bg-white/20 dark:hover:bg-slate-800/50' }} rounded-xl flex items-center justify-center transition" title="Agregar Switch">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                </a>
            </nav>
        </aside>

        <!-- ==================== MAIN CONTENT ==================== -->
        <main class="flex-1 flex flex-col h-full overflow-y-auto custom-scrollbar pr-2 relative">
            @yield('content')
        </main>

        <!-- ==================== RIGHT SIDEBAR ==================== -->
        @hasSection('right-sidebar')
        <aside class="w-80 flex flex-col gap-6 h-full flex-shrink-0 hidden xl:flex overflow-y-auto custom-scrollbar">
            @yield('right-sidebar')
        </aside>
        @endif

    </div>

    <!-- Global Theme Management Helper -->
    <script>
        window.getSystemTheme = function() {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        };

        window.getCurrentThemePreference = function() {
            return localStorage.getItem('theme') || 'system';
        };

        window.setTheme = function(mode) {
            const html = document.documentElement;
            localStorage.setItem('theme', mode);
            let isDark = false;
            if (mode === 'dark') {
                isDark = true;
            } else if (mode === 'light') {
                isDark = false;
            } else { // 'system'
                isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            if (isDark) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: mode, isDark: isDark } }));
            return isDark;
        };

        window.toggleTheme = function() {
            const currentDark = document.documentElement.classList.contains('dark');
            return window.setTheme(currentDark ? 'light' : 'dark');
        };

        // Listen for OS color scheme changes when theme is set to 'system'
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if ((localStorage.getItem('theme') || 'system') === 'system') {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: 'system', isDark: e.matches } }));
            }
        });
    </script>
    @yield('scripts')
</body>
</html>
