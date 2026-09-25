<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración de 2FA - NOC</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased min-h-screen">

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            <h1 class="text-2xl font-bold text-[#5c8096] mb-6">Seguridad de la Cuenta</h1>
            
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-medium text-gray-900">Autenticación de Dos Factores (2FA)</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Añade seguridad adicional a tu cuenta usando la autenticación de dos factores.
                </p>

                @if(! auth()->user()->two_factor_secret)
                    <div class="mt-5">
                        <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#5c8096] hover:bg-[#4a6b7d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#5c8096]">
                                Habilitar 2FA
                            </button>
                        </form>
                    </div>
                @else
                    <div class="mt-5 bg-green-50 p-4 rounded-md border border-green-200 mb-6">
                        <h4 class="text-green-800 font-medium">La Autenticación de Dos Factores está habilitada.</h4>
                    </div>

                    @if(session('status') == 'two-factor-authentication-enabled')
                        <div class="mt-4 mb-6">
                            <p class="text-sm text-gray-600 mb-4 font-medium">
                                Para terminar de configurar la autenticación de dos factores, escanea el siguiente código QR usando la aplicación autenticadora de tu teléfono o introduce la clave de configuración y proporciona el código OTP generado.
                            </p>
                            
                            <div class="p-4 bg-white border border-gray-200 inline-block rounded-lg shadow-sm">
                                {!! auth()->user()->twoFactorQrCodeSvg() !!}
                            </div>
                            
                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-700">Clave de Configuración: <span class="font-mono text-lg ml-2">{{ decrypt(auth()->user()->two_factor_secret) }}</span></p>
                            </div>
                            
                            <!-- Opcional si fortify.confirm está activo -->
                            <div class="mt-6 border-t pt-4">
                                <p class="text-sm text-gray-600 mb-2">Confirma tu código para activarlo definitivamente:</p>
                                <form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="flex gap-4 items-end">
                                    @csrf
                                    <div>
                                        <label for="code" class="block text-sm font-medium text-gray-700">Código OTP</label>
                                        <input id="code" type="text" name="code" required class="mt-1 block w-48 border-gray-300 rounded-md shadow-sm focus:ring-[#e67e22] focus:border-[#e67e22] sm:text-sm">
                                    </div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#e67e22] hover:bg-[#d67118] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#e67e22]">
                                        Confirmar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <h4 class="text-md font-medium text-gray-900 mb-2">Códigos de Recuperación</h4>
                        <p class="text-sm text-gray-500 mb-4">
                            Guarda estos códigos de recuperación en un gestor de contraseñas seguro. Se pueden usar para recuperar el acceso a tu cuenta si pierdes tu dispositivo de autenticación de dos factores.
                        </p>
                        
                        <div class="bg-gray-100 rounded-lg p-4 font-mono text-sm">
                            @foreach (json_decode(decrypt(auth()->user()->two_factor_recovery_codes), true) as $code)
                                <div>{{ $code }}</div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex gap-4">
                            <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}">
                                @csrf
                                <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-1 rounded">
                                    Regenerar Códigos
                                </button>
                            </form>
                            
                            <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:text-red-900 border border-red-200 px-3 py-1 rounded">
                                    Deshabilitar 2FA
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
            
            <div class="mt-8 flex">
                <a href="{{ url('/') }}" class="text-sm font-medium text-[#5c8096] hover:text-[#4a6b7d]">&larr; Volver al Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
