<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('descripcion', 'Acceso al Centro de Operaciones de Red de la Secretaría de la Hacienda Pública.')">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Acceso Institucional') - NOC</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            background-color: #f3f4f6;
            background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 20px 40px -12px rgba(70, 80, 85, 0.25), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .text-institutional-blue { color: #667d93; }
        .bg-institutional-blue { background-color: #667d93; }
        .text-institutional-orange { color: #ff8300; }
        .bg-institutional-orange { background-color: #ff8300; }

        /* Panel institucional SHP: azul #667D93 con brillo metálico, igual que la barra lateral */
        .brand-panel {
            background:
                linear-gradient(135deg, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0) 45%),
                linear-gradient(165deg, #6f869c 0%, #667d93 45%, #4f6478 100%);
            position: relative;
            overflow: hidden;
        }
        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 9999px;
            border: 2px solid rgba(255,255,255,0.08);
            pointer-events: none;
        }
        .brand-panel::before { width: 420px; height: 420px; right: -180px; bottom: -180px; }
        .brand-panel::after  { width: 260px; height: 260px; right: -90px;  bottom: -90px; border-color: rgba(255,131,0,0.22); }

        /* Campos */
        .login-input {
            appearance: none;
            display: block;
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            font-size: 0.875rem;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .login-input:focus {
            outline: none;
            border-color: #667d93;
            box-shadow: 0 0 0 3px rgba(102,125,147,0.2);
        }
        .login-input.con-boton { padding-right: 2.75rem; }
        .login-input[aria-invalid="true"] { border-color: #dc2626; }
        .login-input[aria-invalid="true"]:focus { box-shadow: 0 0 0 3px rgba(220,38,38,0.18); }

        .btn-ojo {
            position: absolute; top: 0; right: 0; bottom: 0;
            width: 2.75rem;
            display: flex; align-items: center; justify-content: center;
            color: #9ca3af;
            border-radius: 0 0.375rem 0.375rem 0;
            transition: color .2s ease;
        }
        .btn-ojo:hover, .btn-ojo:focus-visible { color: #667d93; outline: none; }

        /* Botón "fancy" (Uiverse.io / cssbuttons-io) con colores institucionales: marco azul y guion naranja */
        .fancy {
            background-color: transparent;
            border: 2px solid #667d93;
            border-radius: 0;
            box-sizing: border-box;
            color: #667d93;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin: 0;
            outline: none;
            overflow: visible;
            padding: 0.85em 2em;
            position: relative;
            text-align: center;
            text-decoration: none;
            text-transform: uppercase;
            transition: all 0.3s ease-in-out;
            user-select: none;
            font-size: 13px;
        }
        .fancy::before {
            content: " ";
            width: 1.5625rem;
            height: 2px;
            background: #ff8300;
            top: 50%;
            left: 1.5em;
            position: absolute;
            transform: translateY(-50%);
            transform-origin: center;
            transition: background 0.3s linear, width 0.3s linear;
        }
        .fancy .text {
            font-size: 1.05em;
            line-height: 1.33333em;
            padding-left: 2em;
            display: block;
            text-align: left;
            transition: all 0.3s ease-in-out;
            text-transform: uppercase;
            text-decoration: none;
            color: #667d93;
        }
        .fancy .top-key {
            height: 2px; width: 1.5625rem; top: -2px; left: 0.625rem;
            position: absolute; background: #ffffff;
            transition: width 0.5s ease-out, left 0.3s ease-out;
        }
        .fancy .bottom-key-1 {
            height: 2px; width: 1.5625rem; right: 1.875rem; bottom: -2px;
            position: absolute; background: #ffffff;
            transition: width 0.5s ease-out, right 0.3s ease-out;
        }
        .fancy .bottom-key-2 {
            height: 2px; width: 0.625rem; right: 0.625rem; bottom: -2px;
            position: absolute; background: #ffffff;
            transition: width 0.5s ease-out, right 0.3s ease-out;
        }
        .fancy:hover, .fancy:focus-visible { color: white; background: #667d93; }
        .fancy:focus-visible { box-shadow: 0 0 0 3px rgba(102,125,147,0.35); }
        .fancy:hover::before, .fancy:focus-visible::before { width: 0.9375rem; background: white; }
        .fancy:hover .text, .fancy:focus-visible .text { color: white; padding-left: 1.5em; }
        .fancy:hover .top-key, .fancy:focus-visible .top-key { left: -2px; width: 0px; }
        .fancy:hover .bottom-key-1, .fancy:hover .bottom-key-2,
        .fancy:focus-visible .bottom-key-1, .fancy:focus-visible .bottom-key-2 { right: 0; width: 0; }
        .fancy:disabled { opacity: .6; cursor: wait; }

        /* Variante naranja para acciones críticas (confirmación de contraseña) */
        .fancy.fancy-naranja { border-color: #ff8300; }
        .fancy.fancy-naranja::before { background: #667d93; }
        .fancy.fancy-naranja .text { color: #c25f00; }
        .fancy.fancy-naranja:hover, .fancy.fancy-naranja:focus-visible { background: #ff8300; }
        .fancy.fancy-naranja:hover .text, .fancy.fancy-naranja:focus-visible .text { color: white; }

        /* Respaldo de la huella animada: solo se ve si el reproductor Lottie no carga */
        .cargador {
            position: relative;
            width: 76px; height: 76px;
        }
        .cargador::before,
        .cargador::after {
            content: "";
            position: absolute; inset: 0;
            border-radius: 9999px;
            border: 4px solid transparent;
        }
        .cargador::before {
            border-color: rgba(102,125,147,0.18);
            border-top-color: #667d93;
            animation: girar 1.1s linear infinite;
        }
        .cargador::after {
            inset: 12px;
            border-bottom-color: #ff8300;
            border-left-color: #ff8300;
            animation: girar .8s linear infinite reverse;
        }
        @keyframes girar { to { transform: rotate(360deg); } }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp .5s ease-out both; }

        @media (prefers-reduced-motion: reduce) {
            .fade-up { animation: none; }
            .cargador::before, .cargador::after { animation-duration: 3s; }
        }
    </style>
</head>
<body class="font-sans antialiased text-gray-900 flex items-center justify-center min-h-screen p-4 sm:p-6">

    <main class="w-full max-w-4xl mx-auto fade-up">
        <div class="glass-panel rounded-2xl overflow-hidden grid md:grid-cols-5">

            {{-- Panel institucional: logo SHP (árbol blanco + arco naranja) sobre azul --}}
            <aside class="brand-panel md:col-span-2 text-white px-6 py-5 md:p-10 flex md:flex-col items-center md:items-start gap-4 md:gap-0 md:justify-between">
                <div class="flex items-center gap-3 md:block">
                    <img src="{{ asset('images/logo_shp.png') }}" alt="Secretaría de la Hacienda Pública" width="50" height="50" class="w-11 h-11 md:w-[64px] md:h-[64px] object-contain shrink-0">
                    <div class="md:mt-5">
                        <p class="text-[11px] md:text-xs uppercase tracking-[0.18em] text-white/70 font-semibold">Secretaría de la</p>
                        <p class="text-base md:text-xl font-extrabold leading-tight">Hacienda Pública</p>
                    </div>
                </div>

                <div class="hidden md:block relative z-10">
                    <span class="block w-10 h-[3px] bg-institutional-orange mb-4"></span>
                    <p class="text-2xl font-extrabold leading-snug">@yield('panel_titulo', 'Centro de Operaciones de Red')</p>
                    <p class="mt-2 text-sm text-white/75 leading-relaxed">@yield('panel_texto', 'Monitoreo continuo de la infraestructura de red institucional.')</p>
                </div>

                <p class="hidden md:block text-[11px] text-white/55 relative z-10">&copy; {{ date('Y') }} Secretaría de la Hacienda Pública</p>
            </aside>

            {{-- Contenido: logo DTIF como marca principal --}}
            <section class="md:col-span-3 bg-white p-8 sm:p-10">
                <img src="{{ asset('images/logo_dtif.png') }}?v=320x85" alt="Dirección de Tecnología de la Información Financiera" width="320" height="85" class="w-[320px] max-w-full h-auto mx-auto mb-6 object-contain">

                @yield('contenido')
            </section>
        </div>
    </main>

    {{-- Pantalla de espera al enviar el formulario: huella digital animada (Lottie, servida desde el propio
         proyecto: public/vendor/dotlottie-player y public/animaciones). Si el reproductor no carga, se ve el
         cargador CSS que va dentro (una vez cargado, el reproductor lo oculta). --}}
    <div id="loadingOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-sm transition-opacity" role="status" aria-live="polite">
        <div class="flex flex-col items-center">
            <dotlottie-player src="{{ asset('animaciones/huella.lottie') }}" background="transparent" speed="1"
                              style="width: 250px; height: 250px; display: flex; align-items: center; justify-content: center" loop autoplay aria-hidden="true">
                <div class="cargador"></div>
            </dotlottie-player>
            <h2 class="text-xl font-bold @yield('color_carga', 'text-institutional-blue') -mt-4 animate-pulse">@yield('mensaje_carga', 'Verificando credenciales...')</h2>
        </div>
    </div>
    <script src="{{ asset('vendor/dotlottie-player/dotlottie-player.mjs') }}" type="module"></script>

    <script>
        (function () {
            // Mostrar / ocultar contraseña
            document.querySelectorAll('[data-alternar-clave]').forEach(function (boton) {
                var campo = document.getElementById(boton.getAttribute('data-alternar-clave'));
                if (!campo) return;
                boton.addEventListener('click', function () {
                    var visible = campo.type === 'text';
                    campo.type = visible ? 'password' : 'text';
                    boton.setAttribute('aria-pressed', String(!visible));
                    boton.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
                    boton.querySelector('[data-icono="ver"]').classList.toggle('hidden', !visible);
                    boton.querySelector('[data-icono="ocultar"]').classList.toggle('hidden', visible);
                    campo.focus();
                });
            });

            // Aviso de Bloq Mayús
            document.querySelectorAll('[data-aviso-mayus]').forEach(function (aviso) {
                var campo = document.getElementById(aviso.getAttribute('data-aviso-mayus'));
                if (!campo) return;
                var revisar = function (e) {
                    if (typeof e.getModifierState === 'function') {
                        aviso.classList.toggle('hidden', !e.getModifierState('CapsLock'));
                    }
                };
                campo.addEventListener('keydown', revisar);
                campo.addEventListener('keyup', revisar);
                campo.addEventListener('blur', function () { aviso.classList.add('hidden'); });
            });

            // Pantalla de espera + evitar doble envío
            var overlay = document.getElementById('loadingOverlay');
            document.querySelectorAll('form[data-con-carga]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    overlay.classList.remove('hidden');
                    form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
                });
            });

            // Al volver con el botón "Atrás" el navegador puede restaurar la página con la espera visible
            window.addEventListener('pageshow', function (e) {
                if (e.persisted) {
                    overlay.classList.add('hidden');
                    document.querySelectorAll('form[data-con-carga] button[type="submit"]').forEach(function (b) { b.disabled = false; });
                }
            });
        })();
    </script>
</body>
</html>
