{{--
    Campo de contraseña con botón mostrar/ocultar, aviso de Bloq Mayús y error en línea.
    Parámetros: $id, $etiqueta, $error (string|null), $autofocus (bool), $autocomplete
--}}
@php
    $error = $error ?? null;
    $autofocus = $autofocus ?? false;
    $autocomplete = $autocomplete ?? 'current-password';
@endphp
<div>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $etiqueta }}</label>
    <div class="relative">
        <input id="{{ $id }}" type="password" name="password" required autocomplete="{{ $autocomplete }}" @if($autofocus) autofocus @endif
               class="login-input con-boton"
               @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        <button type="button" class="btn-ojo" data-alternar-clave="{{ $id }}" aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar / ocultar contraseña">
            <svg data-icono="ver" class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0" /><circle cx="12" cy="12" r="3" /></svg>
            <svg data-icono="ocultar" class="w-5 h-5 hidden" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.97 0 8.9 3.1 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.93 3.2" /><path d="M14.08 14.16a3 3 0 0 1-4.24-4.24" /><path d="M17.48 17.5A10.75 10.75 0 0 1 2.06 12.35a1 1 0 0 1 0-.7 10.9 10.9 0 0 1 4.45-5.15" /><path d="m2 2 20 20" /></svg>
        </button>
    </div>
    <p data-aviso-mayus="{{ $id }}" class="hidden mt-1.5 text-xs font-medium text-amber-700" role="status">
        <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /><path d="M12 9v4" /><path d="M12 17h.01" /></svg>
        Bloq Mayús está activado
    </p>
    @if($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
