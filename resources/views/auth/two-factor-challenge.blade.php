<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación MFA - NOC</title>
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
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .text-institutional-blue { color: #5c8096; }
        .bg-institutional-blue { background-color: #5c8096; }
        .hover-bg-institutional-blue:hover { background-color: #4a6b7d; }
        .text-institutional-orange { color: #e67e22; }
    </style>
    <script>
        function toggleRecovery() {
            document.getElementById('totp-form').classList.toggle('hidden');
            document.getElementById('recovery-form').classList.toggle('hidden');
        }
    </script>
</head>
<body class="font-sans antialiased text-gray-900 flex items-center justify-center min-h-screen">
    
    <div class="max-w-md w-full mx-auto p-6">
        <div class="glass-panel rounded-2xl p-8">
            <div class="text-center mb-6">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Hacienda" class="h-20 mx-auto mb-4 dark:brightness-0 dark:invert dark:opacity-90 transition">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Doble Factor Requerido</h2>
                <p class="text-sm text-gray-500 mt-2">Ingrese el código de su aplicación autenticadora.</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 text-sm text-red-600 bg-red-50 p-3 rounded text-center font-medium">
                    El código proporcionado no es válido.
                </div>
            @endif

            <!-- TOTP Form -->
            <div id="totp-form">
                <form method="POST" action="{{ route('two-factor.login') }}">
                    @csrf
                    <div class="mb-6">
                        <label for="code" class="block text-sm font-medium text-gray-700 text-center mb-2">Token de 6 dígitos</label>
                        <input id="code" type="text" inputmode="numeric" name="code" autofocus autocomplete="one-time-code" placeholder="000000" class="appearance-none block w-full text-center text-2xl tracking-widest px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-institutional-blue focus:border-institutional-blue">
                    </div>

                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-institutional-blue hover-bg-institutional-blue focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-institutional-blue transition-colors duration-200">
                        Verificar Código
                    </button>
                </form>
                <div class="mt-4 text-center">
                    <button type="button" onclick="toggleRecovery()" class="text-sm text-gray-500 hover:text-institutional-blue underline">
                        ¿Usar un código de recuperación?
                    </button>
                </div>
            </div>

            <!-- Recovery Form -->
            <div id="recovery-form" class="hidden">
                <form method="POST" action="{{ route('two-factor.login') }}">
                    @csrf
                    <div class="mb-6">
                        <label for="recovery_code" class="block text-sm font-medium text-gray-700 text-center mb-2">Código de Recuperación de Emergencia</label>
                        <input id="recovery_code" type="text" name="recovery_code" autocomplete="one-time-code" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-institutional-blue focus:border-institutional-blue sm:text-sm">
                    </div>

                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-800 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-colors duration-200">
                        Usar Código de Recuperación
                    </button>
                </form>
                <div class="mt-4 text-center">
                    <button type="button" onclick="toggleRecovery()" class="text-sm text-gray-500 hover:text-institutional-blue underline">
                        Usar la aplicación autenticadora
                    </button>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
