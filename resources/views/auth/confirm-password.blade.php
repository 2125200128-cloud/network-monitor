<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autorización Requerida - NOC</title>
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
    </style>
</head>
<body class="font-sans antialiased text-gray-900 flex items-center justify-center min-h-screen">
    
    <div class="max-w-md w-full mx-auto p-6">
        <div class="glass-panel rounded-2xl p-8 border-t-4 border-[#e67e22]">
            
            <div class="text-center mb-6">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Hacienda" class="h-20 mx-auto mb-4 dark:brightness-0 dark:invert dark:opacity-90 transition">
                <h2 class="text-2xl font-bold text-gray-800">Zona de Ejecución Crítica</h2>
                <p class="text-sm text-gray-600 mt-3 bg-orange-50 border border-orange-200 p-3 rounded text-left">
                    <strong>Sujeta a Auditoría Forense.</strong><br>
                    Ha ingresado a una zona de alto impacto. Confirme sus credenciales (contraseña) para continuar y autorizar las transacciones.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-4 text-sm text-red-600 bg-red-50 p-3 rounded text-center font-medium">
                    La contraseña proporcionada es incorrecta.
                </div>
            @endif

            <form method="POST" action="{{ route('password.confirm') }}" onsubmit="document.getElementById('loadingOverlay').classList.remove('hidden')">
                @csrf

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña Institucional</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" autofocus class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-institutional-orange focus:border-institutional-orange sm:text-sm">
                </div>

                <div class="flex items-center justify-between mt-4">
                    <a href="{{ url()->previous() }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Cancelar y Volver
                    </a>
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-institutional-orange hover:bg-[#d67118] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-institutional-orange transition-colors duration-200">
                        Confirmar Identidad
                    </button>
                </div>
            </form>
            
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
            <h2 class="text-xl font-bold text-institutional-orange -mt-4 animate-pulse">Autorizando ejecución...</h2>
        </div>
    </div>
</body>
</html>
