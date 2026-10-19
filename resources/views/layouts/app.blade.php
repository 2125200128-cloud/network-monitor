<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Network Dashboard')</title>
    <!-- Tailwind CSS (via CDN & Vite) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Escalas del sistema de diseño (partials/design-tokens): "blue" = azul real, "indigo" = marino.
        // Se redefinen para que las clases existentes de las vistas adopten la paleta.
        const nmAzure = { 50: '#eef5ff', 100: '#d9e8ff', 200: '#b3d1ff', 300: '#80b3ff', 400: '#4d94fd', 500: '#1a7afb', 600: '#0168fa', 700: '#0153c7', 800: '#023f99', 900: '#03306f', 950: '#031d45' };
        const nmNavy = { 50: '#eef2fb', 100: '#d6e0f5', 200: '#aebfe9', 300: '#7e97d6', 400: '#4f6fc0', 500: '#2c4fa6', 600: '#1a3d8f', 700: '#123178', 800: '#0d2660', 900: '#0a1d4a', 950: '#060f2b' };
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'hacienda-blue': '#123178',
                        'hacienda-orange': '#f07f12',
                        'inst-azul': '#5b7d95',
                        'inst-naranja': '#f07f12',
                        'nm-primary': 'rgb(var(--nm-primary-rgb) / <alpha-value>)',
                        'nm-surface': 'rgb(var(--nm-surface-rgb) / <alpha-value>)',
                        'nm-sunken': 'rgb(var(--nm-sunken-rgb) / <alpha-value>)',
                        'nm-ink': 'rgb(var(--nm-ink-rgb) / <alpha-value>)',
                        blue: nmAzure,
                        indigo: nmNavy,
                    },
                    // Esquinas más sobrias (referencia Azia): tarjetas 12px, controles 8–10px
                    borderRadius: { xl: '0.625rem', '2xl': '0.75rem', '3xl': '0.875rem' },
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
        
        body { font-family: 'Inter', sans-serif; }

        .text-hacienda-orange { color: var(--nm-inst-naranja); }
        .bg-hacienda-orange { background-color: var(--nm-inst-naranja) !important; }

        /* Entrada escalonada de tarjetas: solo en la primera carga (la quita JS al terminar) */
        @keyframes nmEntrada {
            from { opacity: 0; transform: translate3d(0, 8px, 0); }
            to { opacity: 1; transform: none; }
        }
        .nm-entrada { animation: nmEntrada 0.36s var(--nm-ease) both; animation-delay: calc(var(--nm-i, 0) * 45ms); }

        /* Navegación lateral */
        .nm-nav-item { position: relative; transition: background-color .18s ease, color .18s ease, transform .18s var(--nm-ease); }
        .nm-nav-item svg { transition: transform .2s var(--nm-ease); }
        .nm-nav-item:hover svg { transform: scale(1.08); }
        .nm-nav-item:active { transform: scale(0.96); }
        .nm-nav-item[aria-current="page"]::before {
            content: ''; position: absolute; left: -18px; top: 50%; width: 4px; height: 22px; border-radius: 4px;
            background: #ffffff; transform: translateY(-50%); animation: nmIndicador .3s var(--nm-ease) both;
        }
        .dark .nm-nav-item[aria-current="page"]::before { background: var(--nm-primary-2); }
        @keyframes nmIndicador { from { height: 0; opacity: 0; } to { height: 22px; opacity: 1; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
            .floating-card:hover { transform: none !important; }
        }

        /* Barras de desplazamiento finas (la tarjeta flotante y el vidrio viven en partials/design-tokens) */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--nm-border-strong); border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--nm-neutral-400); }
        .custom-scrollbar { scrollbar-width: thin; scrollbar-color: var(--nm-border-strong) transparent; }
        
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

        /* Botón secundario (.fancy): borde fino y superficie; se ocultan las rayitas decorativas heredadas */
        .fancy {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; margin: 0;
            padding: 0.55rem 1rem; font-size: 12.5px; font-weight: 600; line-height: 1.2; letter-spacing: 0.01em;
            text-transform: none; text-decoration: none; white-space: nowrap; user-select: none; position: relative;
            color: var(--nm-text); background: var(--nm-surface); border: 1px solid var(--nm-border-strong);
            border-radius: var(--nm-btn-radius); box-shadow: var(--nm-shadow-sm); cursor: pointer;
            transition: background-color var(--nm-dur-fast) ease, border-color var(--nm-dur-fast) ease, color var(--nm-dur-fast) ease;
        }
        .fancy::before, .fancy .top-key, .fancy .bottom-key-1, .fancy .bottom-key-2 { display: none; }
        .fancy .text { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0; color: inherit; text-transform: none; font-size: inherit; }
        .fancy:hover { color: var(--nm-azure-700); background: var(--nm-azure-50); border-color: var(--nm-azure-300); }
        .dark .fancy:hover { color: var(--nm-azure-200); background: rgb(var(--nm-primary-rgb) / 0.14); border-color: rgb(var(--nm-primary-rgb) / 0.45); }
        .fancy-sm { padding: 0.35rem 0.7rem; font-size: 11.5px; }
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
    @include('partials.design-tokens')
    @yield('styles')
