<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Segundos entre refrescos de las zonas marcadas con data-refrescar (cada vista puede cambiarlo) --}}
    <meta name="nm-refresco" content="@yield('refresco', 30)">
    <title>@yield('title', 'Network Dashboard')</title>
    @include('partials.favicon')
    <!-- Tailwind CSS (via CDN & Vite) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Escalas del sistema de diseño (partials/design-tokens): "blue" y "sky" = azul principal #667D93
        // (un solo tono en 300–800),
        // "indigo" = marino gris #465055, "orange" = naranja NOC #FF8300.
        // Se redefinen para que las clases existentes de las vistas adopten la paleta.
        const nmAzure = { 50: '#f3f5f7', 100: '#e4e8ec', 200: '#cad2da', 300: '#667d93', 400: '#667d93', 500: '#667d93', 600: '#667d93', 700: '#667d93', 800: '#667d93', 900: '#2d3741', 950: '#1b2127' };
        const nmNavy = { 50: '#f1f2f3', 100: '#dde0e2', 200: '#bbc1c3', 300: '#98a1a5', 400: '#778288', 500: '#5e6a70', 600: '#505b61', 700: '#465055', 800: '#384044', 900: '#2c3336', 950: '#1b1f21' };
        // Naranja: un solo tono (#FF8300) para texto, iconos y bordes; 50–200 son fondos tenues
        // y 900–950 fondos del modo oscuro
        const nmOrange = { 50: '#fff6f1', 100: '#ffece0', 200: '#ffd5ba', 300: '#ff8300', 400: '#ff8300', 500: '#ff8300', 600: '#ff8300', 700: '#ff8300', 800: '#ff8300', 900: '#723802', 950: '#3d1a00' };
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'hacienda-blue': '#465055',
                        'hacienda-orange': '#ff8300',
                        'inst-azul': '#667d93',
                        'inst-naranja': '#ff8300',
                        'nm-primary': 'rgb(var(--nm-primary-rgb) / <alpha-value>)',
                        'nm-surface': 'rgb(var(--nm-surface-rgb) / <alpha-value>)',
                        'nm-sunken': 'rgb(var(--nm-sunken-rgb) / <alpha-value>)',
                        'nm-ink': 'rgb(var(--nm-ink-rgb) / <alpha-value>)',
                        blue: nmAzure,
                        indigo: nmNavy,
                        sky: nmAzure,
                        orange: nmOrange,
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
    {{-- Versiones fijas: el navegador las guarda en caché un año (sin versión, la URL se resuelve en cada visita) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.17.5/dist/cdn.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.6.1/dist/chart.umd.min.js"></script>
    <!-- Anti-FOUC Theme Initializer -->
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

        /* Cinta de alertas: carmesí que se difumina hacia el azul institucional (#667D93, oscurecido al final para que
           el texto blanco tenga contraste). Los tonos intermedios son malvas apagados para que la transición no ensucie. */
        .nm-cinta {
            background:
                linear-gradient(180deg, rgb(255 255 255 / 0.10) 0%, rgb(255 255 255 / 0) 55%),
                radial-gradient(90% 220% at 0% 50%, rgb(248 113 113 / 0.38) 0%, rgb(248 113 113 / 0) 60%),
                linear-gradient(90deg, #9b1c2c 0%, #8b2638 18%, #75384f 38%, #654e67 58%, #5c6279 78%, #57708a 100%);
            border: 1px solid rgb(255 255 255 / 0.12);
            box-shadow: 0 12px 30px -14px rgb(155 28 44 / 0.65), 0 8px 24px -16px rgb(70 80 85 / 0.6), inset 0 1px 0 rgb(255 255 255 / 0.14);
        }
        /* ==================== Escenas animadas de las tarjetas KPI (components/kpi-escena) ====================
           Decorativas: líneas finas, opacidad baja y desvanecidas hacia el texto para no competir con los datos. */
        .kpi-escena {
            position: absolute; top: 0; right: 0; bottom: 0; width: 62%;
            pointer-events: none; color: var(--escena-color, rgb(var(--nm-primary-rgb)));
            opacity: .22;
            -webkit-mask-image: linear-gradient(to left, #000 45%, transparent 100%);
            mask-image: linear-gradient(to left, #000 45%, transparent 100%);
        }
        /* En las tarjetas con mini gráfica a la derecha, la escena va en el hueco entre el valor y la gráfica */
        .kpi-escena.kpi-escena-centro {
            right: 112px; width: 44%;
            -webkit-mask-image: linear-gradient(to left, transparent 0, #000 22%, #000 58%, transparent 100%);
            mask-image: linear-gradient(to left, transparent 0, #000 22%, #000 58%, transparent 100%);
        }
        .dark .kpi-escena { opacity: .32; }
        .kpi-escena.kpi-escena-clara { opacity: .26; }
        .kpi-escena svg { width: 100%; height: 100%; display: block; }
        .kpi-escena .t { fill: none; stroke: currentColor; stroke-width: 1.3; stroke-linecap: round; stroke-linejoin: round; }
        .kpi-escena circle:not(.t) { fill: currentColor; }
        @keyframes kpiParpadeo { 0%, 100% { opacity: .2; } 50% { opacity: 1; } }
        @keyframes kpiFlujo { to { stroke-dashoffset: -40; } }
        @keyframes kpiGira { to { transform: rotate(360deg); } }
        @keyframes kpiCae { 0% { transform: translateY(0); opacity: 0; } 15% { opacity: 1; } 100% { transform: translateY(26px); opacity: 0; } }
        @keyframes kpiOnda { from { transform: scale(.35); opacity: .9; } to { transform: scale(1.5); opacity: 0; } }
        @keyframes kpiBarrido { 0% { transform: translateY(0); opacity: 0; } 20%, 80% { opacity: 1; } 100% { transform: translateY(66px); opacity: 0; } }
        @keyframes kpiDesliza { to { transform: translateX(-80px); } }
        @keyframes kpiNodo { 0%, 100% { opacity: .35; } 50% { opacity: 1; } }
        .kpi-led { animation: kpiParpadeo 2.6s ease-in-out infinite; }
        .kpi-flujo { stroke-dasharray: 3 7; animation: kpiFlujo 3s linear infinite; }
        .kpi-gira { transform-box: fill-box; transform-origin: center; animation: kpiGira 30s linear infinite; }
        .kpi-arena { animation: kpiCae 2.1s linear infinite; }
        .kpi-onda { transform-box: fill-box; transform-origin: center; animation: kpiOnda 3.2s ease-out infinite; }
        .kpi-barrido { animation: kpiBarrido 3.6s ease-in-out infinite; }
        .kpi-desliza { animation: kpiDesliza 7s linear infinite; }
        .kpi-nodo { animation: kpiNodo 3s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) {
            .kpi-escena * { animation: none !important; }
        }

        /* Los extremos del texto en movimiento se desvanecen sobre cualquier fondo (sin bloques de color encima) */
        .nm-cinta-pista {
            -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 32px, #000 calc(100% - 32px), transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 32px, #000 calc(100% - 32px), transparent 100%);
        }

        /* ======================== BOTÓN FANCY (Uiverse) con colores institucionales ========================
           Marco azul #667D93 con guion naranja #FF8300; al pasar el mouse se rellena de azul y las "llaves"
           (cortes del marco) se recogen. Las llaves toman el color de la superficie de atrás (--fancy-fondo):
           blanco sobre tarjetas; en zonas sobre el fondo gris de la página se pasa style="--fancy-fondo: var(--nm-bg)". */
        .fancy {
            --fancy-color: var(--nm-azure-600);
            --fancy-acento: var(--nm-orange-500);
            --fancy-fondo: var(--nm-surface);
            background-color: transparent;
            border: 2px solid var(--fancy-color);
            border-radius: 0;
            box-sizing: border-box;
            color: var(--fancy-color);
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
            white-space: nowrap;
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
            background: var(--fancy-acento);
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
            color: var(--fancy-color);
        }
        .fancy .top-key {
            height: 2px; width: 1.5625rem; top: -2px; left: 0.625rem; position: absolute;
            background: var(--fancy-fondo);
            transition: width 0.5s ease-out, left 0.3s ease-out;
        }
        .fancy .bottom-key-1 {
            height: 2px; width: 1.5625rem; right: 1.875rem; bottom: -2px; position: absolute;
            background: var(--fancy-fondo);
            transition: width 0.5s ease-out, right 0.3s ease-out;
        }
        .fancy .bottom-key-2 {
            height: 2px; width: 0.625rem; right: 0.625rem; bottom: -2px; position: absolute;
            background: var(--fancy-fondo);
            transition: width 0.5s ease-out, right 0.3s ease-out;
        }
        .fancy:hover { color: #ffffff; background: var(--fancy-color); }
        .fancy:hover::before { width: 0.8rem; background: #ffffff; }
        .fancy:hover .text { color: #ffffff; padding-left: 1.25em; }
        .fancy:hover .top-key { left: -2px; width: 0; }
        .fancy:hover .bottom-key-1, .fancy:hover .bottom-key-2 { right: 0; width: 0; }
        .fancy:focus-visible { outline: 2px solid var(--nm-ring); outline-offset: 3px; }
        .fancy:disabled { opacity: 0.55; cursor: not-allowed; }
        .fancy:disabled:hover { background: transparent; }
        .fancy:disabled:hover .text { color: var(--fancy-color); }

        /* Variante compacta */
        .fancy-sm { padding: 0.4em 0.85em; font-size: 11px; }
        .fancy-sm::before { left: 0.65em; width: 0.9rem; }
        .fancy-sm .text { padding-left: 1.2em; font-size: 0.95em; gap: 0.3rem; }
        .fancy-sm:hover .text { padding-left: 0.95em; }
        .fancy-sm:hover::before { width: 0.55rem; }
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
                'href'   => route('alertas.index'),
                'active' => request()->is('alertas*'),
                'title'  => 'Alertas: incidencias activas e historial',
                'label'  => 'Alertas',
                'svg'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.27 21a2 2 0 0 0 3.46 0M3.26 15.33A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.67C19.41 13.96 18 12.5 18 8A6 6 0 0 0 6 8c0 4.5-1.41 5.96-2.74 7.33"></path>',
            ],
            [
                'href'   => route('servicios_web.index'),
                'active' => request()->is('servicios-web*'),
                'title'  => 'Monitoreo de Servicios Web y HTTP',
                'label'  => 'Webs',
                'svg'    => '<circle cx="12" cy="12" r="9.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M12 2.5a14 14 0 0 0 0 19 14 14 0 0 0 0-19M2.5 12h19"></path>',
            ],
            [
                'href'   => route('firewalls.index'),
                'active' => request()->is('firewalls*'),
                'title'  => 'Firewalls FortiGate: HA, túneles, SD-WAN y seguridad',
                'label'  => 'Firewalls',
                'svg'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"></path>',
            ],
            [
                'href'   => route('virtualizacion.index'),
                'active' => request()->is('virtualizacion*'),
                'title'  => 'Virtualización VMware: hosts ESXi, VMs y datastores',
                'label'  => 'Virtualización',
                'svg'    => '<rect width="20" height="8" x="2" y="2" rx="2"></rect><rect width="20" height="8" x="2" y="14" rx="2"></rect><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.01M6 18h.01"></path>',
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
                    <img src="{{ asset('images/logo_shp.png') }}" alt="Secretaría de la Hacienda Pública" width="50" height="50" class="w-full h-full object-contain">
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
            {{-- Logo SHP a su tamaño original (50 × 50): árbol blanco y arco naranja sobre la barra azul --}}
            <div class="mb-12">
                <img src="{{ asset('images/logo_shp.png') }}" alt="Secretaría de la Hacienda Pública" width="50" height="50" class="w-[50px] h-[50px] object-contain">
            </div>

            <!-- Nav Icons (generados desde $navItems) -->
            <nav class="flex-1 flex flex-col gap-6 items-center w-full">
                @foreach($navItems as $item)
                <a href="{{ $item['href'] }}"
                   class="nm-nav-item w-12 h-12 {{ $item['active'] ? 'bg-white dark:bg-slate-800/90 text-hacienda-blue dark:text-blue-300 shadow-md dark:shadow-none border border-transparent dark:border-slate-700/50' : 'text-white/85 hover:bg-white/15 dark:hover:bg-slate-800/50 hover:text-white dark:hover:text-slate-200' }} rounded-xl flex items-center justify-center"
                   title="{{ $item['title'] }}" aria-label="{{ $item['title'] }}" @if($item['active']) aria-current="page" @endif>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true">{!! $item['svg'] !!}</svg>
                </a>
                @endforeach
            </nav>

            <!-- Pantallas NOC: ventanas para otras TV, pantalla completa y modo TV -->
            <div class="relative mt-6" x-data="pantallasNoc()" @keydown.escape.window="abierto = false">
                <button type="button" @click="abrirPanel()" title="Pantallas: abrir vistas en otras TV" aria-label="Pantallas"
                        class="nm-nav-item w-12 h-12 rounded-xl flex items-center justify-center text-white/85 hover:bg-white/15 dark:hover:bg-slate-800/50 hover:text-white"
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

                    <div class="text-[11px] font-black uppercase tracking-wider text-slate-400 mt-3 mb-1.5">Sonido de incidencias</div>
                    <div class="flex gap-1.5">
                        <button type="button" @click="sonido = nocSonido.alternar()" class="flex-1 px-2.5 py-2 rounded-xl border text-xs font-bold text-left flex items-center gap-2"
                                :class="sonido ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300' : 'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400'"
                                :aria-pressed="sonido.toString()">
                            <svg x-show="sonido" class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
                            <svg x-show="!sonido" class="w-4 h-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /><path d="M17 17H4a1 1 0 0 1-.74-1.673C4.59 13.956 6 12.499 6 8a6 6 0 0 1 .258-1.742" /><path d="m2 2 20 20" /><path d="M8.668 3.01A6 6 0 0 1 18 8c0 2.687.77 4.653 1.707 6.05" /></svg>
                            <span x-text="sonido ? 'Alerta sonora activa' : 'Alerta sonora desactivada'"></span>
                        </button>
                        <button type="button" @click="nocSonido.probar('critica')" class="px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold" title="Escuchar el aviso de una incidencia crítica">Probar</button>
                    </div>
                    <p class="text-[11px] mt-2" :class="error ? 'text-rose-600' : 'text-slate-500'" x-show="aviso" x-text="aviso"></p>
                </div>
            </div>
        </aside>

        <!-- ==================== MAIN CONTENT ==================== -->
        <main id="contenidoPrincipal" class="flex-1 flex flex-col h-full overflow-y-auto custom-scrollbar pr-0 md:pr-2 pb-24 md:pb-0 relative">
            <div data-refrescar="alertas" data-refrescar-cada="5" data-refrescar-url="{{ route('parciales.alertas') }}">
                @include('partials.alert_banner')
            </div>
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
        <button type="button" id="tvBotonSonido" onclick="window.nocSonido.pintarBotonTV(window.nocSonido.alternar())" class="px-3 py-2 rounded-xl hover:bg-white/15 text-xs font-bold flex items-center gap-2"
                title="Activar o silenciar la alerta sonora de incidencias nuevas">
            <svg data-sonido="activo" class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg>
            <svg data-sonido="inactivo" class="w-4 h-4 hidden" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /><path d="M17 17H4a1 1 0 0 1-.74-1.673C4.59 13.956 6 12.499 6 8a6 6 0 0 1 .258-1.742" /><path d="m2 2 20 20" /><path d="M8.668 3.01A6 6 0 0 1 18 8c0 2.687.77 4.653 1.707 6.05" /></svg>
            <span data-sonido="texto">Alerta sonora</span>
        </button>
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
                abierto: false, aviso: '', error: false, pantallas: [], sonido: window.nocSonido ? window.nocSonido.activo() : true,
                soportaPantallas: 'getScreenDetails' in window,
                vistas: [
                    { nombre: 'Dashboard', url: @json(route('dashboard')) },
                    { nombre: 'Topología', url: @json(route('topologia.index')) },
                    { nombre: 'Centro de datos', url: @json(route('topologia.centro_datos')) },
                    { nombre: 'Servicios web', url: @json(route('servicios_web.index')) },
                    { nombre: 'Esta página', url: location.pathname + location.search },
                ],
                abrirPanel() { this.abierto = !this.abierto; this.aviso = ''; },
                // Las acciones de la ventana viven en window.nocPantallas (también las usan los controles del Modo TV)
                pantallaCompleta() { this.abierto = false; window.nocPantallas.pantallaCompleta(); },
                activarModoTV() { window.nocPantallas.activarModoTV(); },
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

    <script>
        // ==================== CINTA DE ALERTAS ====================
        // Velocidad constante y legible sin importar cuántas alertas haya
        window.nocAjustarMarquesina = function () {
            const track = document.getElementById('nocMarqueeTrack');
            const lane = track?.querySelector('.noc-marquee-lane');
            if (!lane) return;
            track.style.setProperty('--noc-marquee-duration', Math.max(20, lane.offsetWidth / 40).toFixed(1) + 's');
        };
        document.addEventListener('DOMContentLoaded', () => {
            window.nocAjustarMarquesina();
            document.fonts?.ready?.then(window.nocAjustarMarquesina);
        });
        // Las escenas animadas de las tarjetas que se refrescan arrancarían de cero en cada refresco (un salto
        // visible): se adelantan al tiempo de la página para que sigan como si nada hubiera cambiado.
        document.addEventListener('nm:refrescado', () => {
            const t = document.timeline?.currentTime;
            if (t == null) return;
            document.querySelectorAll('.kpi-escena').forEach(e => e.getAnimations?.({ subtree: true }).forEach(a => { a.currentTime = t; }));
        });

        let nocMarquesinaT;
        window.addEventListener('resize', () => { clearTimeout(nocMarquesinaT); nocMarquesinaT = setTimeout(window.nocAjustarMarquesina, 200); });

        // "Ver Alertas" de la cinta: la sección Alertas con todas las incidencias activas
        window.handleBannerNotifClick = function () {
            window.location.href = @json(route('alertas.index'));
        };

        // ==================== AVISO SONORO DE INCIDENCIAS ====================
        // Suena cuando la cinta de alertas (refrescada cada 10 s) trae un id que no estaba: tres pitidos agudos si
        // hay alguna crítica, uno suave si solo son advertencias. Las alertas que ya existían al abrir no suenan, y
        // varias pestañas del mismo navegador suenan una sola vez por incidencia. El sonido se genera con Web Audio
        // (sin archivos); el navegador lo permite solo después de un clic en la página.
        window.nocSonido = (function () {
            const PREF = 'nocSonidoAlertas', SONADAS = 'nocAlertasSonadas';
            const leer = (k, d) => { try { return localStorage.getItem(k) ?? d; } catch (e) { return d; } };
            const escribir = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
            let ctx = null, conocidas = new Set();

            const activo = () => leer(PREF, '1') !== '0';
            const contexto = () => {
                if (!ctx) {
                    const C = window.AudioContext || window.webkitAudioContext;
                    ctx = C ? new C() : null;
                }
                return ctx;
            };
            const alertasActuales = () => {
                try { return JSON.parse(document.getElementById('nocAlertasIds')?.innerHTML || '[]'); } catch (e) { return []; }
            };

            function tono(frecuencia, inicio, duracion, volumen) {
                const c = ctx, t = c.currentTime + inicio;
                const o = c.createOscillator(), g = c.createGain();
                o.type = 'sine';
                o.frequency.value = frecuencia;
                g.gain.setValueAtTime(0.0001, t);
                g.gain.exponentialRampToValueAtTime(volumen, t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + duracion);
                o.connect(g).connect(c.destination);
                o.start(t);
                o.stop(t + duracion + 0.05);
            }

            function pitar(tipo) {
                const c = contexto();
                if (!c) return;
                if (c.state !== 'running') { avisoDesbloqueo(true); return; }
                if (tipo === 'critica') [0, 0.24, 0.48].forEach(t => tono(988, t, 0.17, 0.35));
                else tono(660, 0, 0.3, 0.22);
            }

            // Botón flotante mientras el navegador no deje sonar (falta un clic en la página)
            function avisoDesbloqueo(mostrar) {
                let b = document.getElementById('nocActivarSonido');
                if (!mostrar) { b?.remove(); return; }
                if (b || !activo()) return;
                b = document.createElement('button');
                b.id = 'nocActivarSonido';
                b.type = 'button';
                b.className = 'fixed bottom-4 right-4 z-[70] px-4 py-2.5 rounded-xl text-sm font-bold text-white shadow-2xl flex items-center gap-2';
                b.style.background = 'var(--nm-metal-sheen), var(--nm-metal-azure)';
                b.innerHTML = '<svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0" /><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" /></svg><span>Habilitar alerta sonora</span>';
                b.title = 'El navegador necesita un clic para permitir el sonido';
                b.onclick = () => contexto()?.resume().then(() => { avisoDesbloqueo(false); pitar('advertencia'); });
                document.body.appendChild(b);
            }

            // Botón del Modo TV: alterna el ícono de campana / campana tachada y el texto
            function pintarBotonTV(encendido) {
                const b = document.getElementById('tvBotonSonido');
                if (!b) return;
                b.querySelector('[data-sonido="activo"]').classList.toggle('hidden', !encendido);
                b.querySelector('[data-sonido="inactivo"]').classList.toggle('hidden', encendido);
                b.querySelector('[data-sonido="texto"]').textContent = encendido ? 'Alerta sonora' : 'Alerta sonora silenciada';
            }

            function revisar() {
                const actuales = alertasActuales();
                const nuevas = actuales.filter(a => a.id && !conocidas.has(a.id));
                conocidas = new Set(actuales.map(a => a.id));
                if (!nuevas.length || !activo()) return;
                // Una sola pestaña suena por incidencia (las demás la ven ya anotada)
                let sonadas = {};
                try { sonadas = JSON.parse(leer(SONADAS, '{}')) || {}; } catch (e) {}
                const ahora = Date.now();
                Object.keys(sonadas).forEach(id => { if (ahora - sonadas[id] > 6 * 3600e3) delete sonadas[id]; });
                const porSonar = nuevas.filter(a => !sonadas[a.id]);
                if (!porSonar.length) return;
                porSonar.forEach(a => { sonadas[a.id] = ahora; });
                escribir(SONADAS, JSON.stringify(sonadas));
                pitar(porSonar.some(a => a.tipo === 'critica') ? 'critica' : 'advertencia');
            }

            document.addEventListener('DOMContentLoaded', () => {
                conocidas = new Set(alertasActuales().map(a => a.id));   // lo que ya había al abrir no suena
                const botonTV = document.getElementById('tvBotonSonido');
                if (botonTV) pintarBotonTV(activo());
                contexto();
                // En una TV nadie hace clic: se ofrece el botón desde el inicio. En una PC basta cualquier clic.
                if (document.documentElement.classList.contains('modo-tv') && activo() && ctx && ctx.state !== 'running') avisoDesbloqueo(true);
            });
            // Cualquier clic o tecla habilita el sonido para el resto de la sesión
            ['pointerdown', 'keydown', 'touchstart'].forEach(ev => document.addEventListener(ev, () => {
                const c = contexto();
                if (c && c.state !== 'running') c.resume().then(() => avisoDesbloqueo(false)).catch(() => {});
            }, { passive: true }));
            document.addEventListener('nm:refrescado', e => { if (e.detail.claves.includes('alertas')) revisar(); });

            return {
                activo,
                pintarBotonTV,
                alternar() {
                    escribir(PREF, activo() ? '0' : '1');
                    if (!activo()) avisoDesbloqueo(false);
                    return activo();
                },
                probar(tipo = 'critica') { contexto()?.resume().then(() => { avisoDesbloqueo(false); pitar(tipo); }); },
            };
        })();

        // ==================== REFRESCO AUTOMÁTICO DE ZONAS ====================
        // Cada zona con data-refrescar="clave" se vuelve a pedir en segundo plano (la página actual o su
        // data-refrescar-url) y se reemplaza su contenido solo si cambió. No recarga la página: se conservan el
        // scroll, el zoom y el estado de Alpine del elemento marcado (p. ej. los filtros de una tabla).
        // Se salta una zona mientras el ratón está encima o el foco está dentro (alguien la está usando).
        // data-refrescar-cada="10" da a una zona su propio intervalo en segundos (las alertas van más rápido
        // que el resto); sin él, la zona usa el de la página (meta nm-refresco).
        window.nocRefresco = (function () {
            const segundos = Math.max(10, parseInt(document.querySelector('meta[name="nm-refresco"]')?.content || '30', 10));
            const TICK_S = 5;
            const ultimoHtml = new WeakMap();
            const ultimaVez = new WeakMap();
            let enCurso = false;

            const intervaloDe = (zona) => Math.max(TICK_S, parseInt(zona.dataset.refrescarCada || segundos, 10) || segundos);
            const toca = (zona, ahora) => ahora - (ultimaVez.get(zona) || 0) >= intervaloDe(zona) * 1000 - 500;

            // data-refrescar-pausa="selector": la zona espera mientras exista eso (p. ej. un menú abierto)
            const enUso = (zona) => zona.matches(':hover')
                || (document.activeElement && document.activeElement !== document.body && zona.contains(document.activeElement))
                || (zona.dataset.refrescarPausa && zona.querySelector(zona.dataset.refrescarPausa));

            async function ciclo(todas = false) {
                if (enCurso || document.hidden) return;
                const ahora = Date.now();
                const zonas = [...document.querySelectorAll('[data-refrescar]')].filter(z => todas === true || toca(z, ahora));
                if (!zonas.length) return;
                zonas.forEach(z => ultimaVez.set(z, ahora));
                enCurso = true;
                try {
                    const porUrl = {};
                    zonas.forEach(z => (porUrl[z.dataset.refrescarUrl || location.href] ??= []).push(z));
                    for (const [url, lista] of Object.entries(porUrl)) {
                        let doc;
                        try {
                            const r = await fetch(url, { credentials: 'same-origin', redirect: 'manual',
                                headers: { 'Accept': 'text/html', 'X-Refresco-Suave': '1', 'X-Requested-With': 'XMLHttpRequest' } });
                            if (!r.ok) continue;   // sesión vencida o error: se reintenta en el siguiente ciclo
                            doc = new DOMParser().parseFromString(await r.text(), 'text/html');
                        } catch (e) { continue; }
                        const cambiadas = [];
                        lista.forEach(zona => {
                            const clave = zona.dataset.refrescar;
                            const nueva = doc.querySelector(`[data-refrescar="${CSS.escape(clave)}"]`);
                            if (!nueva || enUso(zona)) return;
                            const html = nueva.innerHTML;
                            if (ultimoHtml.get(zona) === html) return;
                            ultimoHtml.set(zona, html);
                            zona.innerHTML = html;   // Alpine inicializa lo nuevo con el estado del elemento marcado
                            cambiadas.push(clave);
                        });
                        if (cambiadas.length) {
                            if (cambiadas.includes('alertas')) window.nocAjustarMarquesina();
                            document.dispatchEvent(new CustomEvent('nm:refrescado', { detail: { claves: cambiadas } }));
                        }
                    }
                } finally {
                    enCurso = false;
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                // Lo recién cargado cuenta como actualizado: el primer pedido de cada zona llega a su intervalo
                document.querySelectorAll('[data-refrescar]').forEach(z => ultimaVez.set(z, Date.now()));
                setInterval(ciclo, TICK_S * 1000);
                // Al volver a la pestaña se actualiza todo enseguida
                document.addEventListener('visibilitychange', () => { if (!document.hidden) ciclo(true); });
            });
            return { ahora: () => ciclo(true), segundos };
        })();
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
                Chart.defaults.plugins.tooltip.backgroundColor = oscuro ? '#111a2b' : '#2c3336';
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
