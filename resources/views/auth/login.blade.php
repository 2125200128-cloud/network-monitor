<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso Institucional - NOC</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>
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
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .text-institutional-blue { color: #5c8096; }
        .bg-institutional-blue { background-color: #5c8096; }
        .hover-bg-institutional-blue:hover { background-color: #4a6b7d; }
        .text-institutional-orange { color: #e67e22; }
        .bg-institutional-orange { background-color: #e67e22; }
    </style>
</head>
<body class="font-sans antialiased text-gray-900 flex items-center justify-center min-h-screen">
    
    <div class="max-w-md w-full mx-auto p-6">
        <div class="glass-panel rounded-2xl p-8">
            <div class="text-center mb-8">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Hacienda" class="h-20 mx-auto mb-4 dark:brightness-0 dark:invert dark:opacity-90 transition">
                <h1 class="text-3xl font-extrabold text-institutional-blue mb-2">NOC Institucional</h1>
                <p class="text-sm text-gray-500">Acceso Seguro al Centro de Operaciones</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                Las credenciales proporcionadas no son válidas.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" onsubmit="document.getElementById('loadingOverlay').classList.remove('hidden')">
                @csrf

                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Correo Institucional</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-institutional-blue focus:border-institutional-blue sm:text-sm">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-institutional-blue focus:border-institutional-blue sm:text-sm">
                </div>

                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 text-institutional-blue focus:ring-institutional-blue border-gray-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-gray-900">
                            Recordar dispositivo
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-institutional-blue hover-bg-institutional-blue focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-institutional-blue transition-colors duration-200">
                        Ingresar de Forma Segura
                    </button>
                </div>
            </form>
            
            <div class="mt-6 border-t border-gray-200 pt-4 text-center">
                <p class="text-xs text-gray-400">Todo intento de acceso es registrado y auditado.</p>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-sm transition-opacity">
        <div class="flex flex-col items-center">
            <dotlottie-player 
                src="https://lottie.host/79855975-9878-4ae8-be92-6eae04c2e477/JLYlsyf43X.lottie" 
                background="transparent" 
                speed="1" 
                style="width: 250px; height: 250px;" 
                loop 
                autoplay>
            </dotlottie-player>
            <h2 class="text-xl font-bold text-institutional-blue -mt-4 animate-pulse">Verificando credenciales...</h2>
        </div>
    </div>
</body>
</html>