</head>
<body class="antialiased min-h-screen overflow-hidden transition-colors duration-200">
    @php
        // Navegación unificada: iconos estilo Lucide (outline, viewBox 24, trazo 1.75) y mismo orden
        // para el sidebar de escritorio y la barra inferior móvil.
        $navItems = [
            [
                'href'   => url('/'),
                'active' => request()->routeIs('dashboard', 'dispositivos.show'),
                'title'  => 'Dashboard',
                'label'  => 'Inicio',
                'svg'    => '<rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect>',
            ],
            [
                'href'   => route('topologia.index'),
                'active' => request()->is('topologia*'),
                'title'  => 'Mapa de Topología',
                'label'  => 'Topología',
                'svg'    => '<rect x="16" y="16" width="6" height="6" rx="1"></rect><rect x="2" y="16" width="6" height="6" rx="1"></rect><rect x="9" y="2" width="6" height="6" rx="1"></rect><path stroke-linecap="round" stroke-linejoin="round" d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3M12 12V8"></path>',
            ],
            [
                'href'   => route('servicios_web.index'),
                'active' => request()->is('servicios-web*'),
                'title'  => 'Monitoreo de Servicios Web y HTTP',
                'label'  => 'Webs',
                'svg'    => '<circle cx="12" cy="12" r="9.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 2.5a14 14 0 0 0 0 19 14 14 0 0 0 0-19M2.5 12h19"></path>',
            ],
            [
                'href'   => route('consola.index'),
                'active' => request()->is('consola*'),
                'title'  => 'Consola Segura CLI',
                'label'  => 'CLI',
                'svg'    => '<rect x="3" y="3" width="18" height="18" rx="2.5"></rect><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 9.5 2.5 2.5-2.5 2.5M12.5 14.5h4"></path>',
            ],
            [
                'href'   => route('settings.index'),
                'active' => request()->is('settings*'),
                'title'  => 'Configuraciones Generales',
                'label'  => 'Ajustes',
                'svg'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7h-9M14 17H4"></path><circle cx="17" cy="17" r="3"></circle><circle cx="7" cy="7" r="3"></circle>',
            ],
        ];
    @endphp
    
    <div id="contenedorPrincipal" class="flex flex-col md:flex-row h-screen p-2 sm:p-4 lg:p-6 gap-3 md:gap-6 transition-colors duration-200">

        <!-- ==================== MOBILE TOP HEADER ==================== -->
        <header id="navMovilSuperior" class="flex md:hidden items-center justify-between nm-sidebar text-white p-3 px-4 rounded-2xl shadow-lg shrink-0 z-40">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 flex items-center justify-center p-1 bg-white/10 rounded-xl overflow-hidden shadow-xs">
                    <img src="{{ asset('images/logo_swoop.png') }}" alt="Logo" class="w-full h-full object-contain dark:brightness-0 dark:invert" onerror="this.src='https://ui-avatars.com/api/?name=NM&background=fff&color=3b5998'">
                </div>
                <div>
                    <h1 class="text-sm font-black tracking-tight leading-none text-white">Network Monitor</h1>
                    <p class="text-[11px] text-blue-100/80 font-medium mt-0.5">Centro de Operaciones de Red</p>
                </div>
            </div>
            <div class="flex items-center gap-2" x-data="{ isDark: document.documentElement.classList.contains('dark') }">
                <button 
                    type="button" 
                    @click="isDark = window.toggleTheme()" 
                    class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition border border-white/10"
                    title="Cambiar Tema (Claro / Oscuro)" aria-label="Cambiar Tema (Claro / Oscuro)">
                    <svg x-show="!isDark" class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" /></svg>
                    <svg x-show="isDark" class="w-4 h-4 text-amber-300" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4" /> <path d="M12 2v2" /> <path d="M12 20v2" /> <path d="m4.93 4.93 1.41 1.41" /> <path d="m17.66 17.66 1.41 1.41" /> <path d="M2 12h2" /> <path d="M20 12h2" /> <path d="m6.34 17.66-1.41 1.41" /> <path d="m19.07 4.93-1.41 1.41" /></svg>
                </button>
            </div>
        </header>

        <!-- ==================== DESKTOP SIDEBAR ==================== -->
        <aside id="navLateral" class="w-24 nm-sidebar rounded-2xl flex flex-col items-center py-8 shadow-2xl flex-shrink-0 z-30 relative hidden md:flex transition-colors duration-200">
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
                   class="nm-nav-item w-12 h-12 {{ $item['active'] ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-300 shadow-md dark:shadow-none border border-transparent dark:border-slate-700/50' : 'text-blue-100/75 hover:bg-white/10 dark:hover:bg-slate-800/50 hover:text-white dark:hover:text-slate-200' }} rounded-xl flex items-center justify-center"
                   title="{{ $item['title'] }}" aria-label="{{ $item['title'] }}" @if($item['active']) aria-current="page" @endif>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true">{!! $item['svg'] !!}</svg>
                </a>
                @endforeach
            </nav>

            <!-- Pantallas NOC: ventanas para otras TV, pantalla completa y modo TV -->
            <div class="relative mt-6" x-data="pantallasNoc()" @keydown.escape.window="abierto = false">
                <button type="button" @click="abrirPanel()" title="Pantallas: abrir vistas en otras TV" aria-label="Pantallas"
                        class="nm-nav-item w-12 h-12 rounded-xl flex items-center justify-center text-blue-100/75 hover:bg-white/10 dark:hover:bg-slate-800/50 hover:text-white"
                        :class="abierto ? 'bg-white/10 text-white' : ''">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true"><rect x="2" y="4" width="13" height="9" rx="1.5"></rect><path stroke-linecap="round" stroke-linejoin="round" d="M6 17h5M8.5 13v4"></path><rect x="13" y="9" width="9" height="7" rx="1.2"></rect><path stroke-linecap="round" d="M16 19.5h3M17.5 16v3.5"></path></svg>
                </button>

                <div x-show="abierto" x-cloak @click.outside="abierto = false" x-transition.opacity
                     class="absolute left-16 bottom-0 w-80 z-50 nm-glass bg-white dark:bg-nm-surface text-slate-800 dark:text-slate-100 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-4 text-sm">
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
                                    <select x-model="p.vista" class="flex-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-nm-sunken px-2 py-1.5">
                                        <option value="">— Nada —</option>
                                        <template x-for="v in vistas" :key="v.url"><option :value="v.url" x-text="v.nombre"></option></template>
                                    </select>
                                </div>
                            </template>
                            <template x-if="pantallas.length">
                                <button type="button" @click="repartir()" class="w-full mt-1 px-2.5 py-2 rounded-xl bg-blue-600 text-white text-xs font-black">Abrir en los monitores</button>
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
        <nav id="navMovilInferior" class="fixed bottom-3 left-3 right-3 z-50 flex md:hidden items-center justify-around nm-glass p-2 rounded-2xl shadow-2xl transition-all">
            @foreach($navItems as $item)
            <a href="{{ $item['href'] }}" title="{{ $item['title'] }}" @if($item['active']) aria-current="page" @endif
               class="flex flex-col items-center gap-1 p-2 rounded-xl text-xs font-bold transition active:scale-95 {{ $item['active'] ? 'text-hacienda-blue dark:text-blue-300 bg-slate-100 dark:bg-slate-800/80' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}" aria-label="{{ $item['title'] }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true">{!! $item['svg'] !!}</svg>
                <span class="text-[11px] leading-none">{{ $item['label'] }}</span>
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
    {{-- Avisos y confirmaciones propios (sustituyen alert/confirm nativos, que bloquean y no siguen el tema) --}}
    <div id="nmAvisos" class="fixed top-4 right-4 z-[80] flex flex-col gap-2 w-[min(24rem,calc(100vw-2rem))]" role="status" aria-live="polite"></div>
    <div id="nmDialogo" class="fixed inset-0 z-[90] hidden items-center justify-center p-4 bg-slate-950/40 backdrop-blur-sm">
        <div role="alertdialog" aria-modal="true" aria-labelledby="nmDialogoTitulo" aria-describedby="nmDialogoTexto"
             class="nm-glass w-full max-w-md rounded-2xl shadow-2xl p-5 text-slate-800 dark:text-slate-100">
            <h2 id="nmDialogoTitulo" class="text-base font-bold mb-1">Confirmar acción</h2>
            <p id="nmDialogoTexto" class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line"></p>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" data-respuesta="no" class="px-4 py-2 rounded-lg text-sm font-semibold border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">Cancelar</button>
                <button type="button" data-respuesta="si" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-red-600 hover:bg-red-700">Confirmar</button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const ESTILOS = {
                error: 'border-l-red-500', advertencia: 'border-l-amber-500', exito: 'border-l-emerald-500', info: 'border-l-blue-600',
            };
            window.nmAviso = function (mensaje, tipo = 'info') {
                const caja = document.getElementById('nmAvisos');
                const el = document.createElement('div');
                el.className = 'nm-glass nm-entrada rounded-xl shadow-lg border-l-4 px-4 py-3 text-sm text-slate-800 dark:text-slate-100 whitespace-pre-line flex items-start gap-3 ' + (ESTILOS[tipo] || ESTILOS.info);
                const texto = document.createElement('span');
                texto.className = 'flex-1';
                texto.textContent = String(mensaje);
                const cerrar = document.createElement('button');
                cerrar.type = 'button';
                cerrar.setAttribute('aria-label', 'Cerrar aviso');
                cerrar.className = 'text-slate-500 hover:text-slate-800 dark:hover:text-white text-lg leading-none';
                cerrar.textContent = '×';
                cerrar.onclick = () => el.remove();
                el.append(texto, cerrar);
                caja.appendChild(el);
                // Los errores se quedan más tiempo: hay que leerlos y actuar
                setTimeout(() => el.remove(), tipo === 'error' ? 9000 : 5000);
            };

            window.nmConfirmar = function (mensaje, { titulo = 'Confirmar acción', boton = 'Confirmar', peligro = true } = {}) {
                const fondo = document.getElementById('nmDialogo');
                const si = fondo.querySelector('[data-respuesta="si"]');
                const no = fondo.querySelector('[data-respuesta="no"]');
                document.getElementById('nmDialogoTitulo').textContent = titulo;
                document.getElementById('nmDialogoTexto').textContent = String(mensaje);
                si.textContent = boton;
                si.className = 'px-4 py-2 rounded-lg text-sm font-semibold text-white ' + (peligro ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700');
                const previo = document.activeElement;
                fondo.classList.replace('hidden', 'flex');
                no.focus();
                return new Promise((resolver) => {
                    const terminar = (valor) => {
                        fondo.classList.replace('flex', 'hidden');
                        fondo.removeEventListener('keydown', teclas);
                        si.onclick = no.onclick = fondo.onclick = null;
                        previo?.focus?.();
                        resolver(valor);
                    };
                    const teclas = (e) => {
                        if (e.key === 'Escape') terminar(false);
                        if (e.key === 'Tab') { e.preventDefault(); (document.activeElement === si ? no : si).focus(); }
                    };
                    fondo.addEventListener('keydown', teclas);
                    si.onclick = () => terminar(true);
                    no.onclick = () => terminar(false);
                    fondo.onclick = (e) => { if (e.target === fondo) terminar(false); };
                });
            };
        })();
    </script>
    <script>
        // Entrada escalonada de las tarjetas visibles al cargar; se retira la clase para que los
        // re-render de Alpine (sondeos en vivo) no vuelvan a animar.
        document.addEventListener('DOMContentLoaded', function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const alto = window.innerHeight;
            let i = 0;
            document.querySelectorAll('#contenidoPrincipal .floating-card').forEach(function (el) {
                if (i >= 14 || el.getBoundingClientRect().top > alto) return;
                el.style.setProperty('--nm-i', i++);
                el.classList.add('nm-entrada');
                el.addEventListener('animationend', function () { el.classList.remove('nm-entrada'); }, { once: true });
            });
        });

        // Colores por defecto de Chart.js alineados con la paleta (las gráficas con colores propios no cambian)
        (function () {
            if (!window.Chart) return;
            function aplicar() {
                const oscuro = document.documentElement.classList.contains('dark');
                Chart.defaults.font.family = "'Inter', sans-serif";
                Chart.defaults.color = oscuro ? '#9aa8bf' : '#637087';
                Chart.defaults.borderColor = oscuro ? 'rgba(71, 85, 105, 0.42)' : '#e3e7ed';
                Chart.defaults.plugins.tooltip.backgroundColor = oscuro ? '#111a2b' : '#0a1d4a';
                Chart.defaults.plugins.tooltip.cornerRadius = 8;
                Chart.defaults.plugins.tooltip.padding = 10;
                Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 500;
            }
            aplicar();
            window.addEventListener('theme-changed', aplicar);
        })();
    </script>
    @yield('scripts')
</body>
</html>
