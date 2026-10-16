<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Network Dashboard')</title>
    <!-- Tailwind CSS (via CDN & Vite) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'hacienda-blue': '#3b5998',
                        'hacienda-orange': '#f26419'
                    }
                }
            }
        }
    </script>
    @if(file_exists(public_path('build/manifest.json')))
        @php
            $manifestData = json_decode(@file_get_contents(public_path('build/manifest.json')), true);
            $cssAsset = $manifestData['resources/css/app.css']['file'] ?? null;
        @endphp
        @if($cssAsset)
            <link rel="stylesheet" href="{{ asset('build/' . $cssAsset) }}">
        @endif
    @endif
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
                const savedTheme = localStorage.getItem('theme') || 'light';
                if (savedTheme === 'dark') {
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
        
        /* NOC Live Marquee Banner Animation */
        /* La duración la calcula JS (partials/alert_banner) según el ancho, para una velocidad constante en px/s */
        @keyframes nocMarquee {
            from { transform: translate3d(0, 0, 0); }
            to { transform: translate3d(-50%, 0, 0); }
        }
        .animate-noc-marquee {
            display: flex;
            width: max-content;
            animation: nocMarquee var(--noc-marquee-duration, 180s) linear infinite;
            will-change: transform;
            backface-visibility: hidden;
        }
        .animate-noc-marquee:hover {
            animation-play-state: paused;
        }

        /* ======================== FANCY BUTTON (UIVERSE) ======================== */
        .fancy {
            background-color: transparent;
            border: 2px solid #0f172a;
            border-radius: 0;
            box-sizing: border-box;
            color: #0f172a;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin: 0;
            outline: none;
            overflow: visible;
            padding: 0.65em 1.4em;
            position: relative;
            text-align: center;
            text-decoration: none;
            text-transform: uppercase;
            transition: all 0.3s ease-in-out;
            user-select: none;
            font-size: 12px;
            line-height: 1.2;
            vertical-align: middle;
        }

        .fancy::before {
            content: " ";
            width: 1.3rem;
            height: 2px;
            background: #0f172a;
            top: 50%;
            left: 1.1em;
            position: absolute;
            transform: translateY(-50%);
            transform-origin: center;
            transition: background 0.3s linear, width 0.3s linear;
        }

        .fancy .text {
            font-size: 1.05em;
            line-height: 1.33333em;
            padding-left: 1.6em;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-align: left;
            transition: all 0.3s ease-in-out;
            text-transform: uppercase;
            text-decoration: none;
            color: #0f172a;
        }

        .fancy .top-key {
            height: 2px;
            width: 1.5625rem;
            top: -2px;
            left: 0.625rem;
            position: absolute;
            background: #ffffff;
            transition: width 0.5s ease-out, left 0.3s ease-out;
        }

        .fancy .bottom-key-1 {
            height: 2px;
            width: 1.5625rem;
            right: 1.875rem;
            bottom: -2px;
            position: absolute;
            background: #ffffff;
            transition: width 0.5s ease-out, right 0.3s ease-out;
        }

        .fancy .bottom-key-2 {
            height: 2px;
            width: 0.625rem;
            right: 0.625rem;
            bottom: -2px;
            position: absolute;
            background: #ffffff;
            transition: width 0.5s ease-out, right 0.3s ease-out;
        }

        .fancy:hover {
            color: #ffffff;
            background: #0f172a;
        }

        .fancy:hover::before {
            width: 0.8rem;
            background: #ffffff;
        }

        .fancy:hover .text {
            color: #ffffff;
            padding-left: 1.25em;
        }

        .fancy:hover .top-key {
            left: -2px;
            width: 0px;
        }

        .fancy:hover .bottom-key-1,
        .fancy:hover .bottom-key-2 {
            right: 0;
            width: 0px;
        }

        /* Dark mode for .fancy */
        .dark .fancy {
            border-color: #cbd5e1;
            color: #f1f5f9;
        }
        .dark .fancy::before {
            background: #cbd5e1;
        }
        .dark .fancy .text {
            color: #f1f5f9;
        }
        .dark .fancy .top-key,
        .dark .fancy .bottom-key-1,
        .dark .fancy .bottom-key-2 {
            background: #0d1017;
        }
        .dark .fancy:hover {
            color: #0f172a;
            background: #f8fafc;
            border-color: #f8fafc;
        }
        .dark .fancy:hover::before {
            background: #0f172a;
        }
        .dark .fancy:hover .text {
            color: #0f172a;
        }

        /* Compact variant */
        .fancy-sm {
            padding: 0.4em 0.85em;
            font-size: 11px;
        }
        .fancy-sm::before {
            left: 0.65em;
            width: 0.9rem;
        }
        .fancy-sm .text {
            padding-left: 1.2em;
            font-size: 0.95em;
            gap: 0.3rem;
        }
        .fancy-sm:hover .text {
            padding-left: 0.95em;
        }
        .fancy-sm:hover::before {
            width: 0.55rem;
        }
    </style>
    {{-- Modo TV: se decide antes de pintar para no mostrar y luego esconder la barra lateral --}}
    <script>
        (function () {
            try {
                const p = new URLSearchParams(location.search);
                if (p.get('tv') === '1') sessionStorage.setItem('nocModoTV', '1');
                if (p.get('tv') === '0') sessionStorage.removeItem('nocModoTV');
                if (sessionStorage.getItem('nocModoTV') === '1') document.documentElement.classList.add('modo-tv');
            } catch (e) {}
        })();
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .modo-tv #navLateral, .modo-tv #navMovilSuperior, .modo-tv #navMovilInferior { display: none !important; }
        .modo-tv #contenedorPrincipal { padding: 0.75rem !important; gap: 0.75rem !important; }
        .modo-tv #contenidoPrincipal { padding-bottom: 0 !important; }
        #controlesTV { opacity: 0; pointer-events: none; transition: opacity .3s; }
        #controlesTV.visible { opacity: 1; pointer-events: auto; }
    </style>
    @yield('styles')
</head>
<body class="text-gray-800 dark:text-slate-100 antialiased min-h-screen selection:bg-[#f26419] selection:text-white overflow-hidden bg-[#e8eef3] dark:bg-[#07090e] transition-colors duration-200">
    @php
        // Navegación unificada: mismo set de iconos (estilo outline, viewBox 24, stroke 2) y mismo orden
        // para el sidebar de escritorio y la barra inferior móvil.
        $navItems = [
            [
                'href'   => url('/'),
                'active' => request()->routeIs('dashboard', 'dispositivos.show'),
                'title'  => 'Dashboard',
                'label'  => 'Inicio',
                'svg'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>',
            ],
            [
                'href'   => route('topologia.index'),
                'active' => request()->is('topologia*'),
                'title'  => 'Mapa de Topología',
                'label'  => 'Topología',
                'svg'    => '<circle cx="6" cy="6" r="2.5"></circle><circle cx="18" cy="6" r="2.5"></circle><circle cx="12" cy="18" r="2.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M8.2 7.2l2.6 8.6m2.4 0l2.6-8.6M8.5 6h7"></path>',
            ],
            [
                'href'   => route('servicios_web.index'),
                'active' => request()->is('servicios-web*'),
                'title'  => 'Monitoreo de Servicios Web y HTTP',
                'label'  => 'Webs',
                'svg'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>',
            ],
            [
                'href'   => route('consola.index'),
                'active' => request()->is('consola*'),
                'title'  => 'Consola Segura CLI',
                'label'  => 'CLI',
                'svg'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M7 8.5l3 2.5-3 2.5m5 0h4M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
            ],
            [
                'href'   => route('settings.index'),
                'active' => request()->is('settings*'),
                'title'  => 'Configuraciones Generales',
                'label'  => 'Ajustes',
                'svg'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
            ],
        ];
    @endphp
    
    <div id="contenedorPrincipal" class="flex flex-col md:flex-row h-screen bg-[#e8eef3] dark:bg-[#07090e] p-2 sm:p-4 lg:p-6 gap-3 md:gap-6 transition-colors duration-200">

        <!-- ==================== MOBILE TOP HEADER ==================== -->
        <header id="navMovilSuperior" class="flex md:hidden items-center justify-between bg-hacienda-blue dark:bg-[#090b10] text-white p-3 px-4 rounded-2xl shadow-lg shrink-0 z-40 border border-blue-900/20 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 flex items-center justify-center p-1 bg-white/10 rounded-xl overflow-hidden shadow-xs">
                    <img src="{{ asset('images/logo_swoop.png') }}" alt="Logo" class="w-full h-full object-contain dark:brightness-0 dark:invert" onerror="this.src='https://ui-avatars.com/api/?name=NM&background=fff&color=3b5998'">
                </div>
                <div>
                    <h1 class="text-sm font-black tracking-tight leading-none text-white">Network Monitor</h1>
                    <p class="text-[10px] text-blue-200 dark:text-slate-400 font-medium mt-0.5">Sistema NOC Enterprise</p>
                </div>
            </div>
            <div class="flex items-center gap-2" x-data="{ isDark: document.documentElement.classList.contains('dark') }">
                <button 
                    type="button" 
                    @click="isDark = window.toggleTheme()" 
                    class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition border border-white/10"
                    title="Cambiar Tema (Claro / Oscuro)">
                    <svg x-show="!isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    <svg x-show="isDark" class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </button>
            </div>
        </header>

        <!-- ==================== DESKTOP SIDEBAR ==================== -->
        <aside id="navLateral" class="w-24 bg-hacienda-blue dark:bg-[#090b10] dark:border-r dark:border-slate-800/50 dark:shadow-none rounded-[2rem] flex flex-col items-center py-8 shadow-2xl flex-shrink-0 z-30 relative hidden md:flex transition-colors duration-200">
            <!-- Logo/Brand -->
            <div class="mb-12">
                <div class="w-14 h-14 flex items-center justify-center p-2 overflow-hidden">
                    <img src="{{ asset('images/logo_swoop.png') }}" alt="Logo" class="w-full h-full object-contain dark:brightness-0 dark:invert dark:opacity-90 transition" onerror="this.src='https://ui-avatars.com/api/?name=NM&background=fff&color=3b5998'">
                </div>
            </div>

            <!-- Nav Icons (generados desde $navItems) -->
            <nav class="flex-1 flex flex-col gap-6 items-center w-full">
                @foreach($navItems as $item)
                <a href="{{ $item['href'] }}"
                   class="w-12 h-12 {{ $item['active'] ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-400 shadow-md dark:shadow-none transform scale-110 border border-transparent dark:border-slate-700/50' : 'text-blue-100 dark:text-slate-400 hover:bg-white/20 dark:hover:bg-slate-800/50 hover:text-white dark:hover:text-slate-200' }} rounded-xl flex items-center justify-center transition"
                   title="{{ $item['title'] }}" aria-label="{{ $item['title'] }}" @if($item['active']) aria-current="page" @endif>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">{!! $item['svg'] !!}</svg>
                </a>
                @endforeach
            </nav>

            <!-- Pantallas NOC: ventanas para otras TV, pantalla completa y modo TV -->
            <div class="relative mt-6" x-data="pantallasNoc()" @keydown.escape.window="abierto = false">
                <button type="button" @click="abrirPanel()" title="Pantallas: abrir vistas en otras TV" aria-label="Pantallas"
                        class="w-12 h-12 rounded-xl flex items-center justify-center transition text-blue-100 dark:text-slate-400 hover:bg-white/20 dark:hover:bg-slate-800/50 hover:text-white"
                        :class="abierto ? 'bg-white/20 text-white' : ''">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><rect x="2" y="4" width="13" height="9" rx="1.5"></rect><path stroke-linecap="round" stroke-linejoin="round" d="M6 17h5M8.5 13v4"></path><rect x="13" y="9" width="9" height="7" rx="1.2"></rect><path stroke-linecap="round" d="M16 19.5h3M17.5 16v3.5"></path></svg>
                </button>

                <div x-show="abierto" x-cloak @click.outside="abierto = false" x-transition.opacity
                     class="absolute left-16 bottom-0 w-80 z-50 bg-white dark:bg-[#0d1017] text-slate-800 dark:text-slate-100 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-4 text-sm">
                    <div class="font-black text-base mb-1">Pantallas</div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Abre vistas en ventanas aparte para arrastrarlas a otras TV. Se abren en Modo TV y la sesión no se cierra.</p>

                    <div class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Abrir en otra ventana</div>
                    <div class="grid grid-cols-2 gap-1.5 mb-3">
                        <template x-for="v in vistas" :key="v.url">
                            <button type="button" @click="abrirVentana(v.url)" class="px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-left" x-text="v.nombre"></button>
                        </template>
                    </div>

                    <template x-if="soportaPantallas">
                        <div class="mb-3">
                            <div class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Repartir en los monitores conectados</div>
                            <template x-if="!pantallas.length">
                                <button type="button" @click="detectarPantallas()" class="w-full px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold">Detectar monitores</button>
                            </template>
                            <template x-for="(p, i) in pantallas" :key="i">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-xs font-semibold w-28 truncate" :title="p.etiqueta" x-text="p.etiqueta"></span>
                                    <select x-model="p.vista" class="flex-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-[#090b10] px-2 py-1.5">
                                        <option value="">— Nada —</option>
                                        <template x-for="v in vistas" :key="v.url"><option :value="v.url" x-text="v.nombre"></option></template>
                                    </select>
                                </div>
                            </template>
                            <template x-if="pantallas.length">
                                <button type="button" @click="repartir()" class="w-full mt-1 px-2.5 py-2 rounded-xl bg-black text-white dark:bg-white dark:text-black text-xs font-black">Abrir en los monitores</button>
                            </template>
                        </div>
                    </template>

                    <div class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Esta ventana</div>
                    <div class="flex flex-col gap-1.5">
                        <button type="button" @click="pantallaCompleta()" class="px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-left">Pantalla completa (F11)</button>
                        <button type="button" @click="activarModoTV()" class="px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-left">Modo TV: ocultar menú y mantener sesión</button>
                    </div>
                    <p class="text-[11px] mt-2" :class="error ? 'text-rose-600' : 'text-slate-500'" x-show="aviso" x-text="aviso"></p>
                </div>
            </div>
        </aside>

        <!-- ==================== MAIN CONTENT ==================== -->
        <main id="contenidoPrincipal" class="flex-1 flex flex-col h-full overflow-y-auto custom-scrollbar pr-0 md:pr-2 pb-24 md:pb-0 relative">
            @include('partials.alert_banner')
            @yield('content')
        </main>

        <!-- ==================== RIGHT SIDEBAR ==================== -->
        @hasSection('right-sidebar')
        <aside class="w-80 flex flex-col gap-6 h-full flex-shrink-0 hidden xl:flex overflow-y-auto custom-scrollbar">
            @yield('right-sidebar')
        </aside>
        @endif

        <!-- ==================== MOBILE FLOATING BOTTOM BAR ==================== -->
        <nav id="navMovilInferior" class="fixed bottom-3 left-3 right-3 z-50 flex md:hidden items-center justify-around bg-white/95 dark:bg-[#0d1017]/95 backdrop-blur-xl border border-slate-200/90 dark:border-slate-800/80 p-2 rounded-2xl shadow-2xl transition-all">
            @foreach($navItems as $item)
            <a href="{{ $item['href'] }}" title="{{ $item['title'] }}" @if($item['active']) aria-current="page" @endif
               class="flex flex-col items-center gap-1 p-2 rounded-xl text-xs font-bold transition {{ $item['active'] ? 'text-hacienda-blue dark:text-blue-400 bg-blue-50 dark:bg-slate-800/80' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">{!! $item['svg'] !!}</svg>
                <span class="text-[10px] leading-none">{{ $item['label'] }}</span>
            </a>
            @endforeach
        </nav>

    </div>

    <!-- Controles del Modo TV: aparecen al mover el ratón -->
    <div id="controlesTV" class="fixed bottom-4 left-4 z-[60] hidden items-center gap-2 bg-black/80 text-white rounded-2xl p-2 shadow-2xl backdrop-blur">
        <button type="button" onclick="window.nocPantallas.pantallaCompleta()" class="px-3 py-2 rounded-xl hover:bg-white/15 text-xs font-bold">Pantalla completa</button>
        <button type="button" onclick="window.nocPantallas.salirModoTV()" class="px-3 py-2 rounded-xl hover:bg-white/15 text-xs font-bold">Salir de Modo TV</button>
        <span id="estadoSesionTV" class="text-[11px] text-slate-300 px-2"></span>
    </div>

    <script>
        // ==================== PANTALLAS NOC / MODO TV ====================
        window.nocPantallas = (function () {
            const URL_MANTENER = @json(route('pantalla.mantener'));
            const CSRF = @json(csrf_token());
            const LATIDO_MS = 5 * 60 * 1000;          // renueva la sesión cada 5 min
            const RECARGA_MS = 6 * 60 * 60 * 1000;    // recarga completa cada 6 h (libera memoria de pestañas que nunca se cierran)
            const esModoTV = () => document.documentElement.classList.contains('modo-tv');

            const conTV = (url) => {
                const u = new URL(url, location.origin);
                u.searchParams.set('tv', '1');
                return u.toString();
            };

            async function latido() {
                const estado = document.getElementById('estadoSesionTV');
                try {
                    const r = await fetch(URL_MANTENER, {
                        method: 'POST', credentials: 'same-origin', redirect: 'manual',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (r.ok) {
                        const j = await r.json();
                        if (estado) estado.textContent = 'Sesión activa · ' + j.hora;
                        return;
                    }
                    // 401/419/redirección al login: la sesión venció; recargar la restaura con la cookie recordada
                    if (estado) estado.textContent = 'Reconectando sesión…';
                    setTimeout(() => location.reload(), 3000);
                } catch (e) {
                    if (estado) estado.textContent = 'Sin conexión con el servidor, reintentando…';
                }
            }

            let wakeLock = null;
            async function mantenerPantallaEncendida() {
                try {
                    if ('wakeLock' in navigator && document.visibilityState === 'visible') {
                        wakeLock = await navigator.wakeLock.request('screen');
                    }
                } catch (e) { /* solo disponible en HTTPS o localhost */ }
            }

            function iniciarModoTV() {
                const controles = document.getElementById('controlesTV');
                controles.classList.remove('hidden');
                controles.classList.add('flex');
                let t;
                const mostrar = () => {
                    controles.classList.add('visible');
                    document.body.style.cursor = '';
                    clearTimeout(t);
                    t = setTimeout(() => { controles.classList.remove('visible'); document.body.style.cursor = 'none'; }, 3000);
                };
                document.addEventListener('mousemove', mostrar);
                document.addEventListener('touchstart', mostrar, { passive: true });

                latido();
                setInterval(latido, LATIDO_MS);
                mantenerPantallaEncendida();
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') { latido(); mantenerPantallaEncendida(); }
                });
                setTimeout(() => location.reload(), RECARGA_MS);
            }

            if (esModoTV()) {
                document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', iniciarModoTV) : iniciarModoTV();
            }

            return {
                conTV,
                abrirVentana(url, opciones) {
                    const w = window.open(conTV(url), '_blank', opciones || 'popup,width=1600,height=900');
                    return w;
                },
                pantallaCompleta() {
                    if (document.fullscreenElement) document.exitFullscreen();
                    else document.documentElement.requestFullscreen?.().catch(() => {});
                },
                activarModoTV() {
                    location.href = conTV(location.href);
                },
                salirModoTV() {
                    try { sessionStorage.removeItem('nocModoTV'); } catch (e) {}
                    const u = new URL(location.href);
                    u.searchParams.delete('tv');
                    if (document.fullscreenElement) document.exitFullscreen();
                    location.href = u.toString();
                },
            };
        })();

        function pantallasNoc() {
            return {
                abierto: false, aviso: '', error: false, pantallas: [],
                soportaPantallas: 'getScreenDetails' in window,
                vistas: [
                    { nombre: 'Dashboard', url: @json(route('dashboard')) },
                    { nombre: 'Topología', url: @json(route('topologia.index')) },
                    { nombre: 'Servicios web', url: @json(route('servicios_web.index')) },
                    { nombre: 'Esta página', url: location.pathname + location.search },
                ],
                abrirPanel() { this.abierto = !this.abierto; this.aviso = ''; },
                abrirVentana(url) {
                    const w = window.nocPantallas.abrirVentana(url);
                    this.error = !w;
                    this.aviso = w ? 'Ventana abierta: arrástrala a la otra TV y pulsa F11.' : 'El navegador bloqueó la ventana: permite ventanas emergentes para este sitio.';
                },
                async detectarPantallas() {
                    try {
                        const det = await window.getScreenDetails();
                        const orden = ['dashboard', 'topologia', 'servicios'];
                        this.pantallas = det.screens.map((s, i) => ({
                            etiqueta: (s.label || ('Monitor ' + (i + 1))) + (s.isPrimary ? ' (principal)' : ''),
                            izquierda: s.availLeft, arriba: s.availTop, ancho: s.availWidth, alto: s.availHeight,
                            vista: s.isPrimary ? '' : (this.vistas[i % 3]?.url || ''),
                        }));
                        this.error = false;
                        this.aviso = det.screens.length + ' monitor(es) detectados.';
                    } catch (e) {
                        this.error = true;
                        this.aviso = 'No se dio permiso para ver los monitores. Usa "Abrir en otra ventana" y arrástrala.';
                    }
                },
                repartir() {
                    let bloqueadas = 0, abiertas = 0;
                    for (const p of this.pantallas) {
                        if (!p.vista) continue;
                        const w = window.nocPantallas.abrirVentana(p.vista,
                            `popup,left=${p.izquierda},top=${p.arriba},width=${p.ancho},height=${p.alto}`);
                        w ? abiertas++ : bloqueadas++;
                    }
                    this.error = bloqueadas > 0;
                    this.aviso = bloqueadas
                        ? `Se abrieron ${abiertas}; ${bloqueadas} bloqueadas: permite ventanas emergentes para este sitio.`
                        : `${abiertas} ventana(s) abiertas. En cada TV pulsa F11 (o "Pantalla completa") para ocupar toda la pantalla.`;
                },
            };
        }
    </script>

    <!-- Global Theme Management Helper -->
    <script>
        window.getSystemTheme = function() {
            return 'light';
        };

        window.getCurrentThemePreference = function() {
            return localStorage.getItem('theme') || 'light';
        };

        window.setTheme = function(mode) {
            const html = document.documentElement;
            localStorage.setItem('theme', mode);
            let isDark = (mode === 'dark');

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

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            const theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });
    </script>
    @yield('scripts')
</body>
</html>
