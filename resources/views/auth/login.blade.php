@extends('layouts.auth')

@section('titulo', 'Acceso Institucional')
@section('mensaje_carga', 'Verificando credenciales...')

@section('contenido')
    @php
        // Segundos de bloqueo por exceso de intentos (lo envían bootstrap/app.php y FortifyServiceProvider)
        $segundosBloqueo = (int) session('login_bloqueado', 0);
        $credencialesInvalidas = ! $segundosBloqueo && $errors->any();
    @endphp

    <div class="text-center mb-8">
        <h1 class="text-2xl font-extrabold text-institutional-blue mb-1">NOC Institucional</h1>
        <p class="text-sm text-gray-500">Acceso Seguro al Centro de Operaciones</p>
    </div>

    @if ($segundosBloqueo)
        <div id="avisoBloqueo" class="mb-5 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-md" role="alert">
            <p class="text-sm font-semibold text-amber-800">Demasiados intentos de acceso</p>
            <p class="text-sm text-amber-700 mt-0.5">
                Por seguridad, espera <strong><span id="segundosBloqueo">{{ $segundosBloqueo }}</span> s</strong> antes de volver a intentarlo.
            </p>
        </div>
    @elseif ($credencialesInvalidas)
        <div class="mb-5 bg-red-50 border-l-4 border-red-500 p-4 rounded-md" role="alert">
            <div class="flex items-center">
                <svg class="h-5 w-5 text-red-500 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /> <path d="m15 9-6 6" /> <path d="m9 9 6 6" /></svg>
                <p class="ml-3 text-sm text-red-700">El correo o la contraseña no son correctos.</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" data-con-carga novalidate-off>
        @csrf

        <div class="mb-5">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Correo Institucional</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                   @if(! old('email')) autofocus @endif
                   @if($credencialesInvalidas) aria-invalid="true" @endif
                   class="login-input">
        </div>

        <div class="mb-6">
            @include('auth.partials.campo-clave', [
                'id' => 'password',
                'etiqueta' => 'Contraseña',
                'autofocus' => (bool) old('email'),
            ])
        </div>

        <div class="flex items-center mb-6">
            <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 accent-[#667d93] border-gray-300 rounded">
            <label for="remember_me" class="ml-2 block text-sm text-gray-900">Recordar dispositivo</label>
        </div>

        <button type="submit" id="btnIngresar" class="fancy w-full justify-center" @if($segundosBloqueo) disabled @endif>
            <span class="top-key"></span>
            <span class="text">Ingresar de Forma Segura</span>
            <span class="bottom-key-1"></span>
            <span class="bottom-key-2"></span>
        </button>
    </form>

    <div class="mt-6 border-t border-gray-200 pt-4 text-center">
        <p class="text-xs text-gray-400">Todo intento de acceso es registrado y auditado.</p>
    </div>

    @if ($segundosBloqueo)
        <script>
            (function () {
                var restante = {{ $segundosBloqueo }};
                var span = document.getElementById('segundosBloqueo');
                var boton = document.getElementById('btnIngresar');
                var aviso = document.getElementById('avisoBloqueo');
                var reloj = setInterval(function () {
                    restante--;
                    if (restante <= 0) {
                        clearInterval(reloj);
                        boton.disabled = false;
                        aviso.classList.add('hidden');
                        return;
                    }
                    span.textContent = restante;
                }, 1000);
            })();
        </script>
    @endif
@endsection
